<?php
// One button group of ./buttons.php as a card (a form of its own, saved by buttons.php):
// every button's text, emoji, colour, side, simple mode and - where the bot allows it -
// hiding, per language. ./subapps.php shows the two cards of 📲 the same way, with
// $return set so that saving comes back to it.
function genbtn_card($alias, $key, array $defs, $lang, array $t, array $colors, array $itemLabels, $return = '')
{
    $textOnly = genbtn_text_only($alias);
    ?>
<form method="POST" action="buttons.php?<?= http_build_query(['lang' => $lang]) ?>" class="card fade-up" id="g-<?= htmlspecialchars($alias) ?>" style="margin-bottom:14px">
  <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="alias" value="<?= htmlspecialchars($alias) ?>">
  <?php if ($return !== ''): ?><input type="hidden" name="return" value="<?= htmlspecialchars($return) ?>"><?php endif; ?>
  <div class="card-head"><div><div class="card-title"><?= htmlspecialchars(ui_plain($itemLabels[$key] ?? implode(' · ', array_column($defs, 'name')))) ?></div>
    <div class="card-subtitle"><?= $textOnly ? $t['btnTextOnly'] : (in_array($alias, ['gb', 'kb'], true) ? $t['btnAllPlaces'] : '') ?></div></div></div>
  <div class="card-body">
    <?php foreach ($defs as $idx => $def): $ov = genbtn_override($lang, $key, $idx); $n = "b[$idx]";
      $isHidden = array_key_exists('hidden', $ov) ? (bool) $ov['hidden'] : !empty($def['hidden']);
      if ($alias === 'sf' && (int) $idx === 1): // 💳: drawn as on the confirm page, only shown/hidden here ?>
      <div class="set-row" style="flex-wrap:wrap">
        <div style="min-width:180px"><div class="set-label"><?= htmlspecialchars(ui_plain($def['name'])) ?></div><div class="set-hint"><?= $t['btnSfDirectNote'] ?></div></div>
        <div class="lang-chips" style="flex:1;justify-content:flex-end"><label class="lang-chip"><input type="hidden" name="<?= $n ?>[hidden]" value="0"><input type="checkbox" name="<?= $n ?>[hidden]" value="1"<?= $isHidden ? ' checked' : '' ?>> <?= $t['menuHidden'] ?></label></div>
      </div>
      <?php continue; endif; ?>
      <div class="set-row" style="flex-wrap:wrap">
        <div style="min-width:180px"><div class="set-label"><?= htmlspecialchars(ui_plain($def['name'])) ?></div><?php if (!empty($def['note'])): ?><div class="set-hint"><?= htmlspecialchars(ui_plain($def['note'])) ?></div><?php endif; ?></div>
        <div class="lang-chips" style="flex:1;justify-content:flex-end">
          <input type="text" class="input" name="<?= $n ?>[text]" value="<?= htmlspecialchars((string) ($ov['text'] ?? '')) ?>" placeholder="<?= htmlspecialchars((string) $def['text']) ?>" maxlength="64" style="max-width:220px">
          <?php if (!$textOnly): ?>
            <input type="text" class="input" name="<?= $n ?>[emoji]" value="<?= htmlspecialchars((string) ($ov['emoji'] ?? '')) ?>" placeholder="<?= !empty($ov['emojiIcon']) ? '✦' : $t['menuColEmoji'] ?>" style="width:70px;text-align:center">
            <select class="select" name="<?= $n ?>[style]" style="width:auto"><?php foreach ($colors as $v => $label): ?><option value="<?= $v ?>"<?= (string) ($ov['style'] ?? '') === $v ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select>
            <select class="select" name="<?= $n ?>[pos]" style="width:auto"><option value="right"><?= $t['menuPosRight'] ?></option><option value="left"<?= ($ov['pos'] ?? '') === 'left' ? ' selected' : '' ?>><?= $t['menuPosLeft'] ?></option></select>
            <label class="lang-chip"><input type="hidden" name="<?= $n ?>[simple]" value="0"><input type="checkbox" name="<?= $n ?>[simple]" value="1"<?= !empty($ov['simple']) ? ' checked' : '' ?>> <?= $t['btnSimple'] ?></label>
            <?php if (genbtn_hideable($alias, $idx)): ?>
              <label class="lang-chip"><input type="hidden" name="<?= $n ?>[hidden]" value="0"><input type="checkbox" name="<?= $n ?>[hidden]" value="1"<?= $isHidden ? ' checked' : '' ?>> <?= $t['menuHidden'] ?></label>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="modal-foot" style="border-radius:0 0 10px 10px"><button type="submit" class="btn btn-primary"><?= icon('check', 13) ?> <?= $t['bottextSaveBtn'] ?></button></div>
</form>
<?php
}
