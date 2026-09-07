<section class="py-5" style="background:#f7f8fa;">
    <div class="container">
        <a href="<?php echo site_url('skills/explore'); ?>" class="text-muted text-decoration-none">&larr; <?php echo get_phrase('All skills'); ?></a>
        <h1 class="fw-bold mt-2"><?php echo html_escape($skill['name']); ?></h1>
        <p class="text-muted" style="max-width:720px;"><?php echo html_escape($skill['description']); ?></p>
        <?php if (! empty($competencies)): ?>
            <div class="mt-3">
                <span class="text-muted me-2"><?php echo get_phrase('Competencies measured'); ?>:</span>
                <?php foreach ($competencies as $c): ?><span class="badge bg-white text-dark border me-1 mb-1"><?php echo html_escape($c['name']); ?></span><?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <h4 class="fw-bold mb-1"><?php echo get_phrase('Choose your level'); ?></h4>
        <p class="text-muted mb-4"><?php echo get_phrase('You can attempt any level directly — no need to pass lower levels first (spec §5).'); ?></p>

        <div class="row g-3">
            <?php foreach ($levels as $row):
                $lvl = $row['level']; $a = $row['assessment']; $price = $row['price']; ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm" style="border-radius:14px;">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <h5 class="fw-bold text-dark mb-0"><?php echo html_escape($lvl['name']); ?></h5>
                                <span class="badge" style="background:#0e9f8e;">L<?php echo (int) $lvl['rank']; ?></span>
                            </div>
                            <p class="text-muted mt-2" style="font-size:.88rem;min-height:44px;"><?php echo html_escape($lvl['description']); ?></p>
                            <?php if ($a): ?>
                                <div class="mb-2">
                                    <span class="fw-bold" style="color:#0b8577;font-size:1.2rem;">
                                        <?php echo $price ? ($price['currency'] . ' ' . number_format((float) $price['price'])) : get_phrase('Free'); ?>
                                    </span>
                                    <span class="text-muted ms-2" style="font-size:.85rem;"><?php echo (int) $row['questions']; ?> <?php echo get_phrase('items'); ?> · <?php echo (int) $a['duration_minutes']; ?> <?php echo get_phrase('min'); ?></span>
                                </div>
                                <a href="<?php echo site_url('skills/assessment/' . $skill['slug'] . '/' . $lvl['slug']); ?>" class="btn btn-primary w-100"><?php echo get_phrase('View assessment'); ?></a>
                            <?php else: ?>
                                <span class="badge bg-light text-muted"><?php echo get_phrase('Coming soon'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
