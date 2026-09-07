<?php $e = $skill ?? null; ?>
<div class="row"><div class="col-lg-8"><div class="card"><div class="card-body">
    <h4 class="page-title"><i class="mdi mdi-lightbulb-on title_icon"></i>
        <?php echo $e ? get_phrase('Edit Skill') : get_phrase('Add Skill'); ?>
        <a href="<?php echo site_url('skill_admin/skills'); ?>" class="btn btn-link alignToTitle">&larr; <?php echo get_phrase('Back'); ?></a>
    </h4>

    <form action="<?php echo site_url('skill_admin/skill_form' . ($e ? '/' . $e['id'] : '')); ?>" method="post">
        <input type="hidden" name="id" value="<?php echo $e['id'] ?? ''; ?>">
        <div class="mb-3">
            <label class="form-label"><?php echo get_phrase('Skill name'); ?> *</label>
            <input type="text" name="name" class="form-control" required value="<?php echo html_escape($e['name'] ?? ''); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label"><?php echo get_phrase('Slug'); ?></label>
            <input type="text" name="slug" class="form-control" placeholder="auto from name" value="<?php echo html_escape($e['slug'] ?? ''); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label"><?php echo get_phrase('Category'); ?></label>
            <select name="category_id" class="form-control">
                <option value=""><?php echo get_phrase('— none —'); ?></option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo (($e['category_id'] ?? '') == $c['id']) ? 'selected' : ''; ?>><?php echo html_escape($c['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label"><?php echo get_phrase('Description'); ?></label>
            <textarea name="description" class="form-control" rows="3"><?php echo html_escape($e['description'] ?? ''); ?></textarea>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label"><?php echo get_phrase('Credential validity (months)'); ?></label>
                <input type="number" name="validity_months" class="form-control" min="0" placeholder="blank = never expires" value="<?php echo $e['validity_months'] ?? ''; ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label"><?php echo get_phrase('Status'); ?></label>
                <select name="status" class="form-control">
                    <option value="1" <?php echo (($e['status'] ?? 1) == 1) ? 'selected' : ''; ?>><?php echo get_phrase('Active'); ?></option>
                    <option value="0" <?php echo (($e['status'] ?? 1) == 0) ? 'selected' : ''; ?>><?php echo get_phrase('Inactive'); ?></option>
                </select>
            </div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="mdi mdi-content-save"></i> <?php echo get_phrase('Save & manage competencies'); ?></button>
    </form>
</div></div></div></div>
