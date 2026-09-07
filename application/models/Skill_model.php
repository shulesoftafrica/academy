<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Data layer for the Skills Assessment module — skills, categories, levels,
 * competencies, competency standards and assessment pricing (spec §37).
 * Tables live in the academy schema (search_path=academy).
 */
class Skill_model extends CI_Model
{
    // ---- categories ---------------------------------------------------------
    public function get_categories()
    {
        return $this->db->query(
            "SELECT c.*, (SELECT COUNT(*) FROM skills s WHERE s.category_id = c.id AND s.status = 1) AS skill_count
             FROM skill_categories c ORDER BY c.sort_order, c.name"
        )->result_array();
    }

    public function get_category($id)
    {
        return $this->db->get_where('skill_categories', ['id' => $id])->row_array();
    }

    public function save_category($id = null)
    {
        $data = [
            'name'       => html_escape($this->input->post('name')),
            'slug'       => $this->slugify($this->input->post('slug') ?: $this->input->post('name')),
            'icon'       => html_escape($this->input->post('icon')),
            'sort_order' => (int) $this->input->post('sort_order'),
            'status'     => $this->input->post('status') !== null ? (int) $this->input->post('status') : 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($id) {
            $this->db->where('id', $id)->update('skill_categories', $data);
            return $id;
        }
        $this->db->insert('skill_categories', $data);
        return $this->db->insert_id();
    }

    public function delete_category($id)
    {
        $this->db->where('id', $id)->delete('skill_categories');
    }

    // ---- skills -------------------------------------------------------------
    public function get_skills($only_active = false)
    {
        $this->db->select('s.*, c.name AS category_name')
                 ->from('skills s')->join('skill_categories c', 'c.id = s.category_id', 'left')
                 ->order_by('s.name');
        if ($only_active) {
            $this->db->where('s.status', 1);
        }
        return $this->db->get()->result_array();
    }

    public function get_skill($id)
    {
        return $this->db->get_where('skills', ['id' => $id])->row_array();
    }

    public function get_skill_by_slug($slug)
    {
        return $this->db->get_where('skills', ['slug' => $slug])->row_array();
    }

    public function save_skill($id = null)
    {
        $data = [
            'category_id'     => (int) $this->input->post('category_id') ?: null,
            'name'            => html_escape($this->input->post('name')),
            'slug'            => $this->slugify($this->input->post('slug') ?: $this->input->post('name')),
            'description'     => $this->input->post('description', false),
            'validity_months' => $this->input->post('validity_months') !== '' && is_numeric($this->input->post('validity_months'))
                                    ? (int) $this->input->post('validity_months') : null,
            'status'          => $this->input->post('status') !== null ? (int) $this->input->post('status') : 1,
            'updated_at'      => date('Y-m-d H:i:s'),
        ];
        if ($id) {
            $this->db->where('id', $id)->update('skills', $data);
            return $id;
        }
        $this->db->insert('skills', $data);
        return $this->db->insert_id();
    }

    public function delete_skill($id)
    {
        $this->db->where('id', $id)->delete('skills');
    }

    // ---- levels (read; the 5 are seeded) ------------------------------------
    public function get_levels()
    {
        return $this->db->order_by('rank')->get('skill_levels')->result_array();
    }

    // ---- competencies -------------------------------------------------------
    public function get_competencies($skill_id)
    {
        return $this->db->order_by('sort_order')->get_where('skill_competencies', ['skill_id' => $skill_id])->result_array();
    }

    public function get_competency($id)
    {
        return $this->db->get_where('skill_competencies', ['id' => $id])->row_array();
    }

    public function save_competency($skill_id, $id = null)
    {
        $data = [
            'skill_id'    => (int) $skill_id,
            'name'        => html_escape($this->input->post('name')),
            'description' => html_escape($this->input->post('description')),
            'weight'      => is_numeric($this->input->post('weight')) ? $this->input->post('weight') : 0,
            'is_critical' => $this->input->post('is_critical') ? 1 : 0,
            'sort_order'  => (int) $this->input->post('sort_order'),
            'status'      => 1,
        ];
        if ($id) {
            $this->db->where('id', $id)->update('skill_competencies', $data);
            return $id;
        }
        $this->db->insert('skill_competencies', $data);
        return $this->db->insert_id();
    }

    public function delete_competency($id)
    {
        $this->db->where('competency_id', $id)->delete('skill_competency_levels');
        $this->db->where('id', $id)->delete('skill_competencies');
    }

    /** Sum of competency weights for a skill (should be 100). */
    public function competency_weight_total($skill_id)
    {
        return (float) $this->db->select_sum('weight')->get_where('skill_competencies', ['skill_id' => $skill_id])->row()->weight;
    }

    // ---- competency level standards (§17) -----------------------------------
    public function get_competency_standards($competency_id)
    {
        return $this->db->get_where('skill_competency_levels', ['competency_id' => $competency_id])->result_array();
    }

    public function save_competency_standard($competency_id, $skill_level_id, $minimum_score, $description = '')
    {
        $existing = $this->db->get_where('skill_competency_levels',
            ['competency_id' => $competency_id, 'skill_level_id' => $skill_level_id])->row();
        $data = ['minimum_score' => is_numeric($minimum_score) ? $minimum_score : 0, 'description' => $description];
        if ($existing) {
            $this->db->where('id', $existing->id)->update('skill_competency_levels', $data);
            return $existing->id;
        }
        $data['competency_id'] = $competency_id;
        $data['skill_level_id'] = $skill_level_id;
        $this->db->insert('skill_competency_levels', $data);
        return $this->db->insert_id();
    }

    // ---- pricing (assessment_products §6) -----------------------------------
    public function get_products()
    {
        return $this->db->select('p.*, s.name AS skill_name, l.name AS level_name')
            ->from('assessment_products p')
            ->join('skills s', 's.id = p.skill_id', 'left')
            ->join('skill_levels l', 'l.id = p.skill_level_id', 'left')
            ->order_by('s.name, l.rank')->get()->result_array();
    }

    public function get_product($id)
    {
        return $this->db->get_where('assessment_products', ['id' => $id])->row_array();
    }

    /** Active price for a skill+level (most recent effective row). */
    public function get_active_price($skill_id, $skill_level_id)
    {
        return $this->db->where(['skill_id' => $skill_id, 'skill_level_id' => $skill_level_id, 'active' => 1])
                        ->order_by('id', 'desc')->limit(1)->get('assessment_products')->row_array();
    }

    public function save_product($id = null)
    {
        $data = [
            'skill_id'       => (int) $this->input->post('skill_id'),
            'skill_level_id' => (int) $this->input->post('skill_level_id'),
            'assessment_id'  => (int) $this->input->post('assessment_id') ?: null,
            'price'          => is_numeric($this->input->post('price')) ? $this->input->post('price') : 0,
            'retake_price'   => is_numeric($this->input->post('retake_price')) ? $this->input->post('retake_price') : null,
            'currency'       => html_escape($this->input->post('currency') ?: 'TZS'),
            'active'         => $this->input->post('active') ? 1 : 0,
        ];
        if ($id) {
            $this->db->where('id', $id)->update('assessment_products', $data);
            return $id;
        }
        $this->db->insert('assessment_products', $data);
        return $this->db->insert_id();
    }

    public function delete_product($id)
    {
        $this->db->where('id', $id)->delete('assessment_products');
    }

    // ---- dashboard metrics (§39) -------------------------------------------
    public function dashboard_metrics()
    {
        $agg = $this->db->query(
            "SELECT COUNT(*) attempts,
                    COALESCE(SUM(CASE WHEN result='passed' THEN 1 ELSE 0 END),0) passed,
                    COALESCE(SUM(CASE WHEN result IN ('passed','failed','lower_level') THEN 1 ELSE 0 END),0) graded,
                    AVG(score) avg_score,
                    COALESCE(SUM(CASE WHEN integrity_status IS NOT NULL AND integrity_status NOT IN ('','ok','clean') THEN 1 ELSE 0 END),0) flagged
             FROM assessment_attempts"
        )->row();

        return [
            'skills'          => $this->db->where('status', 1)->count_all_results('skills'),
            'assessments'     => $this->db->where('status', 'published')->count_all_results('assessments'),
            'candidates'      => (int) $this->db->query("SELECT COUNT(DISTINCT candidate_id) c FROM assessment_attempts")->row()->c,
            'credentials'     => $this->db->where('status', 'active')->count_all_results('skill_credentials'),
            'pending_reviews' => $this->db->where('status', 'pending')->count_all_results('assessment_reviews'),
            'revenue'         => (float) $this->db->select_sum('amount')->where('status', 'paid')->get('assessment_payments')->row()->amount,
            'attempts'        => (int) $agg->attempts,
            'pass_rate'       => $agg->graded > 0 ? round($agg->passed / $agg->graded * 100, 1) : null,
            'avg_score'       => $agg->avg_score !== null ? round((float) $agg->avg_score, 1) : null,
            'integrity_flags' => (int) $agg->flagged,
        ];
    }

    // ---- analytics & QC (spec §41–§42) --------------------------------------
    /** Per-assessment performance: attempts, pass rate, avg score, duration, revenue. */
    public function assessment_analytics()
    {
        $rows = $this->db->query(
            "SELECT a.id, a.title, a.version, a.status, a.pass_score,
                    s.name AS skill_name, sl.name AS level_name,
                    COUNT(at.id) AS attempts,
                    COUNT(DISTINCT at.candidate_id) AS candidates,
                    COALESCE(SUM(CASE WHEN at.result='passed' THEN 1 ELSE 0 END),0) AS passed,
                    COALESCE(SUM(CASE WHEN at.result IN ('passed','failed','lower_level') THEN 1 ELSE 0 END),0) AS graded,
                    AVG(at.score) FILTER (WHERE at.score IS NOT NULL) AS avg_score,
                    AVG(EXTRACT(EPOCH FROM (at.completed_at - at.started_at)))
                        FILTER (WHERE at.completed_at IS NOT NULL AND at.started_at IS NOT NULL) AS avg_seconds,
                    COALESCE(SUM(CASE WHEN at.needs_review=1 THEN 1 ELSE 0 END),0) AS in_review
             FROM assessments a
             JOIN skills s ON s.id = a.skill_id
             LEFT JOIN skill_levels sl ON sl.id = a.skill_level_id
             LEFT JOIN assessment_attempts at ON at.assessment_id = a.id
             GROUP BY a.id, a.title, a.version, a.status, a.pass_score, s.name, sl.name
             ORDER BY attempts DESC, a.id"
        )->result_array();

        $rev = [];
        foreach ($this->db->query("SELECT assessment_id, COALESCE(SUM(amount),0) r FROM assessment_payments WHERE status='paid' GROUP BY assessment_id")->result_array() as $x) {
            $rev[$x['assessment_id']] = $x['r'];
        }
        foreach ($rows as &$r) {
            $r['revenue']     = $rev[$r['id']] ?? 0;
            $r['pass_rate']   = $r['graded'] > 0 ? round($r['passed'] / $r['graded'] * 100, 1) : null;
            $r['retake_rate'] = $r['attempts'] > 0 ? round(((int) $r['attempts'] - (int) $r['candidates']) / $r['attempts'] * 100, 1) : null;
        }
        return $rows;
    }

    /** Per-competency average attainment across all attempts (§41). */
    public function competency_analytics()
    {
        return $this->db->query(
            "SELECT c.id, c.name, s.name AS skill_name, c.is_critical, c.weight,
                    COUNT(DISTINCT aa.attempt_id) AS attempts,
                    AVG(CASE WHEN aa.max_score > 0 THEN aa.score / aa.max_score * 100 END) AS avg_pct
             FROM skill_competencies c
             JOIN skills s ON s.id = c.skill_id
             LEFT JOIN assessment_attempt_answers aa ON aa.competency_id = c.id AND aa.score IS NOT NULL
             GROUP BY c.id, c.name, s.name, c.is_critical, c.weight
             ORDER BY s.name, c.name"
        )->result_array();
    }

    /** Question-quality stats for one assessment: times shown, correct %, avg score, edits (§42). */
    public function question_quality($assessment_id)
    {
        return $this->db->query(
            "SELECT q.id, q.question_text, q.question_type, q.difficulty, q.points,
                    COUNT(aa.id) AS times_shown,
                    AVG(aa.is_correct::numeric) FILTER (WHERE aa.is_correct IS NOT NULL) * 100 AS correct_pct,
                    AVG(CASE WHEN aa.max_score > 0 THEN aa.score / aa.max_score * 100 END) AS avg_pct,
                    AVG(aa.changes_count) AS avg_changes
             FROM assessment_questions q
             LEFT JOIN assessment_attempt_answers aa ON aa.question_id = q.id
             WHERE q.assessment_id = ?
             GROUP BY q.id, q.question_text, q.question_type, q.difficulty, q.points
             ORDER BY q.id",
            [$assessment_id]
        )->result_array();
    }

    private function slugify($text)
    {
        $text = strtolower(trim((string) $text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }
}
