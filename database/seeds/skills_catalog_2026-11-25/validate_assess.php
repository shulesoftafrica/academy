<?php
// Usage: php validate_assess.php <file.json>   (exit 0 = valid)
$f = $argv[1] ?? null;
if (!$f || !is_file($f)) { fwrite(STDERR, "file not found\n"); exit(2); }
$d = json_decode(file_get_contents($f), true);
if (!is_array($d)) { fwrite(STDERR, "invalid JSON: " . json_last_error_msg() . "\n"); exit(2); }
$spec = json_decode(file_get_contents(__DIR__ . '/skills_spec.json'), true);
$bySlug = [];
foreach ($spec['batches'] as $b => $list) { foreach ($list as $s) { $bySlug[$s['slug']] = $b; } }
$errs = [];
$seen = [];
foreach ($d as $i => $a) {
    $slug = $a['skill_slug'] ?? '?';
    $p = "[$slug]";
    if (!isset($bySlug[$slug])) { $errs[] = "$p unknown skill slug"; continue; }
    $seen[$slug] = true;
    $comps = $a['competencies'] ?? [];
    if (count($comps) < 4 || count($comps) > 6) $errs[] = "$p needs 4-6 competencies, has " . count($comps);
    $w = 0; $crit = 0; $names = [];
    foreach ($comps as $c) {
        foreach (['name', 'description', 'weight', 'is_critical'] as $k) if (!isset($c[$k])) $errs[] = "$p competency missing $k";
        $w += (float) ($c['weight'] ?? 0); $crit += (int) ($c['is_critical'] ?? 0); $names[$c['name'] ?? ''] = 0;
    }
    if (abs($w - 100) > 0.01) $errs[] = "$p competency weights sum to $w, must be 100";
    if ($crit < 1 || $crit > 2) $errs[] = "$p needs 1-2 critical competencies, has $crit";
    $qs = $a['questions'] ?? [];
    if (count($qs) !== 12) $errs[] = "$p needs exactly 12 questions, has " . count($qs);
    $sec = ['knowledge' => 0, 'situational' => 0]; $multi = 0; $diff = ['easy' => 0, 'medium' => 0, 'hard' => 0]; $texts = [];
    foreach ($qs as $n => $q) {
        $qp = "$p Q" . ($n + 1);
        $type = $q['type'] ?? ''; $opts = $q['options'] ?? [];
        if (!in_array($type, ['single', 'multiple'], true)) $errs[] = "$qp bad type";
        if (!isset($names[$q['competency'] ?? ''])) $errs[] = "$qp competency '" . ($q['competency'] ?? '') . "' is not one of the listed competencies"; else $names[$q['competency']]++;
        if (!isset($sec[$q['section'] ?? ''])) $errs[] = "$qp bad section"; else $sec[$q['section']]++;
        if (!isset($diff[$q['difficulty'] ?? ''])) $errs[] = "$qp bad difficulty"; else $diff[$q['difficulty']]++;
        $correct = count(array_filter($opts, fn ($o) => !empty($o['correct'])));
        if ($type === 'single') { if (count($opts) !== 4 || $correct !== 1) $errs[] = "$qp single needs 4 options with exactly 1 correct (has " . count($opts) . "/$correct)"; }
        if ($type === 'multiple') { $multi++; if (count($opts) !== 5 || $correct < 2 || $correct > 3) $errs[] = "$qp multiple needs 5 options with 2-3 correct (has " . count($opts) . "/$correct)"; }
        $texts_opts = array_map(fn ($o) => trim($o['text'] ?? ''), $opts);
        if (count(array_unique($texts_opts)) !== count($texts_opts) || in_array('', $texts_opts, true)) $errs[] = "$qp duplicate or empty option text";
        if (trim($q['text'] ?? '') === '') $errs[] = "$qp empty question text";
        if (trim($q['explanation'] ?? '') === '') $errs[] = "$qp missing explanation";
        if (isset($texts[$q['text'] ?? ''])) $errs[] = "$qp duplicate question text"; $texts[$q['text'] ?? ''] = 1;
    }
    foreach ($names as $nm => $cnt) if ($cnt === 0) $errs[] = "$p competency '$nm' has no question";
    if ($sec['knowledge'] < 6 || $sec['situational'] < 4) $errs[] = "$p needs >=6 knowledge and >=4 situational questions (has {$sec['knowledge']}/{$sec['situational']})";
    if ($multi < 1 || $multi > 3) $errs[] = "$p needs 1-3 multiple-answer questions (has $multi)";
    if ($diff['easy'] < 2 || $diff['hard'] < 2) $errs[] = "$p difficulty mix needs >=2 easy and >=2 hard";
    foreach (['description', 'skill_name'] as $k) if (trim($a[$k] ?? '') === '') $errs[] = "$p missing $k";
}
$batchSlugs = array_keys($bySlug);
if ($errs) { echo implode("\n", $errs), "\n"; echo count($errs), " problem(s)\n"; exit(1); }
echo "OK: " . count($d) . " assessment(s): " . implode(', ', array_keys($seen)) . "\n";
