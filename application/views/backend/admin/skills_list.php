<div class="row"><div class="col-12"><div class="card"><div class="card-body">
    <h4 class="page-title"><i class="mdi mdi-lightbulb-on title_icon"></i> <?php echo get_phrase('Skills'); ?>
        <a href="<?php echo site_url('skill_admin/skill_form'); ?>" class="btn btn-outline-primary btn-rounded alignToTitle"><i class="mdi mdi-plus"></i> <?php echo get_phrase('Add Skill'); ?></a>
        <a href="<?php echo site_url('skill_admin/dashboard'); ?>" class="btn btn-link alignToTitle">&larr; <?php echo get_phrase('Dashboard'); ?></a>
    </h4>
</div></div></div></div>

<div class="row"><div class="col-12"><div class="card"><div class="card-body">
    <table class="table table-hover mb-0">
        <thead><tr>
            <th><?php echo get_phrase('Skill'); ?></th><th><?php echo get_phrase('Category'); ?></th>
            <th><?php echo get_phrase('Validity'); ?></th><th><?php echo get_phrase('Status'); ?></th>
            <th class="text-end"><?php echo get_phrase('Actions'); ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($skills as $s): ?>
            <tr>
                <td><strong><?php echo html_escape($s['name']); ?></strong><br><small class="text-muted"><?php echo html_escape($s['slug']); ?></small></td>
                <td><?php echo html_escape($s['category_name'] ?: '-'); ?></td>
                <td><?php echo $s['validity_months'] ? ((int) $s['validity_months'] . ' ' . get_phrase('months')) : get_phrase('No expiry'); ?></td>
                <td><?php echo $s['status'] ? '<span class="badge bg-success">'.get_phrase('Active').'</span>' : '<span class="badge bg-secondary">'.get_phrase('Inactive').'</span>'; ?></td>
                <td class="text-end">
                    <a href="<?php echo site_url('skill_admin/competencies/' . $s['id']); ?>" class="btn btn-sm btn-outline-info"><i class="mdi mdi-sitemap"></i> <?php echo get_phrase('Competencies'); ?></a>
                    <a href="<?php echo site_url('skill_admin/skill_form/' . $s['id']); ?>" class="btn btn-sm btn-outline-secondary"><i class="mdi mdi-pencil"></i></a>
                    <a href="javascript:;" onclick="confirm_modal('<?php echo site_url('skill_admin/skills/delete/' . $s['id']); ?>');" class="btn btn-sm btn-outline-danger"><i class="mdi mdi-delete"></i></a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($skills)): ?><tr><td colspan="5" class="text-center text-muted"><?php echo get_phrase('No skills yet.'); ?></td></tr><?php endif; ?>
        </tbody>
    </table>
</div></div></div></div>
