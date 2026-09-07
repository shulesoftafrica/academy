<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Resolves whether a verified identity (email / phone) belongs to the ShuleSoft
 * community, and what Academy role they get, by looking across the shared
 * shulesoft2024 schemas. Match key is email (case-insensitive) with a normalised
 * last-9-digits phone fallback. The durable cross-app id `sid` is captured for
 * shadow-account mapping. See role table in the plan.
 */
class Community_model extends CI_Model
{
    /**
     * @return array{found:bool,sid:?int,is_admin:bool,is_student:bool,is_teacher:bool,
     *               is_job_seeker:bool,display_name:?string,email:string,phone:string,sources:array}
     */
    public function resolve($email, $phone = '')
    {
        $email  = strtolower(trim((string) $email));
        $phone9 = $this->last9($phone);

        $p = [
            'found' => false, 'sid' => null,
            'is_admin' => false, 'is_student' => false, 'is_teacher' => false, 'is_job_seeker' => false,
            'display_name' => null, 'email' => $email, 'phone' => $phone, 'sources' => [],
        ];

        // Sysadmin — ONLY admin.users id=2, active.
        $r = $this->find('admin.users', 'name', $email, $phone9, 'id = 2 AND status = 1');
        if ($r) { $p['is_admin'] = true; $p['sources'][] = 'admin'; $this->capture($p, $r); }

        // ShuleSoft teacher -> student + can apply as instructor (career person)
        $r = $this->find('shulesoft.teacher', 'name', $email, $phone9);
        if ($r) { $p['is_teacher'] = true; $p['sources'][] = 'teacher'; $this->capture($p, $r); }

        // ShuleSoft student -> student only
        $r = $this->find('shulesoft.student', 'name', $email, $phone9);
        if ($r) { $p['is_student'] = true; $p['sources'][] = 'student'; $this->capture($p, $r); }

        // Talent candidate -> job seeker / career person
        $r = $this->find('talent.candidates', 'full_name', $email, $phone9);
        if ($r) { $p['is_job_seeker'] = true; $p['sources'][] = 'candidate'; $this->capture($p, $r); }

        // Safaribook user -> job seeker / career person
        $r = $this->find('safaribook.users', 'name', $email, $phone9);
        if ($r) { $p['is_job_seeker'] = true; $p['sources'][] = 'safaribook'; $this->capture($p, $r); }

        $p['found'] = $p['is_admin'] || $p['is_student'] || $p['is_teacher'] || $p['is_job_seeker'];
        return $p;
    }

    /** Run a single email-or-phone lookup against a schema-qualified table. */
    private function find($table, $name_col, $email, $phone9, $extra = '')
    {
        $where = ($extra !== '') ? "($extra) AND " : '';
        $sql   = "SELECT sid, {$name_col} AS display_name, email, phone
                  FROM {$table}
                  WHERE {$where}( lower(coalesce(email,'')) = ? ";
        $binds = [$email];
        if ($phone9 !== '') {
            $sql .= " OR right(regexp_replace(coalesce(phone::text,''), '\\D', '', 'g'), 9) = ? ";
            $binds[] = $phone9;
        }
        $sql .= " ) LIMIT 1";

        return $this->db->query($sql, $binds)->row();
    }

    /** First non-null sid / name wins (ShuleSoft core tables are checked first). */
    private function capture(array &$p, $r)
    {
        if ($p['sid'] === null && !empty($r->sid))                { $p['sid'] = (int) $r->sid; }
        if (empty($p['display_name']) && !empty($r->display_name)) { $p['display_name'] = $r->display_name; }
        if (empty($p['phone']) && !empty($r->phone))              { $p['phone'] = $r->phone; }
        if (empty($p['email']) && !empty($r->email))              { $p['email'] = strtolower(trim($r->email)); }
    }

    /** Last 9 digits of a phone number (empty string if none) — format-agnostic match. */
    private function last9($phone)
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        return strlen($digits) >= 9 ? substr($digits, -9) : '';
    }
}
