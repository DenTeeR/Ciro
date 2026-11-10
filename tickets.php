<?php
// 📨 تیکت‌های پشتیبانی - the customer's support messages as conversations.
//
// The bot always had tickets: «☎️ پشتیبانی» ← a department ← a message, saved in
// support_message and sent to the department's supporter with «پاسخ به پیام».
// What was missing is everything after that: the customer could not see what
// they had sent, whether it was answered, or write again in the same ticket
// once the reply's button was gone; a photo (a screenshot of the error, the
// usual way a customer shows a problem) never reached the supporter at all,
// because the step only took a message with text.
//
// What this adds, riding on the same table and the same flow:
//   - a message with a photo, video, file or voice reaches the supporter, with
//     its caption; the supporter can answer with one too;
//   - every message of one conversation carries its first message's Tracking
//     («thread»), so the conversation can be shown in order;
//   - «📨 تیکت‌های من» on the support page (per language, off by default:
//     🌐 وضعیت قابلیت‌ها (هر زبان)) lists the customer's tickets with their state
//     (🟡 waiting, 🟢 answered, ⚫️ closed); a ticket opens to its conversation,
//     with «✍️ ارسال پیام در همین تیکت» and «🔒 بستن تیکت»;
//   - the supporter's message gets «🔒 بستن تیکت» beside «پاسخ به پیام», and the
//     customer is told, in their own language, when support closed one;
//   - the web panel's «تیکت‌ها» page lists, opens, answers and closes them.
//
// Old rows (from before the thread column) are each a ticket of their own.

if (!function_exists('tk_on')) {
    function tk_on($lang)
    {
        return (string) feature_setting_value('tk_on', $lang, '0') === '1';
    }
    function tk_ensure_schema()
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        ob_start();
        addFieldToTable('support_message', 'thread', null, 'VARCHAR(100) NULL');
        addFieldToTable('support_message', 'media', null, 'TEXT NULL');
        addFieldToTable('support_message', 'result_media', null, 'TEXT NULL');
        addFieldToTable('support_message', 'result_time', null, 'VARCHAR(200) NULL');
        ob_end_clean();
    }

    // ---- the message being sent now ----

    // the photo / video / file / voice of this update, or null
    function tk_media_from_update()
    {
        $m = $GLOBALS['update']['message'] ?? null;
        if (!is_array($m)) {
            return null;
        }
        if (!empty($m['photo']) && is_array($m['photo'])) {
            $p = end($m['photo']);
            return ['t' => 'photo', 'id' => (string) ($p['file_id'] ?? '')];
        }
        foreach (['video', 'document', 'voice', 'audio', 'animation'] as $k) {
            if (!empty($m[$k]['file_id'])) {
                return ['t' => $k, 'id' => (string) $m[$k]['file_id']];
            }
        }
        return null;
    }
    // the words of this update: its text, else its caption
    function tk_text_from_update()
    {
        $m = $GLOBALS['update']['message'] ?? [];
        $t = (string) ($m['text'] ?? '');
        return $t !== '' ? $t : (string) ($m['caption'] ?? '');
    }
    // the same words as Telegram HTML, their formatting kept and a «<» in them
    // escaped - what goes inside a message sent with parse_mode HTML
    function tk_html_from_update()
    {
        $m = $GLOBALS['update']['message'] ?? [];
        $hasText = (string) ($m['text'] ?? '') !== '';
        $text = $hasText ? (string) $m['text'] : (string) ($m['caption'] ?? '');
        $ents = $hasText ? ($m['entities'] ?? []) : ($m['caption_entities'] ?? []);
        if ($text === '') {
            return '';
        }
        if (is_array($ents) && $ents && function_exists('entities_to_html')) {
            [$html, $unknown] = entities_to_html($text, $ents);
            if (!$unknown) {
                return $html;
            }
        }
        return htmlspecialchars($text);
    }
    // does this update carry something a ticket can hold?
    function tk_update_has_content()
    {
        return trim(tk_text_from_update()) !== '' || tk_media_from_update() !== null;
    }
    function tk_media_decode($raw)
    {
        $m = json_decode((string) $raw, true);
        return is_array($m) && !empty($m['t']) && !empty($m['id']) ? $m : null;
    }
    // sends a saved photo/video/... with a caption; true when Telegram took it
    function tk_send_media($chatId, array $media, $caption = '', $keyboard = null)
    {
        $method = ['photo' => 'sendPhoto', 'video' => 'sendVideo', 'document' => 'sendDocument', 'voice' => 'sendVoice', 'audio' => 'sendAudio', 'animation' => 'sendAnimation'][$media['t']] ?? null;
        if ($method === null) {
            return false;
        }
        $data = ['chat_id' => $chatId, $media['t'] => $media['id']];
        if ($caption !== '') {
            $data['caption'] = mb_substr($caption, 0, 1000);
            $data['parse_mode'] = 'HTML';
        }
        if ($keyboard !== null) {
            $data['reply_markup'] = $keyboard;
        }
        $r = telegram($method, $data);
        return is_array($r) && !empty($r['ok']);
    }

    // ---- tickets ----

    function tk_thread_of(array $row)
    {
        $t = trim((string) ($row['thread'] ?? ''));
        return $t !== '' ? $t : (string) $row['Tracking'];
    }
    function tk_row($tracking)
    {
        global $pdo;
        tk_ensure_schema();
        $st = $pdo->prepare("SELECT * FROM support_message WHERE Tracking = ? LIMIT 1");
        $st->execute([(string) $tracking]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return is_array($r) ? $r : null;
    }
    // every message of a conversation, oldest first
    function tk_thread_rows($thread)
    {
        global $pdo;
        tk_ensure_schema();
        $st = $pdo->prepare("SELECT * FROM support_message WHERE thread = ? OR (Tracking = ? AND (thread IS NULL OR thread = '')) ORDER BY id ASC");
        $st->execute([(string) $thread, (string) $thread]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    // open (waiting for support) / answered / closed, from its latest message
    function tk_state(array $rows)
    {
        $last = $rows ? end($rows) : null;
        $s = is_array($last) ? (string) $last['status'] : '';
        if ($s === 'close') {
            return 'closed';
        }
        return $s === 'Answered' ? 'answered' : 'open';
    }
    function tk_state_emoji($state)
    {
        return ['open' => '🟡', 'answered' => '🟢', 'closed' => '⚫️'][$state] ?? '🟡';
    }
    // the customer's tickets, newest activity first: [thread, last_id, rows...]
    function tk_user_threads($userId, $offset = 0, $limit = 8)
    {
        global $pdo;
        tk_ensure_schema();
        $st = $pdo->prepare("SELECT COALESCE(NULLIF(thread, ''), Tracking) AS th, MAX(id) AS last_id FROM support_message WHERE iduser = ? GROUP BY th ORDER BY last_id DESC LIMIT " . (int) $limit . " OFFSET " . (int) $offset);
        $st->execute([(string) $userId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    function tk_user_thread_count($userId)
    {
        global $pdo;
        tk_ensure_schema();
        $st = $pdo->prepare("SELECT COUNT(DISTINCT COALESCE(NULLIF(thread, ''), Tracking)) FROM support_message WHERE iduser = ?");
        $st->execute([(string) $userId]);
        return (int) $st->fetchColumn();
    }
    // a new message, of a new ticket ($thread null) or of $thread; its Tracking
    function tk_insert($idsupport, $iduser, $department, $text, $media, $status, $thread = null)
    {
        global $pdo;
        tk_ensure_schema();
        $tracking = bin2hex(random_bytes(4));
        $st = $pdo->prepare("INSERT INTO support_message (Tracking, idsupport, iduser, name_departman, text, result, time, status, thread, media) VALUES (?, ?, ?, ?, ?, '0', ?, ?, ?, ?)");
        $st->execute([$tracking, (string) $idsupport, (string) $iduser, (string) $department, (string) $text, date('Y/m/d H:i:s'), $status, $thread === null ? $tracking : (string) $thread, $media ? json_encode($media) : null]);
        clearSelectCache('support_message');
        return $tracking;
    }
    // the supporter's answer to one message
    function tk_answer($tracking, $text, $media)
    {
        global $pdo;
        tk_ensure_schema();
        $pdo->prepare("UPDATE support_message SET status = 'Answered', result = ?, result_media = ?, result_time = ? WHERE Tracking = ?")
            ->execute([(string) $text, $media ? json_encode($media) : null, date('Y/m/d H:i:s'), (string) $tracking]);
        clearSelectCache('support_message');
    }
    function tk_close($thread)
    {
        global $pdo;
        $rows = tk_thread_rows($thread);
        if (!$rows) {
            return false;
        }
        $last = end($rows);
        $pdo->prepare("UPDATE support_message SET status = 'close' WHERE id = ?")->execute([(int) $last['id']]);
        clearSelectCache('support_message');
        return true;
    }
    // may $who act on this ticket as its supporter: an admin, or the
    // department's own supporter
    function tk_can_answer($who, array $row)
    {
        global $admin_ids;
        $ids = is_array($admin_ids ?? null) ? $admin_ids : (array) select("admin", "id_admin", null, null, "FETCH_COLUMN");
        return in_array((string) $who, array_map('strval', $ids), true) || (string) $who === (string) $row['idsupport'];
    }
    function tk_short_id($thread)
    {
        return '#' . strtoupper(substr((string) $thread, 0, 8));
    }

    // ---- what the supporter gets ----

    // the supporter's buttons under a customer's message
    function tk_admin_kb($tracking)
    {
        $t = panel_texts();
        return json_encode(['inline_keyboard' => [[
            ['text' => $t['users']['support']['answermessage'], 'callback_data' => 'Responsesupport_' . $tracking, 'style' => 'primary'],
            ['text' => $t['Admin']['Tickets']['closeBtn'], 'callback_data' => 'tkadm|c|' . $tracking, 'style' => 'danger'],
        ]]]);
    }
    // a customer's message to its supporter: the attachment first (with a
    // short caption naming the ticket), then the report with the buttons
    function tk_notify_support($idsupport, $report, $media, $tracking, $thread)
    {
        if ($media) {
            $cap = strtr(panel_texts()['Admin']['Tickets']['attachCaption'], ['{id}' => tk_short_id($thread)]);
            if (!tk_send_media($idsupport, $media, $cap)) {
                $report .= "\n\n" . panel_texts()['Admin']['Tickets']['attachFailed'];
            }
        }
        return sendmessage($idsupport, $report . "\n🎫 " . tk_short_id($thread), tk_admin_kb($tracking), 'HTML');
    }

    // ---- the customer's screens ----

    function tk_texts_for($userRow)
    {
        $lang = is_array($userRow) && in_array($userRow['lang'] ?? '', panel_langs(), true) ? $userRow['lang'] : 'fa';
        return [$lang, lang_tab_texts($lang)];
    }
    function tk_state_label($state, $t)
    {
        $s = $t['users']['support'];
        return ['open' => $s['tkStateOpen'], 'answered' => $s['tkStateAnswered'], 'closed' => $s['tkStateClosed']][$state] ?? $s['tkStateOpen'];
    }
    // «📨 تیکت‌های من»: [text, keyboard]
    function tk_list_view($userRow, $page = 0)
    {
        [$lang, $t] = tk_texts_for($userRow);
        $s = $t['users']['support'];
        $per = 8;
        $total = tk_user_thread_count($userRow['id']);
        $pages = max(1, (int) ceil($total / $per));
        $page = max(0, min((int) $page, $pages - 1));
        $rows = [];
        foreach (tk_user_threads($userRow['id'], $page * $per, $per) as $th) {
            $msgs = tk_thread_rows($th['th']);
            if (!$msgs) {
                continue;
            }
            $first = $msgs[0];
            $last = end($msgs);
            $when = format_datetime('Y/m/d', strtotime((string) $last['time']) ?: time(), $lang);
            $label = tk_state_emoji(tk_state($msgs)) . ' ' . tk_short_id($th['th']) . ' · ' . mb_substr((string) $first['name_departman'], 0, 20) . ' · ' . $when;
            $rows[] = [['text' => $label, 'callback_data' => 'tk|v|' . $th['th']]];
        }
        if ($pages > 1) {
            $nav = [];
            if ($page > 0) {
                $nav[] = ['text' => '◀️', 'callback_data' => 'tk|l|' . ($page - 1)];
            }
            $nav[] = ['text' => ($page + 1) . ' / ' . $pages, 'callback_data' => 'none'];
            if ($page < $pages - 1) {
                $nav[] = ['text' => '▶️', 'callback_data' => 'tk|l|' . ($page + 1)];
            }
            $rows[] = $nav;
        }
        $rows[] = [bt_button($lang, 'users.support.tkBackSupportBtn', $s['tkBackSupportBtn'], 'supportbtns', 'danger')];
        $key = $total > 0 ? 'tkListCaption' : 'tkListEmpty';
        bottext_extras_key_hint('users.support.' . $key);
        $text = strtr($s[$key], ['{count}' => $total]);
        return [$text, json_encode(['inline_keyboard' => $rows])];
    }
    // one ticket's conversation: [text, keyboard], or null when it is not theirs
    function tk_thread_view($userRow, $thread)
    {
        [$lang, $t] = tk_texts_for($userRow);
        $s = $t['users']['support'];
        $msgs = tk_thread_rows($thread);
        if (!$msgs || (string) $msgs[0]['iduser'] !== (string) $userRow['id']) {
            return null;
        }
        $state = tk_state($msgs);
        $lines = [];
        $shown = array_slice($msgs, -6);
        if (count($msgs) > count($shown)) {
            $lines[] = strtr($s['tkOlderHidden'], ['{count}' => count($msgs) - count($shown)]);
        }
        $fmt = function ($raw) use ($lang) {
            $ts = strtotime((string) $raw);
            return $ts ? format_datetime('Y/m/d H:i', $ts, $lang) : '';
        };
        $body = function ($text, $media) use ($s) {
            $text = trim((string) $text);
            $out = $text !== '' && $text !== '0' ? htmlspecialchars(mb_strlen($text) > 350 ? mb_substr($text, 0, 350) . '…' : $text) : '';
            if ($media) {
                $out = $s['tkAttachment'] . ($out !== '' ? "\n" . $out : '');
            }
            return $out !== '' ? $out : '—';
        };
        foreach ($shown as $m) {
            $mMedia = tk_media_decode($m['media'] ?? null);
            if (trim((string) $m['text']) !== '' || $mMedia) {
                $lines[] = strtr($s['tkMsgYou'], ['{time}' => $fmt($m['time'])]) . "\n<blockquote>" . $body($m['text'], $mMedia) . '</blockquote>';
            }
            $res = (string) ($m['result'] ?? '');
            $resMedia = tk_media_decode($m['result_media'] ?? null);
            if (($res !== '' && $res !== '0') || $resMedia) {
                $lines[] = strtr($s['tkMsgSupport'], ['{time}' => $fmt($m['result_time'] ?? '')]) . "\n<blockquote>" . $body($res, $resMedia) . '</blockquote>';
            }
        }
        bottext_extras_key_hint('users.support.tkViewCaption');
        $text = strtr($s['tkViewCaption'], [
            '{id}' => tk_short_id($thread),
            '{department}' => htmlspecialchars((string) $msgs[0]['name_departman']),
            '{status}' => tk_state_label($state, $t),
            '{messages}' => implode("\n", $lines),
        ]);
        if (mb_strlen($text) > 4000) {
            $text = mb_substr($text, 0, 3990) . '…';
        }
        $rows = [];
        if (!bt_button_hidden($lang, 'users.support.tkWriteBtn')) {
            $rows[] = [bt_button($lang, 'users.support.tkWriteBtn', $s['tkWriteBtn'], 'tk|w|' . $thread, 'success')];
        }
        if ($state !== 'closed' && !bt_button_hidden($lang, 'users.support.tkCloseBtn')) {
            $rows[] = [bt_button($lang, 'users.support.tkCloseBtn', $s['tkCloseBtn'], 'tk|c|' . $thread, 'danger')];
        }
        $rows[] = [bt_button($lang, 'users.support.tkBackBtn', $s['tkBackBtn'], 'tk|l|0', 'danger')];
        return [$text, json_encode(['inline_keyboard' => $rows])];
    }
    // the «📨 تیکت‌های من» row of the support page, or null when it is off
    function tk_support_row($lang)
    {
        if (!tk_on($lang) || bt_button_hidden($lang, 'users.support.tkMyBtn')) {
            return null;
        }
        $t = lang_tab_texts($lang);
        return [bt_button($lang, 'users.support.tkMyBtn', $t['users']['support']['tkMyBtn'], 'tk|l|0', 'primary')];
    }
    // $supportoption with the «📨 تیکت‌های من» row above its last (back) row
    function tk_support_keyboard($supportoption, $lang)
    {
        $row = tk_support_row($lang);
        $kb = json_decode((string) $supportoption, true);
        if ($row === null || !is_array($kb) || empty($kb['inline_keyboard'])) {
            return $supportoption;
        }
        $back = array_pop($kb['inline_keyboard']);
        $kb['inline_keyboard'][] = $row;
        $kb['inline_keyboard'][] = $back;
        return json_encode($kb);
    }

    // the customer's taps on its screens: true when handled
    function tk_dispatch()
    {
        global $from_id, $datain, $message_id, $user, $callback_query_id;
        if (!preg_match('/^tk\|([lvwc])\|([A-Za-z0-9]+)$/', (string) $datain, $m) || !is_array($user)) {
            return false;
        }
        [$lang, $t] = tk_texts_for($user);
        $s = $t['users']['support'];
        $alert = function ($text) use ($callback_query_id) {
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => $text, 'show_alert' => true, 'cache_time' => 1]);
        };
        if (!tk_on($lang)) {
            $alert($t['users']['featureUnavailable'] ?? $s['tkGone']);
            return true;
        }
        if ($m[1] === 'l') {
            [$text, $kb] = tk_list_view($user, (int) $m[2]);
            Editmessagetext($from_id, $message_id, $text, $kb, 'HTML');
            return true;
        }
        $thread = $m[2];
        $msgs = tk_thread_rows($thread);
        if (!$msgs || (string) $msgs[0]['iduser'] !== (string) $from_id) {
            $alert($s['tkGone']);
            return true;
        }
        if ($m[1] === 'c') {
            if (tk_state($msgs) !== 'closed') {
                tk_close($thread);
                $last = end($msgs);
                $pt = panel_texts()['Admin']['Tickets'];
                sendmessage($last['idsupport'], strtr($pt['closedByUser'], ['{id}' => tk_short_id($thread), '{user}' => '<a href="tg://user?id=' . (int) $from_id . '">' . (int) $from_id . '</a>']), null, 'HTML');
            }
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => $s['tkClosedAlert'], 'cache_time' => 1]);
        } elseif ($m[1] === 'w') {
            // the same step a reply to the supporter's message takes
            $last = end($msgs);
            update("user", "Processing_value", $last['Tracking'], "id", $from_id);
            step("getextuserfors", $from_id);
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 1]);
            bottext_extras_key_hint('users.support.sendMessage');
            sendmessage($from_id, $s['sendMessage'], $GLOBALS['backuser'] ?? null, 'HTML');
            return true;
        }
        $view = tk_thread_view($user, $thread);
        if ($view !== null) {
            Editmessagetext($from_id, $message_id, $view[0], $view[1], 'HTML');
        }
        return true;
    }
    // the supporter's «🔒 بستن تیکت»: true when handled
    function tk_admin_dispatch()
    {
        global $from_id, $datain, $message_id, $callback_query_id;
        if (!preg_match('/^tkadm\|c\|([A-Za-z0-9]+)$/', (string) $datain, $m)) {
            return false;
        }
        $pt = panel_texts()['Admin']['Tickets'];
        $row = tk_row($m[1]);
        if ($row === null || !tk_can_answer($from_id, $row)) {
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => $pt['notYours'], 'show_alert' => true]);
            return true;
        }
        $thread = tk_thread_of($row);
        $rows = tk_thread_rows($thread);
        $already = tk_state($rows) === 'closed';
        if (!$already) {
            tk_close($thread);
            tk_tell_customer_closed($row['iduser'], $thread);
        }
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => $already ? $pt['alreadyClosed'] : $pt['closedDone']]);
        telegram('editMessageReplyMarkup', ['chat_id' => $from_id, 'message_id' => $message_id, 'reply_markup' => json_encode(['inline_keyboard' => [[['text' => $pt['closedMark'], 'callback_data' => 'none']]]])]);
        return true;
    }
    // the supporter's answer to the customer, in the customer's language and
    // with their reply button (and «📨 دیدن تیکت»): $html is already Telegram HTML
    function tk_send_answer(array $row, $html, $media = null)
    {
        $sa_texts = payer_texts($row['iduser']);
        $sa_row = select("user", "*", "id", $row['iduser'], "select");
        $sa_lang = in_array($sa_row['lang'] ?? '', panel_langs(), true) ? $sa_row['lang'] : 'fa';
        $text = sprintf($sa_texts['users']['support']['messageFromAdmin'], $html);
        $kb = ['inline_keyboard' => [[
            genbtn_render(genbtn_defs('ma', $sa_texts)[0], genbtn_override($sa_lang, 'users.support.messageFromAdminAlt', 0), genbtn_tap_cb('ma', 'Responsesusera_' . $row['Tracking'])),
        ]]];
        $kb = json_encode(tk_answer_kb_extra($row['iduser'], tk_thread_of($row), $kb));
        bottext_extras_key_hint('users.support.messageFromAdmin');
        // a photo or video answer goes as itself, the message under it as its caption
        if ($media && mb_strlen($text) <= 1000 && tk_send_media($row['iduser'], $media, $text, $kb)) {
            return true;
        }
        if ($media) {
            tk_send_media($row['iduser'], $media);
        }
        $r = sendmessage($row['iduser'], $text, $kb, 'HTML');
        return is_array($r) && !empty($r['ok']);
    }
    // an answer with no customer message of its own (the web panel answering a
    // ticket whose last message already has one): a row of its own
    function tk_insert_answer_only(array $last, $text)
    {
        global $pdo;
        tk_ensure_schema();
        $tracking = bin2hex(random_bytes(4));
        $now = date('Y/m/d H:i:s');
        $pdo->prepare("INSERT INTO support_message (Tracking, idsupport, iduser, name_departman, text, result, time, status, thread, result_time) VALUES (?, ?, ?, ?, '', ?, ?, 'Answered', ?, ?)")
            ->execute([$tracking, (string) $last['idsupport'], (string) $last['iduser'], (string) $last['name_departman'], (string) $text, $now, tk_thread_of($last), $now]);
        clearSelectCache('support_message');
        return tk_row($tracking);
    }
    // the customer, in their own language: support closed their ticket
    function tk_tell_customer_closed($userId, $thread)
    {
        $u = select("user", "*", "id", $userId, "select");
        [$lang, $t] = tk_texts_for($u);
        $kb = null;
        if (tk_on($lang) && !bt_button_hidden($lang, 'users.support.tkOpenBtn')) {
            $kb = json_encode(['inline_keyboard' => [[bt_button($lang, 'users.support.tkOpenBtn', $t['users']['support']['tkOpenBtn'], 'tk|v|' . $thread, 'primary')]]]);
        }
        bottext_extras_key_hint('users.support.tkClosedBySupport');
        sendmessage($userId, strtr($t['users']['support']['tkClosedBySupport'], ['{id}' => tk_short_id($thread)]), $kb, 'HTML');
    }
    // under the supporter's answer: «↩️ پاسخ» as before, and «📨 دیدن تیکت» when
    // the customer's language has the tickets on
    function tk_answer_kb_extra($userId, $thread, array $kb)
    {
        $u = select("user", "*", "id", $userId, "select");
        [$lang, $t] = tk_texts_for($u);
        if (tk_on($lang) && !bt_button_hidden($lang, 'users.support.tkOpenBtn')) {
            $kb['inline_keyboard'][] = [bt_button($lang, 'users.support.tkOpenBtn', $t['users']['support']['tkOpenBtn'], 'tk|v|' . $thread, 'primary')];
        }
        return $kb;
    }
}
