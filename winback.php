<?php
// 💌 پیام برگشت مشتری - a customer whose service ended and who did not come
// back is asked back, once, with a discount of their own.
//
// Off by default, per language (🌐 وضعیت قابلیت‌ها (هر زبان) ← 💌 پیام برگشت
// مشتری, ⚙️ beside it: after how many days, how much off, for how long).
//
// The end of a service: the notifications cron (NoticationsService.php) asks
// the panel about every service; wb_track() writes down when one is first seen
// ended (invoice.wb_ended_at) and forgets it when it is active again (renewed).
//
// cronbot/winback.php (every 10 minutes, database only - no panel calls):
//   - sends: a service that ended N days ago (and not more than a week before
//     that - turning this on never messages customers who left months ago),
//     whose customer has no other live service, was not asked back in the last
//     30 days and is not blocked. The discount is the bot's own personal
//     discount (user.pricediscount - 👤 مدیریت کاربر ← ٪ تخفیف), which every
//     purchase and renewal already applies and says so: nothing to type. A
//     customer who already has a bigger one keeps theirs.
//   - takes it back: after their first purchase or renewal since, or when the
//     days run out - the personal discount goes back to what it was, unless an
//     admin changed it meanwhile.
// winback_log keeps every one: sent, used, taken back.

if (!function_exists('wb_cfg')) {
    function wb_cfg($lang)
    {
        $days = (int) feature_setting_value('wb_days', $lang, '3');
        $pct = (int) feature_setting_value('wb_percent', $lang, '15');
        $valid = (int) feature_setting_value('wb_valid', $lang, '7');
        return [
            'on' => (string) feature_setting_value('wb_on', $lang, '0') === '1',
            'days' => max(1, min(60, $days > 0 ? $days : 3)),
            'percent' => max(1, min(90, $pct > 0 ? $pct : 15)),
            'valid' => max(1, min(60, $valid > 0 ? $valid : 7)),
        ];
    }
    function wb_ensure_schema()
    {
        global $pdo;
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        if (function_exists('payg_ensure_schema')) {
            payg_ensure_schema();
        }
        ob_start();
        addFieldToTable('invoice', 'wb_ended_at', null, 'INT NULL');
        addFieldToTable('invoice', 'wb_sent', null, 'INT NULL');
        ob_end_clean();
        $pdo->exec("CREATE TABLE IF NOT EXISTS winback_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(100) NOT NULL,
            id_invoice VARCHAR(200) NOT NULL,
            lang VARCHAR(5) NOT NULL DEFAULT 'fa',
            percent INT NOT NULL,
            prev_discount VARCHAR(20) NOT NULL DEFAULT '0',
            sent_at INT NOT NULL,
            until_at INT NOT NULL,
            used_at INT NULL,
            restored TINYINT NOT NULL DEFAULT 0,
            KEY user_id (user_id),
            KEY restored (restored)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    // from the notifications cron: when did this service end?
    function wb_track($inv, $userData)
    {
        global $pdo;
        wb_ensure_schema();
        $status = (string) ($userData['status'] ?? '');
        if (in_array($status, ['limited', 'expired'], true) && empty($inv['wb_ended_at'])) {
            $pdo->prepare("UPDATE invoice SET wb_ended_at = ? WHERE id_invoice = ? AND wb_ended_at IS NULL")->execute([time(), $inv['id_invoice']]);
        } elseif ($status === 'active' && !empty($inv['wb_ended_at'])) {
            // renewed: a new life, a new end later
            $pdo->prepare("UPDATE invoice SET wb_ended_at = NULL, wb_sent = NULL WHERE id_invoice = ?")->execute([$inv['id_invoice']]);
        }
    }

    // ---- the cron ----

    function wb_cron()
    {
        wb_ensure_schema();
        wb_take_back();
        foreach (panel_langs() as $lang) {
            if (wb_cfg($lang)['on']) {
                wb_send_due($lang);
            }
        }
    }
    function wb_live_statuses()
    {
        return "'active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'";
    }
    function wb_send_due($lang)
    {
        global $pdo;
        $c = wb_cfg($lang);
        $now = time();
        $from = $now - ($c['days'] + 7) * 86400;
        $to = $now - $c['days'] * 86400;
        $tx = lang_tab_texts('fa');
        $test = [(string) ($tx['common']['labels']['testService2'] ?? ''), (string) ($tx['Admin']['adminphp']['db_test_service_name'] ?? '')];
        [$lw, $lp] = user_lang_where($lang, 'u');
        $live = wb_live_statuses();
        $sql = "SELECT i.*, u.pricediscount AS u_discount FROM invoice i JOIN user u ON u.id = i.id_user
            WHERE {$lw} AND i.wb_ended_at IS NOT NULL AND i.wb_ended_at BETWEEN ? AND ? AND i.wb_sent IS NULL
              AND COALESCE(i.payg, 0) = 0 AND COALESCE(i.affrw, 0) = 0 AND i.name_product NOT IN (?, ?)
              AND COALESCE(u.User_Status, '') NOT IN ('block', 'blocked')
              AND NOT EXISTS (SELECT 1 FROM invoice o WHERE o.id_user = i.id_user AND o.id_invoice <> i.id_invoice AND o.Status IN ({$live}) AND o.wb_ended_at IS NULL AND o.name_product NOT IN (?, ?))
              AND NOT EXISTS (SELECT 1 FROM winback_log w WHERE w.user_id = i.id_user AND w.sent_at > ?)
            ORDER BY i.wb_ended_at ASC LIMIT 40";
        $st = $pdo->prepare($sql);
        $st->execute(array_merge($lp, [$from, $to, $test[0], $test[1], $test[0], $test[1], $now - 30 * 86400]));
        $seen = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $inv) {
            if (isset($seen[$inv['id_user']])) {
                continue;
            }
            $seen[$inv['id_user']] = true;
            // claimed first: a second run never sends it again
            $claim = $pdo->prepare("UPDATE invoice SET wb_sent = ? WHERE id_invoice = ? AND wb_sent IS NULL");
            $claim->execute([$now, $inv['id_invoice']]);
            if ($claim->rowCount() !== 1) {
                continue;
            }
            wb_send_one($inv, $lang, $c);
        }
    }
    function wb_send_one($inv, $lang, $c)
    {
        global $pdo;
        $now = time();
        $prev = (int) ($inv['u_discount'] ?? 0);
        $applied = max($prev, $c['percent']);
        $pdo->prepare("INSERT INTO winback_log (user_id, id_invoice, lang, percent, prev_discount, sent_at, until_at) VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([(string) $inv['id_user'], (string) $inv['id_invoice'], $lang, $applied, (string) $prev, $now, $now + $c['valid'] * 86400]);
        if ($applied !== $prev) {
            $pdo->prepare("UPDATE user SET pricediscount = ? WHERE id = ?")->execute([(string) $applied, $inv['id_user']]);
            clearSelectCache('user');
        }
        $t = lang_tab_texts($lang)['users']['winback'];
        $kb = null;
        if (!bt_button_hidden($lang, 'users.winback.btnBuy')) {
            $kb = json_encode(['inline_keyboard' => [[bt_button($lang, 'users.winback.btnBuy', $t['btnBuy'], 'buy', 'success')]]]);
        }
        bottext_extras_key_hint('users.winback.message');
        sendmessage($inv['id_user'], strtr($t['message'], [
            '{service}' => htmlspecialchars((string) $inv['username']),
            '{percent}' => $applied,
            '{days}' => $c['valid'],
        ]), $kb, 'HTML');
    }
    // the discount goes back after the first purchase or renewal, or when its days end
    function wb_take_back()
    {
        global $pdo;
        $now = time();
        foreach ($pdo->query("SELECT * FROM winback_log WHERE restored = 0 ORDER BY id LIMIT 200")->fetchAll(PDO::FETCH_ASSOC) as $w) {
            $used = wb_bought_since($w['user_id'], (int) $w['sent_at']);
            if (!$used && $now < (int) $w['until_at']) {
                continue;
            }
            $u = select("user", "*", "id", $w['user_id'], "select", ['cache' => false]);
            // an admin who changed it meanwhile keeps their own value
            if (is_array($u) && (int) $u['pricediscount'] === (int) $w['percent'] && (int) $w['percent'] !== (int) $w['prev_discount']) {
                $pdo->prepare("UPDATE user SET pricediscount = ? WHERE id = ?")->execute([(string) $w['prev_discount'], $w['user_id']]);
                clearSelectCache('user');
            }
            $pdo->prepare("UPDATE winback_log SET restored = 1, used_at = ? WHERE id = ?")->execute([$used ? $now : null, $w['id']]);
            if ($used) {
                wb_report_used($w, $u);
            }
        }
    }
    // a purchase or a paid renewal by this user after $since
    function wb_bought_since($userId, $since)
    {
        global $pdo;
        $st = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE id_user = ? AND time_sell REGEXP '^[0-9]+$' AND CAST(time_sell AS UNSIGNED) > ? AND LOWER(Status) <> 'unpaid'");
        $st->execute([(string) $userId, $since]);
        if ((int) $st->fetchColumn() > 0) {
            return true;
        }
        $st = $pdo->prepare("SELECT COUNT(*) FROM service_other WHERE id_user = ? AND type = 'extend_user' AND status = 'paid' AND time > ?");
        $st->execute([(string) $userId, date('Y/m/d H:i:s', $since)]);
        return (int) $st->fetchColumn() > 0;
    }
    function wb_report_used($w, $u)
    {
        $setting = select("setting", "*", null, null, "select");
        if (!is_array($setting) || (string) ($setting['Channel_Report'] ?? '') === '' || (string) $setting['Channel_Report'] === '0') {
            return;
        }
        $tid = select("topicid", "idreport", "report", "otherservice", "select");
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => is_array($tid) ? $tid['idreport'] : 0,
            'text' => strtr(panel_texts()['Admin']['WinBack']['reportUsed'], [
                '{id}' => $w['user_id'],
                '{username}' => htmlspecialchars((string) ($u['username'] ?? '')),
                '{percent}' => $w['percent'],
                '{days}' => max(0, (int) floor((time() - (int) $w['sent_at']) / 86400)),
            ]),
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => [[['text' => panel_texts()['Admin']['manageUser']['manageUserBtn'], 'callback_data' => 'manageuser_' . $w['user_id']]]]]),
        ]);
    }
    // [sent, came back] for this language, all time and last 30 days
    function wb_stats($lang)
    {
        global $pdo;
        wb_ensure_schema();
        $st = $pdo->prepare("SELECT COUNT(*) AS sent, SUM(used_at IS NOT NULL) AS used, SUM(sent_at > ?) AS sent30, SUM(used_at IS NOT NULL AND sent_at > ?) AS used30 FROM winback_log WHERE lang = ?");
        $t30 = time() - 30 * 86400;
        $st->execute([$t30, $t30, $lang]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        return array_map('intval', ['sent' => $r['sent'] ?? 0, 'used' => $r['used'] ?? 0, 'sent30' => $r['sent30'] ?? 0, 'used30' => $r['used30'] ?? 0]);
    }

    // ---- the admin's side: ⚙️ beside its switch ----

    function wb_hub_payload($lang)
    {
        $t = panel_texts()['Admin']['WinBack'];
        $c = wb_cfg($lang);
        $s = wb_stats($lang);
        $rows = [panel_lang_tabs($lang, 'wbh|hub|%s')];
        $rows[] = [['text' => strtr($t['daysBtn'], ['{n}' => $c['days']]), 'callback_data' => "wbh|ask|{$lang}|days", 'style' => 'primary']];
        $rows[] = [['text' => strtr($t['percentBtn'], ['{n}' => $c['percent']]), 'callback_data' => "wbh|ask|{$lang}|percent", 'style' => 'primary']];
        $rows[] = [['text' => strtr($t['validBtn'], ['{n}' => $c['valid']]), 'callback_data' => "wbh|ask|{$lang}|valid", 'style' => 'primary']];
        $rows[] = [['text' => $t['textsBtn'], 'callback_data' => "bt_group|{$lang}|msmore", 'style' => 'primary']];
        $rows[] = [['text' => $t['back'], 'callback_data' => "fls_lang:{$lang}", 'style' => 'danger']];
        $caption = strtr($t['hubCaption'], [
            '{lang}' => panel_texts()['bottext']['langs'][$lang] ?? $lang,
            '{state}' => $c['on'] ? '✅' : '❌',
            '{days}' => $c['days'],
            '{percent}' => $c['percent'],
            '{valid}' => $c['valid'],
            '{sent}' => $s['sent'],
            '{used}' => $s['used'],
            '{sent30}' => $s['sent30'],
            '{used30}' => $s['used30'],
        ]);
        return [$caption, json_encode(['inline_keyboard' => $rows])];
    }
    function wb_limits()
    {
        return ['days' => [1, 60], 'percent' => [1, 90], 'valid' => [1, 60]];
    }
    function wb_admin_dispatch()
    {
        global $from_id, $datain, $message_id, $adminrulecheck, $user, $text, $callback_query_id;
        if (($adminrulecheck['rule'] ?? '') !== 'administrator') {
            return false;
        }
        $t = panel_texts()['Admin']['WinBack'];
        $limits = wb_limits();
        if (is_array($user) && preg_match('/^wbh_ask:([a-z]{2}):([a-z]+):(\d+):(\d+)$/', (string) $user['step'], $sm) && $datain === '' && isset($limits[$sm[2]])) {
            [$lo, $hi] = $limits[$sm[2]];
            $n = (int) trim((string) $text);
            deletemessage($from_id, $message_id);
            if ((string) $n !== trim((string) $text) || $n < $lo || $n > $hi) {
                Editmessagetext($from_id, (int) $sm[4], strtr($t['invalid'], ['{lo}' => $lo, '{hi}' => $hi]) . "\n\n" . $t['ask_' . $sm[2]], json_encode(['inline_keyboard' => [[['text' => $t['cancel'], 'callback_data' => "wbh|cancel|{$sm[1]}", 'style' => 'danger']]]]), 'HTML');
                return true;
            }
            feature_setting_set('wb_' . $sm[2], $sm[1], (string) $n);
            step('home', $from_id);
            deletemessage($from_id, (int) $sm[4]);
            [$cap, $kb] = wb_hub_payload($sm[1]);
            Editmessagetext($from_id, (int) $sm[3], $cap, $kb, 'HTML');
            return true;
        }
        if (!preg_match('/^wbh\|(hub|ask|cancel)\|([a-z]{2})(?:\|([a-z]+))?$/', (string) $datain, $m) || !in_array($m[2], panel_langs(), true)) {
            return false;
        }
        $lang = $m[2];
        if ($m[1] === 'cancel') {
            step('home', $from_id);
            deletemessage($from_id, $message_id);
            return true;
        }
        if ($m[1] === 'ask' && isset($limits[$m[3] ?? ''])) {
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 1]);
            $pr = sendmessage($from_id, $t['ask_' . $m[3]] . "\n\n" . strtr($t['now'], ['{n}' => wb_cfg($lang)[$m[3]]]), json_encode(['inline_keyboard' => [[['text' => $t['cancel'], 'callback_data' => "wbh|cancel|{$lang}", 'style' => 'danger']]]]), 'HTML');
            step("wbh_ask:{$lang}:{$m[3]}:{$message_id}:" . (int) ($pr['result']['message_id'] ?? 0), $from_id);
            return true;
        }
        [$cap, $kb] = wb_hub_payload($lang);
        Editmessagetext($from_id, $message_id, $cap, $kb, 'HTML');
        return true;
    }
}
