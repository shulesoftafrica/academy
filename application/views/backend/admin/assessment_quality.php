<?php
function q_pct($pct, $color = '#0e9f8e')
{
    if ($pct === null) {
        return '<span class="text-muted">—</span>';
    }
    $pct = max(0, min(100, (float) $pct));
    return '<div class="d-flex align-items-center" style="gap:8px;"><div style="flex:1;height:8px;background:#eee;border-radius:6px;overflow:hidden;min-width:50px;">'
         . '<div style="height:100%;width:' . $pct . '%;background:' . $color . ';"></div></div>'
         . '<span style="font-size:.8rem;white-space:nowrap;">' . rtrim(rtrim(number_format($pct, 1), '0'), '.') . '%</span></div>';
}
// A question is worth reviewing if almost everyone (or almost no one) gets it right,
// or if candidates edit it a lot — the QC signal in §42.
function q_flag($row)
{
    $shown = (int) $row['times_shown'];
    if ($shown < 5 || $row['correct_pct'] === null) {
        return '';
    }
    $c = (float) $row['correct_pct'];
    if ($c >= 95) {
        return '<span class="badge bg-light text-muted" title="Almost everyone passes — low discrimination">' . get_phrase('Too easy?') . '</span>';
    }
    if ($c <= 15) {
        return '<span class="badge bg-warning" title="Almost no one passes — check the key/wording">' . get_phrase('Too hard?') . '</span>';
    }
    return '';
}
?>
<div class="row"><div class="col-12"><div class="card"><div class="card-body">
    <h4 class="page-title"><i class="mdi mdi-help-box title_icon"></i>
        <?php echo html_escape($assessment['title'] ?? ''); ?> — <?php echo get_phrase('Question Quality'); ?>
        <a href="<?php echo site_url('skill_admin/analytics'); ?>" class="btn btn-link alignToTitle">&larr; <?php echo get_phrase('Analytics'); ?></a>
    </h4>
    <small class="text-muted"><?php echo get_phrase('How each question behaves across attempts — times shown, correct rate, average attainment and how often candidates change their answer (spec §42).'); ?></small>
</div></div></div></div>

<div class="row"><div class="col-12"><div class="card"><div class="card-body">
    <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr>
            <th><?php echo get_phrase('Question'); ?></th><th><?php echo get_phrase('Type'); ?></th>
            <th class="text-center"><?php echo get_phrase('Shown'); ?></th>
            <th style="width:150px;"><?php echo get_phrase('Correct %'); ?></th>
            <th style="width:150px;"><?php echo get_phrase('Avg score'); ?></th>
            <th class="text-center"><?php echo get_phrase('Avg edits'); ?></th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($questions as $q): ?>
            <tr>
                <td style="max-width:360px;"><?php echo html_escape(mb_substr(strip_tags($q['question_text']), 0, 100)); ?><?php echo mb_strlen(strip_tags($q['question_text'])) > 100 ? '…' : ''; ?></td>
                <td><span class="badge bg-light text-dark"><?php echo html_escape($types[$q['question_type']] ?? $q['question_type']); ?></span></td>
                <td class="text-center"><?php echo (int) $q['times_shown']; ?></td>
                <td><?php echo isset($q['correct_pct']) ? q_pct($q['correct_pct'] === null ? null : (float) $q['correct_pct']) : '<span class="text-muted">—</span>'; ?></td>
                <td><?php echo q_pct($q['avg_pct'] === null ? null : (float) $q['avg_pct'], '#3b7ddd'); ?></td>
                <td class="text-center"><?php echo $q['avg_changes'] === null ? '—' : rtrim(rtrim(number_format((float) $q['avg_changes'], 1), '0'), '.'); ?></td>
                <td class="text-end"><?php echo q_flag($q); ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($questions)): ?><tr><td colspan="7" class="text-center text-muted py-3"><?php echo get_phrase('No questions in this assessment.'); ?></td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <small class="text-muted d-block mt-2"><?php echo get_phrase('Correct % is shown for auto-scored (objective) questions; practical/written questions show average attainment from reviewer scores.'); ?></small>
</div></div></div></div>
