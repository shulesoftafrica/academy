<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Admin — Skills Assessment Management (spec §39–§40).
 * Renders through the standard backend layout (views/backend/admin/*).
 */
class Skill_admin extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        date_default_timezone_set(get_settings('timezone'));
        $this->load->database();
        $this->load->library('session');
        $this->user_model->check_session_data('admin');
        $this->load->model('skill_model');
        $this->load->model('assessment_model');
        $this->load->model('review_model');
        $this->load->model('credential_model');
    }

    private function guard()
    {
        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'), 'refresh');
        }
    }

    private function render($page_name, $page_data = [])
    {
        $page_data['page_name']  = $page_name;
        $page_data['page_title'] = $page_data['page_title'] ?? get_phrase('skills_assessment');
        $this->load->view('backend/index', $page_data);
    }

    public function index()
    {
        $this->dashboard();
    }

    public function dashboard()
    {
        $this->guard();
        $this->render('skills_dashboard', ['metrics' => $this->skill_model->dashboard_metrics()]);
    }

    // ---- analytics & QC (§41–§42) -------------------------------------------
    public function analytics($assessment_id = '')
    {
        $this->guard();
        if ($assessment_id) {
            $this->render('assessment_quality', [
                'page_title' => get_phrase('Question Quality'),
                'assessment' => $this->assessment_model->get_assessment($assessment_id),
                'questions'  => $this->skill_model->question_quality($assessment_id),
                'types'      => $this->assessment_model->question_types,
            ]);
            return;
        }
        $this->render('skills_analytics', [
            'page_title'   => get_phrase('Analytics'),
            'metrics'      => $this->skill_model->dashboard_metrics(),
            'assessments'  => $this->skill_model->assessment_analytics(),
            'competencies' => $this->skill_model->competency_analytics(),
        ]);
    }

    // ---- categories ---------------------------------------------------------
    public function categories($action = '', $id = '')
    {
        $this->guard();
        if ($action === 'save') {
            $this->skill_model->save_category($this->input->post('id') ?: null);
            $this->session->set_flashdata('flash_message', get_phrase('saved_successfully'));
            redirect(site_url('skill_admin/categories'), 'refresh');
        } elseif ($action === 'delete' && $id) {
            $this->skill_model->delete_category($id);
            $this->session->set_flashdata('flash_message', get_phrase('data_deleted'));
            redirect(site_url('skill_admin/categories'), 'refresh');
        }
        $this->render('skill_categories', [
            'categories' => $this->skill_model->get_categories(),
            'edit'       => $id ? $this->skill_model->get_category($id) : null,
        ]);
    }

    // ---- skills -------------------------------------------------------------
    public function skills($action = '', $id = '')
    {
        $this->guard();
        if ($action === 'delete' && $id) {
            $this->skill_model->delete_skill($id);
            $this->session->set_flashdata('flash_message', get_phrase('data_deleted'));
            redirect(site_url('skill_admin/skills'), 'refresh');
        }
        $this->render('skills_list', ['skills' => $this->skill_model->get_skills()]);
    }

    public function skill_form($id = '')
    {
        $this->guard();
        if ($this->input->method() === 'post') {
            $new_id = $this->skill_model->save_skill($this->input->post('id') ?: null);
            $this->session->set_flashdata('flash_message', get_phrase('saved_successfully'));
            redirect(site_url('skill_admin/competencies/' . $new_id), 'refresh');
        }
        $this->render('skill_form', [
            'skill'      => $id ? $this->skill_model->get_skill($id) : null,
            'categories' => $this->skill_model->get_categories(),
        ]);
    }

    // ---- competencies + level standards (§9/§17) ----------------------------
    public function competencies($skill_id = '', $action = '', $id = '')
    {
        $this->guard();
        if (! $skill_id) {
            redirect(site_url('skill_admin/skills'), 'refresh');
        }
        if ($action === 'save') {
            $this->skill_model->save_competency($skill_id, $this->input->post('id') ?: null);
            $this->session->set_flashdata('flash_message', get_phrase('saved_successfully'));
            redirect(site_url('skill_admin/competencies/' . $skill_id), 'refresh');
        } elseif ($action === 'delete' && $id) {
            $this->skill_model->delete_competency($id);
            $this->session->set_flashdata('flash_message', get_phrase('data_deleted'));
            redirect(site_url('skill_admin/competencies/' . $skill_id), 'refresh');
        } elseif ($action === 'standards') {
            // save per-competency, per-level minimum scores
            $competency_id = $this->input->post('competency_id');
            foreach ((array) $this->input->post('minimum_score') as $level_id => $score) {
                $this->skill_model->save_competency_standard($competency_id, $level_id, $score);
            }
            $this->session->set_flashdata('flash_message', get_phrase('saved_successfully'));
            redirect(site_url('skill_admin/competencies/' . $skill_id), 'refresh');
        }

        $competencies = $this->skill_model->get_competencies($skill_id);
        $standards = [];
        foreach ($competencies as $c) {
            foreach ($this->skill_model->get_competency_standards($c['id']) as $s) {
                $standards[$c['id']][$s['skill_level_id']] = $s['minimum_score'];
            }
        }
        $this->render('skill_competencies', [
            'skill'        => $this->skill_model->get_skill($skill_id),
            'competencies' => $competencies,
            'levels'       => $this->skill_model->get_levels(),
            'weight_total' => $this->skill_model->competency_weight_total($skill_id),
            'standards'    => $standards,
            'edit'         => ($action === 'edit' && $id) ? $this->skill_model->get_competency($id) : null,
        ]);
    }

    // ---- pricing (assessment_products §6) -----------------------------------
    public function pricing($action = '', $id = '')
    {
        $this->guard();
        if ($action === 'save') {
            $this->skill_model->save_product($this->input->post('id') ?: null);
            $this->session->set_flashdata('flash_message', get_phrase('saved_successfully'));
            redirect(site_url('skill_admin/pricing'), 'refresh');
        } elseif ($action === 'delete' && $id) {
            $this->skill_model->delete_product($id);
            $this->session->set_flashdata('flash_message', get_phrase('data_deleted'));
            redirect(site_url('skill_admin/pricing'), 'refresh');
        }
        $this->render('assessment_pricing', [
            'products' => $this->skill_model->get_products(),
            'skills'   => $this->skill_model->get_skills(),
            'levels'   => $this->skill_model->get_levels(),
            'edit'     => $id ? $this->skill_model->get_product($id) : null,
        ]);
    }

    // ---- assessments (builder + versioning §15/§43) -------------------------
    public function assessments($action = '', $id = '')
    {
        $this->guard();
        if ($action === 'delete' && $id) {
            $this->assessment_model->delete_assessment($id);
            $this->session->set_flashdata('flash_message', get_phrase('data_deleted'));
            redirect(site_url('skill_admin/assessments'), 'refresh');
        } elseif ($action === 'new_version' && $id) {
            $new = $this->assessment_model->new_version($id);
            $this->session->set_flashdata('flash_message', get_phrase('New draft version created.'));
            redirect(site_url('skill_admin/assessment_form/' . $new), 'refresh');
        } elseif ($action === 'publish' && $id) {
            $this->db->where('id', $id)->update('assessments', ['status' => 'published']);
            $this->session->set_flashdata('flash_message', get_phrase('Assessment published.'));
            redirect(site_url('skill_admin/assessments'), 'refresh');
        }
        $this->render('assessments_list', ['assessments' => $this->assessment_model->get_assessments()]);
    }

    public function assessment_form($id = '')
    {
        $this->guard();
        if ($this->input->method() === 'post') {
            $new_id = $this->assessment_model->save_assessment($this->input->post('id') ?: null);
            $this->session->set_flashdata('flash_message', get_phrase('saved_successfully'));
            redirect(site_url('skill_admin/questions/' . $new_id), 'refresh');
        }
        $this->render('assessment_form', [
            'assessment' => $id ? $this->assessment_model->get_assessment($id) : null,
            'skills'     => $this->skill_model->get_skills(),
            'levels'     => $this->skill_model->get_levels(),
        ]);
    }

    // ---- question bank (§11–§14) --------------------------------------------
    public function questions($assessment_id = '', $action = '', $id = '')
    {
        $this->guard();
        if (! $assessment_id) {
            redirect(site_url('skill_admin/assessments'), 'refresh');
        }
        if ($action === 'delete' && $id) {
            $this->assessment_model->delete_question($id);
            $this->session->set_flashdata('flash_message', get_phrase('data_deleted'));
            redirect(site_url('skill_admin/questions/' . $assessment_id), 'refresh');
        } elseif ($action === 'status' && $id) {
            $this->assessment_model->set_question_status($id, $this->input->get('to'));
            redirect(site_url('skill_admin/questions/' . $assessment_id), 'refresh');
        } elseif ($action === 'duplicate' && $id) {
            $this->assessment_model->duplicate_question($id);
            $this->session->set_flashdata('flash_message', get_phrase('Question duplicated as a new draft.'));
            redirect(site_url('skill_admin/questions/' . $assessment_id), 'refresh');
        } elseif ($action === 'archive' && $id) {
            $this->assessment_model->set_question_status($id, 'archived');
            $this->session->set_flashdata('flash_message', get_phrase('Question archived.'));
            redirect(site_url('skill_admin/questions/' . $assessment_id), 'refresh');
        } elseif ($action === 'restore' && $id) {
            $this->assessment_model->set_question_status($id, 'draft');
            $this->session->set_flashdata('flash_message', get_phrase('Question restored to draft.'));
            redirect(site_url('skill_admin/questions/' . $assessment_id), 'refresh');
        }
        $assessment = $this->assessment_model->get_assessment($assessment_id);
        $this->render('questions_list', [
            'assessment'   => $assessment,
            'questions'    => $this->assessment_model->get_questions($assessment_id, ['status' => $this->input->get('status'), 'type' => $this->input->get('type')]),
            'types'        => $this->assessment_model->question_types,
            'competencies' => $this->skill_model->get_competencies($assessment['skill_id']),
            'levels'       => $this->skill_model->get_levels(),
            'difficulties' => $this->assessment_model->difficulties,
            'ai_ready'     => $this->ai_configured(),
        ]);
    }

    private function ai_configured()
    {
        $this->load->library('ai_generator');
        return $this->ai_generator->is_configured();
    }

    /** AI drafts questions for admin review (§14). Never publishes directly. */
    public function generate_questions($assessment_id = '')
    {
        $this->guard();
        if (! $assessment_id) {
            redirect(site_url('skill_admin/assessments'), 'refresh');
        }
        $this->load->library('ai_generator');
        $res = $this->ai_generator->generate([
            'assessment_id'  => $assessment_id,
            'competency_id'  => $this->input->post('competency_id'),
            'skill_level_id' => $this->input->post('skill_level_id'),
            'question_type'  => $this->input->post('question_type'),
            'difficulty'     => $this->input->post('difficulty'),
            'count'          => $this->input->post('count'),
            'topic'          => $this->input->post('topic'),
        ]);
        if ($res['ok']) {
            $this->session->set_flashdata('flash_message', $res['count'] . ' ' . get_phrase('draft question(s) generated by AI — review and approve them below.'));
        } else {
            $this->session->set_flashdata('flash_message', get_phrase('AI generation failed') . ': ' . $res['error']);
        }
        redirect(site_url('skill_admin/questions/' . $assessment_id), 'refresh');
    }

    public function question_form($assessment_id = '', $id = '')
    {
        $this->guard();
        if (! $assessment_id) {
            redirect(site_url('skill_admin/assessments'), 'refresh');
        }
        if ($this->input->method() === 'post') {
            $qid = $this->assessment_model->save_question($assessment_id, $this->input->post('id') ?: null);
            if ($this->input->post('question_type') === 'practical' || $this->input->post('question_type') === 'long_text') {
                $this->assessment_model->save_rubrics($assessment_id, $qid);
            }
            $this->session->set_flashdata('flash_message', get_phrase('saved_successfully'));
            redirect(site_url('skill_admin/questions/' . $assessment_id), 'refresh');
        }
        $assessment = $this->assessment_model->get_assessment($assessment_id);
        $q = $id ? $this->assessment_model->get_question($id) : null;
        $this->render('question_form', [
            'assessment'   => $assessment,
            'question'     => $q,
            'options'      => $id ? $this->assessment_model->get_options($id) : [],
            'rubrics'      => $id ? $this->assessment_model->get_rubrics($id) : [],
            'sections'     => $this->assessment_model->get_sections($assessment_id),
            'competencies' => $this->skill_model->get_competencies($assessment['skill_id']),
            'levels'       => $this->skill_model->get_levels(),
            'types'        => $this->assessment_model->question_types,
            'difficulties' => $this->assessment_model->difficulties,
        ]);
    }

    // ---- practical review queue (§29–§30, P7) -------------------------------
    public function reviews($status = '')
    {
        $this->guard();
        $this->render('assessment_reviews', [
            'reviews' => $this->review_model->queue($status),
            'counts'  => $this->review_model->status_counts(),
            'filter'  => $status ?: 'all',
        ]);
    }

    public function review($review_id = '', $action = '')
    {
        $this->guard();
        $rv = $review_id ? $this->review_model->get_review($review_id) : null;
        if (! $rv) {
            redirect(site_url('skill_admin/reviews'), 'refresh');
        }
        $reviewer_id = (int) $this->session->userdata('user_id');

        if ($action === 'claim') {
            $this->review_model->claim($review_id, $reviewer_id);
            redirect(site_url('skill_admin/review/' . $review_id), 'refresh');
        }

        if ($this->input->method() === 'post') {
            $this->review_model->save_scores($review_id, $this->input->post());
            $decision = $this->input->post('decision'); // save | approve | reject | revision
            if ($decision === 'approve' && ! $this->review_model->is_fully_scored($rv)) {
                $this->session->set_flashdata('flash_message', get_phrase('Score every criterion before approving.'));
                redirect(site_url('skill_admin/review/' . $review_id), 'refresh');
            }
            if ($decision && $decision !== 'save') {
                $this->review_model->decide($review_id, $decision, $reviewer_id);
                $this->session->set_flashdata('flash_message', get_phrase('Review updated.'));
                redirect(site_url('skill_admin/reviews'), 'refresh');
            }
            $this->session->set_flashdata('flash_message', get_phrase('Scores saved.'));
            redirect(site_url('skill_admin/review/' . $review_id), 'refresh');
        }

        $this->render('assessment_review', [
            'review' => $rv,
            'items'  => $this->review_model->review_items($rv),
        ]);
    }

    // ---- credentials + lifecycle (§19–§21, P8) ------------------------------
    public function credentials($action = '', $id = '')
    {
        $this->guard();
        if ($action === 'revoke' && $id) {
            $this->credential_model->set_status($id, 'revoked', $this->input->post('reason') ?: get_phrase('Revoked by administrator'));
            $this->session->set_flashdata('flash_message', get_phrase('Credential revoked.'));
            redirect(site_url('skill_admin/credentials'), 'refresh');
        } elseif ($action === 'suspend' && $id) {
            $this->credential_model->set_status($id, 'suspended', $this->input->post('reason') ?: get_phrase('Suspended by administrator'));
            $this->session->set_flashdata('flash_message', get_phrase('Credential suspended.'));
            redirect(site_url('skill_admin/credentials'), 'refresh');
        } elseif ($action === 'reinstate' && $id) {
            $this->credential_model->set_status($id, 'active');
            $this->session->set_flashdata('flash_message', get_phrase('Credential reinstated.'));
            redirect(site_url('skill_admin/credentials'), 'refresh');
        }
        $this->render('assessment_credentials', [
            'credentials' => $this->credential_model->admin_list([
                'status' => $this->input->get('status'),
                'q'      => $this->input->get('q'),
            ]),
            'counts' => $this->credential_model->status_counts(),
            'filter' => $this->input->get('status') ?: 'all',
            'q'      => $this->input->get('q'),
        ]);
    }
}
