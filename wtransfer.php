<?php
// 💸 انتقال موجودی بین کاربرها - a customer gives part of their wallet to
// another user of the bot, by that user's numeric id.
//
// Off by default, per language (🌐 وضعیت قابلیت‌ها (هر زبان) ← 💸 انتقال موجودی,
// ⚙️ beside it: the smallest and largest amount of one transfer). When on,
// «👤 حساب کاربری» has «💸 انتقال موجودی». One message carries the whole flow:
// the id is asked, then the amount, then a confirm screen; what was typed is
// removed as it comes.
//
// Money: only between wallets of the same currency (a tomans wallet never
// pays a dollars one); the sender pays from what they have (an agent's credit
// never moves). The confirm is claimed once (the pending transfer in
// Processing_value is swapped out in the same statement that reads it), and
// both wallets change in one database transaction, the sender's only if it
// still holds the amount. Both sides are told, each in their own language;
// the report group's payments topic gets every one.

if (!function_exists('wt_cfg')) {
    function wt_cfg($lang)
    {
        $cur = currency_get(currency_for_lang($lang));
        $dec = (int) ($cur['decimals'] ?? 0);
        $defMin = $dec > 0 ? '1' : '10000';
        $min = (float) feature_setting_value('wt_min', $lang, $defMin);
        $max = (float) feature_setting_value('wt_max', $lang, '0');
        return [
            'on' => (string) feature_setting_value('wt_on', $lang, '0') === '1',
            'min' => $min > 0 ? $min : (float) $defMin,
            'max' => max(0, $max),
        ];
    }
    function wt_lang_of($u)
    {
        return is_array($u) && in_array($u['lang'] ?? '', panel_langs(), true) ? $u['lang'] : 'fa';
    }
    function wt_texts($lang)
    {
        return lang_tab_texts($lang)['users']['wtransfer'];
    }
    function wt_money($amount, $u)
    {
        return money($amount, currency_for_user($u));
    }
    function wt_ensure_schema()
    {
        // nothing of its own: the pending transfer rides in user.Processing_value
    }
    // «Ali (123456)»
    function wt_who($u)
    {
        $name = trim((string) ($u['first_name'] ?? ''));
        return ($name !== '' ? htmlspecialchars(mb_substr($name, 0, 32)) . ' ' : '') . '(<code>' . (int) $u['id'] . '</code>)';
    }

    // the «💸 انتقال موجودی» row of 👤 حساب کاربری, or null
    function wt_account_row($u)
    {
        $lang = wt_lang_of($u);
        if (!wt_cfg($lang)['on'] || bt_button_hidden($lang, 'users.wtransfer.entryBtn')) {
            return null;
        }
        return [bt_button($lang, 'users.wtransfer.entryBtn', wt_texts($lang)['entryBtn'], 'wt|s', 'primary')];
    }
    function wt_cancel_kb($lang)
    {
        return json_encode(['inline_keyboard' => [[bt_button($lang, 'users.wtransfer.cancelBtn', wt_texts($lang)['cancelBtn'], 'wt|x', 'danger')]]]);
    }

    // the customer's taps and typing: true when handled
    function wt_dispatch()
    {
        global $from_id, $datain, $message_id, $user, $text, $callback_query_id, $pdo;
        if (!is_array($user)) {
            return false;
        }
        $step = (string) ($user['step'] ?? '');
        $isTap = preg_match('/^wt\|(s|x|c)$/', (string) $datain, $m);
        $isTyping = $datain === '' && preg_match('/^wt_(id|amt):(\d+)(?::(\d+))?$/', $step, $sm);
        if (!$isTap && !$isTyping) {
            return false;
        }
        $lang = wt_lang_of($user);
        $t = wt_texts($lang);
        $cfg = wt_cfg($lang);
        $alert = fn($s) => telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => $s, 'show_alert' => true, 'cache_time' => 1]);
        if (!$cfg['on']) {
            step('home', $from_id);
            if ($isTap) {
                $alert(lang_tab_texts($lang)['users']['featureUnavailable'] ?? $t['cancelled']);
            }
            return true;
        }
        if ($isTap && $m[1] === 'x') {
            step('home', $from_id);
            update("user", "Processing_value", "0", "id", $from_id);
            Editmessagetext($from_id, $message_id, $t['cancelled'], json_encode(['inline_keyboard' => []]), 'HTML');
            return true;
        }
        if ($isTap && $m[1] === 's') {
            step("wt_id:{$message_id}", $from_id);
            bottext_extras_key_hint('users.wtransfer.askId');
            Editmessagetext($from_id, $message_id, strtr($t['askId'], ['{balance}' => wt_money($user['Balance'], $user)]), wt_cancel_kb($lang), 'HTML');
            return true;
        }
        if ($isTyping) {
            $promptId = (int) $sm[2];
            // a menu button or a command instead of an id or an amount: the
            // customer left the flow - the tap goes where it was meant to
            if (!preg_match('/^[\d\s,.٬٫۰-۹٠-٩]+$/u', trim((string) $text))) {
                step('home', $from_id);
                return false;
            }
            deletemessage($from_id, $message_id);
            if ($sm[1] === 'id') {
                $rid = trim((string) $text);
                $to = ctype_digit($rid) ? select("user", "*", "id", $rid, "select", ['cache' => false]) : false;
                $err = null;
                if ((string) $rid === (string) $from_id) {
                    $err = $t['errSelf'];
                } elseif (!is_array($to) || ($to['User_Status'] ?? '') === 'block') {
                    $err = $t['errNotFound'];
                } elseif (currency_for_user($to) !== currency_for_user($user)) {
                    $err = $t['errCurrency'];
                }
                if ($err !== null) {
                    Editmessagetext($from_id, $promptId, $err . "\n\n" . strtr($t['askId'], ['{balance}' => wt_money($user['Balance'], $user)]), wt_cancel_kb($lang), 'HTML');
                    return true;
                }
                step("wt_amt:{$promptId}:{$to['id']}", $from_id);
                Editmessagetext($from_id, $promptId, wt_amount_prompt($user, $to, $cfg, $t), wt_cancel_kb($lang), 'HTML');
                return true;
            }
            // the amount
            $to = select("user", "*", "id", $sm[3] ?? '', "select", ['cache' => false]);
            if (!is_array($to)) {
                step('home', $from_id);
                Editmessagetext($from_id, $promptId, $t['errNotFound'], json_encode(['inline_keyboard' => []]), 'HTML');
                return true;
            }
            $n = money_normalize($text);
            $amount = $n === null ? 0 : (float) $n;
            $have = (float) $user['Balance'];
            $top = $cfg['max'] > 0 ? min($cfg['max'], $have) : $have;
            if ($amount <= 0 || $amount < $cfg['min'] || $amount > $top) {
                Editmessagetext($from_id, $promptId, strtr($t['errAmount'], ['{min}' => wt_money($cfg['min'], $user), '{max}' => wt_money(max(0, $top), $user)]) . "\n\n" . wt_amount_prompt($user, $to, $cfg, $t), wt_cancel_kb($lang), 'HTML');
                return true;
            }
            $amount = round($amount, 2);
            // what the confirm will do, kept on the sender's row (never in the button)
            update("user", "Processing_value", json_encode(['wt' => 1, 'to' => (string) $to['id'], 'amount' => $amount, 'n' => bin2hex(random_bytes(4))]), "id", $from_id);
            step('home', $from_id);
            $kb = json_encode(['inline_keyboard' => [
                [bt_button($lang, 'users.wtransfer.confirmBtn', $t['confirmBtn'], 'wt|c', 'success')],
                [bt_button($lang, 'users.wtransfer.cancelBtn', $t['cancelBtn'], 'wt|x', 'danger')],
            ]]);
            bottext_extras_key_hint('users.wtransfer.confirm');
            Editmessagetext($from_id, $promptId, strtr($t['confirm'], [
                '{amount}' => wt_money($amount, $user),
                '{to}' => wt_who($to),
                '{after}' => wt_money($have - $amount, $user),
            ]), $kb, 'HTML');
            return true;
        }
        // ✅ the confirm: claimed once, both wallets in one transaction
        $pending = json_decode((string) ($user['Processing_value'] ?? ''), true);
        if (!is_array($pending) || empty($pending['wt'])) {
            $alert($t['expired']);
            return true;
        }
        $claim = $pdo->prepare("UPDATE user SET Processing_value = '0' WHERE id = ? AND Processing_value = ?");
        $claim->execute([$from_id, (string) $user['Processing_value']]);
        if ($claim->rowCount() !== 1) {
            $alert($t['expired']);
            return true;
        }
        $to = select("user", "*", "id", $pending['to'], "select", ['cache' => false]);
        $amount = round((float) $pending['amount'], 2);
        if (!is_array($to) || currency_for_user($to) !== currency_for_user($user) || $amount <= 0) {
            $alert($t['errNotFound']);
            return true;
        }
        try {
            $pdo->beginTransaction();
            $dec = $pdo->prepare("UPDATE user SET Balance = Balance - ? WHERE id = ? AND Balance >= ?");
            $dec->execute([$amount, $from_id, $amount]);
            if ($dec->rowCount() !== 1) {
                $pdo->rollBack();
                $alert($t['errLow']);
                return true;
            }
            $pdo->prepare("UPDATE user SET Balance = Balance + ? WHERE id = ?")->execute([$amount, $to['id']]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('wtransfer: ' . $e->getMessage());
            $alert($t['errLow']);
            return true;
        }
        clearSelectCache('user');
        $stamp = date("Y-m-d H:i:s");
        file_put_contents(__DIR__ . '/log.txt', "\nuser_Balance_-{$amount}_id_{$from_id}_wtransfer_to_{$to['id']}_{$stamp}\nuser_Balance_+{$amount}_id_{$to['id']}_wtransfer_from_{$from_id}_{$stamp}", FILE_APPEND);
        $meAfter = select("user", "*", "id", $from_id, "select", ['cache' => false]);
        $toAfter = select("user", "*", "id", $to['id'], "select", ['cache' => false]);
        bottext_extras_key_hint('users.wtransfer.done');
        Editmessagetext($from_id, $message_id, strtr($t['done'], [
            '{amount}' => wt_money($amount, $user),
            '{to}' => wt_who($to),
            '{balance}' => wt_money($meAfter['Balance'] ?? 0, $user),
        ]), json_encode(['inline_keyboard' => []]), 'HTML');
        // the receiver, in their own words
        $tl = wt_lang_of($to);
        bottext_extras_key_hint('users.wtransfer.received');
        sendmessage($to['id'], strtr(wt_texts($tl)['received'], [
            '{amount}' => wt_money($amount, $to),
            '{from}' => wt_who($user),
            '{balance}' => wt_money($toAfter['Balance'] ?? 0, $to),
        ]), null, 'HTML');
        wt_report($user, $to, $amount, $meAfter, $toAfter);
        return true;
    }
    function wt_amount_prompt($user, $to, $cfg, $t)
    {
        $have = (float) $user['Balance'];
        $top = $cfg['max'] > 0 ? min($cfg['max'], $have) : $have;
        bottext_extras_key_hint('users.wtransfer.askAmount');
        return strtr($t['askAmount'], [
            '{to}' => wt_who($to),
            '{min}' => wt_money($cfg['min'], $user),
            '{max}' => wt_money(max(0, $top), $user),
            '{balance}' => wt_money($have, $user),
        ]);
    }
    function wt_report($from, $to, $amount, $fromAfter, $toAfter)
    {
        $setting = select("setting", "*", null, null, "select");
        if (!is_array($setting) || (string) ($setting['Channel_Report'] ?? '') === '' || (string) $setting['Channel_Report'] === '0') {
            return;
        }
        $tid = select("topicid", "idreport", "report", "paymentreport", "select");
        $pt = panel_texts();
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => is_array($tid) ? $tid['idreport'] : 0,
            'text' => strtr($pt['Admin']['WalletTransfer']['report'], [
                '{from}' => (int) $from['id'],
                '{fromu}' => htmlspecialchars((string) ($from['username'] ?? '')),
                '{to}' => (int) $to['id'],
                '{tou}' => htmlspecialchars((string) ($to['username'] ?? '')),
                '{amount}' => wt_money($amount, $from),
                '{fromafter}' => wt_money($fromAfter['Balance'] ?? 0, $from),
                '{toafter}' => wt_money($toAfter['Balance'] ?? 0, $to),
                '{time}' => jdate('Y/m/d H:i:s'),
            ]),
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => [[
                ['text' => $pt['Admin']['WalletTransfer']['fromBtn'], 'callback_data' => 'manageuser_' . $from['id']],
                ['text' => $pt['Admin']['WalletTransfer']['toBtn'], 'callback_data' => 'manageuser_' . $to['id']],
            ]]]),
        ]);
    }

    // ---- the admin's side: ⚙️ beside its switch ----

    function wt_hub_payload($lang)
    {
        $t = panel_texts()['Admin']['WalletTransfer'];
        $c = wt_cfg($lang);
        $cur = currency_for_lang($lang);
        $rows = [panel_lang_tabs($lang, 'wth|hub|%s')];
        $rows[] = [['text' => strtr($t['minBtn'], ['{n}' => money($c['min'], $cur)]), 'callback_data' => "wth|ask|{$lang}|min", 'style' => 'primary']];
        $rows[] = [['text' => strtr($t['maxBtn'], ['{n}' => $c['max'] > 0 ? money($c['max'], $cur) : $t['noLimit']]), 'callback_data' => "wth|ask|{$lang}|max", 'style' => 'primary']];
        $rows[] = [['text' => $t['textsBtn'], 'callback_data' => "bt_group|{$lang}|account", 'style' => 'primary']];
        $rows[] = [['text' => $t['back'], 'callback_data' => "fls_lang:{$lang}", 'style' => 'danger']];
        $caption = strtr($t['hubCaption'], [
            '{lang}' => panel_texts()['bottext']['langs'][$lang] ?? $lang,
            '{state}' => $c['on'] ? '✅' : '❌',
        ]);
        return [$caption, json_encode(['inline_keyboard' => $rows])];
    }
    function wt_admin_dispatch()
    {
        global $from_id, $datain, $message_id, $adminrulecheck, $user, $text, $callback_query_id;
        if (($adminrulecheck['rule'] ?? '') !== 'administrator') {
            return false;
        }
        $t = panel_texts()['Admin']['WalletTransfer'];
        if (is_array($user) && preg_match('/^wth_ask:([a-z]{2}):(min|max):(\d+):(\d+)$/', (string) $user['step'], $sm) && $datain === '') {
            $n = money_normalize($text);
            deletemessage($from_id, $message_id);
            if ($n === null || ($sm[2] === 'min' && (float) $n <= 0)) {
                Editmessagetext($from_id, (int) $sm[4], $t['invalid'] . "\n\n" . $t['ask_' . $sm[2]], json_encode(['inline_keyboard' => [[['text' => $t['cancel'], 'callback_data' => "wth|cancel|{$sm[1]}", 'style' => 'danger']]]]), 'HTML');
                return true;
            }
            feature_setting_set('wt_' . $sm[2], $sm[1], (string) (float) $n);
            step('home', $from_id);
            deletemessage($from_id, (int) $sm[4]);
            [$cap, $kb] = wt_hub_payload($sm[1]);
            Editmessagetext($from_id, (int) $sm[3], $cap, $kb, 'HTML');
            return true;
        }
        if (!preg_match('/^wth\|(hub|ask|cancel)\|([a-z]{2})(?:\|(min|max))?$/', (string) $datain, $m) || !in_array($m[2], panel_langs(), true)) {
            return false;
        }
        $lang = $m[2];
        if ($m[1] === 'cancel') {
            step('home', $from_id);
            deletemessage($from_id, $message_id);
            return true;
        }
        if ($m[1] === 'ask' && !empty($m[3])) {
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 1]);
            $pr = sendmessage($from_id, $t['ask_' . $m[3]], json_encode(['inline_keyboard' => [[['text' => $t['cancel'], 'callback_data' => "wth|cancel|{$lang}", 'style' => 'danger']]]]), 'HTML');
            step("wth_ask:{$lang}:{$m[3]}:{$message_id}:" . (int) ($pr['result']['message_id'] ?? 0), $from_id);
            return true;
        }
        [$cap, $kb] = wt_hub_payload($lang);
        Editmessagetext($from_id, $message_id, $cap, $kb, 'HTML');
        return true;
    }
}
