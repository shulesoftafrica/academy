<?php
$editable = in_array($review['status'], ['pending', 'in_review'], true);
$claimed  = $review['status'] !== 'pending';
?>
<div class="row"><div class="col-12"><div class="card"><div class="card-body">
    <h4 class="page-title"><i class="mdi mdi-clipboard-check-outline title_icon"></i>
        <?php echo html_escape($review['skill_name']); ?> — <?php echo html_escape($review['level_name']); ?>
        <a href="<?php echo site_url('skill_admin/reviews'); ?>" class="btn btn-link alignToTitle">&larr; <?php echo get_phrase('Queue'); ?></a>
    </h4>
    <div class="d-flex flex-wrap gap-3 mt-2" style="gap:1.5rem;">
        <span><strong><?php echo get_phrase('Candidate'); ?>:</strong> <?php echo html_escape($review['candidate_name'] ?: $review['candidate_email']); ?></span>
        <span><strong><?php echo get_phrase('Assessment'); ?>:</strong> <?php echo html_escape($review['assessment_title']); ?></span>
        <span><strong><?php echo get_phrase('Submitted'); ?>:</strong> <?php echo $review['submitted_at'] ? date('d M Y H:i', strtotime($review['submitted_at'])) : '-'; ?></span>
        <span><strong><?php echo get_phrase('Status'); ?>:</strong>
            <?php $sb=['pending'=>'bg-warning','in_review'=>'bg-info','approved'=>'bg-success','rejected'=>'bg-danger','needs_revision'=>'bg-secondary'][$review['status']]??'bg-light';
            echo '<span class="badge '.$sb.'">'.get_phrase(ucwords(str_replace('_',' ',$review['status']))).'</span>'; ?></span>
        <span><strong><?php echo get_phrase('Pass mark'); ?>:</strong> <?php echo rtrim(rtrim(number_format($review['pass_score'],2),'0'),'.'); ?>%</span>
    </div>
</div></div></div></div>

<?php if ($review['status'] === 'pending'): ?>
<div class="row"><div class="col-12"><div class="alert alert-warning d-flex justify-content-between align-items-center">
    <span><?php echo get_phrase('Claim this review to start grading — it will be assigned to you.'); ?></span>
    <a href="<?php echo site_url('skill_admin/review/' . $review['id'] . '/claim'); ?>" class="btn btn-warning btn-sm"><i class="mdi mdi-hand-back-right-outline"></i> <?php echo get_phrase('Claim & grade'); ?></a>
</div></div></div>
<?php endif; ?>

<form method="post" action="<?php echo site_url('skill_admin/review/' . $review['id']); ?>">
<?php foreach ($items as $idx => $q): ?>
    <div class="row"><div class="col-12"><div class="card"><div class="card-body">
        <h5 class="mb-1"><span style="color:#0e9f8e;font-weight:700;">Q<?php echo $idx + 1; ?>.</span>
            <?php echo nl2br(html_escape($q['question_text'])); ?>
            <span class="badge bg-light text-dark float-end"><?php echo rtrim(rtrim(number_format($q['points'],2),'0'),'.'); ?> <?php echo get_phrase('pts'); ?></span></h5>
        <?php if (! empty($q['expected_answer'])): ?>
            <details class="mb-2"><summary class="text-muted small"><?php echo get_phrase('Model / expected answer'); ?></summary>
                <div class="small text-muted p-2" style="background:#f7f8fa;border-radius:8px;"><?php echo nl2br(html_escape($q['expected_answer'])); ?></div></details>
        <?php endif; ?>

        <div class="p-3 mb-3" style="background:#f7f8fa;border-radius:10px;border:1px solid #eee;">
            <div class="text-muted small mb-1"><?php echo get_phrase('Candidate response'); ?></div>
            <?php if (trim($q['response_text']) !== ''): ?>
                <div style="white-space:pre-wrap;"><?php echo html_escape($q['response_text']); ?></div>
            <?php elseif (empty($q['submission'])): ?>
                <em class="text-muted"><?php echo get_phrase('No response submitted.'); ?></em>
            <?php endif; ?>
            <?php if (! empty($q['submission'])): $sb = $q['submission']; ?>
                <div class="mt-2 d-inline-flex align-items-center" style="gap:8px;background:#fff;border:1px solid #dbeeea;border-radius:8px;padding:8px 12px;">
                    <i class="mdi mdi-paperclip" style="color:#0b8577;"></i>
                    <a href="<?php echo site_url('skills/submission_file/' . $sb['id']); ?>" target="_blank"><?php echo html_escape($sb['original_name']); ?></a>
                    <span class="text-muted" style="font-size:.8rem;">(<?php echo $sb['file_size'] ? round($sb['file_size'] / 1024) . ' KB' : ''; ?>)</span>
                    <a href="<?php echo site_url('skills/submission_file/' . $sb['id']); ?>" class="btn btn-sm btn-outline-secondary" target="_blank"><i class="mdi mdi-download"></i> <?php echo get_phrase('Download'); ?></a>
                </div>
            <?php endif; ?>
        </div>

        <?php if (! empty($q['rubrics'])): ?>
            <table class="table table-sm mb-0">
                <thead><tr><th><?php echo get_phrase('Criterion'); ?></th><th style="width:130px;"><?php echo get_phrase('Score'); ?></th><th><?php echo get_phrase('Comment'); ?></th></tr></thead>
                <tbody>
                <?php foreach ($q['rubrics'] as $rb): $sv = $q['saved'][$rb['id']] ?? null; ?>
                    <tr>
                        <td><strong><?php echo html_escape($rb['criterion']); ?></strong>
                            <?php if (! empty($rb['description'])): ?><br><small class="text-muted"><?php echo html_escape($rb['description']); ?></small><?php endif; ?></td>
                        <td class="text-nowrap">
                            <input type="number" step="0.5" min="0" max="<?php echo html_escape($rb['max_score']); ?>"
                                   name="score[<?php echo $q['id']; ?>][<?php echo $rb['id']; ?>]"
                                   value="<?php echo $sv ? html_escape(rtrim(rtrim(number_format($sv['score'],2),'0'),'.')) : ''; ?>"
                                   class="form-control form-control-sm d-inline" style="width:80px;" <?php echo $editable ? '' : 'disabled'; ?>>
                            <span class="text-muted">/ <?php echo rtrim(rtrim(number_format($rb['max_score'],2),'0'),'.'); ?></span></td>
                        <td><input type="text" name="comment[<?php echo $q['id']; ?>][<?php echo $rb['id']; ?>]"
                                   value="<?php echo $sv ? html_escape($sv['comment']) : ''; ?>"
                                   class="form-control form-control-sm" <?php echo $editable ? '' : 'disabled'; ?>></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: /* no rubric — score against the whole question */ $sv = $q['saved'][0] ?? null; ?>
            <div class="row g-2 align-items-center">
                <div class="col-auto"><label class="col-form-label"><?php echo get_phrase('Score'); ?></label></div>
                <div class="col-auto"><input type="number" step="0.5" min="0" max="<?php echo html_escape($q['points']); ?>"
                        name="score[<?php echo $q['id']; ?>][0]" value="<?php echo $sv ? html_escape(rtrim(rtrim(number_format($sv['score'],2),'0'),'.')) : ''; ?>"
                        class="form-control" style="width:100px;" <?php echo $editable ? '' : 'disabled'; ?>>
                    <span class="text-muted">/ <?php echo rtrim(rtrim(number_format($q['points'],2),'0'),'.'); ?></span></div>
                <div class="col"><input type="text" name="comment[<?php echo $q['id']; ?>][0]" placeholder="<?php echo get_phrase('Comment'); ?>"
                        value="<?php echo $sv ? html_escape($sv['comment']) : ''; ?>" class="form-control" <?php echo $editable ? '' : 'disabled'; ?>></div>
            </div>
        <?php endif; ?>
    </div></div></div></div>
<?php endforeach; ?>

<?php if (empty($items)): ?>
    <div class="row"><div class="col-12"><div class="alert alert-info"><?php echo get_phrase('This attempt has no manual questions to review.'); ?></div></div></div>
<?php endif; ?>

<div class="row"><div class="col-12"><div class="card"><div class="card-body">
    <div class="mb-3">
        <label class="form-label"><?php echo get_phrase('Reviewer notes'); ?></label>
        <textarea name="notes" rows="2" class="form-control" <?php echo $editable ? '' : 'disabled'; ?>><?php echo html_escape($review['notes']); ?></textarea>
    </div>
    <?php if ($editable && $claimed): ?>
        <button type="submit" name="decision" value="save" class="btn btn-outline-secondary"><i class="mdi mdi-content-save"></i> <?php echo get_phrase('Save draft'); ?></button>
        <button type="submit" name="decision" value="approve" class="btn btn-success"><i class="mdi mdi-check-bold"></i> <?php echo get_phrase('Approve & finalize result'); ?></button>
        <button type="submit" name="decision" value="revision" class="btn btn-outline-warning"><i class="mdi mdi-backup-restore"></i> <?php echo get_phrase('Needs revision'); ?></button>
        <button type="submit" name="decision" value="reject" class="btn btn-outline-danger" onclick="return confirm('<?php echo get_phrase('Reject and invalidate this attempt?'); ?>');"><i class="mdi mdi-close-thick"></i> <?php echo get_phrase('Reject'); ?></button>
    <?php elseif (! $editable): ?>
        <span class="text-muted"><?php echo get_phrase('This review is closed.'); ?>
            <?php if ($review['attempt_result']): ?>— <?php echo get_phrase('Outcome'); ?>: <strong><?php echo get_phrase(ucwords(str_replace('_',' ',$review['attempt_result']))); ?></strong>
            (<?php echo rtrim(rtrim(number_format($review['attempt_score'],2),'0'),'.'); ?>%)<?php endif; ?></span>
    <?php endif; ?>
</div></div></div></div>
</form>
