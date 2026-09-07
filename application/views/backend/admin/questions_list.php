<div class="row"><div class="col-12"><div class="card"><div class="card-body">
    <h4 class="page-title"><i class="mdi mdi-help-box title_icon"></i>
        <?php echo html_escape($assessment['title'] ?? ''); ?> — <?php echo get_phrase('Question Bank'); ?>
        <a href="<?php echo site_url('skill_admin/question_form/' . $assessment['id']); ?>" class="btn btn-outline-primary btn-rounded alignToTitle"><i class="mdi mdi-plus"></i> <?php echo get_phrase('Add Question'); ?></a>
        <a href="<?php echo site_url('skill_admin/assessments'); ?>" class="btn btn-link alignToTitle">&larr; <?php echo get_phrase('Assessments'); ?></a>
    </h4>
    <small class="text-muted"><?php echo get_phrase('Only Approved/Published questions appear in live assessments (spec §14).'); ?></small>
    <a href="javascript:;" onclick="var e=document.getElementById('ai-gen');e.style.display=e.style.display==='none'?'block':'none';" class="btn btn-outline-primary btn-rounded alignToTitle"><i class="mdi mdi-robot-happy"></i> <?php echo get_phrase('Generate with AI'); ?></a>
</div></div></div></div>

<div class="row" id="ai-gen" style="display:none;"><div class="col-12"><div class="card" style="border:1px solid #cfe3de;"><div class="card-body">
    <h5 class="mb-1"><i class="mdi mdi-robot-happy" style="color:#0e9f8e;"></i> <?php echo get_phrase('AI Question Generator'); ?></h5>
    <p class="text-muted mb-3" style="font-size:.86rem;"><?php echo get_phrase('AI drafts questions for you to review. They are saved as Draft and must be reviewed and approved before they appear in a live assessment (spec §14).'); ?>
        <?php if (empty($ai_ready)): ?><br><span class="text-danger"><i class="mdi mdi-alert"></i> <?php echo get_phrase('OpenAI key not configured — generation is disabled.'); ?></span><?php endif; ?></p>
    <form method="post" action="<?php echo site_url('skill_admin/generate_questions/' . $assessment['id']); ?>">
        <div class="row g-2">
            <div class="col-md-3">
                <label class="form-label mb-1"><?php echo get_phrase('Competency'); ?></label>
                <select name="competency_id" class="form-control form-control-sm">
                    <option value=""><?php echo get_phrase('— general —'); ?></option>
                    <?php foreach ($competencies as $c): ?><option value="<?php echo $c['id']; ?>"><?php echo html_escape($c['name']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1"><?php echo get_phrase('Level'); ?></label>
                <select name="skill_level_id" class="form-control form-control-sm">
                    <?php foreach ($levels as $l): ?><option value="<?php echo $l['id']; ?>" <?php echo $l['id'] == $assessment['skill_level_id'] ? 'selected' : ''; ?>><?php echo html_escape($l['name']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1"><?php echo get_phrase('Type'); ?></label>
                <select name="question_type" class="form-control form-control-sm">
                    <?php foreach (['single','multiple','truefalse','short_text','long_text'] as $t): ?><option value="<?php echo $t; ?>"><?php echo html_escape($types[$t] ?? $t); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1"><?php echo get_phrase('Difficulty'); ?></label>
                <select name="difficulty" class="form-control form-control-sm">
                    <?php foreach ($difficulties as $d): ?><option value="<?php echo $d; ?>" <?php echo $d === 'medium' ? 'selected' : ''; ?>><?php echo html_escape(ucfirst($d)); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label mb-1"><?php echo get_phrase('How many'); ?></label>
                <input type="number" name="count" value="3" min="1" max="10" class="form-control form-control-sm">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary btn-sm w-100" <?php echo empty($ai_ready) ? 'disabled' : ''; ?>><i class="mdi mdi-creation"></i> <?php echo get_phrase('Generate'); ?></button>
            </div>
        </div>
        <div class="mt-2">
            <input type="text" name="topic" class="form-control form-control-sm" placeholder="<?php echo get_phrase('Optional focus / topic (e.g. handling price objections)'); ?>">
        </div>
        <small class="text-muted d-block mt-2"><i class="mdi mdi-information-outline"></i> <?php echo get_phrase('Generation may take 10–30 seconds.'); ?></small>
    </form>
</div></div></div></div>

<div class="row"><div class="col-12"><div class="card"><div class="card-body">
    <table class="table table-hover mb-0">
        <thead><tr>
            <th><?php echo get_phrase('Question'); ?></th><th><?php echo get_phrase('Type'); ?></th>
            <th><?php echo get_phrase('Competency'); ?></th><th><?php echo get_phrase('Diff'); ?></th>
            <th><?php echo get_phrase('Pts'); ?></th><th><?php echo get_phrase('Status'); ?></th><th class="text-end"></th>
        </tr></thead>
        <tbody>
        <?php foreach ($questions as $q): ?>
            <tr>
                <td style="max-width:340px;"><?php echo html_escape(mb_substr(strip_tags($q['question_text']),0,90)); ?><?php echo mb_strlen(strip_tags($q['question_text']))>90?'…':''; ?>
                    <?php if ($q['option_count']): ?><br><small class="text-muted"><?php echo (int)$q['option_count']; ?> <?php echo get_phrase('options'); ?></small><?php endif; ?></td>
                <td><span class="badge bg-light text-dark"><?php echo html_escape($types[$q['question_type']] ?? $q['question_type']); ?></span></td>
                <td><?php echo html_escape($q['competency_name'] ?: '-'); ?></td>
                <td><?php echo html_escape(ucfirst($q['difficulty'])); ?></td>
                <td><?php echo rtrim(rtrim(number_format($q['points'],2),'0'),'.'); ?></td>
                <td><?php
                    $sb = ['draft'=>'bg-secondary','reviewed'=>'bg-info','approved'=>'bg-primary','published'=>'bg-success','archived'=>'bg-dark'][$q['status']] ?? 'bg-light';
                    echo '<span class="badge '.$sb.'">'.get_phrase(ucfirst($q['status'])).'</span>'; ?></td>
                <td class="text-end text-nowrap">
                    <?php
                    $next = ['draft'=>'reviewed','reviewed'=>'approved','approved'=>'published'];
                    if (isset($next[$q['status']])): ?>
                        <a href="<?php echo site_url('skill_admin/questions/' . $assessment['id'] . '/status/' . $q['id']) . '?to=' . $next[$q['status']]; ?>" class="btn btn-sm btn-outline-success" title="<?php echo get_phrase('Advance to') . ' ' . $next[$q['status']]; ?>"><i class="mdi mdi-arrow-right-bold"></i> <?php echo get_phrase(ucfirst($next[$q['status']])); ?></a>
                    <?php endif; ?>
                    <?php if ($q['status'] === 'archived'): ?>
                        <a href="<?php echo site_url('skill_admin/questions/' . $assessment['id'] . '/restore/' . $q['id']); ?>" class="btn btn-sm btn-outline-info" title="<?php echo get_phrase('Restore to draft'); ?>"><i class="mdi mdi-backup-restore"></i> <?php echo get_phrase('Restore'); ?></a>
                    <?php endif; ?>
                    <a href="<?php echo site_url('skill_admin/question_form/' . $assessment['id'] . '/' . $q['id']); ?>" class="btn btn-sm btn-outline-secondary" title="<?php echo get_phrase('Edit'); ?>"><i class="mdi mdi-pencil"></i></a>
                    <a href="<?php echo site_url('skill_admin/questions/' . $assessment['id'] . '/duplicate/' . $q['id']); ?>" class="btn btn-sm btn-outline-secondary" title="<?php echo get_phrase('Duplicate'); ?>"><i class="mdi mdi-content-copy"></i></a>
                    <?php if ($q['status'] !== 'archived'): ?>
                        <a href="javascript:;" onclick="confirm_modal('<?php echo site_url('skill_admin/questions/' . $assessment['id'] . '/archive/' . $q['id']); ?>');" class="btn btn-sm btn-outline-warning" title="<?php echo get_phrase('Archive'); ?>"><i class="mdi mdi-archive"></i></a>
                    <?php endif; ?>
                    <a href="javascript:;" onclick="confirm_modal('<?php echo site_url('skill_admin/questions/' . $assessment['id'] . '/delete/' . $q['id']); ?>');" class="btn btn-sm btn-outline-danger" title="<?php echo get_phrase('Delete'); ?>"><i class="mdi mdi-delete"></i></a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($questions)): ?><tr><td colspan="7" class="text-center text-muted"><?php echo get_phrase('No questions yet — add one.'); ?></td></tr><?php endif; ?>
        </tbody>
    </table>
</div></div></div></div>
