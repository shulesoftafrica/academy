<section class="py-5">
    <div class="container">
        <h2 class="fw-bold mb-4"><?php echo get_phrase('My Verified Skills'); ?></h2>
        <?php if (empty($results)): ?>
            <div class="card border-0 shadow-sm text-center py-5" style="border-radius:14px;">
                <div><i class="mdi mdi-shield-search" style="font-size:2.5rem;color:#0e9f8e;"></i></div>
                <p class="text-muted mt-2 mb-3"><?php echo get_phrase('You have not verified any skills yet.'); ?></p>
                <div><a href="<?php echo site_url('skills/explore'); ?>" class="btn btn-primary"><?php echo get_phrase('Explore Skills'); ?></a></div>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($results as $r): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card border-0 shadow-sm h-100" style="border-radius:14px;">
                            <div class="card-body">
                                <h5 class="fw-bold text-dark mb-1"><?php echo html_escape($r['skill_name']); ?></h5>
                                <div class="mb-2"><span class="badge" style="background:#0e9f8e;"><?php echo html_escape($r['level_name']); ?> ✓</span>
                                    <span class="fw-bold ms-1" style="color:#0b8577;"><?php echo rtrim(rtrim(number_format($r['overall_score'],2),'0'),'.'); ?>/100</span></div>
                                <div class="text-muted" style="font-size:.82rem;"><?php echo get_phrase('Verified'); ?>: <?php echo $r['verified_at'] ? date('d M Y', strtotime($r['verified_at'])) : '-'; ?></div>
                                <?php if (! empty($r['credential_number'])): ?>
                                    <div class="mt-2" style="font-size:.8rem;">
                                        <span class="text-muted"><?php echo get_phrase('Credential'); ?>:</span> <code><?php echo html_escape($r['credential_number']); ?></code>
                                        <?php
                                        $eff = $r['cred_effective'];
                                        $cb = ['active'=>['#0b8577','#e6f7f4','Valid'],'expired'=>['#b26a00','#fff4e5','Expired'],'revoked'=>['#b3261e','#fdecea','Revoked'],'suspended'=>['#7a6a00','#fbf7e0','Suspended'],'superseded'=>['#555','#eee','Superseded']][$eff] ?? ['#555','#eee',ucfirst((string)$eff)];
                                        ?>
                                        <span class="badge ms-1" style="background:<?php echo $cb[1]; ?>;color:<?php echo $cb[0]; ?>;"><?php echo get_phrase($cb[2]); ?></span>
                                    </div>
                                    <a href="<?php echo site_url('skills/verify/' . $r['verification_token']); ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2"><i class="mdi mdi-shield-check"></i> <?php echo get_phrase('View / share credential'); ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
