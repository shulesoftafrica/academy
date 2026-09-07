<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Candidate-facing Skills experience (spec §2, §24). Landing → explore →
 * skill → level/assessment detail (what it measures, duration, questions,
 * price, passing standard) → pay (P4).
 */
class Skills extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        date_default_timezone_set(get_settings('timezone'));
        $this->load->database();
        $this->load->library('session');
        $this->user_model->check_session_data();
        $this->load->model('skill_model');
        $this->load->model('assessment_model');
        $this->load->model('attempt_model');
    }

    private function render($page_name, $page_data = [])
    {
        $page_data['page_name'] = $page_name;
        $this->load->view('frontend/' . get_frontend_settings('theme') . '/index', $page_data);
    }

    /** Landing page — hero + categories + featured skills (§2). */
    public function index()
    {
        // NB: the LMS theme header defines a global $categories / $skills (course
        // categories for the mega-menu). Use distinct names here so the theme
        // doesn't clobber the skill data in this page.
        $this->render('skills_landing', [
            'page_title'       => get_phrase('Skills'),
            'skill_categories' => $this->skill_model->get_categories(),
            'featured_skills'  => $this->skill_model->get_skills(true),
        ]);
    }

    /** Explore all skills, optionally filtered by category slug. */
    public function explore($category_slug = '')
    {
        $skills = $this->skill_model->get_skills(true);
        if ($category_slug) {
            $cat = null;
            foreach ($this->skill_model->get_categories() as $c) {
                if ($c['slug'] === $category_slug) { $cat = $c; break; }
            }
            if ($cat) {
                $skills = array_values(array_filter($skills, fn ($s) => $s['category_id'] == $cat['id']));
            }
        }
        // Distinct names so the LMS theme's global $categories/$skills (course
        // mega-menu) don't clobber the skill data in this page.
        $this->render('skills_explore', [
            'page_title'       => get_phrase('Explore Skills'),
            'catalog_skills'   => $skills,
            'skill_categories' => $this->skill_model->get_categories(),
            'active_category'  => $category_slug,
        ]);
    }

    /** Skill detail — the 5 levels, prices and which are available (§4/§5). */
    public function skill($slug = '')
    {
        $skill = $this->skill_model->get_skill_by_slug($slug);
        if (! $skill) {
            show_404();
        }
        $this->render('skill_detail', [
            'page_title'   => $skill['name'],
            'skill'        => $skill,
            'levels'       => $this->assessment_model->levels_for_skill($skill['id']),
            'competencies' => $this->skill_model->get_competencies($skill['id']),
        ]);
    }

    /** Assessment detail before payment (§24 steps 1–4). */
    public function assessment($skill_slug = '', $level_slug = '')
    {
        $skill = $this->skill_model->get_skill_by_slug($skill_slug);
        $level = null;
        foreach ($this->skill_model->get_levels() as $l) {
            if ($l['slug'] === $level_slug) { $level = $l; break; }
        }
        if (! $skill || ! $level) {
            show_404();
        }
        $assessment = $this->assessment_model->get_published($skill['id'], $level['id']);
        $price      = $this->skill_model->get_active_price($skill['id'], $level['id']);
        $this->render('assessment_detail', [
            'page_title'   => $skill['name'] . ' — ' . $level['name'],
            'skill'        => $skill,
            'level'        => $level,
            'assessment'   => $assessment,
            'price'        => $price,
            'questions'    => $assessment ? $this->assessment_model->live_question_count($assessment['id']) : 0,
            'competencies' => $this->skill_model->get_competencies($skill['id']),
        ]);
    }

    /**
     * Checkout — create the order + billing invoice (§4-payment, via the same
     * Safaribank billing platform talent uses). Free assessments start at once.
     */
    public function checkout($skill_slug = '', $level_slug = '')
    {
        if (! $this->session->userdata('user_id')) {
            redirect(site_url('login'), 'refresh');
        }
        $skill = $this->skill_model->get_skill_by_slug($skill_slug);
        $level = null;
        foreach ($this->skill_model->get_levels() as $l) {
            if ($l['slug'] === $level_slug) { $level = $l; break; }
        }
        if (! $skill || ! $level) { show_404(); }
        $assessment = $this->assessment_model->get_published($skill['id'], $level['id']);
        if (! $assessment) { show_404(); }

        $cid = $this->session->userdata('user_id');
        $user = $this->db->get_where('users', ['id' => $cid])->row_array();

        // Resume an existing in-progress attempt instead of paying again.
        $active = $this->attempt_model->active_attempt($cid, $assessment['id']);
        if ($active) {
            redirect(site_url('skills/take/' . $active['id']), 'refresh');
        }
        // Attempt limit + cooldown between retakes (§23).
        $prior = $this->attempt_model->candidate_attempt_count($cid, $assessment['id']);
        if ($prior >= (int) $assessment['max_attempts']) {
            $this->session->set_flashdata('error_message', get_phrase('You have used all your attempts for this assessment.'));
            redirect(site_url('skills/assessment/' . $skill_slug . '/' . $level_slug), 'refresh');
        }
        if ($prior > 0 && (int) $assessment['cooldown_days'] > 0) {
            $last = $this->attempt_model->last_completed_attempt($cid, $assessment['id']);
            if ($last && ! empty($last['completed_at'])) {
                $ready = strtotime($last['completed_at']) + (int) $assessment['cooldown_days'] * 86400;
                if (time() < $ready) {
                    $days = max(1, (int) ceil(($ready - time()) / 86400));
                    $this->session->set_flashdata('error_message', get_phrase('You can retake this assessment in') . ' ' . $days . ' ' . get_phrase($days === 1 ? 'day' : 'days') . '.');
                    redirect(site_url('skills/assessment/' . $skill_slug . '/' . $level_slug), 'refresh');
                }
            }
        }

        $product = $this->skill_model->get_active_price($skill['id'], $level['id']);
        // Retakes use the configured retake price when one is set (§23).
        if ($product && $prior > 0 && isset($product['retake_price']) && (float) $product['retake_price'] > 0) {
            $product['price'] = $product['retake_price'];
        }
        $payment = $this->attempt_model->create_payment($cid, $user['sid'] ?? null, $assessment, $product);

        // Free assessment → confirm immediately.
        if (! $product || (float) $product['price'] <= 0) {
            $this->attempt_model->update_payment($payment, ['status' => 'paid', 'paid_at' => date('Y-m-d H:i:s')]);
            $attempt = $this->attempt_model->create_attempt($this->attempt_model->get_payment($payment), $assessment);
            redirect(site_url('skills/take/' . $attempt), 'refresh');
        }

        // Paid → render the checkout page immediately. ALL billing calls (create
        // invoice + fetch payment options) happen in the async checkout_gateways
        // endpoint, so this page never blocks on the slow external billing API.
        $this->render('assessment_checkout', [
            'page_title' => get_phrase('Checkout'),
            'skill' => $skill, 'level' => $level, 'assessment' => $assessment,
            'product' => $product, 'payment_id' => $payment,
        ]);
    }

    /**
     * AJAX — does all the slow billing work for a pending payment: creates the
     * invoice on the ShuleSoft billing platform (if not yet created) and fetches
     * the payment options (UCN / Stripe / Flutterwave). Kept off the checkout
     * page load so the page is instant; results are cached on the payment row.
     */
    public function checkout_gateways($payment_id = '')
    {
        if (! $this->session->userdata('user_id')) {
            $this->output->set_status_header(401);
            return;
        }
        $payment = $this->attempt_model->get_payment($payment_id);
        if (! $payment || $payment['candidate_id'] != $this->session->userdata('user_id')) {
            $this->output->set_status_header(404);
            return;
        }
        $this->output->set_content_type('application/json');

        // Cached from a previous fetch → return instantly.
        if ((int) $payment['gateways_fetched'] === 1) {
            $this->output->set_output(json_encode([
                'ok' => true, 'invoice_id' => $payment['invoice_id'] ?: null,
                'ucn' => $payment['ucn'] ?: null,
                'stripe_link' => $payment['stripe_link'] ?: null,
                'flutterwave_link' => $payment['flutterwave_link'] ?: null,
            ]));
            return;
        }

        @ini_set('max_execution_time', '150');
        @set_time_limit(150);
        $this->load->library('billing_client');

        // 1) Create the invoice if we don't have one yet.
        if (empty($payment['invoice_id'])) {
            $assessment = $this->assessment_model->get_assessment($payment['assessment_id']);
            $skill = $this->skill_model->get_skill($assessment['skill_id']);
            $level = null;
            foreach ($this->skill_model->get_levels() as $l) {
                if ($l['id'] == $assessment['skill_level_id']) { $level = $l; break; }
            }
            $user = $this->db->get_where('users', ['id' => $payment['candidate_id']])->row_array();
            $plan = $this->billing_client->get_assessment_price_plan((string) $payment['currency']);
            $customer = [
                'name'  => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'ShuleSoft Candidate',
                'email' => $user['email'] ?? '',
                'phone' => $user['phone'] ?? '',
            ];
            $inv = $this->billing_client->create_invoice($customer, $plan, (float) $payment['amount'],
                $skill['name'] . ' — ' . ($level['name'] ?? '') . ' assessment', $payment['currency']);
            if (! $inv['success']) {
                $this->attempt_model->update_payment($payment_id, ['status' => 'failed']);
                $this->output->set_output(json_encode(['ok' => false, 'error' => 'invoice_failed', 'detail' => $inv['error']]));
                return;
            }
            $this->attempt_model->update_payment($payment_id, ['invoice_id' => $inv['invoice_id'], 'status' => 'awaiting']);
            $payment['invoice_id'] = $inv['invoice_id'];
        }

        // 2) Fetch the payment options for that invoice.
        $g = $this->billing_client->get_payment_gateways($payment['invoice_id']);
        $this->attempt_model->update_payment($payment_id, [
            'ucn' => $g['ucn'] ?: '', 'stripe_link' => $g['stripe_link'] ?: '',
            'flutterwave_link' => $g['flutterwave_link'] ?: '', 'gateways_fetched' => 1,
        ]);
        $this->output->set_output(json_encode(['ok' => true, 'invoice_id' => $payment['invoice_id']] + $g));
    }

    /** Status page — poll after paying; starts the attempt once confirmed. */
    public function checkout_status($payment_id = '')
    {
        if (! $this->session->userdata('user_id')) {
            redirect(site_url('login'), 'refresh');
        }
        $payment = $this->attempt_model->get_payment($payment_id);
        if (! $payment || $payment['candidate_id'] != $this->session->userdata('user_id')) { show_404(); }

        if ($payment['status'] === 'paid') {
            $active = $this->attempt_model->active_attempt($payment['candidate_id'], $payment['assessment_id']);
            if (! $active) {
                $active_id = $this->attempt_model->create_attempt($payment, $this->assessment_model->get_assessment($payment['assessment_id']));
            } else {
                $active_id = $active['id'];
            }
            redirect(site_url('skills/take/' . $active_id), 'refresh');
        }
        $this->render('assessment_checkout_status', ['page_title' => get_phrase('Payment status'), 'payment' => $payment]);
    }

    /** Take the assessment — timed, autosaving, server-delivered (§25–§28). */
    public function take($attempt_id = '')
    {
        $attempt = $this->guard_attempt($attempt_id);
        $assessment = $this->assessment_model->get_assessment($attempt['assessment_id']);

        // Already finished → go to result.
        if (! empty($attempt['submitted_at']) || $attempt['result'] !== 'in_progress') {
            redirect(site_url('skills/result/' . $attempt_id), 'refresh');
        }
        // Start clock / capture integrity on first entry.
        $this->attempt_model->start_attempt($attempt, $assessment, $this->input->ip_address(), $this->input->user_agent());
        $attempt = $this->attempt_model->get_attempt($attempt_id);

        // Time up → auto-submit (§27).
        if (! empty($attempt['expires_at']) && strtotime($attempt['expires_at']) < time()) {
            $this->attempt_model->submit_attempt($attempt_id);
            $this->_maybe_score($attempt_id);
            redirect(site_url('skills/result/' . $attempt_id), 'refresh');
        }

        $skill = $this->skill_model->get_skill($assessment['skill_id']);
        $this->render('assessment_take', [
            'page_title'  => $assessment['title'],
            'attempt'     => $attempt,
            'assessment'  => $assessment,
            'skill'       => $skill,
            'questions'   => $this->attempt_model->take_questions($assessment['id']),
            'saved'       => $this->attempt_model->get_saved_answers($attempt_id),
            'submissions' => $this->attempt_model->submissions_for_attempt($attempt_id),
            'types'       => $this->assessment_model->question_types,
        ]);
    }

    /**
     * Upload a practical artifact for a file/practical question (§7 C/D, §11).
     * One file per question — re-uploading replaces the previous one. AJAX.
     */
    public function upload_file($attempt_id = '', $question_id = '')
    {
        $attempt = $this->guard_attempt($attempt_id, true);
        $this->output->set_content_type('application/json');
        if (! empty($attempt['submitted_at']) || $attempt['result'] !== 'in_progress') {
            $this->output->set_status_header(409)->set_output(json_encode(['ok' => false, 'error' => 'closed']));
            return;
        }
        $q = $this->db->get_where('assessment_questions', ['id' => $question_id, 'assessment_id' => $attempt['assessment_id']])->row_array();
        if (! $q || ! in_array($q['question_type'], ['file', 'practical'], true)) {
            $this->output->set_status_header(400)->set_output(json_encode(['ok' => false, 'error' => 'bad_question']));
            return;
        }

        $config = [
            'upload_path'      => FCPATH . 'uploads/assessment_submissions/',
            'allowed_types'    => 'pdf|doc|docx|xls|xlsx|csv|ppt|pptx|txt|png|jpg|jpeg|zip',
            'max_size'         => 10240, // 10 MB
            'file_name'        => 'sub_' . (int) $attempt_id . '_' . (int) $question_id . '_' . substr(md5(uniqid('', true)), 0, 12),
            'file_ext_tolower' => true,
        ];
        $this->load->library('upload', $config);
        if (! $this->upload->do_upload('file')) {
            $this->output->set_status_header(422)->set_output(json_encode(['ok' => false, 'error' => trim(strip_tags($this->upload->display_errors('', '')))]));
            return;
        }
        $u = $this->upload->data();
        // CI overwrites orig_name when a custom file_name is configured, so take
        // the real client filename from $_FILES for display.
        $client_name = isset($_FILES['file']['name']) && $_FILES['file']['name'] !== ''
            ? $_FILES['file']['name'] : ($u['orig_name'] ?: $u['file_name']);
        $client_name = substr(preg_replace('/[\\x00-\\x1f\\/\\\\]+/', '_', $client_name), 0, 200);
        $sid = $this->attempt_model->save_submission($attempt_id, $question_id, $this->session->userdata('user_id'), [
            'file_path'     => 'uploads/assessment_submissions/' . $u['file_name'],
            'original_name' => $client_name,
            'mime_type'     => $u['file_type'],
            'file_size'     => (int) round(((float) $u['file_size']) * 1024), // KB → bytes
        ]);
        $this->output->set_output(json_encode([
            'ok' => true, 'id' => $sid,
            'name' => $client_name,
            'download' => site_url('skills/submission_file/' . $sid),
        ]));
    }

    /** Stream a submitted file to its owner (candidate) or an admin reviewer. */
    public function submission_file($id = '')
    {
        $s = $this->attempt_model->get_submission($id);
        if (! $s) {
            show_404();
        }
        $is_owner = $this->session->userdata('user_id') && $s['candidate_id'] == $this->session->userdata('user_id');
        $is_admin = $this->session->userdata('admin_login') == true;
        if (! $is_owner && ! $is_admin) {
            show_404();
        }
        $path = FCPATH . $s['file_path'];
        if (! is_file($path)) {
            show_404();
        }
        $this->load->helper('download');
        force_download($s['original_name'] ?: basename($path), file_get_contents($path));
    }

    /** Autosave endpoint (AJAX) — returns JSON. */
    public function save_progress($attempt_id = '')
    {
        $attempt = $this->guard_attempt($attempt_id, true);
        if (empty($attempt['submitted_at']) && $attempt['result'] === 'in_progress') {
            $n = $this->attempt_model->save_answers($attempt_id, $this->input->post());
            $this->output->set_content_type('application/json')->set_output(json_encode(['saved' => $n, 'ok' => true]));
            return;
        }
        $this->output->set_content_type('application/json')->set_output(json_encode(['ok' => false]));
    }

    /** Final submit. */
    public function submit($attempt_id = '')
    {
        $attempt = $this->guard_attempt($attempt_id);
        if (empty($attempt['submitted_at']) && $attempt['result'] === 'in_progress') {
            $this->attempt_model->save_answers($attempt_id, $this->input->post());
            $this->attempt_model->submit_attempt($attempt_id);
            $this->_maybe_score($attempt_id);
        }
        redirect(site_url('skills/result/' . $attempt_id), 'refresh');
    }

    /** Result page (scoring/credential filled by P6/P8). */
    public function result($attempt_id = '')
    {
        $attempt = $this->guard_attempt($attempt_id);
        $assessment = $this->assessment_model->get_assessment($attempt['assessment_id']);
        $skill = $this->skill_model->get_skill($assessment['skill_id']);
        $this->render('assessment_result', [
            'page_title' => get_phrase('Your result'),
            'attempt'    => $attempt,
            'assessment' => $assessment,
            'skill'      => $skill,
            'levels'     => $this->skill_model->get_levels(),
        ]);
    }

    /** Apply scoring if the P6 Scoring_model is present (no-op until then). */
    private function _maybe_score($attempt_id)
    {
        if (is_file(APPPATH . 'models/Scoring_model.php')) {
            $this->load->model('scoring_model');
            $this->scoring_model->score_attempt($attempt_id);
        }
    }

    /** Load an attempt owned by the current candidate, else 404/redirect. */
    private function guard_attempt($attempt_id, $ajax = false)
    {
        if (! $this->session->userdata('user_id')) {
            if ($ajax) { $this->output->set_status_header(401); exit; }
            redirect(site_url('login'), 'refresh');
        }
        $attempt = $this->attempt_model->get_attempt($attempt_id);
        if (! $attempt || $attempt['candidate_id'] != $this->session->userdata('user_id')) {
            show_404();
        }
        // must be paid
        $pay = $attempt['payment_id'] ? $this->attempt_model->get_payment($attempt['payment_id']) : null;
        if ($pay && $pay['status'] !== 'paid') {
            redirect(site_url('skills/my_assessments'), 'refresh');
        }
        return $attempt;
    }

    /** Candidate's verified skills / results (filled by later phases). */
    public function my_skills()
    {
        if (! $this->session->userdata('user_id')) {
            redirect(site_url('login'), 'refresh');
        }
        $cid = $this->session->userdata('user_id');
        $results = $this->db->select('r.*, s.name AS skill_name, s.slug AS skill_slug, l.name AS level_name,
                c.credential_number, c.verification_token, c.expires_at AS cred_expires, c.status AS cred_status', false)
            ->from('candidate_skill_results r')
            ->join('skills s', 's.id = r.skill_id', 'left')
            ->join('skill_levels l', 'l.id = r.skill_level_id', 'left')
            ->join('skill_credentials c', 'c.id = r.credential_id', 'left')
            ->where('r.candidate_id', $cid)->order_by('r.id', 'desc')->get()->result_array();
        $this->load->model('credential_model');
        foreach ($results as &$r) {
            $r['cred_effective'] = $r['credential_number']
                ? $this->credential_model->effective_status(['status' => $r['cred_status'], 'expires_at' => $r['cred_expires']])
                : null;
        }
        $this->render('my_skills', ['page_title' => get_phrase('My Skills'), 'results' => $results]);
    }

    /**
     * My Skill Development (§45) — for each skill the candidate has engaged with:
     * current demonstrated level, the next target level, per-competency gap bars
     * from their latest attempt, and the next-step CTA. Ties Courses → Skills →
     * Assessment → Credential together.
     */
    public function my_development()
    {
        if (! $this->session->userdata('user_id')) {
            redirect(site_url('login'), 'refresh');
        }
        $cid = (int) $this->session->userdata('user_id');
        $levels = $this->skill_model->get_levels(); // all 5, ranked

        $skills = $this->db->query(
            "SELECT DISTINCT s.id, s.name, s.slug
             FROM skills s
             JOIN assessments a ON a.skill_id = s.id
             JOIN assessment_attempts at ON at.assessment_id = a.id
             WHERE at.candidate_id = ?
             ORDER BY s.name",
            [$cid]
        )->result_array();

        foreach ($skills as &$sk) {
            // best demonstrated level (highest rank among passed/lower-level results)
            $best = $this->db->query(
                "SELECT r.skill_level_id, r.overall_score, sl.rank, sl.name
                 FROM candidate_skill_results r JOIN skill_levels sl ON sl.id = r.skill_level_id
                 WHERE r.candidate_id = ? AND r.skill_id = ? AND r.status IN ('verified','lower_level')
                 ORDER BY sl.rank DESC, r.overall_score DESC LIMIT 1",
                [$cid, $sk['id']]
            )->row_array();
            $sk['current_rank'] = $best ? (int) $best['rank'] : 0;
            $sk['current_name'] = $best['name'] ?? null;
            $sk['best_score']   = $best['overall_score'] ?? null;

            // next target: lowest level above current that has a published assessment
            $target = null;
            foreach ($this->assessment_model->levels_for_skill($sk['id']) as $l) {
                if (empty($l['assessment'])) {
                    continue; // can only verify a level that has a published assessment
                }
                $rank = (int) $l['level']['rank'];
                if ($rank > $sk['current_rank'] && ($target === null || $rank < (int) $target['rank'])) {
                    $target = $l['level'];
                }
            }
            $sk['target'] = $target;

            // per-competency attainment from the latest finished attempt
            $att = $this->db->query(
                "SELECT at.id FROM assessment_attempts at JOIN assessments a ON a.id = at.assessment_id
                 WHERE at.candidate_id = ? AND a.skill_id = ? AND at.result != 'in_progress'
                 ORDER BY at.completed_at DESC NULLS LAST, at.id DESC LIMIT 1",
                [$cid, $sk['id']]
            )->row();
            $sk['competencies'] = [];
            if ($att) {
                $sk['competencies'] = $this->db->query(
                    "SELECT c.name,
                            CASE WHEN SUM(aa.max_score) > 0 THEN ROUND(SUM(aa.score) / SUM(aa.max_score) * 100) ELSE NULL END AS pct
                     FROM assessment_attempt_answers aa JOIN skill_competencies c ON c.id = aa.competency_id
                     WHERE aa.attempt_id = ? AND aa.competency_id IS NOT NULL AND aa.score IS NOT NULL
                     GROUP BY c.id, c.name ORDER BY c.name",
                    [$att->id]
                )->result_array();
            }
        }
        unset($sk);

        $this->render('my_development', [
            'page_title' => get_phrase('My Skill Development'),
            'skills'     => $skills,
            'levels'     => $levels,
        ]);
    }

    /**
     * Public credential verification (§19/§46). No login. Shows only
     * skill / level / holder / issue date / status — never questions or answers.
     */
    public function verify($token = '')
    {
        $this->load->model('credential_model');
        $credential = $token ? $this->credential_model->verify_by_token($token) : null;
        $this->render('credential_verify', [
            'page_title' => get_phrase('Verify credential'),
            'credential' => $credential,
        ]);
    }

    /** Candidate's assessment attempts. */
    public function my_assessments()
    {
        if (! $this->session->userdata('user_id')) {
            redirect(site_url('login'), 'refresh');
        }
        $cid = $this->session->userdata('user_id');
        $attempts = $this->db->select('at.*, a.title, s.name AS skill_name, l.name AS level_name')
            ->from('assessment_attempts at')
            ->join('assessments a', 'a.id = at.assessment_id', 'left')
            ->join('skills s', 's.id = a.skill_id', 'left')
            ->join('skill_levels l', 'l.id = a.skill_level_id', 'left')
            ->where('at.candidate_id', $cid)->order_by('at.id', 'desc')->get()->result_array();
        $this->render('my_assessments', ['page_title' => get_phrase('My Assessments'), 'attempts' => $attempts]);
    }
}
