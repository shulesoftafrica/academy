<div class="row"><div class="col-12"><div class="card"><div class="card-body">
    <h4 class="page-title"><i class="mdi mdi-clipboard-check-outline title_icon"></i> <?php echo get_phrase('Practical Review Queue'); ?></h4>
    <small class="text-muted"><?php echo get_phrase('Written and practical responses are scored here against each question\'s rubric (spec §29–§30).'); ?></small>
</div></div></div></div>

<div class="row"><div class="col-12"><div class="card"><div class="card-body">
    <?php
    $tabs = ['pending' => 'Pending', 'in_review' => 'In review', 'approved' => 'Approved', 'rejected' => 'Rejected', 'needs_revision' => 'Needs revision', 'all' => 'All'];
    ?>
    <ul class="nav nav-pills mb-3">
        <?php foreach ($tabs as $key => $label): $c = $key === 'all' ? array_sum($counts) : ($counts[$key] ?? 0); ?>
            <li class="nav-item"><a class="nav-link<?php echo $filter === $key ? ' active' : ''; ?>" style="<?php echo $filter === $key ? 'background:#0e9f8e;' : ''; ?>"
                href="<?php echo site_url('skill_admin/reviews/' . ($key === 'all' ? '' : $key)); ?>">
                <?php echo get_phrase($label); ?> <span class="badge bg-light text-dark"><?php echo (int) $c; ?></span></a></li>
        <?php endforeach; ?>
    </ul>

    <table class="table table-hover mb-0">
        <thead><tr>
            <th><?php echo get_phrase('Candidate'); ?></th><th><?php echo get_phrase('Assessment'); ?></th>
            <th><?php echo get_phrase('Submitted'); ?></th><th><?php echo get_phrase('Items'); ?></th>
            <th><?php echo get_phrase('Status'); ?></th><th class="text-end"></th>
        </tr></thead>
        <tbody>
        <?php foreach ($reviews as $r): ?>
            <tr>
                <td><?php echo html_escape($r['candidate_name'] ?: $r['candidate_email'] ?: ('#' . $r['candidate_id'])); ?>
                    <br><small class="text-muted"><?php echo html_escape($r['candidate_email']); ?></small></td>
                <td><?php echo html_escape($r['skill_name']); ?> — <?php echo html_escape($r['level_name']); ?>
                    <br><small class="text-muted"><?php echo html_escape($r['assessment_title']); ?></small></td>
                <td><small><?php echo $r['submitted_at'] ? date('d M Y H:i', strtotime($r['submitted_at'])) : '-'; ?></small></td>
                <td><?php echo (int) $r['manual_count']; ?></td>
                <td><?php
                    $sb = ['pending'=>'bg-warning','in_review'=>'bg-info','approved'=>'bg-success','rejected'=>'bg-danger','needs_revision'=>'bg-secondary'][$r['status']] ?? 'bg-light';
                    echo '<span class="badge '.$sb.'">'.get_phrase(ucwords(str_replace('_',' ',$r['status']))).'</span>'; ?></td>
                <td class="text-end text-nowrap">
                    <a href="<?php echo site_url('skill_admin/review/' . $r['id']); ?>" class="btn btn-sm btn-outline-primary">
                        <i class="mdi mdi-<?php echo in_array($r['status'],['pending','in_review'])?'pencil-box-outline':'eye-outline'; ?>"></i>
                        <?php echo in_array($r['status'],['pending','in_review']) ? get_phrase('Grade') : get_phrase('View'); ?></a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($reviews)): ?><tr><td colspan="6" class="text-center text-muted py-4"><?php echo get_phrase('Nothing in this queue.'); ?></td></tr><?php endif; ?>
        </tbody>
    </table>
</div></div></div></div>
