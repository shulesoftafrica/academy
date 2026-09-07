<?php
$scored = $attempt['score'] !== null && $attempt['score'] !== '';
$levelName = '';
foreach ($levels as $l) { if ($l['id'] == $attempt['level_awarded']) { $levelName = $l['name']; } }
$passed = $attempt['result'] === 'passed';
$lower  = $attempt['result'] === 'lower_level';
?>
<section class="py-5" style="background:#f7f8fa;min-height:70vh;">
  <div class="container">
    <div class="row justify-content-center"><div class="col-lg-7">
      <div class="card border-0 shadow-sm" style="border-radius:16px;">
        <div class="card-body p-4 text-center">
          <?php if (! $scored): ?>
            <div class="spinner-border mb-3" style="color:#0e9f8e;"></div>
            <h4 class="fw-bold"><?php echo get_phrase('Your assessment has been submitted'); ?></h4>
            <p class="text-muted"><?php echo get_phrase('Some responses may be reviewed by an assessor. You will see your result here shortly.'); ?></p>
            <meta http-equiv="refresh" content="10">
          <?php elseif ($passed): ?>
            <div style="font-size:3rem;">🎉</div>
            <h3 class="fw-bold" style="color:#0b8577;"><?php echo html_escape($skill['name']); ?> — <?php echo html_escape($levelName); ?> <?php echo get_phrase('Demonstrated'); ?></h3>
            <p class="text-muted"><?php echo get_phrase('You demonstrated the level against the ShuleSoft Academy competency standard.'); ?></p>
            <div class="fw-bold my-2" style="font-size:2rem;color:#0b8577;"><?php echo rtrim(rtrim(number_format($attempt['score'],2),'0'),'.'); ?>/100</div>
            <a href="<?php echo site_url('skills/my_skills'); ?>" class="btn btn-primary mt-2"><?php echo get_phrase('View my verified skills'); ?></a>
          <?php elseif ($lower): ?>
            <div style="font-size:3rem;">✅</div>
            <h4 class="fw-bold"><?php echo get_phrase('Lower level demonstrated'); ?></h4>
            <p class="text-muted"><?php echo get_phrase('You attempted a higher level but demonstrated'); ?> <strong><?php echo html_escape($levelName); ?></strong>.</p>
            <div class="fw-bold my-2" style="font-size:1.6rem;"><?php echo rtrim(rtrim(number_format($attempt['score'],2),'0'),'.'); ?>/100</div>
            <a href="<?php echo site_url('skills/my_skills'); ?>" class="btn btn-primary mt-2"><?php echo get_phrase('View my verified skills'); ?></a>
          <?php else: ?>
            <div style="font-size:3rem;">📈</div>
            <h4 class="fw-bold"><?php echo html_escape($levelName ?: $skill['name']); ?> <?php echo get_phrase('level not yet demonstrated'); ?></h4>
            <p class="text-muted"><?php echo get_phrase('Keep developing — you can retake after the cooldown period.'); ?></p>
            <div class="fw-bold my-2" style="font-size:1.6rem;"><?php echo rtrim(rtrim(number_format($attempt['score'],2),'0'),'.'); ?>/100</div>
            <a href="<?php echo site_url('skills/skill/' . $skill['slug']); ?>" class="btn btn-outline-primary mt-2"><?php echo get_phrase('See recommended next step'); ?></a>
          <?php endif; ?>
          <div class="mt-3"><a href="<?php echo site_url('skills/my_assessments'); ?>" class="text-muted"><?php echo get_phrase('Back to My Assessments'); ?></a></div>
        </div>
      </div>
    </div></div>
  </div>
</section>
