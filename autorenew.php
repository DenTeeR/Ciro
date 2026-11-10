<?php
// 🔁 تمدید خودکار از کیف پول - a customer who forgets to renew no longer loses
// the service: with it on for a service, the bot renews it from the wallet a
// little before it ends, with the same plan, the way «تمدید» does by hand.
//
// Off by default, per language (🌐 وضعیت قابلیت‌ها (هر زبان) ← 🔁 تمدید خودکار از
// کیف پول, ⚙️ beside it: how many hours before the end, and whether a customer
// whose wallet is short is told). When on, the service's own page in
// «🛍 سرویس‌های من» has «🔁 تمدید خودکار: خاموش ❌»; one tap turns it on for that
// service (invoice.auto_renew) and the button says so.
//
// When: the notifications cron (cronbot/NoticationsService.php) already asks
// the panel about every active service; for a service with this on it calls
// ar_cron_check() with what the panel said. Due = it has ended (volume or
// time) or ends within the hours set. The plan is the one it was last renewed
// with, else the one it was bought with - only a plan the customer could still
// buy (same panel, their agent level, their language). A custom plan (🧩), an
// hourly service (⏱), a free config (🎁) or a test account is never renewed
// here.
//
// Money: the price (minus the customer's discount and renewal cashback, as by
// hand) comes off the wallet in one guarded UPDATE before the panel is asked,
// so two runs can never both charge; if the panel refuses, it goes back and the
// error topic is told. A renewal claims the service for 6 hours (ar_last), so a
// plan whose time does not reach past the threshold is not renewed again and
// again. It is recorded as an ordinary paid renewal (service_other
// extend_user) - 📊 آمار ربات and the reports count it as one - with 'auto' in
// its value.

if (!function_exists('ar_cfg')) {
    function ar_cfg($lang)
    {
        $hours = (int) feature_setting_value('ar_hours', $lang, '24');
        return [
            'on' => (string) feature_setting_value('ar_on', $lang, '0') === '1',
            'hours' => max(1, min(72, $hours > 0 ? $hours : 24)),
            'notify' => (string) feature_setting_value('ar_notify', $lang, '1') === '1',
        ];
    }
    function ar_ensure_schema()
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        ob_start();
        addFieldToTable('invoice', 'auto_renew', null, "VARCHAR(2) NOT NULL DEFAULT '0'");
        addFieldToTable('invoice', 'ar_last', null, 'INT NULL');
        addFieldToTable('invoice', 'ar_warned', null, 'INT NULL');
        ob_end_clean();
    }
    function ar_texts($lang)
    {
        return lang_tab_texts($lang)['users']['autorenew'];
    }
    // a wallet with its unit («250,000 تومان»)
    function ar_wallet($userRow)
    {
        return money(is_array($userRow) ? ($userRow['Balance'] ?? 0) : 0, currency_for_user($userRow));
    }
    function ar_lang_of($userRow)
    {
        return is_array($userRow) && in_array($userRow['lang'] ?? '', panel_langs(), true) ? $userRow['lang'] : 'fa';
    }

    // ---- which services, which plan ----

    // null when this service can be renewed by itself, else why not
    function ar_ineligible($inv, $panel)
    {
        if (!is_array($inv) || !is_array($panel)) {
            return 'gone';
        }
        if (($panel['status_extend'] ?? '') === 'off_extend' || ($panel['type'] ?? '') === 'Manualsale') {
            return 'panel';
        }
        if ((int) ($inv['payg'] ?? 0) === 1 || (int) ($inv['affrw'] ?? 0) === 1) {
            return 'kind';
        }
        if (function_exists('cvol_invoice_is_custom') && cvol_invoice_is_custom($inv)) {
            return 'custom';
        }
        $tx = lang_tab_texts('fa');
        if (in_array((string) $inv['name_product'], [(string) ($tx['common']['labels']['testService2'] ?? ''), (string) ($tx['Admin']['adminphp']['db_test_service_name'] ?? '')], true)) {
            return 'test';
        }
        return null;
    }
    // the plan it renews with: the last one it was renewed with, else the one
    // it was bought with - only while the customer could still buy it
    function ar_product($inv, $userRow)
    {
        global $pdo;
        $code = null;
        $st = $pdo->prepare("SELECT value FROM service_other WHERE username = ? AND type = 'extend_user' AND status = 'paid' ORDER BY time DESC LIMIT 1");
        $st->execute([(string) $inv['username']]);
        $v = json_decode((string) $st->fetchColumn(), true);
        if (is_array($v) && !empty($v['code_product'])) {
            $code = (string) $v['code_product'];
        } else {
            $p = select("product", "*", "name_product", $inv['name_product'], "select");
            $code = is_array($p) ? (string) $p['code_product'] : null;
        }
        if ($code === null || $code === '') {
            return null;
        }
        $st = $pdo->prepare("SELECT * FROM product WHERE (Location = :loc OR Location = '/all') AND agent = :agent AND code_product = :code AND (FIND_IN_SET(:lang, lang) OR lang = 'all' OR lang IS NULL OR lang = '') LIMIT 1");
        $st->execute([':loc' => $inv['Service_location'], ':agent' => $userRow['agent'], ':code' => $code, ':lang' => ar_lang_of($userRow)]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($p)) {
            return null;
        }
        $hide = json_decode((string) ($p['hide_panel'] ?? '[]'), true);
        if (is_array($hide) && in_array($inv['Service_location'], $hide, true)) {
            return null;
        }
        return $p;
    }
    // what the customer pays for it: their discount, then the renewal cashback,
    // as a renewal by hand
    function ar_price($product, $userRow)
    {
        $price = (float) $product['price_product'];
        if ((int) ($userRow['pricediscount'] ?? 0) !== 0) {
            $price -= $price * (int) $userRow['pricediscount'] / 100;
        }
        if (($userRow['agent'] ?? 'f') === 'f') {
            $cash = (float) (select("shopSetting", "*", "Namevalue", "chashbackextend", "select")['value'] ?? 0);
        } else {
            $cash = (float) (json_decode((string) (select("shopSetting", "*", "Namevalue", "chashbackextend_agent", "select")['value'] ?? ''), true)[$userRow['agent']] ?? 0);
        }
        if ($cash > 0 && $price > 0) {
            $price -= (float) $product['price_product'] * $cash / 100;
        }
        return max(0, round($price, 2));
    }
    // is it due now, from what the panel said about it?
    function ar_due($userData, $hours)
    {
        $status = (string) ($userData['status'] ?? '');
        if (in_array($status, ['limited', 'expired'], true)) {
            return true;
        }
        if ($status !== 'active') {
            return false;
        }
        $expire = (int) ($userData['expire'] ?? 0);
        return $expire > 0 && $expire - time() <= $hours * 3600;
    }

    // ---- the customer's button ----

    // the «🔁 تمدید خودکار» row of a service's page, or null where it has none
    function ar_service_row($inv, $panel, $userRow)
    {
        $lang = ar_lang_of($userRow);
        if (!ar_cfg($lang)['on'] || ar_ineligible($inv, $panel) !== null) {
            return null;
        }
        ar_ensure_schema();
        $on = (string) ($inv['auto_renew'] ?? '0') === '1';
        $key = $on ? 'users.autorenew.btnOn' : 'users.autorenew.btnOff';
        if (bt_button_hidden($lang, $key)) {
            return null;
        }
        $t = ar_texts($lang);
        return [bt_button($lang, $key, $on ? $t['btnOn'] : $t['btnOff'], 'arv|t|' . $inv['id_invoice'], $on ? 'success' : 'danger')];
    }
    // the customer's tap: true when handled
    function ar_dispatch()
    {
        global $from_id, $datain, $message_id, $user, $callback_query_id, $update;
        if (!preg_match('/^arv\|t\|([A-Za-z0-9_]+)$/', (string) $datain, $m) || !is_array($user)) {
            return false;
        }
        ar_ensure_schema();
        $lang = ar_lang_of($user);
        $t = ar_texts($lang);
        $alert = fn($text) => telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => $text, 'show_alert' => true, 'cache_time' => 1]);
        $inv = select("invoice", "*", "id_invoice", $m[1], "select", ['cache' => false]);
        $panel = is_array($inv) ? select("marzban_panel", "*", "name_panel", $inv['Service_location'], "select") : null;
        if (!is_array($inv) || (string) $inv['id_user'] !== (string) $from_id || !ar_cfg($lang)['on'] || ar_ineligible($inv, $panel) !== null) {
            $alert($t['unavailable']);
            return true;
        }
        $on = (string) ($inv['auto_renew'] ?? '0') !== '1';
        if ($on) {
            $product = ar_product($inv, $user);
            if ($product === null) {
                $alert($t['noPlan']);
                return true;
            }
        }
        global $pdo;
        $pdo->prepare("UPDATE invoice SET auto_renew = ? WHERE id_invoice = ?")->execute([$on ? '1' : '0', $inv['id_invoice']]);
        clearSelectCache('invoice');
        $alert($on ? strtr($t['turnedOn'], ['{hours}' => ar_cfg($lang)['hours'], '{product}' => $product['name_product'], '{price}' => money(ar_price($product, $user), currency_for_user($user))]) : $t['turnedOff']);
        // the button is drawn again where it was: its state, in place
        $inv['auto_renew'] = $on ? '1' : '0';
        $row = ar_service_row($inv, $panel, $user);
        $kb = (array) ($update['callback_query']['message']['reply_markup']['inline_keyboard'] ?? []);
        $changed = false;
        foreach ($kb as $r => $btns) {
            foreach ((array) $btns as $c => $b) {
                if ($row !== null && ($b['callback_data'] ?? '') === 'arv|t|' . $m[1]) {
                    $kb[$r][$c] = $row[0];
                    $changed = true;
                }
            }
        }
        if ($changed) {
            telegram('editMessageReplyMarkup', ['chat_id' => $from_id, 'message_id' => $message_id, 'reply_markup' => json_encode(['inline_keyboard' => $kb])]);
        }
        return true;
    }

    // ---- the renewal ----

    // from the notifications cron, with what the panel said: true when it renewed
    function ar_cron_check($inv, $userRow, $userData, $panel, $ManagePanel)
    {
        global $pdo;
        ar_ensure_schema();
        if ((string) ($inv['auto_renew'] ?? '0') !== '1' || !is_array($userRow)) {
            return false;
        }
        $lang = ar_lang_of($userRow);
        $cfg = ar_cfg($lang);
        if (!$cfg['on'] || ar_ineligible($inv, $panel) !== null || !ar_due($userData, $cfg['hours'])) {
            return false;
        }
        // renewed in the last 6 hours: not again
        if ((int) ($inv['ar_last'] ?? 0) > time() - 6 * 3600) {
            return false;
        }
        if (!in_array((string) $inv['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'], true)) {
            return false;
        }
        $t = ar_texts($lang);
        $product = ar_product($inv, $userRow);
        if ($product === null) {
            ar_tell_once($inv, $userRow, strtr($t['noPlanNotice'], ['{service}' => htmlspecialchars((string) $inv['username'])]), null);
            return false;
        }
        $price = ar_price($product, $userRow);
        $isCredit = ($userRow['agent'] ?? '') === 'n2';
        // the charge, in one guarded statement: enough in the wallet (an agent on
        // credit: within their limit), or nothing changes
        if ($isCredit) {
            $limit = (int) ($userRow['maxbuyagent'] ?? 0);
            $st = $limit > 0
                ? $pdo->prepare("UPDATE user SET Balance = Balance - ? WHERE id = ? AND Balance - ? >= ?")
                : $pdo->prepare("UPDATE user SET Balance = Balance - ? WHERE id = ?");
            $st->execute($limit > 0 ? [$price, $userRow['id'], $price, -$limit] : [$price, $userRow['id']]);
        } else {
            $st = $pdo->prepare("UPDATE user SET Balance = Balance - ? WHERE id = ? AND Balance >= ?");
            $st->execute([$price, $userRow['id'], $price]);
        }
        clearSelectCache('user');
        if ($st->rowCount() !== 1) {
            if ($cfg['notify']) {
                $kb = null;
                if (!bt_button_hidden($lang, 'users.autorenew.btnTopup')) {
                    $kb = json_encode(['inline_keyboard' => [[bt_button($lang, 'users.autorenew.btnTopup', $t['btnTopup'], 'Add_Balance', 'success')]]]);
                }
                ar_tell_once($inv, $userRow, strtr($t['lowBalance'], [
                    '{service}' => htmlspecialchars((string) $inv['username']),
                    '{price}' => money($price, currency_for_user($userRow)),
                    '{balance}' => ar_wallet($userRow),
                ]), $kb, 'users.autorenew.lowBalance');
            }
            return false;
        }
        // claimed before the panel is asked: a crash after this cannot renew twice
        $pdo->prepare("UPDATE invoice SET ar_last = ? WHERE id_invoice = ?")->execute([time(), $inv['id_invoice']]);
        file_put_contents(__DIR__ . '/log.txt', "\nuser_Balance_-{$price}_id_{$userRow['id']}_autorenew_" . date("Y-m-d H:i:s"), FILE_APPEND);
        $extend = $ManagePanel->extend($panel['Methodextend'], $product['Volume_constraint'], $product['Service_time'], $inv['username'], $product['code_product'], $panel['code_panel']);
        if (!is_array($extend) || empty($extend['status'])) {
            // the panel said no: the money goes back
            $pdo->prepare("UPDATE user SET Balance = Balance + ? WHERE id = ?")->execute([$price, $userRow['id']]);
            clearSelectCache('user');
            file_put_contents(__DIR__ . '/log.txt', "\nuser_Balance_+{$price}_id_{$userRow['id']}_autorenew_refund_" . date("Y-m-d H:i:s"), FILE_APPEND);
            bottext_extras_key_hint('users.autorenew.failed');
            sendmessage($userRow['id'], strtr($t['failed'], ['{service}' => htmlspecialchars((string) $inv['username'])]), null, 'HTML');
            ar_report('errorreport', strtr(panel_texts()['Admin']['AutoRenew']['reportFail'], [
                '{id}' => $userRow['id'],
                '{service}' => htmlspecialchars((string) $inv['username']),
                '{panel}' => htmlspecialchars((string) $panel['name_panel']),
                '{error}' => htmlspecialchars(mb_substr(json_encode($extend['msg'] ?? $extend, JSON_UNESCAPED_UNICODE), 0, 600)),
            ]), $userRow['id']);
            return false;
        }
        affrw_mark_renewed($extend, $inv['username'], $panel['name_panel']);
        $value = json_encode([
            'volumebuy' => $product['Volume_constraint'],
            'Service_time' => $product['Service_time'],
            'oldvolume' => $userData['data_limit'] ?? null,
            'oldtime' => $userData['expire'] ?? null,
            'code_product' => $product['code_product'],
            'id_order' => bin2hex(random_bytes(2)),
            'auto' => 1,
        ]);
        $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username, value, type, time, price, output, status) VALUES (?, ?, ?, 'extend_user', ?, ?, ?, 'paid')")
            ->execute([$userRow['id'], $inv['username'], $value, date('Y/m/d H:i:s'), $product['price_product'], json_encode($extend)]);
        $pdo->prepare("UPDATE invoice SET Status = 'active', ar_warned = NULL WHERE id_invoice = ?")->execute([$inv['id_invoice']]);
        clearSelectCache('invoice');
        $after = select("user", "*", "id", $userRow['id'], "select", ['cache' => false]);
        bottext_extras_key_hint('users.autorenew.done');
        sendmessage($userRow['id'], strtr($t['done'], [
            '{service}' => htmlspecialchars((string) $inv['username']),
            '{product}' => htmlspecialchars((string) $product['name_product']),
            '{price}' => money($price, currency_for_user($userRow)),
            '{balance}' => ar_wallet(is_array($after) ? $after : $userRow),
        ]), null, 'HTML');
        ar_report('otherservice', strtr(panel_texts()['Admin']['AutoRenew']['report'], [
            '{id}' => $userRow['id'],
            '{username}' => htmlspecialchars((string) ($userRow['username'] ?? '')),
            '{service}' => htmlspecialchars((string) $inv['username']),
            '{panel}' => htmlspecialchars((string) $panel['name_panel']),
            '{product}' => htmlspecialchars((string) $product['name_product']),
            '{price}' => money($price, currency_for_user($userRow)),
            '{before}' => ar_wallet($userRow),
            '{after}' => ar_wallet(is_array($after) ? $after : $userRow),
            '{time}' => jdate('Y/m/d H:i:s'),
        ]), $userRow['id']);
        return true;
    }
    // a notice the customer gets once a day at most, per service
    function ar_tell_once($inv, $userRow, $text, $kb, $hintKey = null)
    {
        global $pdo;
        if ((int) ($inv['ar_warned'] ?? 0) > time() - 86400) {
            return;
        }
        $pdo->prepare("UPDATE invoice SET ar_warned = ? WHERE id_invoice = ?")->execute([time(), $inv['id_invoice']]);
        clearSelectCache('invoice');
        if ($hintKey !== null) {
            bottext_extras_key_hint($hintKey);
        }
        sendmessage($userRow['id'], $text, $kb, 'HTML');
    }
    // to the report group, in its topic, with the manage-user button
    function ar_report($topic, $text, $userId)
    {
        $setting = select("setting", "*", null, null, "select");
        if (!is_array($setting) || (string) ($setting['Channel_Report'] ?? '') === '' || (string) $setting['Channel_Report'] === '0') {
            return;
        }
        $tid = select("topicid", "idreport", "report", $topic, "select");
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => is_array($tid) ? $tid['idreport'] : 0,
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => [[['text' => panel_texts()['Admin']['manageUser']['manageUserBtn'], 'callback_data' => 'manageuser_' . $userId]]]]),
        ]);
    }

    // ---- the admin's side: ⚙️ beside its switch ----

    function ar_hub_payload($lang)
    {
        $t = panel_texts()['Admin']['AutoRenew'];
        $c = ar_cfg($lang);
        $rows = [panel_lang_tabs($lang, 'arh|hub|%s')];
        $rows[] = [['text' => strtr($t['hoursBtn'], ['{hours}' => $c['hours']]), 'callback_data' => "arh|hours|{$lang}", 'style' => 'primary']];
        $rows[] = [['text' => ($c['notify'] ? '✅ ' : '❌ ') . $t['notifyBtn'], 'callback_data' => "arh|notify|{$lang}", 'style' => $c['notify'] ? 'success' : 'danger']];
        $rows[] = [['text' => $t['textsBtn'], 'callback_data' => "bt_group|{$lang}|msmore", 'style' => 'primary']];
        $rows[] = [['text' => $t['back'], 'callback_data' => "fls_lang:{$lang}", 'style' => 'danger']];
        $caption = strtr($t['hubCaption'], [
            '{lang}' => panel_texts()['bottext']['langs'][$lang] ?? $lang,
            '{state}' => $c['on'] ? '✅' : '❌',
            '{count}' => ar_count_on($lang),
        ]);
        return [$caption, json_encode(['inline_keyboard' => $rows])];
    }
    // services of this language's customers with it on
    function ar_count_on($lang)
    {
        global $pdo;
        ar_ensure_schema();
        [$w, $p] = user_lang_where($lang, 'u');
        $st = $pdo->prepare("SELECT COUNT(*) FROM invoice i JOIN user u ON u.id = i.id_user WHERE i.auto_renew = '1' AND {$w}");
        $st->execute($p);
        return (int) $st->fetchColumn();
    }
    function ar_admin_dispatch()
    {
        global $from_id, $datain, $message_id, $adminrulecheck, $user, $text, $callback_query_id;
        if (($adminrulecheck['rule'] ?? '') !== 'administrator') {
            return false;
        }
        $t = panel_texts()['Admin']['AutoRenew'];
        // the number typed after «⏳»: the prompt says what went wrong, or goes
        if (is_array($user) && preg_match('/^arh_hours:([a-z]{2}):(\d+):(\d+)$/', (string) $user['step'], $sm) && $datain === '') {
            $n = (int) trim((string) $text);
            deletemessage($from_id, $message_id);
            if ((string) $n !== trim((string) $text) || $n < 1 || $n > 72) {
                Editmessagetext($from_id, (int) $sm[3], $t['hoursInvalid'] . "\n\n" . strtr($t['hoursPrompt'], ['{hours}' => ar_cfg($sm[1])['hours']]), json_encode(['inline_keyboard' => [[['text' => $t['cancel'], 'callback_data' => "arh|cancel|{$sm[1]}", 'style' => 'danger']]]]), 'HTML');
                return true;
            }
            feature_setting_set('ar_hours', $sm[1], (string) $n);
            step('home', $from_id);
            deletemessage($from_id, (int) $sm[3]);
            [$cap, $kb] = ar_hub_payload($sm[1]);
            Editmessagetext($from_id, (int) $sm[2], $cap, $kb, 'HTML');
            return true;
        }
        if (!preg_match('/^arh\|(hub|hours|notify|cancel)\|([a-z]{2})$/', (string) $datain, $m) || !in_array($m[2], panel_langs(), true)) {
            return false;
        }
        $lang = $m[2];
        if ($m[1] === 'cancel') {
            step('home', $from_id);
            deletemessage($from_id, $message_id);
            return true;
        }
        if ($m[1] === 'hours') {
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 1]);
            $pr = sendmessage($from_id, strtr($t['hoursPrompt'], ['{hours}' => ar_cfg($lang)['hours']]), json_encode(['inline_keyboard' => [[['text' => $t['cancel'], 'callback_data' => "arh|cancel|{$lang}", 'style' => 'danger']]]]), 'HTML');
            step("arh_hours:{$lang}:{$message_id}:" . (int) ($pr['result']['message_id'] ?? 0), $from_id);
            return true;
        }
        if ($m[1] === 'notify') {
            feature_setting_set('ar_notify', $lang, ar_cfg($lang)['notify'] ? '0' : '1');
        }
        [$cap, $kb] = ar_hub_payload($lang);
        Editmessagetext($from_id, $message_id, $cap, $kb, 'HTML');
        return true;
    }
}
