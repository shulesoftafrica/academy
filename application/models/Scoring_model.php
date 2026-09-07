<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Scores a submitted attempt (§16–§18). Objective questions are auto-scored;
 * manual questions (text/practical/file) wait for a rubric review (§29–§30, P7).
 * Level determination: overall ≥ pass AND no critical competency below the
 * critical floor (§17). Outcomes: passed / failed / lower_level (§18). Writes
 * the candidate_skill_results bridge (§37) and hooks credential issuing (P8).
 */
class Scoring_model extends CI_Model
{
    private $objective = ['single', 'multiple', 'truefalse', 'matching', 'ordering', 'numerical'];

    public function score_attempt($attempt_id)
    {
        $attempt = $this->db->get_where('assessment_attempts', ['id' => $attempt_id])->row_array();
        if (! $attempt) {
            return 'no_attempt';
        }
        $assessment = $this->db->get_where('assessments', ['id' => $attempt['assessment_id']])->row_array();

        $questions = $this->db->where('assessment_id', $assessment['id'])
                              ->where_in('status', ['approved', 'published'])->get('assessment_questions')->result_array();

        $answers = [];
        foreach ($this->db->get_where('assessment_attempt_answers', ['attempt_id' => $attempt_id])->result_array() as $r) {
            $answers[$r['question_id']] = $r;
        }

        $pending_manual = false;
        foreach ($questions as $q) {
            $arow = $answers[$q['id']] ?? null;
            $ans  = $arow ? (json_decode($arow['answer'], true) ?: []) : [];

            if (in_array($q['question_type'], $this->objective, true)) {
                [$score, $max, $correct] = $this->auto_score($q, $ans);
                $this->upsert_answer_score($attempt_id, $q['id'], $q['competency_id'], $score, $max, $correct, 1);
            } else {
                // manual — score comes from an approved review (P7); pending until then.
                // Rubric marks are normalized to the question's points so rubric
                // maxima need not sum to `points` (§30).
                $agg = $this->db->select('SUM(rs.score) AS achieved, SUM(r.max_score) AS rubric_max', false)
                    ->from('assessment_review_scores rs')
                    ->join('assessment_reviews rv', 'rv.id = rs.review_id')
                    ->join('assessment_rubrics r', 'r.id = rs.rubric_id', 'left')
                    ->where('rv.attempt_id', $attempt_id)->where('rs.question_id', $q['id'])
                    ->where('rv.status', 'approved')->get()->row();
                if ($agg && $agg->achieved !== null) {
                    $points = (float) $q['points'];
                    $rmax   = (float) $agg->rubric_max;
                    $score  = $rmax > 0 ? round((float) $agg->achieved / $rmax * $points, 2) : min((float) $agg->achieved, $points);
                    $this->upsert_answer_score($attempt_id, $q['id'], $q['competency_id'], $score, $points, null, 0);
                } else {
                    $pending_manual = true;
                    $this->upsert_answer_score($attempt_id, $q['id'], $q['competency_id'], null, (float) $q['points'], null, 0);
                }
            }
        }

        if ($pending_manual) {
            $this->ensure_review($attempt_id);
            $this->db->where('id', $attempt_id)->update('assessment_attempts', ['needs_review' => 1, 'result' => 'in_progress']);
            return 'pending_review';
        }

        return $this->finalize($attempt, $assessment);
    }

    /** Compute overall + per-competency, determine level, set outcome (§17/§18). */
    public function finalize($attempt, $assessment)
    {
        $attempt_id = $attempt['id'];
        $rows = $this->db->get_where('assessment_attempt_answers', ['attempt_id' => $attempt_id])->result_array();

        $sum = 0; $max = 0; $byc = [];
        foreach ($rows as $r) {
            $s = (float) $r['score']; $m = (float) $r['max_score'];
            $sum += $s; $max += $m;
            $c = $r['competency_id'] ?: 0;
            $byc[$c]['s'] = ($byc[$c]['s'] ?? 0) + $s;
            $byc[$c]['m'] = ($byc[$c]['m'] ?? 0) + $m;
        }
        $overall = $max > 0 ? round($sum / $max * 100, 2) : 0;

        $comp_pct = [];
        foreach ($byc as $cid => $v) {
            if ($cid) {
                $comp_pct[$cid] = $v['m'] > 0 ? round($v['s'] / $v['m'] * 100, 2) : 0;
            }
        }

        // critical competency floor (§17)
        $critical_ok = true;
        $criticals = $this->db->select('id')->get_where('skill_competencies', ['skill_id' => $assessment['skill_id'], 'is_critical' => 1])->result_array();
        foreach ($criticals as $c) {
            if (isset($comp_pct[$c['id']]) && $comp_pct[$c['id']] < (float) $assessment['critical_min']) {
                $critical_ok = false;
            }
        }

        $passed = ($overall >= (float) $assessment['pass_score']) && $critical_ok;
        $result = 'failed';
        $level_awarded = null;

        if ($passed) {
            $result = 'passed';
            $level_awarded = (int) $assessment['skill_level_id'];
        } elseif ($overall >= 50) {
            // demonstrated a lower level (§18) — award the next lower rank if it exists.
            $this_level = $this->db->get_where('skill_levels', ['id' => $assessment['skill_level_id']])->row_array();
            $lower = $this->db->where('rank <', (int) $this_level['rank'])->order_by('rank', 'desc')->limit(1)->get('skill_levels')->row_array();
            if ($lower) {
                $result = 'lower_level';
                $level_awarded = (int) $lower['id'];
            }
        }

        $this->db->where('id', $attempt_id)->update('assessment_attempts', [
            'score'             => $overall,
            'result'            => $result,
            'level_awarded'     => $level_awarded,
            'competency_scores' => json_encode($comp_pct),
            'needs_review'      => 0,
            'completed_at'      => $attempt['completed_at'] ?: date('Y-m-d H:i:s'),
        ]);

        // Results bridge + credential (§37/§19) for a demonstrated level.
        if (in_array($result, ['passed', 'lower_level'], true) && $level_awarded) {
            $result_id = $this->write_result($attempt, $assessment, $level_awarded, $overall, $comp_pct, $result === 'passed' ? 'verified' : 'lower_level');
            if (is_file(APPPATH . 'models/Credential_model.php')) {
                $this->load->model('credential_model');
                $this->credential_model->issue($result_id);
            }
        }
        return $result;
    }

    // ---- auto scoring -------------------------------------------------------
    /** @return array [score, max, is_correct|null] */
    private function auto_score($q, $ans)
    {
        $points = (float) $q['points'];
        $opts = $this->db->get_where('assessment_question_options', ['question_id' => $q['id']])->result_array();

        switch ($q['question_type']) {
            case 'single':
            case 'truefalse':
                $sel = $ans['selected'][0] ?? null;
                $weighted = false;
                $maxOptScore = 0;
                foreach ($opts as $o) { if ((float) $o['score'] > 0) { $weighted = true; } $maxOptScore = max($maxOptScore, (float) $o['score']); }
                foreach ($opts as $o) {
                    if ((int) $o['id'] === (int) $sel) {
                        if ($weighted) { return [(float) $o['score'], $maxOptScore ?: $points, ((int) $o['is_correct'] === 1)]; }
                        return [$o['is_correct'] ? $points : 0, $points, ((int) $o['is_correct'] === 1)];
                    }
                }
                return [0, $weighted ? ($maxOptScore ?: $points) : $points, false];

            case 'multiple':
                $sel = array_map('intval', $ans['selected'] ?? []);
                $correctIds = []; foreach ($opts as $o) { if ($o['is_correct']) { $correctIds[] = (int) $o['id']; } }
                $nc = count($correctIds); if ($nc === 0) { return [0, $points, false]; }
                $hit = count(array_intersect($sel, $correctIds));
                $wrong = count(array_diff($sel, $correctIds));
                $frac = max(0, ($hit - $wrong)) / $nc;
                return [round($points * $frac, 2), $points, ($hit === $nc && $wrong === 0)];

            case 'matching':
                $map = $ans['map'] ?? [];
                $n = count($opts); if ($n === 0) { return [0, $points, false]; }
                $ok = 0; foreach ($opts as $o) { if (isset($map[$o['id']]) && (string) $map[$o['id']] === (string) $o['match_key']) { $ok++; } }
                return [round($points * $ok / $n, 2), $points, ($ok === $n)];

            case 'ordering':
                $ord = $ans['order'] ?? [];
                $n = count($opts); if ($n === 0) { return [0, $points, false]; }
                $ok = 0; foreach ($opts as $o) { if (isset($ord[$o['id']]) && (int) $ord[$o['id']] === (int) $o['correct_order']) { $ok++; } }
                return [round($points * $ok / $n, 2), $points, ($ok === $n)];

            case 'numerical':
                $v = isset($ans['value']) && is_numeric($ans['value']) ? (float) $ans['value'] : null;
                if ($v === null || $q['numeric_answer'] === null) { return [0, $points, false]; }
                $ok = abs($v - (float) $q['numeric_answer']) <= (float) $q['numeric_tolerance'];
                return [$ok ? $points : 0, $points, $ok];
        }
        return [0, $points, false];
    }

    private function upsert_answer_score($attempt_id, $qid, $competency_id, $score, $max, $correct, $auto)
    {
        $data = [
            'score'       => $score,
            'max_score'   => $max,
            'is_correct'  => $correct === null ? null : ($correct ? 1 : 0),
            'auto_scored' => $auto,
            'competency_id' => $competency_id ?: null,
        ];
        $existing = $this->db->get_where('assessment_attempt_answers', ['attempt_id' => $attempt_id, 'question_id' => $qid])->row();
        if ($existing) {
            $this->db->where('id', $existing->id)->update('assessment_attempt_answers', $data);
        } else {
            $data['attempt_id'] = $attempt_id; $data['question_id'] = $qid; $data['answer'] = '';
            $this->db->insert('assessment_attempt_answers', $data);
        }
    }

    private function ensure_review($attempt_id)
    {
        if ($this->db->get_where('assessment_reviews', ['attempt_id' => $attempt_id])->num_rows() === 0) {
            $this->db->insert('assessment_reviews', ['attempt_id' => $attempt_id, 'status' => 'pending', 'created_at' => date('Y-m-d H:i:s')]);
        }
    }

    private function write_result($attempt, $assessment, $level_awarded, $overall, $comp_pct, $status)
    {
        $skill = $this->db->get_where('skills', ['id' => $assessment['skill_id']])->row_array();
        $expires = null;
        if (! empty($skill['validity_months'])) {
            $expires = date('Y-m-d H:i:s', strtotime('+' . (int) $skill['validity_months'] . ' months'));
        }
        $existing = $this->db->get_where('candidate_skill_results', ['attempt_id' => $attempt['id']])->row();
        $data = [
            'candidate_id'      => $attempt['candidate_id'], 'sid' => $attempt['sid'],
            'skill_id'          => $assessment['skill_id'], 'skill_level_id' => $level_awarded,
            'assessment_id'     => $assessment['id'], 'attempt_id' => $attempt['id'],
            'overall_score'     => $overall, 'competency_scores' => json_encode($comp_pct),
            'status'            => $status, 'verified_at' => date('Y-m-d H:i:s'), 'expires_at' => $expires,
        ];
        if ($existing) {
            $this->db->where('id', $existing->id)->update('candidate_skill_results', $data);
            return $existing->id;
        }
        $this->db->insert('candidate_skill_results', $data);
        return $this->db->insert_id();
    }
}
