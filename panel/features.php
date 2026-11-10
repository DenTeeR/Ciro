<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/langsync.php';
require_auth();

// 🌐 وضعیت قابلیت‌ها (هر زبان) on the web: the bot's per-language switches
// (setting.feature_lang) and their ⚙️ settings (feature_lang_settings, the
// app table), one language at a time, with the bot's own words and rules.
$t = $textbotlang['panel'];
$fa = lang_tab_texts('fa');
$fs = $fa['Admin']['FeatureSection'];
$lang = web_lang_pick();
$cur = currency_for_lang($lang);
$curTitle = currency_get($cur)['title'] ?? $cur;
$setting = db_fetch($pdo, "SELECT * FROM setting LIMIT 1") ?? [];
$back = fn($card) => 'features.php?' . http_build_query(['lang' => $lang]) . '#' . $card;

// every switch of the bot's screen, in its order: key => [on, off, label]
$switches = [
    'Bot_Status' => ['botstatuson', 'botstatusoff', $fa['Admin']['Status']['statusBot']],
    'inlinebtnmain' => ['oninline', 'offinline', $fa['Admin']['Status']['inlinebtns']],
    'NotUser' => ['onnotuser', 'offnotuser', $fa['Admin']['Status']['statusUsernameBtn']],
    'statusagentrequest' => ['onrequestagent', 'offrequestagent', $fa['Admin']['Status']['statusShowAgent']],
    'roll_Status' => ['rolleon', 'rolleoff', $fa['Admin']['Status']['statusRole']],
    'verifystart' => ['onverify', 'offverify', $fa['keyboard']['authenticate']],
    'statussupportpv' => ['onpvsupport', 'offpvsupport', $fa['keyboard']['supportInPv']],
    'statusnamecustom' => ['onnamecustom', 'offnamecustom', $fa['keyboard']['configNote']],
    'statusnoteforf' => ['1', '0', $fa['keyboard']['userNote']],
    'bulkbuy' => ['onbulk', 'offbulk', $fa['keyboard']['bulkPurchaseStatus']],
    'verifybucodeuser' => ['onverify', 'offverify', $fa['keyboard']['authWithLink']],
    'categoryhelp' => ['1', '0', $fa['keyboard']['educationCategory']],
    'wheelagent' => ['1', '0', $fa['keyboard']['agentWheelOfLuck']],
    'Dice' => ['1', '0', $fa['keyboard']['showDice']],
    'statusfirstwheel' => ['1', '0', $fa['keyboard']['firstPurchaseWheel']],
    'Debtsettlement' => ['1', '0', $fa['keyboard']['settleDebt']],
    'statuscopycart' => ['1', '0', $fa['keyboard']['copyCard']],
    'get_number' => ['onAuthenticationphone', 'offAuthenticationphone', $fa['Admin']['Status']['Authenticationphone']],
    'linkappstatus' => ['1', '0', $fa['keyboard']['appDownloadLinkAlt']],
    'wheelـluck' => ['1', '0', $fa['keyboard']['wheelOfLuck']],
    'affiliatesstatus' => ['onaffiliates', 'offaffiliates', $fa['keyboard']['affiliateGift']],
    'statuslimitchangeloc' => ['1', '0', $fa['keyboard']['locationChangeLimit']],
    'scorestatus' => ['1', '0', $fa['keyboard']['nightLottery']],
];
$switchOn = function ($key) use ($switches, $lang, $setting) {
    $v = (string) feature_value($key, $lang, $setting[$key] ?? '');
    // the bot counts anything but «off» as on for this one
    return $key === 'Bot_Status' ? $v !== $switches[$key][1] : $v === $switches[$key][0];
};
// 🛒 وضعیت قابلیت‌های فروشگاه, in its order: key => [on, off, label, the
// shop-wide value a language without its own follows (after Persian's)]
$shopRows = [];
foreach (db_fetchAll($pdo, "SELECT Namevalue, value FROM shopSetting") as $r) {
    $shopRows[$r['Namevalue']] = (string) $r['value'];
}
$shopSwitches = [
    'extravolunme' => ['onextra', 'offextra', $fa['Admin']['Status']['statusVolumeExtra'], $shopRows['statusextra'] ?? ''],
    'paydirect' => ['ondirectbuy', 'offdirectbuy', $fa['Admin']['Status']['paydirect'], $shopRows['statusdirectpabuy'] ?? ''],
    'statustimeextra' => ['ontimeextraa', 'offtimeextraa', $fa['Admin']['Status']['statusTimeExtra'], $shopRows['statustimeextra'] ?? ''],
    'disorderss' => ['ondisorder', 'offdisorder', $fa['keyboard']['sendDisruptionReport'], $shopRows['statusdisorder'] ?? ''],
    'panelshow' => ['onpanelshow', 'offpanelshow', $fa['Admin']['Status']['showPanelSelection'], (string) ($setting['statuspanelshow'] ?? 'onpanelshow')],
    'categroygenral' => ['oncategorys', 'offcategorys', $fa['keyboard']['categoryBug'], (string) ($setting['statuscategorygenral'] ?? '')],
    'categorytime' => ['oncategory', 'offcategory', $fa['Admin']['Status']['statusCategoryTime'], (string) ($setting['statuscategory'] ?? '')],
    'changgestatus' => ['onstatus', 'offstatus', $fa['keyboard']['deactivateAccountStatus'], $shopRows['statuschangeservice'] ?? ''],
    'showprice' => ['onshowprice', 'offshowprice', $fa['keyboard']['showProductPrice'], $shopRows['statusshowprice'] ?? ''],
    'showconfig' => ['onconfig', 'offconfig', $fa['keyboard']['getConfigBtn'], $shopRows['configshow'] ?? ''],
    'removeservicebackbtn' => ['on', 'off', $fa['keyboard']['refundBtn'], $shopRows['backserviecstatus'] ?? ''],
];
$shopOn = fn($key) => (string) shop_feature_value($key, $lang, $shopSwitches[$key][3]) === $shopSwitches[$key][0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $card = (string) ($_POST['card'] ?? '');
    $err = null;
    $posted = fn($k) => isset($_POST[$k]) && $_POST[$k] === '1';
    if ($card === 'switches') {
        foreach ($switches as $key => [$on, $off]) {
            // written only where it changed: a language nobody set keeps
            // following Persian, as in the bot
            if ($posted('sw_' . md5($key)) !== $switchOn($key)) {
                feature_set($key, $lang, $posted('sw_' . md5($key)) ? $on : $off);
            }
        }
    } elseif ($card === 'shop') {
        foreach ($shopSwitches as $key => [$on, $off]) {
            // this language's own value, and only where it changed - the
            // shop-wide one stays as it is for everyone else, as in the bot
            if ($posted('shop_' . $key) !== $shopOn($key)) {
                shop_feature_set($key, $lang, $posted('shop_' . $key) ? $on : $off);
            }
        }
    } elseif ($card === 'dpay') {
        // 💳 پرداخت مستقیم (directpay.php): each switch written only where it changed
        $now = dpay_cfg($lang);
        if ($posted('dpay_on') !== $now['on']) {
            dpay_toggle($lang, 'on');
        }
        foreach (dpay_paths_all() as $path) {
            if ($posted('dpay_' . $path) !== in_array($path, $now['paths'], true)) {
                dpay_toggle($lang, $path);
            }
        }
        if ($posted('dpay_limits') !== $now['limits']) {
            dpay_toggle($lang, 'limits');
        }
        if ($posted('dpay_gift') !== $now['gift']) {
            dpay_toggle($lang, 'gift');
        }
    } elseif ($card === 'extras') {
        // 📨 🔁 💌 💸 (extras.php): checked first, written after - a bad number leaves all as it was
        $num = function ($k, $lo, $hi) use (&$err, $t) {
            $raw = trim((string) ($_POST[$k] ?? ''));
            if (!ctype_digit($raw) || (int) $raw < $lo || (int) $raw > $hi) {
                $err = sprintf($t['featXfRange'], $lo, $hi);
                return null;
            }
            return (string) (int) $raw;
        };
        $vals = [
            'ar_hours' => $num('ar_hours', 1, 72),
            'wb_days' => $num('wb_days', 1, 60),
            'wb_percent' => $num('wb_percent', 1, 90),
            'wb_valid' => $num('wb_valid', 1, 60),
        ];
        $wtMin = money_normalize($_POST['wt_min'] ?? '');
        $wtMax = money_normalize(($_POST['wt_max'] ?? '') === '' ? '0' : $_POST['wt_max']);
        if ($wtMin === null || (float) $wtMin <= 0 || $wtMax === null) {
            $err = $t['featXfMoney'];
        }
        if ($err === null) {
            foreach (['tk_on', 'ar_on', 'ar_notify', 'wb_on', 'wt_on'] as $k) {
                $now = (string) feature_setting_value($k, $lang, $k === 'ar_notify' ? '1' : '0') === '1';
                if ($posted($k) !== $now) {
                    feature_setting_set($k, $lang, $posted($k) ? '1' : '0');
                }
            }
            foreach ($vals as $k => $val) {
                feature_setting_set($k, $lang, $val);
            }
            feature_setting_set('wt_min', $lang, (string) (float) $wtMin);
            feature_setting_set('wt_max', $lang, (string) (float) $wtMax);
        }
    } elseif ($card === 'phone') {
        $codes = preg_split('/[\s,،]+/u', trim((string) ($_POST['phone_prefix'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
        if (($_POST['phone_default'] ?? '') === '1') {
            // "" is "never set": this language's built-in default again
            feature_setting_set('phone_prefix', $lang, '');
        } else {
            foreach ($codes as $c) {
                if (!preg_match('/^\+?\d{1,4}$/', $c)) {
                    $err = $t['featPhoneInvalid'];
                }
            }
            if ($err === null) {
                phone_prefixes_set($lang, $codes);
            }
        }
        $aud = in_array($_POST['phone_audience'] ?? '', ['all', 'new', 'invited'], true) ? $_POST['phone_audience'] : 'all';
        if ($err === null && $aud !== (string) feature_setting_value('phone_audience', $lang, 'all')) {
            feature_setting_set('phone_audience', $lang, $aud);
            // «new» means from now on
            feature_setting_set('phone_audience_since', $lang, $aud === 'new' ? (string) time() : '0');
        }
    } elseif ($card === 'wheel') {
        if (!money_valid($_POST['wheel_price'] ?? '', $cur)) {
            $err = sprintf($t['prodPriceInvalid'], $curTitle, (int) (currency_get($cur)['decimals'] ?? 0));
        } else {
            feature_setting_set('wheel_price', $lang, money_normalize($_POST['wheel_price']));
        }
    } elseif ($card === 'loc') {
        $all = trim((string) ($_POST['loc_limit_all'] ?? ''));
        $free = trim((string) ($_POST['loc_limit_free'] ?? ''));
        if (!ctype_digit($all) || !ctype_digit($free)) {
            $err = $t['featCountInvalid'];
        } else {
            feature_setting_set('loc_limit_all', $lang, (string) (int) $all);
            feature_setting_set('loc_limit_free', $lang, (string) (int) $free);
        }
    } elseif ($card === 'locreset') {
        // this language's users only, as from the bot's tab
        db_query($pdo, "UPDATE user SET limitchangeloc = '0' WHERE lang = ?", [$lang]);
    } elseif ($card === 'lottery') {
        $prizes = [];
        foreach ([1, 2, 3] as $r) {
            $v = (string) ($_POST['lottery_' . $r] ?? '');
            if (!money_valid($v, $cur)) {
                $err = sprintf($t['prodPriceInvalid'], $curTitle, (int) (currency_get($cur)['decimals'] ?? 0));
            }
            $prizes[$r] = $v;
        }
        if ($err === null) {
            foreach ($prizes as $r => $v) {
                lottery_prize_set($lang, $r, money_normalize($v));
            }
            feature_set('Lotteryagent', $lang, $posted('lottery_agents') ? '1' : '0');
        }
    } elseif ($card === 'appadd') {
        $name = trim((string) ($_POST['app_name'] ?? ''));
        $link = trim((string) ($_POST['app_link'] ?? ''));
        if ($name === '' || mb_strlen($name) > 200) {
            $err = strip_tags($fa['Admin']['apps']['nameTooLong']);
        } elseif (!filter_var($link, FILTER_VALIDATE_URL)) {
            $err = strip_tags($fa['Admin']['managepanel']['invalidDomain']);
        } else {
            // a row added from a language tab belongs to that language only
            db_query($pdo, "INSERT INTO app (name, link, lang) VALUES (?, ?, ?)", [$name, $link, $lang]);
        }
    } elseif ($card === 'appedit' || $card === 'appdel') {
        $app = db_fetch($pdo, "SELECT * FROM app WHERE id = ?", [(int) ($_POST['app_id'] ?? 0)]);
        $rest = $app ? array_values(array_diff(app_row_langs($app), [$lang])) : [];
        $link = trim((string) ($_POST['app_link'] ?? ''));
        if (!$app || !in_array($lang, app_row_langs($app), true)) {
            $err = $t['featAppMissing'];
        } elseif ($card === 'appedit' && !filter_var($link, FILTER_VALIDATE_URL)) {
            $err = strip_tags($fa['Admin']['managepanel']['invalidDomain']);
        } elseif ($rest) {
            // a row other tabs show too: this tab gets its own copy (edit) or
            // simply stops showing it (delete); the others keep theirs
            db_query($pdo, "UPDATE app SET lang = ? WHERE id = ?", [implode(',', $rest), $app['id']]);
            if ($card === 'appedit') {
                db_query($pdo, "INSERT INTO app (name, link, lang) VALUES (?, ?, ?)", [$app['name'], $link, $lang]);
            }
        } elseif ($card === 'appedit') {
            db_query($pdo, "UPDATE app SET link = ? WHERE id = ?", [$link, $app['id']]);
        } else {
            db_query($pdo, "DELETE FROM app WHERE id = ?", [$app['id']]);
        }
        $card = 'apps';
    }
    flash($err === null ? 'success' : 'error', $err ?? $t['refSaved']);
    header('Location: ' . $back($card));
    exit;
}

$limit = json_decode((string) ($setting['limitnumber'] ?? ''), true);
$limit = is_array($limit) ? $limit : [];
$v = [
    'phone' => implode(',', phone_prefixes_for_lang($lang)),
    'aud' => (string) feature_setting_value('phone_audience', $lang, 'all'),
    'wheel' => feature_setting_value('wheel_price', $lang, (string) ($setting['wheelـluck_price'] ?? '0')),
    'locAll' => feature_setting_value('loc_limit_all', $lang, (string) ($limit['all'] ?? 0)),
    'locFree' => feature_setting_value('loc_limit_free', $lang, (string) ($limit['free'] ?? 0)),
    'prizes' => lottery_prizes_for_lang($lang),
    'lotteryAgents' => (string) feature_value('Lotteryagent', $lang, (string) ($setting['Lotteryagent'] ?? '0')) === '1',
];
$apps = app_rows_for_lang($lang);

$chk = fn($name, $on, $label) => '<label class="lang-chip"><input type="hidden" name="' . $name . '" value="0"><input type="checkbox" name="' . $name . '" value="1"' . ($on ? ' checked' : '') . '> ' . htmlspecialchars(ui_plain($label)) . '</label>';
$field = fn($label, $ctl, $hint = '') => '<div class="set-row"><div><div class="set-label">' . htmlspecialchars(ui_plain($label)) . '</div>' . ($hint !== '' ? '<div class="set-hint">' . ui_plain($hint) . '</div>' : '') . '</div><div class="set-ctl">' . $ctl . '</div></div>';
$input = fn($name, $value, $extra = '') => '<input type="text" class="input" name="' . $name . '" value="' . htmlspecialchars((string) $value) . '" ' . $extra . '>';
$cardHead = fn($title, $sub) => '<div class="card-head"><div><div class="card-title">' . $title . '</div><div class="card-subtitle">' . $sub . '</div></div></div>';
$formOpen = fn($card, $extra = '') => '<form method="POST" action="features.php?' . http_build_query(['lang' => $lang]) . '"' . $extra . '><input type="hidden" name="_csrf" value="' . csrf_token() . '"><input type="hidden" name="card" value="' . $card . '">';
$saveBtn = '<div class="modal-foot" style="border-radius:0 0 10px 10px"><button type="submit" class="btn btn-primary">' . icon('check', 13) . ' ' . $t['bottextSaveBtn'] . '</button></div>';
$langName = web_langs()[$lang];

$pageTitle = $t['featPageTitle'];
$pageLede = $t['featPageLede'];
$activeNav = 'features';
include __DIR__ . '/inc/layout_head.php';
echo web_lang_assets();
?>

<?= web_lang_tabs($lang, fn($code) => 'features.php?' . http_build_query(['lang' => $code])) ?>

<div class="card fade-up" id="switches" style="margin-bottom:14px">
  <?= $cardHead('⟦i:globe⟧ ' . $t['featSwitchesTitle'], sprintf($t['featSwitchesSub'], htmlspecialchars($langName))) ?>
  <?= $formOpen('switches') ?>
  <div class="card-body" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:8px">
    <?php foreach ($switches as $key => [$on, $off, $label]): ?>
      <?= $chk('sw_' . md5($key), $switchOn($key), $label) ?>
    <?php endforeach; ?>
  </div>
  <?= $saveBtn ?></form>
</div>

<div class="card fade-up" id="shop" style="margin-bottom:14px">
  <?= $cardHead('⟦i:shopping-cart⟧ ' . $t['featShopTitle'], sprintf($t['featShopSub'], htmlspecialchars($langName))) ?>
  <?= $formOpen('shop') ?>
  <div class="card-body" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:8px">
    <?php foreach ($shopSwitches as $key => [$on, $off, $label]): ?>
      <?= $chk('shop_' . $key, $shopOn($key), $label) ?>
    <?php endforeach; ?>
  </div>
  <?= $saveBtn ?></form>
</div>

<?php $dp = dpay_cfg($lang); ?>
<div class="card fade-up" id="dpay" style="margin-bottom:14px">
  <?= $cardHead('⟦i:credit-card⟧ ' . $t['featDpayTitle'], sprintf($t['featDpaySub'], htmlspecialchars($langName))) ?>
  <?= $formOpen('dpay') ?>
  <div class="card-body">
    <?= $field($t['featDpayOn'], $chk('dpay_on', $dp['on'], $t['refOn']), $t['featDpayHint']) ?>
    <div class="set-row"><div><div class="set-label"><?= htmlspecialchars($t['featDpayTitle']) ?> — <?= htmlspecialchars($t['featDpayPaths']) ?></div></div>
      <div class="set-ctl" style="display:flex;gap:10px;flex-wrap:wrap">
        <?= $chk('dpay_buy', in_array('buy', $dp['paths'], true), $t['featDpayBuy']) ?>
        <?= $chk('dpay_cvol', in_array('cvol', $dp['paths'], true), $t['featDpayCvol']) ?>
        <?= $chk('dpay_renew', in_array('renew', $dp['paths'], true), $t['featDpayRenew']) ?>
      </div></div>
    <?= $field($t['featDpayLimits'], $chk('dpay_limits', $dp['limits'], $t['refOn']), $t['featDpayLimitsHint']) ?>
    <?= $field($t['featDpayGift'], $chk('dpay_gift', $dp['gift'], $t['refOn']), $t['featDpayGiftHint']) ?>
  </div>
  <?= $saveBtn ?></form>
</div>

<?php $xfAr = ar_cfg($lang); $xfWb = wb_cfg($lang); $xfWt = wt_cfg($lang); ?>
<div class="card fade-up" id="extras" style="margin-bottom:14px">
  <?= $cardHead('⟦i:sparkles⟧ ' . $t['featXfTitle'], sprintf($t['featXfSub'], htmlspecialchars($langName))) ?>
  <?= $formOpen('extras') ?>
  <div class="card-body">
    <?= $field($fa['Admin']['Tickets']['rowLabel'], $chk('tk_on', tk_on($lang), $t['refOn']), $fa['Admin']['Tickets']['info']) ?>
    <?= $field($fa['Admin']['AutoRenew']['rowLabel'], $chk('ar_on', $xfAr['on'], $t['refOn']), $fa['Admin']['AutoRenew']['info']) ?>
    <?= $field($t['featXfArHours'], $input('ar_hours', $xfAr['hours'], 'inputmode="numeric" dir="ltr"')) ?>
    <?= $field($fa['Admin']['AutoRenew']['notifyBtn'], $chk('ar_notify', $xfAr['notify'], $t['refOn'])) ?>
    <?= $field($fa['Admin']['WinBack']['rowLabel'], $chk('wb_on', $xfWb['on'], $t['refOn']), $fa['Admin']['WinBack']['info']) ?>
    <?= $field($t['featXfWbDays'], $input('wb_days', $xfWb['days'], 'inputmode="numeric" dir="ltr"')) ?>
    <?= $field($t['featXfWbPercent'], $input('wb_percent', $xfWb['percent'], 'inputmode="numeric" dir="ltr"')) ?>
    <?= $field($t['featXfWbValid'], $input('wb_valid', $xfWb['valid'], 'inputmode="numeric" dir="ltr"')) ?>
    <?= $field($fa['Admin']['WalletTransfer']['rowLabel'], $chk('wt_on', $xfWt['on'], $t['refOn']), $fa['Admin']['WalletTransfer']['info']) ?>
    <?= $field(sprintf($t['featXfWtMin'], htmlspecialchars($curTitle)), $input('wt_min', rtrim(rtrim(number_format($xfWt['min'], 2, '.', ''), '0'), '.'), 'inputmode="decimal" dir="ltr"')) ?>
    <?= $field(sprintf($t['featXfWtMax'], htmlspecialchars($curTitle)), $input('wt_max', rtrim(rtrim(number_format($xfWt['max'], 2, '.', ''), '0'), '.'), 'inputmode="decimal" dir="ltr"'), $t['featXfWtMaxHint']) ?>
  </div>
  <?= $saveBtn ?></form>
</div>

<div class="card fade-up" id="phone" style="margin-bottom:14px">
  <?= $cardHead('⟦i:phone⟧ ' . $t['featPhoneTitle'], $t['featPhoneSub']) ?>
  <?= $formOpen('phone') ?>
  <div class="card-body">
    <?= $field($t['featPhonePrefix'], $input('phone_prefix', $v['phone'], 'dir="ltr" placeholder="98, 1, 44"'), $t['featPhonePrefixHint']) ?>
    <?= $field($t['featPhoneDefault'], $chk('phone_default', false, $t['featPhoneDefaultBtn'])) ?>
    <?= $field($t['featPhoneAudience'], '<select class="select" name="phone_audience">' . implode('', array_map(fn($a) => '<option value="' . $a . '"' . ($v['aud'] === $a ? ' selected' : '') . '>' . htmlspecialchars(strip_tags($fs['phoneAud_' . $a])) . '</option>', ['all', 'new', 'invited'])) . '</select>') ?>
  </div>
  <?= $saveBtn ?></form>
</div>

<div class="card fade-up" id="apps" style="margin-bottom:14px">
  <?= $cardHead('⟦i:link⟧ ' . $t['featAppsTitle'], $t['featAppsSub']) ?>
  <div class="card-body">
    <?php if (!$apps): ?><div class="cf"><?= $t['featAppsNone'] ?></div><?php endif; ?>
    <?php foreach ($apps as $a): $shared = count(app_row_langs($a)) > 1; ?>
      <div class="set-row">
        <div><div class="set-label"><?= ($shared ? '⟦i:earth⟧ ' : '') . htmlspecialchars($a['name']) ?></div><div class="set-hint" dir="ltr"><?= htmlspecialchars($a['link']) ?></div></div>
        <div class="set-ctl" style="display:flex;gap:6px">
          <?= $formOpen('appedit', ' style="display:flex;gap:6px;flex:1"') ?><input type="hidden" name="app_id" value="<?= (int) $a['id'] ?>"><input type="text" class="input" name="app_link" value="<?= htmlspecialchars($a['link']) ?>" dir="ltr"><button class="btn btn-ghost btn-sm" type="submit"><?= icon('check', 13) ?></button></form>
          <?= $formOpen('appdel', ' onsubmit="return confirm(' . htmlspecialchars(json_encode($t['featAppDeleteConfirm'], JSON_UNESCAPED_UNICODE)) . ')"') ?><input type="hidden" name="app_id" value="<?= (int) $a['id'] ?>"><button class="btn btn-no btn-sm btn-icon" type="submit"><?= icon('trash', 13) ?></button></form>
        </div>
      </div>
    <?php endforeach; ?>
    <div class="field-hint" style="margin-top:8px"><?= $t['featAppsSharedHint'] ?></div>
  </div>
  <?= $formOpen('appadd') ?>
  <div class="card-body" style="display:flex;gap:8px;flex-wrap:wrap;border-top:1px solid var(--bd)">
    <input type="text" class="input" name="app_name" placeholder="<?= htmlspecialchars($t['featAppName']) ?>" style="flex:1;min-width:160px" required>
    <input type="text" class="input" name="app_link" placeholder="https://..." dir="ltr" style="flex:2;min-width:200px" required>
    <button type="submit" class="btn btn-primary"><?= icon('plus', 13) ?> <?= $t['featAppAdd'] ?></button>
  </div></form>
</div>

<div class="card fade-up" id="wheel" style="margin-bottom:14px">
  <?= $cardHead('⟦i:dices⟧ ' . $t['featWheelTitle'], $t['featWheelSub']) ?>
  <?= $formOpen('wheel') ?>
  <div class="card-body"><?= $field(sprintf($t['featWheelPrice'], $curTitle), $input('wheel_price', $v['wheel'], 'inputmode="decimal"')) ?></div>
  <?= $saveBtn ?></form>
</div>

<div class="card fade-up" id="loc" style="margin-bottom:14px">
  <?= $cardHead('⟦i:map-pin⟧ ' . $t['featLocTitle'], $t['featLocSub']) ?>
  <?= $formOpen('loc') ?>
  <div class="card-body">
    <?= $field($t['featLocAll'], $input('loc_limit_all', $v['locAll'], 'inputmode="numeric"')) ?>
    <?= $field($t['featLocFree'], $input('loc_limit_free', $v['locFree'], 'inputmode="numeric"')) ?>
  </div>
  <?= $saveBtn ?></form>
  <?= $formOpen('locreset', ' onsubmit="return confirm(' . htmlspecialchars(json_encode(sprintf($t['featLocResetConfirm'], $langName), JSON_UNESCAPED_UNICODE)) . ')"') ?>
  <div class="card-body" style="border-top:1px solid var(--bd)"><button type="submit" class="btn btn-no btn-sm"><?= icon('trash', 13) ?> <?= $t['featLocReset'] ?></button></div></form>
</div>

<div class="card fade-up" id="lottery" style="margin-bottom:14px">
  <?= $cardHead('⟦i:ticket⟧ ' . $t['featLotteryTitle'], $t['featLotterySub']) ?>
  <?= $formOpen('lottery') ?>
  <div class="card-body">
    <?php foreach ([1, 2, 3] as $r): ?>
      <?= $field(sprintf($t['featLotteryPrize'], $r, $curTitle), $input('lottery_' . $r, $v['prizes'][$r - 1], 'inputmode="decimal"')) ?>
    <?php endforeach; ?>
    <?= $field($t['featLotteryAgents'], $chk('lottery_agents', $v['lotteryAgents'], $t['refOn'])) ?>
  </div>
  <?= $saveBtn ?></form>
</div>

<div class="card fade-up" style="margin-bottom:14px">
  <?= $cardHead('⟦i:users⟧ ' . $t['refPageTitle'], $t['featRefHint']) ?>
  <div class="card-body"><a class="btn btn-ghost" href="referral.php?<?= http_build_query(['lang' => $lang]) ?>"><?= icon('users', 13) ?> <?= $t['refPageTitle'] ?></a></div>
</div>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
