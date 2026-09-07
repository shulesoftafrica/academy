<?php
function pct_bar($pct, $color = '#0e9f8e')
{
    if ($pct === null) {
        return '<span class="text-muted">—</span>';
    }
    $pct = max(0, min(100, (float) $pct));
    $label = rtrim(rtrim(number_format($pct, 1), '0'), '.') . '%';
    return '<div class="d-flex align-items-center" style="gap:8px;"><div style="flex:1;height:8px;background:#eee;border-radius:6px;overflow:hidden;min-width:60px;">'
         . '<div style="height:100%;width:' . $pct . '%;background:' . $color . ';"></div></div>'
         . '<span style="font-size:.8rem;white-space:nowrap;">' . $label . '</span></div>';
}
function dur_label($secs)
{
    if ($secs === null) {
        return '—';
    }
    $m = floor($secs / 60);
    return $m >= 1 ? $m . 'm' : round($secs) . 's';
}
?>
<div class="row"><div class="col-12"><div class="card"><div class="card-body">
    <h4 class="page-title"><i class="mdi mdi-chart-bar title_icon"></i> <?php echo get_phrase('Skills Assessment Analytics'); ?>
        <a href="<?php echo site_url('skill_admin/dashboard'); ?>" class="btn btn-link alignToTitle">&larr; <?php echo get_phrase('Dashboard'); ?></a>
    </h4>
    <div class="d-flex flex-wrap gap-4 mt-2" style="gap:2rem;">
        <div><div class="text-muted" style="font-size:.75rem;"><?php echo get_phrase('Attempts'); ?></div><h4 class="mb-0"><?php echo (int) $metrics['attempts']; ?></h4></div>
        <div><div class="text-muted" style="font-size:.75rem;"><?php echo get_phrase('Pass Rate'); ?></div><h4 class="mb-0"><?php echo $metrics['pass_rate'] === null ? '—' : $metrics['pass_rate'] . '%'; ?></h4></div>
        <div><div class="text-muted" style="font-size:.75rem;"><?php echo get_phrase('Avg Score'); ?></div><h4 class="mb-0"><?php echo $metrics['avg_score'] === null ? '—' : $metrics['avg_score'] . '/100'; ?></h4></div>
        <div><div class="text-muted" style="font-size:.75rem;"><?php echo get_phrase('Credentials'); ?></div><h4 class="mb-0"><?php echo (int) $metrics['credentials']; ?></h4></div>
        <div><div class="text-muted" style="font-size:.75rem;"><?php echo get_phrase('Revenue'); ?></div><h4 class="mb-0">TZS <?php echo number_format((float) $metrics['revenue']); ?></h4></div>
        <div><div class="text-muted" style="font-size:.75rem;"><?php echo get_phrase('Integrity Flags'); ?></div><h4 class="mb-0 <?php echo $metrics['integrity_flags'] ? 'text-danger' : ''; ?>"><?php echo (int) $metrics['integrity_flags']; ?></h4></div>
    </div>
</div></div></div></div>

<div class="row"><div class="col-12"><div class="card"><div class="card-body">
    <h5 class="mb-3"><?php echo get_phrase('Per-assessment performance'); ?></h5>
    <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr>
            <th><?php echo get_phrase('Assessment'); ?></th><th class="text-center"><?php echo get_phrase('Attempts'); ?></th>
            <th style="width:160px;"><?php echo get_phrase('Pass rate'); ?></th><th style="width:160px;"><?php echo get_phrase('Avg score'); ?></th>
            <th class="text-center"><?php echo get_phrase('Avg time'); ?></th><th class="text-center"><?php echo get_phrase('Retake'); ?></th><th class="text-center"><?php echo get_phrase('In review'); ?></th>
            <th class="text-end"><?php echo get_phrase('Revenue'); ?></th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($assessments as $a): ?>
            <tr>
                <td><?php echo html_escape($a['skill_name']); ?> — <?php echo html_escape($a['level_name']); ?>
                    <br><small class="text-muted"><?php echo html_escape($a['title']); ?> · v<?php echo (int) $a['version']; ?> · <?php echo html_escape($a['status']); ?></small></td>
                <td class="text-center"><?php echo (int) $a['attempts']; ?></td>
                <td><?php echo pct_bar($a['pass_rate']); ?></td>
                <td><?php echo pct_bar($a['avg_score'] === null ? null : (float) $a['avg_score'], '#3b7ddd'); ?></td>
                <td class="text-center"><?php echo dur_label($a['avg_seconds'] === null ? null : (float) $a['avg_seconds']); ?></td>
                <td class="text-center"><?php echo $a['retake_rate'] === null ? '—' : rtrim(rtrim(number_format($a['retake_rate'], 1), '0'), '.') . '%'; ?></td>
                <td class="text-center"><?php echo (int) $a['in_review'] ? '<span class="badge bg-warning">' . (int) $a['in_review'] . '</span>' : '0'; ?></td>
                <td class="text-end">TZS <?php echo number_format((float) $a['revenue']); ?></td>
                <td class="text-end"><a href="<?php echo site_url('skill_admin/analytics/' . $a['id']); ?>" class="btn btn-sm btn-outline-secondary"><?php echo get_phrase('Questions'); ?></a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($assessments)): ?><tr><td colspan="9" class="text-center text-muted py-3"><?php echo get_phrase('No assessments yet.'); ?></td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div></div></div></div>

<div class="row"><div class="col-12"><div class="card"><div class="card-body">
    <h5 class="mb-3"><?php echo get_phrase('Per-competency attainment'); ?></h5>
    <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr>
            <th><?php echo get_phrase('Competency'); ?></th><th><?php echo get_phrase('Skill'); ?></th>
            <th class="text-center"><?php echo get_phrase('Critical'); ?></th><th class="text-center"><?php echo get_phrase('Weight'); ?></th>
            <th class="text-center"><?php echo get_phrase('Attempts'); ?></th><th style="width:200px;"><?php echo get_phrase('Avg attainment'); ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($competencies as $c): ?>
            <tr>
                <td><?php echo html_escape($c['name']); ?></td>
                <td><?php echo html_escape($c['skill_name']); ?></td>
                <td class="text-center"><?php echo (int) $c['is_critical'] ? '<span class="badge bg-danger">' . get_phrase('Critical') . '</span>' : '<span class="text-muted">—</span>'; ?></td>
                <td class="text-center"><?php echo rtrim(rtrim(number_format((float) $c['weight'], 2), '0'), '.'); ?></td>
                <td class="text-center"><?php echo (int) $c['attempts']; ?></td>
                <td><?php echo pct_bar($c['avg_pct'] === null ? null : (float) $c['avg_pct']); ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($competencies)): ?><tr><td colspan="6" class="text-center text-muted py-3"><?php echo get_phrase('No competencies yet.'); ?></td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div></div></div></div>
