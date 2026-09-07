<?php $e = $assessment ?? null; ?>
<div class="row"><div class="col-lg-9"><div class="card"><div class="card-body">
    <h4 class="page-title"><i class="mdi mdi-clipboard-text title_icon"></i>
        <?php echo $e ? get_phrase('Edit Assessment') : get_phrase('New Assessment'); ?>
        <a href="<?php echo site_url('skill_admin/assessments'); ?>" class="btn btn-link alignToTitle">&larr; <?php echo get_phrase('Assessments'); ?></a>
    </h4>
    <form action="<?php echo site_url('skill_admin/assessment_form' . ($e ? '/' . $e['id'] : '')); ?>" method="post">
        <input type="hidden" name="id" value="<?php echo $e['id'] ?? ''; ?>">
        <div class="mb-3"><label class="form-label"><?php echo get_phrase('Title'); ?> *</label>
            <input type="text" name="title" class="form-control" required value="<?php echo html_escape($e['title'] ?? ''); ?>"></div>
        <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label"><?php echo get_phrase('Skill'); ?> *</label>
                <select name="skill_id" class="form-control" required>
                    <?php foreach ($skills as $s): ?><option value="<?php echo $s['id']; ?>" <?php echo (($e['skill_id'] ?? '') == $s['id']) ? 'selected' : ''; ?>><?php echo html_escape($s['name']); ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-md-6 mb-3"><label class="form-label"><?php echo get_phrase('Level'); ?> *</label>
                <select name="skill_level_id" class="form-control" required>
                    <?php foreach ($levels as $l): ?><option value="<?php echo $l['id']; ?>" <?php echo (($e['skill_level_id'] ?? '') == $l['id']) ? 'selected' : ''; ?>><?php echo html_escape($l['name']); ?></option><?php endforeach; ?>
                </select></div>
        </div>
        <div class="mb-3"><label class="form-label"><?php echo get_phrase('Description'); ?></label>
            <textarea name="description" class="form-control" rows="2"><?php echo html_escape($e['description'] ?? ''); ?></textarea></div>
        <div class="row">
            <div class="col-md-3 mb-3"><label class="form-label"><?php echo get_phrase('Duration (min)'); ?></label>
                <input type="number" name="duration_minutes" class="form-control" value="<?php echo $e['duration_minutes'] ?? 60; ?>"></div>
            <div class="col-md-3 mb-3"><label class="form-label"><?php echo get_phrase('Pass score %'); ?></label>
                <input type="number" step="0.01" name="pass_score" class="form-control" value="<?php echo $e['pass_score'] ?? 70; ?>"></div>
            <div class="col-md-3 mb-3"><label class="form-label" title="<?php echo get_phrase('floor for critical competencies'); ?>"><?php echo get_phrase('Critical min %'); ?></label>
                <input type="number" step="0.01" name="critical_min" class="form-control" value="<?php echo $e['critical_min'] ?? 60; ?>"></div>
            <div class="col-md-3 mb-3"><label class="form-label"><?php echo get_phrase('Status'); ?></label>
                <select name="status" class="form-control">
                    <?php foreach (['draft'=>'Draft','published'=>'Published','retired'=>'Retired'] as $k=>$v): ?>
                    <option value="<?php echo $k; ?>" <?php echo (($e['status'] ?? 'draft') == $k) ? 'selected' : ''; ?>><?php echo get_phrase($v); ?></option><?php endforeach; ?>
                </select></div>
        </div>
        <div class="row">
            <div class="col-md-4 mb-3"><label class="form-label"><?php echo get_phrase('Max attempts / purchase'); ?></label>
                <input type="number" name="max_attempts" class="form-control" value="<?php echo $e['max_attempts'] ?? 1; ?>"></div>
            <div class="col-md-4 mb-3"><label class="form-label"><?php echo get_phrase('Retake cooldown (days)'); ?></label>
                <input type="number" name="cooldown_days" class="form-control" value="<?php echo $e['cooldown_days'] ?? 7; ?>"></div>
        </div>
        <p class="text-muted"><?php echo get_phrase('Level determination: overall ≥ pass AND no critical competency below the critical minimum (spec §17). Standard sections (Knowledge / Situational / Practical) are created automatically.'); ?></p>
        <button type="submit" class="btn btn-primary"><i class="mdi mdi-content-save"></i> <?php echo get_phrase('Save & manage questions'); ?></button>
    </form>
</div></div></div></div>
