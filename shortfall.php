<?php
// 💰 افزایش موجودی به اندازهٔ کمبود - خرید (کانفیگ و حجم دلخواه) و تمدید.
//
// When a customer is short of balance for a purchase, «💰 افزایش موجودی» takes
// them to the payment methods and the invoice it makes is exactly what they
// lack (the way a renewal always did). Once it is paid the purchase is made
// by itself: the amount is credited, the price comes off, what is left stays.
//
// The gateway's «حداقل / حداکثر افزایش موجودی» still holds. When what is
// lacking is below the minimum (or above the maximum) of the method they
// picked, no invoice of that amount is made: they go the way every top-up goes
// (ready amounts, «مبلغ دلخواه»), with a quote under the caption saying why,
// and the purchase still completes by itself after they pay - whatever the
// amount, as long as it covers the price. Short again, they are told what is
// left.
//
// What carries it: the user's Processing_value_one / _tow (the same pair a
// payment's id_invoice is made of - «getconfigafterpay|username», «getextenduser
// |username%order»), which the payment-completion flow already resumes from,
// and short_ctx, the numbers the quote and the checks need.

if (!function_exists('sf_ctx_get')) {
    function sf_ensure_schema()
    {
        static $done = false;
        if (!$done) {
            $done = true;
            addFieldToTable('user', 'short_ctx', null, 'TEXT NULL');
        }
    }
    // {t: buy|renew, need, price, inv, at}
    function sf_ctx_get($userRow)
    {
        $c = json_decode((string) (is_array($userRow) ? ($userRow['short_ctx'] ?? '') : ''), true);
        return is_array($c) ? $c : null;
    }
    function sf_ctx_save($userId, array $ctx)
    {
        try {
            sf_ensure_schema();
            update("user", "short_ctx", json_encode($ctx), "id", $userId);
        } catch (\Throwable $e) {
            error_log('sf_ctx_save: ' . $e->getMessage());
        }
    }
    function sf_ctx_clear($userId)
    {
        try {
            update("user", "short_ctx", null, "id", $userId);
        } catch (\Throwable $e) {
            // the column is made on the next update: nothing was saved without it
        }
    }
    // how far apart two amounts of this wallet's currency may be and still be
    // the same (a rial-less toman, or cents)
    function sf_tolerance($userRow)
    {
        $cur = function_exists('currency_get') ? currency_get(currency_for_user($userRow)) : null;
        return ((int) ($cur['decimals'] ?? 0)) > 0 ? 0.02 : 1.0;
    }
    function sf_round($amount, $userRow)
    {
        $cur = function_exists('currency_get') ? currency_get(currency_for_user($userRow)) : null;
        $dec = (int) ($cur['decimals'] ?? 0);
        $v = round((float) $amount, $dec);
        return $dec > 0 ? $v : (int) $v;
    }
    // a customer text, in the customer's language and with 🎨's edits
    function sf_text($key, $lang)
    {
        $v = function_exists('bottext_resolve_key') ? bottext_resolve_key('users.Balance.' . $key, $lang) : '';
        if (trim((string) $v) === '') {
            $v = lang_tab_texts($lang)['users']['Balance'][$key] ?? '';
        }
        return (string) $v;
    }

    // Is this customer in the middle of paying for a pending purchase?
    function sf_active($userRow)
    {
        if (!is_array($userRow)) {
            return false;
        }
        if (!in_array((string) ($userRow['Processing_value_tow'] ?? ''), ['getconfigafterpay', 'getextenduser'], true)) {
            return false;
        }
        $step = (string) ($userRow['step'] ?? '');
        if (!($step === 'get_step_payment' || $step === 'topup_pick_method' || strpos($step, 'topup_pkg:') === 0 || strpos($step, 'topup_custom:') === 0)) {
            return false;
        }
        $ctx = sf_ctx_get($userRow);
        // a direct payment (directpay.php) pays the whole price: nothing is lacking
        return $ctx !== null && in_array($ctx['t'] ?? '', ['buy', 'renew'], true);
    }

    // «what is lacking is outside this method's range» - the quote under the
    // amount screens' caption, '' when it is inside it (or nothing is pending)
    function sf_range_note($userRow, $gatewayKey)
    {
        if (!sf_active($userRow)) {
            return '';
        }
        $ctx = sf_ctx_get($userRow);
        $need = (float) ($ctx['need'] ?? 0);
        if ($need <= 0) {
            return '';
        }
        $lang = $userRow['lang'] ?? 'fa';
        [$min, $max] = [topup_gateway_min($lang, $gatewayKey), topup_gateway_max($lang, $gatewayKey)];
        if ($min !== null && $need < (float) $min) {
            $key = 'shortUnderMin';
        } elseif ($max !== null && $need > (float) $max) {
            $key = 'shortOverMax';
        } else {
            return '';
        }
        $cur = currency_for_user($userRow);
        $text = strtr(sf_text($key, $lang), [
            '{need}' => money($need, $cur),
            '{min}' => $min === null ? '—' : money($min, $cur),
            '{max}' => $max === null ? '—' : money($max, $cur),
            '{price}' => money((float) ($ctx['price'] ?? 0), $cur),
            '{balance}' => money((float) ($userRow['Balance'] ?? 0), $cur),
        ]);
        return "\n\n<blockquote>" . $text . "</blockquote>";
    }
    function sf_range_note_for($userId, $gatewayKey)
    {
        $row = select("user", "*", "id", $userId, "select");
        return sf_range_note($row, $gatewayKey);
    }

    // The "ready amounts" screen of a gateway (its packages and «مبلغ دلخواه»),
    // the one the customer gets from 💰 افزایش موجودی ← a method.
    function topup_pkg_screen_show($chatId, $messageId, $lang, $key, $textbotlang)
    {
        $kb = ['inline_keyboard' => []];
        foreach (array_chunk(topup_packages_for($lang, $key, true), topup_columns_for($lang, $key), true) as $row) {
            $kbRow = [];
            foreach ($row as $i => $p) {
                $kbRow[] = topup_package_button($p, $lang, "toppick:{$i}");
            }
            $kb['inline_keyboard'][] = $kbRow;
        }
        $customStyle = topup_btnstyle_for($lang, $key, 'custom', true);
        $backStyle = topup_btnstyle_for($lang, $key, 'back', true);
        $customBackRow = [
            topup_styled_button($textbotlang['users']['Balance']['customAmountBtn'], $customStyle, 'topup_custom_start', 'primary'),
            topup_styled_button($textbotlang['users']['Balance']['backToMethodBtn'], $backStyle, 'topup_back_methods', 'danger'),
        ];
        if (topup_custom_back_swapped($lang, $key)) {
            $customBackRow = array_reverse($customBackRow);
        }
        $kb['inline_keyboard'][] = $customBackRow;
        step("topup_pkg:{$key}", $chatId);
        Editmessagetext($chatId, $messageId, topup_caption_for($lang, $key, $textbotlang['users']['Balance']['pkgPromptTitle']) . topup_disc_caption_block($chatId, $lang, $key, $textbotlang) . sf_range_note_for($chatId, $key), json_encode($kb), 'HTML');
    }

    // ---- the purchase side ----

    // the «موجودی کافی نیست» message of a purchase, with what is lacking and the
    // top-up button that carries it: [text, keyboard json]
    function sf_short_view($userRow, $invoiceId, $price)
    {
        global $textbotlang;
        $lang = $userRow['lang'] ?? 'fa';
        $cur = currency_for_user($userRow);
        $balance = (float) ($userRow['Balance'] ?? 0);
        $need = max(0, (float) $price - $balance);
        // one caption, edited as a whole in 🎨 ← 📝 پیام «موجودی کافی نیست»
        $text = strtr(sf_text('shortMessage', $lang), [
            '{need}' => money($need, $cur),
            '{price}' => money((float) $price, $cur),
            '{balance}' => money($balance, $cur),
        ]);
        // 💳 under it when پرداخت مستقیم is on for this purchase's path
        $inv = select("invoice", "*", "id_invoice", $invoiceId, "select");
        $path = (is_array($inv) && (string) ($inv['cvol'] ?? '') === '1') ? 'cvol' : 'buy';
        $direct = (float) $price > 0 && function_exists('dpay_path_on') && dpay_path_on($lang, $path);
        return [$text, sf_short_kb($lang, $invoiceId, $direct)];
    }
    // its buttons (🎨 alias 'sf'): 💰 افزایش موجودی for what is lacking, and
    // 💳 پرداخت مستقیم for the whole price - order and width from its 📐 چیدمان
    function sf_short_kb($lang, $invoiceId, $withDirect)
    {
        $tx = lang_tab_texts($lang);
        $defs = genbtn_defs('sf', $tx);
        $buttons = [0 => genbtn_render($defs[0], genbtn_override_shown('sf', $lang, 0), "sfg|{$invoiceId}")];
        if ($withDirect) {
            $ov = genbtn_override_shown('sf', $lang, 1);
            if (!genbtn_is_hidden($defs[1], $ov)) {
                $buttons[1] = genbtn_render($defs[1], $ov, "sfd|{$invoiceId}");
            }
        }
        return json_encode(['inline_keyboard' => genbtn_group_rows('sf', $lang, $buttons, $tx)]);
    }
    // The purchase just confirmed with too little balance: the message above in
    // place of the confirm screen. The unpaid invoice it made stays what the
    // payment will complete.
    function sf_purchase_short($userRow, $invoiceId, $price, $messageId)
    {
        global $from_id;
        [$text, $kb] = sf_short_view($userRow, $invoiceId, $price);
        Editmessagetext($from_id, $messageId, $text, $kb, 'HTML');
        step('home', $from_id);
    }

    // The payment-method list a pending purchase is paid from: the same list
    // the renewal uses, its ❌ close row replaced by a way back to the message.
    function sf_payment_keyboard($backCallback, $lang, $textbotlang, $amount = null)
    {
        global $step_payment;
        // a gateway whose own floor is above the amount is left out
        $kb = json_decode((string) ($amount !== null ? step_payment_for_amount($step_payment, $amount, $lang) : $step_payment), true);
        $rows = [];
        foreach ((array) ($kb['inline_keyboard'] ?? []) as $row) {
            $close = false;
            foreach ($row as $b) {
                if (($b['callback_data'] ?? '') === 'colselist') {
                    $close = true;
                }
            }
            if (!$close) {
                $rows[] = $row;
            }
        }
        $rows[] = [['text' => sf_text('shortBackBtn', $lang), 'callback_data' => $backCallback, 'style' => 'danger']];
        return json_encode(['inline_keyboard' => $rows]);
    }

    // ✅ the taps of the message above. true when handled.
    function sf_dispatch()
    {
        global $from_id, $datain, $message_id, $user, $callback_query_id, $textbotlang, $step_payment_none, $noCreditText;
        if (!preg_match('/^sf([gbd])\|([A-Za-z0-9_]+)$/', (string) $datain, $m)) {
            return false;
        }
        $lang = $user['lang'] ?? 'fa';
        $alert = function ($key) use ($callback_query_id, $lang) {
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => strip_tags(sf_text($key, $lang)), 'show_alert' => true]);
        };
        $inv = select("invoice", "*", "id_invoice", $m[2], "select");
        if (!is_array($inv) || (string) $inv['id_user'] !== (string) $from_id || strtolower((string) $inv['Status']) !== 'unpaid') {
            $alert('shortGone');
            return true;
        }
        $price = (float) $inv['price_product'];
        $need = $price - (float) ($user['Balance'] ?? 0);
        if ($m[1] === 'b') {
            // 🔙 from the payment methods: the message again - and an ordinary
            // invoice again, if it was the direct payment's methods
            dpay_unmark_invoice($m[2]);
            update("user", "Processing_value", "0", "id", $from_id);
            update("user", "Processing_value_one", "0", "id", $from_id);
            update("user", "Processing_value_tow", "0", "id", $from_id);
            sf_ctx_clear($from_id);
            sf_purchase_short($user, $m[2], $price, $message_id);
            return true;
        }
        if ($m[1] === 'd') {
            // 💳 the whole price from a gateway, the wallet left alone - for the
            // invoice this message is about (directpay.php); 🔙 comes back here
            $path = (string) ($inv['cvol'] ?? '') === '1' ? 'cvol' : 'buy';
            if (!dpay_path_on($lang, $path)) {
                $alert('directPayGone');
                return true;
            }
            if (!dpay_start_buy($user, $m[2], $price, $message_id, (string) $inv['username'], "sfb|{$m[2]}")) {
                step('home', $from_id);
            }
            return true;
        }
        // 💰 what is lacking: an ordinary invoice, whatever was tried before
        dpay_unmark_invoice($m[2]);
        if ($need <= sf_tolerance($user)) {
            $alert('shortEnough');
            return true;
        }
        if (!empty($step_payment_none)) {
            sendmessage($from_id, $noCreditText, null, 'HTML');
            return true;
        }
        $need = sf_round($need, $user);
        sf_ctx_save($from_id, ['t' => 'buy', 'inv' => $m[2], 'need' => $need, 'price' => $price, 'at' => time()]);
        update("user", "Processing_value", $need, "id", $from_id);
        update("user", "Processing_value_one", (string) $inv['username'], "id", $from_id);
        update("user", "Processing_value_tow", "getconfigafterpay", "id", $from_id);
        step('get_step_payment', $from_id);
        Editmessagetext($from_id, $message_id, topup_disc_method_caption($from_id, $lang, $textbotlang), sf_payment_keyboard("sfb|{$m[2]}", $lang, $textbotlang, $need), 'HTML');
        return true;
    }

    // ---- the gateway's range ----

    // At the top of the checkout (topup_gateway_dispatch.php): what is lacking,
    // taken straight to a method, is held to that method's own range. Outside it
    // the customer goes the usual way instead of the invoice being refused.
    // true when it took over.
    function sf_guard()
    {
        global $from_id, $user, $datain, $message_id, $textbotlang;
        if (!sf_active($user) || (string) ($user['step'] ?? '') !== 'get_step_payment') {
            return false;
        }
        // a package or a typed amount was chosen by the customer: the checkout's
        // own range check answers for it
        if (!empty($GLOBALS['topup_amount_origin_step'])) {
            return false;
        }
        $key = gateway_button_key(['callback_data' => $datain], $textbotlang['textbot']['cartToCart'] ?? null);
        if ($key === null) {
            return false;
        }
        $lang = $user['lang'] ?? 'fa';
        [$min, $max] = topup_checkout_limits($lang, $key);
        if (!topup_amount_out_of_range($user['Processing_value'], $min, $max)) {
            return false;
        }
        $ctx = sf_ctx_get($user);
        if (is_array($ctx)) {
            $ctx['need'] = (float) $user['Processing_value'];
            sf_ctx_save($from_id, $ctx);
        }
        // the note reads the stored amount; the screens below are drawn from the
        // row as it is now, so it is passed on in memory as well
        if (empty(topup_packages_for($lang, $key))) {
            topup_custom_screen_show($from_id, $message_id, $lang, $key, $textbotlang, false);
        } else {
            topup_pkg_screen_show($from_id, $message_id, $lang, $key, $textbotlang);
        }
        return true;
    }

    // ---- after the payment ----

    // The price a pending renewal costs this customer (the shortfall was taken
    // on the discounted one).
    function sf_discounted($price, $userRow)
    {
        $d = intval($userRow['pricediscount'] ?? 0);
        return $d != 0 ? ((float) $price - ((float) $price * $d) / 100) : (float) $price;
    }
    // true when $balance + $paid covers $price
    function sf_covers($userRow, $paid, $price)
    {
        return ((float) ($userRow['Balance'] ?? 0)) + (float) $paid + sf_tolerance($userRow) >= (float) $price;
    }
    // What a payment that does not cover the pending purchase is: a top-up. The
    // money is credited, the purchase waits, and the customer is told what is
    // left - with the button that goes on from there.
    function sf_settle_short($orderId, array $paymentReport, array $userRow, $price, $topupCallback, $extraRows = [])
    {
        $uid = $userRow['id'];
        $lang = $userRow['lang'] ?? 'fa';
        $rcpt = intval($paymentReport['receipt_msg_id'] ?? 0);
        if ($rcpt > 0) {
            deletemessage($uid, $rcpt);
        }
        balance_add($uid, round((float) $paymentReport['price'], 2));
        update("Payment_report", "payment_Status", "paid", "id_order", $paymentReport['id_order']);
        clearSelectCache('user');
        $fresh = select("user", "*", "id", $uid, "select");
        $cur = currency_for_user($userRow);
        $need = max(0, (float) $price - (float) $fresh['Balance']);
        $text = strtr(sf_text('shortCharged', $lang), [
            '{amount}' => money($paymentReport['price'], $cur, false),
            '{balance}' => money($fresh['Balance'], $cur, false),
            '{need}' => money($need, $cur),
        ]);
        $btn = balancebtn_button($lang, lang_tab_texts($lang));
        $btn['callback_data'] = $topupCallback;
        $rows = [[$btn]];
        foreach ($extraRows as $r) {
            $rows[] = $r;
        }
        sendmessage($uid, $text, json_encode(['inline_keyboard' => $rows]), 'HTML');
        topup_paid_notify($orderId);
    }
}
