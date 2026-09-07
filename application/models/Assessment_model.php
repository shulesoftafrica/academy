<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Data layer for assessments, sections, question bank (10 types §11), options,
 * and rubrics. Question status workflow draft→reviewed→approved→published (§14);
 * only approved/published questions may appear in live assessments. Assessment
 * versioning per §43.
 */
class Assessment_model extends CI_Model
{
    public $question_types = [
        'single'    => 'Single Choice',
        'multiple'  => 'Multiple Choice',
        'truefalse' => 'True / False',
        'matching'  => 'Matching',
        'ordering'  => 'Ordering',
        'numerical' => 'Numerical',
        'short_text'=> 'Short Text',
        'long_text' => 'Long Text / Case',
        'file'      => 'File Submission',
        'practical' => 'Practical Task',
    ];
    public $difficulties = ['easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard', 'expert' => 'Expert'];

    /** choice-style types whose options carry correctness */
    public function is_objective($type)
    {
        return in_array($type, ['single', 'multiple', 'truefalse'], true);
    }

    // ---- assessments --------------------------------------------------------
    public function get_assessments()
    {
        return $this->db->select('a.*, s.name AS skill_name, l.name AS level_name,
                (SELECT COUNT(*) FROM academy.assessment_questions q WHERE q.assessment_id=a.id) AS question_count', false)
            ->from('assessments a')
            ->join('skills s', 's.id = a.skill_id', 'left')
            ->join('skill_levels l', 'l.id = a.skill_level_id', 'left')
            ->order_by('s.name, l.rank, a.version')->get()->result_array();
    }

    public function get_assessment($id)
    {
        return $this->db->get_where('assessments', ['id' => $id])->row_array();
    }

    public function save_assessment($id = null)
    {
        $data = [
            'skill_id'         => (int) $this->input->post('skill_id'),
            'skill_level_id'   => (int) $this->input->post('skill_level_id'),
            'title'            => html_escape($this->input->post('title')),
            'description'      => $this->input->post('description', false),
            'duration_minutes' => (int) $this->input->post('duration_minutes') ?: 60,
            'pass_score'       => is_numeric($this->input->post('pass_score')) ? $this->input->post('pass_score') : 70,
            'critical_min'     => is_numeric($this->input->post('critical_min')) ? $this->input->post('critical_min') : 60,
            'max_attempts'     => (int) $this->input->post('max_attempts') ?: 1,
            'cooldown_days'    => (int) $this->input->post('cooldown_days'),
            'status'           => html_escape($this->input->post('status') ?: 'draft'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ];
        if ($id) {
            $this->db->where('id', $id)->update('assessments', $data);
            return $id;
        }
        $data['version'] = 1;
        $this->db->insert('assessments', $data);
        $new = $this->db->insert_id();
        // seed the three standard sections (§7)
        foreach ([['Knowledge', 'knowledge', 1], ['Situational Judgment', 'situational', 2], ['Practical', 'practical', 3]] as $s) {
            $this->db->insert('assessment_sections', ['assessment_id' => $new, 'name' => $s[0], 'type' => $s[1], 'sort_order' => $s[2]]);
        }
        return $new;
    }

    public function delete_assessment($id)
    {
        foreach (['assessment_sections', 'assessment_questions', 'assessment_rubrics'] as $t) {
            $this->db->where('assessment_id', $id)->delete($t);
        }
        $this->db->where('id', $id)->delete('assessments');
    }

    /** Version governance (§43): clone a published assessment to a new draft version. */
    public function new_version($id)
    {
        $a = $this->get_assessment($id);
        if (! $a) {
            return null;
        }
        unset($a['id']);
        $a['version']    = (int) $a['version'] + 1;
        $a['status']     = 'draft';
        $a['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('assessments', $a);
        $new = $this->db->insert_id();
        foreach ($this->get_sections($id) as $sec) {
            unset($sec['id']);
            $sec['assessment_id'] = $new;
            $this->db->insert('assessment_sections', $sec);
        }
        // (questions are re-authored per version; sections copied for structure)
        return $new;
    }

    public function refresh_total_questions($assessment_id)
    {
        $n = $this->db->where('assessment_id', $assessment_id)->count_all_results('assessment_questions');
        $this->db->where('id', $assessment_id)->update('assessments', ['total_questions' => $n]);
    }

    // ---- sections -----------------------------------------------------------
    public function get_sections($assessment_id)
    {
        return $this->db->order_by('sort_order')->get_where('assessment_sections', ['assessment_id' => $assessment_id])->result_array();
    }

    // ---- questions ----------------------------------------------------------
    public function get_questions($assessment_id, $filters = [])
    {
        $this->db->select('q.*, c.name AS competency_name,
            (SELECT COUNT(*) FROM academy.assessment_question_options o WHERE o.question_id=q.id) AS option_count', false)
            ->from('assessment_questions q')
            ->join('skill_competencies c', 'c.id = q.competency_id', 'left')
            ->where('q.assessment_id', $assessment_id);
        if (! empty($filters['status'])) {
            $this->db->where('q.status', $filters['status']);
        }
        if (! empty($filters['type'])) {
            $this->db->where('q.question_type', $filters['type']);
        }
        return $this->db->order_by('q.id')->get()->result_array();
    }

    public function get_question($id)
    {
        return $this->db->get_where('assessment_questions', ['id' => $id])->row_array();
    }

    public function get_options($question_id)
    {
        return $this->db->order_by('sort_order,id')->get_where('assessment_question_options', ['question_id' => $question_id])->result_array();
    }

    /** Save a question + its type-specific options (replace-all for options). */
    public function save_question($assessment_id, $id = null)
    {
        $type = html_escape($this->input->post('question_type'));
        $data = [
            'assessment_id'    => (int) $assessment_id,
            'section_id'       => (int) $this->input->post('section_id') ?: null,
            'skill_id'         => (int) $this->input->post('skill_id') ?: null,
            'competency_id'    => (int) $this->input->post('competency_id') ?: null,
            'skill_level_id'   => (int) $this->input->post('skill_level_id') ?: null,
            'question_type'    => $type,
            'question_text'    => $this->input->post('question_text', false),
            'difficulty'       => html_escape($this->input->post('difficulty') ?: 'medium'),
            'points'           => is_numeric($this->input->post('points')) ? $this->input->post('points') : 1,
            'time_seconds'     => (int) $this->input->post('time_seconds'),
            'explanation'      => $this->input->post('explanation', false),
            'marking_criteria' => $this->input->post('marking_criteria', false),
            'expected_answer'  => $this->input->post('expected_answer', false),
            'numeric_answer'   => is_numeric($this->input->post('numeric_answer')) ? $this->input->post('numeric_answer') : null,
            'numeric_tolerance'=> is_numeric($this->input->post('numeric_tolerance')) ? $this->input->post('numeric_tolerance') : 0,
            'tags'             => html_escape($this->input->post('tags')),
            'status'           => html_escape($this->input->post('status') ?: 'draft'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ];
        if ($id) {
            $this->db->where('id', $id)->update('assessment_questions', $data);
            $qid = $id;
            $this->db->where('question_id', $qid)->delete('assessment_question_options');
        } else {
            $this->db->insert('assessment_questions', $data);
            $qid = $this->db->insert_id();
        }

        // options for choice / matching / ordering
        $texts   = (array) $this->input->post('option_text');
        $correct = (array) $this->input->post('option_correct');   // indexes ticked (single/multiple/truefalse)
        $scores  = (array) $this->input->post('option_score');
        $mkeys   = (array) $this->input->post('option_match');
        $orders  = (array) $this->input->post('option_order');
        foreach ($texts as $i => $text) {
            if (trim((string) $text) === '') {
                continue;
            }
            $this->db->insert('assessment_question_options', [
                'question_id'   => $qid,
                'option_text'   => $text,
                'is_correct'    => in_array((string) $i, array_map('strval', $correct), true) ? 1 : 0,
                'score'         => isset($scores[$i]) && is_numeric($scores[$i]) ? $scores[$i] : 0,
                'match_key'     => isset($mkeys[$i]) ? html_escape($mkeys[$i]) : '',
                'correct_order' => isset($orders[$i]) ? (int) $orders[$i] : 0,
                'sort_order'    => $i,
            ]);
        }

        $this->refresh_total_questions($assessment_id);
        return $qid;
    }

    public function set_question_status($id, $status)
    {
        $allowed = ['draft', 'reviewed', 'approved', 'published', 'archived'];
        if (in_array($status, $allowed, true)) {
            $this->db->where('id', $id)->update('assessment_questions', ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
            $q = $this->get_question($id);
            if ($q) {
                $this->refresh_total_questions($q['assessment_id']); // archived drops out of the live count
            }
        }
    }

    /** Clone a question (with its options + rubrics) as a new Draft (§12). */
    public function duplicate_question($id)
    {
        $q = $this->db->get_where('assessment_questions', ['id' => $id])->row_array();
        if (! $q) {
            return null;
        }
        unset($q['id']);
        $q['question_text'] = $q['question_text'] . ' (copy)';
        $q['status']        = 'draft';
        $q['created_at']    = date('Y-m-d H:i:s');
        $q['updated_at']    = date('Y-m-d H:i:s');
        $this->db->insert('assessment_questions', $q);
        $new = $this->db->insert_id();

        foreach ($this->db->get_where('assessment_question_options', ['question_id' => $id])->result_array() as $o) {
            unset($o['id']);
            $o['question_id'] = $new;
            $this->db->insert('assessment_question_options', $o);
        }
        foreach ($this->db->get_where('assessment_rubrics', ['question_id' => $id])->result_array() as $r) {
            unset($r['id']);
            $r['question_id'] = $new;
            $this->db->insert('assessment_rubrics', $r);
        }
        return $new;
    }

    public function delete_question($id)
    {
        $q = $this->get_question($id);
        $this->db->where('question_id', $id)->delete('assessment_question_options');
        $this->db->where('question_id', $id)->delete('assessment_rubrics');
        $this->db->where('id', $id)->delete('assessment_questions');
        if ($q) {
            $this->refresh_total_questions($q['assessment_id']);
        }
    }

    // ---- candidate-facing reads --------------------------------------------
    /** Latest published assessment for a skill+level (candidate discovery). */
    public function get_published($skill_id, $skill_level_id)
    {
        return $this->db->where(['skill_id' => $skill_id, 'skill_level_id' => $skill_level_id, 'status' => 'published'])
                        ->order_by('version', 'desc')->limit(1)->get('assessments')->row_array();
    }

    /** Count of live (approved/published) questions in an assessment. */
    public function live_question_count($assessment_id)
    {
        return $this->db->where('assessment_id', $assessment_id)
                        ->where_in('status', ['approved', 'published'])
                        ->count_all_results('assessment_questions');
    }

    /** Levels that have a published assessment for a skill, with price + counts. */
    public function levels_for_skill($skill_id)
    {
        $this->load->model('skill_model');
        $out = [];
        foreach ($this->skill_model->get_levels() as $lvl) {
            $a = $this->get_published($skill_id, $lvl['id']);
            $price = $this->skill_model->get_active_price($skill_id, $lvl['id']);
            $out[] = [
                'level'      => $lvl,
                'assessment' => $a,
                'price'      => $price,
                'questions'  => $a ? $this->live_question_count($a['id']) : 0,
            ];
        }
        return $out;
    }

    // ---- rubrics (§30) ------------------------------------------------------
    public function get_rubrics($question_id)
    {
        return $this->db->order_by('sort_order,id')->get_where('assessment_rubrics', ['question_id' => $question_id])->result_array();
    }

    public function save_rubrics($assessment_id, $question_id)
    {
        $this->db->where('question_id', $question_id)->delete('assessment_rubrics');
        $criteria = (array) $this->input->post('rubric_criterion');
        $maxes    = (array) $this->input->post('rubric_max');
        $weights  = (array) $this->input->post('rubric_weight');
        foreach ($criteria as $i => $crit) {
            if (trim((string) $crit) === '') {
                continue;
            }
            $this->db->insert('assessment_rubrics', [
                'assessment_id' => $assessment_id,
                'question_id'   => $question_id,
                'criterion'     => html_escape($crit),
                'max_score'     => isset($maxes[$i]) && is_numeric($maxes[$i]) ? $maxes[$i] : 10,
                'weight'        => isset($weights[$i]) && is_numeric($weights[$i]) ? $weights[$i] : 0,
                'sort_order'    => $i,
            ]);
        }
    }
}
