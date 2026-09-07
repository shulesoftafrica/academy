<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Verified skill credentials (spec §19–§21, P8). A credential is issued when a
 * candidate demonstrates a level (result status verified/lower_level). It carries
 * a credential number + verification token backing a public verification URL that
 * exposes only skill / level / date / status — never questions or answers (§46).
 * Lifecycle: active → expired (validity) / revoked (admin) / superseded (re-verified).
 */
class Credential_model extends CI_Model
{
    /**
     * Issue (or refresh) a credential for a candidate_skill_results row.
     * Idempotent per result; supersedes the candidate's prior active credential
     * for the same skill. Returns the credential id (or null if not issuable).
     */
    public function issue($result_id)
    {
        $r = $this->db->get_where('candidate_skill_results', ['id' => $result_id])->row_array();
        if (! $r || ! in_array($r['status'], ['verified', 'lower_level'], true)) {
            return null;
        }

        // already issued for this exact result → return it
        $existing = $this->db->get_where('skill_credentials', ['result_id' => $result_id])->row_array();
        if ($existing) {
            return $existing['id'];
        }

        // supersede any earlier active credential for this candidate + skill (§20)
        $this->db->where('candidate_id', $r['candidate_id'])->where('skill_id', $r['skill_id'])
                 ->where('status', 'active')
                 ->update('skill_credentials', ['status' => 'superseded']);

        $skill    = $this->db->get_where('skills', ['id' => $r['skill_id']])->row_array();
        $version  = (int) ($this->db->select('assessment_version')->get_where('assessment_attempts', ['id' => $r['attempt_id']])->row()->assessment_version ?? 1);
        $expires  = null;
        if (! empty($skill['validity_months'])) {
            $expires = date('Y-m-d H:i:s', strtotime('+' . (int) $skill['validity_months'] . ' months'));
        }

        // credential_number is NOT NULL but the readable form needs the new id,
        // so insert a unique placeholder derived from the token, then finalize.
        $token = $this->make_token();
        $this->db->insert('skill_credentials', [
            'candidate_id'       => $r['candidate_id'],
            'sid'                => $r['sid'],
            'skill_id'           => $r['skill_id'],
            'skill_level_id'     => $r['skill_level_id'],
            'result_id'          => $result_id,
            'credential_number'  => 'SSA-' . date('Y') . '-' . substr($token, 0, 10),
            'verification_token' => $token,
            'overall_score'      => $r['overall_score'],
            'assessment_version' => $version,
            'expires_at'         => $expires,
            'status'             => 'active',
            'issued_at'          => date('Y-m-d H:i:s'),
        ]);
        if ($this->db->affected_rows() < 1) {
            return null; // insert failed — don't leave a dangling credential_id
        }
        $id = $this->db->insert_id();

        $number = 'SSA-' . date('Y') . '-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
        $this->db->where('id', $id)->update('skill_credentials', ['credential_number' => $number]);
        $this->db->where('id', $result_id)->update('candidate_skill_results', ['credential_id' => $id]);

        return $id;
    }

    private function make_token()
    {
        do {
            $token = bin2hex(random_bytes(20)); // 40 hex chars
        } while ($this->db->get_where('skill_credentials', ['verification_token' => $token])->num_rows() > 0);
        return $token;
    }

    /**
     * Public verification lookup by token — the only data a verifier sees (§19/§46).
     * Returns null for an unknown token. Adds a computed effective status.
     */
    public function verify_by_token($token)
    {
        $c = $this->db->select("c.*, TRIM(CONCAT(u.first_name,' ',u.last_name)) AS candidate_name,
                                s.name AS skill_name, s.slug AS skill_slug, sl.name AS level_name", false)
                      ->from('skill_credentials c')
                      ->join('users u', 'u.id = c.candidate_id', 'left')
                      ->join('skills s', 's.id = c.skill_id', 'left')
                      ->join('skill_levels sl', 'sl.id = c.skill_level_id', 'left')
                      ->where('c.verification_token', $token)->get()->row_array();
        if (! $c) {
            return null;
        }
        $c['effective_status'] = $this->effective_status($c);
        return $c;
    }

    /** active + not past expiry = valid; anything else is shown as-is/expired. */
    public function effective_status($c)
    {
        if ($c['status'] === 'active' && ! empty($c['expires_at']) && strtotime($c['expires_at']) < time()) {
            return 'expired';
        }
        return $c['status'];
    }

    public function is_valid($c)
    {
        return $this->effective_status($c) === 'active';
    }

    /** Credentials for a candidate (My Skills / talent surfacing), newest first. */
    public function for_candidate($candidate_id)
    {
        $rows = $this->db->select('c.*, s.name AS skill_name, s.slug AS skill_slug, sl.name AS level_name, sl.rank AS level_rank', false)
                         ->from('skill_credentials c')
                         ->join('skills s', 's.id = c.skill_id', 'left')
                         ->join('skill_levels sl', 'sl.id = c.skill_level_id', 'left')
                         ->where('c.candidate_id', $candidate_id)
                         ->order_by('c.issued_at', 'desc')->get()->result_array();
        foreach ($rows as &$r) {
            $r['effective_status'] = $this->effective_status($r);
        }
        return $rows;
    }

    // ---- admin --------------------------------------------------------------
    public function admin_list($filter = [])
    {
        $this->db->select("c.*, TRIM(CONCAT(u.first_name,' ',u.last_name)) AS candidate_name, u.email AS candidate_email,
                           s.name AS skill_name, sl.name AS level_name", false)
                 ->from('skill_credentials c')
                 ->join('users u', 'u.id = c.candidate_id', 'left')
                 ->join('skills s', 's.id = c.skill_id', 'left')
                 ->join('skill_levels sl', 'sl.id = c.skill_level_id', 'left');
        if (! empty($filter['status']) && $filter['status'] !== 'all') {
            $this->db->where('c.status', $filter['status']);
        }
        if (! empty($filter['q'])) {
            $this->db->group_start()
                     ->like('c.credential_number', $filter['q'])
                     ->or_like('u.email', $filter['q'])
                     ->or_like('s.name', $filter['q'])
                     ->group_end();
        }
        $rows = $this->db->order_by('c.issued_at', 'desc')->limit(300)->get()->result_array();
        foreach ($rows as &$r) {
            $r['effective_status'] = $this->effective_status($r);
        }
        return $rows;
    }

    public function status_counts()
    {
        $out = ['active' => 0, 'expired' => 0, 'revoked' => 0, 'superseded' => 0, 'suspended' => 0];
        foreach ($this->db->select('status, COUNT(*) c')->group_by('status')->get('skill_credentials')->result_array() as $r) {
            $out[$r['status']] = (int) $r['c'];
        }
        return $out;
    }

    public function get($id)
    {
        return $this->db->get_where('skill_credentials', ['id' => $id])->row_array();
    }

    /** Admin lifecycle change (revoke / suspend / reinstate, §20). */
    public function set_status($id, $status, $reason = '')
    {
        $allowed = ['active', 'revoked', 'suspended'];
        if (! in_array($status, $allowed, true)) {
            return;
        }
        $this->db->where('id', $id)->update('skill_credentials', [
            'status'         => $status,
            'revoked_reason' => $status === 'active' ? '' : $reason,
        ]);
        // keep the results bridge in step so talent surfacing reflects revocation
        $cred = $this->get($id);
        if ($cred) {
            $this->db->where('id', $cred['result_id'])
                     ->update('candidate_skill_results', ['status' => $status === 'active' ? 'verified' : $status]);
        }
    }
}
