<div class="row"><div class="col-12"><div class="card"><div class="card-body">
    <h4 class="page-title"><i class="mdi mdi-clipboard-text title_icon"></i> <?php echo get_phrase('Assessments'); ?>
        <a href="<?php echo site_url('skill_admin/assessment_form'); ?>" class="btn btn-outline-primary btn-rounded alignToTitle"><i class="mdi mdi-plus"></i> <?php echo get_phrase('New Assessment'); ?></a>
        <a href="<?php echo site_url('skill_admin/dashboard'); ?>" class="btn btn-link alignToTitle">&larr; <?php echo get_phrase('Dashboard'); ?></a>
    </h4>
</div></div></div></div>

<div class="row"><div class="col-12"><div class="card"><div class="card-body">
    <table class="table table-hover mb-0">
        <thead><tr>
            <th><?php echo get_phrase('Assessment'); ?></th><th><?php echo get_phrase('Skill / Level'); ?></th>
            <th><?php echo get_phrase('Questions'); ?></th><th><?php echo get_phrase('Pass'); ?></th>
            <th><?php echo get_phrase('Version'); ?></th><th><?php echo get_phrase('Status'); ?></th>
            <th class="text-end"></th>
        </tr></thead>
        <tbody>
        <?php foreach ($assessments as $a): ?>
            <tr>
                <td><strong><?php echo html_escape($a['title']); ?></strong><br><small class="text-muted"><?php echo (int) $a['duration_minutes']; ?> <?php echo get_phrase('min'); ?></small></td>
                <td><?php echo html_escape($a['skill_name']); ?> — <?php echo html_escape($a['level_name']); ?></td>
                <td><?php echo (int) $a['question_count']; ?></td>
                <td><?php echo rtrim(rtrim(number_format($a['pass_score'],2),'0'),'.'); ?>% <small class="text-muted">(crit <?php echo rtrim(rtrim(number_format($a['critical_min'],2),'0'),'.'); ?>%)</small></td>
                <td>v<?php echo (int) $a['version']; ?></td>
                <td><?php
                    $badge = ['published'=>'bg-success','draft'=>'bg-warning','retired'=>'bg-secondary'][$a['status']] ?? 'bg-light';
                    echo '<span class="badge '.$badge.'">'.get_phrase(ucfirst($a['status'])).'</span>'; ?></td>
                <td class="text-end text-nowrap">
                    <a href="<?php echo site_url('skill_admin/questions/' . $a['id']); ?>" class="btn btn-sm btn-outline-info"><i class="mdi mdi-help-box"></i> <?php echo get_phrase('Questions'); ?></a>
                    <a href="<?php echo site_url('skill_admin/assessment_form/' . $a['id']); ?>" class="btn btn-sm btn-outline-secondary"><i class="mdi mdi-pencil"></i></a>
                    <?php if ($a['status'] === 'draft'): ?>
                        <a href="<?php echo site_url('skill_admin/assessments/publish/' . $a['id']); ?>" class="btn btn-sm btn-outline-success" onclick="return confirm('Publish this assessment?');"><i class="mdi mdi-publish"></i></a>
                    <?php else: ?>
                        <a href="<?php echo site_url('skill_admin/assessments/new_version/' . $a['id']); ?>" class="btn btn-sm btn-outline-warning" title="<?php echo get_phrase('New version'); ?>" onclick="return confirm('Create a new draft version? Existing credentials keep their version.');"><i class="mdi mdi-content-copy"></i> v<?php echo ((int)$a['version']+1); ?></a>
                    <?php endif; ?>
                    <a href="javascript:;" onclick="confirm_modal('<?php echo site_url('skill_admin/assessments/delete/' . $a['id']); ?>');" class="btn btn-sm btn-outline-danger"><i class="mdi mdi-delete"></i></a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($assessments)): ?><tr><td colspan="7" class="text-center text-muted"><?php echo get_phrase('No assessments yet.'); ?></td></tr><?php endif; ?>
        </tbody>
    </table>
</div></div></div></div>
