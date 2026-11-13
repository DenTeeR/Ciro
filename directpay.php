<?php
// 💳 پرداخت مستقیم - a button beside «تایید و پرداخت از کیف پول» on the three
// confirm pages (buying a subscription, a 🧩 custom plan, renewing): the customer
// pays what the service costs from a gateway right now, and once it is paid the
// service is made (or renewed) - the wallet is not touched.
//
// Off by default, per language (🏬 تنظیمات فروشگاه ← 🛒 وضعیت قابلیت‌های فروشگاه).
// When on, the admin picks which of the three paths have it and whether the
// gateway's «حداقل و حداکثر افزایش موجودی» also hold for it (off by default).
//
// How it rides on what is there: the payment is the same «getconfigafterpay» /
// «getextenduser» one a top-up for a short purchase makes (shortfall.php), only
// for the whole price, and marked direct - invoice.pay_direct for a purchase,
// 'direct' in the renewal's service_other row - so the settlement leaves the
// wallet alone.

if (!function_exists('dpay_cfg')) {
    function dpay_paths_all()
    {
        return ['buy', 'cvol', 'renew'];
    }
    // {on, paths: [buy|cvol|renew...], limits}, for one language
    function dpay_cfg($lang)
    {
        $raw = (string) feature_setting_value('dpay_paths', $lang, 'buy,cvol,renew');
        $paths = array_values(array_intersect(dpay_paths_all(), preg_split('/[,\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: []));
        return [
            'on' => (string) feature_setting_value('dpay_on', $lang, '0') === '1',
            'paths' => $paths,
            'limits' => (string) feature_setting_value('dpay_limits', $lang, '0') === '1',
            // 🎁 هدیهٔ شارژ on a direct payment, the way a top-up has it
            'gift' => (string) feature_setting_value('dpay_gift', $lang, '0') === '1',
        ];
    }
    function dpay_path_on($lang, $path)
    {
        $c = dpay_cfg($lang);
        return $c['on'] && in_array($path, $c['paths'], true);
    }
    // flips one thing: 'on', 'limits', or one of the three paths
    function dpay_toggle($lang, $what)
    {
        $c = dpay_cfg($lang);
        if ($what === 'on') {
            feature_setting_set('dpay_on', $lang, $c['on'] ? '0' : '1');
        } elseif ($what === 'limits') {
            feature_setting_set('dpay_limits', $lang, $c['limits'] ? '0' : '1');
        } elseif ($what === 'gift') {
            feature_setting_set('dpay_gift', $lang, $c['gift'] ? '0' : '1');
        } elseif (in_array($what, dpay_paths_all(), true)) {
            $paths = in_array($what, $c['paths'], true) ? array_values(array_diff($c['paths'], [$what])) : array_merge($c['paths'], [$what]);
            // 'none' rather than '': an empty value reads as «never set» and brings the default back
            feature_setting_set('dpay_paths', $lang, $paths ? implode(',', array_intersect(dpay_paths_all(), $paths)) : 'none');
        }
    }
    function dpay_ensure_schema()
    {
        static $done = false;
        if (!$done) {
            $done = true;
            addFieldToTable('invoice', 'pay_direct', '0', 'VARCHAR(2)');
        }
    }
    function dpay_mark_invoice($idInvoice)
    {
        global $pdo;
        dpay_ensure_schema();
        $pdo->prepare("UPDATE invoice SET pay_direct = '1' WHERE id_invoice = ?")->execute([(string) $idInvoice]);
        clearSelectCache('invoice');
    }
    // the invoice goes back to an ordinary one: the customer left the direct
    // payment for «💰 افزایش موجودی» on the «موجودی کافی نیست» message
    function dpay_unmark_invoice($idInvoice)
    {
        global $pdo;
        dpay_ensure_schema();
        $pdo->prepare("UPDATE invoice SET pay_direct = '0' WHERE id_invoice = ? AND pay_direct = '1'")->execute([(string) $idInvoice]);
        clearSelectCache('invoice');
    }
    function dpay_is_direct_invoice($inv)
    {
        return is_array($inv) && (string) ($inv['pay_direct'] ?? '0') === '1';
    }

    // ---- the buttons ----

    function dpay_has_layout($lang, $alias)
    {
        $setting = select("setting", "*", null, null, "select");
        $be = json_decode((string) ($setting['button_edit'] ?? ''), true);
        return is_array($be) && !empty($be[$lang][genbtn_alias_to_key($alias)]['layout']);
    }
    // The confirm page of a purchase with پرداخت مستقیم on: ✅ تایید از کیف پول and
    // 💳 پرداخت مستقیم on one row, 🔙 under them - unless the admin has set a
    // 📐 چیدمان for these buttons, which then has the say.
    function dpay_confirm_kb($lang, $textbotlang, $confirmCb, $backCb, $directCb)
    {
        $defs = genbtn_defs('cf', $textbotlang);
        $cbs = [0 => $backCb, 1 => $confirmCb, 2 => $directCb];
        $buttons = [];
        foreach ($cbs as $i => $cb) {
            $d = $defs[$i];
            if ($i === 1) {
                $d['text'] = $textbotlang['keyboard']['payFromWalletBtn'] ?? $d['text'];
            }
            $buttons[$i] = genbtn_render($d, genbtn_override($lang, 'users.sell.confirmButtons', $i), $cb);
        }
        $rows = dpay_has_layout($lang, 'cf')
            ? genbtn_group_rows('cf', $lang, $buttons, $textbotlang)
            : [[$buttons[1], $buttons[2]], [$buttons[0]]];
        return json_encode(['inline_keyboard' => $rows]);
    }
    // The renewal invoice with پرداخت مستقیم on: ✅ تایید تمدید از کیف پول and
    // 💳 پرداخت مستقیم, then «افزایش موجودی», then 🔙.
    function dpay_renew_kb($lang, $textbotlang, $confirmCb, $backCb, $topupCb, array $balanceBtn)
    {
        $defs = genbtn_defs('rn', $textbotlang);
        $d0 = $defs[0];
        $d0['text'] = $textbotlang['users']['extend']['confirmWallet'] ?? $d0['text'];
        $confirm = genbtn_render($d0, genbtn_override($lang, 'users.extend.invoiceCreated', 0), $confirmCb);
        $back = genbtn_render($defs[1], genbtn_override($lang, 'users.extend.invoiceCreated', 1), $backCb);
        $direct = genbtn_render($defs[3], genbtn_override($lang, 'users.extend.invoiceCreated', 3), str_replace('rn_topup_', 'rn_direct_', $topupCb));
        return json_encode(['inline_keyboard' => [[$confirm, $direct], [$balanceBtn], [$back]]]);
    }

    // ---- the payment ----

    // the payment-method screen of a direct payment: its caption (what is paid,
    // and that the wallet stays as it is) over the methods, and a way back
    function dpay_methods_view($userRow, $price, $backCallback)
    {
        global $textbotlang;
        $lang = $userRow['lang'] ?? 'fa';
        $cur = currency_for_user($userRow);
        $caption = strtr(sf_text('directPayCaption', $lang), [
            '{price}' => money((float) $price, $cur),
            '{balance}' => money((float) ($userRow['Balance'] ?? 0), $cur),
        ]);
        // 🎁 the gifts a top-up announces above its methods (an automatic one, a code the customer
        // already activated), when the admin has them on for direct payments
        if (dpay_cfg($lang)['gift']) {
            $full = topup_disc_method_caption($userRow['id'], $lang, lang_tab_texts($lang));
            $base = lang_tab_texts($lang)['users']['Balance']['selectPaymentGrouped'];
            if ($base !== '' && strpos($full, $base) === 0 && trim(substr($full, strlen($base))) !== '') {
                $caption .= "\n\n" . trim(substr($full, strlen($base)));
            }
        }
        return [$caption, sf_payment_keyboard($backCallback, $lang, $textbotlang, $price)];
    }
    // A purchase confirmed with 💳 پرداخت مستقیم: the unpaid invoice it made is what
    // the payment will complete, for its whole price. true when it went on to the
    // payment methods.
    function dpay_start_buy($userRow, $invoiceId, $price, $messageId, $username, $backCallback = null)
    {
        global $from_id, $step_payment_none, $noCreditText;
        if (!empty($step_payment_none)) {
            sendmessage($from_id, $noCreditText, null, 'HTML');
            return false;
        }
        dpay_mark_invoice($invoiceId);
        $price = sf_round($price, $userRow);
        sf_ctx_save($from_id, ['t' => 'direct', 'inv' => $invoiceId, 'need' => $price, 'price' => $price, 'at' => time()]);
        update("user", "Processing_value", $price, "id", $from_id);
        update("user", "Processing_value_one", (string) $username, "id", $from_id);
        update("user", "Processing_value_tow", "getconfigafterpay", "id", $from_id);
        step('get_step_payment', $from_id);
        // 🔙 to where it began: the plan list, or the «موجودی کافی نیست» message
        [$text, $kb] = dpay_methods_view($userRow, $price, $backCallback ?? "dpb|{$invoiceId}");
        Editmessagetext($from_id, $messageId, $text, $kb, 'HTML');
        return true;
    }
    // ✅ the customer's taps: 🔙 from the payment methods. Returns the callback
    // to carry on with (the plan list), or false when it is not ours.
    function dpay_dispatch()
    {
        global $from_id, $datain;
        if (!preg_match('/^dpb\|([A-Za-z0-9_]+)$/', (string) $datain)) {
            return false;
        }
        update("user", "Processing_value", "0", "id", $from_id);
        update("user", "Processing_value_one", "0", "id", $from_id);
        update("user", "Processing_value_tow", "0", "id", $from_id);
        sf_ctx_clear($from_id);
        step('home', $from_id);
        return 'buybacktow';
    }
    // Is this customer in the middle of a direct payment whose language has the
    // 🎁 هدیهٔ شارژ switch off? Then the gift is neither promised on the invoice
    // nor paid (topup_disc_caption_block / topup_disc_promise_save look here).
    function dpay_gift_blocked($userId)
    {
        $u = select("user", "*", "id", $userId, "select");
        if (!is_array($u) || (string) ($u['step'] ?? '') !== 'get_step_payment') {
            return false;
        }
        $ctx = sf_ctx_get($u);
        if (!is_array($ctx) || ($ctx['t'] ?? '') !== 'direct') {
            return false;
        }
        return !dpay_cfg($u['lang'] ?? 'fa')['gift'];
    }
    // what the checkout holds the amount of a direct payment to: with
    // «رعایت حداقل و حداکثر» off, only the gateway's own floor (the way a ready
    // amount is held); on, the gateway's range as for a typed top-up. null: not
    // a direct payment, the caller's own rule.
    function dpay_checkout_limits($lang, $key)
    {
        global $user;
        if (!is_array($user) || (string) ($user['step'] ?? '') !== 'get_step_payment' || !empty($GLOBALS['topup_amount_origin_step'])) {
            return null;
        }
        $ctx = sf_ctx_get($user);
        if (!is_array($ctx) || ($ctx['t'] ?? '') !== 'direct') {
            return null;
        }
        // on: the range the admin set for this gateway in 🏦 بسته‌های شارژ (حداقل و
        // حداکثر مبلغ دلخواه), falling back to 💎 مالی - the same numbers a typed
        // top-up is held to. It used to fall through to the 💎 مالی value alone,
        // so a range set in 🏦 بسته‌های شارژ never applied to a direct payment.
        if (dpay_cfg($user['lang'] ?? 'fa')['limits']) {
            return topup_effective_limits($lang, $key);
        }
        return [topup_usd_floor_toman($lang, $key), null];
    }

    // ---- the admin's side (🏬 تنظیمات فروشگاه ← 🛒 وضعیت قابلیت‌های فروشگاه) ----

    function dpay_hub_payload($lang)
    {
        global $textbotlang;
        $t = panel_texts()['Admin']['DirectPay'];
        $c = dpay_cfg($lang);
        $b = fn($on, $label, $cb) => ['text' => ($on ? '✅ ' : '❌ ') . $label, 'callback_data' => $cb, 'style' => $on ? 'success' : 'danger'];
        $rows = [panel_lang_tabs($lang, 'dpay|hub|%s')];
        $rows[] = [$b(in_array('buy', $c['paths'], true), $t['pathBuy'], "dpay|tog|{$lang}|buy")];
        $rows[] = [$b(in_array('cvol', $c['paths'], true), $t['pathCvol'], "dpay|tog|{$lang}|cvol")];
        $rows[] = [$b(in_array('renew', $c['paths'], true), $t['pathRenew'], "dpay|tog|{$lang}|renew")];
        $rows[] = [$b($c['limits'], $t['limits'], "dpay|tog|{$lang}|limits")];
        $rows[] = [$b($c['gift'], $t['gift'], "dpay|tog|{$lang}|gift")];
        $rows[] = [['text' => $t['btnConfirm'], 'callback_data' => "gbs|hub|{$lang}|cf", 'style' => 'primary']];
        $rows[] = [['text' => $t['btnRenew'], 'callback_data' => "gbs|hub|{$lang}|rn", 'style' => 'primary']];
        $rows[] = [['text' => $t['btnCaption'], 'callback_data' => "bt_edit|{$lang}|users.Balance.directPayCaption", 'style' => 'primary']];
        $rows[] = [['text' => $t['back'], 'callback_data' => "sfs_lang:{$lang}", 'style' => 'danger']];
        $caption = strtr($t['hubCaption'], ['{lang}' => $textbotlang['bottext']['langs'][$lang] ?? $lang]);
        return [$caption, json_encode(['inline_keyboard' => $rows])];
    }
    // true when handled
    function dpay_admin_dispatch()
    {
        global $from_id, $datain, $message_id, $textbotlang, $adminrulecheck;
        if (($adminrulecheck['rule'] ?? '') !== 'administrator') {
            return false;
        }
        if (!preg_match('/^dpay\|(hub|tog)\|([a-z]{2})(?:\|([a-z]+))?$/', (string) $datain, $m)) {
            return false;
        }
        $lang = $m[2];
        if (!in_array($lang, panel_langs(), true)) {
            return true;
        }
        if ($m[1] === 'tog') {
            $what = $m[3] ?? '';
            dpay_toggle($lang, $what);
            if ($what === 'on') {
                // flipped from the shop's feature list: that list is drawn again
                Editmessagetext($from_id, $message_id, shop_feature_status_caption($textbotlang, $lang), shop_feature_status_payload($textbotlang, $lang));
                return true;
            }
        }
        [$text, $kb] = dpay_hub_payload($lang);
        Editmessagetext($from_id, $message_id, $text, $kb, 'HTML');
        return true;
    }
}
