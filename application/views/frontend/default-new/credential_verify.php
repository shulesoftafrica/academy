<?php
$c = $credential;
$eff = $c['effective_status'] ?? null;
$badge = ['active' => ['#0b8577', '#e6f7f4', 'Valid'], 'expired' => ['#b26a00', '#fff4e5', 'Expired'],
          'revoked' => ['#b3261e', '#fdecea', 'Revoked'], 'suspended' => ['#7a6a00', '#fbf7e0', 'Suspended'],
          'superseded' => ['#555', '#eee', 'Superseded']][$eff] ?? ['#555', '#eee', ucfirst((string) $eff)];
?>
<section class="py-5" style="background:#f7f8fa;min-height:78vh;">
  <div class="container">
    <div class="row justify-content-center"><div class="col-lg-7">

      <div class="text-center mb-4">
        <img src="<?php echo base_url('assets/frontend/default-new/image/logo.png'); ?>" alt="ShuleSoft Academy" style="height:44px;">
        <div class="text-muted mt-2" style="letter-spacing:.06em;font-size:.85rem;"><?php echo get_phrase('CREDENTIAL VERIFICATION'); ?></div>
      </div>

      <?php if (! $c): ?>
        <div class="card border-0 shadow-sm" style="border-radius:16px;"><div class="card-body p-5 text-center">
          <div style="font-size:3rem;">🔍</div>
          <h4 class="fw-bold"><?php echo get_phrase('Credential not found'); ?></h4>
          <p class="text-muted mb-0"><?php echo get_phrase('This verification link is invalid or the credential no longer exists.'); ?></p>
        </div></div>
      <?php else: ?>
        <div class="card border-0 shadow-sm" style="border-radius:16px;overflow:hidden;">
          <div style="height:6px;background:<?php echo $badge[0]; ?>;"></div>
          <div class="card-body p-4 p-md-5">
            <div class="d-flex justify-content-between align-items-start flex-wrap">
              <div>
                <div class="text-muted" style="font-size:.8rem;"><?php echo get_phrase('Verified skill'); ?></div>
                <h3 class="fw-bold mb-0" style="color:#0b8577;"><?php echo html_escape($c['skill_name']); ?></h3>
                <div class="fw-semibold" style="font-size:1.1rem;"><?php echo html_escape($c['level_name']); ?></div>
              </div>
              <span class="badge" style="background:<?php echo $badge[1]; ?>;color:<?php echo $badge[0]; ?>;font-size:.9rem;padding:.5rem .9rem;border-radius:999px;">
                <?php echo $eff === 'active' ? '✓ ' : ''; ?><?php echo get_phrase($badge[2]); ?>
              </span>
            </div>

            <hr>

            <div class="row g-3">
              <div class="col-sm-6">
                <div class="text-muted" style="font-size:.78rem;"><?php echo get_phrase('Holder'); ?></div>
                <div class="fw-semibold"><?php echo html_escape($c['candidate_name'] ?: '—'); ?></div>
              </div>
              <div class="col-sm-6">
                <div class="text-muted" style="font-size:.78rem;"><?php echo get_phrase('Credential number'); ?></div>
                <div class="fw-semibold"><?php echo html_escape($c['credential_number']); ?></div>
              </div>
              <div class="col-sm-6">
                <div class="text-muted" style="font-size:.78rem;"><?php echo get_phrase('Issued'); ?></div>
                <div class="fw-semibold"><?php echo $c['issued_at'] ? date('d M Y', strtotime($c['issued_at'])) : '—'; ?></div>
              </div>
              <div class="col-sm-6">
                <div class="text-muted" style="font-size:.78rem;"><?php echo get_phrase('Valid until'); ?></div>
                <div class="fw-semibold"><?php echo ! empty($c['expires_at']) ? date('d M Y', strtotime($c['expires_at'])) : get_phrase('No expiry'); ?></div>
              </div>
            </div>

            <?php if ($eff === 'active'): ?>
              <div class="alert mt-4 mb-0" style="background:#e6f7f4;color:#0b8577;border:none;border-radius:10px;">
                <i class="fas fa-shield-alt"></i> <?php echo get_phrase('This is a genuine ShuleSoft Academy credential, verified against our assessment records.'); ?>
              </div>
            <?php elseif ($eff === 'revoked'): ?>
              <div class="alert mt-4 mb-0" style="background:#fdecea;color:#b3261e;border:none;border-radius:10px;">
                <?php echo get_phrase('This credential has been revoked and is no longer valid.'); ?>
              </div>
            <?php elseif ($eff === 'expired'): ?>
              <div class="alert mt-4 mb-0" style="background:#fff4e5;color:#b26a00;border:none;border-radius:10px;">
                <?php echo get_phrase('This credential has expired. The holder can re-verify to renew it.'); ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <p class="text-center text-muted mt-3" style="font-size:.8rem;"><?php echo get_phrase('ShuleSoft Academy confirms only the skill, level, holder, date and status shown above.'); ?></p>
      <?php endif; ?>

    </div></div>
  </div>
</section>
