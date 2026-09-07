<section class="py-5">
    <div class="container">
        <h2 class="fw-bold mb-4"><?php echo get_phrase('My Assessments'); ?></h2>
        <?php if (empty($attempts)): ?>
            <div class="card border-0 shadow-sm text-center py-5" style="border-radius:14px;">
                <div><i class="mdi mdi-clipboard-text-clock" style="font-size:2.5rem;color:#0e9f8e;"></i></div>
                <p class="text-muted mt-2 mb-3"><?php echo get_phrase('You have not taken any assessments yet.'); ?></p>
                <div><a href="<?php echo site_url('skills/explore'); ?>" class="btn btn-primary"><?php echo get_phrase('Explore Skills'); ?></a></div>
            </div>
        <?php else: ?>
            <div class="card border-0 shadow-sm" style="border-radius:14px;"><div class="card-body">
                <table class="table table-hover mb-0">
                    <thead><tr><th><?php echo get_phrase('Assessment'); ?></th><th><?php echo get_phrase('Date'); ?></th><th><?php echo get_phrase('Score'); ?></th><th><?php echo get_phrase('Result'); ?></th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($attempts as $a): ?>
                        <tr>
                            <td><strong><?php echo html_escape($a['title']); ?></strong><br><small class="text-muted"><?php echo html_escape($a['skill_name']); ?> — <?php echo html_escape($a['level_name']); ?></small></td>
                            <td><?php echo $a['started_at'] ? date('d M Y', strtotime($a['started_at'])) : '-'; ?></td>
                            <td><?php echo $a['score'] !== null ? (rtrim(rtrim(number_format($a['score'],2),'0'),'.') . '%') : '—'; ?></td>
                            <td><?php
                                $map = ['passed'=>'bg-success','failed'=>'bg-danger','lower_level'=>'bg-warning','invalidated'=>'bg-dark','in_progress'=>'bg-info'];
                                echo '<span class="badge '.($map[$a['result']] ?? 'bg-secondary').'">'.get_phrase(ucwords(str_replace('_',' ',$a['result']))).'</span>'; ?></td>
                            <td class="text-end">
                                <?php if ($a['result'] === 'in_progress'): ?>
                                    <a href="<?php echo site_url('skills/take/' . $a['id']); ?>" class="btn btn-sm btn-primary"><?php echo get_phrase('Continue'); ?></a>
                                <?php else: ?>
                                    <a href="<?php echo site_url('skills/result/' . $a['id']); ?>" class="btn btn-sm btn-outline-secondary"><?php echo get_phrase('View'); ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div></div>
        <?php endif; ?>
    </div>
</section>
