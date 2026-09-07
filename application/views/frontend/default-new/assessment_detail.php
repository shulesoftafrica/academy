<section class="py-5" style="background:#f7f8fa;">
    <div class="container">
        <a href="<?php echo site_url('skills/skill/' . $skill['slug']); ?>" class="text-muted text-decoration-none">&larr; <?php echo html_escape($skill['name']); ?></a>
        <div class="row mt-2">
            <div class="col-lg-8">
                <h1 class="fw-bold"><?php echo html_escape($skill['name']); ?> — <?php echo html_escape($level['name']); ?></h1>
                <?php if ($assessment): ?>
                    <p class="text-muted"><?php echo html_escape($assessment['description']); ?></p>

                    <div class="card border-0 shadow-sm my-4" style="border-radius:14px;">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3"><?php echo get_phrase('What this assessment measures'); ?></h5>
                            <div class="row">
                                <?php foreach ($competencies as $c): ?>
                                    <div class="col-md-6 mb-2 d-flex align-items-center">
                                        <i class="mdi mdi-check-circle me-2" style="color:#0e9f8e;"></i>
                                        <span><?php echo html_escape($c['name']); ?>
                                            <?php if ($c['is_critical']): ?><span class="badge bg-danger ms-1" style="font-size:.6rem;"><?php echo get_phrase('critical'); ?></span><?php endif; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="row text-center g-3">
                        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm py-3" style="border-radius:12px;">
                            <div class="fw-bold" style="font-size:1.4rem;color:#0b8577;"><?php echo (int) $assessment['duration_minutes']; ?></div>
                            <div class="text-muted" style="font-size:.85rem;"><?php echo get_phrase('minutes'); ?></div></div></div>
                        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm py-3" style="border-radius:12px;">
                            <div class="fw-bold" style="font-size:1.4rem;color:#0b8577;"><?php echo (int) $questions; ?></div>
                            <div class="text-muted" style="font-size:.85rem;"><?php echo get_phrase('questions/tasks'); ?></div></div></div>
                        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm py-3" style="border-radius:12px;">
                            <div class="fw-bold" style="font-size:1.4rem;color:#0b8577;"><?php echo rtrim(rtrim(number_format($assessment['pass_score'],2),'0'),'.'); ?>%</div>
                            <div class="text-muted" style="font-size:.85rem;"><?php echo get_phrase('to pass'); ?></div></div></div>
                        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm py-3" style="border-radius:12px;">
                            <div class="fw-bold" style="font-size:1.4rem;color:#0b8577;"><?php echo rtrim(rtrim(number_format($assessment['critical_min'],2),'0'),'.'); ?>%</div>
                            <div class="text-muted" style="font-size:.85rem;"><?php echo get_phrase('critical min'); ?></div></div></div>
                    </div>

                    <div class="alert mt-4" style="background:#e6f5f2;border:none;">
                        <i class="mdi mdi-information me-1" style="color:#0b8577;"></i>
                        <?php echo get_phrase('Passing standard: you must score at least the pass mark overall AND meet the minimum on every critical competency — otherwise the level is not verified (spec §17).'); ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning mt-3"><?php echo get_phrase('This assessment is not available yet.'); ?></div>
                <?php endif; ?>
            </div>

            <?php if ($assessment): ?>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm sticky-top" style="border-radius:14px;top:100px;">
                    <div class="card-body text-center">
                        <div class="text-muted"><?php echo get_phrase('Assessment fee'); ?></div>
                        <div class="fw-bold my-2" style="font-size:2rem;color:#0b8577;">
                            <?php echo $price ? ($price['currency'] . ' ' . number_format((float) $price['price'])) : get_phrase('Free'); ?>
                        </div>
                        <div class="text-muted mb-3" style="font-size:.85rem;"><?php echo get_phrase('One attempt per purchase (spec §23).'); ?></div>
                        <?php if ($this->session->userdata('user_id')): ?>
                            <a href="<?php echo site_url('skills/checkout/' . $skill['slug'] . '/' . $level['slug']); ?>" class="btn btn-primary w-100 btn-lg"><?php echo get_phrase('Pay & Start'); ?></a>
                        <?php else: ?>
                            <a href="<?php echo site_url('login'); ?>" class="btn btn-primary w-100 btn-lg"><?php echo get_phrase('Log in to start'); ?></a>
                        <?php endif; ?>
                        <div class="text-muted mt-2" style="font-size:.78rem;"><?php echo get_phrase('Verified credential issued on success, added to your Talent Network profile.'); ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>
