<?php
// Builds the Academy skills + assessments seed (idempotent, one transaction) from skills_spec.json and assess_*.json.
$sp = __DIR__;
$spec = json_decode(file_get_contents("$sp/skills_spec.json"), true);
$skillMeta = [];
foreach ($spec['batches'] as $list) { foreach ($list as $s) { $skillMeta[$s['slug']] = $s; } }

$assess = [];
foreach (glob("$sp/assess_*.json") as $f) {
    foreach (json_decode(file_get_contents($f), true) as $a) { $assess[$a['skill_slug']] = $a; }
}
$missing = array_diff(array_keys($skillMeta), array_keys($assess));
if ($missing) { fwrite(STDERR, "WARNING: no assessment content yet for: " . implode(', ', $missing) . "\n"); }

function q(string $s): string { return "'" . str_replace("'", "''", $s) . "'"; }
function nz($v) { return $v === null ? 'NULL' : (int) $v; }

$LEVEL = 4; // academy.skill_levels id 4 = Professional (rank 3); Practitioner (rank 2) is awarded on a 50-69% score.
$out = [];
$out[] = "-- Academy skills catalogue + assessments, generated " . date('Y-m-d') . ". Idempotent: skills, competencies and assessments that already exist are left alone.";
$out[] = "-- Level model: every assessment is a Professional-level (id 4) assessment. A score at or above the pass mark awards Professional;";
$out[] = "-- 50% up to the pass mark awards the next lower level (Practitioner). Price is TZS 5,000 per assessment.";
$out[] = "BEGIN;";

foreach ($spec['new_categories'] as $c) {
    $out[] = "INSERT INTO academy.skill_categories (name, slug, sort_order, status, created_at, updated_at) SELECT " . q($c['name']) . ", " . q($c['slug']) . ", {$c['sort_order']}, 1, now(), now() WHERE NOT EXISTS (SELECT 1 FROM academy.skill_categories WHERE slug = " . q($c['slug']) . ");";
}

foreach ($skillMeta as $slug => $m) {
    if (empty($m['existing'])) {
        $out[] = "INSERT INTO academy.skills (category_id, name, slug, description, validity_months, status, created_at, updated_at) SELECT (SELECT id FROM academy.skill_categories WHERE slug = " . q($m['category']) . "), " . q($m['name']) . ", " . q($slug) . ", " . q($assess[$slug]['description'] ?? $m['about']) . ", " . nz($m['validity']) . ", 1, now(), now() WHERE NOT EXISTS (SELECT 1 FROM academy.skills WHERE slug = " . q($slug) . ");";
    }
}

foreach ($assess as $slug => $a) {
    $m = $skillMeta[$slug];
    $title = $a['skill_name'] . ' — Professional';
    $nq = count($a['questions']);
    $nk = count(array_filter($a['questions'], fn ($x) => $x['section'] === 'knowledge'));
    $ns = $nq - $nk;
    $pts = ['easy' => 6, 'medium' => 8, 'hard' => 10];

    $b = [];
    $b[] = "DO \$seed\$";
    $b[] = "DECLARE v_skill int; v_assess int; v_sec_k int; v_sec_s int; v_q int; v_c int; v_comp jsonb := '{}'::jsonb;";
    $b[] = "BEGIN";
    $b[] = "  SELECT id INTO v_skill FROM academy.skills WHERE slug = " . q($slug) . ";";
    $b[] = "  IF v_skill IS NULL THEN RAISE EXCEPTION 'skill % not found', " . q($slug) . "; END IF;";
    $b[] = "  IF EXISTS (SELECT 1 FROM academy.assessments WHERE skill_id = v_skill AND skill_level_id = $LEVEL) THEN RETURN; END IF;";
    $b[] = "  IF EXISTS (SELECT 1 FROM academy.skill_competencies WHERE skill_id = v_skill) THEN RAISE EXCEPTION 'skill % already has competencies but no assessment: resolve by hand', " . q($slug) . "; END IF;";
    foreach ($a['competencies'] as $i => $c) {
        $b[] = "  INSERT INTO academy.skill_competencies (skill_id, name, description, weight, is_critical, sort_order, status) VALUES (v_skill, " . q($c['name']) . ", " . q($c['description']) . ", {$c['weight']}, " . (int) $c['is_critical'] . ", " . ($i + 1) . ", 1) RETURNING id INTO v_c;";
        $b[] = "  v_comp := v_comp || jsonb_build_object(" . q($c['name']) . ", v_c);";
        $b[] = "  INSERT INTO academy.skill_competency_levels (competency_id, skill_level_id, description, minimum_score) VALUES (v_c, $LEVEL, 'Professional minimum standard', " . ($c['is_critical'] ? 60 : 50) . ");";
    }
    $b[] = "  INSERT INTO academy.assessments (skill_id, skill_level_id, title, description, duration_minutes, pass_score, critical_min, total_questions, max_attempts, cooldown_days, version, status, created_at, updated_at) VALUES (v_skill, $LEVEL, " . q($title) . ", " . q($a['description']) . ", 30, 70, 60, $nq, 1, 7, 1, 'published', now(), now()) RETURNING id INTO v_assess;";
    $b[] = "  INSERT INTO academy.assessment_sections (assessment_id, name, type, num_questions, sort_order) VALUES (v_assess, 'Knowledge', 'knowledge', $nk, 1) RETURNING id INTO v_sec_k;";
    $b[] = "  INSERT INTO academy.assessment_sections (assessment_id, name, type, num_questions, sort_order) VALUES (v_assess, 'Situational Judgment', 'situational', $ns, 2) RETURNING id INTO v_sec_s;";
    foreach ($a['questions'] as $qq) {
        $sec = $qq['section'] === 'knowledge' ? 'v_sec_k' : 'v_sec_s';
        $b[] = "  INSERT INTO academy.assessment_questions (assessment_id, section_id, skill_id, competency_id, skill_level_id, question_type, question_text, difficulty, points, time_seconds, explanation, ai_generated, status, is_active, created_at, updated_at) VALUES (v_assess, $sec, v_skill, (v_comp->>" . q($qq['competency']) . ")::int, $LEVEL, " . q($qq['type']) . ", " . q($qq['text']) . ", " . q($qq['difficulty']) . ", {$pts[$qq['difficulty']]}, 0, " . q($qq['explanation']) . ", 1, 'published', 1, now(), now()) RETURNING id INTO v_q;";
        foreach ($qq['options'] as $j => $o) {
            $b[] = "  INSERT INTO academy.assessment_question_options (question_id, option_text, is_correct, score, match_key, correct_order, sort_order) VALUES (v_q, " . q($o['text']) . ", " . (!empty($o['correct']) ? 1 : 0) . ", 0, '', 0, " . ($j + 1) . ");";
        }
    }
    $b[] = "  INSERT INTO academy.assessment_products (skill_id, skill_level_id, assessment_id, price, currency, active, created_at) VALUES (v_skill, $LEVEL, v_assess, 5000, 'TZS', 1, now());";
    $b[] = "END \$seed\$;";
    $out[] = implode("\n", $b);
}

$out[] = "COMMIT;";
$target = $argv[1] ?? "$sp/skills_catalog_seed.sql";
file_put_contents($target, implode("\n", $out) . "\n");
$qn = 0; foreach ($assess as $a) { $qn += count($a['questions']); }
echo "wrote $target: " . count($skillMeta) . " skills in spec, " . count($assess) . " assessments, $qn questions\n";
