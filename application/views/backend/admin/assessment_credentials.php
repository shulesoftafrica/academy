<div class="row"><div class="col-12"><div class="card"><div class="card-body">
    <h4 class="page-title"><i class="mdi mdi-certificate title_icon"></i> <?php echo get_phrase('Issued Credentials'); ?></h4>
    <small class="text-muted"><?php echo get_phrase('Verified skill credentials and their lifecycle (spec §19–§21). Revoking hides the skill from the candidate\'s Talent profile.'); ?></small>
</div></div></div></div>

<div class="row"><div class="col-12"><div class="card"><div class="card-body">
    <?php $tabs = ['active'=>'Active','expired'=>'Expired','revoked'=>'Revoked','suspended'=>'Suspended','superseded'=>'Superseded','all'=>'All']; ?>
    <div class="d-flex justify-content-between flex-wrap align-items-center mb-3">
        <ul class="nav nav-pills mb-0">
            <?php foreach ($tabs as $key => $label): $c = $key==='all'?array_sum($counts):($counts[$key]??0); ?>
                <li class="nav-item"><a class="nav-link<?php echo $filter===$key?' active':''; ?>" style="<?php echo $filter===$key?'background:#0e9f8e;':''; ?>"
                    href="<?php echo site_url('skill_admin/credentials').($key==='all'?'':'?status='.$key); ?>"><?php echo get_phrase($label); ?> <span class="badge bg-light text-dark"><?php echo (int)$c; ?></span></a></li>
            <?php endforeach; ?>
        </ul>
        <form method="get" action="<?php echo site_url('skill_admin/credentials'); ?>" class="d-flex" style="gap:.4rem;">
            <?php if ($filter!=='all'): ?><input type="hidden" name="status" value="<?php echo html_escape($filter); ?>"><?php endif; ?>
            <input type="text" name="q" value="<?php echo html_escape($q); ?>" class="form-control form-control-sm" placeholder="<?php echo get_phrase('number / email / skill'); ?>" style="width:220px;">
            <button class="btn btn-sm btn-outline-secondary"><i class="mdi mdi-magnify"></i></button>
        </form>
    </div>

    <table class="table table-hover mb-0">
        <thead><tr>
            <th><?php echo get_phrase('Credential'); ?></th><th><?php echo get_phrase('Holder'); ?></th>
            <th><?php echo get_phrase('Skill / Level'); ?></th><th><?php echo get_phrase('Score'); ?></th>
            <th><?php echo get_phrase('Issued'); ?></th><th><?php echo get_phrase('Expires'); ?></th>
            <th><?php echo get_phrase('Status'); ?></th><th class="text-end"></th>
        </tr></thead>
        <tbody>
        <?php foreach ($credentials as $c): $eff=$c['effective_status'];
            $sb=['active'=>'bg-success','expired'=>'bg-warning','revoked'=>'bg-danger','suspended'=>'bg-secondary','superseded'=>'bg-light text-dark'][$eff]??'bg-light'; ?>
            <tr>
                <td><code><?php echo html_escape($c['credential_number']); ?></code></td>
                <td><?php echo html_escape($c['candidate_name'] ?: $c['candidate_email']); ?><br><small class="text-muted"><?php echo html_escape($c['candidate_email']); ?></small></td>
                <td><?php echo html_escape($c['skill_name']); ?> — <?php echo html_escape($c['level_name']); ?></td>
                <td><?php echo rtrim(rtrim(number_format($c['overall_score'],2),'0'),'.'); ?></td>
                <td><small><?php echo $c['issued_at']?date('d M Y',strtotime($c['issued_at'])):'-'; ?></small></td>
                <td><small><?php echo !empty($c['expires_at'])?date('d M Y',strtotime($c['expires_at'])):get_phrase('—'); ?></small></td>
                <td><span class="badge <?php echo $sb; ?>"><?php echo get_phrase(ucfirst($eff)); ?></span></td>
                <td class="text-end text-nowrap">
                    <a href="<?php echo site_url('skills/verify/'.$c['verification_token']); ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="<?php echo get_phrase('Public page'); ?>"><i class="mdi mdi-open-in-new"></i></a>
                    <?php if (in_array($c['status'],['active'])): ?>
                        <a href="javascript:;" onclick="confirm_modal('<?php echo site_url('skill_admin/credentials/revoke/'.$c['id']); ?>');" class="btn btn-sm btn-outline-danger" title="<?php echo get_phrase('Revoke'); ?>"><i class="mdi mdi-cancel"></i></a>
                        <a href="javascript:;" onclick="confirm_modal('<?php echo site_url('skill_admin/credentials/suspend/'.$c['id']); ?>');" class="btn btn-sm btn-outline-secondary" title="<?php echo get_phrase('Suspend'); ?>"><i class="mdi mdi-pause"></i></a>
                    <?php elseif (in_array($c['status'],['revoked','suspended'])): ?>
                        <a href="javascript:;" onclick="confirm_modal('<?php echo site_url('skill_admin/credentials/reinstate/'.$c['id']); ?>');" class="btn btn-sm btn-outline-success" title="<?php echo get_phrase('Reinstate'); ?>"><i class="mdi mdi-restore"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($credentials)): ?><tr><td colspan="8" class="text-center text-muted py-4"><?php echo get_phrase('No credentials in this view.'); ?></td></tr><?php endif; ?>
        </tbody>
    </table>
</div></div></div></div>
