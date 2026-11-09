<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/langsync.php';
require_auth();

// 💎 مالی ← 💳 درگاه‌های پرداخت on the web, one language at a time: which
// gateways this language offers, each one's settings (its own for this
// language, or the one shared by all), the cards of card-to-card, the amount
// range and the top-up packages - the stores the bot's hub writes, through its
// own helpers.
$t = $textbotlang['panel'];
$fa = lang_tab_texts('fa');
$gl = $fa['Admin']['GatewayLang'];
$lang = web_lang_pick();
$cur = currency_for_lang($lang);
$curRow = currency_get($cur);
$names = gateway_registry(lang_tab_texts('fa'));
$keys = array_values(array_filter(gateway_all_keys(), fn($k) => gateway_applicable_for_lang($k, $lang)));
$fields = gw_field_registry();
$moneyErr = sprintf($t['prodPriceInvalid'], $curRow['title'] ?? $cur, (int) ($curRow['decimals'] ?? 0));

// 👥 card-to-card for all of this language's users at once (the bot's 👥
// کارت‌به‌کارت کاربرها does the same): a link the browser asks about first
if (isset($_GET['card_all'])) {
    csrf_check_get();
    if (in_array((string) $_GET['card_all'], ['on', 'off', 'offf'], true)) {
        flash('success', sprintf($t['gwCardAllDone'], card_access_apply($lang, (string) $_GET['card_all'])));
    }
    header('Location: gateways.php?' . http_build_query(['lang' => $lang]) . '#gw-card');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $key = (string) ($_POST['gw'] ?? '');
    $err = null;
    if (!in_array($key, $keys, true)) {
        $err = $t['gwMissing'];
    } else {
        // checked first, then written: a mistake leaves the gateway as it was
        $writes = [];
        foreach ($fields[$key] ?? [] as $i => $f) {
            if ($f['type'] === 'cards') {
                $cards = [];
                foreach (preg_split('/\R/u', trim((string) ($_POST['cards'] ?? ''))) as $line) {
                    if (trim($line) === '') {
                        continue;
                    }
                    [$num, $name] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
                    // the bot's rule: digits (Persian ones too), 4 to 64 of them
                    $num = (string) money_normalize(preg_replace('/[\s-]+/u', '', $num));
                    if (!preg_match('/^[0-9A-Za-z]{4,64}$/', $num) || containsHtmlMarkup($name)) {
                        $err = sprintf($t['gwCardInvalid'], $line);
                    }
                    $cards[] = ['number' => $num, 'name' => $name];
                }
                $writes[] = fn() => gw_cards_set($lang, $cards);
                continue;
            }
            $val = trim((string) ($_POST['f'][$i] ?? ''));
            if (gw_field_scope($f) === 'global') {
                // one value for the whole bot, stored as typed
                if ($val !== (string) getPaySettingValue($f['field'])) {
                    $writes[] = fn() => pay_global_set($f['field'], $val);
                }
                continue;
            }
            if ($val === '' || $val === '0') {
                $writes[] = fn() => gw_pay_override_set($f['field'], $lang, '');
            } elseif ($f['type'] === 'money' && !money_valid($val, $cur)) {
                $err = $moneyErr;
            } elseif ($f['type'] === 'number' && (!ctype_digit($val) || (int) $val < 1)) {
                $err = $t['gwNumberInvalid'];
            } else {
                $v = $f['type'] === 'money' ? money_normalize($val) : $val;
                $writes[] = fn() => gw_pay_override_set($f['field'], $lang, $v);
            }
        }
        if ($key === 'card') {
            // what card-to-card keeps per language (the bot's 🔧 تنظیمات فنی
            // درگاه): three switches, the auto-approve wait and the help text
            $smsOn = (string) (select("setting", "smsForwardEnabled", null, null, "select")['smsForwardEnabled'] ?? '0') === '1';
            foreach (card_legacy_toggle_fields() as $field => $def) {
                $want = ($_POST['cl'][$field] ?? '') === '1';
                if ($want !== (pay_value($field, $lang, $def['off']) === $def['on'])) {
                    if ($want && $field === 'autoconfirmcart' && $smsOn) {
                        // a real bank SMS confirms payments then; this would skip it
                        $err = $t['gwCardSmsLock'];
                    }
                    $writes[] = fn() => gw_pay_override_set($field, $lang, $want ? $def['on'] : $def['off']);
                }
            }
            $mins = trim((string) ($_POST['cl_time'] ?? ''));
            $mins = $mins === '' ? '0' : $mins;
            if ($mins !== (string) intval(pay_value('timeauto_not_verify', $lang, '0'))) {
                if (!ctype_digit($mins)) {
                    $err = $t['gwNumberInvalid'];
                } elseif ((int) $mins > 0 && $smsOn) {
                    $err = $t['gwCardSmsLock'];
                }
                $writes[] = fn() => gw_pay_override_set('timeauto_not_verify', $lang, (string) (int) $mins);
            }
            // 🤖 on with no time would confirm nothing: 20 minutes, as in the bot
            if (($_POST['cl']['autoconfirmcart'] ?? '') === '1' && (int) $mins <= 0 && !$smsOn) {
                $writes[] = fn() => gw_pay_override_set('timeauto_not_verify', $lang, '20');
            }
            // 🛡 how many paid purchases/renewals a customer needs MORE than
            // (the bot's «✏️ تغییر تعداد خرید/تمدید لازم»); empty = 3
            $trustMin = trim((string) ($_POST['cl_trustmin'] ?? ''));
            if ($trustMin !== (string) card_trust_min($lang)) {
                $trustN = $trustMin === '' ? '' : money_normalize($trustMin);
                if ($trustMin !== '' && ($trustN === null || (string) (int) $trustN !== (string) $trustN || (int) $trustN < 1 || (int) $trustN > 1000)) {
                    $err = $t['gwCardTrustMinInvalid'];
                } else {
                    $writes[] = fn() => gw_pay_override_set('cardtrustmin', $lang, $trustN === '' ? '' : (string) (int) $trustN);
                }
            }
            // 📅 members who joined by this Gregorian day count too (the bot's
            // «📅 عضوهای قبل از تاریخ»); empty = no date rule
            $trustDate = trim((string) ($_POST['cl_trustdate'] ?? ''));
            if ($trustDate !== card_trust_date($lang)) {
                $trustD = $trustDate === '' ? '' : card_trust_date_parse($trustDate);
                if ($trustD === null) {
                    $err = $t['gwCardTrustDateInvalid'];
                } else {
                    $writes[] = fn() => gw_pay_override_set('cardtrustdate', $lang, $trustD);
                }
            }
            // 👥 🆕 what a new user of this language starts with
            $wantNew = ($_POST['cl_new'] ?? '') === '1';
            if ($wantNew !== (card_new_default($lang) === '1')) {
                $writes[] = fn() => gw_pay_override_set('showcard', $lang, $wantNew ? '1' : '0');
            }
            // 🚫 this language's users in the list, one id a line
            $excNew = [];
            $excBad = [];
            foreach (preg_split('/\s+/u', trim((string) ($_POST['cl_exc'] ?? ''))) as $excId) {
                if ($excId === '') {
                    continue;
                }
                $excRow = db_fetch($pdo, "SELECT id, lang FROM user WHERE id = ?", [$excId]);
                $excLang = ($excRow && in_array($excRow['lang'] ?? '', panel_langs(), true)) ? $excRow['lang'] : 'fa';
                if (!$excRow || $excLang !== $lang) {
                    $excBad[] = $excId;
                    continue;
                }
                $excNew[] = (string) $excRow['id'];
            }
            if ($excBad) {
                $err = sprintf($t['gwCardExcBad'], htmlspecialchars(implode(', ', $excBad)));
            }
            $excNew = array_values(array_unique($excNew));
            $excOld = array_map('strval', array_keys(card_exc_members($lang)));
            sort($excNew);
            sort($excOld);
            if ($excNew !== $excOld) {
                $writes[] = fn() => card_exc_set_lang($lang, $excNew);
            }
            $help = gw_pay_override_has('helpcart', $lang) ? json_decode((string) pay_value('helpcart', $lang, ''), true) : null;
            $helpNew = trim(str_replace("\r\n", "\n", (string) ($_POST['cl_help'] ?? '')));
            if (($_POST['cl_help_shared'] ?? '') === '1') {
                if ($help !== null) {
                    $writes[] = fn() => gw_pay_override_set('helpcart', $lang, '');
                }
            } elseif ($helpNew !== (string) ($help['text'] ?? '')) {
                if (!web_tg_html_ok($helpNew)) {
                    $err = $t['helpHtmlInvalid'];
                }
                // its photo or video, sent from the bot, stays with it
                $media = is_array($help) && in_array($help['type'] ?? '', ['photo', 'video'], true);
                $data = is_array($help) ? $help : ['type' => 'text'];
                $data['text'] = $helpNew;
                $writes[] = ($helpNew === '' && !$media)
                    ? fn() => gw_pay_override_set('helpcart', $lang, '')
                    : fn() => gw_pay_override_set('helpcart', $lang, json_encode($data));
            }
        }
        if ($key === 'ton') {
            // the memo the customer sends with the payment: the bot's rules
            $memo = topup_memo_config($lang, 'ton');
            $lim = topup_memo_limits();
            $prefix = trim((string) ($_POST['memo_prefix'] ?? ''));
            if ($prefix !== $memo['prefix']) {
                $clean = topup_memo_clean_prefix($prefix);
                if ($prefix !== '' && $clean === '') {
                    $err = $t['gwTonPrefixInvalid'];
                }
                $writes[] = fn() => topup_memo_set($lang, 'ton', 'prefix', $clean);
            }
            $len = trim((string) ($_POST['memo_len'] ?? ''));
            if ($len !== (string) $memo['len']) {
                if (!ctype_digit($len) || (int) $len < $lim['lenMin'] || (int) $len > $lim['lenMax']) {
                    $err = $t['gwTonLenInvalid'];
                }
                $writes[] = fn() => topup_memo_set($lang, 'ton', 'len', (int) $len);
            }
        }
        // the amount range, checked as the bot's 🏦 screen checks it: '0' or
        // empty clears a side, and only a side that changed is checked
        $tp = $fa['Admin']['TopupPkg'];
        $curTitle = $curRow['title'] ?? $cur;
        [$oldMin, $oldMax] = topup_minmax_for($lang, $key);
        $range = [];
        foreach (['min', 'max'] as $side) {
            $v = trim((string) ($_POST[$side] ?? ''));
            if ($v === '' || $v === '0') {
                $range[$side] = '';
            } elseif (money_valid($v, $cur)) {
                $range[$side] = money_normalize($v);
            } else {
                $err = $moneyErr;
                $range[$side] = (string) ($side === 'min' ? $oldMin : $oldMax);
            }
        }
        $minChanged = $range['min'] !== (string) $oldMin;
        $maxChanged = $range['max'] !== (string) $oldMax;
        $floor = topup_usd_floor_toman($lang, $key);
        if ($minChanged && $floor !== null && ($range['min'] === '' || (float) $range['min'] < $floor)) {
            // an online gateway cannot go under a dollar, so neither can its minimum
            $err = strtr($tp[topup_floor_text_key($key, 'below')], ['{price}' => number_format($floor), '{currency}' => $curTitle]);
        } elseif ($minChanged || $maxChanged) {
            // what the customer would be held to with the new pair - the bot's
            // topup_effective_limits(), fed the pair about to be saved
            $effMin = $range['min'] !== '' ? $range['min'] : topup_gateway_min($lang, $key);
            $effMax = $range['max'] !== '' ? $range['max'] : topup_gateway_max($lang, $key);
            if ($floor !== null && ($effMin === null || (float) $effMin < $floor)) {
                $effMin = $floor;
            }
            // a minimum above the maximum would refuse every amount
            if ($effMin !== null && $effMax !== null && (float) $effMin > (float) $effMax) {
                $err = $minChanged
                    ? strtr($tp['minAboveMax'], ['{max}' => number_format((float) $effMax), '{currency}' => $curTitle])
                    : strtr($tp['maxBelowMin'], ['{min}' => number_format((float) $effMin), '{currency}' => $curTitle]);
            }
        }
        if ($minChanged || $maxChanged) {
            $writes[] = fn() => topup_minmax_set($lang, $key, $range['min'], $range['max']);
        }
        // packages: one a line, «amount» or «amount | label»; each keeps the
        // look it already had in the bot
        $old = [];
        foreach (topup_packages_for($lang, $key) as $p) {
            $old[(string) $p['amount']] = $p;
        }
        $packages = [];
        foreach (preg_split('/\R/u', trim((string) ($_POST['packages'] ?? ''))) as $line) {
            if (trim($line) === '') {
                continue;
            }
            [$amount, $label] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            if (!money_valid($amount, $cur)) {
                $err = $moneyErr;
                continue;
            }
            $amount = money_normalize($amount);
            $packages[] = array_merge($old[$amount] ?? [], ['amount' => $amount, 'label' => $label]);
        }
        if ($err === null) {
            foreach ($writes as $w) {
                $w();
            }
            topup_packages_set($lang, $key, $packages);
            // offered in this language: this gateway added to or taken off the
            // list the bot's per-language switch keeps, the rest left alone
            $map = gateway_lang_map();
            $offered = (isset($map[$lang]) && is_array($map[$lang])) ? $map[$lang] : [];
            $wasOffered = in_array($key, $offered, true);
            $wantOffered = ($_POST['offered'] ?? '') === '1';
            if ($wantOffered !== $wasOffered) {
                gateway_set_for_lang($lang, $wantOffered ? array_merge($offered, [$key]) : array_diff($offered, [$key]));
            }
            $globalOn = ($_POST['global'] ?? '') === '1';
            if ($wantOffered && !$wasOffered) {
                // as in the bot: switched on here, the shared switch goes on
                // too - otherwise it shows ✅ and the customer sees nothing
                $globalOn = true;
            }
            if ($globalOn !== gateway_globally_on($key)) {
                gateway_globally_set($key, $globalOn);
            }
        }
    }
    flash($err === null ? 'success' : 'error', $err ?? $t['refSaved']);
    header('Location: gateways.php?' . http_build_query(['lang' => $lang]) . '#gw-' . $key);
    exit;
}

$pageTitle = $t['gwPageTitle'];
$pageLede = $t['gwPageLede'];
$activeNav = 'gateways';
include __DIR__ . '/inc/layout_head.php';
echo web_lang_assets();
$chk = fn($name, $on, $label) => '<label class="lang-chip"><input type="hidden" name="' . $name . '" value="0"><input type="checkbox" name="' . $name . '" value="1"' . ($on ? ' checked' : '') . '> ' . htmlspecialchars(ui_plain($label)) . '</label>';
?>

<?= web_lang_tabs($lang, fn($code) => 'gateways.php?' . http_build_query(['lang' => $code])) ?>

<div class="card fade-up" style="margin-bottom:14px">
  <div class="card-body"><?= sprintf($t['gwCurrencyLine'], htmlspecialchars(web_langs()[$lang]), htmlspecialchars(($curRow['title'] ?? $cur) . (!empty($curRow['symbol']) ? ' (' . $curRow['symbol'] . ')' : ''))) ?></div>
</div>

<?php foreach ($keys as $key):
  $offered = gateway_allowed_for_lang($key, $lang);
  $globalOn = gateway_globally_on($key);
  [$min, $max] = topup_minmax_for($lang, $key);
  $pk = implode("\n", array_map(fn($p) => $p['amount'] . (($p['label'] ?? '') !== '' ? ' | ' . $p['label'] : ''), topup_packages_for($lang, $key)));
?>
<form method="POST" action="gateways.php?<?= http_build_query(['lang' => $lang]) ?>" class="card fade-up" id="gw-<?= htmlspecialchars($key) ?>" style="margin-bottom:14px">
  <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="gw" value="<?= htmlspecialchars($key) ?>">
  <div class="card-head"><div><div class="card-title"><?= ($offered && $globalOn) ? '⟦i:circle-check:ok⟧' : '⟦i:circle-x:no⟧' ?> <?= htmlspecialchars(ui_plain($names[$key] ?? $key)) ?></div>
    <div class="card-subtitle"><?= ($offered && !$globalOn) ? $t['gwGlobalOffNote'] : '' ?></div></div></div>
  <div class="card-body">
    <div class="set-row"><div><div class="set-label"><?= $t['gwOffered'] ?></div><div class="set-hint"><?= $t['gwOfferedHint'] ?></div></div><div class="set-ctl"><?= $chk('offered', $offered, $t['refOn']) ?></div></div>
    <div class="set-row"><div><div class="set-label"><?= $t['gwGlobal'] ?></div><div class="set-hint"><?= $t['gwGlobalHint'] ?></div></div><div class="set-ctl"><?= $chk('global', $globalOn, $t['refOn']) ?></div></div>
    <?php foreach ($fields[$key] ?? [] as $i => $f): ?>
      <?php if ($f['type'] === 'cards'): ?>
        <div class="set-row"><div><div class="set-label"><?= htmlspecialchars(ui_plain($gl[$f['label']] ?? $f['label'])) ?></div><div class="set-hint"><?= $t['gwCardsHint'] ?></div></div>
          <div class="set-ctl"><textarea class="textarea" name="cards" rows="3" dir="ltr" placeholder="6037991122223333 | Ali"><?= htmlspecialchars(implode("\n", array_map(fn($c) => $c['number'] . (($c['name'] ?? '') !== '' ? ' | ' . $c['name'] : ''), gw_cards_has_override($lang) ? gw_cards_for_lang($lang) : []))) ?></textarea></div></div>
      <?php else: $global = gw_field_scope($f) === 'global'; $val = $global ? (string) getPaySettingValue($f['field']) : (gw_pay_override_has($f['field'], $lang) ? (string) pay_value($f['field'], $lang, '') : ''); ?>
        <div class="set-row"><div><div class="set-label"><?= htmlspecialchars(($global ? '⟦i:globe⟧ ' : '') . ui_plain($gl[$f['label']] ?? $f['label'])) ?></div>
          <div class="set-hint"><?= $global ? $t['gwFieldGlobal'] : sprintf($t['gwFieldOwn'], htmlspecialchars((string) pay_value($f['field'], null, '—'))) ?></div></div>
          <div class="set-ctl"><input type="text" class="input" name="f[<?= $i ?>]" value="<?= htmlspecialchars($val) ?>" dir="auto"></div></div>
      <?php endif; ?>
    <?php endforeach; ?>
    <?php if ($key === 'card'): $help = gw_pay_override_has('helpcart', $lang) ? json_decode((string) pay_value('helpcart', $lang, ''), true) : null; ?>
      <div class="set-row"><div><div class="set-label">⟦i:credit-card⟧ <?= $t['gwCardLegacy'] ?></div><div class="set-hint"><?= $t['gwCardLegacyHint'] ?></div></div>
        <div class="set-ctl lang-chips"><?php foreach (card_legacy_toggle_fields() as $field => $def): ?><label class="lang-chip"><input type="checkbox" name="cl[<?= $field ?>]" value="1"<?= pay_value($field, $lang, $def['off']) === $def['on'] ? ' checked' : '' ?>> <?= htmlspecialchars(ui_plain($def['label'])) ?></label><?php endforeach; ?></div></div>
      <div class="set-row"><div><div class="set-label"><?= $t['gwCardTime'] ?></div><div class="set-hint"><?= $t['gwCardTimeHint'] ?></div></div>
        <div class="set-ctl"><input type="text" class="input" name="cl_time" value="<?= intval(pay_value('timeauto_not_verify', $lang, '0')) ?>" inputmode="numeric"></div></div>
      <div class="set-row"><div><div class="set-label">⟦i:shield-check⟧ <?= $t['gwCardTrustMin'] ?></div><div class="set-hint"><?= $t['gwCardTrustMinHint'] ?></div></div>
        <div class="set-ctl"><input type="text" class="input" name="cl_trustmin" value="<?= card_trust_min($lang) ?>" inputmode="numeric"></div></div>
      <?php $trustDay = card_trust_date($lang); ?>
      <div class="set-row"><div><div class="set-label">⟦i:calendar⟧ <?= $t['gwCardTrustDate'] ?></div><div class="set-hint"><?= $t['gwCardTrustDateHint'] ?><?= $trustDay !== '' ? '<br>' . sprintf($t['gwCardTrustDateNow'], htmlspecialchars(card_trust_date_label($lang)), number_format(card_trust_date_count($lang))) : '' ?></div></div>
        <div class="set-ctl"><input type="date" class="input" name="cl_trustdate" value="<?= htmlspecialchars($trustDay) ?>" min="2000-01-01" max="2100-12-31" dir="ltr"></div></div>
      <div class="set-row"><div><div class="set-label"><?= $t['gwCardHelp'] ?></div><div class="set-hint"><?= $t['gwCardHelpHint'] ?><?= is_array($help) && in_array($help['type'] ?? '', ['photo', 'video'], true) ? '<br>' . sprintf($t['gwCardHelpMedia'], $help['type'] === 'photo' ? '⟦i:image⟧' : '⟦i:clapperboard⟧') : '' ?></div></div>
        <div class="set-ctl"><textarea class="textarea" name="cl_help" rows="3" dir="auto"><?= htmlspecialchars((string) ($help['text'] ?? '')) ?></textarea>
          <?php if ($help !== null): ?><label class="lang-chip" style="margin-top:6px"><input type="checkbox" name="cl_help_shared" value="1"> <?= $t['gwCardHelpShared'] ?></label><?php endif; ?></div></div>
      <?php [$accOn, $accOff] = card_access_counts($lang); ?>
      <div class="set-row"><div><div class="set-label"><?= $t['gwCardAccess'] ?></div><div class="set-hint"><?= sprintf($t['gwCardAccessHint'], $accOn, $accOff) ?></div></div>
        <div class="set-ctl lang-chips"><?= $chk('cl_new', card_new_default($lang) === '1', $t['gwCardNewUsers']) ?></div></div>
      <div class="set-row"><div><div class="set-label"><?= $t['gwCardAll'] ?></div><div class="set-hint"><?= $t['gwCardAllHint'] ?></div></div>
        <div class="set-ctl" style="display:flex;flex-wrap:wrap;gap:6px">
          <?php foreach (['on' => 'gwCardAllOn', 'off' => 'gwCardAllOff', 'offf' => 'gwCardAllOffAgents'] as $op => $lbl): ?>
            <a class="btn btn-ghost btn-sm" href="gateways.php?<?= http_build_query(['lang' => $lang, 'card_all' => $op, '_csrf' => csrf_token()]) ?>" data-confirm="<?= htmlspecialchars(sprintf($t['gwCardAllConfirm_' . $op], card_access_target_count($lang, $op), web_langs()[$lang])) ?>"><?= $t[$lbl] ?></a>
          <?php endforeach; ?>
        </div></div>
      <div class="set-row"><div><div class="set-label"><?= $t['gwCardExc'] ?></div><div class="set-hint"><?= $t['gwCardExcHint'] ?></div></div>
        <div class="set-ctl"><textarea class="textarea" name="cl_exc" rows="3" dir="ltr" placeholder="123456789"><?= htmlspecialchars(implode("\n", array_keys(card_exc_members($lang)))) ?></textarea></div></div>
    <?php endif; ?>
    <?php $cbUrl = ['frenzyex' => 'frenzyex_callback_url', 'variza' => 'variza_webhook_url', 'tronado' => 'tronado_callback_url', 'hooshpay' => 'hooshpay_callback_url'][$key] ?? null; ?>
    <?php if ($cbUrl !== null && function_exists($cbUrl)): ?>
      <div class="set-row"><div><div class="set-label">⟦i:link⟧ <?= $t['gwCallbackUrl'] ?></div><div class="set-hint"><?= $t['gwCallbackUrlHint'] ?></div></div>
        <div class="set-ctl"><input type="text" class="input" value="<?= htmlspecialchars($cbUrl()) ?>" dir="ltr" readonly onclick="this.select()"></div></div>
    <?php endif; ?>
    <?php if ($key === 'ton'): $memo = topup_memo_config($lang, 'ton'); ?>
      <div class="set-row"><div><div class="set-label">⟦i:tag⟧ <?= $t['gwTonPrefix'] ?></div><div class="set-hint"><?= $t['gwTonPrefixHint'] ?></div></div>
        <div class="set-ctl"><input type="text" class="input" name="memo_prefix" value="<?= htmlspecialchars($memo['prefix']) ?>" dir="ltr" maxlength="12"></div></div>
      <div class="set-row"><div><div class="set-label">⟦i:dices⟧ <?= $t['gwTonLen'] ?></div><div class="set-hint"><?= $t['gwTonLenHint'] ?></div></div>
        <div class="set-ctl"><input type="text" class="input" name="memo_len" value="<?= (int) $memo['len'] ?>" inputmode="numeric"></div></div>
    <?php endif; ?>
    <div class="set-row"><div><div class="set-label"><?= sprintf($t['gwRange'], htmlspecialchars($curRow['title'] ?? $cur)) ?></div><div class="set-hint"><?= $t['gwRangeHint'] ?></div></div>
      <div class="set-ctl" style="display:flex;gap:6px"><input type="text" class="input" name="min" value="<?= htmlspecialchars((string) $min) ?>" placeholder="<?= $t['gwMin'] ?>" inputmode="decimal"><input type="text" class="input" name="max" value="<?= htmlspecialchars((string) $max) ?>" placeholder="<?= $t['gwMax'] ?>" inputmode="decimal"></div></div>
    <div class="set-row"><div><div class="set-label"><?= $t['gwPackages'] ?></div><div class="set-hint"><?= $t['gwPackagesHint'] ?></div></div>
      <div class="set-ctl"><textarea class="textarea" name="packages" rows="3" dir="auto" placeholder="50000&#10;100000 | 100K"><?= htmlspecialchars($pk) ?></textarea></div></div>
  </div>
  <div class="modal-foot" style="border-radius:0 0 10px 10px"><button type="submit" class="btn btn-primary"><?= icon('check', 13) ?> <?= $t['bottextSaveBtn'] ?></button></div>
</form>
<?php endforeach; ?>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
