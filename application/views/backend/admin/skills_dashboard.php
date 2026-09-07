<div class="row">
    <div class="col-12">
        <div class="card"><div class="card-body">
            <h4 class="page-title"><i class="mdi mdi-certificate title_icon"></i> <?php echo get_phrase('Skills Assessment Management'); ?></h4>
        </div></div>
    </div>
</div>

<?php
$tiles = [
    ['Active Skills', $metrics['skills'], 'mdi-lightbulb-on', 'skill_admin/skills'],
    ['Published Assessments', $metrics['assessments'], 'mdi-clipboard-text', 'skill_admin/assessments'],
    ['Candidates Assessed', $metrics['candidates'], 'mdi-account-group', 'skill_admin/analytics'],
    ['Credentials Issued', $metrics['credentials'], 'mdi-shield-check', 'skill_admin/credentials'],
    ['Pending Reviews', $metrics['pending_reviews'], 'mdi-clipboard-alert', 'skill_admin/reviews/pending'],
    ['Revenue', 'TZS ' . number_format((float) $metrics['revenue']), 'mdi-cash-multiple', 'skill_admin/analytics'],
    ['Pass Rate', $metrics['pass_rate'] === null ? '—' : $metrics['pass_rate'] . '%', 'mdi-trophy', 'skill_admin/analytics'],
    ['Avg Score', $metrics['avg_score'] === null ? '—' : $metrics['avg_score'] . '/100', 'mdi-chart-line', 'skill_admin/analytics'],
    ['Attempts', $metrics['attempts'], 'mdi-file-document-edit', 'skill_admin/analytics'],
    ['Integrity Flags', $metrics['integrity_flags'], 'mdi-alert-octagon', 'skill_admin/analytics'],
];
?>
<div class="row">
    <?php foreach ($tiles as $t): ?>
    <div class="col-md-4 col-xl-2">
        <a href="<?php echo $t[3] === '#' ? 'javascript:;' : site_url($t[3]); ?>">
        <div class="card widget-flat">
            <div class="card-body">
                <div class="float-end"><i class="mdi <?php echo $t[2]; ?> widget-icon"></i></div>
                <h5 class="text-muted fw-normal mt-0" title="<?php echo $t[0]; ?>"><?php echo get_phrase($t[0]); ?></h5>
                <h3 class="mt-3 mb-1"><?php echo $t[1]; ?></h3>
            </div>
        </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<div class="row">
    <div class="col-12"><div class="card"><div class="card-body">
        <h5 class="mb-3"><?php echo get_phrase('Manage'); ?></h5>
        <a href="<?php echo site_url('skill_admin/skills'); ?>" class="btn btn-outline-primary m-1"><i class="mdi mdi-lightbulb-on"></i> <?php echo get_phrase('Skills'); ?></a>
        <a href="<?php echo site_url('skill_admin/categories'); ?>" class="btn btn-outline-primary m-1"><i class="mdi mdi-shape"></i> <?php echo get_phrase('Categories'); ?></a>
        <a href="<?php echo site_url('skill_admin/pricing'); ?>" class="btn btn-outline-primary m-1"><i class="mdi mdi-cash"></i> <?php echo get_phrase('Assessment Pricing'); ?></a>
        <a href="<?php echo site_url('skill_admin/reviews'); ?>" class="btn btn-outline-primary m-1"><i class="mdi mdi-clipboard-check"></i> <?php echo get_phrase('Review Queue'); ?></a>
        <a href="<?php echo site_url('skill_admin/credentials'); ?>" class="btn btn-outline-primary m-1"><i class="mdi mdi-certificate"></i> <?php echo get_phrase('Credentials'); ?></a>
        <a href="<?php echo site_url('skill_admin/analytics'); ?>" class="btn btn-primary m-1"><i class="mdi mdi-chart-bar"></i> <?php echo get_phrase('Analytics'); ?></a>
    </div></div></div>
</div>
