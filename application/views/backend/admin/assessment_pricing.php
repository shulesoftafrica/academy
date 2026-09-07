<?php $e = $edit ?? null; ?>
<div class="row">
    <div class="col-lg-7">
        <div class="card"><div class="card-body">
            <h4 class="page-title"><i class="mdi mdi-cash title_icon"></i> <?php echo get_phrase('Assessment Pricing'); ?>
                <a href="<?php echo site_url('skill_admin/dashboard'); ?>" class="btn btn-link alignToTitle">&larr; <?php echo get_phrase('Dashboard'); ?></a>
            </h4>
            <p class="text-muted"><?php echo get_phrase('Prices are configurable per skill and level (spec §6 — not hard-coded).'); ?></p>
            <table class="table table-hover mb-0">
                <thead><tr><th><?php echo get_phrase('Skill'); ?></th><th><?php echo get_phrase('Level'); ?></th><th><?php echo get_phrase('Price'); ?></th><th><?php echo get_phrase('Retake'); ?></th><th><?php echo get_phrase('Active'); ?></th><th class="text-end"></th></tr></thead>
                <tbody>
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td><?php echo html_escape($p['skill_name']); ?></td>
                        <td><?php echo html_escape($p['level_name']); ?></td>
                        <td><?php echo $p['currency'] . ' ' . number_format((float) $p['price']); ?></td>
                        <td><?php echo $p['retake_price'] !== null ? ($p['currency'] . ' ' . number_format((float) $p['retake_price'])) : '—'; ?></td>
                        <td><?php echo $p['active'] ? '<span class="badge bg-success">'.get_phrase('Yes').'</span>' : '<span class="badge bg-secondary">'.get_phrase('No').'</span>'; ?></td>
                        <td class="text-end">
                            <a href="<?php echo site_url('skill_admin/pricing/edit/' . $p['id']); ?>" class="btn btn-sm btn-outline-secondary"><i class="mdi mdi-pencil"></i></a>
                            <a href="javascript:;" onclick="confirm_modal('<?php echo site_url('skill_admin/pricing/delete/' . $p['id']); ?>');" class="btn btn-sm btn-outline-danger"><i class="mdi mdi-delete"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($products)): ?><tr><td colspan="6" class="text-center text-muted"><?php echo get_phrase('No prices set yet.'); ?></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div></div>
    </div>
    <div class="col-lg-5">
        <div class="card"><div class="card-body">
            <h5><?php echo $e ? get_phrase('Edit Price') : get_phrase('Add Price'); ?></h5>
            <form action="<?php echo site_url('skill_admin/pricing/save'); ?>" method="post">
                <input type="hidden" name="id" value="<?php echo $e['id'] ?? ''; ?>">
                <div class="mb-2"><label class="form-label"><?php echo get_phrase('Skill'); ?> *</label>
                    <select name="skill_id" class="form-control" required>
                        <option value=""><?php echo get_phrase('— select —'); ?></option>
                        <?php foreach ($skills as $s): ?><option value="<?php echo $s['id']; ?>" <?php echo (($e['skill_id'] ?? '') == $s['id']) ? 'selected' : ''; ?>><?php echo html_escape($s['name']); ?></option><?php endforeach; ?>
                    </select></div>
                <div class="mb-2"><label class="form-label"><?php echo get_phrase('Level'); ?> *</label>
                    <select name="skill_level_id" class="form-control" required>
                        <option value=""><?php echo get_phrase('— select —'); ?></option>
                        <?php foreach ($levels as $l): ?><option value="<?php echo $l['id']; ?>" <?php echo (($e['skill_level_id'] ?? '') == $l['id']) ? 'selected' : ''; ?>><?php echo html_escape($l['name']); ?></option><?php endforeach; ?>
                    </select></div>
                <div class="row">
                    <div class="col-6 mb-2"><label class="form-label"><?php echo get_phrase('Price'); ?></label>
                        <input type="number" step="0.01" name="price" class="form-control" value="<?php echo $e['price'] ?? ''; ?>"></div>
                    <div class="col-6 mb-2"><label class="form-label"><?php echo get_phrase('Currency'); ?></label>
                        <input type="text" name="currency" class="form-control" value="<?php echo html_escape($e['currency'] ?? 'TZS'); ?>"></div>
                </div>
                <div class="mb-2"><label class="form-label"><?php echo get_phrase('Retake price (optional)'); ?></label>
                    <input type="number" step="0.01" name="retake_price" class="form-control" value="<?php echo $e['retake_price'] ?? ''; ?>"></div>
                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" name="active" value="1" id="act" <?php echo (!isset($e['active']) || $e['active']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="act"><?php echo get_phrase('Active'); ?></label>
                </div>
                <button type="submit" class="btn btn-primary"><?php echo get_phrase('Save'); ?></button>
                <?php if ($e): ?><a href="<?php echo site_url('skill_admin/pricing'); ?>" class="btn btn-light"><?php echo get_phrase('Cancel'); ?></a><?php endif; ?>
            </form>
        </div></div>
    </div>
</div>
