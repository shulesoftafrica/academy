<?php $e = $edit ?? null; ?>
<div class="row"><div class="col-12"><div class="card"><div class="card-body">
    <h4 class="page-title"><i class="mdi mdi-sitemap title_icon"></i>
        <?php echo html_escape($skill['name'] ?? ''); ?> — <?php echo get_phrase('Competency Framework'); ?>
        <a href="<?php echo site_url('skill_admin/skills'); ?>" class="btn btn-link alignToTitle">&larr; <?php echo get_phrase('Skills'); ?></a>
    </h4>
    <?php $wt = (float) $weight_total; ?>
    <span class="badge <?php echo abs($wt - 100) < 0.01 ? 'bg-success' : 'bg-warning'; ?>">
        <?php echo get_phrase('Total weight'); ?>: <?php echo rtrim(rtrim(number_format($wt, 2), '0'), '.'); ?>%
        <?php echo abs($wt - 100) < 0.01 ? '' : ' — ' . get_phrase('should total 100%'); ?>
    </span>
</div></div></div></div>

<div class="row">
    <div class="col-lg-8">
        <div class="card"><div class="card-body">
            <h5 class="mb-3"><?php echo get_phrase('Competencies'); ?> &amp; <?php echo get_phrase('Level Standards'); ?> (<?php echo get_phrase('minimum %'); ?>)</h5>
            <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
                <thead><tr>
                    <th><?php echo get_phrase('Competency'); ?></th><th><?php echo get_phrase('Weight'); ?></th><th><?php echo get_phrase('Critical'); ?></th>
                    <?php foreach ($levels as $l): ?><th class="text-center" title="<?php echo html_escape($l['name']); ?>"><?php echo html_escape(substr($l['name'],0,4)); ?></th><?php endforeach; ?>
                    <th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($competencies as $c): ?>
                    <form action="<?php echo site_url('skill_admin/competencies/' . $skill['id'] . '/standards'); ?>" method="post">
                    <input type="hidden" name="competency_id" value="<?php echo $c['id']; ?>">
                    <tr>
                        <td><strong><?php echo html_escape($c['name']); ?></strong></td>
                        <td><?php echo rtrim(rtrim(number_format($c['weight'],2),'0'),'.'); ?>%</td>
                        <td class="text-center"><?php echo $c['is_critical'] ? '<span class="badge bg-danger">'.get_phrase('Yes').'</span>' : '<span class="text-muted">—</span>'; ?></td>
                        <?php foreach ($levels as $l): ?>
                            <td style="width:60px;"><input type="number" name="minimum_score[<?php echo $l['id']; ?>]" class="form-control form-control-sm text-center" min="0" max="100"
                                value="<?php echo isset($standards[$c['id']][$l['id']]) ? rtrim(rtrim(number_format($standards[$c['id']][$l['id']],2),'0'),'.') : ''; ?>"></td>
                        <?php endforeach; ?>
                        <td class="text-nowrap">
                            <button type="submit" class="btn btn-sm btn-outline-primary" title="<?php echo get_phrase('Save standards'); ?>"><i class="mdi mdi-content-save"></i></button>
                            <a href="<?php echo site_url('skill_admin/competencies/' . $skill['id'] . '/edit/' . $c['id']); ?>" class="btn btn-sm btn-outline-secondary"><i class="mdi mdi-pencil"></i></a>
                            <a href="javascript:;" onclick="confirm_modal('<?php echo site_url('skill_admin/competencies/' . $skill['id'] . '/delete/' . $c['id']); ?>');" class="btn btn-sm btn-outline-danger"><i class="mdi mdi-delete"></i></a>
                        </td>
                    </tr>
                    </form>
                <?php endforeach; ?>
                <?php if (empty($competencies)): ?><tr><td colspan="<?php echo 4 + count($levels); ?>" class="text-center text-muted"><?php echo get_phrase('No competencies yet — add one.'); ?></td></tr><?php endif; ?>
                </tbody>
            </table>
            </div>
            <small class="text-muted d-block mt-2"><?php echo get_phrase('Critical competencies must meet their minimum at the awarded level, or the level is not verified (spec §17). Save each row after editing its standards.'); ?></small>
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card"><div class="card-body">
            <h5><?php echo $e ? get_phrase('Edit Competency') : get_phrase('Add Competency'); ?></h5>
            <form action="<?php echo site_url('skill_admin/competencies/' . $skill['id'] . '/save'); ?>" method="post">
                <input type="hidden" name="id" value="<?php echo $e['id'] ?? ''; ?>">
                <div class="mb-2"><label class="form-label"><?php echo get_phrase('Name'); ?> *</label>
                    <input type="text" name="name" class="form-control" required value="<?php echo html_escape($e['name'] ?? ''); ?>"></div>
                <div class="mb-2"><label class="form-label"><?php echo get_phrase('Description'); ?></label>
                    <input type="text" name="description" class="form-control" value="<?php echo html_escape($e['description'] ?? ''); ?>"></div>
                <div class="row">
                    <div class="col-6 mb-2"><label class="form-label"><?php echo get_phrase('Weight %'); ?></label>
                        <input type="number" step="0.01" name="weight" class="form-control" value="<?php echo $e['weight'] ?? 0; ?>"></div>
                    <div class="col-6 mb-2"><label class="form-label"><?php echo get_phrase('Order'); ?></label>
                        <input type="number" name="sort_order" class="form-control" value="<?php echo $e['sort_order'] ?? 0; ?>"></div>
                </div>
                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" name="is_critical" value="1" id="crit" <?php echo (!empty($e['is_critical'])) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="crit"><?php echo get_phrase('Critical competency'); ?></label>
                </div>
                <button type="submit" class="btn btn-primary"><?php echo get_phrase('Save'); ?></button>
                <?php if ($e): ?><a href="<?php echo site_url('skill_admin/competencies/' . $skill['id']); ?>" class="btn btn-light"><?php echo get_phrase('Cancel'); ?></a><?php endif; ?>
            </form>
        </div></div>
    </div>
</div>
