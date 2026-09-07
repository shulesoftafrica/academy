<?php
/** My Skill Development (§45) — current level, target, competency gap bars, next step. */
function dev_bar($pct)
{
    if ($pct === null || $pct === '') {
        return '<span style="color:#98a7a4;">—</span>';
    }
    $pct = max(0, min(100, (float) $pct));
    $color = $pct >= 70 ? '#0e9f8e' : ($pct >= 50 ? '#e6a417' : '#e06c53');
    return '<div style="display:flex;align-items:center;gap:10px;">'
         . '<div style="flex:1;height:9px;background:#eef3f2;border-radius:6px;overflow:hidden;min-width:80px;">'
         . '<div style="height:100%;width:' . $pct . '%;background:' . $color . ';"></div></div>'
         . '<span style="font-size:.82rem;font-weight:600;color:#2a3d3a;white-space:nowrap;">' . round($pct) . '%</span></div>';
}
?>
<style>
  .dev-wrap { --t:#0e9f8e; --td:#0b8577; --tint:#e9f7f4; --ink:#152625; --mut:#66807c; --line:#e7edeb; --bg:#f6f9f8; background:var(--bg); }
  .dev-wrap .container { max-width:900px; }
  .dev-h { font-weight:800; font-size:1.7rem; color:var(--ink); margin:0 0 6px; letter-spacing:-.01em; }
  .dev-sub { color:var(--mut); margin:0 0 26px; }
  .dev-card { background:#fff; border:1px solid var(--line); border-radius:16px; padding:22px 24px; margin-bottom:16px; box-shadow:0 1px 2px rgba(21,38,37,.04); }
  .dev-top { display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; }
  .dev-skill { font-weight:700; font-size:1.2rem; color:var(--ink); margin:0; }
  .dev-lvl { display:inline-flex; align-items:center; gap:8px; margin-top:6px; }
  .dev-badge { background:var(--tint); color:var(--td); font-weight:700; font-size:.8rem; padding:4px 12px; border-radius:999px; }
  .dev-badge.none { background:#f1f3f2; color:var(--mut); }
  .dev-score { font-weight:800; color:var(--td); }
  .dev-target { text-align:right; }
  .dev-target .lbl { font-size:.75rem; color:var(--mut); text-transform:uppercase; letter-spacing:.03em; }
  .dev-target .val { font-weight:700; color:var(--ink); }
  .dev-cta { display:inline-block; margin-top:8px; background:var(--t); color:#fff; text-decoration:none; font-weight:700; font-size:.85rem; padding:8px 16px; border-radius:10px; }
  .dev-cta:hover { background:var(--td); color:#fff; }
  .dev-comps { margin-top:18px; border-top:1px solid #f1f5f4; padding-top:16px; }
  .dev-comps .ct { font-size:.78rem; font-weight:700; color:var(--mut); text-transform:uppercase; letter-spacing:.03em; margin-bottom:12px; }
  .dev-crow { display:grid; grid-template-columns:200px 1fr; gap:14px; align-items:center; margin-bottom:10px; }
  .dev-crow .cn { font-size:.9rem; color:var(--ink); font-weight:600; }
  .dev-empty { text-align:center; padding:56px 20px; }
  @media (max-width:600px){ .dev-crow { grid-template-columns:1fr; gap:4px; } }
</style>

<section class="dev-wrap py-5">
  <div class="container">
    <h1 class="dev-h"><?php echo get_phrase('My Skill Development'); ?></h1>
    <p class="dev-sub"><?php echo get_phrase('Track where you stand on each skill, see your gaps, and take the next step to level up.'); ?></p>

    <?php if (empty($skills)): ?>
      <div class="dev-card dev-empty">
        <div style="font-size:2.4rem;">📈</div>
        <h4 class="fw-bold mt-2"><?php echo get_phrase('Your development starts here'); ?></h4>
        <p class="text-muted"><?php echo get_phrase('Take a skill assessment to see your demonstrated level and where to grow next.'); ?></p>
        <a href="<?php echo site_url('skills/explore'); ?>" class="dev-cta"><?php echo get_phrase('Explore skills'); ?></a>
      </div>
    <?php else: ?>
      <?php foreach ($skills as $sk): ?>
        <div class="dev-card">
          <div class="dev-top">
            <div>
              <h3 class="dev-skill"><?php echo html_escape($sk['name']); ?></h3>
              <div class="dev-lvl">
                <span class="text-muted" style="font-size:.85rem;"><?php echo get_phrase('Demonstrated level'); ?>:</span>
                <?php if ($sk['current_name']): ?>
                  <span class="dev-badge">✓ <?php echo html_escape($sk['current_name']); ?></span>
                  <?php if ($sk['best_score'] !== null): ?><span class="dev-score"><?php echo rtrim(rtrim(number_format($sk['best_score'], 1), '0'), '.'); ?>/100</span><?php endif; ?>
                <?php else: ?>
                  <span class="dev-badge none"><?php echo get_phrase('Not yet demonstrated'); ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="dev-target">
              <?php if (! empty($sk['target'])): ?>
                <div class="lbl"><?php echo get_phrase('Next target'); ?></div>
                <div class="val"><?php echo html_escape($sk['target']['name']); ?></div>
                <a class="dev-cta" href="<?php echo site_url('skills/assessment/' . $sk['slug'] . '/' . $sk['target']['slug']); ?>"><?php echo get_phrase('Verify'); ?> <?php echo html_escape($sk['target']['name']); ?> →</a>
              <?php else: ?>
                <div class="lbl"><?php echo get_phrase('Status'); ?></div>
                <div class="val" style="color:#0b8577;">🏆 <?php echo get_phrase('Top level reached'); ?></div>
              <?php endif; ?>
            </div>
          </div>

          <?php if (! empty($sk['competencies'])): ?>
            <div class="dev-comps">
              <div class="ct"><?php echo get_phrase('Your competency breakdown (latest attempt)'); ?></div>
              <?php foreach ($sk['competencies'] as $c): ?>
                <div class="dev-crow">
                  <div class="cn"><?php echo html_escape($c['name']); ?></div>
                  <div><?php echo dev_bar($c['pct']); ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <div class="text-center mt-3">
        <a href="<?php echo site_url('home/courses'); ?>" class="text-decoration-none" style="color:#0b8577;font-weight:600;"><?php echo get_phrase('Browse Academy courses to close your gaps'); ?> →</a>
      </div>
    <?php endif; ?>
  </div>
</section>
