<?php
/**
 * Skills landing (spec §2). Self-contained styling under the .sk- namespace so
 * it doesn't inherit the LMS theme's .card sizing quirks, and uses $skill_categories
 * / $featured_skills to avoid the theme's global $categories/$skills collision.
 */
function sk_icon($cat)
{
    $key = strtolower(($cat['slug'] ?? '') . ' ' . ($cat['name'] ?? ''));
    $map = [
        'financ' => 'fa-calculator', 'account' => 'fa-calculator', 'sales' => 'fa-handshake',
        'market' => 'fa-bullhorn', 'tech' => 'fa-laptop-code', 'software' => 'fa-laptop-code',
        'ict' => 'fa-laptop-code', 'data' => 'fa-chart-line', 'analy' => 'fa-chart-line',
        'admin' => 'fa-clipboard-list', 'educat' => 'fa-graduation-cap', 'teach' => 'fa-graduation-cap',
        'custom' => 'fa-headset', 'service' => 'fa-headset', 'lead' => 'fa-user-tie',
        'business' => 'fa-briefcase', 'health' => 'fa-stethoscope', 'nurs' => 'fa-stethoscope',
        'agri' => 'fa-seedling', 'communic' => 'fa-comments', 'language' => 'fa-language', 'vocation' => 'fa-wrench',
    ];
    foreach ($map as $needle => $icon) {
        if (strpos($key, $needle) !== false) {
            return $icon;
        }
    }
    return 'fa-certificate';
}
$decode = fn ($s) => html_escape(html_entity_decode((string) $s, ENT_QUOTES));
?>

<style>
  .sk-wrap { --sk-teal:#0e9f8e; --sk-teal-d:#0b8577; --sk-tint:#e9f7f4; --sk-ink:#152625;
             --sk-mut:#66807c; --sk-line:#e7edeb; --sk-bg:#f6f9f8; }

  /* Hero */
  .sk-hero { background:linear-gradient(135deg,#0e9f8e 0%,#0b8577 100%); color:#fff; }
  .sk-hero-inner { max-width:720px; margin:0 auto; text-align:center; padding:64px 16px; }
  .sk-hero h1 { font-weight:800; font-size:clamp(2rem,4vw,2.8rem); line-height:1.12; margin:0 0 14px; letter-spacing:-.02em; }
  .sk-hero p { font-size:1.05rem; opacity:.94; margin:0 auto 26px; max-width:600px; line-height:1.6; }
  .sk-hero-cta { display:flex; gap:12px; justify-content:center; flex-wrap:wrap; }
  .sk-btn { display:inline-flex; align-items:center; gap:8px; padding:12px 24px; border-radius:12px;
            font-weight:700; font-size:.95rem; text-decoration:none; transition:.18s; border:2px solid transparent; }
  .sk-btn-solid { background:#fff; color:var(--sk-teal-d); }
  .sk-btn-solid:hover { transform:translateY(-2px); box-shadow:0 10px 22px rgba(0,0,0,.16); color:var(--sk-teal-d); }
  .sk-btn-ghost { background:rgba(255,255,255,.10); color:#fff; border-color:rgba(255,255,255,.55); }
  .sk-btn-ghost:hover { background:rgba(255,255,255,.20); color:#fff; }

  /* Section shell */
  .sk-section { padding:56px 0; }
  .sk-section.alt { background:var(--sk-bg); }
  .sk-head { text-align:center; max-width:620px; margin:0 auto 36px; }
  .sk-head h2 { font-weight:800; font-size:1.7rem; color:var(--sk-ink); margin:0 0 8px; letter-spacing:-.01em; }
  .sk-head p { color:var(--sk-mut); margin:0; font-size:1rem; }

  /* Category grid — uniform tiles */
  .sk-cats { display:grid; grid-template-columns:repeat(auto-fill,minmax(184px,1fr)); gap:16px; }
  .sk-cat { display:flex; flex-direction:column; align-items:center; text-align:center; gap:12px;
            background:#fff; border:1px solid var(--sk-line); border-radius:16px; padding:26px 16px;
            text-decoration:none; transition:.18s; box-shadow:0 1px 2px rgba(21,38,37,.04); }
  .sk-cat:hover { transform:translateY(-4px); border-color:var(--sk-teal); box-shadow:0 12px 26px rgba(14,159,142,.16); }
  .sk-cat-ic { width:60px; height:60px; border-radius:16px; background:var(--sk-tint);
               display:flex; align-items:center; justify-content:center; line-height:1; }
  .sk-cat-ic i { font-size:1.45rem; color:var(--sk-teal-d); }
  .sk-cat-name { font-weight:700; color:var(--sk-ink); font-size:.98rem; line-height:1.3; }
  .sk-cat-count { font-size:.8rem; color:var(--sk-mut); margin-top:-4px; }

  /* Skill cards — uniform, equal height */
  .sk-skills { display:grid; grid-template-columns:repeat(auto-fill,minmax(288px,1fr)); gap:18px; }
  .sk-skill { display:flex; flex-direction:column; background:#fff; border:1px solid var(--sk-line);
              border-radius:16px; padding:22px; text-decoration:none; transition:.18s;
              box-shadow:0 1px 2px rgba(21,38,37,.04); height:100%; }
  .sk-skill:hover { transform:translateY(-4px); border-color:var(--sk-teal); box-shadow:0 12px 26px rgba(14,159,142,.14); }
  .sk-chip { align-self:flex-start; background:var(--sk-tint); color:var(--sk-teal-d);
             font-size:.72rem; font-weight:700; padding:5px 11px; border-radius:999px; letter-spacing:.02em; text-transform:uppercase; }
  .sk-skill h3 { font-size:1.12rem; font-weight:700; color:var(--sk-ink); margin:14px 0 8px; line-height:1.3; }
  .sk-skill p { color:var(--sk-mut); font-size:.9rem; line-height:1.55; margin:0 0 18px;
                display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; min-height:2.8em; }
  .sk-skill .sk-more { margin-top:auto; color:var(--sk-teal-d); font-weight:700; font-size:.9rem; display:inline-flex; align-items:center; gap:6px; }
  .sk-skill:hover .sk-more { gap:10px; }
  .sk-empty { text-align:center; color:var(--sk-mut); padding:40px 0; }
  @media (max-width:520px){ .sk-cats{ grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); } }
</style>

<div class="sk-wrap">

  <section class="sk-hero">
    <div class="sk-hero-inner">
      <h1><?php echo get_phrase('Prove what you can do.'); ?></h1>
      <p><?php echo get_phrase('Assess your skills, discover your level and earn verified credentials that strengthen your professional profile.'); ?></p>
      <div class="sk-hero-cta">
        <a href="<?php echo site_url('skills/explore'); ?>" class="sk-btn sk-btn-solid"><?php echo get_phrase('Explore Skills'); ?> →</a>
        <a href="<?php echo site_url('skills/my_skills'); ?>" class="sk-btn sk-btn-ghost"><?php echo get_phrase('My Skills'); ?></a>
        <a href="<?php echo site_url('skills/my_development'); ?>" class="sk-btn sk-btn-ghost"><?php echo get_phrase('My Development'); ?></a>
        <a href="<?php echo site_url('skills/my_assessments'); ?>" class="sk-btn sk-btn-ghost"><?php echo get_phrase('My Assessments'); ?></a>
      </div>
    </div>
  </section>

  <section class="sk-section">
    <div class="container">
      <div class="sk-head">
        <h2><?php echo get_phrase('Browse by category'); ?></h2>
        <p><?php echo get_phrase('Find the area you want to prove your expertise in.'); ?></p>
      </div>
      <div class="sk-cats">
        <?php foreach ($skill_categories as $c): ?>
          <a href="<?php echo site_url('skills/explore/' . $c['slug']); ?>" class="sk-cat">
            <span class="sk-cat-ic"><i class="fas <?php echo sk_icon($c); ?>"></i></span>
            <span class="sk-cat-name"><?php echo $decode($c['name']); ?></span>
            <?php if (isset($c['skill_count'])): ?>
              <span class="sk-cat-count"><?php echo (int) $c['skill_count']; ?> <?php echo get_phrase($c['skill_count'] == 1 ? 'skill' : 'skills'); ?></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
        <?php if (empty($skill_categories)): ?><div class="sk-empty"><?php echo get_phrase('No categories yet.'); ?></div><?php endif; ?>
      </div>
    </div>
  </section>

  <section class="sk-section alt">
    <div class="container">
      <div class="sk-head">
        <h2><?php echo get_phrase('Skills you can verify'); ?></h2>
        <p><?php echo get_phrase('Pick a skill, choose your level and earn a credential employers trust.'); ?></p>
      </div>
      <div class="sk-skills">
        <?php foreach (array_slice($featured_skills, 0, 12) as $s): ?>
          <a href="<?php echo site_url('skills/skill/' . $s['slug']); ?>" class="sk-skill">
            <span class="sk-chip"><?php echo $decode($s['category_name'] ?: get_phrase('Skill')); ?></span>
            <h3><?php echo $decode($s['name']); ?></h3>
            <p><?php echo $decode(mb_substr((string) $s['description'], 0, 110)); ?></p>
            <span class="sk-more"><?php echo get_phrase('View levels'); ?> →</span>
          </a>
        <?php endforeach; ?>
        <?php if (empty($featured_skills)): ?><div class="sk-empty"><?php echo get_phrase('No skills published yet.'); ?></div><?php endif; ?>
      </div>
    </div>
  </section>

</div>
