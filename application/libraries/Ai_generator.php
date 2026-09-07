<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * AI question generation (spec §14). Drafts assessment questions with OpenAI
 * (same key/model as the talent app — read from talent/.env at runtime, never
 * copied into code). AI only ever produces DRAFTS: an administrator must review
 * and approve them before they can appear in a live assessment. AI never defines
 * the competency standard itself.
 */
class Ai_generator
{
    private $CI;
    private $talent_env = null;
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->config->load('shulesoft_auth', true);
    }

    public function is_configured()
    {
        return $this->cfg('openai_api_key') !== '';
    }

    /**
     * Generate draft questions for an assessment.
     *
     * @param array $p ['assessment_id','competency_id','skill_level_id','question_type','difficulty','count','topic']
     * @return array ['ok'=>bool,'count'=>int,'error'=>?string]
     */
    public function generate($p)
    {
        if (! $this->is_configured()) {
            return ['ok' => false, 'count' => 0, 'error' => 'OpenAI key not configured (checked talent/.env).'];
        }
        $assessment = $this->CI->db->get_where('assessments', ['id' => (int) $p['assessment_id']])->row_array();
        if (! $assessment) {
            return ['ok' => false, 'count' => 0, 'error' => 'assessment_not_found'];
        }
        $skill      = $this->CI->db->get_where('skills', ['id' => $assessment['skill_id']])->row_array();
        $competency = $p['competency_id'] ? $this->CI->db->get_where('skill_competencies', ['id' => (int) $p['competency_id']])->row_array() : null;
        $level      = $this->CI->db->get_where('skill_levels', ['id' => (int) $p['skill_level_id']])->row_array();
        $type       = $p['question_type'];
        $difficulty = $p['difficulty'] ?: 'medium';
        $count      = max(1, min(10, (int) $p['count']));

        [$system, $user] = $this->prompt($skill, $competency, $level, $type, $difficulty, $count, $p['topic'] ?? '');
        $resp = $this->call($system, $user);
        if (! $resp['ok']) {
            return ['ok' => false, 'count' => 0, 'error' => $resp['error']];
        }
        $data = json_decode($resp['content'], true);
        $questions = $data['questions'] ?? [];
        if (! is_array($questions) || ! $questions) {
            return ['ok' => false, 'count' => 0, 'error' => 'no_questions_returned'];
        }

        $made = 0;
        foreach ($questions as $q) {
            if (empty($q['question_text'])) {
                continue;
            }
            $qid = $this->insert_question($assessment, $p, $type, $difficulty, $q);
            if ($qid) {
                $this->insert_options($qid, $type, $q);
                $made++;
            }
        }
        return ['ok' => true, 'count' => $made, 'error' => null];
    }

    // ---- prompt -------------------------------------------------------------
    private function prompt($skill, $competency, $level, $type, $difficulty, $count, $topic)
    {
        $typeGuide = [
            'single'    => 'single-choice: provide exactly 4 options with EXACTLY ONE correct.',
            'multiple'  => 'multiple-response: provide 4-5 options with 2 or 3 correct.',
            'truefalse' => 'true/false: provide exactly two options, "True" and "False", one correct.',
            'short_text'=> 'short written answer: no options; include "expected_answer" and "marking_criteria".',
            'long_text' => 'case-study written answer: no options; include a realistic scenario in question_text, plus "expected_answer" and "marking_criteria".',
        ];
        $guide = $typeGuide[$type] ?? $typeGuide['single'];

        $system = 'You are an expert assessment author for ShuleSoft Academy, a skills-verification platform in Africa. '
            . 'Write realistic, job-relevant, unambiguous questions that measure demonstrated competency (not trivia). '
            . 'Return ONLY a JSON object of the form {"questions":[{"question_text":string,"explanation":string,'
            . '"options":[{"text":string,"is_correct":boolean}],"expected_answer":string,"marking_criteria":string}]}. '
            . 'Never put option letters (A/B/C) or "correct answer" hints inside question_text. Do not include markdown.';

        $ctx = [
            'skill'         => $skill['name'] ?? '',
            'competency'    => $competency['name'] ?? 'general',
            'competency_desc' => $competency['description'] ?? '',
            'level'         => $level['name'] ?? '',
            'difficulty'    => $difficulty,
            'question_type' => $type,
            'count'         => $count,
            'instructions'  => "Generate {$count} {$type} question(s). {$guide} Difficulty: {$difficulty}. "
                             . 'Tailor to the level and competency above.'
                             . ($topic ? " Focus on: {$topic}." : ''),
        ];
        return [$system, json_encode($ctx)];
    }

    // ---- insert -------------------------------------------------------------
    private function insert_question($assessment, $p, $type, $difficulty, $q)
    {
        $this->CI->db->insert('assessment_questions', [
            'assessment_id'    => (int) $assessment['id'],
            'skill_id'         => (int) $assessment['skill_id'],
            'competency_id'    => $p['competency_id'] ? (int) $p['competency_id'] : null,
            'skill_level_id'   => (int) $p['skill_level_id'],
            'question_type'    => $type,
            'question_text'    => trim($q['question_text']),
            'difficulty'       => $difficulty,
            'points'           => 10,
            'explanation'      => $q['explanation'] ?? '',
            'expected_answer'  => $q['expected_answer'] ?? '',
            'marking_criteria' => $q['marking_criteria'] ?? '',
            'ai_generated'     => 1,
            'status'           => 'draft', // must be reviewed + approved before going live (§14)
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);
        return $this->CI->db->insert_id();
    }

    private function insert_options($qid, $type, $q)
    {
        if (! in_array($type, ['single', 'multiple', 'truefalse'], true)) {
            return;
        }
        $opts = $q['options'] ?? [];
        if ($type === 'truefalse' && ! $opts) {
            $opts = [['text' => 'True', 'is_correct' => true], ['text' => 'False', 'is_correct' => false]];
        }
        $i = 1;
        foreach ($opts as $o) {
            if (empty($o['text'])) {
                continue;
            }
            $this->CI->db->insert('assessment_question_options', [
                'question_id' => (int) $qid,
                'option_text' => trim($o['text']),
                'is_correct'  => ! empty($o['is_correct']) ? 1 : 0,
                'sort_order'  => $i++,
            ]);
        }
    }

    // ---- OpenAI call (curl, JSON mode) --------------------------------------
    private function call($system, $user)
    {
        $payload = [
            'model'       => $this->cfg('openai_model') ?: 'gpt-4o-mini',
            'max_tokens'  => (int) ($this->cfg('openai_max_tokens') ?: 2000),
            'temperature' => (float) ($this->cfg('openai_temperature') ?: 0.7),
            'response_format' => ['type' => 'json_object'],
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
        ];
        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => (int) ($this->cfg('openai_timeout') ?: 60),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->cfg('openai_api_key'),
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);
        $body   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($err) {
            log_message('error', 'Ai_generator: network error ' . $err);
            return ['ok' => false, 'content' => null, 'error' => 'network_error'];
        }
        if ($status < 200 || $status >= 300) {
            log_message('error', 'Ai_generator: OpenAI http ' . $status . ' ' . substr((string) $body, 0, 300));
            return ['ok' => false, 'content' => null, 'error' => 'openai_http_' . $status];
        }
        $json = json_decode((string) $body, true);
        $content = $json['choices'][0]['message']['content'] ?? null;
        if (! $content) {
            return ['ok' => false, 'content' => null, 'error' => 'empty_response'];
        }
        return ['ok' => true, 'content' => $content, 'error' => null];
    }

    // ---- config with talent/.env fallback -----------------------------------
    private $env_map = [
        'openai_api_key'     => 'OPENAI_API_KEY',
        'openai_model'       => 'OPENAI_DEFAULT_MODEL',
        'openai_max_tokens'  => 'OPENAI_MAX_TOKENS',
        'openai_temperature' => 'OPENAI_TEMPERATURE',
        'openai_timeout'     => 'OPENAI_REQUEST_TIMEOUT',
    ];

    private function cfg($key)
    {
        $val = $this->CI->config->item($key, 'shulesoft_auth');
        if (($val === '' || $val === null) && isset($this->env_map[$key])) {
            $val = $this->talent_env($this->env_map[$key]);
        }
        return $val;
    }

    private function talent_env($key)
    {
        if ($this->talent_env === null) {
            $this->talent_env = [];
            $path = $this->CI->config->item('talent_env_path', 'shulesoft_auth');
            if ($path && is_readable($path)) {
                foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    $line = trim($line);
                    if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                        continue;
                    }
                    list($k, $v) = explode('=', $line, 2);
                    $v = trim($v);
                    if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) {
                        $v = substr($v, 1, -1);
                    }
                    $this->talent_env[trim($k)] = $v;
                }
            }
        }
        return $this->talent_env[$key] ?? '';
    }
}
