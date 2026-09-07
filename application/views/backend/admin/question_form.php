<?php
$e = $question ?? null;
$opts = $options ?? [];
$rubs = $rubrics ?? [];
$type = $e['question_type'] ?? 'single';
// pad option rows to at least 4 (or existing+1)
$rows = max(4, count($opts) + 1);
?>
<div class="row"><div class="col-lg-10"><div class="card"><div class="card-body">
    <h4 class="page-title"><i class="mdi mdi-help-box title_icon"></i>
        <?php echo $e ? get_phrase('Edit Question') : get_phrase('Add Question'); ?>
        <small class="text-muted">— <?php echo html_escape($assessment['title'] ?? ''); ?></small>
        <a href="<?php echo site_url('skill_admin/questions/' . $assessment['id']); ?>" class="btn btn-link alignToTitle">&larr; <?php echo get_phrase('Questions'); ?></a>
    </h4>

    <form action="<?php echo site_url('skill_admin/question_form/' . $assessment['id'] . ($e ? '/' . $e['id'] : '')); ?>" method="post">
        <input type="hidden" name="id" value="<?php echo $e['id'] ?? ''; ?>">
        <input type="hidden" name="skill_id" value="<?php echo $assessment['skill_id'] ?? ''; ?>">

        <div class="row">
            <div class="col-md-4 mb-3"><label class="form-label"><?php echo get_phrase('Question type'); ?></label>
                <select name="question_type" id="qtype" class="form-control" onchange="toggleType()">
                    <?php foreach ($types as $k => $v): ?><option value="<?php echo $k; ?>" <?php echo $type == $k ? 'selected' : ''; ?>><?php echo get_phrase($v); ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-md-4 mb-3"><label class="form-label"><?php echo get_phrase('Section'); ?></label>
                <select name="section_id" class="form-control">
                    <option value=""><?php echo get_phrase('—'); ?></option>
                    <?php foreach ($sections as $s): ?><option value="<?php echo $s['id']; ?>" <?php echo (($e['section_id'] ?? '') == $s['id']) ? 'selected' : ''; ?>><?php echo html_escape($s['name']); ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-md-4 mb-3"><label class="form-label"><?php echo get_phrase('Competency'); ?></label>
                <select name="competency_id" class="form-control">
                    <option value=""><?php echo get_phrase('—'); ?></option>
                    <?php foreach ($competencies as $c): ?><option value="<?php echo $c['id']; ?>" <?php echo (($e['competency_id'] ?? '') == $c['id']) ? 'selected' : ''; ?>><?php echo html_escape($c['name']); ?></option><?php endforeach; ?>
                </select></div>
        </div>

        <div class="mb-3"><label class="form-label"><?php echo get_phrase('Question text'); ?> *</label>
            <textarea name="question_text" class="form-control" rows="3" required><?php echo html_escape($e['question_text'] ?? ''); ?></textarea></div>

        <div class="row">
            <div class="col-md-3 mb-3"><label class="form-label"><?php echo get_phrase('Difficulty'); ?></label>
                <select name="difficulty" class="form-control">
                    <?php foreach ($difficulties as $k => $v): ?><option value="<?php echo $k; ?>" <?php echo (($e['difficulty'] ?? 'medium') == $k) ? 'selected' : ''; ?>><?php echo get_phrase($v); ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-md-3 mb-3"><label class="form-label"><?php echo get_phrase('Points'); ?></label>
                <input type="number" step="0.01" name="points" class="form-control" value="<?php echo $e['points'] ?? 1; ?>"></div>
            <div class="col-md-3 mb-3"><label class="form-label"><?php echo get_phrase('Time (sec, 0=none)'); ?></label>
                <input type="number" name="time_seconds" class="form-control" value="<?php echo $e['time_seconds'] ?? 0; ?>"></div>
            <div class="col-md-3 mb-3"><label class="form-label"><?php echo get_phrase('Status'); ?></label>
                <select name="status" class="form-control">
                    <?php foreach (['draft'=>'Draft','reviewed'=>'Reviewed','approved'=>'Approved','published'=>'Published'] as $k=>$v): ?>
                    <option value="<?php echo $k; ?>" <?php echo (($e['status'] ?? 'draft') == $k) ? 'selected' : ''; ?>><?php echo get_phrase($v); ?></option><?php endforeach; ?>
                </select></div>
        </div>

        <!-- OPTIONS (choice / matching / ordering) -->
        <div id="block-options" class="mb-3">
            <label class="form-label"><?php echo get_phrase('Options'); ?></label>
            <table class="table table-sm table-bordered">
                <thead><tr>
                    <th><?php echo get_phrase('Option text'); ?></th>
                    <th class="col-correct" style="width:80px;"><?php echo get_phrase('Correct'); ?></th>
                    <th class="col-score" style="width:90px;"><?php echo get_phrase('Score'); ?></th>
                    <th class="col-match" style="width:150px;"><?php echo get_phrase('Match with'); ?></th>
                    <th class="col-order" style="width:90px;"><?php echo get_phrase('Order'); ?></th>
                </tr></thead>
                <tbody>
                <?php for ($i = 0; $i < $rows; $i++): $o = $opts[$i] ?? null; ?>
                    <tr>
                        <td><input type="text" name="option_text[<?php echo $i; ?>]" class="form-control form-control-sm" value="<?php echo html_escape($o['option_text'] ?? ''); ?>"></td>
                        <td class="col-correct text-center"><input type="checkbox" name="option_correct[]" value="<?php echo $i; ?>" <?php echo (!empty($o['is_correct'])) ? 'checked' : ''; ?>></td>
                        <td class="col-score"><input type="number" step="0.01" name="option_score[<?php echo $i; ?>]" class="form-control form-control-sm" value="<?php echo $o['score'] ?? ''; ?>"></td>
                        <td class="col-match"><input type="text" name="option_match[<?php echo $i; ?>]" class="form-control form-control-sm" value="<?php echo html_escape($o['match_key'] ?? ''); ?>"></td>
                        <td class="col-order"><input type="number" name="option_order[<?php echo $i; ?>]" class="form-control form-control-sm" value="<?php echo $o['correct_order'] ?? ''; ?>"></td>
                    </tr>
                <?php endfor; ?>
                </tbody>
            </table>
            <small class="text-muted"><?php echo get_phrase('Single/Multiple/True-False: tick Correct. Matching: put the match on the right. Ordering: set the correct Order number. Situational: use Score for weighted options.'); ?></small>
        </div>

        <!-- NUMERICAL -->
        <div id="block-numeric" class="row mb-3">
            <div class="col-md-3"><label class="form-label"><?php echo get_phrase('Correct number'); ?></label>
                <input type="number" step="any" name="numeric_answer" class="form-control" value="<?php echo $e['numeric_answer'] ?? ''; ?>"></div>
            <div class="col-md-3"><label class="form-label"><?php echo get_phrase('Tolerance ±'); ?></label>
                <input type="number" step="any" name="numeric_tolerance" class="form-control" value="<?php echo $e['numeric_tolerance'] ?? 0; ?>"></div>
        </div>

        <!-- TEXT / PRACTICAL -->
        <div id="block-text">
            <div class="mb-3"><label class="form-label"><?php echo get_phrase('Expected answer / model solution'); ?></label>
                <textarea name="expected_answer" class="form-control" rows="2"><?php echo html_escape($e['expected_answer'] ?? ''); ?></textarea></div>
            <div class="mb-3"><label class="form-label"><?php echo get_phrase('Marking criteria'); ?></label>
                <textarea name="marking_criteria" class="form-control" rows="2"><?php echo html_escape($e['marking_criteria'] ?? ''); ?></textarea></div>
        </div>

        <!-- RUBRIC (practical / long_text) -->
        <div id="block-rubric" class="mb-3">
            <label class="form-label"><?php echo get_phrase('Rubric (for manual review §30)'); ?></label>
            <table class="table table-sm table-bordered">
                <thead><tr><th><?php echo get_phrase('Criterion'); ?></th><th style="width:110px;"><?php echo get_phrase('Max score'); ?></th><th style="width:110px;"><?php echo get_phrase('Weight'); ?></th></tr></thead>
                <tbody>
                <?php $rrows = max(4, count($rubs) + 1); for ($i = 0; $i < $rrows; $i++): $r = $rubs[$i] ?? null; ?>
                    <tr>
                        <td><input type="text" name="rubric_criterion[<?php echo $i; ?>]" class="form-control form-control-sm" value="<?php echo html_escape($r['criterion'] ?? ''); ?>"></td>
                        <td><input type="number" step="0.01" name="rubric_max[<?php echo $i; ?>]" class="form-control form-control-sm" value="<?php echo $r['max_score'] ?? ''; ?>"></td>
                        <td><input type="number" step="0.01" name="rubric_weight[<?php echo $i; ?>]" class="form-control form-control-sm" value="<?php echo $r['weight'] ?? ''; ?>"></td>
                    </tr>
                <?php endfor; ?>
                </tbody>
            </table>
        </div>

        <div class="mb-3"><label class="form-label"><?php echo get_phrase('Explanation (shown after scoring)'); ?></label>
            <input type="text" name="explanation" class="form-control" value="<?php echo html_escape($e['explanation'] ?? ''); ?>"></div>
        <div class="mb-3"><label class="form-label"><?php echo get_phrase('Tags'); ?></label>
            <input type="text" name="tags" class="form-control" value="<?php echo html_escape($e['tags'] ?? ''); ?>"></div>

        <button type="submit" class="btn btn-primary"><i class="mdi mdi-content-save"></i> <?php echo get_phrase('Save Question'); ?></button>
    </form>
</div></div></div></div>

<script>
function toggleType() {
    var t = document.getElementById('qtype').value;
    var choice = ['single','multiple','truefalse'].indexOf(t) > -1;
    var isMatch = t === 'matching', isOrder = t === 'ordering';
    var options = choice || isMatch || isOrder;
    var textual = ['short_text','long_text','file','practical'].indexOf(t) > -1;
    var rubric  = ['long_text','practical'].indexOf(t) > -1;

    document.getElementById('block-options').style.display = options ? '' : 'none';
    document.getElementById('block-numeric').style.display = (t === 'numerical') ? '' : 'none';
    document.getElementById('block-text').style.display    = textual ? '' : 'none';
    document.getElementById('block-rubric').style.display  = rubric ? '' : 'none';

    // column visibility inside options
    var show = function(cls, on){ document.querySelectorAll('.'+cls).forEach(function(el){ el.style.display = on ? '' : 'none'; }); };
    show('col-correct', choice);
    show('col-score', choice);          // weighted (situational) uses score too
    show('col-match', isMatch);
    show('col-order', isOrder);
}
toggleType();
</script>
