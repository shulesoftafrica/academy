<?php
$remaining = max(0, strtotime($attempt['expires_at']) - time());
$sel = function ($saved, $qid, $oid) { return isset($saved[$qid]['selected']) && in_array((int) $oid, array_map('intval', $saved[$qid]['selected']), true); };
?>
<style>
  #take-timer.warn { color:#e67e22; } #take-timer.danger { color:#e74c3c; }
  .take-q { border:1px solid #eee; border-radius:12px; padding:20px; margin-bottom:18px; background:#fff; }
  .take-q .qnum { color:#0e9f8e; font-weight:700; }
</style>

<section class="py-4" style="background:#f7f8fa;">
  <div class="container">
    <div class="d-flex justify-content-between align-items-center flex-wrap sticky-top py-2" style="top:0;background:#f7f8fa;z-index:10;">
      <div>
        <h5 class="fw-bold mb-0"><?php echo html_escape($assessment['title']); ?></h5>
        <small class="text-muted"><?php echo count($questions); ?> <?php echo get_phrase('items'); ?> · <span id="save-status" class="text-muted"><?php echo get_phrase('answers autosave'); ?></span></small>
      </div>
      <div class="text-end">
        <div class="text-muted" style="font-size:.8rem;"><?php echo get_phrase('Time remaining'); ?></div>
        <div id="take-timer" class="fw-bold" style="font-size:1.6rem;">--:--</div>
      </div>
    </div>

    <form id="take-form" action="<?php echo site_url('skills/submit/' . $attempt['id']); ?>" method="post" class="mt-3">
      <?php foreach ($questions as $i => $q): $qid = $q['id']; ?>
        <div class="take-q">
          <div class="mb-2"><span class="qnum"><?php echo get_phrase('Q'); ?><?php echo $i + 1; ?>.</span>
            <span class="badge bg-light text-dark float-end"><?php echo html_escape($types[$q['question_type']] ?? $q['question_type']); ?></span>
            <span><?php echo nl2br(html_escape($q['question_text'])); ?></span>
          </div>

          <?php if (in_array($q['question_type'], ['single', 'truefalse'], true)): ?>
            <?php foreach ($q['options'] as $o): ?>
              <div class="form-check"><input class="form-check-input auto" type="radio" name="selected[<?php echo $qid; ?>][]" value="<?php echo $o['id']; ?>" id="o<?php echo $o['id']; ?>" <?php echo $sel($saved, $qid, $o['id']) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="o<?php echo $o['id']; ?>"><?php echo html_escape($o['option_text']); ?></label></div>
            <?php endforeach; ?>

          <?php elseif ($q['question_type'] === 'multiple'): ?>
            <?php foreach ($q['options'] as $o): ?>
              <div class="form-check"><input class="form-check-input auto" type="checkbox" name="selected[<?php echo $qid; ?>][]" value="<?php echo $o['id']; ?>" id="o<?php echo $o['id']; ?>" <?php echo $sel($saved, $qid, $o['id']) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="o<?php echo $o['id']; ?>"><?php echo html_escape($o['option_text']); ?></label></div>
            <?php endforeach; ?>

          <?php elseif ($q['question_type'] === 'matching'): ?>
            <?php foreach ($q['options'] as $o): $cur = $saved[$qid]['map'][$o['id']] ?? ''; ?>
              <div class="row g-2 align-items-center mb-2">
                <div class="col-6"><?php echo html_escape($o['option_text']); ?></div>
                <div class="col-6"><select class="form-select auto" name="match[<?php echo $qid; ?>][<?php echo $o['id']; ?>]">
                  <option value=""><?php echo get_phrase('— match —'); ?></option>
                  <?php foreach ($q['match_choices'] as $mk): ?><option value="<?php echo html_escape($mk); ?>" <?php echo $cur === $mk ? 'selected' : ''; ?>><?php echo html_escape($mk); ?></option><?php endforeach; ?>
                </select></div>
              </div>
            <?php endforeach; ?>

          <?php elseif ($q['question_type'] === 'ordering'): ?>
            <?php foreach ($q['options'] as $o): $cur = $saved[$qid]['order'][$o['id']] ?? ''; ?>
              <div class="row g-2 align-items-center mb-2">
                <div class="col-2"><input type="number" min="1" class="form-control auto" name="order[<?php echo $qid; ?>][<?php echo $o['id']; ?>]" value="<?php echo html_escape($cur); ?>" style="width:70px;"></div>
                <div class="col-10"><?php echo html_escape($o['option_text']); ?></div>
              </div>
            <?php endforeach; ?>

          <?php elseif ($q['question_type'] === 'numerical'): ?>
            <input type="number" step="any" class="form-control auto" name="answer[<?php echo $qid; ?>]" value="<?php echo html_escape($saved[$qid]['value'] ?? ''); ?>" style="max-width:240px;">

          <?php elseif ($q['question_type'] === 'short_text'): ?>
            <input type="text" class="form-control auto" name="answer[<?php echo $qid; ?>]" value="<?php echo html_escape($saved[$qid]['value'] ?? ''); ?>">

          <?php elseif ($q['question_type'] === 'long_text'): ?>
            <textarea class="form-control auto" name="answer[<?php echo $qid; ?>]" rows="6" placeholder="<?php echo get_phrase('Type your response'); ?>"><?php echo html_escape($saved[$qid]['value'] ?? ''); ?></textarea>
            <?php if (! empty($q['marking_criteria'])): ?><small class="text-muted"><?php echo get_phrase('This response is graded against a rubric by a reviewer.'); ?></small><?php endif; ?>

          <?php else: /* practical / file → optional note + file artifact upload (§7 C/D, §11) */ ?>
            <?php if ($q['question_type'] === 'practical'): ?>
              <textarea class="form-control auto mb-3" name="answer[<?php echo $qid; ?>]" rows="4" placeholder="<?php echo get_phrase('Describe your approach (optional)'); ?>"><?php echo html_escape($saved[$qid]['value'] ?? ''); ?></textarea>
            <?php endif; ?>
            <?php $sub = $submissions[$qid] ?? null; ?>
            <div class="sub-upload" data-qid="<?php echo $qid; ?>" style="border:1px dashed #cfe3de;border-radius:10px;padding:14px;background:#f6fbfa;">
              <div class="d-flex flex-wrap align-items-center" style="gap:10px;">
                <input type="file" class="form-control sub-file" style="max-width:360px;">
                <button type="button" class="btn btn-outline-primary btn-sm sub-btn"><i class="fas fa-upload"></i> <?php echo get_phrase('Upload file'); ?></button>
                <span class="sub-status" style="font-size:.85rem;color:#0b8577;">
                  <?php if ($sub): ?><i class="fas fa-paperclip"></i> <a href="<?php echo site_url('skills/submission_file/' . $sub['id']); ?>" target="_blank"><?php echo html_escape($sub['original_name']); ?></a> — <?php echo get_phrase('uploaded'); ?><?php else: ?><span class="text-muted"><?php echo get_phrase('No file uploaded yet'); ?></span><?php endif; ?>
                </span>
              </div>
              <small class="text-muted d-block mt-2"><?php echo get_phrase('Accepted: PDF, Word, Excel, CSV, images, ZIP · max 10 MB · graded by a reviewer. Re-uploading replaces your file.'); ?></small>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <div class="text-center py-3">
        <button type="submit" id="submit-btn" class="btn btn-primary btn-lg px-5"><?php echo get_phrase('Submit assessment'); ?></button>
        <p class="text-muted mt-2" style="font-size:.82rem;"><?php echo get_phrase('You cannot change answers after submitting.'); ?></p>
      </div>
    </form>
  </div>
</section>

<script>
(function(){
  var remaining = <?php echo (int) $remaining; ?>;
  var timerEl = document.getElementById('take-timer');
  var form = document.getElementById('take-form');
  var status = document.getElementById('save-status');
  var warned = {600:false,300:false,60:false}, submitted = false;

  function fmt(s){ var m=Math.floor(s/60), ss=s%60; return (m<10?'0':'')+m+':'+(ss<10?'0':'')+ss; }
  function tick(){
    if(remaining<=0){ if(!submitted){ submitted=true; save(function(){ form.submit(); }); } return; }
    timerEl.textContent = fmt(remaining);
    timerEl.className = remaining<=60 ? 'fw-bold danger' : (remaining<=300 ? 'fw-bold warn' : 'fw-bold');
    [600,300,60].forEach(function(t){ if(remaining<=t && !warned[t]){ warned[t]=true; status.textContent = Math.round(t/60)+' <?php echo get_phrase('minute(s) remaining'); ?>'; } });
    remaining--; setTimeout(tick,1000);
  }
  tick();

  function save(cb){
    var data = new URLSearchParams(new FormData(form));
    fetch('<?php echo site_url('skills/save_progress/' . $attempt['id']); ?>', {method:'POST', body:data, headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(function(r){ return r.json(); }).then(function(j){ if(j.ok){ status.textContent='<?php echo get_phrase('Saved'); ?> ✓'; } if(cb) cb(); })
      .catch(function(){ if(cb) cb(); });
  }
  // autosave on change + every 20s
  form.addEventListener('change', function(){ save(); });
  setInterval(function(){ if(!submitted) save(); }, 20000);
  form.addEventListener('submit', function(){ submitted=true; document.getElementById('submit-btn').disabled=true; });

  // Practical / file artifact uploads (separate multipart request, not the autosave).
  var uploadBase = '<?php echo site_url('skills/upload_file/' . $attempt['id']); ?>/';
  document.querySelectorAll('.sub-upload').forEach(function(box){
    var qid = box.getAttribute('data-qid');
    var fileInput = box.querySelector('.sub-file');
    var btn = box.querySelector('.sub-btn');
    var statusEl = box.querySelector('.sub-status');
    btn.addEventListener('click', function(){
      if(!fileInput.files || !fileInput.files.length){ statusEl.innerHTML = '<span style="color:#e67e22;"><?php echo get_phrase('Choose a file first'); ?></span>'; return; }
      var fd = new FormData(); fd.append('file', fileInput.files[0]);
      btn.disabled = true; statusEl.innerHTML = '<?php echo get_phrase('Uploading…'); ?>';
      fetch(uploadBase + qid, {method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(function(r){ return r.json().then(function(j){ return {ok:r.ok, j:j}; }); })
        .then(function(res){
          btn.disabled = false;
          if(res.j && res.j.ok){
            statusEl.innerHTML = '<i class="fas fa-paperclip"></i> <a href="'+res.j.download+'" target="_blank">'+res.j.name+'</a> — <?php echo get_phrase('uploaded'); ?>';
            fileInput.value = '';
          } else {
            statusEl.innerHTML = '<span style="color:#e74c3c;">'+((res.j && res.j.error) ? res.j.error : '<?php echo get_phrase('Upload failed'); ?>')+'</span>';
          }
        })
        .catch(function(){ btn.disabled = false; statusEl.innerHTML = '<span style="color:#e74c3c;"><?php echo get_phrase('Upload failed'); ?></span>'; });
    });
  });
})();
</script>
