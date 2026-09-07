<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Practical / written-response review queue (spec §29–§30, P7).
 * Manual question types (short_text, long_text, practical, file) can't be
 * auto-scored — they land here, get scored against a rubric by a reviewer,
 * and on approval the attempt is re-scored to a final result (P6 finalize).
 * Review is an admin capability, not a separate user type (§29).
 */
class Review_model extends CI_Model
{
    /** Question types that route to human review. */
    public $manual_types = ['short_text', 'long_text', 'practical', 'file'];

    private function manual_where($k = 'q.question_type')
    {
        return $k . " IN ('" . implode("','", $this->manual_types) . "')";
    }

    // ---- queue --------------------------------------------------------------
    /** Reviews to work, newest first. Optional status filter. */
    public function queue($status = '')
    {
        $this->db->select("rv.*, a.title AS assessment_title, s.name AS skill_name,
                           sl.name AS level_name, TRIM(CONCAT(u.first_name,' ',u.last_name)) AS candidate_name, u.email AS candidate_email,
                           att.assessment_id, att.submitted_at, att.score AS attempt_score, att.result AS attempt_result", false)
                 ->from('assessment_reviews rv')
                 ->join('assessment_attempts att', 'att.id = rv.attempt_id')
                 ->join('assessments a', 'a.id = att.assessment_id')
                 ->join('skills s', 's.id = a.skill_id', 'left')
                 ->join('skill_levels sl', 'sl.id = a.skill_level_id', 'left')
                 ->join('users u', 'u.id = att.candidate_id', 'left');
        if ($status !== '' && $status !== 'all') {
            $this->db->where('rv.status', $status);
        }
        $rows = $this->db->order_by("CASE rv.status WHEN 'pending' THEN 0 WHEN 'in_review' THEN 1 ELSE 2 END", '', false)
                         ->order_by('rv.created_at', 'desc')->get()->result_array();
        foreach ($rows as &$r) {
            $r['manual_count'] = $this->count_manual($r['assessment_id']);
        }
        return $rows;
    }

    public function status_counts()
    {
        $out = ['pending' => 0, 'in_review' => 0, 'approved' => 0, 'rejected' => 0, 'needs_revision' => 0];
        foreach ($this->db->select('status, COUNT(*) c')->group_by('status')->get('assessment_reviews')->result_array() as $r) {
            $out[$r['status']] = (int) $r['c'];
        }
        return $out;
    }

    private function count_manual($assessment_id)
    {
        return $this->db->from('assessment_questions q')
                        ->where('q.assessment_id', $assessment_id)
                        ->where_in('q.status', ['approved', 'published'])
                        ->where($this->manual_where(), null, false)
                        ->count_all_results();
    }

    // ---- one review ---------------------------------------------------------
    public function get_review($review_id)
    {
        $rv = $this->db->select("rv.*, att.assessment_id, att.candidate_id, att.sid, att.submitted_at,
                                 att.score AS attempt_score, att.result AS attempt_result,
                                 a.title AS assessment_title, a.pass_score, s.name AS skill_name,
                                 sl.name AS level_name, TRIM(CONCAT(u.first_name,' ',u.last_name)) AS candidate_name, u.email AS candidate_email", false)
                       ->from('assessment_reviews rv')
                       ->join('assessment_attempts att', 'att.id = rv.attempt_id')
                       ->join('assessments a', 'a.id = att.assessment_id')
                       ->join('skills s', 's.id = a.skill_id', 'left')
                       ->join('skill_levels sl', 'sl.id = a.skill_level_id', 'left')
                       ->join('users u', 'u.id = att.candidate_id', 'left')
                       ->where('rv.id', $review_id)->get()->row_array();
        return $rv;
    }

    /** Manual questions for the attempt, each with the candidate's answer, rubric criteria and any saved marks. */
    public function review_items($review)
    {
        $questions = $this->db->from('assessment_questions q')
                              ->where('q.assessment_id', $review['assessment_id'])
                              ->where_in('q.status', ['approved', 'published'])
                              ->where($this->manual_where(), null, false)
                              ->order_by('q.id')->get()->result_array();

        // saved marks for this review, keyed by rubric_id (0 = question-level)
        $saved = [];
        foreach ($this->db->get_where('assessment_review_scores', ['review_id' => $review['id']])->result_array() as $s) {
            $saved[$s['question_id']][$s['rubric_id'] ?: 0] = $s;
        }

        foreach ($questions as &$q) {
            $ans = $this->db->select('answer')->get_where('assessment_attempt_answers',
                        ['attempt_id' => $review['attempt_id'], 'question_id' => $q['id']])->row();
            $decoded = $ans ? (json_decode($ans->answer, true) ?: []) : [];
            $q['response_text'] = $decoded['value'] ?? '';
            $q['rubrics'] = $this->db->order_by('sort_order, id')
                                     ->get_where('assessment_rubrics', ['question_id' => $q['id']])->result_array();
            $q['saved'] = $saved[$q['id']] ?? [];
            $q['submission'] = $this->db->get_where('assessment_submissions',
                        ['attempt_id' => $review['attempt_id'], 'question_id' => $q['id']])->row_array() ?: null;
        }
        return $questions;
    }

    // ---- actions ------------------------------------------------------------
    public function claim($review_id, $reviewer_id)
    {
        $rv = $this->db->get_where('assessment_reviews', ['id' => $review_id])->row_array();
        if ($rv && $rv['status'] === 'pending') {
            $this->db->where('id', $review_id)->update('assessment_reviews', [
                'reviewer_id' => (int) $reviewer_id,
                'status'      => 'in_review',
                'assigned_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Persist rubric marks from the grading form. POST shape:
     *   score[question_id][rubric_id] = n   (rubric_id 0 = whole-question mark)
     *   comment[question_id][rubric_id] = text
     * Replaces this review's existing marks (clean re-grade).
     */
    public function save_scores($review_id, $post)
    {
        $this->db->where('review_id', $review_id)->delete('assessment_review_scores');
        $scores   = (array) ($post['score'] ?? []);
        $comments = (array) ($post['comment'] ?? []);
        foreach ($scores as $qid => $byrubric) {
            foreach ((array) $byrubric as $rid => $val) {
                if ($val === '' || $val === null) {
                    continue;
                }
                $this->db->insert('assessment_review_scores', [
                    'review_id'   => (int) $review_id,
                    'question_id' => (int) $qid,
                    'rubric_id'   => $rid ? (int) $rid : null,
                    'score'       => is_numeric($val) ? (float) $val : 0,
                    'comment'     => $comments[$qid][$rid] ?? null,
                ]);
            }
        }
        if (isset($post['notes'])) {
            $this->db->where('id', $review_id)->update('assessment_reviews', ['notes' => $post['notes']]);
        }
    }

    /** Every manual question has at least one mark recorded for this review. */
    public function is_fully_scored($review)
    {
        $items = $this->review_items($review);
        foreach ($items as $q) {
            $marked = $this->db->where('review_id', $review['id'])->where('question_id', $q['id'])
                               ->count_all_results('assessment_review_scores');
            if ($marked === 0) {
                return false;
            }
        }
        return count($items) > 0;
    }

    /**
     * Finalize the review decision (§29).
     *  approved       → re-score the attempt (P6 finalize combines auto + review marks)
     *  rejected       → attempt invalidated (§18)
     *  needs_revision → sent back; attempt stays pending
     */
    public function decide($review_id, $decision, $reviewer_id)
    {
        $rv = $this->get_review($review_id);
        if (! $rv) {
            return 'no_review';
        }
        $map = ['approve' => 'approved', 'reject' => 'rejected', 'revision' => 'needs_revision'];
        $status = $map[$decision] ?? null;
        if (! $status) {
            return 'bad_decision';
        }

        $this->db->where('id', $review_id)->update('assessment_reviews', [
            'status'      => $status,
            'reviewer_id' => (int) $reviewer_id,
            'reviewed_at' => date('Y-m-d H:i:s'),
        ]);

        if ($status === 'approved') {
            $this->load->model('scoring_model');
            return $this->scoring_model->score_attempt($rv['attempt_id']);
        }
        if ($status === 'rejected') {
            $this->db->where('id', $rv['attempt_id'])->update('assessment_attempts', [
                'result' => 'invalidated', 'needs_review' => 0, 'completed_at' => date('Y-m-d H:i:s'),
            ]);
        }
        // needs_revision leaves the attempt in_progress/needs_review for a re-look.
        return $status;
    }
}
