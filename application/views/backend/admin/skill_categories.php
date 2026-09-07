<?php $e = $edit ?? null; ?>
<div class="row">
    <div class="col-lg-7">
        <div class="card"><div class="card-body">
            <h4 class="page-title"><i class="mdi mdi-shape title_icon"></i> <?php echo get_phrase('Skill Categories'); ?>
                <a href="<?php echo site_url('skill_admin/dashboard'); ?>" class="btn btn-link alignToTitle">&larr; <?php echo get_phrase('Dashboard'); ?></a>
            </h4>
            <table class="table table-hover mb-0">
                <thead><tr><th><?php echo get_phrase('Name'); ?></th><th><?php echo get_phrase('Order'); ?></th><th><?php echo get_phrase('Status'); ?></th><th class="text-end"></th></tr></thead>
                <tbody>
                <?php foreach ($categories as $c): ?>
                    <tr>
                        <td><i class="<?php echo html_escape($c['icon']); ?>"></i> <?php echo html_escape($c['name']); ?><br><small class="text-muted"><?php echo html_escape($c['slug']); ?></small></td>
                        <td><?php echo (int) $c['sort_order']; ?></td>
                        <td><?php echo $c['status'] ? get_phrase('Active') : get_phrase('Inactive'); ?></td>
                        <td class="text-end">
                            <a href="<?php echo site_url('skill_admin/categories/edit/' . $c['id']); ?>" class="btn btn-sm btn-outline-secondary"><i class="mdi mdi-pencil"></i></a>
                            <a href="javascript:;" onclick="confirm_modal('<?php echo site_url('skill_admin/categories/delete/' . $c['id']); ?>');" class="btn btn-sm btn-outline-danger"><i class="mdi mdi-delete"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div></div>
    </div>
    <div class="col-lg-5">
        <div class="card"><div class="card-body">
            <h5><?php echo $e ? get_phrase('Edit Category') : get_phrase('Add Category'); ?></h5>
            <form action="<?php echo site_url('skill_admin/categories/save'); ?>" method="post">
                <input type="hidden" name="id" value="<?php echo $e['id'] ?? ''; ?>">
                <div class="mb-2"><label class="form-label"><?php echo get_phrase('Name'); ?> *</label>
                    <input type="text" name="name" class="form-control" required value="<?php echo html_escape($e['name'] ?? ''); ?>"></div>
                <div class="mb-2"><label class="form-label"><?php echo get_phrase('Slug'); ?></label>
                    <input type="text" name="slug" class="form-control" value="<?php echo html_escape($e['slug'] ?? ''); ?>"></div>
                <div class="mb-2"><label class="form-label"><?php echo get_phrase('Icon (mdi/fa class)'); ?></label>
                    <input type="text" name="icon" class="form-control" placeholder="mdi mdi-briefcase" value="<?php echo html_escape($e['icon'] ?? ''); ?>"></div>
                <div class="row">
                    <div class="col-6 mb-2"><label class="form-label"><?php echo get_phrase('Order'); ?></label>
                        <input type="number" name="sort_order" class="form-control" value="<?php echo $e['sort_order'] ?? 0; ?>"></div>
                    <div class="col-6 mb-2"><label class="form-label"><?php echo get_phrase('Status'); ?></label>
                        <select name="status" class="form-control">
                            <option value="1" <?php echo (($e['status'] ?? 1) == 1) ? 'selected' : ''; ?>><?php echo get_phrase('Active'); ?></option>
                            <option value="0" <?php echo (($e['status'] ?? 1) == 0) ? 'selected' : ''; ?>><?php echo get_phrase('Inactive'); ?></option>
                        </select></div>
                </div>
                <button type="submit" class="btn btn-primary"><?php echo get_phrase('Save'); ?></button>
                <?php if ($e): ?><a href="<?php echo site_url('skill_admin/categories'); ?>" class="btn btn-light"><?php echo get_phrase('Cancel'); ?></a><?php endif; ?>
            </form>
        </div></div>
    </div>
</div>
