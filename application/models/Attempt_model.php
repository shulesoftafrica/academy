<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Runtime layer for the assessment flow — payment orders (§4-payment),
 * attempts (§22), answers, scoring hooks. Payment mirrors talent's
 * PaymentService order/fulfil lifecycle.
 */
class Attempt_model extends CI_Model
{
    // ---- payments -----------------------------------------------------------
    public function create_payment($candidate_id, $sid, $assessment, $product)
    {
        $this->db->insert('assessment_payments', [
            'candidate_id' => (int) $candidate_id,
            'sid'          => $sid ? (int) $sid : null,
            'assessment_id'=> (int) $assessment['id'],
            'product_id'   => $product ? (int) $product['id'] : null,
            'amount'       => $product ? $product['price'] : 0,
            'currency'     => $product ? $product['currency'] : 'TZS',
            'status'       => 'pending',
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
        return $this->db->insert_id();
    }

    public function get_payment($id)
    {
        return $this->db->get_where('assessment_payments', ['id' => $id])->row_array();
    }

    public function get_payment_by_invoice($invoice_id)
    {
        return $this->db->get_where('assessment_payments', ['invoice_id' => $invoice_id])->row_array();
    }

    public function update_payment($id, $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', $id)->update('assessment_payments', $data);
    }

    // ---- attempts -----------------------------------------------------------
    public function candidate_attempt_count($candidate_id, $assessment_id)
    {
        return $this->db->where(['candidate_id' => $candidate_id, 'assessment_id' => $assessment_id])
                        ->count_all_results('assessment_attempts');
    }

    /** Existing in-progress attempt for this candidate+assessment (resume). */
    public function active_attempt($candidate_id, $assessment_id)
    {
        return $this->db->where(['candidate_id' => $candidate_id, 'assessment_id' => $assessment_id, 'result' => 'in_progress'])
                        ->order_by('id', 'desc')->limit(1)->get('assessment_attempts')->row_array();
    }

    /** Most recent finished attempt (for cooldown between retakes, §23). */
    public function last_completed_attempt($candidate_id, $assessment_id)
    {
        return $this->db->where(['candidate_id' => $candidate_id, 'assessment_id' => $assessment_id])
                        ->where('result !=', 'in_progress')
                        ->where('completed_at IS NOT NULL', null, false)
                        ->order_by('completed_at', 'desc')->limit(1)->get('assessment_attempts')->row_array();
    }

    /** Create the attempt shell once a payment is confirmed (started in P5). */
    public function create_attempt($payment, $assessment)
    {
        $n = $this->candidate_attempt_count($payment['candidate_id'], $payment['assessment_id']) + 1;
        $this->db->insert('assessment_attempts', [
            'candidate_id'       => (int) $payment['candidate_id'],
            'sid'                => $payment['sid'] ? (int) $payment['sid'] : null,
            'assessment_id'      => (int) $payment['assessment_id'],
            'assessment_version' => (int) ($assessment['version'] ?? 1),
            'payment_id'         => (int) $payment['id'],
            'attempt_number'     => $n,
            'result'             => 'in_progress',
            'created_at'         => date('Y-m-d H:i:s'),
        ]);
        return $this->db->insert_id();
    }

    public function get_attempt($id)
    {
        return $this->db->get_where('assessment_attempts', ['id' => $id])->row_array();
    }

    /** Start the clock on first entry (§27) + capture integrity context (§25). */
    public function start_attempt($attempt, $assessment, $ip, $device)
    {
        if (! empty($attempt['started_at'])) {
            return; // already started — resume
        }
        $expires = date('Y-m-d H:i:s', time() + ((int) $assessment['duration_minutes'] * 60));
        $this->db->where('id', $attempt['id'])->update('assessment_attempts', [
            'started_at' => date('Y-m-d H:i:s'),
            'expires_at' => $expires,
            'ip_address' => substr((string) $ip, 0, 60),
            'device_info'=> substr((string) $device, 0, 255),
        ]);
    }

    /**
     * Live questions for taking — randomized, and WITHOUT any answer key
     * (never ship correctness to the browser, §26). Matching gets a shuffled
     * pool of match targets; ordering gets shuffled items only.
     */
    public function take_questions($assessment_id)
    {
        $qs = $this->db->where('assessment_id', $assessment_id)
                       ->where_in('status', ['approved', 'published'])
                       ->get('assessment_questions')->result_array();
        shuffle($qs);
        foreach ($qs as &$q) {
            $q['options'] = [];
            if (in_array($q['question_type'], ['single', 'multiple', 'truefalse', 'matching', 'ordering'], true)) {
                $opts = $this->db->select('id, option_text')->order_by('id')
                                 ->get_where('assessment_question_options', ['question_id' => $q['id']])->result_array();
                shuffle($opts);
                $q['options'] = $opts;
                if ($q['question_type'] === 'matching') {
                    $keys = $this->db->select('match_key')->get_where('assessment_question_options', ['question_id' => $q['id']])->result_array();
                    $keys = array_values(array_unique(array_filter(array_column($keys, 'match_key'))));
                    shuffle($keys);
                    $q['match_choices'] = $keys;
                }
            }
        }
        unset($q);
        return $qs;
    }

    public function get_saved_answers($attempt_id)
    {
        $out = [];
        foreach ($this->db->get_where('assessment_attempt_answers', ['attempt_id' => $attempt_id])->result_array() as $r) {
            $out[$r['question_id']] = json_decode($r['answer'], true) ?: [];
        }
        return $out;
    }

    /**
     * Upsert answers from a POST (autosave §28 or final submit). Structures:
     *  selected[qid][] = option_ids ; match[qid][option_id] = key ;
     *  order[qid][option_id] = n ; answer[qid] = text/number.
     */
    public function save_answers($attempt_id, $post)
    {
        $built = [];
        foreach ((array) ($post['selected'] ?? []) as $qid => $ids) {
            $built[(int) $qid] = ['selected' => array_map('intval', (array) $ids)];
        }
        foreach ((array) ($post['match'] ?? []) as $qid => $map) {
            $built[(int) $qid] = ['map' => array_map('strval', (array) $map)];
        }
        foreach ((array) ($post['order'] ?? []) as $qid => $ord) {
            $built[(int) $qid] = ['order' => array_map('intval', (array) $ord)];
        }
        foreach ((array) ($post['answer'] ?? []) as $qid => $val) {
            if (trim((string) $val) !== '') {
                $built[(int) $qid] = ['value' => (string) $val];
            }
        }

        foreach ($built as $qid => $ans) {
            $comp = $this->db->select('competency_id')->get_where('assessment_questions', ['id' => $qid])->row();
            $existing = $this->db->get_where('assessment_attempt_answers', ['attempt_id' => $attempt_id, 'question_id' => $qid])->row();
            $data = ['answer' => json_encode($ans), 'competency_id' => $comp->competency_id ?? null, 'answered_at' => date('Y-m-d H:i:s')];
            if ($existing) {
                $this->db->where('id', $existing->id)->set('changes_count', 'changes_count + 1', false)->update('assessment_attempt_answers', $data);
            } else {
                $data['attempt_id']  = $attempt_id;
                $data['question_id'] = $qid;
                $this->db->insert('assessment_attempt_answers', $data);
            }
        }
        return count($built);
    }

    /** Finalize submission (scoring is applied by Scoring_model in P6). */
    public function submit_attempt($attempt_id)
    {
        $this->db->where('id', $attempt_id)->update('assessment_attempts', [
            'submitted_at' => date('Y-m-d H:i:s'),
            'completed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // ---- practical file submissions (§7 C/D, §11 type 9) --------------------
    /** One file per question: replace any prior submission (and its file). */
    public function save_submission($attempt_id, $question_id, $candidate_id, $file)
    {
        $old = $this->db->get_where('assessment_submissions', ['attempt_id' => $attempt_id, 'question_id' => $question_id])->row_array();
        if ($old) {
            if (! empty($old['file_path']) && is_file(FCPATH . $old['file_path'])) {
                @unlink(FCPATH . $old['file_path']);
            }
            $this->db->where('id', $old['id'])->delete('assessment_submissions');
        }
        $this->db->insert('assessment_submissions', [
            'attempt_id'    => (int) $attempt_id,
            'question_id'   => (int) $question_id,
            'candidate_id'  => (int) $candidate_id,
            'file_path'     => $file['file_path'],
            'original_name' => $file['original_name'],
            'mime_type'     => $file['mime_type'],
            'file_size'     => (int) $file['file_size'],
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
        $sid = $this->db->insert_id();

        // Record an answer row so the item counts as answered (manual → review).
        $comp = $this->db->select('competency_id')->get_where('assessment_questions', ['id' => $question_id])->row();
        $data = ['answer' => json_encode(['file_submission_id' => $sid, 'file_name' => $file['original_name']]),
                 'competency_id' => $comp->competency_id ?? null, 'answered_at' => date('Y-m-d H:i:s')];
        $existing = $this->db->get_where('assessment_attempt_answers', ['attempt_id' => $attempt_id, 'question_id' => $question_id])->row();
        if ($existing) {
            $this->db->where('id', $existing->id)->update('assessment_attempt_answers', $data);
        } else {
            $data['attempt_id'] = $attempt_id; $data['question_id'] = $question_id;
            $this->db->insert('assessment_attempt_answers', $data);
        }
        return $sid;
    }

    public function get_submission($id)
    {
        return $this->db->get_where('assessment_submissions', ['id' => $id])->row_array();
    }

    /** Submissions for an attempt keyed by question_id (for the take screen). */
    public function submissions_for_attempt($attempt_id)
    {
        $out = [];
        foreach ($this->db->get_where('assessment_submissions', ['attempt_id' => $attempt_id])->result_array() as $r) {
            $out[$r['question_id']] = $r;
        }
        return $out;
    }
}
