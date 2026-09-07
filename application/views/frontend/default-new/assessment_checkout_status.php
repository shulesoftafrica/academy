<section class="py-5" style="background:#f7f8fa;min-height:70vh;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6 text-center">
                <div class="card border-0 shadow-sm" style="border-radius:16px;">
                    <div class="card-body p-5">
                        <div class="spinner-border mb-3" style="color:#0e9f8e;" role="status"></div>
                        <h4 class="fw-bold"><?php echo get_phrase('Waiting for payment confirmation'); ?></h4>
                        <p class="text-muted"><?php echo get_phrase('This page refreshes automatically. Once your payment is confirmed, your assessment will start.'); ?></p>
                        <div class="p-2 mb-3" style="background:#f7f8fa;border-radius:10px;">
                            <span class="text-muted"><?php echo get_phrase('Status'); ?>:</span>
                            <span class="badge bg-warning"><?php echo get_phrase(ucfirst($payment['status'])); ?></span>
                        </div>
                        <a href="<?php echo site_url('skills/checkout_status/' . $payment['id']); ?>" class="btn btn-primary"><?php echo get_phrase('Check again'); ?></a>
                        <div class="mt-3"><a href="<?php echo site_url('skills/my_assessments'); ?>" class="text-muted"><?php echo get_phrase('Back to My Assessments'); ?></a></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<meta http-equiv="refresh" content="8">
