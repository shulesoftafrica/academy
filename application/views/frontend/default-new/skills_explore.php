<?php
/** Explore skills — self-contained styling (.sk-) + collision-safe variables. */
$decode = fn ($s) => html_escape(html_entity_decode((string) $s, ENT_QUOTES));
?>
<style>
  .sk-wrap { --sk-teal:#0e9f8e; --sk-teal-d:#0b8577; --sk-tint:#e9f7f4; --sk-ink:#152625;
             --sk-mut:#66807c; --sk-line:#e7edeb; --sk-bg:#f6f9f8; }
  .sk-x-head { padding:44px 0 26px; }
  .sk-x-head h1 { font-weight:800; font-size:1.9rem; color:var(--sk-ink); margin:0 0 6px; letter-spacing:-.01em; }
  .sk-x-head p { color:var(--sk-mut); margin:0; }
  .sk-filters { display:flex; flex-wrap:wrap; gap:9px; margin:0 0 30px; }
  .sk-pill { padding:8px 16px; border-radius:999px; font-size:.85rem; font-weight:600; text-decoration:none;
             border:1px solid var(--sk-line); color:var(--sk-mut); background:#fff; transition:.15s; }
  .sk-pill:hover { border-color:var(--sk-teal); color:var(--sk-teal-d); }
  .sk-pill.active { background:var(--sk-teal); border-color:var(--sk-teal); color:#fff; }
  .sk-skills { display:grid; grid-template-columns:repeat(auto-fill,minmax(288px,1fr)); gap:18px; padding-bottom:56px; }
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
  .sk-empty { grid-column:1/-1; text-align:center; color:var(--sk-mut); padding:48px 0; }
</style>

<div class="sk-wrap">
  <div class="container">
    <div class="sk-x-head">
      <h1><?php echo get_phrase('Explore Skills'); ?></h1>
      <p><?php echo get_phrase('Choose a skill to see its levels, standard and price.'); ?></p>
    </div>

    <div class="sk-filters">
      <a href="<?php echo site_url('skills/explore'); ?>" class="sk-pill <?php echo $active_category === '' ? 'active' : ''; ?>"><?php echo get_phrase('All'); ?></a>
      <?php foreach ($skill_categories as $c): ?>
        <a href="<?php echo site_url('skills/explore/' . $c['slug']); ?>" class="sk-pill <?php echo $active_category === $c['slug'] ? 'active' : ''; ?>"><?php echo $decode($c['name']); ?></a>
      <?php endforeach; ?>
    </div>

    <div class="sk-skills">
      <?php foreach ($catalog_skills as $s): ?>
        <a href="<?php echo site_url('skills/skill/' . $s['slug']); ?>" class="sk-skill">
          <span class="sk-chip"><?php echo $decode($s['category_name'] ?: get_phrase('Skill')); ?></span>
          <h3><?php echo $decode($s['name']); ?></h3>
          <p><?php echo $decode(mb_substr((string) $s['description'], 0, 110)); ?></p>
          <span class="sk-more"><?php echo get_phrase('View levels'); ?> →</span>
        </a>
      <?php endforeach; ?>
      <?php if (empty($catalog_skills)): ?><div class="sk-empty"><?php echo get_phrase('No skills in this category yet.'); ?></div><?php endif; ?>
    </div>
  </div>
</div>
