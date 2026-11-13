<?php
$version = file_get_contents('version');
date_default_timezone_set('Asia/Tehran');
ini_set('default_charset', 'UTF-8');
ini_set('error_log', 'error_log');
ini_set('memory_limit', '-1');
require_once 'config.php';
require_once 'botapi.php';
require_once 'jdf.php';
require_once 'function.php';
require_once 'keyboard.php';
require_once 'vendor/autoload.php';
require_once 'panels.php';
// The same copy keyboard.php built its keyboards from, so that a tapped button
// is matched against the text it was actually printed with. See ui_texts().
$textbotlang = ui_texts();
if ($is_bot)
    return;
// Before the join/leave updates below, not after: a join Telegram reports can
// complete the invite that earns a free config (🎁's 🛡 channel check), and a
// leave can pause one (🚪) - both talk to the panel. Made after them, the panel
// object did not exist yet there: the request died right after the inviter's
// «3 / 3» and the config was never made nor sent to anyone.
$setting = select("setting", "*");
// a database restored by hand from an older bot is brought up to this code
ciro_schema_selfheal($setting);
$ManagePanel = new ManagePanel();
if (isset($update['chat_member'])) {
    $status = $update['chat_member']['new_chat_member']['status'];
    $from_id = $update['chat_member']['new_chat_member']['user']['id'];
    $user = select("user", "*", "id", $from_id, "select");
    // $textbotlang above was resolved before this update had a user (botapi.php
    // takes $from_id from messages and callbacks only), so it was always
    // Persian. Now that the user is known: their own language.
    $textbotlang = ui_texts();
    $lc_lang = is_array($user) ? ($user['lang'] ?? 'fa') : 'fa';
    // a public channel by its @username, as before; a private one has none,
    // so it goes to the join link the admin gave that channel
    $lc_chat = $update['chat_member']['chat'];
    $lc_url = !empty($lc_chat['username']) ? "https://t.me/{$lc_chat['username']}" : '';
    if ($lc_url === '') {
        foreach ((array) select("channels", "*", null, null, "fetchAll") as $lc_row) {
            if (is_array($lc_row) && (string) ($lc_row['link'] ?? '') === (string) ($lc_chat['id'] ?? '') && !empty($lc_row['linkjoin'])) {
                $lc_url = $lc_row['linkjoin'];
                break;
            }
        }
    }
    $keyboard_channel_left = null;
    if ($lc_url !== '') {
        $lc_defs = genbtn_defs('lc', $textbotlang);
        $lc_btn = genbtn_render($lc_defs[0], genbtn_override($lc_lang, 'users.channel.left_channel', 0), 'none');
        unset($lc_btn['callback_data']);
        $lc_btn['url'] = $lc_url;
        $keyboard_channel_left = json_encode(['inline_keyboard' => [[$lc_btn]]]);
    }
    // 🚪 someone behind a free config leaving its channel, or back in it
    if (in_array($status, ['member', 'administrator', 'creator', 'left', 'kicked'], true)) {
        $lc_who = $update['chat_member']['new_chat_member']['user'];
        affrw_member_moved($from_id, $lc_chat, !in_array($status, ['left', 'kicked'], true), !empty($lc_who['username']) ? '@' . $lc_who['username'] : (string) ($lc_who['first_name'] ?? $from_id));
    }
    if (in_array($status, ['member', 'administrator', 'creator'], true) && is_array($user)) {
        // 🛡 a referral waiting on this channel: counted once all is done
        refv_try($from_id, null, true);
        return;
    }
    if (in_array($status, ['left', 'kicked', 'restricted'])) {
        sendmessage($from_id, $textbotlang['users']['channel']['left_channel'], $keyboard_channel_left, 'html');
        return;
    }
}
if (!in_array($Chat_type, ["private", "supergroup"]))
    return;
if (isset($chat_member))
    return;
$first_name = sanitizeUserName($first_name);
$keyboard_check = json_decode($setting['keyboardmain'], true);
if (is_array($keyboard_check) && preg_match('/[\x{600}-\x{6FF}\x{FB50}-\x{FDFF}]/u', $keyboard_check['keyboard'][0][0]['text'])) {
    // Same starting layout table.php installs - six on, the rest hidden. This
    // one repairs a legacy keyboard whose first cell still held a translated
    // label instead of a text_ key; it used to rebuild the menu with every
    // button switched on, language selection included.
    $keyboardmain = '{"keyboard":[[{"text":"text_sell"},{"text":"text_extend","hidden":true}],[{"text":"text_usertest"},{"text":"text_wheel_luck","hidden":true}],[{"text":"text_Purchased_services"},{"text":"accountwallet"}],[{"text":"addbalance"}],[{"text":"text_affiliates","hidden":true},{"text":"text_Tariff_list","hidden":true}],[{"text":"text_support","hidden":true},{"text":"text_help"}],[{"text":"text_change_language","hidden":true}]]}';
    update("setting", "keyboardmain", $keyboardmain, null, null);
}

#-----------telegram_ip_ranges------------#
if (!checktelegramip())
    die("Unauthorized access");
#-----------end telegram_ip_ranges------------#
if (!webhook_secret_ok())
    die("Unauthorized access");
if (intval($from_id) == 0)
    return;
// One update per person at a time. A double tap on «buy» or «renew» reached
// the bot as two updates at once: both read the same balance and both went
// through on it. The second now waits for the first (up to 15 s), then reads
// what the first one left. Named per bot: bots can share one MySQL server.
$pdo->query("SELECT GET_LOCK(" . $pdo->quote('mz' . substr(md5((string) $APIKEY), 0, 10) . '_' . (int) $from_id) . ", 15)");
clearSelectCache('user');
#-------------Variable----------#
$users_ids = select("user", "id", null, null, "FETCH_COLUMN");
$otherreport = select("topicid", "idreport", "report", "otherreport", "select")['idreport'];
$date = time();
if ($from_id != 0) {
    // A new user has no language yet, so this cannot ask theirs. If ANY
    // language requires verification they start unverified; the gate below
    // still only stops them when their own language has it on. Reading the
    // shared value alone let a language that turned it on in 🌐 وضعیت
    // قابلیت‌ها (هر زبان) never verify anyone new.
    $valueverify = 1;
    foreach (panel_langs() as $vs_lang) {
        if (feature_value('verifystart', $vs_lang, $setting['verifystart']) == "onverify") {
            $valueverify = 0;
            break;
        }
    }
    $randomString = bin2hex(random_bytes(6));
    $stmt = $pdo->prepare("INSERT IGNORE INTO user (id , step,limit_usertest,User_Status,number,Balance,pagenumber,username,agent,message_count,last_message_time,affiliates,affiliatescount,cardpayment,number_username,namecustom,register,verify,codeInvitation,pricediscount,maxbuyagent,joinchannel,score,status_cron) VALUES (:from_id, 'none',:limit_usertest_all,'Active','none','0','1',:username,'f','0','0','0','0',:showcard,'100','none',:date,:verifycode,:codeInvitation,'0','0','0','0','1')");
    $stmt->bindParam(':from_id', $from_id);
    $stmt->bindParam(':limit_usertest_all', $setting['limit_usertest_all']);
    $stmt->bindParam(':username', $username);
    // 👥 a new user has no language yet: card-to-card as Persian gives one
    $newCardDefault = card_new_default('fa');
    $stmt->bindParam(':showcard', $newCardDefault);
    $stmt->bindParam(':date', $date);
    $stmt->bindParam(':verifycode', $valueverify);
    $stmt->bindParam(':codeInvitation', $randomString);
    $stmt->execute();
}
// 🎉 a new user, to the report group - sent once they are saved, so its
// language line is theirs and not their inviter's, whose id it may carry too
if (!in_array($from_id, $users_ids) && $setting['statusnewuser'] == "onnewuser") {
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['manageUser']['manageUserBtn'], 'callback_data' => 'manageuser_' . $from_id],
            ],
        ]
    ]);
    [$nu_name, $nu_user] = aff_name_parts($from_id, ['first_name' => $first_name, 'username' => $username]);
    $newuser = sprintf($textbotlang['Admin']['reportgroup']['newUser'], $nu_name !== '' ? $nu_name : '—', $nu_user !== '' ? $nu_user : '—', "<code>$from_id</code>");
    // 👥 came through someone's invite link (/start <their id>): who
    if (preg_match('/^\/start (\d+)$/', (string) $text, $nu_m) && $nu_m[1] !== (string) $from_id && in_array($nu_m[1], $users_ids)
        && feature_value('affiliatesstatus', 'fa', $setting['affiliatesstatus']) != "offaffiliates") {
        $newuser .= strtr($textbotlang['Admin']['AffReward']['newUserInviter'], ['{inviter}' => aff_user_block($nu_m[1], select("user", "*", "id", $nu_m[1], "select"))]);
    }
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $newuser,
            'reply_markup' => $Response,
            'parse_mode' => "HTML"
        ]);
    }
}
$user = select("user", "*", "id", $from_id, "select");
if ($user == false) {
    $user = array();
    $user = array(
        'step' => '',
        'Processing_value' => '',
        'User_Status' => '',
        'agent' => '',
        'username' => '',
        'limit_usertest' => '',
        'message_count' => '',
        'affiliates' => '',
        'last_message_time' => '',
        'cardpayment' => '',
        'roll_Status' => '',
        'number_username' => '',
        'number' => '',
        'register' => '',
        'codeInvitation' => '',
        'pricediscount' => '',
        'joinchannel' => '',
        'score' => "",
        'limitchangeloc' => ''
    );
}
// if the incoming tap is a recognized main-menu button (user OR admin side)
// while the user is genuinely mid-flow somewhere else, silently cancel that
// flow first so the tapped button runs normally instead of being swallowed
// by a stale step-gated branch further down this same dispatch chain
// exception: a handful of admin steps explicitly ask the admin to type
// free-form text for a button/caption label - those already have their own
// inline "❌ انصراف" cancel button, so a typed reply that happens to match
// an existing menu label's text (e.g. re-typing a button's current name)
// must reach that step's own handler instead of being hijacked as navigation
// Two English main-menu labels were renamed to match the Persian buttons. A
// reply keyboard already on a customer's phone keeps sending the old text
// until the bot sends a new one, so the old text still means the same button.
$mm_legacy = [
    '🏦 Wallet + Top-up' => $textbotlang['textbot']['accountWallet'] ?? null,
    '👥 Referral collection' => $textbotlang['textbot']['affiliates'] ?? null,
];
if ((string) $datain === '' && ($mm_legacy[(string) $text] ?? null) !== null) {
    $text = $mm_legacy[(string) $text];
}
$mm_step = (string) ($user['step'] ?? '');
$mm_exempt_step =((string) $datain === '' && preg_match('/^(btbtntext|btbbtntext|gbtntxt|gbtnemo)-/', $mm_step) === 1);
if (!$mm_exempt_step && is_main_menu_trigger($text, $datain, $textbotlang)) {
    preempt_active_session($user, $from_id);
}
// An amount may be typed the way it reads: "100,000" is the same number as
// 100000 on every step that asks for money, for the admin and the customer
// alike. One place rather than sixty, next to the Persian-digit rewrite that
// already happens in botapi.php, and only for the steps money_input_steps()
// names - days, volume and counts reach their handlers exactly as typed.
$text = money_step_text($mm_step, $text);
$admin_ids = select("admin", "id_admin", null, null, "FETCH_COLUMN");
if (!is_array($admin_ids)) {
    $admin_ids = [];
}
// A service's buttons act only on the tapper's own service: its id travels
// in the button, and anyone can send any button. Admins manage everyone's.
if (is_string($datain) && $datain !== '' && !in_array((string) $from_id, array_map('strval', $admin_ids), true)) {
    $own_id = service_button_invoice_id($datain);
    $own_inv = $own_id !== null ? select("invoice", "*", "id_invoice", $own_id, "select") : false;
    if (is_array($own_inv) && (string) $own_inv['id_user'] !== (string) $from_id) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => $textbotlang['users']['serviceNotYours'], 'show_alert' => true]);
        return;
    }
}
// ⏱ an hourly service has only its own buttons: a renewal, an extra or a
// hand-made on/off would fight the minute-by-minute caps (payg_blocked_action)
if (is_string($datain) && $datain !== '' && payg_blocked_action($datain)) {
    $pg_bid = service_button_invoice_id($datain);
    if ($pg_bid !== null && payg_invoice_of($pg_bid) !== null) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => $textbotlang['users']['payg']['notAllowed'], 'show_alert' => true]);
        return;
    }
}
// 🛡 دسترسی ادمین (🎨 شخصی‌سازی): an admin's own test accounts skip the
// per-user limit (default on) and their purchases cost nothing (default off)
$admin_test_free = in_array($from_id, $admin_ids) && (string) ($setting['admin_test_unlimited'] ?? '1') !== '0';
$admin_buy_free = in_array($from_id, $admin_ids) && (string) ($setting['admin_buy_free'] ?? '0') === '1';
// set by the phone-verification step once the number is accepted: the flow
// (verifybuy / verifyusertest / verifybulk / verifytopup) to pick up again in
// this same request instead of the welcome screen
$verify_resume = '';
$helpdata = select("help", "*");
$id_invoice = select("invoice", "id_invoice", null, null, "FETCH_COLUMN");
$usernameinvoice = select("invoice", "username", null, null, "FETCH_COLUMN");
$code_Discount = select("Discount", "code", null, null, "FETCH_COLUMN");
$marzban_list = select("marzban_panel", "name_panel", null, null, "FETCH_COLUMN");
$name_product = select("product", "name_product", null, null, "FETCH_COLUMN");
$channels_id = select("channels", "link", null, null, "FETCH_COLUMN");
$pricepayment = select("Payment_report", "price", null, null, "FETCH_COLUMN");
$listcard = select("card_number", "cardnumber", null, null, "FETCH_COLUMN");
$topic_id = select("topicid", "*", null, null, "fetchAll");
$statusnote = false;
foreach ($topic_id as $topic) {
    if ($topic['report'] == "reportnight")
        $reportnight = $topic['idreport'];
    if ($topic['report'] == 'reporttest')
        $reporttest = $topic['idreport'];
    if ($topic['report'] == 'errorreport')
        $errorreport = $topic['idreport'];
    if ($topic['report'] == 'porsantreport')
        $porsantreport = $topic['idreport'];
    if ($topic['report'] == 'reportcron')
        $reportcron = $topic['idreport'];
    if ($topic['report'] == 'backupfile')
        $reportbackup = $topic['idreport'];
    if ($topic['report'] == 'buyreport')
        $buyreport = $topic['idreport'];
    if ($topic['report'] == 'otherservice')
        $otherservice = $topic['idreport'];
    if ($topic['report'] == 'paymentreport')
        $paymentreports = $topic['idreport'];

}
if (feature_value('statusnamecustom', $user['lang'] ?? 'fa', $setting['statusnamecustom']) == 'onnamecustom')
    $statusnote = true;
if (feature_value('statusnoteforf', $user['lang'] ?? 'fa', $setting['statusnoteforf']) == "0" && $user['agent'] == "f")
    $statusnote = false;
$time_Start = jdate('Y/m/d');
$date_start = jdate('H:i:s', time());
if ($user['username'] == "none" || $user['username'] == null || $user['username'] != $username) {
    update("user", "username", $username, "id", $from_id);
}
// 👥 their Telegram name, for the referral reports (affrw_ensure_table()
// makes the column - update() would otherwise make it with this value as
// everyone's default)
if (array_key_exists('first_name', $user) && (string) $user['first_name'] !== (string) $first_name) {
    update("user", "first_name", (string) $first_name, "id", $from_id);
    $user['first_name'] = (string) $first_name;
}
$lang_array = panel_langs();
if (!in_array($user['lang'], $lang_array)) {
    wallet_switch_lang($from_id, 'fa');
}
if ($user['register'] == "none") {
    update("user", "register", time(), "id", $from_id);
}
if (!in_array($user['agent'], ["n", "n2", "f"]))
    update("user", "agent", "f", "id", $from_id);
#-----------User_Status------------#
if ($user['User_Status'] == "block" && !in_array($from_id, $admin_ids)) {
    $textblock = sprintf($textbotlang['users']['block']['descriptions'], $user['description_blocking']);
    sendmessage($from_id, $textblock, null, 'html');
    return;
}
#---------anti spam--------------#
$timebot = time();
$TimeLastMessage = $timebot - intval($user['last_message_time']);
if (floor($TimeLastMessage / 60) >= 1) {
    update("user", "last_message_time", $timebot, "id", $from_id);
    update("user", "message_count", "1", "id", $from_id);
} else {
    if (!in_array($from_id, $admin_ids)) {
        $addmessage = intval($user['message_count']) + 1;
        update("user", "message_count", $addmessage, "id", $from_id);
        if ($user['message_count'] >= "35") {
            $User_Status = "block";
            $textblok = sprintf($textbotlang['users']['spam']['spamedReport'], $from_id);
            $Response = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['Admin']['manageUser']['manageUserBtn'], 'callback_data' => 'manageuser_' . $from_id],
                    ],
                ]
            ]);
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $otherservice,
                    'text' => $textblok,
                    'parse_mode' => "HTML",
                    'reply_markup' => $Response
                ]);
            }
            update("user", "User_Status", $User_Status, "id", $from_id);
            update("user", "description_blocking", $textbotlang['users']['spam']['spamed'], "id", $from_id);
            sendmessage($from_id, $textbotlang['users']['spam']['spamedMessage'], null, 'html');
            return;
        }
    }
}


if (strpos($text, "/start ") !== false && $user['step'] != "gettextSystemMessage") {
    $affiliatesid = explode(" ", $text)[1];
    if (!in_array($affiliatesid, ['start', "usertest", "/start", "buy", "help"])) {
        isValidInvitationCode($setting, $from_id, $user['verify'], $user['lang'] ?? 'fa');
        if (feature_value('affiliatesstatus', $user['lang'] ?? 'fa', $setting['affiliatesstatus']) == "offaffiliates") {
            sendmessage($from_id, $textbotlang['users']['affiliates']['offaffiliates'], $keyboard, 'HTML');
            return;
        }
        if (is_numeric($affiliatesid) && in_array($affiliatesid, $users_ids)) {
            if ($affiliatesid == $from_id) {
                bottext_extras_key_hint('users.affiliates.invalidaffiliates');
                sendmessage($from_id, $textbotlang['users']['affiliates']['invalidaffiliates'], null, 'html');
                return;
            }
            $user = select("user", "*", "id", $from_id, "select");
            // checked BEFORE writing: writing first moved someone else's
            // referral over to whoever's link was opened last - and one still
            // being verified for someone is theirs too
            $aff_pendingNow = (string) ($user['aff_pending'] ?? '');
            if ($aff_pendingNow === (string) $affiliatesid) {
                // the same link again while being verified: what is left
                // (one plan may count them already, the other not yet)
                refv_try($from_id, $keyboard);
            } elseif (intval($user['affiliates']) != 0 || (string) ($user['aff_rw'] ?? '') !== '' || $aff_pendingNow !== '') {
                bottext_extras_key_hint('users.affiliates.affiliateedago');
                sendmessage($from_id, $textbotlang['users']['affiliates']['affiliateedago'], null, 'html');
                return;
            } else {
                // 🛡 counted now, or for each plan once its own part is done
                // (phone / channels) - see aff_count_for() for who is told
                // what once it counts
                aff_invite_start($user, $affiliatesid, $keyboard);
            }
        } else {
            sendmessage($from_id, strtr($textbotlang['users']['text_start'], bottext_user_placeholders($user, $from_id)), $keyboard, 'html');
            update("user", "Processing_value", "0", "id", $from_id);
            update("user", "Processing_value_one", "0", "id", $from_id);
            update("user", "Processing_value_tow", "0", "id", $from_id);
            update("user", "Processing_value_four", "0", "id", $from_id);
            step('home', $from_id);
        }
    } else {
        $text = $affiliatesid;
    }
}
if (intval($user['verify']) == 0 && !in_array($from_id, $admin_ids) && feature_value('verifystart', $user['lang'] ?? 'fa', $setting['verifystart']) == "onverify") {
    $textverify = sprintf($textbotlang['users']['account']['notVerifiedNotice'], $setting['id_support']);
    sendmessage($from_id, $textverify, null, 'html');
    return;
}
;

#-----------roll------------#
// the accept button's label is admin-editable (🎨 شخصی‌سازی), so both the
// default and the override have to be recognised - otherwise renaming it
// locks every user behind a rules screen whose button no longer matches
$rules_btn_label = bt_reply_label($user['lang'] ?? 'fa', 'keyboard.acceptRules', $textbotlang['keyboard']['acceptRules']);
if (feature_value('roll_Status', $user['lang'] ?? 'fa', $setting['roll_Status']) == "rolleon" && $user['roll_Status'] == 0 && ($text != $textbotlang['keyboard']['acceptRulesButton'] and $text != $rules_btn_label and $datain != "acceptrule") && !in_array($from_id, $admin_ids)) {
    sendmessage($from_id, $textbotlang['textbot']['rules'], $confrimrolls, 'html');
    return;
}
if ($text == $textbotlang['keyboard']['acceptRules'] or $text == $rules_btn_label or $datain == "acceptrule") {
    deletemessage($from_id, $message_id);
    sendmessage($from_id, $textbotlang['users']['Rules'], $keyboard, 'html');
    $confrim = true;
    update("user", "roll_Status", $confrim, "id", $from_id);
}

#-----------Bot_Status------------#
// on or off per language (🌐 وضعیت قابلیت‌ها (هر زبان)). Picking another
// language still goes through, so a customer can move to one that is on.
if (feature_value('Bot_Status', $user['lang'] ?? 'fa', $setting['Bot_Status']) == "botstatusoff" && !in_array($from_id, $admin_ids)
    && $datain !== 'change_language' && strpos((string) $datain, 'setlang:') !== 0) {
    // its 🎨 text and sticker, this language's
    bottext_extras_key_hint('textbot.botOff');
    sendmessage($from_id, $textbotlang['textbot']['botOff'], null, 'html');
    return;
}
#-----------/start------------#
if ($user['joinchannel'] != "active") {
    // 📯 per channel and language: everyone, only newcomers, only invited
    // customers, or nobody (a channel kept for referral verification) - a
    // customer who came without a link never sees an «invited only» one
    $gate_links = channel_gate_links_for($user);
    if (count($gate_links) != 0) {
        $channels = channel($gate_links);
        if ($datain == "confirmchannel") {
            if (count($channels) == 0) {
                deletemessage($from_id, $message_id);
                sendmessage($from_id, strtr($textbotlang['users']['text_start'], bottext_user_placeholders($user, $from_id)), $keyboard, 'html');
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => $textbotlang['users']['channel']['confirmed'],
                    'show_alert' => false,
                    'cache_time' => 5,
                ]);
                return;
            }
            $keyboardchannel = [
                'inline_keyboard' => [],
            ];
            foreach (channels_effective_order($user['lang'] ?? 'fa') as $channelremark) {
                if ($channelremark['remark'] == null)
                    continue;
                if ($channelremark['linkjoin'] == null)
                    continue;
                // hidden here means gone from the real message too, not just
                // the admin preview - channel_button_text() only marks it
                // with 🚫 for admin screens (forAdminPreview=true), so a
                // plain call here never leaks that marker to real users
                if (!empty($channelremark['hidden']))
                    continue;
                if (!in_array($channelremark['link'], $gate_links))
                    continue;
                list($cbc_text, $cbc_iconId) = channel_button_text($channelremark);
                $cbc_btn = [
                    'text' => $cbc_text,
                    'url' => $channelremark['linkjoin']
                ];
                if ($cbc_iconId !== '') {
                    $cbc_btn['icon_custom_emoji_id'] = $cbc_iconId;
                }
                $cbc_style = channel_button_style($channelremark);
                if ($cbc_style !== '') {
                    $cbc_btn['style'] = $cbc_style;
                }
                $keyboardchannel['inline_keyboard'][] = [$cbc_btn];
            }
            $keyboardchannel['inline_keyboard'][] = [['text' => $textbotlang['users']['channel']['confirmjoin'], 'callback_data' => "confirmchannel"]];
            $keyboardchannel = json_encode($keyboardchannel);
            Editmessagetext($from_id, $message_id, $textbotlang['textbot']['channel'], $keyboardchannel);
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => $textbotlang['users']['channel']['notconfirmed'],
                'show_alert' => true,
                'cache_time' => 5,
            ]);
            $partsaffiliates = explode("_", $user['Processing_value_four']);
            if ($partsaffiliates[0] == "affiliates") {
                $affiliatesid = $partsaffiliates[1];
                if (!in_array($affiliatesid, $users_ids)) {
                    sendmessage($from_id, $textbotlang['users']['affiliates']['affiliatesidyou'], null, 'html');
                    return;
                }
                if ($affiliatesid == $from_id) {
                    sendmessage($from_id, $textbotlang['users']['affiliates']['invalidaffiliates'], null, 'html');
                    return;
                }
                $marzbanDiscountaffiliates = select("affiliates", "*", null, null, "select");
                $useraffiliates = select("user", "*", 'id', $affiliatesid, "select");
                // the gift lands in the REFERRER's balance, so it is their
                // language's amount that applies - the same one they were shown
                // on their own 👥 زیرمجموعه‌گیری screen
                $aff_reflang = $useraffiliates['lang'] ?? 'fa';
                if (aff_classic_on($aff_reflang) && feature_setting_value('aff_startgift', $aff_reflang, $marzbanDiscountaffiliates['Discount']) == "onDiscountaffiliates") {
                    $aff_giftamount = feature_setting_value('aff_giftamount', $aff_reflang, $marzbanDiscountaffiliates['price_Discount']);
                    wallet_credit($affiliatesid, $aff_giftamount, currency_for_lang($aff_reflang));
                    $addbalancediscount = money($aff_giftamount, currency_for_user($useraffiliates));
                    sendmessage($affiliatesid, strtr(payer_texts($affiliatesid)['users']['affiliates']['balanceGift'], ['{addbalancediscount}' => $addbalancediscount, '{from_id}' => $from_id]), null, 'html');
                }
                sendmessage($from_id, strtr($textbotlang['users']['text_start'], bottext_user_placeholders($user, $from_id)), $keyboard, 'html');
                $addcountaffiliates = intval($useraffiliates['affiliatescount']) + 1;
                update("user", "affiliates", $affiliatesid, "id", $from_id);
                update("user", "Processing_value_four", "none", "id", $from_id);
                update("user", "affiliatescount", $addcountaffiliates, "id", $affiliatesid);
                affrw_check($affiliatesid);
            }
            return;
        }
        if (count($channels) != 0 && !in_array($from_id, $admin_ids)) {
            $keyboardchannel = [
                'inline_keyboard' => [],
            ];
            foreach (channels_effective_order($user['lang'] ?? 'fa') as $channelremark) {
                if ($channelremark['remark'] == null)
                    continue;
                if ($channelremark['linkjoin'] == null)
                    continue;
                // hidden here means gone from the real message too, not just
                // the admin preview - channel_button_text() only marks it
                // with 🚫 for admin screens (forAdminPreview=true), so a
                // plain call here never leaks that marker to real users
                if (!empty($channelremark['hidden']))
                    continue;
                if (!in_array($channelremark['link'], $gate_links))
                    continue;
                list($cbc_text, $cbc_iconId) = channel_button_text($channelremark);
                $cbc_btn = [
                    'text' => $cbc_text,
                    'url' => $channelremark['linkjoin']
                ];
                if ($cbc_iconId !== '') {
                    $cbc_btn['icon_custom_emoji_id'] = $cbc_iconId;
                }
                $cbc_style = channel_button_style($channelremark);
                if ($cbc_style !== '') {
                    $cbc_btn['style'] = $cbc_style;
                }
                $keyboardchannel['inline_keyboard'][] = [$cbc_btn];
            }
            $keyboardchannel['inline_keyboard'][] = [['text' => $textbotlang['users']['channel']['confirmjoin'], 'callback_data' => "confirmchannel"]];
            $keyboardchannel = json_encode($keyboardchannel);
            sendmessage($from_id, $textbotlang['textbot']['channel'], $keyboardchannel, 'html');
            return;
        }
    }
}
#-----------🛡 referral verification: «✅ عضو شدم»------------#
if ($datain === 'refverify') {
    if (refv_try($from_id, $keyboard, true)) {
        deletemessage($from_id, $message_id);
    } else {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => $textbotlang['users']['affiliates']['verifyNotYet'], 'show_alert' => true]);
    }
    return;
}
#-----------🎁 کانفیگ رایگان: the customer names its account------------#
if ($datain === 'affrwname') {
    if ((affrw_row($from_id)['status'] ?? '') !== 'naming') {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => $textbotlang['users']['affiliates']['rewardNameDone'], 'show_alert' => true]);
        return;
    }
    step('affrwname', $from_id);
    bottext_extras_key_hint('users.affiliates.rewardNamePrompt');
    sendmessage($from_id, $textbotlang['users']['affiliates']['rewardNamePrompt'], null, 'HTML');
    return;
}
if ($user['step'] === 'affrwname' && $datain === '' && is_string($text) && $text !== '') {
    $rwn = strtolower(trim($text));
    if (!preg_match('/^[a-z][a-z\d_]{2,31}$/', $rwn) || substr($rwn, -1) === '_') {
        if (preg_match('/[^\x21-\x7e]/', trim($text)) || $text[0] === '/') {
            // a menu button or a command, not a try at a name: it goes on as
            // usual, and the ✍️ button still works later
            step('home', $from_id);
            $user['step'] = 'home';
        } else {
            sendmessage($from_id, $textbotlang['users']['invalidusername'], null, 'HTML');
            return;
        }
    } else {
        if (affrw_name_taken($from_id, $rwn)) {
            bottext_extras_key_hint('users.affiliates.rewardNameTaken');
            sendmessage($from_id, $textbotlang['users']['affiliates']['rewardNameTaken'], null, 'HTML');
            return;
        }
        step('home', $from_id);
        // made once, however many times it is sent
        if (affrw_move($from_id, 'naming', 'giving') && !affrw_give($from_id, $rwn)) {
            affrw_move($from_id, 'giving', 'naming');
            step('affrwname', $from_id);
            bottext_extras_key_hint('users.affiliates.rewardNameTaken');
            sendmessage($from_id, $textbotlang['users']['affiliates']['rewardNameTaken'], null, 'HTML');
        }
        return;
    }
}
// 💡 روشن/خاموش: no question first - the tap is the answer. The service is
// switched, a short note says what happened, and the screen it was tapped on
// is drawn again so the button shows the new state (green on, red off): the
// service's own screen in full, a purchase / test / gift message by its 💡
// alone (its text is the config, not the status).
if (preg_match('/^changestatus_(\w+)$/', (string) $datain, $cs_m)) {
    $cs_toast = function ($t, $alert = false) use ($callback_query_id) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => $t, 'show_alert' => $alert, 'cache_time' => 1]);
    };
    $statuschangeservice = shop_feature_value('changgestatus', $user['lang'] ?? 'fa', select("shopSetting", "*", "Namevalue", "statuschangeservice", "select")['value']);
    if ($statuschangeservice == "offstatus") {
        sendmessage($from_id, $textbotlang['users']['featureUnavailable'], null, 'html');
        return;
    }
    $id_invoice = $cs_m[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    if (!is_array($nameloc) || $nameloc['Status'] == "disablebyadmin") {
        sendmessage($from_id, $textbotlang['users']['featureUnavailable'], null, 'html');
        return;
    }
    // 🚪 a free config paused because invitees left its channel: only
    // inviting again (or them coming back) turns it on
    $rw_missing = affrw_paused_missing($nameloc);
    if ($rw_missing !== null) {
        bottext_extras_key_hint('users.affiliates.rewardPausedLocked');
        sendmessage($from_id, strtr($textbotlang['users']['affiliates']['rewardPausedLocked'], ['{missing}' => $rw_missing]), null, 'html');
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "on_hold") {
        // not connected yet: a popup over the screen it was tapped on, not a
        // new message under it - the same in a bought service, a test account,
        // a referral gift and 🛍 سرویس‌های من (⏱'s own: paygpause_)
        $cs_toast($textbotlang['users']['status']['notConnectedCannotChangeStatus'], true);
        return;
    }
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['status']['error'], null, 'html');
        return;
    }
    // The panel is asked once before (above) and once to switch: what the
    // service is now is worked out from what it was, so the answer reaches the
    // customer after two requests instead of five. The screen redrawn after
    // that is told the same, and the panel's own word replaces it on the next
    // refresh.
    $dataoutput = $ManagePanel->Change_status($nameloc['username'], $nameloc['Service_location'], $DataUserOut);
    if (!is_array($dataoutput) || $dataoutput['status'] == "Unsuccessful") {
        $cs_toast($textbotlang['users']['status']['notchanged'], true);
        return;
    }
    $DataUserOut['status'] = $DataUserOut['status'] == "active" ? "disabled" : "active";
    $cs_now = $DataUserOut['status'] == "active";
    $cs_toast($cs_now ? $textbotlang['users']['status']['activedconfig'] : $textbotlang['users']['status']['disabledconfig']);
    $cs_kb = (array) ($update['callback_query']['message']['reply_markup']['inline_keyboard'] ?? []);
    $cs_mine = false;
    foreach ($cs_kb as $cs_row) {
        foreach ((array) $cs_row as $cs_btn) {
            if (($cs_btn['callback_data'] ?? '') === 'backorder') {
                $cs_mine = true;
            }
        }
    }
    // the button first - it is what the customer is looking at
    $cs_panel = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $cs_new = is_array($cs_panel) ? svc_flip_status_button($cs_kb, $nameloc, $cs_panel, $DataUserOut['status'], $user, $textbotlang, $cs_mine ? 'mine' : null) : null;
    if ($cs_new !== null) {
        telegram('editMessageReplyMarkup', ['chat_id' => $from_id, 'message_id' => $message_id, 'reply_markup' => json_encode(['inline_keyboard' => $cs_new])]);
    }
    if (!$cs_mine) {
        return;
    }
    // the service's own screen: drawn again in place, status line and all
    $GLOBALS['mz_datauser_handover'][$nameloc['Service_location'] . '|' . $nameloc['username']] = $DataUserOut;
    $datain = 'updateproduct_' . $id_invoice;
    $cs_answered = true;
}
// 🧩 حجم دلخواه (cvol.php): the plan screen's taps and typing. Its 🔙 hands
// the tap on to the screen it goes back to, which is then drawn as usual.
if (function_exists('cvol_customer_dispatch')) {
    $cv_res = cvol_customer_dispatch();
    if ($cv_res === true) {
        return;
    }
    if (is_string($cv_res) && $cv_res !== '') {
        $datain = $cv_res;
    }
}
// 💰 «افزایش موجودی» of a purchase that is short (shortfall.php)
if (function_exists('sf_dispatch') && sf_dispatch()) {
    return;
}
// 💳 پرداخت مستقیم (directpay.php): 🔙 from its payment methods goes back to the plan list
if (function_exists('dpay_dispatch') && ($dp_res = dpay_dispatch()) !== false) {
    $datain = $dp_res;
}
// 📨 تیکت‌های من (tickets.php): the customer's ticket list and conversations,
// and the supporter's «🔒 بستن تیکت» (a supporter need not be an admin)
if (function_exists('tk_dispatch') && (tk_dispatch() || tk_admin_dispatch())) {
    return;
}
// 🔁 تمدید خودکار (autorenew.php): the button on a service's page
if (function_exists('ar_dispatch') && ar_dispatch()) {
    return;
}
// 💸 انتقال موجودی (wtransfer.php): its taps and what is typed into it
if (function_exists('wt_dispatch') && wt_dispatch()) {
    return;
}
if ($text == "/start" || $datain == "start" || $text == "start") {
    update("user", "Processing_value", "0", "id", $from_id);
    update("user", "Processing_value_one", "0", "id", $from_id);
    update("user", "Processing_value_tow", "0", "id", $from_id);
    update("user", "Processing_value_four", "0", "id", $from_id);
    step('home', $from_id);
    $lsw_start = json_decode((string) ($setting['lang_switch'] ?? ''), true);
    // If the language picker is about to be shown, defer "سلام خوش‌آمدید" to
    // the setlang: handler (which already sends it) instead of sending it
    // here too - otherwise the user gets it twice in the same /start.
    $lsw_will_show_start = false;
    if (is_array($lsw_start) && (($lsw_start['enabled'] ?? '0') === '1')) {
        $lsw_mode_start = ((($lsw_start['mode'] ?? 'once') === 'always')) ? 'always' : 'once';
        if ($lsw_mode_start === 'always' || ($user['lang_prompted'] ?? '0') !== '1') {
            $lsw_will_show_start = true;
        }
    }
    if ($lsw_will_show_start) {
        list($lsw_caption_start, $lsw_kb_start) = language_picker_payload();
        // HTML, as the 🌏 تغییر زبان edit of the same screen: a premium emoji in
        // its 🎨 caption is a <tg-emoji> tag
        sendmessage($from_id, $lsw_caption_start, $lsw_kb_start, 'HTML');
        update("user", "lang_prompted", "1", "id", $from_id);
    } else {
        sendmessage($from_id, strtr($textbotlang['users']['text_start'], bottext_user_placeholders($user, $from_id)), $keyboard, "html");
    }
    return;
} elseif ($text == "/language" || in_array($text, language_button_labels(), true)) {
    list($lsw_caption_cmd, $lsw_kb_cmd) = language_picker_payload();
    sendmessage($from_id, $lsw_caption_cmd, $lsw_kb_cmd, 'HTML');
    return;
} elseif ($text == "version") {
    sendmessage($from_id, $version, null, 'html');
// 🏠 بازگشت به منوی اصلی is printed by BOTH surfaces: the shop's own screens
// carry it in the reader's language, and the panel's keyboards carry it in
// Persian like the rest of the panel. This branch is the only thing that reads
// it back, so it has to accept either - matching only the reader's copy left
// the panel's button dead for anyone who had not picked Persian.
} elseif ($text == $textbotlang['users']['backbtn'] || $text == panel_texts()['users']['backbtn'] || $datain == "backuser") {
    if ($datain == "backuser")
        deletemessage($from_id, $message_id);
    $message_id = sendmessage($from_id, $textbotlang['users']['back'], $keyboard, 'html');
    step('home', $from_id);
    update("user", "Processing_value", "0", "id", $from_id);
    update("user", "Processing_value_one", "0", "id", $from_id);
    update("user", "Processing_value_tow", "0", "id", $from_id);
    update("user", "Processing_value_four", "0", "id", $from_id);
    return;
} elseif ($user['step'] == 'get_number') {
    if (empty($user_phone)) {
        sendmessage($from_id, $textbotlang['users']['number']['false'], $request_contact, 'html');
        return;
    }
    if ($contact_id != $from_id) {
        sendmessage($from_id, $textbotlang['users']['number']['warning'], $request_contact, 'html');
        return;
    }
    // Phone verification carries the country requirement itself: when it is on
    // for a language, that language's OWN dial code is what gets enforced
    // (🌐 وضعیت قابلیت‌ها (هر زبان) → ⚙️ on the phone row). A language with no
    // country configured accepts any number.
    if (feature_value('get_number', $user['lang'] ?? 'fa', $setting['get_number']) == "onAuthenticationphone" && !phone_matches_lang($user_phone, $user['lang'] ?? 'fa')) {
        sendmessage($from_id, strtr($textbotlang['users']['number']['erroriran'], [
            '{prefixes}' => implode(' / ', array_map(function ($p) {
                return '+' . $p;
            }, phone_prefixes_for_lang($user['lang'] ?? 'fa'))),
        ]), $request_contact, 'html');
        return;
    }
    update("user", "number", $user_phone, "id", $from_id);
    step('home', $from_id);
    // Back to the flow that asked for the number (each of the four gates leaves
    // its marker in Processing_value), not the welcome screen. Anything else in
    // that field means there is nothing to pick up.
    if (in_array((string) $user['Processing_value'], ['verifybuy', 'verifyusertest', 'verifybulk', 'verifytopup', 'verifyref'], true)) {
        $verify_resume = $user['Processing_value'];
        update("user", "Processing_value", "0", "id", $from_id);
    }
    // the flows below read the number from here, not from the database
    $user['number'] = $user_phone;
    // the contact keyboard has to go: a reply-keyboard main menu takes its place
    // in this same message, an inline one can't ride on a keyboard removal
    $verifyMenuIsReply = array_key_exists('keyboard', (array) json_decode((string) $keyboard, true));
    sendmessage($from_id, $textbotlang['users']['number']['active'], $verifyMenuIsReply ? $keyboard : json_encode(['remove_keyboard' => true]), 'html');
    if ($verify_resume === 'verifyref') {
        // 🛡 a referral waiting on this number: counted, or asked for the
        // channels still to join
        refv_try($from_id, $keyboard);
        return;
    }
    if ($verify_resume === 'verifyusertest') {
        // the test-account entry is further down this same elseif chain, so this
        // request can't reach it - its next screen, behind the same checks
        if (!mainmenu_btn_active($user['lang'] ?? 'fa', "text_usertest")) {
            sendmessage($from_id, $textbotlang['users']['usertest']['unavailable'], null, 'HTML');
            return;
        }
        if (panels_available_count($user['lang'] ?? 'fa', $user['agent'], 'test') == 0) {
            sendmessage($from_id, $textbotlang['users']['usertest']['noPanel'], null, 'HTML');
            return;
        }
        if ($user['limit_usertest'] <= 0 && !$admin_test_free) {
            sendmessage($from_id, $textbotlang['users']['usertest']['limitwarning'], $keyboard_buy, 'html');
            return;
        }
        sell_sticker_retire($from_id);
        $usertestLocationMsg = sendmessage($from_id, $textbotlang['textbot']['selectLocationTest'], $list_marzban_usertest, 'html');
        if (!empty($usertestLocationMsg['_sticker_message_id'])) {
            update("user", "bt_sticker_id", (string) $usertestLocationMsg['_sticker_message_id'], "id", $from_id);
        }
    } elseif ($verify_resume === '' && !$verifyMenuIsReply) {
        // nothing to pick up: the inline menu still needs a message to sit under -
        // the one 🏠 بازگشت به منوی اصلی sends, not the welcome text a second time
        sendmessage($from_id, $textbotlang['users']['back'], $keyboard, 'html');
    }
    // verifybuy / verifybulk / verifytopup: their own entries in the next elseif
    // chain match $verify_resume and run for this same request, checks and all
} elseif ($text == $textbotlang['textbot']['purchasedServices'] || $datain == "backorder" || $text == "/services") {
    $pages = 1;
    update("user", "pagenumber", $pages, "id", $from_id);
    $page = 1;
    $items_per_page = 20;
    $start_index = ($page - 1) * $items_per_page;
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    // ⏱ hourly services first, above every other (invoice.payg, payg_ensure_schema)
    payg_ensure_schema();
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :from_id AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') ORDER BY payg = 1 DESC, time_sell DESC LIMIT :start_index, :items_per_page");
    $stmt->bindParam(':from_id', $from_id, PDO::PARAM_STR);
    $stmt->bindParam(':start_index', $start_index, PDO::PARAM_INT);
    $stmt->bindParam(':items_per_page', $items_per_page, PDO::PARAM_INT);
    $stmt->execute();
    if (feature_value('statusnamecustom', $user['lang'] ?? 'fa', $setting['statusnamecustom']) == 'onnamecustom') {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rowDataUserOut = $ManagePanel->DataUser($row['Service_location'], $row['username']);
            if (isset($rowDataUserOut['msg']) && strcasecmp(trim((string) $rowDataUserOut['msg']), "user not found") === 0) {
                update("invoice", "Status", "disabled", "id_invoice", $row['id_invoice']);
                if ($row['name_product'] === $textbotlang['Admin']['adminphp']['db_test_service_name']) {
                    notify_test_expired($row, $textbotlang);
                }
                continue;
            }
            $data = "";
            if ($row != null)
                $data = " | {$row['note']}";
            $keyboardlists['inline_keyboard'][] = [
                myservices_row_btn($row, $textbotlang, $user['lang'] ?? 'fa', $data),
            ];
        }
    } else {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rowDataUserOut = $ManagePanel->DataUser($row['Service_location'], $row['username']);
            if (isset($rowDataUserOut['msg']) && strcasecmp(trim((string) $rowDataUserOut['msg']), "user not found") === 0) {
                update("invoice", "Status", "disabled", "id_invoice", $row['id_invoice']);
                if ($row['name_product'] === $textbotlang['Admin']['adminphp']['db_test_service_name']) {
                    notify_test_expired($row, $textbotlang);
                }
                continue;
            }
            $keyboardlists['inline_keyboard'][] = [
                myservices_row_btn($row, $textbotlang, $user['lang'] ?? 'fa'),
            ];
        }
    }
    // nothing survived the live panel check (or the user never had a service):
    // show the buy prompt instead of an empty list carrying only pagination
    if (count($keyboardlists['inline_keyboard']) === 0 && feature_value('NotUser', $user['lang'] ?? 'fa', $setting['NotUser']) == "offnotuser") {
        $noServiceKb = sell_noservice_kb($user['lang'] ?? 'fa', $textbotlang);
        // the 🛍 سرویس‌های من tap already auto-fired ITS OWN sticker before this
        // point - if there is a dedicated sticker for "no active service" it
        // replaces that one (bottext_fire_extras() below retires-before-firing
        // on its own); if there is NOT one configured, this line is what keeps
        // the generic 🛍 sticker from showing through anyway, matching the
        // requested default of "no sticker at all" for the empty state
        if (function_exists('bottext_sticker_retire')) {
            bottext_sticker_retire($from_id);
        }
        if ($datain == "backorder") {
            Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['service_not_available'], $noServiceKb);
        } else {
            // the user's own tap is deliberately left in place - deleting it made
            // the 🛍 سرویس های من message vanish from their chat history
            sendmessage($from_id, $textbotlang['users']['sell']['service_not_available'], $noServiceKb, 'html');
        }
        return;
    }
    // how many service rows this page actually produced - a full page means
    // there may be more, anything less means this is the last one and a "next"
    // button would just loop the user back to page 1
    $ms_rowCount = count($keyboardlists['inline_keyboard']);
    if (feature_value('NotUser', $user['lang'] ?? 'fa', $setting['NotUser']) == "onnotuser") {
        $keyboardlists['inline_keyboard'][] = [['text' => $textbotlang['users']['page']['notusernameme'], 'callback_data' => 'notusernameme']];
    }
    // search and paging when there is more than this page, and ❌ بستن -
    // the same rows every page gets (genbtn 'sc', per language)
    foreach (myservices_nav_rows($user['lang'] ?? 'fa', $textbotlang, 1, $ms_rowCount >= $items_per_page) as $ms_row) {
        $keyboardlists['inline_keyboard'][] = $ms_row;
    }
    $keyboard_json = json_encode($keyboardlists);
    // 🛍 سرویس‌های من might have JUST fired its own sticker on this exact tap -
    // if there is a dedicated sticker for "has an active service" it replaces
    // that one (bottext_fire_extras() retires-before-firing on its own below);
    // if there is NOT one configured, this is what stops the generic 🛍
    // sticker from showing through anyway (default: no sticker), matching how
    // the "no active service" screen already behaves
    if (function_exists('bottext_sticker_retire')) {
        bottext_sticker_retire($from_id);
    }
    if ($datain == "backorder") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['service_sell'], $keyboard_json);
    } else {
        sendmessage($from_id, $textbotlang['users']['sell']['service_sell'], $keyboard_json, 'html');
    }
} elseif (preg_match('/^mmclose(?::([a-z]{2}))?$/', $datain, $mm_alias_m)) {
    // shared ❌ بستن for the main-menu screens (اکانت تست / حساب کاربری /
    // افزایش موجودی / آموزش) - four different sections behind one callback,
    // so the ':xx' suffix says which section's OWN 🖼 استیکر دکمه بستن setting
    // applies (each is customized independently now). Nothing is deleted here
    // directly: the caption and whatever sticker came with it are handed to
    // close_sticker_play(), which removes them together with that section's
    // own sticker once its timer runs out (or right away if that section has
    // it off) - see its docblock.
    $mm_key = close_sticker_alias_to_key($mm_alias_m[1] ?? '') ?? 'bottext.btnCloseAccount';
    $mm_toDelete = [(int) $message_id];
    if (ctype_digit((string) ($user['menu_sticker_id'] ?? '')) && intval($user['menu_sticker_id']) > 0) {
        $mm_toDelete[] = intval($user['menu_sticker_id']);
        update("user", "menu_sticker_id", "0", "id", $from_id);
    }
    // the category screen falls back to this close when 🖥 نمایش انتخاب پنل is
    // off, so a purchase-flow sticker can be on screen here too
    $mm_sellSticker = sell_sticker_capture($from_id);
    if ($mm_sellSticker > 0) {
        $mm_toDelete[] = $mm_sellSticker;
    }
    // the reply-keyboard tap message ("🔑 اکانت تست" etc, sent as the user's
    // OWN message) joins the same deferred removal - it used to vanish the
    // instant بستن was tapped, ahead of everything else
    $mm_tap = menu_tap_capture($from_id, $user);
    if ($mm_tap > 0) {
        $mm_toDelete[] = $mm_tap;
    }
    step('home', $from_id);
    close_sticker_play($from_id, $mm_key, $mm_toDelete);
    // the test-account list is a message of its own under the menu; the other
    // three took the menu's message
    if ($mm_key !== 'bottext.btnCloseTest' && glass_on($user['lang'] ?? 'fa', $setting)) {
        sendmessage($from_id, $textbotlang['users']['back'], $keyboard, 'html');
    }
} elseif ($datain == "gwinvclose") {
    // An invoice's own way out. Same cleanup as the shared close, plus the
    // main menu back on screen: someone who gives up on a payment should
    // land somewhere they can act from, not on an empty chat with no
    // keyboard - which is all mmclose leaves behind.
    deletemessage($from_id, $message_id);
    if (ctype_digit((string) ($user['menu_sticker_id'] ?? '')) && intval($user['menu_sticker_id']) > 0) {
        deletemessage($from_id, intval($user['menu_sticker_id']));
        update("user", "menu_sticker_id", "0", "id", $from_id);
    }
    menu_tap_cleanup($from_id, $user);
    step('home', $from_id);
    update("user", "Processing_value", "0", "id", $from_id);
    sendmessage($from_id, $textbotlang['users']['back'], $keyboard, 'html');
} elseif (preg_match('/^gwpaid:([a-z0-9]+)$/', $datain, $gp_m)) {
    // the invoice this button is on has already been paid - say so instead
    // of reopening a payment page. Wording is per gateway; telegram()
    // trims it to the 200 characters Telegram allows an alert.
    telegram('answerCallbackQuery', [
        'callback_query_id' => $callback_query_id,
        'text' => topup_paid_alert_for($user['lang'] ?? 'fa', $gp_m[1], $textbotlang['users']['Balance']['topupPaidAlert']),
        'show_alert' => true,
    ]);
} elseif ($datain == "topup_range_close") {
    // ❌ under the "حداقل/حداکثر مبلغ" notice - same deferred-together removal
    // as every other ❌ بستن, using 💰 افزایش موجودی's own sticker setting since
    // this notice belongs to that same flow; end the top-up session right
    // away, the notice itself goes with close_sticker_play(). Every gateway's
    // refusal shares this one button (topup_amount_notice).
    //
    // It closes the whole top-up screen, not just the notice: the
    // "💵 مبلغ دلخواه" prompt above it, the customer's own "💰 افزایش موجودی"
    // tap and its sticker - left behind, the prompt kept asking for an amount
    // nobody was going to type.
    $tr_toDelete = [(int) $message_id];
    $tr_prompt = (int) ($user['topup_custom_msg_id'] ?? 0);
    if ($tr_prompt > 0) {
        $tr_toDelete[] = $tr_prompt;
        update("user", "topup_custom_msg_id", "0", "id", $from_id);
    }
    if (ctype_digit((string) ($user['menu_sticker_id'] ?? '')) && intval($user['menu_sticker_id']) > 0) {
        $tr_toDelete[] = intval($user['menu_sticker_id']);
        update("user", "menu_sticker_id", "0", "id", $from_id);
    }
    $tr_tap = menu_tap_capture($from_id, $user);
    if ($tr_tap > 0) {
        $tr_toDelete[] = $tr_tap;
    }
    update("user", "topup_range_msg_id", "0", "id", $from_id);
    step('home', $from_id);
    close_sticker_play($from_id, 'bottext.btnCloseTopup', $tr_toDelete);
    if (glass_on($user['lang'] ?? 'fa', $setting)) {
        sendmessage($from_id, $textbotlang['users']['back'], $keyboard, 'html');
    }
} elseif ($datain == "sellclose") {
    // closes the panel picker and takes the buy-button sticker that was sent
    // with it along with it. Processing_value_tow is the right field to read
    // here: keyboard.php stashes the text_sell sticker id there on the tap
    // that opened this screen, and 'sellclose' is not in the sticker map so
    // nothing has overwritten it since.
    $sc_toDelete = [(int) $message_id];
    if (ctype_digit((string) ($user['Processing_value_tow'] ?? '')) && intval($user['Processing_value_tow']) > 0) {
        $sc_toDelete[] = intval($user['Processing_value_tow']);
        update("user", "Processing_value_tow", "", "id", $from_id);
    }
    // ...and the sticker a screen INSIDE the flow put up, which lives in its
    // own field (bt_sticker_id).
    $sc_sellSticker = sell_sticker_capture($from_id);
    if ($sc_sellSticker > 0) {
        $sc_toDelete[] = $sc_sellSticker;
    }
    // same deferred removal for the reply-keyboard tap message - see mmclose
    $sc_tap = menu_tap_capture($from_id, $user);
    if ($sc_tap > 0) {
        $sc_toDelete[] = $sc_tap;
    }
    step('home', $from_id);
    close_sticker_play($from_id, 'bottext.btnCloseBuy', $sc_toDelete);
    if (glass_on($user['lang'] ?? 'fa', $setting)) {
        sendmessage($from_id, $textbotlang['users']['back'], $keyboard, 'html');
    }
} elseif ($datain == "servclose") {
    // closes the list the same way every other ❌ بستن in the bot does, and
    // takes the main-menu sticker that was sent alongside it with it
    $sv_toDelete = [(int) $message_id];
    if (ctype_digit((string) ($user['menu_sticker_id'] ?? '')) && intval($user['menu_sticker_id']) > 0) {
        $sv_toDelete[] = intval($user['menu_sticker_id']);
        update("user", "menu_sticker_id", "0", "id", $from_id);
    }
    // same deferred removal for the reply-keyboard tap message - see mmclose
    $sv_tap = menu_tap_capture($from_id, $user);
    if ($sv_tap > 0) {
        $sv_toDelete[] = $sv_tap;
    }
    close_sticker_play($from_id, 'servclose', $sv_toDelete);
    if (glass_on($user['lang'] ?? 'fa', $setting)) {
        sendmessage($from_id, $textbotlang['users']['back'], $keyboard, 'html');
    }
} elseif ($datain == 'next_page') {
    $numpage = select("invoice", "id_user", "id_user", $from_id, "count");
    $page = $user['pagenumber'];
    $items_per_page = 20;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    // ⏱ hourly services first, above every other (invoice.payg, payg_ensure_schema)
    payg_ensure_schema();
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :from_id AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') ORDER BY payg = 1 DESC, time_sell DESC LIMIT :start_index, :items_per_page");
    $stmt->bindParam(':from_id', $from_id, PDO::PARAM_STR);
    $stmt->bindParam(':start_index', $start_index, PDO::PARAM_INT);
    $stmt->bindParam(':items_per_page', $items_per_page, PDO::PARAM_INT);
    $stmt->execute();
    if (feature_value('statusnamecustom', $user['lang'] ?? 'fa', $setting['statusnamecustom']) == 'onnamecustom') {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rowDataUserOut = $ManagePanel->DataUser($row['Service_location'], $row['username']);
            if (isset($rowDataUserOut['msg']) && strcasecmp(trim((string) $rowDataUserOut['msg']), "user not found") === 0) {
                update("invoice", "Status", "disabled", "id_invoice", $row['id_invoice']);
                if ($row['name_product'] === $textbotlang['Admin']['adminphp']['db_test_service_name']) {
                    notify_test_expired($row, $textbotlang);
                }
                continue;
            }
            $data = "";
            if ($row != null)
                $data = " | {$row['note']}";
            $keyboardlists['inline_keyboard'][] = [
                myservices_row_btn($row, $textbotlang, $user['lang'] ?? 'fa', $data),
            ];
        }
    } else {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rowDataUserOut = $ManagePanel->DataUser($row['Service_location'], $row['username']);
            if (isset($rowDataUserOut['msg']) && strcasecmp(trim((string) $rowDataUserOut['msg']), "user not found") === 0) {
                update("invoice", "Status", "disabled", "id_invoice", $row['id_invoice']);
                if ($row['name_product'] === $textbotlang['Admin']['adminphp']['db_test_service_name']) {
                    notify_test_expired($row, $textbotlang);
                }
                continue;
            }
            $keyboardlists['inline_keyboard'][] = [
                myservices_row_btn($row, $textbotlang, $user['lang'] ?? 'fa'),
            ];
        }
    }
    if (feature_value('NotUser', $user['lang'] ?? 'fa', $setting['NotUser']) == "onnotuser") {
        $keyboardlists['inline_keyboard'][] = [['text' => $textbotlang['users']['page']['notusernameme'], 'callback_data' => 'notusernameme']];
    }
    // the same rows as page one (genbtn 'sc', per language): search, the
    // paging for the page this is, ❌ بستن
    foreach (myservices_nav_rows($user['lang'] ?? 'fa', $textbotlang, $next_page, true) as $ms_row) {
        $keyboardlists['inline_keyboard'][] = $ms_row;
    }
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    // 🛍 سرویس‌های من might have JUST fired its own sticker on this exact tap -
    // if there is a dedicated sticker for "has an active service" it replaces
    // that one (bottext_fire_extras() retires-before-firing on its own below);
    // if there is NOT one configured, this is what stops the generic 🛍
    // sticker from showing through anyway (default: no sticker), matching how
    // the "no active service" screen already behaves
    if (function_exists('bottext_sticker_retire')) {
        bottext_sticker_retire($from_id);
    }
    Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['service_sell'], $keyboard_json);
} elseif ($datain == 'previous_page') {
    $numpage = select("invoice", "id_user", "id_user", $from_id, "count");
    $page = $user['pagenumber'];
    $items_per_page = 20;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $previous_page = 1;
    } else {
        $previous_page = max(1, $page - 1);
    }
    $start_index = ($previous_page - 1) * $items_per_page;
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    // ⏱ hourly services first, above every other (invoice.payg, payg_ensure_schema)
    payg_ensure_schema();
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :from_id AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') ORDER BY payg = 1 DESC, time_sell DESC LIMIT :start_index, :items_per_page");
    $stmt->bindParam(':from_id', $from_id, PDO::PARAM_STR);
    $stmt->bindParam(':start_index', $start_index, PDO::PARAM_INT);
    $stmt->bindParam(':items_per_page', $items_per_page, PDO::PARAM_INT);
    $stmt->execute();
    if (feature_value('statusnamecustom', $user['lang'] ?? 'fa', $setting['statusnamecustom']) == 'onnamecustom') {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rowDataUserOut = $ManagePanel->DataUser($row['Service_location'], $row['username']);
            if (isset($rowDataUserOut['msg']) && strcasecmp(trim((string) $rowDataUserOut['msg']), "user not found") === 0) {
                update("invoice", "Status", "disabled", "id_invoice", $row['id_invoice']);
                if ($row['name_product'] === $textbotlang['Admin']['adminphp']['db_test_service_name']) {
                    notify_test_expired($row, $textbotlang);
                }
                continue;
            }
            $data = "";
            if ($row != null)
                $data = " | {$row['note']}";
            $keyboardlists['inline_keyboard'][] = [
                myservices_row_btn($row, $textbotlang, $user['lang'] ?? 'fa', $data),
            ];
        }
    } else {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rowDataUserOut = $ManagePanel->DataUser($row['Service_location'], $row['username']);
            if (isset($rowDataUserOut['msg']) && strcasecmp(trim((string) $rowDataUserOut['msg']), "user not found") === 0) {
                update("invoice", "Status", "disabled", "id_invoice", $row['id_invoice']);
                if ($row['name_product'] === $textbotlang['Admin']['adminphp']['db_test_service_name']) {
                    notify_test_expired($row, $textbotlang);
                }
                continue;
            }
            $keyboardlists['inline_keyboard'][] = [
                myservices_row_btn($row, $textbotlang, $user['lang'] ?? 'fa'),
            ];
        }
    }
    if (feature_value('NotUser', $user['lang'] ?? 'fa', $setting['NotUser']) == "onnotuser") {
        $keyboardlists['inline_keyboard'][] = [['text' => $textbotlang['users']['page']['notusernameme'], 'callback_data' => 'notusernameme']];
    }
    // the same rows as page one (genbtn 'sc', per language): search, the
    // paging for the page this is, ❌ بستن
    foreach (myservices_nav_rows($user['lang'] ?? 'fa', $textbotlang, $previous_page, true) as $ms_row) {
        $keyboardlists['inline_keyboard'][] = $ms_row;
    }
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $previous_page, "id", $from_id);
    // 🛍 سرویس‌های من might have JUST fired its own sticker on this exact tap -
    // if there is a dedicated sticker for "has an active service" it replaces
    // that one (bottext_fire_extras() retires-before-firing on its own below);
    // if there is NOT one configured, this is what stops the generic 🛍
    // sticker from showing through anyway (default: no sticker), matching how
    // the "no active service" screen already behaves
    if (function_exists('bottext_sticker_retire')) {
        bottext_sticker_retire($from_id);
    }
    Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['service_sell'], $keyboard_json);
} elseif ($datain == "notusernameme") {
    sendmessage($from_id, $textbotlang['users']['status']['sendUsername'], $backuser, 'html');
    step('getusernameinfo', $from_id);
} elseif ($user['step'] == "getusernameinfo") {
    if (empty($text))
        return;
    $usernameconfig = $text;
    update("user", "Processing_value", $usernameconfig, "id", $from_id);
    sendmessage($from_id, $textbotlang['textbot']['selectLocation'], $list_marzban_panel_user, 'html');
    step('getdata', $from_id);
} elseif (preg_match('/locationnotuser_(.*)/', $datain, $dataget)) {
    $marzban_list_get = select("marzban_panel", "*", "code_panel", $dataget[1]);
    update("user", "Processing_value_four", $marzban_list_get['code_panel'], "id", $from_id);
    $DataUserOut = $ManagePanel->DataUser($marzban_list_get['name_panel'], $user['Processing_value']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        if ($DataUserOut['msg'] == "User not found") {
            sendmessage($from_id, $textbotlang['users']['status']['notUsernameGet'], $keyboard, 'html');
            step('home', $from_id);
            return;
        }
        sendmessage($from_id, $textbotlang['users']['status']['error'], $keyboard, 'html');
        step('home', $from_id);
        return;
    }
    #-------------[ status ]----------------#
    $status = $DataUserOut['status'];
    $status_var = [
        'active' => $textbotlang['users']['status']['active'],
        'limited' => $textbotlang['users']['status']['limited'],
        'disabled' => $textbotlang['users']['status']['disabled'],
        'deactivev' => $textbotlang['users']['status']['disabled'],
        'expired' => $textbotlang['users']['status']['expired'],
        'on_hold' => $textbotlang['users']['status']['on_hold'],
        'Unknown' => $textbotlang['users']['status']['unknown']
    ][$status];
    #--------------[ expire ]---------------#
    $expirationDate = $DataUserOut['expire'] ? format_datetime('Y/m/d', $DataUserOut['expire'], $user['lang']) : $textbotlang['users']['status']['unlimited'];
    #-------------[ data_limit ]----------------#
    $LastTraffic = $DataUserOut['data_limit'] ? formatBytes($DataUserOut['data_limit']) : $textbotlang['users']['status']['unlimited'];
    #---------------[ RemainingVolume ]--------------#
    $output = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
    $RemainingVolume = $DataUserOut['data_limit'] ? formatBytes($output) : $textbotlang['common']['labels']['unlimitedShort'];
    #---------------[ used_traffic ]--------------#
    $usedTrafficGb = $DataUserOut['used_traffic'] ? formatBytes($DataUserOut['used_traffic']) : $textbotlang['users']['status']['notConsumed'];
    #--------------[ day ]---------------#
    $timeDiff = $DataUserOut['expire'] - time();
    $day = $DataUserOut['expire'] ? floor($timeDiff / 86400) . $textbotlang['users']['status']['day'] : $textbotlang['users']['status']['unlimited'];
    #-----------------------------#


    $keyboardinfo = [
        'inline_keyboard' => [
            [
                ['text' => $DataUserOut['username'], 'callback_data' => "username"],
                ['text' => $textbotlang['users']['status']['username'], 'callback_data' => 'username'],
            ],
            [
                ['text' => $status_var, 'callback_data' => 'status_var'],
                ['text' => $textbotlang['users']['status']['stateus'], 'callback_data' => 'status_var'],
            ],
            [
                ['text' => $expirationDate, 'callback_data' => 'expirationDate'],
                ['text' => $textbotlang['users']['status']['expirationDate'], 'callback_data' => 'expirationDate'],
            ],
            [],
            [
                ['text' => $day, 'callback_data' => $textbotlang['common']['units']['dayShort']],
                ['text' => $textbotlang['users']['status']['daysleft'], 'callback_data' => 'day'],
            ],
            [
                ['text' => $LastTraffic, 'callback_data' => 'LastTraffic'],
                ['text' => $textbotlang['users']['status']['lastTraffic'], 'callback_data' => 'LastTraffic'],
            ],
            [
                ['text' => $usedTrafficGb, 'callback_data' => 'expirationDate'],
                ['text' => $textbotlang['users']['status']['usedTrafficGb'], 'callback_data' => 'expirationDate'],
            ],
            [
                ['text' => $RemainingVolume, 'callback_data' => 'RemainingVolume'],
                ['text' => $textbotlang['users']['status']['remainingVolume'], 'callback_data' => 'RemainingVolume'],
            ]
        ]
    ];
    $marzbanstatusextra = shop_feature_value('extravolunme', $user['lang'] ?? 'fa', select("shopSetting", "*", "Namevalue", "statusextra", "select")['value']);
    if ($marzbanstatusextra == "onextra") {
        $keyboardinfo['inline_keyboard'][] = [
            ['text' => $textbotlang['users']['extend']['title'], 'callback_data' => 'extends_' . $DataUserOut['username'] . "_" . $dataget[1]],
            ['text' => $textbotlang['users']['extraVolume']['sellextra'], 'callback_data' => 'Extra_volumes_' . $DataUserOut['username'] . '_' . $dataget[1]],
        ];
    } else {
        $keyboardinfo['inline_keyboard'][] = [['text' => $textbotlang['users']['extend']['title'], 'callback_data' => 'extends_' . $DataUserOut['username'] . "_" . $dataget[1]]];
    }
    $keyboardinfo = json_encode($keyboardinfo);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['status']['info'], $keyboardinfo);
    sendmessage($from_id, $textbotlang['users']['selectoption'], $keyboard, 'html');
    step('home', $from_id);
} elseif (preg_match('/^product_(\w+)/', $datain, $dataget) || preg_match('/updateproduct_(\w+)/', $datain, $dataget) || $user['step'] == "getuseragnetservice" || $datain == "productcheckdata") {
    if ($user['step'] == "getuseragnetservice") {
        $username = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $sql = "SELECT * FROM invoice WHERE (username LIKE CONCAT('%', :username, '%') OR note  LIKE CONCAT('%', :notes, '%') OR Volume LIKE CONCAT('%',:Volume, '%') OR Service_time LIKE CONCAT('%',:Service_time, '%')) AND id_user = :id_user AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold')";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->bindParam(':Service_time', $username, PDO::PARAM_STR);
        $stmt->bindParam(':Volume', $username, PDO::PARAM_STR);
        $stmt->bindParam(':notes', $username, PDO::PARAM_STR);
        $stmt->bindParam(':id_user', $from_id);
        $stmt->execute();
    } elseif ($datain == "productcheckdata") {
        $username = $user['Processing_value'];
        $sql = "SELECT * FROM invoice WHERE username = :username AND id_user = :id_user";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':id_user', $from_id);
        $stmt->execute();
    } elseif ($datain[0] == "u") {
        $username = $dataget[1];
        $sql = "SELECT * FROM invoice WHERE id_invoice = :username AND id_user = :id_user";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':id_user', $from_id);
        $stmt->execute();
        // (a 💡 that redraws this screen has already answered the tap)
        if (empty($cs_answered)) {
            telegram('answerCallbackQuery', array(
                'callback_query_id' => $callback_query_id,
                'text' => $textbotlang['keyboard']['infoRefreshed'],
                'show_alert' => false,
                'cache_time' => 5,
            ));
        }
    } else {
        $username = $dataget[1];
        $sql = "SELECT * FROM invoice WHERE id_invoice = :username AND id_user = :id_user";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':id_user', $from_id);
        $stmt->execute();
    }
    if ($user['step'] == "getuseragnetservice" && $stmt->rowCount() > 1) {
        $countservice = $stmt->rowCount();
        $pages = 1;
        update("user", "pagenumber", $pages, "id", $from_id);
        $page = 1;
        $items_per_page = 20;
        $start_index = ($page - 1) * $items_per_page;
        $keyboardlists = [
            'inline_keyboard' => [],
        ];
        if (feature_value('statusnamecustom', $user['lang'] ?? 'fa', $setting['statusnamecustom']) == 'onnamecustom') {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $data = "";
                if ($row != null)
                    $data = " | {$row['note']}";
                $keyboardlists['inline_keyboard'][] = [
                    myservices_row_btn($row, $textbotlang, $user['lang'] ?? 'fa', $data),
                ];
            }
        } else {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $keyboardlists['inline_keyboard'][] = [
                    myservices_row_btn($row, $textbotlang, $user['lang'] ?? 'fa'),
                ];
            }
        }
        $backuser = [
            [
                'text' => $textbotlang['keyboard']['backToMainMenu'],
                'callback_data' => 'backuser'
            ]
        ];
        if (feature_value('NotUser', $user['lang'] ?? 'fa', $setting['NotUser']) == "onnotuser") {
            $keyboardlists['inline_keyboard'][] = [['text' => $textbotlang['users']['page']['notusernameme'], 'callback_data' => 'notusernameme']];
        }
        $keyboardlists['inline_keyboard'][] = $backuser;
        $keyboard_json = json_encode($keyboardlists);
        sendmessage($from_id, strtr($textbotlang['users']['status']['servicesFound'], ['{countservice}' => $countservice]), $keyboard_json, 'html');
        step("home", $from_id);
        return;
    }
    $nameloc = $stmt->fetch(PDO::FETCH_ASSOC);
    // a tap from an old list whose service is gone answers like an unavailable one
    $username = is_array($nameloc) ? $nameloc['id_invoice'] : '';
    if (!is_array($nameloc) || !in_array($nameloc['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'])) {
        sendmessage($from_id, $textbotlang['users']['status']['infoUnavailable'], $keyboard, 'html');
        step('home', $from_id);
        return;
    }
    // ⏱ an hourly service has a screen of its own: what it has cost, its
    // rates, the wallet behind it (payg_service_screen)
    if ((int) ($nameloc['payg'] ?? 0) === 1 && ($pg_svc = payg_row($nameloc['id_invoice'])) !== null) {
        $pg_d = payg_panel_data(select("marzban_panel", "*", "name_panel", $pg_svc['panel'], "select"), $pg_svc['username']);
        // what 🔄 will call «since you last looked» starts from here
        payg_set($pg_svc['id_invoice'], ['seen_charged' => $pg_svc['charged']]);
        [$pg_text, $pg_kb] = payg_service_screen($pg_svc, $textbotlang, $pg_d);
        $pg_sub = is_array($pg_d['data'] ?? null) ? (string) ($pg_d['data']['subscription_url'] ?? '') : '';
        if ($user['step'] == "getuseragnetservice") {
            svc_send_status_screen($from_id, 0, $pg_text, $pg_kb, $pg_sub, true);
        } else {
            svc_send_status_screen($from_id, $message_id, $pg_text, $pg_kb, $pg_sub, true);
        }
        step('home', $from_id);
        return;
    }
    $marzban = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($marzban['name_panel'] != null) {
        update("user", "Processing_value_four", $marzban['name_panel'], "id", $from_id);
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if (isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") {
        update("invoice", "Status", "disabled", "id_invoice", $nameloc['id_invoice']);
        if ($nameloc['name_product'] === $textbotlang['Admin']['adminphp']['db_test_service_name']) {
            notify_test_expired($nameloc, $textbotlang);
        }
        sendmessage($from_id, $textbotlang['users']['status']['userNotFound'], $keyboard, 'html');
        step('home', $from_id);
        return;
    }
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['status']['panelNotConnected'], $keyboard, 'html');
        step('home', $from_id);
        return;
    }
    if ($DataUserOut['online_at'] == "online") {
        $lastonline = $textbotlang['common']['connection']['onlineAlt'];
    } elseif ($DataUserOut['online_at'] == "offline") {
        $lastonline = $textbotlang['common']['connection']['offlineAlt'];
    } else {
        if (isset($DataUserOut['online_at']) && $DataUserOut['online_at'] !== null) {
            $dateTime = new DateTime($DataUserOut['online_at'], new DateTimeZone('UTC'));
            $dateTime->setTimezone(new DateTimeZone('Asia/Tehran'));
            $lastonline = format_datetime('Y/m/d H:i:s', $dateTime->getTimestamp(), $user['lang']);
        } else {
            $lastonline = $textbotlang['common']['connection']['notConnectedAlt'];
        }
    }
    #-------------status----------------#
    $status = $DataUserOut['status'];
    $status_var = [
        'active' => $textbotlang['users']['status']['active'],
        'limited' => $textbotlang['users']['status']['limited'],
        'disabled' => $textbotlang['users']['status']['disabled'],
        'expired' => $textbotlang['users']['status']['expired'],
        'on_hold' => $textbotlang['users']['status']['on_hold'],
        'Unknown' => $textbotlang['users']['status']['unknown'],
        'deactivev' => $textbotlang['users']['status']['disabled'],
    ][$status];
    #--------------[ expire ]---------------#
    $expirationDate = $DataUserOut['expire'] ? format_datetime('Y/m/d', $DataUserOut['expire'], $user['lang']) : $textbotlang['users']['status']['unlimited'];
    #-------------[ data_limit ]----------------#
    $LastTraffic = $DataUserOut['data_limit'] ? formatBytes($DataUserOut['data_limit']) : $textbotlang['users']['status']['unlimited'];
    #---------------[ RemainingVolume ]--------------#
    $output = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
    $RemainingVolume = $DataUserOut['data_limit'] ? formatBytes($output) : $textbotlang['common']['labels']['unlimitedShort'];
    #---------------[ used_traffic ]--------------#
    $usedTrafficGb = $DataUserOut['used_traffic'] ? formatBytes($DataUserOut['used_traffic']) : $textbotlang['users']['status']['notConsumed'];
    #--------------[ day ]---------------#
    $timeDiff = $DataUserOut['expire'] - time();
    if ($timeDiff < 0) {
        $day = 0;
    } else {
        $day = "";
        $timemonth = floor($timeDiff / 2592000);
        if ($timemonth > 0) {
            $day .= $timemonth . $textbotlang['users']['status']['month'];
            $timeDiffday = $timeDiff - (2592000 * $timemonth);
        } else {
            $timeDiffday = $timeDiff;
        }
        $timereminday = floor($timeDiffday / 86400);
        if ($timereminday > 0) {
            $day .= $timereminday . $textbotlang['users']['status']['day'];
        }
        $timehoures = intval(($timeDiffday - ($timereminday * 86400)) / 3600);
        if ($timehoures > 0) {
            $day .= $timehoures . $textbotlang['users']['status']['hour'];
        }
        $timehoursall = $timeDiffday - ($timereminday * 86400);
        $timehoursall = $timehoursall - ($timehoures * 3600);
        $timeminuts = intval($timehoursall / 60);
        if ($timeminuts > 0) {
            $day .= $timeminuts . $textbotlang['users']['status']['min'];
        }
        $day .= $textbotlang['common']['labels']['remainingSuffix'];
    }
    #--------------[ subsupdate ]---------------#
    if ($DataUserOut['sub_updated_at'] !== null) {
        $sub_updated = $DataUserOut['sub_updated_at'];
        $dateTime = new DateTime($sub_updated, new DateTimeZone('UTC'));
        $dateTime->setTimezone(new DateTimeZone('Asia/Tehran'));
        $lastupdate = format_datetime('Y/m/d H:i:s', $dateTime->getTimestamp(), $user['lang']);
    }
    #--------------[ Percent ]---------------#
    if ($DataUserOut['data_limit'] != null && $DataUserOut['used_traffic'] != null) {
        $Percent = ($DataUserOut['data_limit'] - $DataUserOut['used_traffic']) * 100 / $DataUserOut['data_limit'];
    } else {
        $Percent = "100";
    }
    if ($Percent < 0)
        $Percent = -($Percent);
    $Percent = round($Percent, 2);
    $keyboardsetting = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['status']['backlist'], 'callback_data' => 'backorder'],
            ]
        ]
    ]);
    if ($marzban['type'] == "ibsng" || $marzban['type'] == "mikrotik") {
        $userpassword = strtr($textbotlang['users']['status']['servicePassword'], ['{subscription_url}' => $DataUserOut['subscription_url']]);
    } else {
        $userpassword = "";
    }
    if ($marzban['type'] == "Manualsale") {
        $userinfo = select("manualsell", "*", "username", $nameloc['username'], "select");
        $textinfo = sprintf($textbotlang['users']['status']['infoBasic'], $status_var, $DataUserOut['username'], $nameloc['id_invoice'], $userinfo['contentrecord']);
        if ($user['step'] == "getuseragnetservice") {
            sendmessage($from_id, $textinfo, $keyboardsetting, 'html');
        } elseif ($datain == "productcheckdata") {
            deletemessage($from_id, $message_id);
            sendmessage($from_id, $textinfo, $keyboardsetting, 'html');
        } else {
            Editmessagetext($from_id, $message_id, $textinfo, $keyboardsetting);
        }
        return;
    }
    $nameconfig = "";
    if ($nameloc['note'] != null) {
        $nameconfig = strtr($textbotlang['users']['status']['configNote'], ['{note}' => $nameloc['note']]);
    }
    $stmt = $pdo->prepare("SELECT value FROM service_other WHERE username = :username AND type = 'extend_user' AND status = 'paid' ORDER BY time DESC");
    $stmt->execute([
        ':username' => $nameloc['username'],
    ]);
    if ($stmt->rowCount() != 0) {
        $service_other = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!($service_other == false || !(is_string($service_other['value']) && is_array(json_decode($service_other['value'], true))))) {
            $service_other = json_decode($service_other['value'], true);
            $codeproduct = select("product", "*", "code_product", $service_other['code_product'], "select");
            if ($codeproduct != false) {
                $nameloc['name_product'] = $codeproduct['name_product'];
                $nameloc['Volume'] = $codeproduct['Volume_constraint'];
                $nameloc['Service_time'] = $codeproduct['Service_time'];
            }
        }
    }
    #-----------------------------#
    $statustimeextra = shop_feature_value('statustimeextra', $user['lang'] ?? 'fa', select("shopSetting", "*", "Namevalue", "statustimeextra", "select")['value']);
    $marzbanstatusextra = shop_feature_value('extravolunme', $user['lang'] ?? 'fa', select("shopSetting", "*", "Namevalue", "statusextra", "select")['value']);
    if (!in_array($status, ["active", "on_hold", "disabled", "Unknown"])) {
        $textinfo = sprintf($textbotlang['users']['status']['infoDetailed'], $status_var, $DataUserOut['username'], $nameloc['Service_location'], $nameloc['name_product'], $lastonline, $LastTraffic, $usedTrafficGb, $RemainingVolume, $Percent, $expirationDate, $day, $nameconfig);

        $keyboardsetting = [
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['extend']['title'], 'callback_data' => 'extend_' . $username],
                    ['text' => $textbotlang['users']['extraVolume']['sellextra'], 'callback_data' => 'Extra_volume_' . $username],
                ],
                [
                    ['text' => $textbotlang['keyboard']['deleteService'], 'callback_data' => 'removeauto-' . $username],
                    ['text' => $textbotlang['users']['extraTime']['title'], 'callback_data' => 'Extra_time_' . $username],
                ],
                [
                    ['text' => $textbotlang['users']['status']['backlist'], 'callback_data' => 'backorder'],
                ]
            ]
        ];
        if ($marzban['type'] == "ibsng" || $marzban['type'] == "mikrotik") {
            unset($keyboardsetting['inline_keyboard'][1][1]);
            unset($keyboardsetting['inline_keyboard'][0]);
        }
        if ($statustimeextra == "offtimeextraa")
            unset($keyboardsetting['inline_keyboard'][1][1]);
        if ($marzbanstatusextra == "offextra")
            unset($keyboardsetting['inline_keyboard'][0][1]);
        if (affrw_renew_blocked($nameloc)) {
            $keyboardsetting['inline_keyboard'] = array_filter(array_map(fn($r) => array_values(array_filter($r, fn($b) => strpos($b['callback_data'] ?? '', 'extend_') !== 0)), $keyboardsetting['inline_keyboard']));
        }
        $keyboardsetting['inline_keyboard'] = array_values($keyboardsetting['inline_keyboard']);
        $keyboardsetting = json_encode($keyboardsetting);
    } else {
        // the buttons are built by svc_action_rows() - the purchase message's
        // config carries the same set, with customizations of its own
        $keyboardsetting = ['inline_keyboard' => svc_action_rows('mine', $nameloc, $marzban, $DataUserOut['status'], (string) ($DataUserOut['subscription_url'] ?? ''), $user, $textbotlang)];
        // 🔁 تمدید خودکار (autorenew.php), where this language has it on
        if (function_exists('ar_service_row') && ($ar_row = ar_service_row($nameloc, $marzban, $user)) !== null) {
            $keyboardsetting['inline_keyboard'][] = $ar_row;
        }
        $keyboardsetting['inline_keyboard'][] = [['text' => $textbotlang['users']['status']['backlist'], 'callback_data' => 'backorder', 'style' => 'danger']];
        $keyboardsetting = json_encode($keyboardsetting);
        if ($DataUserOut['sub_updated_at'] !== null) {
            $textconnect = sprintf($textbotlang['users']['status']['connectionInfo'], $lastonline, $lastupdate, $DataUserOut['sub_last_user_agent']);
        } elseif ($marzban['type'] == "WGDashboard") {
            $textconnect = "";
        } else {
            $textconnect = strtr($textbotlang['users']['status']['lastOnline'], ['{lastonline}' => $lastonline]);
        }
        // The location split needs one extra call to the panel, and only three
        // panel types can answer it at all - panel_user_usage() says which, so
        // an unsupported panel simply renders without the block instead of
        // paying for a request that cannot work.
        $svc_lang = $user['lang'] ?? 'fa';
        $svc_usage = panel_user_usage($nameloc['Service_location'], $DataUserOut['username']);
        $svc_blocks = svc_status_blocks($DataUserOut, $svc_usage, $svc_lang, $textbotlang);
        // Both the old placeholders and the new ones are filled: an admin who
        // has already written their own template keeps working exactly as
        // before, and the new blocks are there the moment they want them.
        // A screen with a subscription QR is a photo, and a photo caption stops
        // at 1024 characters; without one it is a plain message and gets 4096.
        // svc_build_caption() trims the location list to whichever applies, so a
        // shop with many nodes still gets a screen instead of nothing.
        $svc_cap_limit = (strpos((string) ($DataUserOut['subscription_url'] ?? ''), 'http') === 0) ? 1024 : 4096;
        $textinfo = svc_build_caption(bottext_resolve_key('users.status.infoFull'), [
            '{status}' => $status_var,
            '{username}' => $DataUserOut['username'],
            '{password_line}' => $userpassword,
            '{note_line}' => $nameconfig,
            '{location}' => $nameloc['Service_location'],
            '{product}' => $nameloc['name_product'],
            '{traffic}' => $svc_blocks['total_text'],
            '{used}' => $svc_blocks['used_text'],
            '{remaining}' => $RemainingVolume,
            '{percent}' => $Percent,
            '{expiration}' => svc_expire_text($DataUserOut['expire'] ?? 0, $svc_lang, $textbotlang),
            '{days}' => $day,
            '{connection_info}' => $textconnect,
            '{usage_bar}' => $svc_blocks['usage_bar'],
            '{usage_line}' => $svc_blocks['usage_line'],
            '{online_block}' => $svc_blocks['online_block'],
        ], (array) ($svc_usage['nodes'] ?? []), $textbotlang, $svc_cap_limit);
    }
    // the screen carries the subscription QR now; svc_send_status_screen()
    // edits the caption in place when it can and only re-sends when the
    // previous message was still text (see its own note)
    $svc_sub_url = (string) ($DataUserOut['subscription_url'] ?? '');
    // Only "♻️ بروزرسانی اطلاعات" edits the screen where it stands - that is the
    // whole point of a refresh, and the message must not jump to the bottom of
    // the chat for it. Every other way in (the service list, a back button from
    // تغییر لینک / لینک اشتراک / گزارش مصرف) is arriving from a DIFFERENT screen,
    // so that screen is taken away and this one is sent fresh.
    $svc_refresh_in_place = strpos((string) $datain, 'updateproduct_') === 0;
    if ($user['step'] == "getuseragnetservice") {
        svc_send_status_screen($from_id, 0, $textinfo, $keyboardsetting, $svc_sub_url, true);
    } else {
        svc_send_status_screen($from_id, $message_id, $textinfo, $keyboardsetting, $svc_sub_url, !$svc_refresh_in_place);
    }
    step('home', $from_id);
    return;
} elseif (preg_match('/^subapps_([A-Za-z0-9]+)(_k)?$/', $datain, $dataget)) {
    // 📲 افزودن به برنامه: the service's screen goes and the app list (one
    // for every kind of service) comes - each app a link to subapp.php, which opens it with the
    // subscription; ↪️ brings the service's screen back. From the purchase
    // message (_k) nothing goes: it carries the config, and the list follows it
    $nameloc = select("invoice", "*", "id_invoice", $dataget[1], "select");
    if (!is_array($nameloc) || !in_array($nameloc['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'], true)) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => $textbotlang['users']['subapps']['noLink'], 'show_alert' => true]);
        return;
    }
    [$sa_text, $sa_kb] = subapp_list_screen($user['lang'] ?? 'fa', $textbotlang, $nameloc);
    if (empty($dataget[2])) {
        deletemessage($from_id, $message_id);
    }
    bottext_extras_key_hint(genbtn_alias_to_key('kb'));
    sendmessage($from_id, $sa_text, $sa_kb, 'HTML');
    return;
} elseif (preg_match('/subscriptionurl_(\w+)/', $datain, $dataget) || strpos($text, "/sub ") !== false) {
    if (!empty($text) && $text[0] == "/") {
        $id_invoice = explode(' ', $text)[1];
        $nameloc = select("invoice", "*", "username", $id_invoice, "select");
        if ($nameloc['id_user'] != $from_id) {
            $nameloc = false;
        }
    } else {
        $id_invoice = $dataget[1];
        $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    }
    if ($nameloc == false)
        return;
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['status']['error'], null, 'html');
        return;
    }
    $subscriptionurl = $DataUserOut['subscription_url'];
    // both captions and the back button below are customizable via 🎨
    // شخصی‌سازی پیام‌های ربات -> 🛍 پیام‌های سرویس‌های من, matching the same
    // treatment given to changelink/transfer in the same group
    $linksubBack = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['status']['backToPreviousMenuBtn'], 'callback_data' => "productcheckdata", 'style' => 'danger'],
            ]
        ]
    ]);
    // the previous screen (service detail/status) can't be turned into a
    // photo/document message via Editmessagetext, so delete it outright -
    // tapping the back button above goes to "productcheckdata", which already
    // rebuilds that exact same detail screen fresh (same caption/buttons)
    deletemessage($from_id, $message_id);
    if ($marzban_list_get['type'] == "WGDashboard") {
        $textsub = $textbotlang['users']['status']['subscriptionFile'];
        $bakinfos = $linksubBack;
        update("user", "Processing_value", $nameloc['username'], "id", $from_id);
        $subscriptionurl = $DataUserOut['subscription_url'];
        $urlimage = "{$marzban_list_get['inboundid']}_{$nameloc['username']}.conf";
        file_put_contents($urlimage, $subscriptionurl);
        telegram('senddocument', [
            'chat_id' => $from_id,
            'document' => new CURLFile($urlimage),
            'reply_markup' => $bakinfos,
            'caption' => $textsub,
            'parse_mode' => "HTML",
        ]);
        unlink($urlimage);
    } else {
        $textsub = strtr($textbotlang['users']['status']['linksubCaption'], ['{link}' => $subscriptionurl]);
        $bakinfos = $linksubBack;
        update("user", "Processing_value", $nameloc['username'], "id", $from_id);
        $subscriptionurl = $DataUserOut['subscription_url'];
        $randomString = bin2hex(random_bytes(3));
        $urlimage = "$from_id$randomString.png";
        $qrCode = createqrcode($subscriptionurl);
        file_put_contents($urlimage, $qrCode->getString());
        addBackgroundImage($urlimage, $qrCode, 'images.jpg');
        telegram('sendphoto', [
            'chat_id' => $from_id,
            'photo' => new CURLFile($urlimage),
            'reply_markup' => $bakinfos,
            'caption' => $textsub,
            'parse_mode' => "HTML",
        ]);
        unlink($urlimage);
    }
} elseif (preg_match('/removeauto-(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $ManagePanel->RemoveUser($nameloc['Service_location'], $nameloc['username']);
    update('invoice', 'status', 'removebyuser', 'id_invoice', $id_invoice);
    $tetremove = sprintf($textbotlang['Admin']['reportgroup']['userDeletedService'], $nameloc['username']);
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $tetremove,
            'parse_mode' => "HTML"
        ]);
    }
    sendmessage($from_id, $textbotlang['users']['status']['deletedSuccess'], null, 'html');
} elseif (preg_match('/config_(\w+)/', $datain, $dataget) || strpos($text, "/link ") !== false) {
    if (!empty($text) && $text[0] == "/") {
        $id_invoice = explode(' ', $text)[1];
        $nameloc = select("invoice", "*", "username", $id_invoice, "select");
        if ($nameloc['id_user'] != $from_id) {
            $nameloc = false;
        }
    } else {
        $id_invoice = $dataget[1];
        $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    }
    if ($nameloc == false) {
        sendmessage($from_id, $textbotlang['users']['status']['userNotFound'], null, 'html');
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['status']['error'], null, 'html');
        return;
    }
    if (!is_array($DataUserOut['links'])) {
        sendmessage($from_id, $textbotlang['users']['status']['configReadError'], null, 'html');
        return;
    }
    // usertest and a real purchase both flow through this same dispatcher -
    // the my-services admin asked for these to have fully independent
    // caption/column/button settings, so branch on the same test-account
    // sentinel used elsewhere in this file (e.g. the notify_test_expired
    // check above) rather than adding a second code path
    $cc_isTest = ($nameloc['name_product'] === $textbotlang['Admin']['adminphp']['db_test_service_name']);
    $cc_kind = $cc_isTest ? 'usertest' : 'buy';
    if ($cc_isTest) {
        $cc_hintText = strtr($textbotlang['users']['status']['getConfigHint'], [
            '{testtime}' => $nameloc['Service_time'] ?? '',
            '{testvolume}' => $nameloc['Volume'] ?? '',
        ]);
    } else {
        $cc_hintText = strtr($textbotlang['users']['status']['getConfigHintBuy'], [
            '{time}' => $nameloc['Service_time'] ?? '',
            '{volume}' => $nameloc['Volume'] ?? '',
        ]);
    }
    Editmessagetext($from_id, $message_id, $cc_hintText, keyboard_config($DataUserOut['links'], $nameloc['id_invoice'], true, $cc_kind));
} elseif (preg_match('/configget_(.*)_(.*)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    if ($nameloc == false) {
        sendmessage($from_id, $textbotlang['users']['status']['userNotFound'], null, 'html');
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['status']['backinfo'], 'callback_data' => "productcheckdata"],
            ]
        ]
    ]);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['status']['error'], null, 'html');
        return;
    }
    $config = "";
    if ($dataget[2] == "1520") {
        for ($i = 0; $i < count($DataUserOut['links']); ++$i) {
            $randomString = bin2hex(random_bytes(3));
            $urlimage = "$from_id$randomString.png";
            $qrCode = createqrcode($DataUserOut['links'][$i]);
            file_put_contents($urlimage, $qrCode->getString());
            addBackgroundImage($urlimage, $qrCode, 'images.jpg');
            telegram('sendphoto', [
                'chat_id' => $from_id,
                'photo' => new CURLFile($urlimage),
                'caption' => "<code>{$DataUserOut['links'][$i]}</code>",
                'parse_mode' => "HTML",
            ]);
            unlink($urlimage);
        }
        return;
    }
    $randomString = bin2hex(random_bytes(3));
    $urlimage = "$from_id$randomString.png";
    $qrCode = createqrcode($DataUserOut['links'][$dataget[2]]);
    file_put_contents($urlimage, $qrCode->getString());
    addBackgroundImage($urlimage, $qrCode, 'images.jpg');
    telegram('sendphoto', [
        'chat_id' => $from_id,
        'photo' => new CURLFile($urlimage),
        'caption' => "<code>{$DataUserOut['links'][$dataget[2]]}</code>",
        'parse_mode' => "HTML",
    ]);
    unlink($urlimage);
} elseif (preg_match('/confirmaccountdisable_(\w+)/', $datain, $dataget)) {
    // 💡 itself no longer asks (it is handled before this chain); this answers
    // the confirm button of a message sent before that change
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    // 🚪 a free config paused because invitees left its channel: only
    // inviting again (or them coming back) turns it on
    $rw_missing = affrw_paused_missing($nameloc);
    if ($rw_missing !== null) {
        bottext_extras_key_hint('users.affiliates.rewardPausedLocked');
        sendmessage($from_id, strtr($textbotlang['users']['affiliates']['rewardPausedLocked'], ['{missing}' => $rw_missing]), null, 'html');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['status']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    $dataoutput = $ManagePanel->Change_status($nameloc['username'], $nameloc['Service_location']);
    if ($dataoutput['status'] == "Unsuccessful") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['status']['notchanged'], $bakinfos);
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "active") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['status']['activedconfig'], $bakinfos);
    } else {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['status']['disabledconfig'], $bakinfos);
    }
} elseif (preg_match('/extend_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    if ($nameloc == false) {
        sendmessage($from_id, $textbotlang['users']['extend']['error'], null, 'HTML');
        return;
    }
    // 🎁 a free config its tab does not let be renewed (an old button)
    if (affrw_renew_blocked($nameloc)) {
        bottext_extras_key_hint('users.affiliates.rewardNoRenew');
        sendmessage($from_id, $textbotlang['users']['affiliates']['rewardNoRenew'], null, 'HTML');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($marzban_list_get['status_extend'] == "off_extend") {
        sendmessage($from_id, $textbotlang['users']['extend']['notSupportedPanel'], null, 'html');
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['status']['error'], null, 'html');
        return;
    }
    if ($DataUserOut['status'] == "on_hold") {
        sendmessage($from_id, $textbotlang['users']['extend']['connectFirst'], null, 'html');
        return;
    }
    $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location = :service_location OR Location = '/all') AND agent = :agent AND one_buy_status = '0' AND (FIND_IN_SET(:userlang, lang) OR lang = 'all' OR lang IS NULL OR lang = '')");
    $stmt->execute([
        ':service_location' => $marzban_list_get['name_panel'],
        ':agent' => $user['agent'],
        ':userlang' => $user['lang'] ?? 'fa',
    ]);
    $product = $stmt->rowCount();
    savedata("clear", "id_invoice", $nameloc['id_invoice']);
    // 🧩 a service bought as a custom plan renews as one, and so does any
    // service on a panel with no ready plans - when the panel offers them
    if ($product == 0 || cvol_invoice_is_custom($nameloc)) {
        if (cvol_open($user, $marzban_list_get, 'r', 'product_' . $nameloc['id_invoice'], $message_id, $nameloc['id_invoice'])) {
            return;
        }
        if ($product == 0) {
            sendmessage($from_id, $textbotlang['users']['sell']['nullProduct'], null, 'HTML');
            step('home', $from_id);
            return;
        }
    }
    if (shop_feature_value('categorytime', $user['lang'] ?? 'fa', $setting['statuscategory']) == "offcategory") {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location = :service_location OR Location = '/all') AND agent = :agent AND one_buy_status = '0' AND (FIND_IN_SET(:userlang, lang) OR lang = 'all' OR lang IS NULL OR lang = '')");
        $stmt->execute([
            ':service_location' => $nameloc['Service_location'],
            ':agent' => $user['agent'],
            ':userlang' => $user['lang'] ?? 'fa',
        ]);
        $productextend = ['inline_keyboard' => []];
        $statusshowprice = shop_feature_value('showprice', $user['lang'] ?? 'fa', select("shopSetting", "*", "Namevalue", "statusshowprice", "select")['value']);
        while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $hide_panel = json_decode($result['hide_panel'], true);
            if (in_array($nameloc['Service_location'], $hide_panel))
                continue;
            if (intval($user['pricediscount']) != 0) {
                $resultper = ($result['price_product'] * $user['pricediscount']) / 100;
                $result['price_product'] = $result['price_product'] - $resultper;
            }
            if ($statusshowprice == "offshowprice") {
                $namekeyboard = $result['name_product'];
            } else {
                $result['price_product'] = number_format($result['price_product']);
                $namekeyboard = $result['name_product'] . " - " . $result['price_product'] . $textbotlang['common']['labels']['tomanUnit'];
            }
            $productextend['inline_keyboard'][] = [
                ['text' => $namekeyboard, 'callback_data' => "serviceextendselect_" . $result['code_product'], 'style' => 'primary']
            ];
        }
        // 🧩 renewing with a custom plan, when this service's panel offers them
        if (cvol_available($marzban_list_get, $user) && ($pe_cv = cvol_entry_button($user['lang'] ?? 'fa', 'cvs|r|' . $nameloc['id_invoice']))) {
            $productextend['inline_keyboard'][] = [$pe_cv];
        }
        $productextend['inline_keyboard'][] = [
            ['text' => $textbotlang['keyboard']['renewCurrentPlan'], 'callback_data' => "exntedagei", 'style' => 'primary']
        ];
        $productextend['inline_keyboard'][] = [
            ['text' => $textbotlang['keyboard']['backToServiceInfo'], 'callback_data' => "product_" . $nameloc['id_invoice'], 'style' => 'danger']
        ];

        $json_list_product_lists = json_encode($productextend);
        Editmessagetext($from_id, $message_id, $textbotlang['users']['extend']['selectservice'], $json_list_product_lists);
    } else {
        $monthkeyboard = keyboardTimeCategory($nameloc['Service_location'], $user['agent'], "productextendmonths_", "product_$id_invoice", false, true);
        Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['selectDuration'], $monthkeyboard);
    }
} elseif (preg_match('/productextendmonths_(\w+)/', $datain, $dataget)) {
    $monthenumber = $dataget[1];
    $userdate = json_decode($user['Processing_value'], true);
    $nameloc = select("invoice", "*", "id_invoice", $userdate['id_invoice'], "select");
    $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location = :service_location OR Location = '/all') AND agent = :agent AND Service_time = :monthe AND one_buy_status = '0' AND (FIND_IN_SET(:userlang, lang) OR lang = 'all' OR lang IS NULL OR lang = '')");
    $stmt->execute([
        ':service_location' => $nameloc['Service_location'],
        ':agent' => $user['agent'],
        'monthe' => $monthenumber,
        ':userlang' => $user['lang'] ?? 'fa',
    ]);
    $productextend = ['inline_keyboard' => []];
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $statusshowprice = shop_feature_value('showprice', $user['lang'] ?? 'fa', select("shopSetting", "*", "Namevalue", "statusshowprice", "select")['value']);
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (intval($user['pricediscount']) != 0) {
            $resultper = ($result['price_product'] * $user['pricediscount']) / 100;
            $result['price_product'] = $result['price_product'] - $resultper;
        }
        if ($statusshowprice == "offshowprice") {
            $namekeyboard = $result['name_product'];
        } else {
            $result['price_product'] = number_format($result['price_product']);
            $namekeyboard = $result['name_product'] . " - " . $result['price_product'] . $textbotlang['common']['labels']['tomanUnit'];
        }
        $productextend['inline_keyboard'][] = [
            ['text' => $namekeyboard, 'callback_data' => "serviceextendselect_" . $result['code_product'], 'style' => 'primary']
        ];
    }
    // 🧩 renewing with a custom plan, when this service's panel offers them
    if (cvol_available($marzban_list_get, $user) && ($pe_cv = cvol_entry_button($user['lang'] ?? 'fa', 'cvs|r|' . $nameloc['id_invoice']))) {
        $productextend['inline_keyboard'][] = [$pe_cv];
    }
    $productextend['inline_keyboard'][] = [
        ['text' => $textbotlang['keyboard']['backToServiceInfo'], 'callback_data' => "product_" . $nameloc['id_invoice'], 'style' => 'danger']
    ];

    $json_list_product_lists = json_encode($productextend);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['extend']['selectservice'], $json_list_product_lists);
} elseif (preg_match('/^serviceextendselect_(.*)/', $datain, $dataget) || $datain == "exntedagei") {
    $userdate = json_decode($user['Processing_value'], true);
    $nameloc = select("invoice", "*", "id_invoice", $userdate['id_invoice'], "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($datain == "exntedagei") {
        $stmt = $pdo->prepare("SELECT value FROM service_other WHERE username = :username AND type = 'extend_user' AND status = 'paid' ORDER BY time DESC");
        $stmt->execute([
            ':username' => $nameloc['username'],
        ]);
        if ($stmt->rowCount() == 0) {
            $codeproduct = select("product", "*", "name_product", $nameloc['name_product']);
        } else {
            $service_other = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($service_other == false || !(is_string($service_other['value']) && is_array(json_decode($service_other['value'], true)))) {
                sendmessage($from_id, $textbotlang['users']['extend']['planNotAvailable'], $keyboard, 'HTML');
                return;
            }
            $service_other = json_decode($service_other['value'], true);
            $codeproduct = select("product", "code_product", "code_product", $service_other['code_product'], "select");
        }
        if ($codeproduct == false) {
            sendmessage($from_id, $textbotlang['users']['extend']['planNotAvailable'], $keyboard, 'HTML');
            return;
        }
        $codeproduct = $codeproduct['code_product'];
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    } else {
        $codeproduct = $dataget[1];
    }
    $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location = :service_location OR Location = '/all') AND agent = :agent AND code_product = :code_product AND (FIND_IN_SET(:userlang, lang) OR lang = 'all' OR lang IS NULL OR lang = '')");
    $stmt->execute([
        ':service_location' => $nameloc['Service_location'],
        ':agent' => $user['agent'],
        ':code_product' => $codeproduct,
        ':userlang' => $user['lang'] ?? 'fa',
    ]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($product == false) {
        sendmessage($from_id, $textbotlang['users']['extend']['restartError'], $keyboard, 'HTML');
        return;
    }
    savedata("save", "time", $product['Service_time']);
    savedata("save", "data_limit", $product['Volume_constraint']);
    savedata("save", "price_product", $product['price_product']);
    savedata("save", "code_product", $product['code_product']);
    list($textextend, $keyboardextend) = render_extend_invoice_from_product($user, $textbotlang, $nameloc, $product);
    Editmessagetext($from_id, $message_id, $textextend, $keyboardextend);
} elseif ($datain == "confirmserivce") {
    $partsdic = explode("_", $user['Processing_value_four']);
    $userdata = json_decode($user['Processing_value'], true);
    $id_invoice = $userdata['id_invoice'];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    if ($nameloc == false) {
        sendmessage($from_id, $textbotlang['users']['extend']['error'], null, 'HTML');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($marzban_list_get['status_extend'] == "off_extend") {
        sendmessage($from_id, $textbotlang['users']['extend']['notSupportedPanel'], null, 'html');
        return;
    }
    // 🧩 renewed with a custom plan: priced by the panel's settings as they are now
    if (cvol_is_renew_code($userdata['code_product'] ?? '')) {
        $prodcut = cvol_renew_product($marzban_list_get, $user, $nameloc, $userdata['data_limit'] ?? 0, $userdata['time'] ?? 0);
        if ($prodcut === null) {
            sendmessage($from_id, cvol_tx($user['lang'] ?? 'fa')['unavailable'], null, 'HTML');
            return;
        }
    } else {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location = :service_location OR Location = '/all') AND agent = :agent AND code_product = :code_product AND (FIND_IN_SET(:userlang, lang) OR lang = 'all' OR lang IS NULL OR lang = '')");
        $stmt->execute([
            ':service_location' => $nameloc['Service_location'],
            ':agent' => $user['agent'],
            ':code_product' => $userdata['code_product'],
            ':userlang' => $user['lang'] ?? 'fa',
        ]);
        $prodcut = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    $pricelastextend = $prodcut['price_product'];
    if ($prodcut == false || !in_array($nameloc['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'])) {
        sendmessage($from_id, $textbotlang['users']['extend']['error'], null, 'HTML');
        return;
    }
    if (intval($user['pricediscount']) != 0) {
        $result = ($pricelastextend * $user['pricediscount']) / 100;
        $pricelastextend = $pricelastextend - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }
    if ($admin_buy_free) {
        $pricelastextend = 0;
    }
    if ($user['Balance'] < $pricelastextend && $user['agent'] != "n2" && intval($pricelastextend) != 0) {
        $marzbandirectpay = shop_feature_value('paydirect', $user['lang'] ?? 'fa', select('shopSetting', "*", "Namevalue", "statusdirectpabuy", "select")['value']);
        if ($marzbandirectpay == "offdirectbuy") {
            $minbalance = json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']];
            $maxbalance = json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']];
            $minbalance = number_format($minbalance);
            $maxbalance = number_format($maxbalance);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['status']['backinfo'], 'callback_data' => "account"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['Balance']['insufficientbalance'], $minbalance, $maxbalance), $bakinfos, 'HTML');
            step('getprice', $from_id);
            return;
        } else {
            // invoice stays exactly as it is - just a popup nudge toward
            // the "افزایش موجودی" button already on that same invoice.
            // No Processing_value/service_other writes here anymore; those
            // only happen once the user actually taps that button (see the
            // rn_topup_ dispatcher), so a repeated confirm-tap never creates
            // duplicate pending renewal orders.
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => $textbotlang['users']['extend']['insufficientBalanceAlert'],
                'show_alert' => true,
            ]);
            return;
        }
    }
    Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));
    $randomString = bin2hex(random_bytes(2));
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
        if (($user['Balance'] - $pricelastextend) < intval("-" . $user['maxbuyagent'])) {
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }
    if ($nameloc['name_product'] == $textbotlang['common']['labels']['testService2']) {
        update("invoice", "name_product", $prodcut['name_product'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "price_product", $prodcut['price_product'], "id_invoice", $nameloc['id_invoice']);
    }
    $extend = $ManagePanel->extend($marzban_list_get['Methodextend'], $prodcut['Volume_constraint'], $prodcut['Service_time'], $nameloc['username'], $prodcut['code_product'], $marzban_list_get['code_panel']);
    affrw_mark_renewed($extend, $nameloc['username'], $marzban_list_get['name_panel']);
    if ($extend['status'] == false) {
        $extend['msg'] = json_encode($extend['msg']);
        $textreports = sprintf($textbotlang['Admin']['reportgroup']['errorRenewService'], $marzban_list_get['name_panel'], $nameloc['username'], $extend['msg']);
        sendmessage($from_id, $textbotlang['users']['extend']['errorSupport'], null, 'HTML');
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $textreports,
                'parse_mode' => "HTML"
            ]);
        }
        return;
    }
    if ($user['agent'] == "f") {
        $valurcashbackextend = select("shopSetting", "*", "Namevalue", "chashbackextend", "select")['value'];
    } else {
        $valurcashbackextend = json_decode(select("shopSetting", "*", "Namevalue", "chashbackextend_agent", "select")['value'], true)[$user['agent']];
    }
    if (intval($valurcashbackextend) != 0 and intval($pricelastextend) != 0) {
        $result = ($prodcut['price_product'] * $valurcashbackextend) / 100;
        $pricelastextend = $pricelastextend - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['extend']['giftCharged'], $result), null, 'HTML');
    }
    $Balance_Low_user = $user['Balance'] - $pricelastextend;
    balance_add($from_id, -$pricelastextend);
    $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username,value,type,time,price,output,status) VALUES (?, ?, ?, ?,?,?,?,?)");
    $dateacc = date('Y/m/d H:i:s');
    $value = json_encode(array(
        "volumebuy" => $prodcut['Volume_constraint'],
        "Service_time" => $prodcut['Service_time'],
        "oldvolume" => $DataUserOut['data_limit'],
        "oldtime" => $DataUserOut['expire'],
        'code_product' => $prodcut['code_product'],
        'id_order' => $randomString
    ));
    $type = "extend_user";
    $status = "paid";
    $extend_json = json_encode($extend);
    $stmt->execute([$from_id, $nameloc['username'], $value, $type, $dateacc, $prodcut['price_product'], $extend_json, $status]);
    update("invoice", "Status", "active", "id_invoice", $id_invoice);
    if (score_on($user['lang'] ?? 'fa', $setting) and !in_array($from_id, $admin_ids)) {
        sendmessage($from_id, $textbotlang['users']['affiliates']['pointsEarned2Alt'], null, 'html');
        $scorenew = $user['score'] + 2;
        update("user", "score", $scorenew, "id", $from_id);
    }
    $keyboardextendfnished = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['status']['backlist'], 'callback_data' => "backorder"],
            ],
            [
                ['text' => $textbotlang['users']['status']['backservice'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    $priceproductformat = number_format($pricelastextend);
    $balanceformatsell = money(select("user", "Balance", "id", $from_id, "select")['Balance'], currency_for_user($user), false);
    $balanceformatsellbefore = wallet_amount_text($user);
    $textextend = sprintf($textbotlang['users']['extend']['success'], $nameloc['username'], $prodcut['name_product'], $priceproductformat);
    sendmessage($from_id, $textextend, $keyboardextendfnished, 'HTML');
    $timejalali = jdate('Y/m/d H:i:s');
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['manageUser']['manageUserBtn'], 'callback_data' => 'manageuser_' . $from_id],
            ],
        ]
    ]);
    $text_report = sprintf($textbotlang['Admin']['reportgroup']['renewed'], $from_id, $username, $nameloc['username'], $first_name, $nameloc['Service_location'], $prodcut['name_product'], $prodcut['Volume_constraint'], $prodcut['Service_time'], $prodcut['price_product'], $balanceformatsellbefore, $balanceformatsell, $timejalali);
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $text_report,
            'parse_mode' => "HTML",
            'reply_markup' => $Response
        ]);
    }
} elseif (preg_match('/^rn_(topup|direct)_(\w+)/', $datain, $dataget)) {
    // 💳 rn_direct_: the same, for the whole price and paid by a gateway (directpay.php)
    $rn_direct = ($dataget[1] === 'direct');
    if ($rn_direct && !dpay_path_on($user['lang'] ?? 'fa', 'renew')) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => strip_tags(sf_text('directPayGone', $user['lang'] ?? 'fa')), 'show_alert' => true]);
        return;
    }
    // reached by tapping افزایش موجودی on the renewal invoice - owns
    // everything the insufficient-balance branch of confirmserivce used to
    // do (state-prep + pending order + show the payment-method screen),
    // just moved here so a mere confirm-tap no longer creates it
    $id_invoice = $dataget[2];
    $userdata = json_decode($user['Processing_value'], true);
    if (!is_array($userdata) || ($userdata['id_invoice'] ?? null) != $id_invoice) {
        sendmessage($from_id, $textbotlang['users']['extend']['restartError'], $keyboard, 'HTML');
        return;
    }
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    if ($nameloc == false) {
        sendmessage($from_id, $textbotlang['users']['extend']['error'], null, 'HTML');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($marzban_list_get['status_extend'] == "off_extend") {
        sendmessage($from_id, $textbotlang['users']['extend']['notSupportedPanel'], null, 'html');
        return;
    }
    $randomString = bin2hex(random_bytes(2));
    // 🧩 renewed with a custom plan: priced by the panel's settings as they are now
    if (cvol_is_renew_code($userdata['code_product'] ?? '')) {
        $prodcut = cvol_renew_product($marzban_list_get, $user, $nameloc, $userdata['data_limit'] ?? 0, $userdata['time'] ?? 0);
        if ($prodcut === null) {
            sendmessage($from_id, cvol_tx($user['lang'] ?? 'fa')['unavailable'], null, 'HTML');
            return;
        }
    } else {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location = :service_location OR Location = '/all') AND agent = :agent AND code_product = :code_product AND (FIND_IN_SET(:userlang, lang) OR lang = 'all' OR lang IS NULL OR lang = '')");
        $stmt->execute([
            ':service_location' => $nameloc['Service_location'],
            ':agent' => $user['agent'],
            ':code_product' => $userdata['code_product'],
            ':userlang' => $user['lang'] ?? 'fa',
        ]);
        $prodcut = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if ($prodcut == false || !in_array($nameloc['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'])) {
        sendmessage($from_id, $textbotlang['users']['extend']['error'], null, 'HTML');
        return;
    }
    $pricelastextend = $prodcut['price_product'];
    if (intval($user['pricediscount']) != 0) {
        $result = ($pricelastextend * $user['pricediscount']) / 100;
        $pricelastextend = $pricelastextend - $result;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    // Processing_value is about to be overwritten with the plain top-up
    // shortfall amount (needed by the shared payment-completion flow) -
    // that destroys the {id_invoice,time,data_limit,price_product,
    // code_product} blob rn_reshow_ needs to rebuild this exact invoice, so
    // stash the resume context in the dedicated renew_resume_ctx column first
    // (id_invoice itself already travels in the rn_reshow_ callback).
    // NOTE: this used to reuse Processing_value_four, but that column is ALSO
    // used elsewhere (e.g. caching the current panel name for the "تغییر
    // لوکیشن" picker) - a real collision, so this now has its own column.
    update("user", "renew_resume_ctx", json_encode([
        'code_product' => $userdata['code_product'] ?? null,
        'data_limit' => $userdata['data_limit'] ?? null,
        'time' => $userdata['time'] ?? null,
    ]), "id", $from_id);
    $Balance_prim = $rn_direct ? $pricelastextend : $pricelastextend - $user['Balance'];
    update("user", "Processing_value", $Balance_prim, "id", $from_id);
    // 💰 what it lacks, for the amount screens' quote and for the payment to finish the renewal (shortfall.php);
    // a direct payment pays the whole price and lacks nothing
    sf_ctx_save($from_id, ['t' => $rn_direct ? 'direct' : 'renew', 'need' => sf_round($Balance_prim, $user), 'price' => $pricelastextend, 'inv' => $id_invoice, 'at' => time()]);
    // decode the shared step_payment as a LOCAL copy only - never touch how
    // $step_payment/$noCreditText themselves get built (keyboard.php), so
    // every other call site of that shared picker keeps its own unmodified
    // copy. Drop the shared close ("❌ بستن") row and add our own red
    // back-to-invoice button instead.
    // a gateway whose own floor is above this renewal's amount is left out
    $extendPaymentKb = json_decode(step_payment_for_amount($step_payment, $Balance_prim, $user['lang'] ?? 'fa'), true);
    if (is_array($extendPaymentKb) && isset($extendPaymentKb['inline_keyboard'])) {
        $extendPaymentKb['inline_keyboard'] = array_values(array_filter($extendPaymentKb['inline_keyboard'], function ($row) {
            foreach ($row as $btn) {
                if (($btn['callback_data'] ?? '') === 'colselist') {
                    return false;
                }
            }
            return true;
        }));
        $rnBackDef = genbtn_defs('rn', $textbotlang)[2];
        $rnBackOv = genbtn_override($user['lang'] ?? 'fa', 'users.extend.invoiceCreated', 2);
        $extendPaymentKb['inline_keyboard'][] = [genbtn_render($rnBackDef, $rnBackOv, "rn_reshow_" . $id_invoice)];
        $extendPaymentKbJson = json_encode($extendPaymentKb);
    } else {
        $extendPaymentKbJson = step_payment_for_amount($step_payment, $Balance_prim, $user['lang'] ?? 'fa');
    }
    if ($rn_direct) {
        Editmessagetext($from_id, $message_id, dpay_methods_view($user, $Balance_prim, 'rn_reshow_' . $id_invoice)[0], $extendPaymentKbJson, 'HTML');
    } else {
        Editmessagetext($from_id, $message_id, topup_disc_method_caption($from_id, $user['lang'] ?? 'fa', $textbotlang), $extendPaymentKbJson);
    }
    step('get_step_payment', $from_id);
    $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username,value,type,time,price,output,status) VALUES (?, ?,?, ?, ?,?,?,?)");
    $dateacc = date('Y/m/d H:i:s');
    $value = json_encode(array(
        "volumebuy" => $prodcut['Volume_constraint'],
        "Service_time" => $prodcut['Service_time'],
        "oldvolume" => $DataUserOut['data_limit'],
        "oldtime" => $DataUserOut['expire'],
        'code_product' => $prodcut['code_product'],
        'id_order' => $randomString
    ) + ($rn_direct ? ['direct' => 1] : []));
    $type = "extend_user";
    $status = "unpaid";
    $extend = '';
    $stmt->execute([$from_id, $nameloc['username'], $value, $type, $dateacc, $prodcut['price_product'], $extend, $status]);
    update("user", "Processing_value_one", "{$nameloc['username']}%$randomString", "id", $from_id);
    update("user", "Processing_value_tow", "getextenduser", "id", $from_id);
} elseif (preg_match('/^rn_reshow_(\w+)/', $datain, $dataget)) {
    // reached from the red back button on the payment-method screen above.
    // Processing_value no longer holds this renewal's blob at this point -
    // rn_topup_ overwrote it with the top-up shortfall amount - so read the
    // resume context rn_topup_ stashed in renew_resume_ctx instead
    // (id_invoice itself travels in this very callback).
    $id_invoice = $dataget[1];
    $renewCtx = json_decode($user['renew_resume_ctx'], true);
    if (!is_array($renewCtx) || empty($renewCtx['code_product'])) {
        sendmessage($from_id, $textbotlang['users']['extend']['restartError'], $keyboard, 'HTML');
        return;
    }
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    if ($nameloc == false) {
        sendmessage($from_id, $textbotlang['users']['extend']['error'], null, 'HTML');
        return;
    }
    if (cvol_is_renew_code($renewCtx['code_product'])) {
        $product = cvol_renew_product($nameloc['Service_location'], $user, $nameloc, $renewCtx['data_limit'] ?? 0, $renewCtx['time'] ?? 0, 'customvolume');
        if ($product === null) {
            sendmessage($from_id, cvol_tx($user['lang'] ?? 'fa')['unavailable'], null, 'HTML');
            return;
        }
    } else {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location = :service_location OR Location = '/all') AND agent = :agent AND code_product = :code_product AND (FIND_IN_SET(:userlang, lang) OR lang = 'all' OR lang IS NULL OR lang = '')");
        $stmt->execute([
            ':service_location' => $nameloc['Service_location'],
            ':agent' => $user['agent'],
            ':code_product' => $renewCtx['code_product'],
            ':userlang' => $user['lang'] ?? 'fa',
        ]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($product == false) {
            sendmessage($from_id, $textbotlang['users']['extend']['restartError'], $keyboard, 'HTML');
            return;
        }
    }
    // restore Processing_value to the same {id_invoice,time,data_limit,
    // price_product,code_product} shape the invoice originally had, so a
    // subsequent تایید تمدید tap on this re-shown invoice resolves correctly
    // again instead of tripping over the leftover shortfall number
    savedata("clear", "id_invoice", $id_invoice);
    savedata("save", "time", $product['Service_time']);
    savedata("save", "data_limit", $product['Volume_constraint']);
    savedata("save", "price_product", $product['price_product']);
    savedata("save", "code_product", $product['code_product']);
    list($textextend, $keyboardextend) = render_extend_invoice_from_product($user, $textbotlang, $nameloc, $product);
    Editmessagetext($from_id, $message_id, $textextend, $keyboardextend);
} elseif (preg_match('/changelink_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['status']['error'], null, 'html');
        return;
    }
    if ($DataUserOut['status'] == "disabled" || $DataUserOut['status'] == "on_hold") {
        sendmessage($from_id, $textbotlang['users']['changeLink']['serviceInactive'], null, 'html');
        return;
    }
    // buttons customizable via 🎨 شخصی‌سازی پیام‌های ربات -> 🛍 پیام‌های
    // سرویس‌های من (genbtn alias 'cl'); defaults: confirm=blue, back=red
    $cl_defs = genbtn_defs('cl', $textbotlang);
    $cl_lang = $user['lang'] ?? 'fa';
    $cl_confirmBtn = genbtn_render($cl_defs[0], genbtn_override($cl_lang, 'users.changeLink.warnchange', 0), "confirmchange_" . $nameloc['id_invoice']);
    $cl_backBtn = genbtn_render($cl_defs[1], genbtn_override($cl_lang, 'users.changeLink.warnchange', 1), "product_" . $nameloc['id_invoice']);
    // order and width from 📐 چیدمان; with none saved, one row each as before
    $keyboardextend = json_encode([
        'inline_keyboard' => genbtn_group_rows('cl', $cl_lang, [0 => $cl_confirmBtn, 1 => $cl_backBtn], $textbotlang),
    ]);
    // The service screen is a QR photo, and a photo cannot be edited into a text
    // message - so take it away and send this one. Doing it explicitly rather
    // than leaning on Editmessagetext()'s fallback: that fallback exists to
    // catch the paths nobody thought about, and this is one we did think about.
    // Its back button is product_{id}, which rebuilds the service screen the
    // same way - fresh, with the old screen removed.
    deletemessage($from_id, $message_id);
    sendmessage($from_id, $textbotlang['users']['changeLink']['warnchange'], $keyboardextend, 'HTML');
} elseif (preg_match('/confirmchange_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->Revoke_sub($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['changeLink']['error'], null, 'HTML');
        return;
    }
    $textconfig = $textbotlang['users']['changeLink']['updated'];
    if ($marzban_list_get['sublink'] == "onsublink") {
        $output_config_link = $DataUserOut['subscription_url'];
        $textconfig .= strtr($textbotlang['users']['status']['subscriptionLine'], ['{output_config_link}' => $output_config_link]);
    }
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['status']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    if ($marzban_list_get['config'] == "onconfig") {
        Editmessagetext($from_id, $message_id, $textconfig, keyboard_config($DataUserOut['configs'], $nameloc['id_invoice'], true));
    } else {
        Editmessagetext($from_id, $message_id, $textconfig, $bakinfos);
    }
    $timejalali = jdate('Y/m/d H:i:s');
    $text_report = sprintf($textbotlang['Admin']['reportgroup']['linkChanged'], $from_id, $username, $nameloc['username'], $first_name, $marzban_list_get['name_panel'], $user['agent'], $timejalali);
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $text_report,
            'parse_mode' => "HTML",
        ]);
    }
} elseif (preg_match('/Extra_volume_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($marzban_list_get['status_extend'] == "off_extend") {
        sendmessage($from_id, $textbotlang['users']['extraVolume']['notSupportedPanel'], null, 'html');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['status']['error'], null, 'html');
        return;
    }
    $eextraprice = json_decode($marzban_list_get['priceextravolume'], true);
    $extrapricevalue = $eextraprice[$user['agent']];
    update("user", "Processing_value", $nameloc['id_invoice'], "id", $from_id);
    $textextra = sprintf($textbotlang['users']['extraVolume']['prompt'], $extrapricevalue);
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['status']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textextra, $bakinfos);
    step('getvolumeextra', $from_id);
} elseif ($user['step'] == "getvolumeextra") {
    $ev_gb = volume_parse($text, 'gb');
    if ($ev_gb === null) {
        sendmessage($from_id, $textbotlang['common']['invalidVolume'], $backuser, 'HTML');
        return;
    }
    $text = volume_store($ev_gb);
    $nameloc = select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $eextraprice = json_decode($marzban_list_get['priceextravolume'], true);
    $extrapricevalue = $eextraprice[$user['agent']];
    $priceextra = $extrapricevalue * $text;
    $keyboardsetting = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['extraVolume']['extracheck'], 'callback_data' => 'confirmaextra-' . $extrapricevalue * $text],
            ]
        ]
    ]);
    $priceextra = number_format($priceextra, 0);
    $extrapricevalues = number_format($extrapricevalue, 0);
    $textextra = sprintf($textbotlang['users']['extraVolume']['invoiceCreated'], $extrapricevalues, $text, $priceextra);
    sendmessage($from_id, $textextra, $keyboardsetting, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/confirmaextra-(\d+(?:\.\d+)?)/', $datain, $dataget)) {
    $volume = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
    if (!in_array($nameloc['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'])) {
        sendmessage($from_id, $textbotlang['users']['sell']['purchaseError'], null, 'HTML');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $eextraprice = json_decode($marzban_list_get['priceextravolume'], true);
    $extrapricevalue = $eextraprice[$user['agent']];
    if ($user['Balance'] < $volume && $user['agent'] != "n2" && !$admin_buy_free) {
        $marzbandirectpay = shop_feature_value('paydirect', $user['lang'] ?? 'fa', select('shopSetting', "*", "Namevalue", "statusdirectpabuy", "select")['value']);
        if ($marzbandirectpay == "offdirectbuy") {
            $minbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']]);
            $maxbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']]);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['status']['backinfo'], 'callback_data' => "account"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['Balance']['insufficientbalance'], $minbalance, $maxbalance), $bakinfos, 'HTML');
            step('getprice', $from_id);
            return;
        } else {
            $valuevolume = (float) $extrapricevalue > 0 ? (float) $volume / (float) $extrapricevalue : 0;
            if (intval($user['pricediscount']) != 0) {
                $result = ($volume * $user['pricediscount']) / 100;
                $volume = $volume - $result;
                sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
            }
            $Balance_prim = $volume - $user['Balance'];
            update("user", "Processing_value", $Balance_prim, "id", $from_id);
            Editmessagetext($from_id, $message_id, $noCreditText, step_payment_for_amount($step_payment, $Balance_prim, $user['lang'] ?? 'fa'));
            step('get_step_payment', $from_id);
            update("user", "Processing_value_one", "{$nameloc['username']}%{$valuevolume}", "id", $from_id);
            update("user", "Processing_value_tow", "getextravolumeuser", "id", $from_id);
            return;
        }
    }
    deletemessage($from_id, $message_id);
    $volumepricelast = $volume;
    if (intval($user['pricediscount']) != 0) {
        $result = ($volume * $user['pricediscount']) / 100;
        $volumepricelast = $volume - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }
    if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
        if (($user['Balance'] - $volumepricelast) < intval("-" . $user['maxbuyagent'])) {
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }
    // $volume is also what the GB amount is worked out from, so only the
    // charged price is zeroed for a free admin purchase
    if ($admin_buy_free) {
        $volumepricelast = 0;
    }
    $Balance_Low_user = $user['Balance'] - $volumepricelast;
    balance_add($from_id, -$volumepricelast);
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    $data_for_database = json_encode(array(
        'volume_value' => (float) $extrapricevalue > 0 ? (float) $volume / (float) $extrapricevalue : 0,
        'priceـper_gig' => $extrapricevalue,
        'old_volume' => $DataUserOut['data_limit'],
        'expire_old' => $DataUserOut['expire']
    ));
    $data_limit = (float) $extrapricevalue > 0 ? (float) $volume / (float) $extrapricevalue : 0;
    $extra_volume = $ManagePanel->extra_volume($nameloc['username'], $marzban_list_get['code_panel'], $data_limit);
    if ($extra_volume['status'] == false) {
        $extra_volume['msg'] = json_encode($extra_volume['msg']);
        $textreports = sprintf($textbotlang['Admin']['reportgroup']['errorExtraVolume'], $marzban_list_get['name_panel'], $nameloc['username'], $extra_volume['msg']);
        sendmessage($from_id, $textbotlang['users']['extraVolume']['serviceError'], null, 'HTML');
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $textreports,
                'parse_mode' => "HTML"
            ]);
        }
        return;
    }
    $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username, value, type, time, price, output) VALUES (:id_user, :username, :value, :type, :time, :price, :output)");
    $value = $data_for_database;
    $dateacc = date('Y/m/d H:i:s');
    $type = "extra_user";
    $stmt->execute([
        ':id_user' => $from_id,
        ':username' => $nameloc['username'],
        ':value' => $value,
        ':type' => $type,
        ':time' => $dateacc,
        ':price' => $volumepricelast,
        ':output' => json_encode($extra_volume),
    ]);
    $keyboardextrafnished = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['status']['backservice'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    if (score_on($user['lang'] ?? 'fa', $setting) and !in_array($from_id, $admin_ids)) {
        sendmessage($from_id, $textbotlang['users']['affiliates']['pointsEarned1Alt'], null, 'html');
        $scorenew = $user['score'] + 1;
        update("user", "score", $scorenew, "id", $from_id);
    }
    $volumesformat = number_format($volumepricelast, 0);
    $volumes = $volume / $extrapricevalue;
    $textvolume = sprintf($textbotlang['users']['extraVolume']['success'], $nameloc['username'], $volumes, $volumesformat);
    sendmessage($from_id, $textvolume, $keyboardextrafnished, 'HTML');
    $text_report = sprintf($textbotlang['Admin']['reportgroup']['extraVolume'], $from_id, $volumes, $volumesformat, $nameloc['username'], wallet_amount_text($user));
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/changeloc_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $limitchangeloc = json_decode($setting['limitnumber'], true);
    // per-language override of the two location-change limits, falling back to
    // the shared limitnumber values
    $limitchangeloc['all'] = feature_setting_value('loc_limit_all', $user['lang'] ?? 'fa', $limitchangeloc['all'] ?? 0);
    $limitchangeloc['free'] = feature_setting_value('loc_limit_free', $user['lang'] ?? 'fa', $limitchangeloc['free'] ?? 0);
    if ($user['limitchangeloc'] > $limitchangeloc['all'] and intval(feature_value('statuslimitchangeloc', $user['lang'] ?? 'fa', $setting['statuslimitchangeloc'])) == 1) {
        sendmessage($from_id, $textbotlang['users']['changeLocation']['limitReached'], null, 'html');
        return;
    }
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    update("user", "Processing_value", $nameloc['id_invoice'], "id", $from_id);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($marzban_list_get['changeloc'] == "offchangeloc") {
        sendmessage($from_id, $textbotlang['users']['featureUnavailable2'], null, 'html');
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful" || $DataUserOut['status'] == "disabled") {
        sendmessage($from_id, $textbotlang['users']['status']['error'], null, 'html');
        return;
    }
    Editmessagetext($from_id, $message_id, $textbotlang['textbot']['selectLocation'], $list_marzban_panel_userschange);
} elseif (preg_match('/changelocselectlo-(\w+)/', $datain, $dataget)) {
    update("user", "Processing_value_one", $dataget[1], "id", $from_id);
    $limitchangeloc = json_decode($setting['limitnumber'], true);
    // per-language override of the two location-change limits, falling back to
    // the shared limitnumber values
    $limitchangeloc['all'] = feature_setting_value('loc_limit_all', $user['lang'] ?? 'fa', $limitchangeloc['all'] ?? 0);
    $limitchangeloc['free'] = feature_setting_value('loc_limit_free', $user['lang'] ?? 'fa', $limitchangeloc['free'] ?? 0);
    $userlimitlast = $limitchangeloc['all'] - $user['limitchangeloc'];
    $userlimitlastfree = $limitchangeloc['free'] - $user['limitchangeloc'];
    if ($userlimitlastfree < 0)
        $userlimitlastfree = 0;
    $Pricechange = select("marzban_panel", "*", "code_panel", $dataget[1], "select")['priceChangeloc'];
    $textchange = sprintf($textbotlang['users']['changeLocation']['confirmPrompt'], $Pricechange, $userlimitlast, $userlimitlastfree);
    $keyboardextend = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['changeLocation']['confirm'], 'callback_data' => "confirmchangeloccha_" . $user['Processing_value']],
            ],
            [
                ['text' => $textbotlang['users']['status']['backinfo'], 'callback_data' => "product_" . $user['Processing_value']],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textchange, $keyboardextend);
} elseif (preg_match('/confirmchangeloccha_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $marzban_list_get_new = select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
    $limitchangeloc = json_decode($setting['limitnumber'], true);
    // per-language override of the two location-change limits, falling back to
    // the shared limitnumber values
    $limitchangeloc['all'] = feature_setting_value('loc_limit_all', $user['lang'] ?? 'fa', $limitchangeloc['all'] ?? 0);
    $limitchangeloc['free'] = feature_setting_value('loc_limit_free', $user['lang'] ?? 'fa', $limitchangeloc['free'] ?? 0);
    $limitfree = true;
    if ($user['limitchangeloc'] < $limitchangeloc['free'] and intval(feature_value('statuslimitchangeloc', $user['lang'] ?? 'fa', $setting['statuslimitchangeloc'])) == 1) {
        $limitfree = false;
    }
    if ($user['limitchangeloc'] >= $limitchangeloc['all'] and intval(feature_value('statuslimitchangeloc', $user['lang'] ?? 'fa', $setting['statuslimitchangeloc'])) == 1) {
        sendmessage($from_id, $textbotlang['users']['changeLocation']['limitReached'], null, 'html');
        return;
    }
    if ($marzban_list_get_new['changeloc'] == "offchangeloc") {
        sendmessage($from_id, $textbotlang['users']['featureUnavailable2'], null, 'html');
        return;
    }
    if ($marzban_list_get_new == false) {
        sendmessage($from_id, $textbotlang['users']['genericRestart'], null, 'html');
        return;
    }
    $Pricechange = $marzban_list_get_new['priceChangeloc'];
    // 🧩 a custom plan moves as one: its own volume and days, no product behind it
    if (cvol_invoice_is_custom($nameloc)) {
        $prodcut = ['code_product' => 'customvolume'];
        $product['inbounds'] = null;
    } else {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location = :service_location OR Location = '/all') AND agent= :agent AND name_product = :name_product AND (FIND_IN_SET(:userlang, lang) OR lang = 'all' OR lang IS NULL OR lang = '')");
        $stmt->execute([
            ':service_location' => $nameloc['Service_location'],
            ':agent' => $user['agent'],
            'name_product' => $nameloc['name_product'],
            ':userlang' => $user['lang'] ?? 'fa',
        ]);
        $prodcut = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if ($product['inbounds'] != null) {
        $marzban_list_get_new['inboundid'] = $prodcut['inbounds'];
    }
    if ($marzban_list_get_new['type'] == "Manualsale" && $marzban_list_get['url_panel'] == $marzban_list_get_new['url_panel']) {
        sendmessage($from_id, $textbotlang['users']['changeLocation']['notPossible'], null, 'html');
        return;
    }
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "on_hold") {
        sendmessage($from_id, $textbotlang['users']['changeLocation']['configUnused'], null, 'html');
        return;
    }
    if ($DataUserOut['status'] != "active") {
        sendmessage($from_id, $textbotlang['users']['status']['error'], null, 'html');
        return;
    }
    if ($limitfree == false) {
        $Pricechange = 0;
    }
    if ($user['Balance'] < $Pricechange && $user['agent'] != "n2" && $limitfree) {
        $marzbandirectpay = shop_feature_value('paydirect', $user['lang'] ?? 'fa', select('shopSetting', "*", "Namevalue", "statusdirectpabuy", "select")['value']);
        if ($marzbandirectpay == "offdirectbuy") {
            $minbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']]);
            $maxbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']]);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['status']['backinfo'], 'callback_data' => "account"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['Balance']['insufficientbalance'], $minbalance, $maxbalance), $bakinfos, 'HTML');
            step('getprice', $from_id);
            return;
        } else {
            if (intval($user['pricediscount']) != 0) {
                $result = ($Pricechange * $user['pricediscount']) / 100;
                $Pricechange = $Pricechange - $result;
                sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
            }
            if (intval($Pricechange) != 0) {
                $Balance_prim = $Pricechange - $user['Balance'];
                update("user", "Processing_value", $Balance_prim, "id", $from_id);
                Editmessagetext($from_id, $message_id, $noCreditText, step_payment_for_amount($step_payment, $Balance_prim, $user['lang'] ?? 'fa'));
                step('get_step_payment', $from_id);
                return;
            }
        }
    }
    if (intval($user['pricediscount']) != 0 and intval($Pricechange) != 0) {
        $result = ($Pricechange * $user['pricediscount']) / 100;
        $Pricechange = $Pricechange - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }
    if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
        if (($user['Balance'] - $Pricechange) < intval("-" . $user['maxbuyagent'])) {
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }
    $keyboardextend = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['status']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    $value = json_encode(array(
        "old_panel" => $marzban_list_get['code_panel'],
        "new_panel" => $marzban_list_get_new['code_panel'],
        "volume" => $DataUserOut['data_limit'],
        "used_traffic" => $DataUserOut['used_traffic'],
        "expire" => $DataUserOut['expire'],
        "stateus" => $DataUserOut['status']
    ));
    $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username,value,type,time,price) VALUES (?, ?, ?, ?,?,?)");
    $dateacc = date('Y/m/d H:i:s');
    $type = "change_location";
    $stmt->execute([$from_id, $nameloc['username'], $value, $type, $dateacc, $prodcut['price_product']]);
    if ($DataUserOut['data_limit'] == 0 || $DataUserOut['data_limit'] == null) {
        $data_limit = 0;
    } else {
        $data_limit = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
    }
    $datac = array(
        'expire' => $DataUserOut['expire'],
        'data_limit' => $data_limit,
        'from_id' => $from_id,
        'username' => $username,
        'type' => 'usertest'
    );
    $expirationDate = $DataUserOut['expire'] ? format_datetime('Y/m/d', $DataUserOut['expire'], $user['lang']) : $textbotlang['users']['status']['unlimited'];
    $timeDiff = $DataUserOut['expire'] - time();
    $day = $DataUserOut['expire'] ? floor($timeDiff / 86400) . $textbotlang['users']['status']['day'] : $textbotlang['users']['status']['unlimited'];
    $output = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
    $RemainingVolume = $DataUserOut['data_limit'] ? formatBytes($output) : $textbotlang['common']['labels']['unlimitedShort'];
    if ($marzban_list_get['url_panel'] == $marzban_list_get_new['url_panel']) {
        $remove = $ManagePanel->RemoveUser($nameloc['Service_location'], $nameloc['username']);
        $dataoutput = $ManagePanel->createUser($marzban_list_get_new['name_panel'], "usertest", $DataUserOut['username'], $datac);
    } else {
        $dataoutput = $ManagePanel->createUser($marzban_list_get_new['name_panel'], "usertest", $DataUserOut['username'], $datac);
        if ($dataoutput['username'] == null) {
            $dataoutput['msg'] = json_encode($dataoutput['msg']);
            sendmessage($from_id, $textbotlang['users']['sell']['errorConfig'], $keyboard, 'HTML');
            $texterros = sprintf($textbotlang['Admin']['reportgroup']['errorChangeLocation'], $dataoutput['msg'], $from_id, $username, $marzban_list_get['name_panel'], $marzban_list_get_new['name_panel']);
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $texterros,
                    'parse_mode' => "HTML"
                ]);
            }
            return;
        }
        $remove = $ManagePanel->RemoveUser($nameloc['Service_location'], $nameloc['username']);
    }
    $output_config_link = "";
    if ($marzban_list_get_new['sublink'] == "onsublink") {
        $output_config_link = $dataoutput['subscription_url'];
    }
    if ($marzban_list_get_new['config'] == "onconfig") {
        if (is_array($dataoutput['configs'])) {
            foreach ($dataoutput['configs'] as $configs) {
                $output_config_link .= "\n" . $configs;
            }
        }
    }
    $limitnew = $user['limitchangeloc'] + 1;
    update("user", "limitchangeloc", $limitnew, "id", $from_id);
    $textchangeloc = sprintf($textbotlang['users']['changeLocation']['success'], $marzban_list_get_new['name_panel'], $nameloc['username'], $RemainingVolume, $expirationDate, $day, $output_config_link);
    if (intval($Pricechange) != 0) {
        $Balance_Low_user = $user['Balance'] - $Pricechange;
        balance_add($from_id, -$Pricechange);
    }
    update("invoice", "Service_location", $marzban_list_get_new['name_panel'], "username", $nameloc['username']);
    if ($marzban_list_get_new['inboundid'] != null) {
        update("invoice", "inboundid", $marzban_list_get_new['inboundid'], "username", $nameloc['username']);
    }
    Editmessagetext($from_id, $message_id, $textchangeloc, $keyboardextend);
    $balanceformatsell = money(select("user", "Balance", "id", $from_id, "select")['Balance'], currency_for_user($user), false);
    $format_byte = formatBytes($data_limit);
    $textreport = sprintf($textbotlang['Admin']['reportgroup']['locationChanged'], $from_id, $username, $marzban_list_get['name_panel'], $marzban_list_get_new['name_panel'], $nameloc['username'], $format_byte, $balanceformatsell);
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $textreport,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/^usagereport_(\w+)$/', $datain, $dataget)) {
    // 📊 گزارش مصرف - the day-by-day history, read from the subscription link.
    // Only the multi-node panels keep it; the rest get an alert saying so
    // rather than an empty screen (panel_usage_supported() decides).
    $ur_invoice = select("invoice", "*", "id_invoice", $dataget[1], "select");
    if ($ur_invoice == false || $ur_invoice['id_user'] != $from_id) {
        return;
    }
    $ur_panel = select("marzban_panel", "*", "name_panel", $ur_invoice['Service_location'], "select");
    // ask the panel, do not assume from its type - see panel_usage_really_works()
    if (!panel_usage_really_works($ur_invoice['Service_location'], $ur_invoice['username'], $ur_panel['type'] ?? '')) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => bottext_resolve_key('users.status.svcUsageUnavailable'),
            'show_alert' => true,
            'cache_time' => 1,
        ]);
        return;
    }
    // the USERNAME, not the invoice id: the back button goes to
    // "productcheckdata", and that handler looks the service up with
    // "WHERE username = Processing_value". Writing an invoice id here made that
    // lookup find nothing, so بازگشت answered "اطلاعات اکانت در دسترس نیست".
    // Every other flow that hands off to productcheckdata stores the username
    // too (see the linksub path).
    update("user", "Processing_value", $ur_invoice['username'], "id", $from_id);
    $ur_kb = usage_report_keyboard($user['lang'] ?? 'fa', $textbotlang);
    // the status screen is a photo, so the menu is a fresh message; its back
    // button rebuilds the status screen exactly as productcheckdata already does
    deletemessage($from_id, $message_id);
    sendmessage($from_id, $textbotlang['users']['status']['svcUsageMenuTitle'], $ur_kb, 'html');
    return;
} elseif (preg_match('/^usagerep\|(usage_1|usage_2|usage_10|usage_all)$/', $datain, $dataget)) {
    // read back by username, matching what the menu stored (and what
    // productcheckdata expects to find there when بازگشت is tapped)
    $ur_invoice = select("invoice", "*", "username", $user['Processing_value'], "select");
    if ($ur_invoice == false || $ur_invoice['id_user'] != $from_id) {
        return;
    }
    $ur_panel = select("marzban_panel", "*", "name_panel", $ur_invoice['Service_location'], "select");
    $ur_data = $ManagePanel->DataUser($ur_invoice['Service_location'], $ur_invoice['username']);
    $ur_daily = ($ur_data['status'] ?? '') === 'Unsuccessful'
        ? ['supported' => false, 'daily' => [], 'error' => 'panel']
        : svc_daily_usage($ur_data['subscription_url'] ?? '', $ur_panel['type'] ?? null);
    if (empty($ur_daily['supported'])) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => bottext_resolve_key('users.status.svcUsageUnavailable'),
            'show_alert' => true,
            'cache_time' => 1,
        ]);
        return;
    }
    $ur_kb = usage_report_keyboard($user['lang'] ?? 'fa', $textbotlang);
    Editmessagetext($from_id, $message_id, svc_usage_report_text($dataget[1], $ur_daily['daily'], $user['lang'] ?? 'fa', $textbotlang), $ur_kb);
    return;
} elseif (preg_match('/disorder-(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    update("user", "Processing_value", $id_invoice, "id", $from_id);
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['status']['error'], null, 'html');
        return;
    }
    $textdisorder = $textbotlang['users']['support']['disruptionPrompt'];
    $keyboarddisorder = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['status']['backinfo'], 'callback_data' => "product_" . $id_invoice],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textdisorder, $keyboarddisorder);
    step("getdesdisorder", $from_id);
} elseif ($user['step'] == "getdesdisorder") {
    update("user", "Processing_value", $text, "id", $from_id);
    $nameloc = select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['status']['error'], null, 'html');
        return;
    }
    $textdisorder = $textbotlang['users']['support']['disruptionConfirm'];
    $keyboarddisorder = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['keyboard']['confirmDisruptionReport'], 'callback_data' => "confirmdisorders-" . $user['Processing_value']],
            ],
            [
                ['text' => $textbotlang['users']['status']['backinfo'], 'callback_data' => "product_" . $user['Processing_value']],
            ]
        ]
    ]);
    sendmessage($from_id, $textdisorder, $keyboarddisorder, 'html');
    step("home", $from_id);
} elseif (preg_match('/confirmdisorders-(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['support']['answermessage'], 'callback_data' => 'Response_' . $from_id],
            ],
        ]
    ]);
    $textdisorder = sprintf($textbotlang['Admin']['reportgroup']['disruption'], $username, $from_id, $nameloc['username'], $nameloc['name_product'], $nameloc['Service_location'], $user['Processing_value']);
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['online_at'] == "online") {
        $lastonline = $textbotlang['common']['connection']['onlineAlt'];
    } elseif ($DataUserOut['online_at'] == "offline") {
        $lastonline = $textbotlang['common']['connection']['offlineAlt'];
    } else {
        if (isset($DataUserOut['online_at']) && $DataUserOut['online_at'] !== null) {
            $dateString = $DataUserOut['online_at'];
            $lastonline = format_datetime('Y/m/d H:i:s', strtotime($dateString), $user['lang']);
        } else {
            $lastonline = $textbotlang['common']['connection']['notConnectedAlt'];
        }
    }
    #-------------status----------------#
    $status = $DataUserOut['status'];
    $status_var = [
        'active' => $textbotlang['users']['status']['active'],
        'limited' => $textbotlang['users']['status']['limited'],
        'disabled' => $textbotlang['users']['status']['disabled'],
        'expired' => $textbotlang['users']['status']['expired'],
        'on_hold' => $textbotlang['users']['status']['on_hold'],
        'Unknown' => $textbotlang['users']['status']['unknown'],
        'deactivev' => $textbotlang['users']['status']['disabled'],
    ][$status];
    #--------------[ expire ]---------------#
    $expirationDate = $DataUserOut['expire'] ? format_datetime('Y/m/d', $DataUserOut['expire'], $user['lang']) : $textbotlang['users']['status']['unlimited'];
    #-------------[ data_limit ]----------------#
    $LastTraffic = $DataUserOut['data_limit'] ? formatBytes($DataUserOut['data_limit']) : $textbotlang['users']['status']['unlimited'];
    #---------------[ RemainingVolume ]--------------#
    $output = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
    $RemainingVolume = $DataUserOut['data_limit'] ? formatBytes($output) : $textbotlang['common']['labels']['unlimitedShort'];
    #---------------[ used_traffic ]--------------#
    $usedTrafficGb = $DataUserOut['used_traffic'] ? formatBytes($DataUserOut['used_traffic']) : $textbotlang['users']['status']['notConsumed'];
    #--------------[ day ]---------------#
    $timeDiff = $DataUserOut['expire'] - time();
    $day = $DataUserOut['expire'] ? floor($timeDiff / 86400) . $textbotlang['users']['status']['day'] : $textbotlang['users']['status']['unlimited'];
    #--------------[ subsupdate ]---------------#
    if ($DataUserOut['sub_updated_at'] !== null) {
        $sub_updated = $DataUserOut['sub_updated_at'];
        $dateTime = new DateTime($sub_updated, new DateTimeZone('UTC'));
        $dateTime->setTimezone(new DateTimeZone('Asia/Tehran'));
        $lastupdate = format_datetime('Y/m/d H:i:s', $dateTime->getTimestamp(), $user['lang']);
    }
    if ($DataUserOut['data_limit'] != null && $DataUserOut['used_traffic'] != null) {
        $Percent = ($DataUserOut['data_limit'] - $DataUserOut['used_traffic']) * 100 / $DataUserOut['data_limit'];
    } else {
        $Percent = "100";
    }
    if ($Percent < 0)
        $Percent = -($Percent);
    $Percent = round($Percent, 2);
    $textdisorder .= sprintf($textbotlang['users']['status']['summary'], $status_var, $LastTraffic, $usedTrafficGb, $RemainingVolume, $Percent, $expirationDate, $day, $DataUserOut['subscription_url'], $lastonline, $lastupdate, $DataUserOut['sub_last_user_agent']);
    foreach ($admin_ids as $admin) {
        $adminrulecheck = select("admin", "*", "id_admin", $admin, "select");
        if ($adminrulecheck['rule'] == "Seller")
            continue;
        sendmessage($admin, $textdisorder, $Response, 'html');
    }
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['status']['backinfo'], 'callback_data' => "product_$id_invoice"],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['support']['requestSubmitted'], $bakinfos, 'html');
} elseif (preg_match('/Extra_time_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    if ($marzban_list_get['status_extend'] == "off_extend") {
        sendmessage($from_id, $textbotlang['users']['extraTime']['notSupportedPanel'], null, 'html');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['status']['error'], null, 'html');
        return;
    }
    if ($DataUserOut['status'] == "on_hold") {
        sendmessage($from_id, $textbotlang['users']['extend']['connectFirst'], null, 'html');
        return;
    }
    $eextraprice = json_decode($marzban_list_get['priceextratime'], true);
    $extratimepricevalue = $eextraprice[$user['agent']];
    update("user", "Processing_value", $nameloc['id_invoice'], "id", $from_id);
    $textextra = sprintf($textbotlang['users']['extraTime']['prompt'], $extratimepricevalue);
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['status']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textextra, $bakinfos);
    step('gettimeextra', $from_id);
} elseif ($user['step'] == "gettimeextra") {
    if (!ctype_digit($text) || $text < 1) {
        sendmessage($from_id, $textbotlang['common']['invalidTime'], $backuser, 'HTML');
        return;
    }
    $nameloc = select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $eextraprice = json_decode($marzban_list_get['priceextratime'], true);
    $extratimepricevalue = $eextraprice[$user['agent']];
    $eextraprice = json_decode($marzban_list_get['priceextravolume'], true);
    $extrapricevalue = $eextraprice[$user['agent']];
    $priceextratime = $extratimepricevalue * $text;
    $keyboardsetting = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['extraTime']['extratimecheck'], 'callback_data' => 'confirmaextratime-' . $extratimepricevalue * $text],
            ]
        ]
    ]);
    $priceextratime = number_format($priceextratime, 0);
    $extrapricevalues = number_format($extrapricevalue, 0);
    $textextra = sprintf($textbotlang['users']['extraTime']['invoiceCreated'], $extratimepricevalue, $text, $priceextratime);
    sendmessage($from_id, $textextra, $keyboardsetting, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/confirmaextratime-(\w+)/', $datain, $dataget)) {
    $tmieextra = $dataget[1];
    $pricelasttime = $tmieextra;
    $nameloc = select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
    if (!in_array($nameloc['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'])) {
        sendmessage($from_id, $textbotlang['users']['sell']['purchaseError'], null, 'HTML');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $eextraprice = json_decode($marzban_list_get['priceextratime'], true);
    $extratimepricevalue = $eextraprice[$user['agent']];
    if ($user['Balance'] < $tmieextra && $user['agent'] != "n2" && !$admin_buy_free) {
        $marzbandirectpay = shop_feature_value('paydirect', $user['lang'] ?? 'fa', select('shopSetting', "*", "Namevalue", "statusdirectpabuy", "select")['value']);
        if ($marzbandirectpay == "offdirectbuy") {
            $minbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']]);
            $maxbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']]);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['status']['backinfo'], 'callback_data' => "account"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['Balance']['insufficientbalance'], $minbalance, $maxbalance), $bakinfos, 'HTML');
            step('getprice', $from_id);
            return;
        } else {
            $valuetime = $tmieextra / $extratimepricevalue;
            if (intval($user['pricediscount']) != 0) {
                $result = ($tmieextra * $user['pricediscount']) / 100;
                $pricelasttime = $tmieextra - $result;
                sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
            }
            if (intval($pricelasttime) != 0) {
                $Balance_prim = $pricelasttime - $user['Balance'];
                update("user", "Processing_value", $Balance_prim, "id", $from_id);
                Editmessagetext($from_id, $message_id, $noCreditText, step_payment_for_amount($step_payment, $Balance_prim, $user['lang'] ?? 'fa'));
                step('get_step_payment', $from_id);
                update("user", "Processing_value_one", "{$nameloc['username']}%{$valuetime}", "id", $from_id);
                update("user", "Processing_value_tow", "getextratimeuser", "id", $from_id);
                return;
            }
        }
    }
    deletemessage($from_id, $message_id);
    if (intval($user['pricediscount']) != 0 and intval($pricelasttime) != 0) {
        $result = ($tmieextra * $user['pricediscount']) / 100;
        $pricelasttime = $tmieextra - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }
    // $tmieextra still gives the number of days; only the charge is zeroed
    if ($admin_buy_free) {
        $pricelasttime = 0;
    }
    $Balance_Low_user = $user['Balance'] - $pricelasttime;
    if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
        if ($Balance_Low_user < intval("-" . $user['maxbuyagent'])) {
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }
    update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
    $extratimeday = $tmieextra / $extratimepricevalue;
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    $data_for_database = json_encode(array(
        'day' => $extratimeday,
        'priceـper_day' => $extratimeday,
        'old_volume' => $DataUserOut['data_limit'],
        'expire_old' => $DataUserOut['expire']
    ));
    $timeservice = $DataUserOut['expire'] - time();
    $day = floor($timeservice / 86400);
    $extra_time = $ManagePanel->extra_time($nameloc['username'], $marzban_list_get['code_panel'], $extratimeday);
    if ($extra_time['status'] == false) {
        $extra_time['msg'] = json_encode($extra_time['msg']);
        $textreports = sprintf($textbotlang['Admin']['reportgroup']['errorExtraTime'], $marzban_list_get['name_panel'], $nameloc['username'], $extra_time['msg']);
        sendmessage($from_id, $textbotlang['users']['extraTime']['serviceError'], null, 'HTML');
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $textreports,
                'parse_mode' => "HTML"
            ]);
        }
        return;
    }
    balance_add($from_id, -$pricelasttime);
    $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username, value, type, time, price, output) VALUES (:id_user, :username, :value, :type, :time, :price, :output)");
    $value = $data_for_database;
    $dateacc = date('Y/m/d H:i:s');
    $type = "extra_time_user";
    $output = json_encode($extra_time);
    $stmt->execute([
        ':id_user' => $from_id,
        ':username' => $nameloc['username'],
        ':value' => $value,
        ':type' => $type,
        ':time' => $dateacc,
        ':price' => $pricelasttime,
        ':output' => $output,
    ]);
    $keyboardextrafnished = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['status']['backservice'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    if (score_on($user['lang'] ?? 'fa', $setting) and !in_array($from_id, $admin_ids)) {
        sendmessage($from_id, $textbotlang['users']['affiliates']['pointsEarned1Alt'], null, 'html');
        $scorenew = $user['score'] + 1;
        update("user", "score", $scorenew, "id", $from_id);
    }
    $volumesformat = number_format($tmieextra);
    $textextratime = sprintf($textbotlang['users']['extraTime']['success'], $nameloc['username'], $extratimeday, $volumesformat);
    sendmessage($from_id, $textextratime, $keyboardextrafnished, 'HTML');
    $volumes = $tmieextra / $extratimepricevalue;
    $text_report = sprintf($textbotlang['Admin']['reportgroup']['extraTime'], $from_id, $volumes, $volumesformat, $nameloc['username']);
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/removeserviceuser_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    savedata("clear", "id_invoice", $id_invoice);
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['status']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['status']['askDeleteReason'], $bakinfos);
    step("getdisdeleteconfig", $from_id);
} elseif ($user['step'] == "getdisdeleteconfig") {
    $userdata = json_decode($user['Processing_value'], true);
    $id_invoice = $userdata['id_invoice'];
    savedata("save", "descritionsremove", $text);
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    if ($nameloc['name_product'] == $textbotlang['common']['labels']['testService3']) {
        sendmessage($from_id, $textbotlang['users']['status']['errorusertest'], null, 'html');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if (isset($DataUserOut['status']) && in_array($DataUserOut['status'], ["expired", "limited", "disabled"])) {
        sendmessage($from_id, $textbotlang['users']['status']['error'], null, 'html');
        step("home", $from_id);
        return;
    }
    $requestcheck = select("cancel_service", "*", "username", $nameloc['username'], "count");
    if ($requestcheck != 0) {
        sendmessage($from_id, $textbotlang['users']['status']['errorexits'], null, 'html');
        return;
    }
    $confirmremove = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['keyboard']['confirmDeleteService'], 'callback_data' => "confirmremoveservices-$id_invoice"],
            ],
        ]
    ]);
    sendmessage($from_id, $textbotlang['users']['status']['descriptionsRemoveService'], $confirmremove, "html");
    step("home", $from_id);
} elseif (preg_match('/confirmremoveservices-(\w+)/', $datain, $dataget)) {
    $userdata = json_decode($user['Processing_value'], true);
    $stmt = $pdo->prepare("SELECT * FROM cancel_service WHERE id_user = :from_id AND status = 'waiting'");
    $stmt->execute([
        ':from_id' => $from_id
    ]);
    $checkcancelservicecount = $stmt->rowCount();
    if ($checkcancelservicecount != 0) {
        sendmessage($from_id, $textbotlang['users']['status']['exitsRequests'], null, 'HTML');
        return;
    }
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $stmt = $pdo->prepare("INSERT IGNORE INTO cancel_service (id_user, username,description,status) VALUES (?, ?, ?, ?)");
    $descriptions = "0";
    $Status = "waiting";
    $stmt->execute([$from_id, $nameloc['username'], $descriptions, $Status]);
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if (isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") {
        sendmessage($from_id, $textbotlang['users']['status']['userNotFound'], null, 'html');
        step('home', $from_id);
        return;
    }
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['status']['panelNotConnected'], null, 'html');
        step('home', $from_id);
        return;
    }
    #-------------status----------------#
    if ($DataUserOut['online_at'] == "online") {
        $lastonline = $textbotlang['common']['connection']['onlineAlt'];
    } elseif ($DataUserOut['online_at'] == "offline") {
        $lastonline = $textbotlang['common']['connection']['offlineAlt'];
    } else {
        if (isset($DataUserOut['online_at']) && $DataUserOut['online_at'] !== null) {
            $dateString = $DataUserOut['online_at'];
            $lastonline = format_datetime('Y/m/d H:i:s', strtotime($dateString), $user['lang']);
        } else {
            $lastonline = $textbotlang['common']['connection']['notConnectedAlt'];
        }
    }
    $status = $DataUserOut['status'];
    $status_var = [
        'active' => $textbotlang['users']['status']['active'],
        'limited' => $textbotlang['users']['status']['limited'],
        'disabled' => $textbotlang['users']['status']['disabled'],
        'expired' => $textbotlang['users']['status']['expired'],
        'on_hold' => $textbotlang['users']['status']['on_hold'],
        'Unknown' => $textbotlang['users']['status']['unknown'],
        'deactivev' => $textbotlang['users']['status']['disabled'],

    ][$status];
    #--------------[ expire ]---------------#
    $expirationDate = $DataUserOut['expire'] ? format_datetime('Y/m/d', $DataUserOut['expire'], $user['lang']) : $textbotlang['users']['status']['unlimited'];
    #-------------[ data_limit ]----------------#
    $LastTraffic = $DataUserOut['data_limit'] ? formatBytes($DataUserOut['data_limit']) : $textbotlang['users']['status']['unlimited'];
    #---------------[ RemainingVolume ]--------------#
    $output = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
    $RemainingVolume = $DataUserOut['data_limit'] ? formatBytes($output) : $textbotlang['common']['labels']['unlimitedShort'];
    #---------------[ used_traffic ]--------------#
    $usedTrafficGb = $DataUserOut['used_traffic'] ? formatBytes($DataUserOut['used_traffic']) : $textbotlang['users']['status']['notConsumed'];
    #--------------[ day ]---------------#
    $timeDiff = $DataUserOut['expire'] - time();
    $day = $DataUserOut['expire'] ? floor($timeDiff / 86400) . $textbotlang['users']['status']['day'] : $textbotlang['users']['status']['unlimited'];
    #-----------------------------#
    $textinfoadmin = sprintf($textbotlang['Admin']['reportgroup']['deleteServiceRequest'], $from_id, $username, $nameloc['username'], $status_var, $nameloc['Service_location'], $nameloc['id_invoice'], $lastonline, $usedTrafficGb, $LastTraffic, $RemainingVolume, $expirationDate, $day, $userdata['descritionsremove']);
    $confirmremoveadmin = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['keyboard']['manualDelete'], 'callback_data' => "remoceserviceadminmanual-{$nameloc['id_invoice']}"],
                ['text' => $textbotlang['keyboard']['deleteServiceAlt'], 'callback_data' => "remoceserviceadmin-{$nameloc['id_invoice']}"],
                ['text' => $textbotlang['keyboard']['rejectDelete'], 'callback_data' => "rejectremoceserviceadmin-{$nameloc['id_invoice']}"],
            ],
        ]
    ]);
    foreach ($admin_ids as $admin) {
        sendmessage($admin, $textinfoadmin, $confirmremoveadmin, 'html');
        step("home", $admin);
    }
    deletemessage($from_id, $message_id);
    sendmessage($from_id, $textbotlang['users']['status']['sendrequestsremove'], $keyboard, 'html');
} elseif (preg_match('/transfer_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    if ($nameloc['name_product'] == $textbotlang['common']['labels']['testService4']) {
        sendmessage($from_id, $textbotlang['users']['transfer']['transferNotValid'], null, 'html');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
    $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
    if (isset($DataUserOut['status']) && in_array($DataUserOut['status'], ["expired", "limited", "disabled"])) {
        sendmessage($from_id, $textbotlang['users']['status']['error'], null, 'html');
        return;
    }
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['status']['backToPreviousMenuBtn'], 'callback_data' => "product_" . $nameloc['id_invoice'], 'style' => 'danger'],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['transfer']['description'], $bakinfos);
    step("getidfortransfer", $from_id);
    update("user", "Processing_value_one", $nameloc['username'], "id", $from_id);
    update("user", "Processing_value_tow", $nameloc['id_invoice'], "id", $from_id);
} elseif ($user['step'] == "getidfortransfer") {
    if (!in_array($text, $users_ids)) {
        sendmessage($from_id, $textbotlang['users']['transfer']['notUserTrans'], $backuser, 'HTML');
        return;
    }
    update("user", "Processing_value_one", $text, "id", $from_id);
    $confirmtransfer = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['keyboard']['confirmTransferService'], 'callback_data' => "confrimtransfers_{$user['Processing_value_tow']}"],
            ],
        ]
    ]);
    sendmessage($from_id, $textbotlang['users']['transfer']['confirm'], $confirmtransfer, 'HTML');
    step("home", $from_id);
} elseif (preg_match('/confrimtransfers_(\w+)/', $datain, $dataget)) {
    if ($from_id == $user['Processing_value_one']) {
        sendmessage($from_id, $textbotlang['users']['transfer']['notSendServiceYou'], $keyboard, 'HTML');
        return;
    }
    $id_invoice = $dataget[1];
    $nameloc = select("invoice", "*", "id_invoice", $id_invoice, "select");
    update("invoice", "id_user", $user['Processing_value_one'], "id_invoice", $id_invoice);
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['status']['backinfo'], 'callback_data' => "backorder"],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['transfer']['confirmed'], $bakinfos);
    $texttransfer = strtr($textbotlang['users']['transfer']['receivedNotice'], ['{service_username}' => $nameloc['username'], '{from_id}' => $from_id]);
    sendmessage($user['Processing_value_one'], $texttransfer, $keyboard, 'HTML');
    $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username,value,type,time,price) VALUES (?, ?, ?, ?,?,?)");
    $value = $user['Processing_value_one'];
    $dateacc = date('Y/m/d H:i:s');
    $type = "transfertouser";
    $price = "0";
    $stmt->execute([$from_id, $nameloc['username'], $value, $type, $dateacc, $price]);
} elseif ($text == $textbotlang['textbot']['userTest'] || $datain == "usertestbtn" || $text == "usertest") {
    if (!mainmenu_btn_active($user['lang'] ?? 'fa', "text_usertest")) {
        sendmessage($from_id, $textbotlang['users']['usertest']['unavailable'], null, 'HTML');
        return;
    }
    $locationproduct = panels_available_count($user['lang'] ?? 'fa', $user['agent'], 'test');
    if ($locationproduct == 0) {
        // its own message now: this is the test-account flow, and it used to
        // borrow the purchase flow's nullPanel, so rewording one reworded both
        sendmessage($from_id, $textbotlang['users']['usertest']['noPanel'], null, 'HTML');
        return;
    }
    // panel selection is now always shown, even with a single active test panel, instead
    // of silently auto-picking it - mirrors the purchase flow's "if (false && ...)" fix
    if (true || $locationproduct != 1) {
        if (phone_required_for($user) && $user['step'] != "get_number" && $user['number'] == "none") {
            sendmessage($from_id, $textbotlang['users']['number']['confirming'], $request_contact, 'HTML');
            update("user", "Processing_value", "verifyusertest", "id", $from_id);
            step('get_number', $from_id);
        }
        if ($user['number'] == "none" && phone_required_for($user))
            return;
        if ($user['limit_usertest'] <= 0 && !$admin_test_free) {
            sendmessage($from_id, $textbotlang['users']['usertest']['limitwarning'], $keyboard_buy, 'html');
            return;
        }
        // any sticker still up from an abandoned run goes first, so only this
        // screen's own is on the books
        sell_sticker_retire($from_id);
        $usertestLocationMsg = sendmessage($from_id, $textbotlang['textbot']['selectLocationTest'], $list_marzban_usertest, 'html');
        if (!empty($usertestLocationMsg['_sticker_message_id'])) {
            // the dedicated sticker column, not Processing_value_tow: that field
            // is re-purposed a few steps later in this very flow (it takes the
            // username-prompt message id), and ❌ بستن never cleared it - so the
            // caption went and this sticker stayed behind in the chat.
            update("user", "bt_sticker_id", (string) $usertestLocationMsg['_sticker_message_id'], "id", $from_id);
        }
    }
}
#-----------⏱ اکانت ساعتی (payg.php): «🔐 خرید اشتراک»'s first screen and its own flow------------#
// Here, between the two chains, so a purchase resumed after the phone check
// ($verify_resume) meets the type screen too.
// «📅 اشتراک معمولی» is the ordinary purchase from its first step, exactly as
// if the type screen had never been there.
$pg_normal = false;
if ($datain === 'buytype_normal') {
    $datain = 'buy';
    $pg_normal = true;
}
if (!$pg_normal && ($text == $textbotlang['textbot']['sell'] || $datain == "buy" || $datain == "buyfresh" || $text == "/buy" || $text == "buy" || $verify_resume === 'verifybuy') && payg_offer($user)) {
    if (!mainmenu_btn_active($user['lang'] ?? 'fa', "text_sell")) {
        sendmessage($from_id, $textbotlang['users']['buttonDisabled'], null, 'HTML');
        return;
    }
    if (phone_required_for($user) && $user['number'] == "none") {
        sendmessage($from_id, $textbotlang['users']['number']['confirming'], $request_contact, 'HTML');
        update("user", "Processing_value", "verifybuy", "id", $from_id);
        step('get_number', $from_id);
        return;
    }
    step('home', $from_id);
    if ($datain == "buyfresh") {
        deletemessage($from_id, $message_id);
    }
    sell_screen($from_id, $datain == "buy" ? $message_id : 0, $textbotlang['users']['payg']['typeChoice'], payg_type_kb($textbotlang));
    return;
}
// ⏱ اشتراک ساعتی: the panels, or straight to the only one's rates
if ($datain === 'buytype_payg' || preg_match('/^paygpanel_(.+)$/', (string) $datain, $pg_m)) {
    $pg_w = $textbotlang['users']['payg'];
    // ↩️ from the 💡 name's ask: it waits for nothing any more
    if (str_starts_with((string) $user['step'], 'paygname|')) {
        step('home', $from_id);
    }
    $pg_panels = payg_user_panels($user);
    if (!$pg_panels) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => $pg_w['off'], 'show_alert' => true]);
        return;
    }
    $pg_panel = null;
    if ($datain === 'buytype_payg') {
        if (count($pg_panels) > 1) {
            sell_screen($from_id, $message_id, $pg_w['selectPanel'], payg_panels_kb($pg_panels, $textbotlang));
            return;
        }
        $pg_panel = $pg_panels[0];
    } else {
        foreach ($pg_panels as $pg_p) {
            if ((string) $pg_p['code_panel'] === (string) $pg_m[1]) {
                $pg_panel = $pg_p;
            }
        }
        if ($pg_panel === null) {
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => $pg_w['noPanel'], 'show_alert' => true]);
            return;
        }
    }
    [$pg_text, $pg_kb] = payg_offer_screen($user, $pg_panel, $textbotlang, count($pg_panels) === 1);
    sell_screen($from_id, $message_id, $pg_text, $pg_kb);
    return;
}
// 💡 «نام کاربری دلخواه»: the name the customer sent (step paygname|<panel>|
// <the ask's message>) - on to ✅ بساز with it; a /command is not a try at one
$pg_typed = null;
if ((string) $datain === '' && preg_match('/^paygname\|(.+)\|(\d+)$/', (string) $user['step'], $pg_nm)) {
    $pg_try = trim((string) $text);
    if ($pg_try !== '' && $pg_try[0] === '/') {
        step('home', $from_id);
        $user['step'] = 'home';
    } elseif (!payg_name_ok($pg_try)) {
        // one message all along: the customer's try goes, the ask says why
        deletemessage($from_id, $message_id);
        Editmessagetext($from_id, (int) $pg_nm[2], $textbotlang['users']['invalidusername'] . "\n\n" . $textbotlang['users']['payg']['askUsername'], payg_username_kb($user['lang'] ?? 'fa', $textbotlang, $pg_nm[1]));
        return;
    } else {
        step('home', $from_id);
        $user['step'] = 'home';
        $pg_typed = strtolower($pg_try);
        // the customer's own message with the name goes; the ask turns into
        // «⏳ در حال ساخت» below and is gone once it is made
        deletemessage($from_id, $message_id);
        $datain = 'paygmk_' . $pg_nm[1];
        $message_id = (int) $pg_nm[2];
    }
}
// 🟢 «پیش‌فرض» under the 💡 name's ask: made without one - ⏱'s own name
// (payg_make_username falls back to payg_<id>_<n> when none is given)
if (preg_match('/^paygdef_(.+)$/', (string) $datain, $pg_dm)) {
    if (str_starts_with((string) $user['step'], 'paygname|')) {
        step('home', $from_id);
        $user['step'] = 'home';
    }
    $pg_typed = '';
    $datain = 'paygmk_' . $pg_dm[1];
}
// ✅ بساز: made, and shown as its own service screen
if (preg_match('/^paygmk_(.+)$/', (string) $datain, $pg_m)) {
    $pg_w = $textbotlang['users']['payg'];
    // 💡 «نام کاربری دلخواه»: the name is asked for first - once the wallet,
    // the 🔢 count and the panel are known to be fine
    $pg_ask = $pg_typed === null && payg_uname_asks($user['lang'] ?? 'fa');
    if ($pg_ask) {
        $pg_res = payg_create($from_id, $pg_m[1], null, true);
    } else {
        Editmessagetext($from_id, $message_id, $pg_w['creating'], null);
        // a second tap while the first is still being made waits for it, and
        // then meets the 🔢 count and the wallet as the first one left them
        $pg_res = payg_locked('create_' . $from_id, fn() => payg_create($from_id, $pg_m[1], $pg_typed));
    }
    if (!is_array($pg_res)) {
        $pg_res = ['error' => 'panel'];
    }
    if (empty($pg_res['ok'])) {
        $pg_err = (string) ($pg_res['error'] ?? 'panel');
        $pg_back = json_encode(['inline_keyboard' => [[['text' => $pg_w['btnBack'], 'callback_data' => 'buytype_payg', 'style' => 'danger']]]]);
        if ($pg_err === 'lowbal') {
            $pg_cur = $pg_res['cur'] ?? currency_for_lang($user['lang'] ?? 'fa');
            Editmessagetext($from_id, $message_id, strtr($pg_w['lowBalance'], ['{need}' => money($pg_res['need'], $pg_cur), '{balance}' => money($pg_res['have'], $pg_cur)]), payg_topup_kb($textbotlang));
        } else {
            $pg_msg = ['max' => strtr($pg_w['maxReached'], ['{max}' => (string) ($pg_res['max'] ?? 1)]), 'full' => $pg_w['panelFull'], 'off' => $pg_w['off']][$pg_err] ?? $pg_w['createFailed'];
            Editmessagetext($from_id, $message_id, $pg_msg, $pg_back);
        }
        return;
    }
    if ($pg_ask) {
        // the purchase's own question and its 🔴 / 🟢 buttons, marked ⏱ on top
        Editmessagetext($from_id, $message_id, $pg_w['askUsername'], payg_username_kb($user['lang'] ?? 'fa', $textbotlang, $pg_m[1]));
        step("paygname|{$pg_m[1]}|{$message_id}", $from_id);
        return;
    }
    // a 🔐 sticker still up from the screens before goes with them
    sell_sticker_retire($from_id);
    // ONE message, in place of «⏳ در حال ساخت»: its own screen of «🛍 سرویس‌های
    // من» - status, cost, rates, a QR of its link and every button
    $pg_new = payg_row($pg_res['id_invoice']);
    if ($pg_new === null) {
        deletemessage($from_id, $message_id);
        return;
    }
    [$pg_text, $pg_kb] = payg_service_screen($pg_new, $textbotlang, null, null, (string) ($pg_res['out']['subscription_url'] ?? ''));
    svc_send_status_screen($from_id, $message_id, $pg_text, $pg_kb, (string) ($pg_res['out']['subscription_url'] ?? ''), true);
    return;
}
// the service's own buttons: 🔄 💡 ⏩ 🗑 - each for its owner only (the check
// at the top, service_button_invoice_id), each on the service's lock
if (preg_match('/^(paygref|paygpause|paygdel|paygdelok|paygconv|paygrm|paygrmok)_([A-Za-z0-9]+)$/', (string) $datain, $pg_m) || preg_match('/^(paygcp|paygcpok)_([A-Za-z0-9]+)_(.+)$/', (string) $datain, $pg_m)) {
    $pg_w = $textbotlang['users']['payg'];
    $pg_act = $pg_m[1];
    $pg_id = $pg_m[2];
    $pg_alert = function ($t) use ($callback_query_id) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => $t, 'show_alert' => true]);
    };
    $pg_svc = payg_invoice_of($pg_id) !== null ? payg_row($pg_id) : null;
    $pg_open = $pg_svc !== null && in_array($pg_svc['status'], ['waiting', 'active', 'stopped', 'paused'], true);
    $pg_toList = json_encode(['inline_keyboard' => [[['text' => $textbotlang['users']['status']['backlist'], 'callback_data' => 'backorder', 'style' => 'danger']]]]);
    if ($pg_act === 'paygref') {
        if ($pg_svc === null) {
            $pg_alert($pg_w['notActive']);
            return;
        }
        $pg_res = payg_locked($pg_id, function () use ($pg_id) {
            $svc = payg_row($pg_id);
            $r = payg_settle($svc);
            $svc = payg_row($pg_id);
            payg_set($pg_id, ['seen_charged' => $svc['charged']]);
            return [$svc, $r, (float) $svc['charged'] - (float) $svc['seen_charged']];
        });
        if (!is_array($pg_res)) {
            $pg_alert($pg_w['notActive']);
            return;
        }
        [$pg_svc, $pg_r, $pg_since] = $pg_res;
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => $textbotlang['keyboard']['infoRefreshed'], 'show_alert' => false, 'cache_time' => 5]);
        [$pg_text, $pg_kb] = payg_service_screen($pg_svc, $textbotlang, $pg_r['data'] ?? null, $pg_since);
        $pg_sub = is_array($pg_r['data']['data'] ?? null) ? (string) ($pg_r['data']['data']['subscription_url'] ?? '') : '';
        svc_send_status_screen($from_id, $message_id, $pg_text, $pg_kb, $pg_sub, false);
        return;
    }
    if ($pg_act === 'paygpause') {
        // 💡: an ordinary service's on/off, as an hourly one can have it - off
        // stops the clock (payg_user_pause), on needs what a ⛔️ one needs
        if (!$pg_open) {
            $pg_alert($pg_w['notActive']);
            return;
        }
        if (in_array('changestatus', statusbtn_shop_off($user['lang'] ?? 'fa'), true)) {
            $pg_alert($textbotlang['users']['featureUnavailable']);
            return;
        }
        if ($pg_svc['status'] === 'waiting' || $pg_svc['status'] === 'stopped') {
            $pg_alert($pg_w[$pg_svc['status'] === 'waiting' ? 'pauseWaiting' : 'pauseStopped']);
            return;
        }
        $pg_res = payg_locked($pg_id, function () use ($pg_id) {
            $svc = payg_row($pg_id);
            if ($svc === null) {
                return ['error' => 'state'];
            }
            return $svc['status'] === 'paused' ? payg_user_resume($svc) : payg_user_pause($svc);
        });
        if (!is_array($pg_res) || empty($pg_res['ok'])) {
            $pg_err = is_array($pg_res) ? (string) ($pg_res['error'] ?? '') : '';
            if ($pg_err === 'lowbal') {
                $pg_alert(strtr($pg_w['resumeLowBalance'], ['{need}' => money($pg_res['need'], $pg_res['cur']), '{balance}' => money($pg_res['have'], $pg_res['cur'])]));
            } else {
                $pg_alert($pg_err === 'panel' ? $pg_w['pauseFailed'] : $pg_w['notActive']);
            }
            return;
        }
        $pg_svc = $pg_res['svc'];
        $pg_alert($pg_svc['status'] === 'paused' ? $pg_w['pausedOk'] : $pg_w['resumedOk']);
        $pg_d = payg_panel_data(select("marzban_panel", "*", "name_panel", $pg_svc['panel'], "select"), $pg_svc['username']);
        [$pg_text, $pg_kb] = payg_service_screen($pg_svc, $textbotlang, $pg_d);
        $pg_sub = is_array($pg_d['data'] ?? null) ? (string) ($pg_d['data']['subscription_url'] ?? '') : '';
        svc_send_status_screen($from_id, $message_id, $pg_text, $pg_kb, $pg_sub, false);
        return;
    }
    if ($pg_act === 'paygdel' || $pg_act === 'paygdelok') {
        if (!$pg_open) {
            $pg_alert($pg_w['notActive']);
            return;
        }
        [$pg_can, $pg_left] = payg_can_delete($pg_svc);
        if (!$pg_can) {
            // a part of a minute counts as a minute: «30» while 29:59 is left
            $pg_alert(strtr($pg_w['deleteTooEarly'], ['{left}' => payg_duration_text((int) ceil($pg_left / 60) * 60, $textbotlang)]));
            return;
        }
        if ($pg_act === 'paygdel') {
            [$pg_text, $pg_kb] = payg_delete_confirm($pg_svc, $textbotlang);
            Editmessagetext($from_id, $message_id, $pg_text, $pg_kb);
            return;
        }
        // what it used up to now is taken first; nothing after it
        $pg_done = payg_locked($pg_id, function () use ($pg_id) {
            $svc = payg_row($pg_id);
            if ($svc === null || !in_array($svc['status'], ['waiting', 'active', 'stopped', 'paused'], true)) {
                return null;
            }
            payg_settle($svc);
            return payg_delete(payg_row($pg_id), 'user');
        });
        if (!is_array($pg_done)) {
            $pg_alert($pg_w['notActive']);
            return;
        }
        Editmessagetext($from_id, $message_id, strtr($pg_done['status'] === 'ended' ? $pg_w['deletedKept'] : $pg_w['deleted'], [
            '{username}' => htmlspecialchars($pg_done['username']),
            '{charged}' => money((float) $pg_done['charged'], $pg_done['currency']),
        ]), $pg_toList);
        return;
    }
    if ($pg_act === 'paygconv' || $pg_act === 'paygcp' || $pg_act === 'paygcpok') {
        if ($pg_svc === null || !in_array($pg_svc['status'], ['waiting', 'active'], true)) {
            $pg_alert($pg_w['notActive']);
            return;
        }
        if (!payg_cfg($pg_svc['lang'])['convert']) {
            $pg_alert($pg_w['notAllowed']);
            return;
        }
        if ($pg_act === 'paygconv') {
            [$pg_text, $pg_kb] = payg_convert_list($pg_svc, $textbotlang);
            Editmessagetext($from_id, $message_id, $pg_text, $pg_kb);
            return;
        }
        if ($pg_act === 'paygcp') {
            $pg_prod = null;
            foreach (payg_panel_products($pg_svc['lang'], $pg_svc['panel'], payg_group_of($user)) as $pg_p) {
                if ((string) $pg_p['code_product'] === (string) $pg_m[3]) {
                    $pg_prod = $pg_p;
                }
            }
            if ($pg_prod === null) {
                $pg_alert($pg_w['convertNoProduct']);
                return;
            }
            [$pg_text, $pg_kb] = payg_convert_confirm($pg_svc, $pg_prod, $textbotlang);
            Editmessagetext($from_id, $message_id, $pg_text, $pg_kb);
            return;
        }
        $pg_res = payg_locked($pg_id, function () use ($pg_id, $pg_m) {
            $svc = payg_row($pg_id);
            return $svc === null ? ['error' => 'off'] : payg_convert($svc, $pg_m[3]);
        });
        if (!is_array($pg_res)) {
            $pg_res = ['error' => 'panel'];
        }
        if (!empty($pg_res['ok'])) {
            Editmessagetext($from_id, $message_id, strtr($pg_w['converted'], [
                '{username}' => htmlspecialchars($pg_svc['username']),
                '{product}' => htmlspecialchars((string) $pg_res['product']['name_product']),
            ]), json_encode(['inline_keyboard' => [[['text' => $pg_w['btnBack'], 'callback_data' => 'product_' . $pg_id, 'style' => 'primary']]]]));
            return;
        }
        if (($pg_res['error'] ?? '') === 'lowbal') {
            Editmessagetext($from_id, $message_id, strtr($pg_w['convertLowBalance'], [
                '{need}' => money($pg_res['need'], $pg_svc['currency']),
                '{balance}' => money($pg_res['have'], $pg_svc['currency']),
            ]), payg_topup_kb($textbotlang));
            return;
        }
        $pg_alert(['product' => $pg_w['convertNoProduct'], 'off' => $pg_w['notActive']][$pg_res['error'] ?? ''] ?? $pg_w['convertFailed']);
        return;
    }
    // 🗑 حذف کامل: one its owner had deleted and kept switched off
    if ($pg_svc === null || $pg_svc['status'] !== 'ended') {
        $pg_alert($pg_w['notActive']);
        return;
    }
    if ($pg_act === 'paygrm') {
        Editmessagetext($from_id, $message_id, strtr($pg_w['removeConfirm'], ['{username}' => htmlspecialchars($pg_svc['username'])]), json_encode(['inline_keyboard' => [
            [['text' => $pg_w['btnDeleteYes'], 'callback_data' => 'paygrmok_' . $pg_id, 'style' => 'danger']],
            [['text' => $pg_w['btnNo'], 'callback_data' => 'product_' . $pg_id, 'style' => 'primary']],
        ]]));
        return;
    }
    $pg_done = payg_locked($pg_id, function () use ($pg_id) {
        $svc = payg_row($pg_id);
        return ($svc !== null && $svc['status'] === 'ended') ? payg_delete($svc, 'purge') : null;
    });
    if (!is_array($pg_done)) {
        $pg_alert($pg_w['notActive']);
        return;
    }
    Editmessagetext($from_id, $message_id, strtr($pg_w['removedFully'], ['{username}' => htmlspecialchars($pg_done['username'])]), $pg_toList);
    return;
}
if ($user['step'] == "createusertest" || preg_match('/locationtest_(.*)/', $datain, $dataget) || ($text == $textbotlang['textbot']['userTest'] || $datain == "usertestbtn" || $text == "usertest")) {
    if ($datain == "ucancel") {
        deletemessage($from_id, $message_id);
        step('home', $from_id);
        update("user", "Processing_value", "0", "id", $from_id);
        update("user", "Processing_value_one", "0", "id", $from_id);
        update("user", "Processing_value_tow", "0", "id", $from_id);
        update("user", "Processing_value_four", "0", "id", $from_id);
        sendmessage($from_id, $textbotlang['users']['back'], $keyboard, 'html');
        return;
    }
    if ($datain == "usedefaultname") {
        $text = 'user' . substr(bin2hex(random_bytes(4)), 0, 8);
    }
    if (!mainmenu_btn_active($user['lang'] ?? 'fa', "text_usertest")) {
        sendmessage($from_id, $textbotlang['users']['usertest']['unavailable'], null, 'HTML');
        return;
    }
    $userlimit = select("user", "*", "id", $from_id, "select");
    if ($userlimit['limit_usertest'] <= 0 && !$admin_test_free) {
        sendmessage($from_id, $textbotlang['users']['usertest']['limitwarning'], $keyboard_buy, 'html');
        return;
    }
    if (phone_required_for($user) && $user['step'] != "get_number" && $user['number'] == "none") {
        sendmessage($from_id, $textbotlang['users']['number']['confirming'], $request_contact, 'HTML');
        update("user", "Processing_value", "verifyusertest", "id", $from_id);
        step('get_number', $from_id);
    }
    if ($user['number'] == "none" && phone_required_for($user))
        return;
    $locationproduct = panels_available_count($user['lang'] ?? 'fa', $user['agent'], 'test');
    // disabled (was auto-picking the single panel and skipping the picker entirely) -
    // pairs with the always-show-picker fix above so a single-panel setup still waits
    // for the explicit locationtest_{code} tap like the multi-panel case does
    if (false && $locationproduct == 1) {
        $panel = select("marzban_panel", "*", "TestAccount", "ONTestAccount", "select");
        if ($panel['hide_user'] != null) {
            $list_user = json_decode($panel['hide_user'], true);
            if (in_array($from_id, $list_user)) {
                sendmessage($from_id, $textbotlang['users']['usertest']['noPanel'], null, 'HTML');
                return;
            }
        }
        $location = $panel['code_panel'];
    } else {
        if (isset($dataget[1])) {
            // a panel was just picked from the list - take away the sticker that
            // came with it, the same way the purchase flow's location_ handler
            // does. It lives in the dedicated column now, so this no longer has
            // to guess whether the number in Processing_value_tow is a message
            // id or one of the other things that field carries.
            sell_sticker_retire($from_id);
            $location = $dataget[1];
        } else {
            if ($user['step'] != "createusertest") {
                return;
            } else {
                $location = $user['Processing_value_one'];
            }
        }
    }
    $marzban_list_get = select("marzban_panel", "*", "code_panel", $location, "select");
    if (username_method_is($marzban_list_get['MethodUsername'], 'users.customusername') || username_method_is($marzban_list_get['MethodUsername'], 'keyboard.customUsernameRandom')) {
        if ($user['step'] != "createusertest") {
            // the custom-username panels send the prompt as a brand new message
            // instead of editing in place, so the "select a panel" message (with
            // its now-stale panel-list keyboard) has to be deleted explicitly here -
            // the default-username panels already get this via the deletemessage()
            // in the final else below
            deletemessage($from_id, $message_id);
            step('createusertest', $from_id);
            update("user", "Processing_value_one", $location, "id", $from_id);
            $usertestPromptLang = $user['lang'] ?? 'fa';
            $usertestPromptText = strtr($textbotlang['users']['usertest']['selectUsernamePrompt'], [
                '{testtime}' => $marzban_list_get['time_usertest'],
                '{testvolume}' => $marzban_list_get['val_usertest'],
            ]);
            $usertestPromptKb = usertest_selectUsername_kb($usertestPromptLang, $textbotlang);
            $usernamePromptMsg = sendmessage($from_id, $usertestPromptText, $usertestPromptKb, 'html');
            update("user", "Processing_value_tow", (string) ($usernamePromptMsg['result']['message_id'] ?? 0), "id", $from_id);
            return;
        }
    } else {
        $name_panel = $location;
    }
    if ($user['step'] == "createusertest") {
        $name_panel = $user['Processing_value_one'];
        if (!preg_match('~(?!_)^[a-z][a-z\d_]{2,32}(?<!_)$~i', $text)) {
            sendmessage($from_id, $textbotlang['users']['invalidusername'], usertest_selectUsername_kb($user['lang'] ?? 'fa', $textbotlang), 'HTML');
            return;
        }
        if (ctype_digit((string) $user['Processing_value_tow'])) {
            deletemessage($from_id, (int) $user['Processing_value_tow']);
        }
        if ($datain === '') {
            // the user typed their own name (not a پیش‌فرض/ucancel callback
            // tap) - $message_id here is THAT typed message, not the prompt
            // (already handled above via Processing_value_tow) - clean it up
            // too so no trace of the raw typed username is left in the chat
            deletemessage($from_id, $message_id);
        }
    } else {
        deletemessage($from_id, $message_id);
    }
    if ($marzban_list_get['type'] == "Manualsale") {
        $stmt = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = :codepanel AND codeproduct = :codeproduct AND status = 'active'");
        $value = "usertest";
        $stmt->bindParam(':codepanel', $marzban_list_get['code_panel']);
        $stmt->bindParam(':codeproduct', $value);
        $stmt->execute();
        $configexits = $stmt->rowCount();
        if (intval($configexits) == 0) {
            sendmessage($from_id, $textbotlang['users']['sell']['stockFinished'], null, 'HTML');
            return;
        }
    }
    // an unlimited admin's own counter is left alone, so switching the limit
    // back on does not start them below zero
    if (!$admin_test_free) {
        $limit_usertest = $userlimit['limit_usertest'] - 1;
        update("user", "limit_usertest", $limit_usertest, "id", $from_id);
    }
    $randomString = bin2hex(random_bytes(4));
    $text = strtolower($text);
    $marzban_list_get = select("marzban_panel", "*", "code_panel", $name_panel, "select");
    $text = strtolower($text);
    $username_ac = generateUsername($from_id, $marzban_list_get['MethodUsername'], $user['username'], $randomString, $text, $marzban_list_get['namecustom'], $user['namecustom']);
    $username_ac = strtolower($username_ac);
    $DataUserOut = $ManagePanel->DataUser($marzban_list_get['name_panel'], $username_ac);
    $random_number = rand(1000000, 9999999);
    if (isset($DataUserOut['username']) || in_array($username_ac, $usernameinvoice)) {
        $username_ac = $random_number . "_" . $username_ac;
    }
    $datac = array(
        'expire' => strtotime(date("Y-m-d H:i:s", strtotime("+" . $marzban_list_get['time_usertest'] . "hours"))),
        'data_limit' => $marzban_list_get['val_usertest'] * 1048576,
        'from_id' => $from_id,
        'username' => $username,
        'type' => 'usertest'
    );
    $date = time();
    $notifctions = json_encode(array(
        'volume' => false,
        'time' => false,
    ));
    $stmt = $pdo->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username,time_sell, Service_location, name_product, price_product, Volume, Service_time,Status,notifctions) VALUES (?, ?,  ?, ?, ?, ?, ?,?,?,?,?)");
    $Status = "active";
    $info_product['name_product'] = $textbotlang['common']['labels']['testService5'];
    $info_product['price_product'] = "0";
    $Status = "active";
    $stmt->execute([$from_id, $randomString, $username_ac, $date, $marzban_list_get['name_panel'], $info_product['name_product'], $info_product['price_product'], $marzban_list_get['val_usertest'], $marzban_list_get['time_usertest'], $Status, $notifctions]);
    $dataoutput = $ManagePanel->createUser($marzban_list_get['name_panel'], "usertest", $username_ac, $datac);
    if ($dataoutput['username'] == null) {
        $dataoutput['msg'] = json_encode($dataoutput['msg']);
        sendmessage($from_id, $textbotlang['users']['usertest']['errorcreat'], $keyboard, 'html');
        $texterros = sprintf($textbotlang['Admin']['reportgroup']['errorTestAccountCreate'], $dataoutput['msg'], $from_id, $username, $marzban_list_get['name_panel']);
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $texterros,
                'parse_mode' => "HTML"
            ]);
        }
        step('home', $from_id);
        update("invoice", "Status", "Unsuccessful", "id_invoice", $randomString);
        return;
    }
    $output_config_link = "";
    $config = "";
    $output_config_link = $marzban_list_get['sublink'] == "onsublink" ? $dataoutput['subscription_url'] : "";
    // same as the purchase flow: {config} (and the QR) is the subscription link
    // alone, the configs go to {links} and the config page. They used to be
    // appended under the link, dumping every config into the message.
    if ($marzban_list_get['config'] == "onconfig" && is_array($dataoutput['configs'])) {
        foreach ($dataoutput['configs'] as $link) {
            $config .= "\n" . $link;
        }
    }

    $usertestinfo = usertest_help_kb($user['lang'] ?? 'fa', $textbotlang);
    if ($marzban_list_get['type'] == "WGDashboard") {
        $textbotlang['textbot']['afterText'] = $textbotlang['users']['sell']['created'];
    }
    $textbotlang['textbot']['afterText'] = $marzban_list_get['type'] == "ibsng" || $marzban_list_get['type'] == "mikrotik" ? $textbotlang['textbot']['afterPayIbsng'] : $textbotlang['textbot']['afterText'];
    $textcreatuser = str_replace('{username}', $dataoutput['username'], $textbotlang['textbot']['afterText']);
    $textcreatuser = str_replace('{name_service}', $textbotlang['common']['labels']['test'], $textcreatuser);
    $textcreatuser = str_replace('{location}', $marzban_list_get['name_panel'], $textcreatuser);
    $textcreatuser = str_replace('{day}', $marzban_list_get['time_usertest'], $textcreatuser);
    $textcreatuser = str_replace('{volume}', $marzban_list_get['val_usertest'], $textcreatuser);
    // the same two numbers written the way a person says them - one unit, the
    // one that fits, with the word in the reader's language. {day} and {volume}
    // above stay as the raw stored numbers, for captions that supply their own
    // unit.
    $ut_lang = $user['lang'] ?? 'fa';
    $textcreatuser = str_replace('{time_human}', service_time_human($marzban_list_get['time_usertest'], $ut_lang), $textcreatuser);
    $textcreatuser = str_replace('{volume_human}', service_volume_human($marzban_list_get['val_usertest'], $ut_lang), $textcreatuser);
    $textcreatuser = str_replace('{config}', "<code>{$output_config_link}</code>", $textcreatuser);
    $textcreatuser = str_replace('{links}', $config, $textcreatuser);
    $textcreatuser = str_replace('{links2}', $output_config_link, $textcreatuser);
    if ($marzban_list_get['type'] == "ibsng" || $marzban_list_get['type'] == "mikrotik") {
        $textcreatuser = str_replace('{password}', $dataoutput['subscription_url'], $textcreatuser);
        update("invoice", "user_info", $dataoutput['subscription_url'], "id_invoice", $randomString);
    }
    sendMessageService($marzban_list_get, $dataoutput['configs'], $output_config_link, $dataoutput['username'], $usertestinfo, $textcreatuser, $randomString, kind: 'usertest');
    step('home', $from_id);
    if (username_method_is($marzban_list_get['MethodUsername'], 'keyboard.customTextSequential') || username_method_is($marzban_list_get['MethodUsername'], 'keyboard.usernameSequential') || username_method_is($marzban_list_get['MethodUsername'], 'keyboard.numericIdSequential') || username_method_is($marzban_list_get['MethodUsername'], 'keyboard.agentCustomTextSequential')) {
        $value = intval($user['number_username']) + 1;
        update("user", "number_username", $value, "id", $from_id);
        if (username_method_is($marzban_list_get['MethodUsername'], 'keyboard.customTextSequential') || username_method_is($marzban_list_get['MethodUsername'], 'keyboard.agentCustomTextSequential')) {
            $value = intval($setting['numbercount']) + 1;
            update("setting", "numbercount", $value);
        }
    }
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['manageUser']['manageUserBtn'], 'callback_data' => 'manageuser_' . $from_id],
            ],
        ]
    ]);
    $timejalali = jdate('Y/m/d H:i:s');
    $text_report = sprintf($textbotlang['Admin']['reportgroup']['testAccountCreated'], $from_id, $username, $username_ac, $first_name, $marzban_list_get['name_panel'], $marzban_list_get['time_usertest'], $marzban_list_get['val_usertest'], $randomString, $user['agent'], $user['number'], $timejalali);
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $reporttest,
            'text' => $text_report,
            'parse_mode' => "HTML",
            'reply_markup' => $Response
        ]);
    }
} elseif ($text == $textbotlang['textbot']['help'] || $datain == "helpbtn" || $datain == "helpbtns" || $text == "/help" || $text == "help") {
    if (!mainmenu_btn_active($user['lang'] ?? 'fa', "text_help") || !help_section_on()) {
        sendmessage($from_id, $textbotlang['users']['help']['disablehelp'], null, 'HTML');
        return;
    }
    // help_rows_for_user() drops the shipped tutorials when the admin has the
    // "📦 آموزش‌های آماده" switch off, so "is there anything to show?" is asked
    // about what this customer would ACTUALLY see
    if (empty(help_rows_for_user())) {
        sendmessage($from_id, $textbotlang['users']['help']['disablehelp'], null, 'HTML');
        return;
    }
    // 'helpbtn' is the back button of a tutorial that was sent AS MEDIA: a photo
    // or video caption cannot be edited into a plain text menu, so that message
    // goes away and the menu is sent fresh. 'helpbtns' (text-only tutorial) still
    // edits the very same message in place, which keeps the chat from growing.
    if ($datain == "helpbtn") {
        deletemessage($from_id, $message_id);
    }
    // no separate on/off switch any more: the category screen shows itself when
    // the admin has actually put a tutorial in a category, and the flat list
    // shows when nothing is categorized. One less setting to get out of sync
    // with the data it describes.
    $help_has_categories = false;
    foreach (help_rows_for_user() as $help_row_chk) {
        $help_cat_chk = trim((string) ($help_row_chk['category'] ?? ''));
        if ($help_cat_chk !== '' && $help_cat_chk !== '0') {
            $help_has_categories = true;
            break;
        }
    }
    if ($help_has_categories) {
        // its own key, defaulting to the exact sentence this screen has always
        // shown - it used to borrow the purchase flow's category caption, so
        // rewording the shop's silently reworded the tutorial menu too
        $help_cat_caption = $textbotlang['users']['help']['categoryCaption'] ?? $textbotlang['users']['sell']['selectCategoryShort'];
        if ($datain == "helpbtns") {
            Editmessagetext($from_id, $message_id, $help_cat_caption, $json_list_helpـcategory, 'HTML');
        } else {
            sendmessage($from_id, $help_cat_caption, $json_list_helpـcategory, 'HTML');
        }
    } else {
        $helplist = help_rows_for_user();
        $helpidos = ['inline_keyboard' => []];
        $help_tut_buttons = [];
        foreach ($helplist as $result) {
            $help_resolved = help_resolve_lang($result, $user['lang']);
            $help_tut_buttons[(string) $result['id']] = ['text' => $help_resolved['name'], 'callback_data' => "helpos_{$result['id']}"];
        }
        // apply the admin's per-language tutorial order/width/emoji (📐
        // چیدمان / 🎭 ایموجی under 🎨 نمایش دسته‌بندی و آموزش‌ها)
        $help_tut_section = help_layout_section($user['lang'], 'tutorials');
        $help_tut_ordered = help_layout_visible(help_layout_apply_order(array_keys($help_tut_buttons), $help_tut_section['order']), $help_tut_section);
        foreach ($help_tut_ordered as $help_tut_key) {
            $help_tut_emoji = $help_tut_section['emoji'][$help_tut_key] ?? '';
            if ($help_tut_emoji !== '') {
                $help_tut_buttons[$help_tut_key]['text'] = $help_tut_emoji . ' ' . $help_tut_buttons[$help_tut_key]['text'];
            }
            $help_tut_color = $help_tut_section['color'][$help_tut_key] ?? '';
            if ($help_tut_color !== '' && in_array($help_tut_color, ['primary', 'success', 'danger'], true)) {
                $help_tut_buttons[$help_tut_key]['style'] = $help_tut_color;
            }
        }
        $helpidos['inline_keyboard'] = array_merge($helpidos['inline_keyboard'], help_layout_chunk_rows($help_tut_ordered, $help_tut_buttons, $help_tut_section['width']));
        if (feature_value('linkappstatus', $user['lang'] ?? 'fa', $setting['linkappstatus']) == "1") {
            $helpidos['inline_keyboard'][] = [
                ['text' => $textbotlang['keyboard']['appDownloadLink'], 'callback_data' => "linkappdownlod"],
            ];
        }
        $helpidos['inline_keyboard'][] = [
            ['text' => $textbotlang['users']['backmenu'], 'callback_data' => "backuser"],
        ];
        $json_list_help = json_encode($helpidos);
        // same story as the category caption above: users.selectoption is shared
        // with a dozen unrelated screens, so the tutorial list gets its own key
        $help_list_caption = $textbotlang['users']['help']['listCaption'] ?? $textbotlang['users']['selectoption'];
        if ($datain == "helpbtns") {
            Editmessagetext($from_id, $message_id, $help_list_caption, $json_list_help, 'HTML');
        } else {
            sendmessage($from_id, $help_list_caption, $json_list_help, 'HTML');
        }
    }
} elseif (preg_match('/^helpctgoryـ(.*)/', $datain, $dataget)) {
    $helplist = help_rows_for_user($dataget[1]);
    $helpidos = ['inline_keyboard' => []];
    $help_tut_buttons = [];
    foreach ($helplist as $result) {
        $help_resolved = help_resolve_lang($result, $user['lang']);
        $help_tut_buttons[(string) $result['id']] = ['text' => $help_resolved['name'], 'callback_data' => "helpos_{$result['id']}"];
    }
    $help_tut_section = help_layout_section($user['lang'], 'tutorials');
    $help_tut_ordered = help_layout_visible(help_layout_apply_order(array_keys($help_tut_buttons), $help_tut_section['order']), $help_tut_section);
    foreach ($help_tut_ordered as $help_tut_key) {
        $help_tut_emoji = $help_tut_section['emoji'][$help_tut_key] ?? '';
        if ($help_tut_emoji !== '') {
            $help_tut_buttons[$help_tut_key]['text'] = $help_tut_emoji . ' ' . $help_tut_buttons[$help_tut_key]['text'];
        }
        // tutorials are green out of the box (categories are blue) - an admin
        // colour set in 🎨 نمایش دسته‌بندی و آموزش‌ها still wins
        $help_tut_color = $help_tut_section['color'][$help_tut_key] ?? '';
        $help_tut_buttons[$help_tut_key]['style'] = in_array($help_tut_color, ['primary', 'success', 'danger'], true) ? $help_tut_color : 'success';
    }
    $helpidos['inline_keyboard'] = array_merge($helpidos['inline_keyboard'], help_layout_chunk_rows($help_tut_ordered, $help_tut_buttons, $help_tut_section['width']));
    // own-key button of this caption ('hb'), so its label/colour/emoji are
    // editable in 🎨 شخصی‌سازی ← 📚 پیام و دکمه‌های آموزش like every other button
    $help_cat_lang = $user['lang'] ?? 'fa';
    if (!bt_button_hidden($help_cat_lang, 'users.help.listCaption')) {
        $helpidos['inline_keyboard'][] = [
            bt_button($help_cat_lang, 'users.help.listCaption', $textbotlang['users']['help']['backToCategoriesBtn'] ?? $textbotlang['users']['backmenu'], "helpbtns", 'danger'),
        ];
    }
    $json_list_help = json_encode($helpidos);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['help']['listCaption'] ?? $textbotlang['users']['selectoption'], $json_list_help, 'HTML');
} elseif (preg_match('/^helpos_(.*)/', $datain, $dataget)) {
    deletemessage($from_id, $message_id);
    $helpid = $dataget[1];
    $helpdata = select("help", "*", "id", $helpid, "select");
    if ($helpdata !== false) {
        $help_resolved = help_resolve_lang($helpdata, $user['lang']);
        $help_back_cb = (strlen($help_resolved['media']) != 0) ? "helpbtn" : "helpbtns";
        // own-key button of the category caption ('hv'), so its label/colour/
        // emoji are editable in 🎨 شخصی‌سازی ← 📚 پیام و دکمه‌های آموزش
        $help_view_lang = $user['lang'] ?? 'fa';
        $help_view_rows = [];
        if (!bt_button_hidden($help_view_lang, 'users.help.categoryCaption')) {
            $help_view_rows[] = [
                bt_button($help_view_lang, 'users.help.categoryCaption', $textbotlang['users']['help']['backToCategoryListBtn'] ?? $textbotlang['users']['status']['backinfo'], $help_back_cb, 'danger'),
            ];
        }
        $backinfoss = json_encode(['inline_keyboard' => $help_view_rows]);
        help_send_content($from_id, $help_resolved, $backinfoss);
    }
} elseif ($text == $textbotlang['textbot']['support'] || $datain == "supportbtns" || $text == "/support") {
    if (!mainmenu_btn_active($user['lang'] ?? 'fa', "text_support")) {
        sendmessage($from_id, $textbotlang['users']['buttonDisabled'], null, 'HTML');
        return;
    }
    // 📨 تیکت‌های من (tickets.php), when this language has it on
    $tk_supportoption = function_exists('tk_support_keyboard') ? tk_support_keyboard($supportoption, $user['lang'] ?? 'fa') : $supportoption;
    if ($datain == "supportbtns") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['support']['btnsupport'], $tk_supportoption);
    } else {
        sendmessage($from_id, $textbotlang['users']['support']['btnsupport'], $tk_supportoption, 'HTML');
    }
} elseif ($datain == "support") {
    Editmessagetext($from_id, $message_id, $textbotlang['users']['support']['selectDepartment'], $list_departman, 'HTML');
} elseif (preg_match('/^departman_(.*)/', $datain, $dataget)) {
    $iddeparteman = $dataget[1];
    savedata("clear", "iddeparteman", $iddeparteman);
    deletemessage($from_id, $message_id);
    sendmessage($from_id, $textbotlang['users']['support']['sendMessage'], $backuser, 'HTML');
    step("gettextticket", $from_id);
} elseif ($user['step'] == "gettextticket" && $datain == '' && tk_update_has_content()) {
    // a photo, video, file or voice reaches support too, with its caption -
    // this step used to take text only, so a screenshot was never delivered
    $userdata = json_decode($user['Processing_value'], true);
    $departeman = select("departman", "*", "id", $userdata['iddeparteman'] ?? 0, "select");
    if (!is_array($departeman)) {
        step("home", $from_id);
        sendmessage($from_id, $textbotlang['users']['support']['selectDepartment'], $list_departman, 'HTML');
        return;
    }
    $tk_text = tk_text_from_update();
    $tk_media = tk_media_from_update();
    $timejalali = jdate('Y/m/d H:i:s');
    $randomString = tk_insert($departeman['idsupport'], $from_id, $departeman['name_departman'], $tk_text, $tk_media, 'Unseen');
    // the customer's words are text, not markup: a «<» no longer stops the report
    $textsuppoer = sprintf($textbotlang['Admin']['reportgroup']['supportMessage'], $from_id, $from_id, $timejalali, $username, htmlspecialchars($departeman['name_departman']), htmlspecialchars($tk_text), '');
    tk_notify_support($departeman['idsupport'], $textsuppoer, $tk_media, $randomString, $randomString);
    sendmessage($from_id, $textbotlang['users']['support']['sentForReview'], $keyboard, 'HTML');
    step("home", $from_id);
    if ((string) $departeman['idsupport'] !== (string) $from_id) {
        step("home", $departeman['idsupport']);
    }
} elseif (preg_match('/^Responsesupport_(\w+)/', $datain, $dataget)) {
    $idtraking = $dataget[1];
    $trakingdetail = tk_row($idtraking);
    // only an admin or the department's own supporter answers a ticket
    if ($trakingdetail === null || !tk_can_answer($from_id, $trakingdetail)) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => panel_texts()['Admin']['Tickets']['notYours'], 'show_alert' => true]);
        return;
    }
    if ($trakingdetail['status'] == "Answered") {
        sendmessage($from_id, $textbotlang['Admin']['messageBulk']['answeredByOther'], null, 'HTML');
        return;
    }
    sendmessage($from_id, panel_texts()['Admin']['Tickets']['answerPrompt'], $backuser, 'HTML');
    update("user", "Processing_value", $idtraking, "id", $from_id);
    step("getextsupport", $from_id);
} elseif ($user['step'] == "getextsupport" && $datain == '' && tk_update_has_content()) {
    $trakingdetail = tk_row($user['Processing_value']);
    if ($trakingdetail === null || !tk_can_answer($from_id, $trakingdetail)) {
        step("home", $from_id);
        return;
    }
    // the answer as the supporter wrote it (bold, links ... kept), a «<» in it no
    // longer making Telegram refuse the whole message; a photo or video goes too
    $tk_media = tk_media_from_update();
    tk_answer($trakingdetail['Tracking'], tk_text_from_update(), $tk_media);
    tk_send_answer($trakingdetail, tk_html_from_update(), $tk_media);
    sendmessage($from_id, $textbotlang['users']['support']['sentSuccess'], null, 'HTML');
    step("home", $from_id);
} elseif (preg_match('/^Responsesusera_(\w+)/', $datain, $dataget)) {
    $idtraking = $dataget[1];
    $tk_row = tk_row($idtraking);
    if ($tk_row === null || (string) $tk_row['iduser'] !== (string) $from_id) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => $textbotlang['users']['support']['tkGone'], 'show_alert' => true]);
        return;
    }
    sendmessage($from_id, $textbotlang['users']['support']['sendMessageText'], $backuser, 'HTML');
    update("user", "Processing_value", $idtraking, "id", $from_id);
    step("getextuserfors", $from_id);
} elseif ($user['step'] == "getextuserfors" && $datain == '' && tk_update_has_content()) {
    $trakingdetail = tk_row($user['Processing_value']);
    step("home", $from_id);
    if ($trakingdetail === null || (string) $trakingdetail['iduser'] !== (string) $from_id) {
        return;
    }
    $timejalali = jdate('Y/m/d H:i:s');
    $tk_text = tk_text_from_update();
    $tk_media = tk_media_from_update();
    // the same ticket: it keeps its first message's Tracking as its thread
    $tk_thread = tk_thread_of($trakingdetail);
    $randomString = tk_insert($trakingdetail['idsupport'], $trakingdetail['iduser'], $trakingdetail['name_departman'], $tk_text, $tk_media, 'Customerresponse', $tk_thread);
    $textsuppoer = sprintf($textbotlang['Admin']['reportgroup']['supportMessage2'], $from_id, $from_id, $timejalali, $username, htmlspecialchars($trakingdetail['name_departman']), htmlspecialchars($tk_text));
    tk_notify_support($trakingdetail['idsupport'], $textsuppoer, $tk_media, $randomString, $tk_thread);
    sendmessage($from_id, $textbotlang['users']['support']['sentForRequestReview'], $keyboard, 'HTML');
} elseif ($datain == "fqQuestions") {
    sendmessage($from_id, $textbotlang['textbot']['faqDesc'], null, 'HTML');
} elseif ($text == $textbotlang['textbot']['accountWallet'] || $datain == "account" || $text == "/wallet") {
    $dateacc = format_datetime('Y/m/d', null, $user['lang']);
    $current_time = time();
    $timeacc = format_datetime('H:i:s', $current_time, $user['lang']);
    if ($user['codeInvitation'] == null) {
        $randomString = bin2hex(random_bytes(6));
        update("user", "codeInvitation", $randomString, "id", $from_id);
        $user['codeInvitation'] = $randomString;
    }
    $first_name = htmlspecialchars($first_name);
    $Balanceuser = wallet_amount_text($user);
    if ($user['number'] == "none") {
        $numberphone = $textbotlang['common']['labels']['notSent'];
    } else {
        $numberphone = $user['number'];
    }
    if ($user['number'] == "confrim number by admin") {
        $numberphone = $textbotlang['common']['labels']['confirmedByAdminAlt'];
    } else {
        $numberphone = $numberphone;
    }
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :id_user AND name_product != :mp1 AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold')");
    $stmt->bindValue(':mp1', $textbotlang['common']['labels']['testServiceName'], PDO::PARAM_STR);
    $stmt->bindValue(':id_user', $from_id, PDO::PARAM_INT);
    $stmt->execute();
    $countorder = $stmt->rowCount();
    $stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_user = :from_id AND payment_Status = 'paid'");
    $stmt->execute([
        ':from_id' => $from_id
    ]);
    $countpayment = $stmt->rowCount();
    $groupuser = [
        'f' => $textbotlang['common']['roles']['normalAlt'],
        'n' => $textbotlang['common']['roles']['agentAlt'],
        'n2' => $textbotlang['common']['roles']['advancedAgentAlt'],
    ][$user['agent']];
    $userjoin = format_datetime('Y/m/d H:i:s', $user['register'], $user['lang']);
    if (score_on($user['lang'] ?? 'fa', $setting)) {
        $textscore = strtr($textbotlang['users']['affiliates']['accountScore'], ['{score}' => $user['score']]);
    } else {
        $textscore = "";
    }
    $textinvite = "";
    if (feature_value('verifybucodeuser', $user['lang'] ?? 'fa', $setting['verifybucodeuser']) == "onverify" and feature_value('verifystart', $user['lang'] ?? 'fa', $setting['verifystart']) == "onverify") {
        $textscore = sprintf($textbotlang['users']['affiliates']['referralLink'], $usernamebot, $user['codeInvitation']);
    }
    $tp_usernameDisplay = (!empty($user['username']) && $user['username'] !== 'none') ? ('@' . $user['username']) : $textbotlang['users']['account']['usernameNotSet'];
    $text_account = sprintf($textbotlang['users']['account']['infoSimple'], $tp_usernameDisplay, $from_id, $countorder, money($user['Balance'], currency_for_user($user)));
    if ($datain == "account") {
        Editmessagetext($from_id, $message_id, $text_account, $keyboardPanel);
    } else {
        sendmessage($from_id, $text_account, $keyboardPanel, 'HTML');
    }
    step('home', $from_id);
    return;
// The service name is asked for only when there is something to buy. With no
// product for this customer it led straight into «⭕️ محصولی یافت نشد» - after
// the name was typed, and with the name question's back keyboard still up.
// Skipping it hands the tap to the branch below, which says only that.
} elseif (($text == $textbotlang['textbot']['sell'] || $datain == "buy" || $datain == "buyback" || $datain == "buyfresh" || $text == "/buy" || $text == "buy" || $verify_resume === 'verifybuy') && $statusnote
    && products_available_count($user['lang'] ?? 'fa', $user['agent']) > 0) {
    if (phone_required_for($user) && $user['step'] != "get_number" && $user['number'] == "none") {
        sendmessage($from_id, $textbotlang['users']['number']['confirming'], $request_contact, 'HTML');
        update("user", "Processing_value", "verifybuy", "id", $from_id);
        step('get_number', $from_id);
    }
    if ($user['number'] == "none" && phone_required_for($user))
        return;
    if (!mainmenu_btn_active($user['lang'] ?? 'fa', "text_sell")) {
        sendmessage($from_id, $textbotlang['users']['buttonDisabled'], null, 'HTML');
        return;
    }
    if ($datain == "buy") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['notestep'], $backuser);
    } elseif ($datain == "buyback" || $datain == "buyfresh") {
        deletemessage($from_id, $message_id);
        sendmessage($from_id, $textbotlang['users']['sell']['notestep'], $backuser, 'HTML');
    } else {
        sendmessage($from_id, $textbotlang['users']['sell']['notestep'], $backuser, 'HTML');
    }
    step("statusnamecustom", $from_id);
    return;
} elseif ($text == $textbotlang['textbot']['sell'] || $datain == "buy" || $datain == "buybacktow" || $datain == "buyback" || $datain == "buyfresh" || $text == "/buy" || $text == "buy" || $user['step'] == "statusnamecustom" || $verify_resume === 'verifybuy') {
    if (!mainmenu_btn_active($user['lang'] ?? 'fa', "text_sell")) {
        sendmessage($from_id, $textbotlang['users']['buttonDisabled'], null, 'HTML');
        return;
    }
    if ($datain == "buyfresh") {
        // the no-service message is being replaced by a brand new screen, not edited
        deletemessage($from_id, $message_id);
        // ...and so is the sticker that came with it
        if (ctype_digit((string) ($user['menu_sticker_id'] ?? '')) && intval($user['menu_sticker_id']) > 0) {
            deletemessage($from_id, intval($user['menu_sticker_id']));
            update("user", "menu_sticker_id", "0", "id", $from_id);
        }
    }
    $locationproduct = $pdo->prepare("SELECT * FROM marzban_panel  WHERE status = 'active' AND (agent = ? OR agent = 'all')");
    $locationproduct->bindValue(1, $user['agent'], PDO::PARAM_STR);
    $locationproduct->execute();
    if (($locationproduct)->rowCount() == 0) {
        sendmessage($from_id, $textbotlang['users']['sell']['nullPanel'], null, 'HTML');
        return;
    }
    if (phone_required_for($user) && $user['step'] != "get_number" && $user['number'] == "none") {
        sendmessage($from_id, $textbotlang['users']['number']['confirming'], $request_contact, 'HTML');
        update("user", "Processing_value", "verifybuy", "id", $from_id);
        step('get_number', $from_id);
    }
    if ($user['number'] == "none" && phone_required_for($user))
        return;
    #-----------------------#
    // 🖥 نمایش انتخاب پنل (setting.statuspanelshow, toggled in 🛒 وضعیت قابلیت‌های
    // فروشگاه) mirrors the category toggle: ON (default, matches the behaviour
    // this always had until now) always shows the panel picker below, even with
    // one active panel. OFF restores the original auto-pick-when-there-is-only-
    // one-panel shortcut. With 2+ panels this condition is false either way, so
    // the picker always shows regardless of the toggle - there is no other way
    // to ask which panel the user wants.
    if (shop_feature_value('panelshow', $user['lang'] ?? 'fa', $setting['statuspanelshow']) != 'onpanelshow' && ($locationproduct)->rowCount() == 1) {
        $location = ($locationproduct)->fetch(PDO::FETCH_ASSOC)['name_panel'];
        $locationproduct = select("marzban_panel", "*", "name_panel", $location, "select");
        if ($locationproduct['hide_user'] != null) {
            $list_user = json_decode($locationproduct['hide_user'], true);
            if (in_array($from_id, $list_user)) {
                sendmessage($from_id, $textbotlang['users']['sell']['nullPanel'], null, 'HTML');
                return;
            }
        }
        $stmt = $pdo->prepare("SELECT * FROM invoice WHERE status = 'active' AND (status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold')");
        $stmt->execute();
        $countinovoice = $stmt->rowCount();
        if ($locationproduct['limit_panel'] != "unlimited") {
            if ($countinovoice >= $locationproduct['limit_panel']) {
                sendmessage($from_id, $textbotlang['users']['sell']['capacityFull'], null, 'HTML');
                return;
            }
        }
        if ($user['step'] == "statusnamecustom") {
            savedata('clear', "nameconfig", $text);
            savedata('save', "name_panel", $location);
            step("home", $from_id);
        } else {
            savedata('clear', "name_panel", $location);
        }
        if (shop_feature_value('categorytime', $user['lang'] ?? 'fa', $setting['statuscategory']) == "offcategory") {
            $marzban_list_get = $locationproduct;
            $nullproduct = products_available_count($user['lang'] ?? 'fa', $user['agent']);
            if ($nullproduct == 0) {
                // 🧩 no ready plans: the custom plan screen, when this panel offers it
                if (!cvol_open($user, $marzban_list_get, 'b', 'backuser', $datain == "buy" ? $message_id : 0)) {
                    sendmessage($from_id, $textbotlang['users']['sell']['nullProduct'], null, 'HTML');
                    step('home', $from_id);
                }
                return;
            }
            if (shop_feature_value('categroygenral', $user['lang'] ?? 'fa', $setting['statuscategorygenral']) == "oncategorys") {
                $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
                if (feature_value('statusnamecustom', $user['lang'] ?? 'fa', $setting['statusnamecustom']) == 'onnamecustom') {
                    $backuser = "buyback";
                } else {
                    $backuser = "backuser";
                }
                sell_screen($from_id, $datain == "buy" ? $message_id : 0, $textbotlang['users']['sell']['selectCategory'], KeyboardCategory($location, $user['agent'], $backuser), 2);
            } else {
                $query = "SELECT * FROM product WHERE (Location = '$location' OR Location = '/all')AND agent= '{$user['agent']}' AND (FIND_IN_SET('{$user['lang']}', lang) OR lang = 'all' OR lang IS NULL OR lang = '')";
                $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
                if (username_method_is($marzban_list_get['MethodUsername'], 'users.customusername') || username_method_is($marzban_list_get['MethodUsername'], 'keyboard.customUsernameRandom')) {
                    $datakeyboard = "prodcutservices_";
                } else {
                    $datakeyboard = "prodcutservice_";
                }
                $statuscustom = cvol_available($marzban_list_get, $user);
                $textproduct = $textbotlang['users']['sell']['serviceSelectFirst'];
                if (feature_value('statusnamecustom', $user['lang'] ?? 'fa', $setting['statusnamecustom']) == 'onnamecustom') {
                    $backuser = "buyback";
                } else {
                    $backuser = "backuser";
                }
                sell_screen($from_id, $datain == "buy" ? $message_id : 0, $textproduct, KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, $backuser, null, cvol_entry_cb('b', $marzban_list_get['code_panel'], 'cvpl')), 3);
            }
        } else {
            $nullproduct = products_available_count($user['lang'] ?? 'fa', $user['agent']);
            $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
            if ($nullproduct == 0) {
                // 🧩 no ready plans: the custom plan screen, when this panel offers it
                if (!cvol_open($user, $marzban_list_get, 'b', 'backuser', ($datain == "buy" || $datain == "buybacktow") ? $message_id : 0)) {
                    sendmessage($from_id, $textbotlang['users']['sell']['nullProduct'], null, 'HTML');
                }
                return;
            }
            $statuscustom = cvol_available($marzban_list_get, $user) ? cvol_entry_cb('b', $marzban_list_get['code_panel'], 'buy') : false;
            if ($statusnote) {
                $back = "buyback";
            } else {
                $back = "backuser";
            }
            $monthkeyboard = keyboardTimeCategory($marzban_list_get['name_panel'], $user['agent'], "productmonth_", $back, $statuscustom, false);
            if ($datain == "buy" || $datain == "buybacktow") {
                Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['selectDuration'], $monthkeyboard);
            } else {
                sendmessage($from_id, $textbotlang['users']['sell']['selectDuration'], $monthkeyboard, 'HTML');
            }
        }
        return;
    }
    if ($user['step'] == "statusnamecustom") {
        savedata('clear', "nameconfig", $text);
        step("home", $from_id);
    }
    // Nothing this customer can be sold: say so here, at step 1, instead of
    // opening a panel list that leads to an empty product screen two taps
    // later. Asked per language, so a shop that has set products up for one
    // language and not the other tells the truth to both.
    //
    // No keyboard either way: on a callback the panel caption and its buttons
    // are edited into this message in place, and on a fresh entry the message
    // is sent on its own - neither touches the reply keyboard, so the customer
    // keeps the menu they already had rather than being handed a back button.
    if (products_available_count($user['lang'] ?? 'fa', $user['agent']) === 0 && !cvol_any_available($user)) {
        $np_text = $textbotlang['users']['sell']['nullProduct'];
        if ($message_id && ($datain == "buy" || $datain == "buybacktow" || $datain == "buyback")) {
            Editmessagetext($from_id, $message_id, $np_text, null, 'HTML');
        } else {
            sendmessage($from_id, $np_text, null, 'HTML');
        }
        return;
    }
    // step 1 of the purchase flow - sell_screen() edits in place when the panel
    // caption has no sticker, and replaces the screen when it has one
    if ($datain == "buy" || $datain == "buybacktow" || $datain == "buyback") {
        sell_screen($from_id, $message_id, $textbotlang['textbot']['selectLocation'], $list_marzban_panel_user, 1);
    } else {
        sell_screen($from_id, 0, $textbotlang['textbot']['selectLocation'], $list_marzban_panel_user, 1);
    }
} elseif (preg_match('/^location_(.*)/', $datain, $dataget) || $datain == "backproduct") {
    // the 🔐 خرید اشتراک sticker belongs to the panel list only - once a panel
    // is chosen and the category/product screen takes over, remove it
    if (ctype_digit((string) $user['Processing_value_tow'])) {
        deletemessage($from_id, (int) $user['Processing_value_tow']);
        update("user", "Processing_value_tow", "", "id", $from_id);
        $user['Processing_value_tow'] = "";
    }
    $userdate = json_decode($user['Processing_value'], true);
    if ($datain != "backproduct") {
        $location = select("marzban_panel", "*", "code_panel", $dataget[1], "select")['name_panel'];
    } else {
        $location = $userdate['name_panel'];
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
    $locationproductcount = select("marzban_panel", "*", "name_panel", $location, "count");
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') AND  Service_location = :mp2");
    $stmt->execute([':mp2' => $marzban_list_get['name_panel']]);
    $countinovoice = $stmt->rowCount();
    if ($marzban_list_get['limit_panel'] != "unlimited") {
        if ($countinovoice >= $marzban_list_get['limit_panel']) {
            sendmessage($from_id, $textbotlang['users']['sell']['panelCapacityFull'], null, 'HTML');
            return;
        }
    }
    if ($statusnote) {
        savedata('save', "name_panel", $location);
    } else {
        savedata('clear', "name_panel", $location);
    }
    $nullproduct = products_available_count($user['lang'] ?? 'fa', $user['agent']);
    if ($nullproduct == 0) {
        // 🧩 no ready plans: the custom plan screen, when this panel offers it
        if (!cvol_open($user, $marzban_list_get, 'b', 'buybacktow', $message_id)) {
            sendmessage($from_id, $textbotlang['users']['sell']['nullProduct'], null, 'HTML');
            step('home', $from_id);
        }
        return;
    }
    if (shop_feature_value('categorytime', $user['lang'] ?? 'fa', $setting['statuscategory']) == "offcategory") {
        if (shop_feature_value('categroygenral', $user['lang'] ?? 'fa', $setting['statuscategorygenral']) == "oncategorys") {
            $marzban_list_get = select("marzban_panel", "*", "name_panel", $location, "select");
            sell_screen($from_id, $message_id, $textbotlang['users']['sell']['selectCategory'], KeyboardCategory($location, $user['agent'], "buybacktow"), 2);
        } else {
            $query = "SELECT * FROM product WHERE (Location = '$location' OR Location = '/all')AND agent= '{$user['agent']}' AND (FIND_IN_SET('{$user['lang']}', lang) OR lang = 'all' OR lang IS NULL OR lang = '')";
            if (username_method_is($marzban_list_get['MethodUsername'], 'users.customusername') || username_method_is($marzban_list_get['MethodUsername'], 'keyboard.customUsernameRandom')) {
                $datakeyboard = "prodcutservices_";
            } else {
                $datakeyboard = "prodcutservice_";
            }
            $statuscustom = cvol_available($marzban_list_get, $user);
            // back to the panels: buybacktow, like the category list's back. The
            // panel screen decides how it arrives (sell_screen) - edited in place
            // when it has no sticker, replaced together with its sticker when it
            // has one. This used to be buyfresh, which always deleted the message
            // and sent a new one, sticker or not.
            $back = "buybacktow";
            sell_screen($from_id, $message_id, $textbotlang['users']['sell']['serviceSelect'], KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, $back, null, cvol_entry_cb('b', $marzban_list_get['code_panel'], 'location_' . $marzban_list_get['code_panel'])), 3);
        }
    } else {
        $statuscustom = cvol_available($marzban_list_get, $user) ? cvol_entry_cb('b', $marzban_list_get['code_panel'], 'location_' . $marzban_list_get['code_panel']) : false;
        $monthkeyboard = keyboardTimeCategory($marzban_list_get['name_panel'], $user['agent'], "productmonth_", "buybacktow", $statuscustom, false);
        Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['selectDuration'], $monthkeyboard);
    }
} elseif (preg_match('/^categorynames_(.*)/', $datain, $dataget)) {
    $categorynames = $dataget[1];
    $categorynames = select("category", "remark", "id", $categorynames, "select")['remark'];
    $userdate = json_decode($user['Processing_value'], true);
    if (!is_array($userdate) || empty($userdate['name_panel'])) {
        // a category / duration button from an old message, after the purchase was left
        sendmessage($from_id, $textbotlang['users']['sell']['restartProcess'], null, 'HTML');
        step("home", $from_id);
        return;
    }
    if (isset($userdate['monthproduct'])) {
        $query = "SELECT * FROM product WHERE (Location = '{$userdate['name_panel']}' OR Location = '/all') AND agent= '{$user['agent']}' AND category = '$categorynames' AND Service_time = '{$userdate['monthproduct']}' AND (FIND_IN_SET('{$user['lang']}', lang) OR lang = 'all' OR lang IS NULL OR lang = '')";
    } else {
        $query = "SELECT * FROM product WHERE (Location = '{$userdate['name_panel']}' OR Location = '/all') AND agent= '{$user['agent']}' AND category = '$categorynames' AND (FIND_IN_SET('{$user['lang']}', lang) OR lang = 'all' OR lang IS NULL OR lang = '')";
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    if (username_method_is($marzban_list_get['MethodUsername'], 'users.customusername') || username_method_is($marzban_list_get['MethodUsername'], 'keyboard.customUsernameRandom')) {
        $datakeyboard = "prodcutservices_";
    } else {
        $datakeyboard = "prodcutservice_";
    }
    $statuscustom = cvol_available($marzban_list_get, $user);
    // picking a category used to delete this screen and post a brand new one,
    // which broke the "one screen changing" feel of the two steps before it
    sell_screen($from_id, $message_id, $textbotlang['users']['sell']['serviceSelectFirst'], KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, "location_{$marzban_list_get['code_panel']}", null, cvol_entry_cb('b', $marzban_list_get['code_panel'], 'categorynames_' . $dataget[1])), 3);
} elseif (preg_match('/^productmonth_(\w+)/', $datain, $dataget)) {
    $monthenumber = $dataget[1];
    $userdate = json_decode($user['Processing_value'], true);
    if (!is_array($userdate) || empty($userdate['name_panel'])) {
        // a category / duration button from an old message, after the purchase was left
        sendmessage($from_id, $textbotlang['users']['sell']['restartProcess'], null, 'HTML');
        step("home", $from_id);
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel']);
    if (shop_feature_value('categroygenral', $user['lang'] ?? 'fa', $setting['statuscategorygenral']) == "oncategorys") {
        savedata("save", "monthproduct", $monthenumber);
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
        $stmt = $pdo->prepare("SELECT * FROM marzban_panel  WHERE status = 'active' AND (agent = :mp3 OR agent = 'all')");
        $stmt->execute([':mp3' => $user['agent']]);
        $count_panel = $stmt->rowCount();
        if ($count_panel == 1) {
            $back = "buybacktow";
        } else {
            $back = "location_{$marzban_list_get['code_panel']}";
        }
        sell_screen($from_id, $message_id, $textbotlang['users']['sell']['selectCategory'], KeyboardCategory($marzban_list_get['name_panel'], $user['agent'], $back), 2);
    } else {
        $query = "SELECT * FROM product WHERE (Location = '{$userdate['name_panel']}' OR Location = '/all') AND agent= '{$user['agent']}' AND Service_time = '$monthenumber' AND (FIND_IN_SET('{$user['lang']}', lang) OR lang = 'all' OR lang IS NULL OR lang = '')";
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
        if (username_method_is($marzban_list_get['MethodUsername'], 'users.customusername') || username_method_is($marzban_list_get['MethodUsername'], 'keyboard.customUsernameRandom')) {
            $datakeyboard = "prodcutservices_";
        } else {
            $datakeyboard = "prodcutservice_";
        }
        $statuscustom = cvol_available($marzban_list_get, $user);
        sell_screen($from_id, $message_id, $textbotlang['users']['sell']['serviceSelectFirst'], KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, "location_{$marzban_list_get['code_panel']}", null, cvol_entry_cb('b', $marzban_list_get['code_panel'], 'productmonth_' . $monthenumber)), 3);
    }
} elseif (preg_match('/^prodcutservices_(.*)/', $datain, $dataget)) {
    $prodcut = $dataget[1];
    $userdate = json_decode($user['Processing_value'], true);
    update("user", "Processing_value_one", $prodcut, "id", $from_id);
    step('endstepuser', $from_id);
    deletemessage($from_id, $message_id);
    // the product screen is being left for the username prompt - take its
    // sticker with it
    sell_sticker_retire($from_id);
    $usernamePromptMsg = sendmessage($from_id, $textbotlang['users']['sell']['selectUsernamePrompt'], $selectUsernameKb, 'html');
    update("user", "Processing_value_tow", (string) ($usernamePromptMsg['result']['message_id'] ?? 0), "id", $from_id);
} elseif ($user['step'] == "endstepuser" || $user['step'] == "endstepusers" || preg_match('/prodcutservice_(.*)/', $datain, $dataget)) {
    if ($datain == "ucancel") {
        // ucancel used to be (mis-)handled inside the earlier statusnamecustom
        // branch, which no longer matches the REAL step active when this
        // button is actually shown (endstepuser/endstepusers) - that mismatch
        // is exactly why step('home', ...) never ran and the user got stuck
        deletemessage($from_id, $message_id);
        step('home', $from_id);
        update("user", "Processing_value", "0", "id", $from_id);
        update("user", "Processing_value_one", "0", "id", $from_id);
        update("user", "Processing_value_tow", "0", "id", $from_id);
        update("user", "Processing_value_four", "0", "id", $from_id);
        sendmessage($from_id, $textbotlang['users']['back'], $keyboard, 'html');
        return;
    }
    if ($datain == "usedefaultname") {
        // "پیش‌فرض" button: bot picks a random name itself, valid against the
        // same ^[a-z][a-z\d_]{2,32}$ rule the typed-input path enforces below
        $text = 'user' . substr(bin2hex(random_bytes(4)), 0, 8);
    }
    $userdate = json_decode($user['Processing_value'], true);
    if ($user['step'] == "endstepusers" || $user['step'] == "endstepuser") {
        $prodcut = $user['Processing_value_one'];
    } else {
        $prodcut = $dataget[1];
    }
    // a plan button from an old message, after the purchase was left: there is no
    // panel to buy from any more, so the customer starts over instead of reaching
    // an invoice made of empty fields
    $marzban_list_get = is_array($userdate) && !empty($userdate['name_panel'])
        ? select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select")
        : false;
    if (!is_array($marzban_list_get)) {
        sendmessage($from_id, $textbotlang['users']['sell']['restartProcess'], null, 'HTML');
        step("home", $from_id);
        return;
    }
    if ($marzban_list_get['status'] == "disable") {
        sendmessage($from_id, $textbotlang['users']['sell']['panelUnavailable'], $backuser, 'html');
        step("home", $from_id);
        return;
    }
    if (username_method_is($marzban_list_get['MethodUsername'], 'users.customusername') || username_method_is($marzban_list_get['MethodUsername'], 'keyboard.customUsernameRandom')) {
        if (!preg_match('~(?!_)^[a-z][a-z\d_]{2,32}(?<!_)$~i', $text)) {
            sendmessage($from_id, $textbotlang['users']['invalidusername'], $selectUsernameKb, 'HTML');
            return;
        }
        if (ctype_digit((string) $user['Processing_value_tow'])) {
            deletemessage($from_id, (int) $user['Processing_value_tow']);
        }
        if ($datain === '') {
            // the user typed their own name (not a پیش‌فرض/ucancel callback
            // tap) - $message_id here is THAT typed message, not the prompt
            // (already handled above via Processing_value_tow) - clean it up
            // too so no trace of the raw typed username is left in the chat
            deletemessage($from_id, $message_id);
        }
        $loc = $user['Processing_value_one'];
    } else {
        $loc = $prodcut;
    }
    update("user", "Processing_value_one", $loc, "id", $from_id);
    $parts = explode("_", $loc);
    if ($parts[0] == "customvolume") {
        // 🧩 a custom plan: priced by the panel's settings, as long as they still offer it
        $cv_p = cvol_parse_code($loc);
        $cv_q = $cv_p ? cvol_quote_for($marzban_list_get, $user, $cv_p['gb'], $cv_p['days']) : null;
        if ($cv_q === null || !cvol_minbal_ok($marzban_list_get, $user)) {
            sendmessage($from_id, cvol_tx($user['lang'] ?? 'fa')['unavailable'], null, 'HTML');
            return;
        }
        $info_product = ['Volume_constraint' => $cv_q['gb'], 'name_product' => cvol_title($user['lang'] ?? 'fa'), 'code_product' => 'customvolume', 'Service_time' => $cv_q['days'], 'price_product' => $cv_q['price'], 'currency' => $cv_q['cur'], 'note' => ''];
    } else {
        $info_product = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (Location = :location OR Location = '/all') AND (FIND_IN_SET(:userlang, lang) OR lang = 'all' OR lang IS NULL OR lang = '') LIMIT 1");
        $info_product->bindValue(':code_product', $loc, PDO::PARAM_STR);
        $info_product->bindValue(':location', $userdate['name_panel'], PDO::PARAM_STR);
        $info_product->bindValue(':userlang', $user['lang'] ?? 'fa', PDO::PARAM_STR);
        $info_product->execute();
        $info_product = $info_product->fetch(PDO::FETCH_ASSOC);
    }
    if (!isset($info_product['price_product'])) {
        sendmessage($from_id, $textbotlang['users']['Balance']['confirmError'], $keyboard, 'HTML');
        return;
    }
    // past every early return the purchase is committed and the product screen
    // is gone for good - its sticker must not stay behind above the invoice
    sell_sticker_retire($from_id);
    if (intval($user['pricediscount']) != 0) {
        $resultper = ($info_product['price_product'] * $user['pricediscount']) / 100;
        $info_product['price_product'] = $info_product['price_product'] - $resultper;
    }
    $randomString = bin2hex(random_bytes(2));
    $text = strtolower($text);
    $username_ac = generateUsername($from_id, $marzban_list_get['MethodUsername'], $username, $randomString, $text, $marzban_list_get['namecustom'], $user['namecustom']);
    $username_ac = strtolower($username_ac);
    $DataUserOut = $ManagePanel->DataUser($marzban_list_get['name_panel'], $username_ac);
    $random_number = rand(1000000, 9999999);
    if (isset($DataUserOut['username']) || in_array($username_ac, $usernameinvoice)) {
        $username_ac = $random_number . "_" . $username_ac;
    }
    if (isset($username_ac))
        update("user", "Processing_value_tow", $username_ac, "id", $from_id);
    if ((float) $info_product['Volume_constraint'] <= 0)
        $info_product['Volume_constraint'] = $textbotlang['users']['status']['unlimited'];
    if (intval($info_product['Service_time']) == 0)
        $info_product['Service_time'] = $textbotlang['users']['status']['unlimited'];
    // 💳 with پرداخت مستقیم on for this path the page has the extra button (directpay.php)
    $dp_path = (($info_product['code_product'] ?? '') === 'customvolume') ? 'cvol' : 'buy';
    $dp_confirm_kb = dpay_path_on($user['lang'] ?? 'fa', $dp_path)
        ? dpay_confirm_kb($user['lang'] ?? 'fa', $customer_texts ?? $textbotlang, "confirmandgetservice", "buybacktow", "dpaybuy")
        : $payment;
    $info_product_price_product = money($info_product['price_product'], $info_product['currency'] ?? null);
    $userBalance = money($user['Balance'], currency_for_user($user));
    $replacements = [
        '{username}' => $username_ac,
        '{name_product}' => $info_product['name_product'],
        '{Service_time}' => $info_product['Service_time'],
        '{note}' => $info_product['note'],
        '{price}' => $info_product_price_product,
        '{Volume}' => $info_product['Volume_constraint'],
        '{userBalance}' => $userBalance,
    ];
    $replacements = array_merge($replacements, bottext_user_placeholders($user, $from_id));
    $textin = strtr($textbotlang['textbot']['preInvoice'], $replacements);
    if ((float) $info_product['Volume_constraint'] <= 0) {
        $textin = str_replace($textbotlang['common']['units']['gb'], "", $textin);
    }
    if (!username_method_is($marzban_list_get['MethodUsername'], 'common.labels.customUsername') && !username_method_is($marzban_list_get['MethodUsername'], 'common.labels.customUsernameRandom')) {
        Editmessagetext($from_id, $message_id, $textin, $dp_confirm_kb);
    } else {
        sendmessage($from_id, $textin, $dp_confirm_kb, 'HTML');
    }
    step('payment', $from_id);
} elseif ($user['step'] == "payment" && ($datain == "confirmandgetservice" || $datain == "dpaybuy")) {
    $userdate = json_decode($user['Processing_value'], true);
    // $pats for customm service
    $parts = explode("_", $user['Processing_value_one']);
    // 💳 «پرداخت مستقیم» tapped on a page that no longer has it (the admin turned it off, or the path):
    // nothing is bought from the wallet in its place
    $dp_direct = ($datain == "dpaybuy");
    if ($dp_direct && !dpay_path_on($user['lang'] ?? 'fa', $parts[0] == "customvolume" ? 'cvol' : 'buy')) {
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => strip_tags(sf_text('directPayGone', $user['lang'] ?? 'fa')), 'show_alert' => true]);
        return;
    }
    Editmessagetext($from_id, $message_id, $text_inline, json_encode(['inline_keyboard' => []]));
    // $partsdic for discount value
    $partsdic = explode("_", $user['Processing_value_four']);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
    if ($marzban_list_get['status'] == "disable") {
        sendmessage($from_id, $textbotlang['users']['sell']['panelUnavailable'], $backuser, 'html');
        step("home", $from_id);
        return;
    }
    if ($parts[0] == "customvolume") {
        // 🧩 priced again by the panel's settings - the same as on the invoice, unless they changed
        $cv_p = cvol_parse_code($user['Processing_value_one']);
        $cv_q = $cv_p ? cvol_quote_for($marzban_list_get, $user, $cv_p['gb'], $cv_p['days']) : null;
        if ($cv_q === null || !cvol_minbal_ok($marzban_list_get, $user)) {
            sendmessage($from_id, cvol_tx($user['lang'] ?? 'fa')['unavailable'], null, 'HTML');
            return;
        }
        $info_product = ['Volume_constraint' => $cv_q['gb'], 'name_product' => cvol_title($user['lang'] ?? 'fa'), 'code_product' => 'customvolume', 'Service_time' => $cv_q['days'], 'price_product' => $cv_q['price'], 'data_limit_reset' => 'no_reset'];
    } else {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (Location = :location OR Location = '/all') AND (FIND_IN_SET(:userlang, lang) OR lang = 'all' OR lang IS NULL OR lang = '') LIMIT 1");
        $stmt->execute([
            ':code_product' => $user['Processing_value_one'],
            ':location' => $userdate['name_panel'],
            ':userlang' => $user['lang'] ?? 'fa',
        ]);
        $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!isset($info_product['price_product']))
        return;
    $priceproduct = $info_product['price_product'];
    if ($admin_buy_free) {
        $priceproduct = 0;
    }
    $username_ac = strtolower($user['Processing_value_tow']);
    $DataUserOut = $ManagePanel->DataUser($marzban_list_get['name_panel'], $username_ac);
    // this customer's own earlier try with the same name, never paid for (a
    // «افزایش موجودی» or a direct payment left halfway): it gives way to this one.
    // It held the name for good before - every new try was told to start over.
    if (!isset($DataUserOut['username']) && in_array($username_ac, $usernameinvoice)) {
        $stale = $pdo->prepare("DELETE FROM invoice WHERE username = ? AND id_user = ? AND LOWER(Status) = 'unpaid'");
        $stale->execute([$username_ac, $from_id]);
        $usernameinvoice = select("invoice", "username", null, null, "FETCH_COLUMN");
    }
    if (isset($DataUserOut['username']) || in_array($username_ac, $usernameinvoice)) {
        sendmessage($from_id, $textbotlang['users']['sell']['restartProcess'], null, 'HTML');
        return;
    }
    $date = time();
    $randomString = bin2hex(random_bytes(4));
    $random_number = rand(1000000, 9999999);
    if (in_array($randomString, $id_invoice)) {
        $randomString = $random_number . $randomString;
    }
    if ($marzban_list_get['type'] == "Manualsale") {
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $userdate['name_panel'], "select");
        $stmt = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = :codepanel AND codeproduct = :codeproduct AND status = 'active'");
        $stmt->bindParam(':codepanel', $marzban_list_get['code_panel']);
        $stmt->bindParam(':codeproduct', $info_product['code_product']);
        $stmt->execute();
        $configexits = $stmt->rowCount();
        if (intval($configexits) == 0) {
            sendmessage($from_id, $textbotlang['users']['sell']['stockFinishedBuyAnother'], null, 'HTML');
            return;
        }
    }
    if (intval($user['pricediscount']) != 0) {
        $result = ($priceproduct * $user['pricediscount']) / 100;
        $priceproduct = $priceproduct - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }
    $notifctions = json_encode(array(
        'volume' => false,
        'time' => false,
    ));
    // one tap, one purchase: a second tap on the same page (or Telegram sending
    // the tap again while the panel was slow) finds the step already taken
    $buy_claim = $pdo->prepare("UPDATE user SET step = 'buying' WHERE id = ? AND step = 'payment'");
    $buy_claim->execute([$from_id]);
    if ($buy_claim->rowCount() === 0) {
        return;
    }
    $stmt = $pdo->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username,time_sell, Service_location, name_product, price_product, Volume, Service_time,Status,note,refral,notifctions) VALUES (?,  ?, ?, ?, ?, ?, ?,?,?,?,?,?,?)");
    $Status = "unpaid";
    $stmt->execute([$from_id, $randomString, $username_ac, $date, $marzban_list_get['name_panel'], $info_product['name_product'], $priceproduct, $info_product['Volume_constraint'], $info_product['Service_time'], $Status, $userdate['nameconfig'], $user['affiliates'], $notifctions]);
    // 🧩 marked, so its renewal and its count in 📊 know what it was
    if ($parts[0] == "customvolume") {
        cvol_mark_invoice($randomString);
    }
    // 💳 paid directly: the whole price from a gateway, the wallet untouched (directpay.php)
    if ($dp_direct && intval($priceproduct) != 0) {
        if (!dpay_start_buy($user, $randomString, $priceproduct, $message_id, $username_ac)) {
            step('home', $from_id);
        }
        return;
    }
    if ($priceproduct > $user['Balance'] && $user['agent'] != "n2" && intval($priceproduct) != 0) {
        // 💰 what is lacking, and a «افزایش موجودی» that carries it: the invoice
        // just made is what the payment will complete (shortfall.php)
        sf_purchase_short($user, $randomString, $priceproduct, $message_id);
        return;
    }
    if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
        if (intval($user['Balance'] - $priceproduct) < intval("-" . $user['maxbuyagent'])) {
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            step('home', $from_id);
            return;
        }
    }
    Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['creating'], null);
    $datetimestep = strtotime("+" . $info_product['Service_time'] . "days");
    if ($info_product['Service_time'] == 0) {
        $datetimestep = 0;
    } else {
        $datetimestep = strtotime(date("Y-m-d H:i:s", $datetimestep));
    }
    $datac = array(
        'expire' => $datetimestep,
        'data_limit' => $info_product['Volume_constraint'] * pow(1024, 3),
        'from_id' => $from_id,
        'username' => $username,
        'type' => 'buy'
    );
    $Shoppinginfo = afterpay_help_kb($user['lang'] ?? 'fa', $textbotlang);
    $dataoutput = $ManagePanel->createUser($marzban_list_get['name_panel'], $info_product['code_product'], $username_ac, $datac);
    if (!isset($dataoutput['username']) || $dataoutput['username'] === null || $dataoutput['username'] === '') {
        $errorMessage = $dataoutput['msg'] ?? 'unknown error';
        if (is_array($errorMessage) || is_object($errorMessage)) {
            $errorMessage = json_encode($errorMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $errorMessage = (string) $errorMessage;
        }
        $dataoutput['msg'] = $errorMessage;
        sendmessage($from_id, $textbotlang['users']['sell']['errorConfig'], $keyboard, 'HTML');
        $texterros = sprintf($textbotlang['Admin']['reportgroup']['errorSubscriptionCreate'], $dataoutput['msg'], $from_id, $username, $marzban_list_get['name_panel']);
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $texterros,
                'parse_mode' => "HTML"
            ]);
        }
        step('home', $from_id);
        return;
    }
    // the service exists: the invoice and the wallet follow at once, before any
    // message - a failure further down can no longer leave one without the other
    update("invoice", "Status", "active", "id_invoice", $randomString);
    if (intval($priceproduct) != 0) {
        $Balance_prim = $user['Balance'] - $priceproduct;
        balance_add($from_id, -$priceproduct);
    }
    $output_config_link = "";
    $config = "";
    $output_config_link = $marzban_list_get['sublink'] == "onsublink" ? $dataoutput['subscription_url'] : "";
    if ($marzban_list_get['config'] == "onconfig" && is_array($dataoutput['configs'])) {
        foreach ($dataoutput['configs'] as $link) {
            $config .= "\n" . $link;
        }
    }
    $textbotlang['textbot']['afterPay'] = $marzban_list_get['type'] == "Manualsale" ? $textbotlang['textbot']['manual'] : $textbotlang['textbot']['afterPay'];
    $textbotlang['textbot']['afterPay'] = $marzban_list_get['type'] == "WGDashboard" ? $textbotlang['textbot']['wgDashboard'] : $textbotlang['textbot']['afterPay'];
    $textbotlang['textbot']['afterPay'] = $marzban_list_get['type'] == "ibsng" || $marzban_list_get['type'] == "mikrotik" ? $textbotlang['textbot']['afterPayIbsng'] : $textbotlang['textbot']['afterPay'];
    if (intval($info_product['Service_time']) == 0)
        $info_product['Service_time'] = $textbotlang['users']['status']['unlimited'];
    if ((float) $info_product['Volume_constraint'] <= 0)
        $info_product['Volume_constraint'] = $textbotlang['users']['status']['unlimited'];
    $textcreatuser = str_replace('{username}', "<code>{$dataoutput['username']}</code>", $textbotlang['textbot']['afterPay']);
    $textcreatuser = str_replace('{name_service}', $info_product['name_product'], $textcreatuser);
    $textcreatuser = str_replace('{location}', $marzban_list_get['name_panel'], $textcreatuser);
    $textcreatuser = str_replace('{day}', $info_product['Service_time'], $textcreatuser);
    $textcreatuser = str_replace('{volume}', $info_product['Volume_constraint'], $textcreatuser);
    $textcreatuser = str_replace('{time_human}', service_days_text(intval($info_product['Service_time']), $textbotlang), $textcreatuser);
    $textcreatuser = str_replace('{volume_human}', service_volume_text((float) $info_product['Volume_constraint'] * 1024, $textbotlang), $textcreatuser);
    $textcreatuser = str_replace('{config}', "<code>{$output_config_link}</code>", $textcreatuser);
    $textcreatuser = str_replace('{links}', $config, $textcreatuser);
    $textcreatuser = str_replace('{links2}', $output_config_link, $textcreatuser);
    if ((float) $info_product['Volume_constraint'] <= 0) {
        $textcreatuser = str_replace($textbotlang['common']['units']['gigabyte'], "", $textcreatuser);
    }
    if ($marzban_list_get['type'] == "Manualsale" || $marzban_list_get['type'] == "ibsng" || $marzban_list_get['type'] == "mikrotik") {
        $textcreatuser = str_replace('{password}', $dataoutput['subscription_url'], $textcreatuser);
        update("invoice", "user_info", $dataoutput['subscription_url'], "id_invoice", $randomString);
    }
    sendMessageService($marzban_list_get, $dataoutput['configs'], $output_config_link, $dataoutput['username'], $Shoppinginfo, $textcreatuser, $randomString);
    if (username_method_is($marzban_list_get['MethodUsername'], 'keyboard.customTextSequential') || username_method_is($marzban_list_get['MethodUsername'], 'keyboard.usernameSequential') || username_method_is($marzban_list_get['MethodUsername'], 'keyboard.numericIdSequential') || username_method_is($marzban_list_get['MethodUsername'], 'keyboard.agentCustomTextSequential')) {
        $value = intval($user['number_username']) + 1;
        update("user", "number_username", $value, "id", $from_id);
        if (username_method_is($marzban_list_get['MethodUsername'], 'keyboard.customTextSequential') || username_method_is($marzban_list_get['MethodUsername'], 'keyboard.agentCustomTextSequential')) {
            $value = intval($setting['numbercount']) + 1;
            update("setting", "numbercount", $value);
        }
    }
    $affiliatescommission = select("affiliates", "*", null, null, "select");
    $marzbanporsant_one_buy = select("affiliates", "*", null, null, "select");
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE name_product != :name_product  AND id_user = :id_user AND Status != 'Unpaid'");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->bindParam(':name_product', $textbotlang['common']['labels']['testServiceName']);
    $stmt->execute();
    $countinvoice = $stmt->rowCount();
    // the commission is credited to the referrer, so their language's
    // percentage/flags apply - same value their own referral screen advertises
    $aff_reflang = ($user['affiliates'] != null && intval($user['affiliates']) != 0)
        ? (select("user", "*", "id", $user['affiliates'], "select")['lang'] ?? 'fa')
        : 'fa';
    // ...and none to a referrer ⛔️ kept out of 💼 (aff_blocked)
    if (aff_classic_on($aff_reflang) && feature_setting_value('aff_commission', $aff_reflang, $affiliatescommission['status_commission']) == "oncommission" && ($user['affiliates'] != null && intval($user['affiliates']) != 0) && !aff_blocked($user['affiliates'], 'c')) {
        if (feature_setting_value('aff_firstbuy', $aff_reflang, $marzbanporsant_one_buy['porsant_one_buy']) == "on_buy_porsant") {
            if ($countinvoice == 1) {
                $result = ($priceproduct * feature_setting_value('aff_percent', $aff_reflang, $setting['affiliatespercentage'])) / 100;
                $user_Balance = select("user", "*", "id", $user['affiliates'], "select");
                if (score_on($aff_reflang, $setting) and !in_array($user['affiliates'], $admin_ids)) {
                    sendmessage($user['affiliates'], payer_texts($user['affiliates'])['users']['affiliates']['pointsEarned2Alt'], null, 'html');
                    $scorenew = $user_Balance['score'] + 2;
                    update("user", "score", $scorenew, "id", $user['affiliates']);
                }
                // a share of what the buyer paid, so in the buyer's currency
                wallet_credit($user['affiliates'], $result, currency_for_user($user));
                $result = money($result, currency_for_user($user));
                $dateacc = date('Y/m/d H:i:s');
                $textadd = sprintf(payer_texts($user['affiliates'])['users']['affiliates']['commissionPaid'], $result);
                $textreportport = sprintf(panel_texts()['Admin']['reportgroup']['commissionPaid'], $result, $user['affiliates'], $from_id, $dateacc);
                if (strlen($setting['Channel_Report']) > 0) {
                    telegram('sendmessage', [
                        'chat_id' => $setting['Channel_Report'],
                        'message_thread_id' => $porsantreport,
                        'text' => $textreportport,
                        'parse_mode' => "HTML"
                    ]);
                }
                sendmessage($user['affiliates'], $textadd, null, 'HTML');
            }
        } else {

            $result = ($priceproduct * feature_setting_value('aff_percent', $aff_reflang, $setting['affiliatespercentage'])) / 100;
            $user_Balance = select("user", "*", "id", $user['affiliates'], "select");
            if (score_on($aff_reflang, $setting) and !in_array($user['affiliates'], $admin_ids)) {
                sendmessage($user['affiliates'], payer_texts($user['affiliates'])['users']['affiliates']['pointsEarned2Alt'], null, 'html');
                $scorenew = $user_Balance['score'] + 2;
                update("user", "score", $scorenew, "id", $user['affiliates']);
            }
            // a share of what the buyer paid, so in the buyer's currency
            wallet_credit($user['affiliates'], $result, currency_for_user($user));
            $result = money($result, currency_for_user($user));
            $dateacc = date('Y/m/d H:i:s');
            $textadd = sprintf(payer_texts($user['affiliates'])['users']['affiliates']['commissionPaid'], $result);
            $textreportport = sprintf(panel_texts()['Admin']['reportgroup']['commissionPaid2'], $result, $user['affiliates'], $from_id, $dateacc);
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $porsantreport,
                    'text' => $textreportport,
                    'parse_mode' => "HTML"
                ]);
            }
            sendmessage($user['affiliates'], $textadd, null, 'HTML');
        }
    }
    if (score_on($user['lang'] ?? 'fa', $setting) and !in_array($from_id, $admin_ids)) {
        sendmessage($from_id, $textbotlang['users']['affiliates']['pointsEarned1Alt'], null, 'html');
        $scorenew = $user['score'] + 1;
        update("user", "score", $scorenew, "id", $from_id);
    }
    $balanceformatsell = money(select("user", "Balance", "id", $from_id, "select")['Balance'], currency_for_user($user), false);
    $textonebuy = "";
    if ($countinvoice == 1) {
        $textonebuy = $textbotlang['common']['labels']['firstPurchaseAlt'];
    }
    $balanceformatsellbefore = wallet_amount_text($user);
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['Admin']['manageUser']['manageUserBtn'], 'callback_data' => 'manageuser_' . $from_id],
            ],
        ]
    ]);
    $timejalali = jdate('Y/m/d H:i:s');
    $text_report = sprintf($textbotlang['Admin']['reportgroup']['accountCreated'], $textonebuy, $from_id, $username, $username_ac, $first_name, $userdate['name_panel'], $info_product['name_product'], $info_product['Service_time'], $info_product['Volume_constraint'], $balanceformatsellbefore, $balanceformatsell, $randomString, $user['agent'], $user['number'], $info_product['category'], $info_product['price_product'], $priceproduct, $timejalali);
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $buyreport,
            'text' => $text_report,
            'parse_mode' => "HTML",
            'reply_markup' => $Response
        ]);
    }
    update("user", "Processing_value_four", "none", "id", $from_id);
    step('home', $from_id);
} elseif ($text == $textbotlang['keyboard']['bulkPurchase'] || $datain == "kharidanbuh" || $verify_resume === 'verifybulk') {
    if (feature_value('bulkbuy', $user['lang'] ?? 'fa', $setting['bulkbuy']) == "offbulk") {
        sendmessage($from_id, $textbotlang['users']['Major']['disabled'], null, 'HTML');
        return;
    }
    // the admin saves this in shopSetting (and table.php seeds it there); it was
    // read from PaySetting, where no such row exists, so it never applied
    $PaySetting = select("shopSetting", "value", "Namevalue", "minbalancebuybulk", "select")['value'] ?? 0;
    if ($user['Balance'] < $PaySetting && !$admin_buy_free) {
        sendmessage($from_id, strtr($textbotlang['users']['Major']['minBalance'], ['{PaySetting}' => $PaySetting]), null, 'HTML');
        return;
    }
    $locationproduct = $pdo->prepare("SELECT * FROM marzban_panel");
    $locationproduct->execute();
    if (($locationproduct)->rowCount() == 0) {
        sendmessage($from_id, $textbotlang['users']['sell']['nullPanel'], null, 'HTML');
        return;
    }
    if (phone_required_for($user) && $user['step'] != "get_number" && $user['number'] == "none") {
        sendmessage($from_id, $textbotlang['users']['number']['confirming'], $request_contact, 'HTML');
        update("user", "Processing_value", "verifybulk", "id", $from_id);
        step('get_number', $from_id);
    }
    if ($user['number'] == "none" && phone_required_for($user))
        return;
    #-----------------------#
    if ($datain == "kharidanbuh") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['Major']['title'], $backuser, 'HTML');
    } else {
        sendmessage($from_id, $textbotlang['users']['Major']['title'], $backuser, 'HTML');
    }
    step('getcountconfig', $from_id);
} elseif ($user['step'] == "getcountconfig") {
    if (intval($text) > 15 || intval($text) < 1)
        return sendmessage($from_id, $textbotlang['common']['invalidInput'], $backuser, 'HTML');
    if (!is_numeric($text))
        return sendmessage($from_id, $textbotlang['users']['Balance']['errorprice'], null, 'HTML');
    sendmessage($from_id, $textbotlang['textbot']['selectLocation'], $list_marzban_panel_userom, 'HTML');
    update("user", "Processing_value_four", $text, "id", $from_id);
    step('home', $from_id);
} elseif (preg_match('/^locationom_(.*)/', $datain, $dataget)) {
    $location = select("marzban_panel", "*", "code_panel", $dataget[1], "select")['name_panel'];
    $marzban_list_get = select("marzban_panel", "*", "code_panel", $dataget[1], "select");
    update("user", "Processing_value", $location, "id", $from_id);
    $nullproduct = products_available_count($user['lang'] ?? 'fa', $user['agent']);
    if ($nullproduct == 0) {
        // 🧩 no ready plans: several custom plans at once, when this panel offers them
        $user['Processing_value'] = $location;
        if (!cvol_open($user, $marzban_list_get, 'o', 'backuser', $message_id)) {
            sendmessage($from_id, $textbotlang['users']['sell']['nullProduct'], null, 'HTML');
        }
        return;
    }
    if (username_method_is($marzban_list_get['MethodUsername'], 'users.customusername') || username_method_is($marzban_list_get['MethodUsername'], 'keyboard.customUsernameRandom')) {
        $datakeyboard = "prodcutservicesom_";
    } else {
        $datakeyboard = "prodcutserviceom_";
    }
    $statuscustom = cvol_available($marzban_list_get, $user);
    $query = "SELECT * FROM product WHERE (Location = '$location' OR Location = '/all')AND agent= '{$user['agent']}' AND (FIND_IN_SET('{$user['lang']}', lang) OR lang = 'all' OR lang IS NULL OR lang = '')";
    Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['serviceSelect'], KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], $datakeyboard, $statuscustom, "backuser", null, cvol_entry_cb('o', $marzban_list_get['code_panel'], 'locationom_' . $marzban_list_get['code_panel'])));
} elseif (preg_match('/^prodcutservicesom_(.*)/', $datain, $dataget)) {
    $prodcut = $dataget[1];
    update("user", "Processing_value_one", $prodcut, "id", $from_id);
    step('endstepuserom', $from_id);
    $usernamePromptMsg = sendmessage($from_id, $textbotlang['users']['sell']['selectUsernamePrompt'], $selectUsernameKb, 'html');
    update("user", "Processing_value_tow", (string) ($usernamePromptMsg['result']['message_id'] ?? 0), "id", $from_id);
} elseif ($user['step'] == "endstepuserom" || $user['step'] == "endstepusersom" || preg_match('/prodcutserviceom_(.*)/', $datain, $dataget)) {
    if ($datain == "ucancel") {
        // ucancel used to be (mis-)handled inside the earlier statusnamecustom
        // branch, which no longer matches the REAL step active when this
        // button is actually shown (endstepuser/endstepusers) - that mismatch
        // is exactly why step('home', ...) never ran and the user got stuck
        deletemessage($from_id, $message_id);
        step('home', $from_id);
        update("user", "Processing_value", "0", "id", $from_id);
        update("user", "Processing_value_one", "0", "id", $from_id);
        update("user", "Processing_value_tow", "0", "id", $from_id);
        update("user", "Processing_value_four", "0", "id", $from_id);
        sendmessage($from_id, $textbotlang['users']['back'], $keyboard, 'html');
        return;
    }
    if ($datain == "usedefaultname") {
        $text = 'user' . substr(bin2hex(random_bytes(4)), 0, 8);
    }
    if ($user['step'] == "endstepusersom" || $user['step'] == "endstepuserom") {
        $prodcut = $user['Processing_value_one'];
    } else {
        $prodcut = $dataget[1];
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (username_method_is($marzban_list_get['MethodUsername'], 'users.customusername') || username_method_is($marzban_list_get['MethodUsername'], 'keyboard.customUsernameRandom')) {
        if (!preg_match('~(?!_)^[a-z][a-z\d_]{2,32}(?<!_)$~i', $text)) {
            sendmessage($from_id, $textbotlang['users']['invalidusername'], $selectUsernameKb, 'HTML');
            return;
        }
        if (ctype_digit((string) $user['Processing_value_tow'])) {
            deletemessage($from_id, (int) $user['Processing_value_tow']);
        }
        if ($datain === '') {
            // the user typed their own name (not a پیش‌فرض/ucancel callback
            // tap) - $message_id here is THAT typed message, not the prompt
            // (already handled above via Processing_value_tow) - clean it up
            // too so no trace of the raw typed username is left in the chat
            deletemessage($from_id, $message_id);
        }
        $loc = $user['Processing_value_one'];
    } else {
        $loc = $prodcut;
    }
    update("user", "Processing_value_one", $loc, "id", $from_id);
    $parts = explode("_", $loc);
    if ($parts[0] == "customvolume") {
        // 🧩 a custom plan: priced by the panel's settings, as long as they still offer it
        $cv_p = cvol_parse_code($loc);
        $cv_q = $cv_p ? cvol_quote_for($marzban_list_get, $user, $cv_p['gb'], $cv_p['days']) : null;
        if ($cv_q === null || !cvol_minbal_ok($marzban_list_get, $user)) {
            sendmessage($from_id, cvol_tx($user['lang'] ?? 'fa')['unavailable'], null, 'HTML');
            return;
        }
        $info_product = ['Volume_constraint' => $cv_q['gb'], 'name_product' => cvol_title($user['lang'] ?? 'fa'), 'code_product' => 'customvolume', 'Service_time' => $cv_q['days'], 'price_product' => $cv_q['price'], 'currency' => $cv_q['cur'], 'note' => ''];
    } else {
        $__q8 = $pdo->prepare("SELECT * FROM product WHERE code_product = ? AND (Location = ? or Location = '/all') AND (FIND_IN_SET(?, lang) OR lang = 'all' OR lang IS NULL OR lang = '') LIMIT 1");
        $__q8->bindValue(1, $loc, PDO::PARAM_STR);
        $__q8->bindValue(2, $user['Processing_value'], PDO::PARAM_STR);
        $__q8->bindValue(3, $user['lang'] ?? 'fa', PDO::PARAM_STR);
        $__q8->execute();
        $info_product = $__q8->fetch(PDO::FETCH_ASSOC);
    }
    $randomString = bin2hex(random_bytes(2));
    $username_ac = generateUsername($from_id, $marzban_list_get['MethodUsername'], $username, $randomString, $text, $marzban_list_get['namecustom'], $user['namecustom']);
    $username_ac = strtolower($username_ac);
    update("user", "Processing_value_tow", $username_ac, "id", $from_id);
    if ($info_product['Volume_constraint'] == 0)
        $info_product['Volume_constraint'] = $textbotlang['users']['status']['unlimited'];
    if ($info_product['Service_time'] == 0)
        $info_product['Service_time'] = $textbotlang['users']['status']['unlimited'];
    $info_product['price_product'] = intval($info_product['price_product']) * intval($user['Processing_value_four']);
    $price_product_format = money($info_product['price_product'], $info_product['currency'] ?? null);
    $userbalancepish = money($user['Balance'], currency_for_user($user));
    $textin = sprintf($textbotlang['users']['sell']['preInvoice2'], $username_ac, $info_product['name_product'], $info_product['Service_time'], $price_product_format, $info_product['Volume_constraint'], $userbalancepish, $user['Processing_value_four']);
    sendmessage($from_id, $textin, $paymentom, 'HTML');
    step('payments', $from_id);
} elseif ($user['step'] == "payments" && $datain == "confirmandgetservice") {
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $parts = explode("_", $user['Processing_value_one']);
    if ($parts[0] == "customvolume") {
        // 🧩 priced again by the panel's settings - the same as on the invoice, unless they changed
        $cv_p = cvol_parse_code($user['Processing_value_one']);
        $cv_q = $cv_p ? cvol_quote_for($marzban_list_get, $user, $cv_p['gb'], $cv_p['days']) : null;
        if ($cv_q === null || !cvol_minbal_ok($marzban_list_get, $user)) {
            sendmessage($from_id, cvol_tx($user['lang'] ?? 'fa')['unavailable'], null, 'HTML');
            return;
        }
        $info_product = ['Volume_constraint' => $cv_q['gb'], 'name_product' => cvol_title($user['lang'] ?? 'fa'), 'code_product' => 'customvolume', 'Service_time' => $cv_q['days'], 'price_product' => $cv_q['price'], 'data_limit_reset' => 'no_reset'];
    } else {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE code_product = :code_product AND (Location = :location OR Location = '/all') AND (FIND_IN_SET(:userlang, lang) OR lang = 'all' OR lang IS NULL OR lang = '') LIMIT 1");
        $stmt->bindValue(':code_product', $user['Processing_value_one'], PDO::PARAM_STR);
        $stmt->bindValue(':location', $user['Processing_value'], PDO::PARAM_STR);
        $stmt->bindValue(':userlang', $user['lang'] ?? 'fa', PDO::PARAM_STR);
        $stmt->execute();
        $info_product = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (empty($info_product['price_product']) || empty($info_product['price_product']))
        return;
    $priceproduct = $info_product['price_product'] * $user['Processing_value_four'];
    if ($admin_buy_free) {
        $priceproduct = 0;
    }
    Editmessagetext($from_id, $message_id, $text_inline, null);
    $username_ac = $user['Processing_value_tow'];
    $date = time();
    if (intval($user['pricediscount']) != 0) {
        $result = ($priceproduct * $user['pricediscount']) / 100;
        $priceproduct = $priceproduct - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }
    if ($priceproduct > $user['Balance'] && $user['agent'] != "n2" && !$admin_buy_free) {
        $bakinfos = balancebtn_kb($user['lang'] ?? 'fa', $textbotlang);
        Editmessagetext($from_id, $message_id, $textbotlang['users']['Balance']['insufficientBalanceSimple'], $bakinfos, 'HTML');
        step('home', $from_id);
        return;
    }
    if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
        if (($user['Balance'] - $priceproduct) < intval("-" . $user['maxbuyagent'])) {
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }
    $datep = strtotime("+" . $info_product['Service_time'] . "days");
    if (username_method_is($marzban_list_get['MethodUsername'], 'keyboard.customTextSequential') || username_method_is($marzban_list_get['MethodUsername'], 'keyboard.usernameSequential') || username_method_is($marzban_list_get['MethodUsername'], 'keyboard.numericIdSequential') || username_method_is($marzban_list_get['MethodUsername'], 'keyboard.agentCustomTextSequential')) {
        $value = intval($user['number_username']) + $user['Processing_value_four'];
        update("user", "number_username", $value, "id", $from_id);
        if (username_method_is($marzban_list_get['MethodUsername'], 'keyboard.customTextSequential') || username_method_is($marzban_list_get['MethodUsername'], 'keyboard.agentCustomTextSequential')) {
            $value = intval($setting['numbercount']) + $user['Processing_value_four'];
            update("setting", "numbercount", $value);
        }
    }
    if ($info_product['Service_time'] == 0) {
        $datep = 0;
    } else {
        $datep = strtotime(date("Y-m-d H:i:s", $datep));
    }
    $datac = array(
        'expire' => strtotime(date("Y-m-d H:i:s", $datep)),
        'data_limit' => $info_product['Volume_constraint'] * pow(1024, 3),
        'from_id' => $from_id,
        'username' => $username,
        'type' => 'buyomdh'
    );
    if ($info_product['inbounds'] != null) {
        $marzban_list_get['inboundid'] = $info_product['inbounds'];
    }
    $notifctions = json_encode(array(
        'volume' => false,
        'time' => false,
    ));
    $Shoppinginfo = afterpay_help_kb($user['lang'] ?? 'fa', $textbotlang);
    for ($i = 0; $i < $user['Processing_value_four']; $i++) {
        $random_number = rand(1000000, 9999999);
        $username_acc = $username_ac . "_" . $i;
        $get_username_Check = $ManagePanel->DataUser($marzban_list_get['name_panel'], $username_acc);
        if (isset($get_username_Check['username']) || in_array($username_acc, $usernameinvoice)) {
            $username_acc = $random_number . "_" . $username_acc;
        }
        $randomString = bin2hex(random_bytes(4));
        if (in_array($randomString, $id_invoice)) {
            $randomString = $random_number . $randomString;
        }
        $dataoutput = $ManagePanel->createUser($marzban_list_get['name_panel'], $info_product['code_product'], $username_acc, $datac);
        if ($dataoutput['username'] == null) {
            $dataoutput['msg'] = json_encode($dataoutput['msg']);
            sendmessage($from_id, $textbotlang['users']['sell']['errorConfig'], $keyboard, 'HTML');
            $texterros = sprintf($textbotlang['Admin']['reportgroup']['errorBulkAccountCreate'], $dataoutput['msg'], $from_id, $username, $marzban_list_get['name_panel']);
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $texterros,
                    'parse_mode' => "HTML"
                ]);
            }
            step('home', $from_id);
            return;
        }
        $stmt = $pdo->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username,time_sell, Service_location, name_product, price_product, Volume, Service_time,Status,notifctions) VALUES (?, ?, ?, ?, ?, ?, ?,?,?,?,?)");
        $Status = "active";
        $stmt->execute([$from_id, $randomString, $username_acc, $date, $user['Processing_value'], $info_product['name_product'], $info_product['price_product'], $info_product['Volume_constraint'], $info_product['Service_time'], $Status, $notifctions]);
        if ($parts[0] == "customvolume") {
            cvol_mark_invoice($randomString);
        }
        $config = "";
        $output_config_link = $marzban_list_get['sublink'] == "onsublink" ? $dataoutput['subscription_url'] : "";
        if ($marzban_list_get['config'] == "onconfig") {
            if (is_array($dataoutput['configs'])) {
                foreach ($dataoutput['configs'] as $configs) {
                    $config .= $configs;
                }
            }
        }
        $textbotlang['textbot']['afterPay'] = $marzban_list_get['type'] == "Manualsale" ? $textbotlang['textbot']['manual'] : $textbotlang['textbot']['afterPay'];
        if ($marzban_list_get['type'] == "WGDashboard") {
            $textbotlang['textbot']['afterPay'] = $textbotlang['users']['sell']['created2'];
        }
        $textcreatuser = str_replace('{username}', "<code>{$dataoutput['username']}</code>", $textbotlang['textbot']['afterPay']);
        $textcreatuser = str_replace('{name_service}', $info_product['name_product'], $textcreatuser);
        $textcreatuser = str_replace('{location}', $marzban_list_get['name_panel'], $textcreatuser);
        $textcreatuser = str_replace('{day}', $info_product['Service_time'], $textcreatuser);
        $textcreatuser = str_replace('{volume}', $info_product['Volume_constraint'], $textcreatuser);
        $textcreatuser = str_replace('{time_human}', service_days_text(intval($info_product['Service_time']), $textbotlang), $textcreatuser);
        $textcreatuser = str_replace('{volume_human}', service_volume_text((float) $info_product['Volume_constraint'] * 1024, $textbotlang), $textcreatuser);
        $textcreatuser = str_replace('{config}', "<code>{$output_config_link}</code>", $textcreatuser);
        $textcreatuser = str_replace('{links}', "<code>{$config}</code>", $textcreatuser);
        $textcreatuser = str_replace('{links2}', "{$output_config_link}", $textcreatuser);
        sendMessageService($marzban_list_get, $dataoutput['configs'], $output_config_link, $dataoutput['username'], $Shoppinginfo, $textcreatuser, $randomString);
    }
    $user_Balance = select("user", "*", "id", $from_id, "select");
    $Balance_prim = $user_Balance['Balance'] - $priceproduct;
    balance_add($from_id, -$priceproduct);
    $balanceformatsell = money(select("user", "Balance", "id", $from_id, "select")['Balance'], currency_for_user($user), false);
    $balanceformatsellbefore = wallet_amount_text($user);
    $pricebulk = $info_product['price_product'] * intval($user['Processing_value_four']);
    $count_service = $user['Processing_value_four'];
    $timejalali = jdate('Y/m/d H:i:s');
    $text_report = sprintf($textbotlang['Admin']['reportgroup']['bulkAccountCreated'], $from_id, $username, $username_ac, $count_service, $first_name, $user['Processing_value'], $info_product['name_product'], $info_product['Service_time'], $info_product['Volume_constraint'], $balanceformatsellbefore, $balanceformatsell, $randomString, $user['agent'], $user['number'], $info_product['price_product'], $info_product['price_product'], $user['Processing_value_four'], $timejalali);
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $buyreport,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
    step('home', $from_id);
} elseif ($text == $textbotlang['textbot']['addBalance'] || $datain == "Add_Balance" || $datain == "Add_Balance_ac" || $text == "/topup" || $verify_resume === 'verifytopup') {
    update("user", "Processing_value", "0", "id", $from_id);
    update("user", "Processing_value_one", "0", "id", $from_id);
    update("user", "Processing_value_tow", "0", "id", $from_id);
    update("user", "Processing_value_four", "0", "id", $from_id);
    if (phone_required_for($user) && $user['step'] != "get_number" && $user['number'] == "none") {
        sendmessage($from_id, $textbotlang['users']['number']['confirming'], $request_contact, 'HTML');
        // after the resets above, so the marker survives them
        update("user", "Processing_value", "verifytopup", "id", $from_id);
        step('get_number', $from_id);
    }
    if ($user['number'] == "none" && phone_required_for($user))
        return;
    // method first, amount second: $step_payment is already filtered to this
    // user's language (and, since 2026-08-08, hard-excludes fa-only gateways)
    if ($step_payment_none) {
        // from the 👤 account page: the page, its sticker and the
        // "👤 حساب کاربری" tap that opened it go, and only the notice stays.
        // keyboard.php has already cleared both ids for the new section, so
        // they are read from $users, the row as it was before this tap.
        if ($datain == "Add_Balance_ac") {
            deletemessage($from_id, $message_id);
            $ac_tap = explode(':', (string) ($users['menu_tap_id'] ?? ''), 2);
            if (ctype_digit($ac_tap[0]) && intval($ac_tap[0]) > 0 && ($ac_tap[1] ?? '') === 'accountwallet') {
                deletemessage($from_id, intval($ac_tap[0]));
            }
            if (ctype_digit((string) ($users['menu_sticker_id'] ?? '')) && intval($users['menu_sticker_id']) > 0) {
                deletemessage($from_id, intval($users['menu_sticker_id']));
            }
        }
        sendmessage($from_id, $noCreditText, null, 'HTML');
        return;
    }
    step('topup_pick_method', $from_id);
    // only a callback gives us a bot-authored message to edit; the reply
    // button and /topup must send a fresh one (same shape as accountWallet)
    $tp_methodKb = topup_disc_method_keyboard(topup_method_keyboard($step_payment, $user['lang'] ?? 'fa'), $from_id, $user['lang'] ?? 'fa');
    if ($datain == "Add_Balance" || $datain == "Add_Balance_ac") {
        Editmessagetext($from_id, $message_id, topup_disc_method_caption($from_id, $user['lang'] ?? 'fa', $textbotlang), $tp_methodKb, 'HTML');
    } else {
        sendmessage($from_id, topup_disc_method_caption($from_id, $user['lang'] ?? 'fa', $textbotlang), $tp_methodKb, 'HTML');
    }
    return;
} elseif ($datain == "topup_disc_enter" && $user['step'] == "topup_pick_method") {
    step('topup_disc_code', $from_id);
    // remember THIS message: the code arrives as a message of the user's own, so
    // without its id the reply below would have nothing to edit and would have
    // to post a new screen instead
    update("user", "topup_disc_msg_id", (string) intval($message_id), "id", $from_id);
    list($tp_pCap, $tp_pKb) = topup_disc_prompt_payload($user['lang'] ?? 'fa', $textbotlang);
    Editmessagetext($from_id, $message_id, $tp_pCap, $tp_pKb, 'HTML');
} elseif ($datain == "topup_disc_cancel" && $user['step'] == "topup_disc_code") {
    step('topup_pick_method', $from_id);
    Editmessagetext($from_id, $message_id, topup_disc_method_caption($from_id, $user['lang'] ?? 'fa', $textbotlang), topup_disc_method_keyboard(topup_method_keyboard($step_payment, $user['lang'] ?? 'fa'), $from_id, $user['lang'] ?? 'fa'), 'HTML');
} elseif ($datain == "topup_disc_auto_info" && $user['step'] == "topup_pick_method") {
    // the codeless discount has no code to show, so this is the only place a
    // user can read its terms before choosing a gateway
    telegram('answerCallbackQuery', [
        'callback_query_id' => $callback_query_id,
        'text' => topup_disc_auto_info_text($user['lang'] ?? 'fa', $textbotlang),
        'show_alert' => true,
        'cache_time' => 1,
    ]);
} elseif ($user['step'] == "topup_disc_code" && $datain == '' && trim((string) $text) !== '') {
    // step-gated branches in index.php must ALSO validate the real input, never
    // match on the step alone - see the 2026-08-08 swallowed-navigation bug
    $tp_lang = $user['lang'] ?? 'fa';
    $tp_typed = trim((string) $text);
    $tp_found = topup_disc_find_code($tp_typed);
    deletemessage($from_id, $message_id);
    $tp_bal = $textbotlang['users']['Balance'];
    $tp_err = null;
    if ($tp_found === null) {
        $tp_err = $tp_bal['topupDiscErrNotFound'];
    } elseif ((string) $tp_found['lang'] !== (string) $tp_lang) {
        $tp_err = $tp_bal['topupDiscErrWrongLang'];
    } else {
        $tp_st = topup_disc_code_status($tp_found['code']);
        if ($tp_st === 'expired') {
            $tp_err = $tp_bal['topupDiscErrExpired'];
        } elseif ($tp_st === 'exhausted') {
            $tp_err = $tp_bal['topupDiscErrExhausted'];
        } elseif ($tp_st !== 'active') {
            $tp_err = $tp_bal['topupDiscErrInactive'];
        } else {
            $tp_per = intval($tp_found['code']['limitPerUser'] ?? 0);
            if ($tp_per > 0 && topup_disc_code_user_count($tp_found['code']['code'], $from_id) >= $tp_per) {
                $tp_err = $tp_bal['topupDiscErrUsed'];
            }
        }
    }
    // the whole exchange stays inside the prompt message that topup_disc_enter
    // opened; the user's typed code was deleted above, so nothing but this one
    // screen is left in the chat either way
    $tp_promptId = intval($user['topup_disc_msg_id'] ?? 0);
    if ($tp_err !== null) {
        list($tp_eCap, $tp_eKb) = topup_disc_prompt_payload($tp_lang, $textbotlang, $tp_err);
        if ($tp_promptId > 0) {
            Editmessagetext($from_id, $tp_promptId, $tp_eCap, $tp_eKb, 'HTML');
        } else {
            $tp_sent = sendmessage($from_id, $tp_eCap, $tp_eKb, 'HTML');
            update("user", "topup_disc_msg_id", (string) intval($tp_sent['result']['message_id'] ?? 0), "id", $from_id);
        }
        return;
    }
    topup_disc_user_activate($from_id, $tp_found['code']['code'], $tp_found['lang'], $tp_found['gateway']);
    step('topup_pick_method', $from_id);
    update("user", "topup_disc_msg_id", "0", "id", $from_id);
    // back to the method screen, with the activation confirmed above its own
    // caption (which already carries the discount and its terms)
    $tp_okMsg = bottext_resolve_key('users.Balance.topupDiscActivated');
    if (trim((string) $tp_okMsg) === '') {
        $tp_okMsg = $textbotlang['users']['Balance']['topupDiscActivated'];
    }
    $tp_okCap = trim((string) $tp_okMsg) . "\n\n" . topup_disc_method_caption($from_id, $tp_lang, $textbotlang);
    $tp_okKb = topup_disc_method_keyboard(topup_method_keyboard($step_payment, $user['lang'] ?? 'fa'), $from_id, $tp_lang);
    if ($tp_promptId > 0) {
        Editmessagetext($from_id, $tp_promptId, $tp_okCap, $tp_okKb, 'HTML');
    } else {
        sendmessage($from_id, $tp_okCap, $tp_okKb, 'HTML');
    }
    return;
} elseif ($datain === 'iranpay2') {
    // the original bot's own Tronado gateway was removed - a method list sent
    // before the update can still carry its button, and a tap must say so
    // instead of spinning and doing nothing
    telegram('answerCallbackQuery', [
        'callback_query_id' => $callback_query_id,
        'text' => $textbotlang['users']['Balance']['methodRemoved'],
        'show_alert' => true,
        'cache_time' => 5,
    ]);
} elseif ($user['step'] == "topup_pick_method" && gateway_button_key(['callback_data' => $datain], $textbotlang['textbot']['cartToCart'] ?? null) !== null) {
    $tp_lang = $user['lang'] ?? 'fa';
    $tp_key = gateway_button_key(['callback_data' => $datain], $textbotlang['textbot']['cartToCart'] ?? null);
    if (!gateway_allowed_for_lang($tp_key, $tp_lang) || !gateway_applicable_for_lang($tp_key, $tp_lang)) {
        return;
    }
    // Nothing ready to pick? Then "#️⃣ مبلغ واریز" would invite the customer to
    // choose one of the amounts below while listing none, and the only way on
    // would be its own مبلغ دلخواه button. Open that screen directly instead -
    // without its "بازگشت به منوی قبلی", which would point at the screen this
    // very branch decided not to show.
    if (empty(topup_packages_for($tp_lang, $tp_key))) {
        topup_custom_screen_show($from_id, $message_id, $tp_lang, $tp_key, $textbotlang, false);
        return;
    }
    $tp_kb = ['inline_keyboard' => []];
    foreach (array_chunk(topup_packages_for($tp_lang, $tp_key, true), topup_columns_for($tp_lang, $tp_key), true) as $tp_row) {
        $tp_kbRow = [];
        foreach ($tp_row as $tp_i => $tp_p) {
            $tp_kbRow[] = topup_package_button($tp_p, $tp_lang, "toppick:{$tp_i}");
        }
        $tp_kb['inline_keyboard'][] = $tp_kbRow;
    }
    $tp_customStyle = topup_btnstyle_for($tp_lang, $tp_key, 'custom', true);
    $tp_backStyle = topup_btnstyle_for($tp_lang, $tp_key, 'back', true);
    $tp_customBackRow = [
        topup_styled_button($textbotlang['users']['Balance']['customAmountBtn'], $tp_customStyle, 'topup_custom_start', 'primary'),
        topup_styled_button($textbotlang['users']['Balance']['backToMethodBtn'], $tp_backStyle, 'topup_back_methods', 'danger'),
    ];
    if (topup_custom_back_swapped($tp_lang, $tp_key)) {
        $tp_customBackRow = array_reverse($tp_customBackRow);
    }
    $tp_kb['inline_keyboard'][] = $tp_customBackRow;
    step("topup_pkg:{$tp_key}", $from_id);
    Editmessagetext($from_id, $message_id, topup_caption_for($tp_lang, $tp_key, $textbotlang['users']['Balance']['pkgPromptTitle']) . topup_disc_caption_block($from_id, $tp_lang, $tp_key, $textbotlang) . sf_range_note_for($from_id, $tp_key), json_encode($tp_kb), 'HTML');
// 'get_step_payment' is included because the confirm-before-invoice screen is
// rendered AFTER that step is set - without it, the back button on that screen
// matched nothing and simply did nothing when tapped
} elseif (preg_match('/^topupmgrp:([a-z]+)$/', $datain, $tg_m) && $user['step'] === 'topup_pick_method') {
    // one family's own screen. Its rows come from the ungrouped keyboard, so
    // the buttons keep the styling and the layout the admin gave them.
    if (!isset(gateway_groups()[$tg_m[1]])) {
        return;
    }
    $tg_lang = $user['lang'] ?? 'fa';
    $tg_flat = json_decode(topup_method_keyboard($step_payment), true)['inline_keyboard'] ?? [];
    $tg_rows = topup_group_screen_rows($tg_flat, $tg_m[1], $tg_lang, $textbotlang, $textbotlang['textbot']['cartToCart'] ?? null);
    Editmessagetext($from_id, $message_id, strtr(
        topup_group_caption_for($tg_lang, $textbotlang['users']['Balance']['groupMethodCaption']),
        ['{group}' => gateway_group_label($tg_m[1], $textbotlang)]
    ), json_encode(['inline_keyboard' => $tg_rows]), 'HTML');
} elseif ($datain == "topup_back_methods" && (preg_match('/^topup_pkg:/', (string) $user['step']) || preg_match('/^topup_custom:/', (string) $user['step']) || $user['step'] === 'get_step_payment'
    || $user['step'] === 'topup_pick_method')) {
    if ($step_payment_none) {
        step('home', $from_id);
        sendmessage($from_id, $noCreditText, null, 'HTML');
        return;
    }
    step('topup_pick_method', $from_id);
    Editmessagetext($from_id, $message_id, topup_disc_method_caption($from_id, $user['lang'] ?? 'fa', $textbotlang), topup_disc_method_keyboard(topup_method_keyboard($step_payment, $user['lang'] ?? 'fa'), $from_id, $user['lang'] ?? 'fa'), 'HTML');
} elseif (preg_match('/^topup_pkg:([a-z0-9]+)$/', (string) $user['step'], $tp_m) && $datain == "topup_custom_start") {
    // reached from the amount screen, so it keeps both ways back - including
    // the one to the screen the customer just came from
    topup_custom_screen_show($from_id, $message_id, $user['lang'] ?? 'fa', $tp_m[1], $textbotlang);
// back one step, to the "#️⃣ مبلغ واریز" screen with its package buttons. The
// gateway comes from the callback rather than the step, because the screens that
// offer this button sit on three different steps (topup_custom:, topup_pkg: and
// get_step_payment) and only two of them carry the key.
} elseif (preg_match('/^topup_back_pkg:([a-z0-9]+)$/', (string) $datain, $tp_m)
    && (preg_match('/^topup_custom:/', (string) $user['step']) || preg_match('/^topup_pkg:/', (string) $user['step']) || $user['step'] === 'get_step_payment')) {
    $tp_key = $tp_m[1];
    $tp_lang = $user['lang'] ?? 'fa';
    // the packages can be taken away by the admin while a customer sits on this
    // screen - "back" would then rebuild an empty amount screen and strand
    // them, so the custom-amount screen stands in for it here too
    if (empty(topup_packages_for($tp_lang, $tp_key))) {
        topup_custom_screen_show($from_id, $message_id, $tp_lang, $tp_key, $textbotlang, false);
        return;
    }
    $tp_kb = ['inline_keyboard' => []];
    foreach (array_chunk(topup_packages_for($tp_lang, $tp_key, true), topup_columns_for($tp_lang, $tp_key), true) as $tp_row) {
        $tp_kbRow = [];
        foreach ($tp_row as $tp_i => $tp_p) {
            $tp_kbRow[] = topup_package_button($tp_p, $tp_lang, "toppick:{$tp_i}");
        }
        $tp_kb['inline_keyboard'][] = $tp_kbRow;
    }
    $tp_customStyle = topup_btnstyle_for($tp_lang, $tp_key, 'custom', true);
    $tp_backStyle = topup_btnstyle_for($tp_lang, $tp_key, 'back', true);
    $tp_customBackRow = [
        topup_styled_button($textbotlang['users']['Balance']['customAmountBtn'], $tp_customStyle, 'topup_custom_start', 'primary'),
        topup_styled_button($textbotlang['users']['Balance']['backToMethodBtn'], $tp_backStyle, 'topup_back_methods', 'danger'),
    ];
    if (topup_custom_back_swapped($tp_lang, $tp_key)) {
        $tp_customBackRow = array_reverse($tp_customBackRow);
    }
    $tp_kb['inline_keyboard'][] = $tp_customBackRow;
    step("topup_pkg:{$tp_key}", $from_id);
    Editmessagetext($from_id, $message_id, topup_caption_for($tp_lang, $tp_key, $textbotlang['users']['Balance']['pkgPromptTitle']) . topup_disc_caption_block($from_id, $tp_lang, $tp_key, $textbotlang) . sf_range_note_for($from_id, $tp_key), json_encode($tp_kb), 'HTML');
} elseif (preg_match('/^topup_pkg:([a-z0-9]+)$/', (string) $user['step'], $tp_m) && preg_match('/^toppick:([0-9]{1,3})$/', (string) $datain, $tp_pick)) {
    $tp_key = $tp_m[1];
    $tp_lang = $user['lang'] ?? 'fa';
    $tp_pkg = topup_packages_for($tp_lang, $tp_key)[(int) $tp_pick[1]] ?? null;
    if ($tp_pkg === null) {
        return;
    }
    update("user", "Processing_value", $tp_pkg['amount'], "id", $from_id);
    step('get_step_payment', $from_id);
    // Straight into the gateway's own checkout, the way card-to-card and Plisio
    // already worked. No "✅ مبلغ ... انتخاب شد" screen in between any more, for
    // any gateway: one amount, one path. gateway_datain() maps the key to the
    // callback the checkout block is keyed on, and a handler's early return
    // comes back from the include as NULL.
    $user['Processing_value'] = $tp_pkg['amount'];
    // if the gateway refuses this amount, its notice puts the customer back
    // on this screen rather than the dead step the dispatch runs on
    $GLOBALS['topup_amount_origin_step'] = "topup_pkg:{$tp_key}";
    $datain = gateway_datain($tp_key);
    if ((include __DIR__ . '/topup_gateway_dispatch.php') !== 1) {
        return;
    }
} elseif (preg_match('/^topup_custom:([a-z0-9]+)$/', (string) $user['step'], $tp_m) && $datain == '' && preg_match('/[0-9۰-۹]/u', (string) $text)) {
    $tp_key = $tp_m[1];
    $tp_lang = $user['lang'] ?? 'fa';
    if (!money_valid((string) $text, currency_for_lang($tp_lang))) {
        // letters, symbols, anything that is not a plain amount: take it away
        // and say so, the same self-replacing notice a bad amount gets
        topup_notnumber_notice($from_id, $tp_lang, $tp_key, $textbotlang, (int) ($update['message']['message_id'] ?? 0));
        return;
    }
    $tp_amt = money_normalize((string) $text);
    // the gateway's dollar floor is folded in here, so a customer is refused
    // once with one number instead of twice with two
    [$tp_min, $tp_max] = topup_effective_limits($tp_lang, $tp_key);
    if (($tp_min !== null && $tp_amt < $tp_min) || ($tp_max !== null && $tp_amt > $tp_max)) {
        // the number the customer typed goes with the notice, so a second
        // wrong try leaves one message on screen rather than a column of them
        topup_range_notice($from_id, $tp_lang, $tp_key, $tp_min ?? 0, $tp_max ?? 0,
            $textbotlang, (int) ($update['message']['message_id'] ?? 0));
        return;
    }
    update("user", "Processing_value", $tp_amt, "id", $from_id);
    step('get_step_payment', $from_id);
    // the same direct hand-off the package buttons above make
    $user['Processing_value'] = $tp_amt;
    // same for the custom-amount screen - and the number they typed, so a
    // refusal from inside the gateway can take it away too
    $GLOBALS['topup_amount_origin_step'] = "topup_custom:{$tp_key}";
    $GLOBALS['topup_typed_message_id'] = (int) ($update['message']['message_id'] ?? 0);
    $datain = gateway_datain($tp_key);
    if ((include __DIR__ . '/topup_gateway_dispatch.php') !== 1) {
        return;
    }
} elseif ($user['step'] == "getprice") {
    deletemessage($from_id, $user['Processing_value']);
    if (!is_numeric($text))
        return sendmessage($from_id, $textbotlang['users']['Balance']['errorprice'], null, 'HTML');
    $minbalance = json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']] ?? null;
    $maxbalance = json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']] ?? null;
    $balancelast = $text;
    // a group with no saved limit is not limited - comparing with null refused every amount
    if (topup_amount_out_of_range($text, $minbalance, $maxbalance)) {
        $minbalance = number_format((float) $minbalance);
        $maxbalance = number_format((float) $maxbalance);
        sendmessage($from_id, sprintf($textbotlang['users']['Balance']['amountRangeError'], $minbalance, $maxbalance), null, 'HTML');
        return;
    }
    if ($user['Balance'] < 0 and intval(feature_value('Debtsettlement', $user['lang'] ?? 'fa', $setting['Debtsettlement'])) == 1) {
        $balancruser = abs($user['Balance']);
        if ($text < $balancruser) {
            sendmessage($from_id, sprintf($textbotlang['users']['Balance']['debtRequired'], $balancruser), null, 'HTML');
            return;
        }
    }
    update("user", "Processing_value", $balancelast, "id", $from_id);
    sendmessage($from_id, $textbotlang['users']['Balance']['selectPayment'], $step_payment, 'HTML');
    step('get_step_payment', $from_id);
} elseif ($user['step'] == "get_step_payment") {
    if ((include __DIR__ . '/topup_gateway_dispatch.php') !== 1) {
        return;
    }
}
if (preg_match('/Confirmpay_user_(\w+)_(\w+)/', $datain, $dataget)) {
    $id_payment = $dataget[1];
    $id_order = $dataget[2];
    $__q9 = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order = ? LIMIT 1");
    $__q9->bindValue(1, $id_order, PDO::PARAM_STR);
    $__q9->execute();
    $Payment_report = $__q9->fetch(PDO::FETCH_ASSOC);
    if ($Payment_report['payment_Status'] == "paid") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['confirmPayAdmin'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        return;
    }
    $StatusPayment = StatusPayment($id_payment);
    if ($StatusPayment['payment_status'] == "finished") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['finished'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
        update("Payment_report", "payment_Status", "paid", "id_order", $Payment_report['id_order']);
        DirectPayment($Payment_report['id_order']);
        $__q10 = $pdo->prepare("SELECT * FROM user WHERE id = ? LIMIT 1");
        $__q10->bindValue(1, $Payment_report['id_user'], PDO::PARAM_STR);
        $__q10->execute();
        $Balance_id = $__q10->fetch(PDO::FETCH_ASSOC);
        $Payment_report['price'] = number_format($Payment_report['price'], 0);
        $text_report = sprintf($textbotlang['Admin']['reportgroup']['newPayment'], $from_id, $Payment_report['price']);
        // NowPayment's own cashback (payment/nowpayment.php reads the same row) - this
        // used to borrow the removed legacy Tronado gateway's
        $pricecashback = select("PaySetting", "ValuePay", "NamePay", "cashbacknowpayment", "select")['ValuePay'] ?? '0';
        if ($pricecashback != "0") {
            $result = ($Payment_report['price'] * $pricecashback) / 100;
            // into the wallet the payment was made in
            wallet_credit($user['id'], $result, payment_currency($Payment_report));
            $pricecashback = number_format($pricecashback);
            $text_report = sprintf($textbotlang['users']['Discount']['gift-deposit'], $result);
            sendmessage($from_id, $text_report, null, 'HTML');
        }
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $paymentreports,
                'text' => $text_report,
                'parse_mode' => "HTML"
            ]);
        }
        update("Payment_report", "payment_Status", "paid", "id_order", $Payment_report['id_order']);
        update("user", "Processing_value_one", "none", "id", $Payment_report['id_order']);
        update("user", "Processing_value_tow", "none", "id", $Payment_report['id_order']);
        update("user", "Processing_value_four", "none", "id", $Payment_report['id_order']);
    } elseif ($StatusPayment['payment_status'] == "expired") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['expired'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
    } elseif ($StatusPayment['payment_status'] == "refunded") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['refunded'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
    } elseif ($StatusPayment['payment_status'] == "waiting") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['waiting'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
    } elseif ($StatusPayment['payment_status'] == "sending") {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['sending'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
    } else {
        telegram('answerCallbackQuery', array(
            'callback_query_id' => $callback_query_id,
            'text' => $textbotlang['users']['Balance']['Failed'],
            'show_alert' => true,
            'cache_time' => 5,
        ));
    }
}
if (preg_match('/^sendresidcart-(.*)/', $datain, $dataget)) {
    $timefivemin = time() - 120;
    $timefivemin = date('Y/m/d H:i:s', intval($timefivemin));
    $sql = "SELECT * FROM Payment_report WHERE id_user = :from_id AND Payment_Method = 'cart to cart' AND at_updated > :timefivemin";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':from_id', $from_id, PDO::PARAM_STR);
    $stmt->bindParam(':timefivemin', $timefivemin, PDO::PARAM_STR);
    $stmt->execute();
    $paymentcount = $stmt->rowCount();
    if ($paymentcount != 0 and !in_array($from_id, $admin_ids)) {
        sendmessage($from_id, $textbotlang['users']['Balance']['receiptCooldown'], null, 'HTML');
        return;
    }
    $payemntcheck = select("Payment_report", "*", "id_order", $dataget[1], "select");
    if ($payemntcheck['payment_Status'] == "paid") {
        sendmessage($from_id, $textbotlang['users']['Balance']['alreadyConfirmed'], null, 'HTML');
        return;
    }
    if ($payemntcheck['payment_Status'] == "expire") {
        sendmessage($from_id, $textbotlang['users']['Balance']['transactionExpired'], null, 'HTML');
        return;
    }
    deletemessage($from_id, $message_id);
    sendmessage($from_id, $textbotlang['users']['Balance']['askReceiptImage'], $backuser, 'HTML');
    step('cart_to_cart_user', $from_id);
    update("user", "Processing_value", $dataget[1], "id", $from_id);
} elseif (preg_match('/^cardreissue:(.+)$/', $datain, $crm)) {
    // the "ساخت فاکتور جدید" button on an expired card-to-card invoice
    // message (edited into place by cronbot/payment_expire.php). Rebuilds
    // through the exact same card_invoice_build() the original amount-pick
    // flow uses, carrying the old row's price + id_invoice forward (same
    // transaction, just a fresh card number/deadline/id_order) and edits
    // this same message in place rather than sending a new one.
    $oldRow = select("Payment_report", "*", "id_order", $crm[1], "select");
    if (!is_array($oldRow) || (string) $oldRow['id_user'] !== (string) $from_id || $oldRow['payment_Status'] !== 'expire') {
        return;
    }
    // $oldRow['price'] is already the FINAL amount from the original invoice
    // (may already include a random addition from before) - never randomize
    // it a second time on reissue
    $built = card_invoice_build($from_id, $user['lang'] ?? 'fa', $oldRow['price'], $oldRow['id_invoice'], $textbotlang, $setting, false);
    if ($built === null) {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['Balance']['noActiveCard'], null, 'HTML');
        return;
    }
    Editmessagetext($from_id, $message_id, $built['text'], $built['keyboard'], 'HTML');
    update("Payment_report", "message_id", (int) $message_id, "id_order", $built['randomString']);
} elseif (preg_match('/^usdtbepcheck:([a-z0-9]+)$/', $datain, $ub_m)) {
    // Same two-in-one tap as TRX below: look for the transfer first, and only
    // ask for a hash when the chain has nothing yet. A BEP20 transfer carries no
    // note either, so the amount is what identifies the invoice.
    $ub_row = select("Payment_report", "*", "id_order", $ub_m[1], "select");
    if (!is_array($ub_row) || (string) $ub_row['id_user'] !== (string) $from_id) {
        return;
    }
    $ub_lang = $user['lang'] ?? 'fa';
    if ($ub_row['payment_Status'] === 'paid') {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => topup_paid_alert_for($ub_lang, 'usdtbep', $textbotlang['users']['Balance']['topupPaidAlert']),
            'show_alert' => true,
        ]);
        return;
    }
    // from the invoice's own age, so the block window stays as small as it can
    $ub_since = strtotime((string) $ub_row['time']);
    $ub_incoming = usdtbep_incoming_transfers(topup_usdtbep_address($ub_lang), $ub_since > 0 ? $ub_since - 300 : 0);
    if (usdtbep_payment_settled($ub_row, $ub_incoming)) {
        $ub_claim = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'paid' WHERE id_order = ? AND payment_Status = 'Unpaid'");
        $ub_claim->execute([$ub_row['id_order']]);
        if ($ub_claim->rowCount() === 1) {
            DirectPayment($ub_row['id_order'], "images.jpg");
        }
        return;
    }
    telegram('answerCallbackQuery', [
        'callback_query_id' => $callback_query_id,
        'text' => topup_notseen_caption_for($ub_lang, 'usdtbep', $textbotlang['users']['Balance']['usdtbepNotSeenYet']),
        'show_alert' => true,
    ]);
    // one prompt at a time, same as TRX
    $ub_prev = (int) (select("user", "*", "id", $from_id, "select")['topup_range_msg_id'] ?? 0);
    if ($ub_prev > 0) {
        deletemessage($from_id, $ub_prev);
    }
    $ub_cancelKb = json_encode(['inline_keyboard' => [
        [['text' => $textbotlang['bottext']['btn_close'] ?? '❌', 'callback_data' => 'topup_range_close', 'style' => 'danger']],
    ]]);
    $ub_prompt = sendmessage($from_id, topup_askhash_caption_for($ub_lang, 'usdtbep', $textbotlang['users']['Balance']['usdtbepAskHash']), $ub_cancelKb, 'HTML');
    $ub_promptId = (int) ($ub_prompt['result']['message_id'] ?? 0);
    update("user", "topup_range_msg_id", (string) $ub_promptId, "id", $from_id);
    step("usdtbephash:{$ub_m[1]}:{$ub_promptId}", $from_id);
} elseif (preg_match('/^usdtbephash:([a-z0-9]+):([0-9]+)$/', (string) $user['step'], $ub_m) && $datain == '') {
    $ub_row = select("Payment_report", "*", "id_order", $ub_m[1], "select");
    if (!is_array($ub_row) || (string) $ub_row['id_user'] !== (string) $from_id) {
        step('home', $from_id);
        return;
    }
    $ub_lang = $user['lang'] ?? 'fa';
    $ub_promptId = (int) $ub_m[2];
    deletemessage($from_id, (int) ($update['message']['message_id'] ?? 0));
    if (!usdtbep_verify_hash($ub_row, $text, topup_usdtbep_address($ub_lang))) {
        $ub_cancelKb = json_encode(['inline_keyboard' => [
            [['text' => $textbotlang['bottext']['btn_close'] ?? '❌', 'callback_data' => 'topup_range_close', 'style' => 'danger']],
        ]]);
        Editmessagetext($from_id, $ub_promptId,
            '<blockquote>' . topup_hashbad_caption_for($ub_lang, 'usdtbep', $textbotlang['users']['Balance']['usdtbepHashInvalid']) . "</blockquote>\n\n"
            . topup_askhash_caption_for($ub_lang, 'usdtbep', $textbotlang['users']['Balance']['usdtbepAskHash']),
            $ub_cancelKb, 'HTML');
        step("usdtbephash:{$ub_m[1]}:{$ub_promptId}", $from_id);
        return;
    }
    topup_amount_prompt_clear($from_id);
    step('home', $from_id);
    $ub_claim = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'paid' WHERE id_order = ? AND payment_Status = 'Unpaid'");
    $ub_claim->execute([$ub_row['id_order']]);
    if ($ub_claim->rowCount() === 1) {
        DirectPayment($ub_row['id_order'], "images.jpg");
    }
} elseif (preg_match('/^trxcheck:([a-z0-9]+)$/', $datain, $tx_m)) {
    // One tap does both: look for the transfer first, and only ask for a hash
    // if the chain has nothing yet. TRON carries no note, so the amount is what
    // identifies the invoice - and a customer who rounded it needs the hash.
    $tx_row = select("Payment_report", "*", "id_order", $tx_m[1], "select");
    if (!is_array($tx_row) || (string) $tx_row['id_user'] !== (string) $from_id) {
        return;
    }
    $tx_lang = $user['lang'] ?? 'fa';
    if ($tx_row['payment_Status'] === 'paid') {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => topup_paid_alert_for($tx_lang, 'trx', $textbotlang['users']['Balance']['topupPaidAlert']),
            'show_alert' => true,
        ]);
        return;
    }
    $tx_incoming = trx_incoming_transfers(topup_trx_address($tx_lang), 0, 200);
    if (trx_payment_settled($tx_row, $tx_incoming)) {
        $tx_claim = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'paid' WHERE id_order = ? AND payment_Status = 'Unpaid'");
        $tx_claim->execute([$tx_row['id_order']]);
        if ($tx_claim->rowCount() === 1) {
            DirectPayment($tx_row['id_order'], "images.jpg");
        }
        return;
    }
    // say plainly that nothing has arrived yet, then ask for the hash - one
    // answer per callback, so the alert and the prompt are separate things
    telegram('answerCallbackQuery', [
        'callback_query_id' => $callback_query_id,
        'text' => topup_notseen_caption_for($tx_lang, 'trx', $textbotlang['users']['Balance']['trxNotSeenYet']),
        'show_alert' => true,
    ]);
    // one prompt at a time: tapping again replaces the old one instead of
    // leaving a column of identical requests behind
    $tx_prev = (int) (select("user", "*", "id", $from_id, "select")['topup_range_msg_id'] ?? 0);
    if ($tx_prev > 0) {
        deletemessage($from_id, $tx_prev);
    }
    $tx_cancelKb = json_encode(['inline_keyboard' => [
        [['text' => $textbotlang['bottext']['btn_close'] ?? '❌', 'callback_data' => 'topup_range_close', 'style' => 'danger']],
    ]]);
    $tx_prompt = sendmessage($from_id, topup_askhash_caption_for($tx_lang, 'trx', $textbotlang['users']['Balance']['trxAskHash']), $tx_cancelKb, 'HTML');
    $tx_promptId = (int) ($tx_prompt['result']['message_id'] ?? 0);
    update("user", "topup_range_msg_id", (string) $tx_promptId, "id", $from_id);
    step("trxhash:{$tx_m[1]}:{$tx_promptId}", $from_id);
} elseif (preg_match('/^trxhash:([a-z0-9]+):([0-9]+)$/', (string) $user['step'], $tx_m) && $datain == '') {
    // the hash the customer pasted, checked against the chain rather than
    // against an admin's patience
    $tx_row = select("Payment_report", "*", "id_order", $tx_m[1], "select");
    if (!is_array($tx_row) || (string) $tx_row['id_user'] !== (string) $from_id) {
        step('home', $from_id);
        return;
    }
    $tx_lang = $user['lang'] ?? 'fa';
    $tx_promptId = (int) $tx_m[2];
    // whatever they sent goes, right or wrong - a rejected attempt should
    // not leave a trail above the prompt
    deletemessage($from_id, (int) ($update['message']['message_id'] ?? 0));
    if (!trx_verify_hash($tx_row, $text, topup_trx_address($tx_lang))) {
        // the complaint belongs on the prompt itself, quoted above it, so the
        // customer reads what went wrong and what to send in one place
        $tx_cancelKb = json_encode(['inline_keyboard' => [
            [['text' => $textbotlang['bottext']['btn_close'] ?? '❌', 'callback_data' => 'topup_range_close', 'style' => 'danger']],
        ]]);
        Editmessagetext($from_id, $tx_promptId,
            '<blockquote>' . topup_hashbad_caption_for($tx_lang, 'trx', $textbotlang['users']['Balance']['trxHashInvalid']) . "</blockquote>\n\n"
            . topup_askhash_caption_for($tx_lang, 'trx', $textbotlang['users']['Balance']['trxAskHash']),
            $tx_cancelKb, 'HTML');
        step("trxhash:{$tx_m[1]}:{$tx_promptId}", $from_id);
        return;
    }
    topup_amount_prompt_clear($from_id);
    step('home', $from_id);
    $tx_claim = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'paid' WHERE id_order = ? AND payment_Status = 'Unpaid'");
    $tx_claim->execute([$tx_row['id_order']]);
    if ($tx_claim->rowCount() === 1) {
        DirectPayment($tx_row['id_order'], "images.jpg");
    }
// mixed case, because the memo may carry the shop's own prefix now and an
// admin who typed "CiroVPN" should get "CiroVPN" back, not a button that
// silently stops matching (topup_memo_clean_prefix keeps it to [A-Za-z0-9])
} elseif (preg_match('/^toncheck:([a-zA-Z0-9]+)$/', $datain, $tc_m)) {
    // The customer asking "have you seen it yet?". It is the same check the
    // cron makes every few minutes - offered here because waiting in front of
    // an invoice with no feedback is the worst part of paying on-chain.
    $tc_row = select("Payment_report", "*", "id_order", $tc_m[1], "select");
    if (!is_array($tc_row) || (string) $tc_row['id_user'] !== (string) $from_id) {
        return;
    }
    if ($tc_row['payment_Status'] === 'paid') {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => topup_paid_alert_for($user['lang'] ?? 'fa', 'ton', $textbotlang['users']['Balance']['topupPaidAlert']),
            'show_alert' => true,
        ]);
        return;
    }
    // a callback query can be answered exactly once, so the answer has to be
    // the result - reading the chain first costs a fraction of a second and
    // Telegram allows far longer than that
    $tc_incoming = ton_incoming_transfers(topup_ton_address($user['lang'] ?? 'fa'), 100);
    if (!ton_payment_settled($tc_row, $tc_incoming)) {
        telegram('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => topup_notseen_caption_for($user['lang'] ?? 'fa', 'ton', $textbotlang['users']['Balance']['tonNotSeenYet']),
            'show_alert' => true,
        ]);
        return;
    }
    // claim it before crediting, so the cron cannot pay the same transfer twice
    $tc_claim = $pdo->prepare("UPDATE Payment_report SET payment_Status = 'paid' WHERE id_order = ? AND payment_Status = 'Unpaid'");
    $tc_claim->execute([$tc_row['id_order']]);
    if ($tc_claim->rowCount() === 1) {
        DirectPayment($tc_row['id_order'], "images.jpg");
    }
} elseif (preg_match('/^gwreissue:([a-z0-9]+):(.+)$/', $datain, $grm)) {
    // "ساخت فاکتور جدید" under an expired invoice. Every online gateway's
    // builder shares one contract, so the only thing that differs here is which
    // one to call - Plisio had this to itself until NowPayments and Star
    // Telegram were brought onto the same footing.
    $gr_builders = [
        'plisio' => 'plisio_invoice_build',
        'nowpayment' => 'nowpayment_invoice_build',
        'startelegrams' => 'star_invoice_build',
        'ton' => 'ton_invoice_build',
        'trx' => 'trx_invoice_build',
        'usdtbep' => 'usdtbep_invoice_build',
    ];
    $gr_key = $grm[1];
    if (!isset($gr_builders[$gr_key])) {
        return;
    }
    $oldRow = select("Payment_report", "*", "id_order", $grm[2], "select");
    if (!is_array($oldRow) || (string) $oldRow['id_user'] !== (string) $from_id || $oldRow['payment_Status'] !== 'expire') {
        return;
    }
    Editmessagetext($from_id, $message_id, topup_linkmsg_for($user['lang'] ?? 'fa', $gr_key, $textbotlang['users']['Balance']['linkpayments']), null, 'HTML');
    $built = $gr_builders[$gr_key]($from_id, $user['lang'] ?? 'fa', $oldRow['price'], $oldRow['id_invoice'], $textbotlang, $setting);
    if ($built['error'] === 'toolow') {
        [$gr_min, $gr_max] = topup_effective_limits($user['lang'] ?? 'fa', $gr_key);
        Editmessagetext($from_id, $message_id, topup_range_text($user['lang'] ?? 'fa', $gr_key, $gr_min, $gr_max, $textbotlang), null, 'HTML');
        return;
    }
    if ($built['error'] !== null) {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['Balance']['errorLinkPayment'], null, 'HTML');
        if ($built['error'] === 'api' && strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => sprintf($textbotlang['Admin']['reportgroup']['errorCryptoLink'], $built['apiMessage'], $from_id, $username),
                'parse_mode' => "HTML"
            ]);
        }
        return;
    }
    Editmessagetext($from_id, $message_id, $built['text'], $built['keyboard'], 'HTML');
    update("Payment_report", "message_id", (int) $message_id, "id_order", $built['randomString']);
} elseif (preg_match('/^sendresidarze-(.*)/', $datain, $dataget) and $text_inline != null) {
    $payemntcheck = select("Payment_report", "*", "id_order", $dataget[1], "select");
    if ($payemntcheck['payment_Status'] == "paid") {
        sendmessage($from_id, $textbotlang['users']['Balance']['alreadyConfirmed'], null, 'HTML');
        return;
    }
    if ($payemntcheck['payment_Status'] == "expire") {
        sendmessage($from_id, $textbotlang['users']['Balance']['transactionExpired'], null, 'HTML');
        return;
    }
    deletemessage($from_id, $message_id);
    sendmessage($from_id, $textbotlang['users']['Balance']['askReceiptOrTron'], $backuser, 'HTML');
    step('getresidcurrency', $from_id);
    update("user", "Processing_value", $dataget[1], "id", $from_id);
} elseif ($user['step'] == "getresidcurrency") {
    $format_balance = wallet_amount_text($user);
    step('home', $from_id);
    $PaymentReport = select("Payment_report", "*", "id_order", $user['Processing_value'], "select");
    $Paymentusercount = select("Payment_report", "*", "id_user", $PaymentReport['id_user'], "count");
    if ($PaymentReport == false) {
        sendmessage($from_id, $textbotlang['users']['Balance']['restartPurchaseOrPay'], $keyboard, 'HTML');
        return;
    }
    $Confirm_pay = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['Balance']['confirmPaying'], 'callback_data' => "Confirm_pay_{$PaymentReport['id_order']}"],
                ['text' => $textbotlang['users']['Balance']['rejectPay'], 'callback_data' => "reject_pay_{$PaymentReport['id_order']}"],
            ],
            [
                ['text' => $textbotlang['users']['Balance']['addBalanceUser'], 'callback_data' => "addbalamceuser_{$PaymentReport['id_order']}"],
                ['text' => $textbotlang['users']['Balance']['blockedfake'], 'callback_data' => "blockuserfake_{$PaymentReport['id_user']}"],
            ]
        ]
    ]);
    $textdiscount = "";
    $format_price_cart = number_format($PaymentReport['price'], 0);
    if ($user['Processing_value_tow'] == "getconfigafterpay") {
        $get_invoice = select("invoice", "*", "username", $user['Processing_value_one'], "select");
        if ($get_invoice == false) {
            sendmessage($from_id, $textbotlang['users']['Balance']['restartPurchaseOrPay'], $keyboard, 'HTML');
            return;
        }
        $textsendrasid = sprintf($textbotlang['Admin']['reportgroup']['newPaymentService'], $get_invoice['username'], $get_invoice['name_product'], $get_invoice['Volume'], $get_invoice['Service_time'], $first_name, $from_id, $from_id, $format_balance, $PaymentReport['id_order'], $username, $Paymentusercount, $format_price_cart, $caption, $text);
    } elseif ($user['Processing_value_tow'] == "getextenduser") {
        $partsdic = explode("%", $user['Processing_value_one']);
        $usernamepanel = $partsdic[0];
        $sql = "SELECT * FROM service_other WHERE username = :username  AND value  LIKE CONCAT('%', :value, '%') AND id_user = :id_user ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $usernamepanel, PDO::PARAM_STR);
        $stmt->bindParam(':value', $partsdic[1], PDO::PARAM_STR);
        $stmt->bindParam(':id_user', $from_id);
        $stmt->execute();
        $service_other = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($service_other == false) {
            sendmessage($from_id, $textbotlang['users']['infoFetchErrorRestart'], $keyboard, 'HTML');
            return;
        }
        $service_other = json_decode($service_other['value'], true);
        $nameloc = select("invoice", "*", "username", $usernamepanel, "select");
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        $codeproduct = $service_other['code_product'];
        // 🧩 a renewal with a custom plan: the report only names it
        if (cvol_is_renew_code($codeproduct)) {
            $prodcut = ['code_product' => 'custom_volume', 'name_product' => $nameloc['name_product'], 'Service_time' => $service_other['Service_time'] ?? $nameloc['Service_time'], 'Volume_constraint' => $service_other['volumebuy'] ?? $nameloc['Volume']];
        } else {
            $nameloc = select("invoice", "*", "username", $usernamepanel, "select");
            $__q11 = $pdo->prepare("SELECT * FROM product WHERE (Location = ? OR Location = '/all') AND agent= ? AND code_product = ? AND (FIND_IN_SET(?, lang) OR lang = 'all' OR lang IS NULL OR lang = '')");
            $__q11->bindValue(1, $nameloc['Service_location'], PDO::PARAM_STR);
            $__q11->bindValue(2, $user['agent'], PDO::PARAM_STR);
            $__q11->bindValue(3, $codeproduct, PDO::PARAM_STR);
            $__q11->bindValue(4, $user['lang'] ?? 'fa', PDO::PARAM_STR);
            $__q11->execute();
            $prodcut = $__q11->fetch(PDO::FETCH_ASSOC);
        }
        $Confirm_pay = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['confirmPaying'], 'callback_data' => "Confirm_pay_{$PaymentReport['id_order']}"],
                    ['text' => $textbotlang['users']['Balance']['rejectPay'], 'callback_data' => "reject_pay_{$PaymentReport['id_order']}"],
                ],
                [
                    ['text' => $textbotlang['users']['Balance']['addBalanceUser'], 'callback_data' => "addbalamceuser_{$PaymentReport['id_order']}"],
                    ['text' => $textbotlang['users']['Balance']['blockedfake'], 'callback_data' => "blockuserfake_{$PaymentReport['id_user']}"],
                ],
                [
                    ['text' => $textbotlang['keyboard']['configInfo'], 'callback_data' => "manageinvoice_{$nameloc['id_invoice']}"],
                ]
            ]
        ]);
        $textsendrasid = sprintf($textbotlang['Admin']['reportgroup']['newPaymentRenew'], $usernamepanel, $prodcut['name_product'], $first_name, $from_id, $from_id, $format_balance, $PaymentReport['id_order'], $username, $Paymentusercount, $format_price_cart, $caption, $text);
    } elseif ($user['Processing_value_tow'] == "getextravolumeuser") {
        $partsdic = explode("%", $user['Processing_value_one']);
        $usernamepanel = $partsdic[0];
        $volumes = $partsdic[1];
        $textsendrasid = sprintf($textbotlang['Admin']['reportgroup']['newPaymentExtraVolume'], $usernamepanel, $volumes, $first_name, $from_id, $from_id, $format_balance, $PaymentReport['id_order'], $username, $Paymentusercount, $format_price_cart, $caption, $text);
    } elseif ($user['Processing_value_tow'] == "getextratimeuser") {
        $partsdic = explode("%", $user['Processing_value_one']);
        $usernamepanel = $partsdic[0];
        $time = $partsdic[1];
        $textsendrasid = sprintf($textbotlang['Admin']['reportgroup']['newPaymentExtraTime'], $usernamepanel, $time, $first_name, $from_id, $from_id, $format_balance, $PaymentReport['id_order'], $username, $Paymentusercount, $format_price_cart, $caption, $text);
    } else {

        $textsendrasid = sprintf($textbotlang['Admin']['reportgroup']['newPaymentBalance'], $first_name, $from_id, $from_id, $format_balance, $PaymentReport['id_order'], $username, $Paymentusercount, $format_price_cart, $caption, $text);
    }
    foreach ($admin_ids as $id_admin) {
        $adminrulecheck = select("admin", "*", "id_admin", $id_admin, "select");
        if ($adminrulecheck['rule'] == "support")
            continue;
        if ($photo) {
            telegram('sendphoto', [
                'chat_id' => $id_admin,
                'photo' => $photoid,
                'caption' => $textbotlang['users']['Balance']['receiptimage'],
                'parse_mode' => "HTML",
            ]);
        }
        sendmessage($id_admin, $textsendrasid, $Confirm_pay, 'HTML');
    }
    if ($user['Processing_value_tow'] == "getconfigafterpay") {
        sendmessage($from_id, $textbotlang['users']['Balance']['sendReceiptAndConfig'], $keyboard, 'HTML');
    } else {
        $rcpt_sent = sendmessage($from_id, $textbotlang['users']['Balance']['sendReceipt'], $keyboard, 'HTML');
        update("Payment_report", "receipt_msg_id", intval($rcpt_sent['result']['message_id'] ?? 0), "id_order", $PaymentReport['id_order']);
    }
    update("Payment_report", "payment_Status", "waiting", "id_order", $PaymentReport['id_order']);
    update("Payment_report", "dec_not_confirmed", "$text $caption", "id_order", $PaymentReport['id_order']);
    $dateacc = date('Y/m/d H:i:s');
    update("Payment_report", "at_updated", $dateacc, "id_order", $PaymentReport['id_order']);
} elseif ($user['step'] == "cart_to_cart_user") {
    $format_balance = wallet_amount_text($user);
    // a receipt can be a photo (with or without a caption) OR plain text -
    // people who bank by SMS often have no screenshot to send, just the bank's
    // message, and refusing that used to leave them with no way to pay.
    // An album is still refused: the admin notification below sends exactly
    // one photo, so extra images would be silently dropped.
    if (isset($update['message']['media_group_id'])) {
        sendmessage($from_id, $textbotlang['users']['Balance']['onlyOneImage'], null, 'HTML');
        return;
    }
    $rcpt_hasPhoto = !empty($photo);
    $rcpt_text = trim((string) $text);
    // A text receipt has to carry at least one digit: every real bank SMS
    // states an amount, while none of the bot's own menu-button labels contain
    // a digit in any of the 5 languages. That distinction matters because a few
    // main-menu buttons (تعرفه اشتراک ها، زیر مجموعه گیری، تمدید سرویس،
    // گردونه شانس، درخواست نمایندگی) are dispatched further down index.php than
    // this step, so their label text reaches here - without this check, tapping
    // one of them mid-flow would be forwarded to the admin as "the receipt" and
    // would flip the invoice to waiting.
    $rcpt_hasText = ($rcpt_text !== '' && preg_match('/[0-9۰-۹٠-٩]/u', $rcpt_text) === 1);
    if (!$rcpt_hasPhoto && !$rcpt_hasText) {
        sendmessage($from_id, $textbotlang['users']['Balance']['receiptNeedsPhotoOrText'], null, 'HTML');
        return;
    }
    step('home', $from_id);
    $PaymentReport = select("Payment_report", "*", "id_order", $user['Processing_value']);
    if ($PaymentReport == false) {
        sendmessage($from_id, $textbotlang['users']['infoFetchErrorRestart'], $keyboard, 'HTML');
        return;
    }
    // The invoice can settle itself between tapping "رسید را بفرستید" and
    // actually sending the receipt - SMS Forward confirms from the bank SMS,
    // croncard confirms on a timer. Without this check the receipt would still
    // be forwarded to the admins AND the row would be pushed back to 'waiting'
    // further down, which re-opens an already-credited payment: the admin's
    // "paid/reject" guard would no longer trip, so tapping تایید would run
    // DirectPayment() a second time and credit the user twice.
    if ($PaymentReport['payment_Status'] == "paid") {
        sendmessage($from_id, $textbotlang['users']['Balance']['alreadyConfirmed'], $keyboard, 'HTML');
        return;
    }
    $Confirm_pay = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['Balance']['confirmPaying'], 'callback_data' => "Confirm_pay_{$PaymentReport['id_order']}"],
                ['text' => $textbotlang['users']['Balance']['rejectPay'], 'callback_data' => "reject_pay_{$PaymentReport['id_order']}"],
            ],
            [
                ['text' => $textbotlang['users']['Balance']['addBalanceUser'], 'callback_data' => "addbalamceuser_{$PaymentReport['id_order']}"],
                ['text' => $textbotlang['users']['Balance']['blockedfake'], 'callback_data' => "blockuserfake_{$PaymentReport['id_user']}"],
            ]
        ]
    ]);
    $format_price_cart = number_format($PaymentReport['price'], 0);
    $split_data = explode('|', $PaymentReport['id_invoice']);
    if ($split_data[0] == "getconfigafterpay") {
        $get_invoice = select("invoice", "*", "username", $split_data[1], "select");
        if ($get_invoice == false) {
            sendmessage($from_id, $textbotlang['users']['Balance']['restartPurchaseOrPay'], $keyboard, 'HTML');
            return;
        }
        $textdiscount = "";
        $textsendrasid = sprintf($textbotlang['Admin']['reportgroup']['newPaymentService2'], $get_invoice['username'], $get_invoice['name_product'], $get_invoice['Volume'], $get_invoice['Service_time'], $first_name, $from_id, $from_id, $format_balance, $PaymentReport['id_order'], $username, $format_price_cart);
        sendmessage($from_id, $textbotlang['users']['Balance']['sendReceiptAndConfig'], $keyboard, 'HTML');
    } elseif ($split_data[0] == "getextenduser") {
        $partsdic = explode("%", $split_data[1]);
        $usernamepanel = $partsdic[0];
        $sql = "SELECT * FROM service_other WHERE username = :username  AND value  LIKE CONCAT('%', :value, '%') AND id_user = :id_user ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $usernamepanel, PDO::PARAM_STR);
        $stmt->bindParam(':value', $partsdic[1], PDO::PARAM_STR);
        $stmt->bindParam(':id_user', $from_id);
        $stmt->execute();
        $service_other = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($service_other == false) {
            sendmessage($from_id, $textbotlang['users']['infoFetchErrorRestart'], $keyboard, 'HTML');
            return;
        }
        $service_other = json_decode($service_other['value'], true);
        $nameloc = select("invoice", "*", "username", $usernamepanel, "select");
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        $codeproduct = $service_other['code_product'];
        // 🧩 a renewal with a custom plan: the report only names it
        if (cvol_is_renew_code($codeproduct)) {
            $prodcut = ['code_product' => 'custom_volume', 'name_product' => $nameloc['name_product'], 'Service_time' => $service_other['Service_time'] ?? $nameloc['Service_time'], 'Volume_constraint' => $service_other['volumebuy'] ?? $nameloc['Volume']];
        } else {
            $nameloc = select("invoice", "*", "username", $usernamepanel, "select");
            $__q12 = $pdo->prepare("SELECT * FROM product WHERE (Location = ? OR Location = '/all') AND agent= ? AND code_product = ? AND (FIND_IN_SET(?, lang) OR lang = 'all' OR lang IS NULL OR lang = '')");
            $__q12->bindValue(1, $nameloc['Service_location'], PDO::PARAM_STR);
            $__q12->bindValue(2, $user['agent'], PDO::PARAM_STR);
            $__q12->bindValue(3, $codeproduct, PDO::PARAM_STR);
            $__q12->bindValue(4, $user['lang'] ?? 'fa', PDO::PARAM_STR);
            $__q12->execute();
            $prodcut = $__q12->fetch(PDO::FETCH_ASSOC);
        }
        $Confirm_pay = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['Balance']['confirmPaying'], 'callback_data' => "Confirm_pay_{$PaymentReport['id_order']}"],
                    ['text' => $textbotlang['users']['Balance']['rejectPay'], 'callback_data' => "reject_pay_{$PaymentReport['id_order']}"],
                ],
                [
                    ['text' => $textbotlang['users']['Balance']['addBalanceUser'], 'callback_data' => "addbalamceuser_{$PaymentReport['id_order']}"],
                    ['text' => $textbotlang['users']['Balance']['blockedfake'], 'callback_data' => "blockuserfake_{$PaymentReport['id_user']}"],
                ],
                [
                    ['text' => $textbotlang['keyboard']['configInfo'], 'callback_data' => "manageinvoice_{$nameloc['id_invoice']}"],
                ]
            ]
        ]);
        $textsendrasid = sprintf($textbotlang['Admin']['reportgroup']['newPaymentRenew2'], $usernamepanel, $prodcut['name_product'], $first_name, $from_id, $from_id, $format_balance, $PaymentReport['id_order'], $username, $format_price_cart);
        sendmessage($from_id, $textbotlang['users']['Balance']['receiptSentRenew'], $keyboard, 'HTML');
    } elseif ($split_data[0] == "getextravolumeuser") {
        $partsdic = explode("%", $split_data[1]);
        $usernamepanel = $partsdic[0];
        $volumes = $partsdic[1];
        $textsendrasid = sprintf($textbotlang['Admin']['reportgroup']['newPaymentExtraVolume2'], $usernamepanel, $volumes, $first_name, $from_id, $from_id, $format_balance, $PaymentReport['id_order'], $username, $format_price_cart);
        sendmessage($from_id, $textbotlang['users']['Balance']['receiptSentExtraVolume'], $keyboard, 'HTML');
    } elseif ($split_data[0] == "getextratimeuser") {
        $partsdic = explode("%", $split_data[1]);
        $usernamepanel = $partsdic[0];
        $time = $partsdic[1];
        $textsendrasid = sprintf($textbotlang['Admin']['reportgroup']['newPaymentExtraTime2'], $usernamepanel, $time, $first_name, $from_id, $from_id, $format_balance, $PaymentReport['id_order'], $username, $format_price_cart);
        sendmessage($from_id, $textbotlang['users']['Balance']['receiptSentExtraTime'], $keyboard, 'HTML');
    } else {

        $textsendrasid = sprintf($textbotlang['Admin']['reportgroup']['newPaymentBalance2'], $first_name, $from_id, $from_id, $format_balance, $PaymentReport['id_order'], $username, $format_price_cart);
        $rcpt_sent = sendmessage($from_id, $textbotlang['users']['Balance']['sendReceipt'], $keyboard, 'HTML');
        update("Payment_report", "receipt_msg_id", intval($rcpt_sent['result']['message_id'] ?? 0), "id_order", $PaymentReport['id_order']);
    }
    // both the caption and a text receipt are user-supplied and go out with
    // parse_mode HTML - escaped so a receipt containing < > & can't break the
    // admin's message (which would make the send fail outright, silently
    // costing the admin the receipt)
    $rcpt_caption = htmlspecialchars((string) $caption, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $rcpt_textBlock = $textbotlang['users']['Balance']['textReceiptFromUser'] . "\n<blockquote>"
        . htmlspecialchars($rcpt_text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</blockquote>';
    foreach ($admin_ids as $id_admin) {
        $adminrulecheck = select("admin", "*", "id_admin", $id_admin, "select");
        if ($adminrulecheck['rule'] == "support")
            continue;
        if ($rcpt_hasPhoto) {
            telegram('sendphoto', [
                'chat_id' => $id_admin,
                'photo' => $photoid,
                'caption' => $rcpt_caption,
                'parse_mode' => "HTML",
            ]);
        } else {
            sendmessage($id_admin, $rcpt_textBlock, null, 'HTML');
        }
        sendmessage($id_admin, $textsendrasid, $Confirm_pay, 'HTML');
    }
    update("Payment_report", "payment_Status", "waiting", "id_order", $PaymentReport['id_order']);
    $dateacc = date('Y/m/d H:i:s');
    update("Payment_report", "at_updated", $dateacc, "id_order", $PaymentReport['id_order']);
} elseif ($datain == "Discount") {
    $bakinfos = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['status']['backinfo'], 'callback_data' => "account"],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['Discount']['getcode'], $bakinfos);
    step('get_code_user', $from_id);
} elseif ($user['step'] == "get_code_user") {
    if (!in_array($text, $code_Discount)) {
        sendmessage($from_id, $textbotlang['users']['Discount']['notcode'], null, 'HTML');
        return;
    }
    $checklimit = select("Discount", "*", "code", $text, "select");
    if ($checklimit['limitused'] >= $checklimit['limituse']) {
        sendmessage($from_id, $textbotlang['users']['Discount']['errorLimitDiscount'], $backuser, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("SELECT * FROM Giftcodeconsumed WHERE id_user = :from_id AND code = :code");
    $stmt->bindParam(':from_id', $from_id, PDO::PARAM_STR);
    $stmt->bindParam(':code', $text, PDO::PARAM_STR);
    $stmt->execute();
    $Checkcodesql = $stmt->rowCount();
    if ($Checkcodesql != 0) {
        sendmessage($from_id, $textbotlang['users']['Discount']['giftcodeonce'], $keyboard, 'HTML');
        step('home', $from_id);
        return;
    }
    $stmt = $pdo->prepare("SELECT * FROM Discount WHERE code = :code LIMIT 1");
    $stmt->bindParam(':code', $text);
    $stmt->execute();
    $get_codesql = $stmt->fetch(PDO::FETCH_ASSOC);
    // codes are made in the shop's main currency: into that wallet, so a
    // 50,000 code can never become $50,000
    wallet_credit($from_id, $get_codesql['price'], currency_default_code());
    $discountlimitadd = intval($checklimit['limitused']) + 1;
    update("Discount", "limitused", $discountlimitadd, "code", $text);
    step('home', $from_id);
    $text_balance_code = sprintf($textbotlang['users']['Discount']['giftcodesuccess'], $get_codesql['price']);
    sendmessage($from_id, $text_balance_code, $keyboard, 'HTML');
    $stmt = $pdo->prepare("INSERT INTO Giftcodeconsumed (id_user, code) VALUES (:id_user, :code)");
    $stmt->execute([
        ':id_user' => $from_id,
        ':code' => $text,
    ]);
    $text_report = sprintf($textbotlang['users']['Discount']['giftcodeused'], $username, $from_id, $text);
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif ($text == $textbotlang['textbot']['tariffList'] || $datain == "Tariff_list") {
    sendmessage($from_id, $textbotlang['textbot']['tariffListDesc'], null, 'HTML');
} elseif ($datain == "colselist") {
    deletemessage($from_id, $message_id);
    sendmessage($from_id, $textbotlang['users']['back'], $keyboard, 'HTML');
} elseif ($text == $textbotlang['textbot']['affiliates'] || $datain == "affiliatesbtn") {
    if (!mainmenu_btn_active($user['lang'] ?? 'fa', "text_affiliates")) {
        sendmessage($from_id, $textbotlang['users']['buttonDisabled'], null, 'HTML');
        return;
    }
    if (feature_value('affiliatesstatus', $user['lang'] ?? 'fa', $setting['affiliatesstatus']) == "offaffiliates") {
        sendmessage($from_id, $textbotlang['users']['affiliates']['offaffiliates'], null, 'HTML');
        return;
    }
    $affiliates = select("affiliates", "*", null, null, "select");
    // banner, percentages and gift amounts are all per-language now (🌐 وضعیت
    // قابلیت‌ها (هر زبان) -> ⚙️ تنظیمات), falling back to the shared value
    $aff_lang = $user['lang'] ?? 'fa';
    // 1️⃣ 💼 زیرمجموعه‌گیری و هدیه خوش‌آمد - its banner and page, when on; for
    // one ⛔️ kept out of it (👤 مدیریت کاربر), a message saying so instead
    $aff_classic = aff_classic_on($aff_lang);
    if ($aff_classic && aff_blocked($from_id, 'c')) {
        bottext_extras_key_hint('users.affiliates.classicBlocked');
        sendmessage($from_id, $textbotlang['users']['affiliates']['classicBlocked'], null, 'HTML');
    } elseif ($aff_classic) {
        list($aff_banner_text, $aff_banner_media) = feature_aff_banner($aff_lang, $affiliates);
        $textaffiliates = "{$aff_banner_text}\n\n🔗 https://t.me/$usernamebot?start=$from_id";
        if (strlen($aff_banner_media) >= 5) {
            telegram('sendphoto', [
                'chat_id' => $from_id,
                'photo' => $aff_banner_media,
                'caption' => $textaffiliates,
                'parse_mode' => "HTML",
            ]);
        }
        $affiliatescommission = select("affiliates", "*", null, null, "select");
        $sqlPanel = sprintf("SELECT COUNT(*) AS orders, SUM(price_product) AS total_price\n                 FROM invoice \n                 WHERE Status IN ('active', 'end_of_time', 'sendedwarn', 'send_on_hold') \n                 AND refral = '%s'\n                 AND name_product != '{$textbotlang['common']['labels']['testServiceName']}'", $from_id);
        $stmt = $pdo->prepare($sqlPanel);
        $stmt->execute();
        $inforefral = $stmt->fetch(PDO::FETCH_ASSOC);
        $inforefral['total_price'] = ($inforefral['total_price'] * feature_setting_value('aff_percent', $aff_lang, $setting['affiliatespercentage'])) / 100;
        $share_url = "https://t.me/share/url?url=https://t.me/$usernamebot?start=$from_id";
        $share_row = [];
        if (!bt_button_hidden($aff_lang, 'keyboard.receiveMembershipGift')) {
            $share_row[] = bt_button($aff_lang, 'keyboard.receiveMembershipGift', $textbotlang['keyboard']['receiveMembershipGift'], "get_gift_start", 'success');
        }
        if (!bt_button_hidden($aff_lang, 'keyboard.shareLink')) {
            // a url button carries no callback_data, so only its label is overridden
            $share_row[] = ['text' => bt_reply_label($aff_lang, 'keyboard.shareLink', $textbotlang['keyboard']['shareLink']), 'url' => $share_url];
        }
        $keyboard_share = json_encode(['inline_keyboard' => $share_row ? [$share_row] : []]);
        $text_start = "";
        $text_porsant = "";
        $Percent_porsant = feature_setting_value('aff_percent', $aff_lang, $setting['affiliatespercentage']);
        $aff_cur = currency_for_user($user);
        $sum_order = money($inforefral['total_price'], $aff_cur);
        if (feature_setting_value('aff_startgift', $aff_lang, $affiliatescommission['Discount']) == "onDiscountaffiliates") {
            $text_start = text_fill_s($textbotlang['users']['affiliates']['membershipGiftInfo'], money(feature_setting_value('aff_giftamount', $aff_lang, $affiliatescommission['price_Discount']), $aff_cur));
        }
        if (feature_setting_value('aff_commission', $aff_lang, $affiliatescommission['status_commission']) == "oncommission") {
            $text_porsant = text_fill_s($textbotlang['users']['affiliates']['purchaseCommissionInfo'], $Percent_porsant);
        }
        $textaffiliates = text_fill_s($textbotlang['users']['affiliates']['welcomeGiftInfo'], $text_start, $text_porsant, $user['affiliatescount'], $inforefral['orders'], $sum_order);

        sendmessage($from_id, $textaffiliates, $keyboard_share, 'HTML');
    }
    // 2️⃣ 🎁 کانفیگ رایگان با دعوت, when this customer's language has it
    affrw_check($from_id);
    $affrw_key = 'rewardInfo';
    $affrw_text = affrw_info_text($user, $textbotlang, "https://t.me/$usernamebot?start=$from_id", $affrw_key);
    if ($affrw_text !== null) {
        bottext_extras_key_hint("users.affiliates.{$affrw_key}");
        sendmessage($from_id, $affrw_text, (affrw_row($from_id)['status'] ?? '') === 'naming' ? affrw_name_kb($textbotlang) : null, 'HTML');
        // 🛡 دسترسی ادمین: an admin with no limit gets the config right here,
        // every tap - no invites, nobody's approval - until 🔁 چند بار is used up
        // (not while ⛔️ kept out of 🎁)
        if (affrw_admin_free($from_id) && !aff_blocked($from_id, 'r')) {
            affrw_admin_give($from_id);
        }
    }
    // neither: say so, instead of a tap that answers nothing
    if (!$aff_classic && $affrw_text === null) {
        bottext_extras_key_hint('users.affiliates.nothingActive');
        sendmessage($from_id, $textbotlang['users']['affiliates']['nothingActive'], null, 'HTML');
    }
} elseif ($datain == "get_gift_start") {
    $gift_status = select("affiliates", "*", null, null, "select");
    if (!aff_classic_on($user['lang'] ?? 'fa') || feature_setting_value('aff_startgift', $user['lang'] ?? 'fa', $gift_status['Discount']) == "offDiscountaffiliates") {
        sendmessage($from_id, $textbotlang['users']['sectionDisabled'], $keyboard, 'HTML');
        return;
    }
    if (!in_array($user['affiliates'], $users_ids)) {
        sendmessage($from_id, $textbotlang['users']['affiliates']['notReferral'], $keyboard, 'HTML');
        return;
    }
    $reagent = select("reagent_report", "*", "user_id", $from_id, "select");
    if (!$reagent) {
        sendmessage($from_id, $textbotlang['users']['affiliates']['notReferral'], $keyboard, 'HTML');
        return;
    }
    update("reagent_report", "get_gift", true, "user_id", $from_id);
    if ($reagent['get_gift']) {
        sendmessage($from_id, $textbotlang['users']['affiliates']['membershipGiftClaimed'], $keyboard, 'HTML');
        return;
    }
    $reagent['get_gift'] = true;
    $price_gift_Start = select("affiliates", "*", null, null, "select");
    $price_gift_Start = ((float) feature_setting_value('aff_giftamount', $user['lang'] ?? 'fa', $price_gift_Start['price_Discount'])) / 2;
    $useraffiliates = select("user", "*", 'id', $reagent['reagent'], "select");
    // an inviter ⛔️ kept out of 💼 since gets no share of it - the newcomer
    // still gets theirs; the report shows the inviter's wallet unchanged
    $aff_regentBlocked = aff_blocked($reagent['reagent'], 'c');
    $Balance_add_regent = $aff_regentBlocked ? wallet_amount_text($useraffiliates) : wallet_credit($reagent['reagent'], $price_gift_Start, currency_for_user($user));
    $Balance_add_user = $user['Balance'] + $price_gift_Start;
    balance_add($from_id, $price_gift_Start);
    $addbalancediscount = money($price_gift_Start, currency_for_user($user));
    // to the inviter, in their own language
    if (!$aff_regentBlocked) {
        bottext_extras_key_hint('users.affiliates.joinedGift');
        sendmessage($reagent['reagent'], payer_texts($reagent['reagent'])['users']['affiliates']['joinedGift'], null, 'html');
    }
    sendmessage($from_id, $textbotlang['users']['affiliates']['joinGiftActivated'], null, 'html');
    $report_join_gift = sprintf($textbotlang['Admin']['reportgroup']['membershipGiftPaid'], $from_id, $username, $reagent['reagent'], wallet_amount_text($user), $Balance_add_user, wallet_amount_text($useraffiliates), $Balance_add_regent);
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $porsantreport,
            'text' => $report_join_gift,
            'parse_mode' => "HTML"
        ]);
    }
} elseif (preg_match('/Extra_volumes_(\w+)_(.*)/', $datain, $dataget)) {
    $usernamepanel = $dataget[1];
    $locations = select("marzban_panel", "*", "code_panel", $dataget[2], "select");
    $location = $locations['name_panel'];
    $eextraprice = json_decode($locations['priceextravolume'], true);
    $extrapricevalue = $eextraprice[$user['agent']];
    update("user", "Processing_value", $usernamepanel, "id", $from_id);
    update("user", "Processing_value_one", $location, "id", $from_id);

    $textextra = sprintf($textbotlang['users']['extraVolume']['enterextravolume'], $extrapricevalue);
    sendmessage($from_id, $textextra, $backuser, 'HTML');
    step('getvolumeextras', $from_id);
} elseif ($user['step'] == "getvolumeextras") {
    $ev_gb = volume_parse($text, 'gb');
    if ($ev_gb === null) {
        sendmessage($from_id, $textbotlang['common']['invalidVolume'], $backuser, 'HTML');
        return;
    }
    $text = volume_store($ev_gb);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value_one'], "select");
    $eextraprice = json_decode($marzban_list_get['priceextravolume'], true);
    $extrapricevalue = $eextraprice[$user['agent']];
    $priceextra = $extrapricevalue * $text;
    $keyboardsetting = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['extraVolume']['extracheck'], 'callback_data' => 'confirmaextras_' . $priceextra],
            ]
        ]
    ]);
    $priceextra = number_format($priceextra, 0);
    $extrapricevalues = number_format($extrapricevalue, 0);
    $textextra = sprintf($textbotlang['users']['extraVolume']['extravolumeinvoice'], $extrapricevalues, $priceextra, $text);
    sendmessage($from_id, $textextra, $keyboardsetting, 'HTML');
    step('home', $from_id);
} elseif (preg_match('/confirmaextras_(\d+(?:\.\d+)?)/', $datain, $dataget)) {
    $volume = $dataget[1];
    if ($user['Balance'] < $volume && $user['agent'] != "n2" && !$admin_buy_free) {
        $marzbandirectpay = shop_feature_value('paydirect', $user['lang'] ?? 'fa', select('shopSetting', "*", "Namevalue", "statusdirectpabuy", "select")['value']);
        if ($marzbandirectpay == "offdirectbuy") {
            $minbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']]);
            $maxbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']]);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['status']['backinfo'], 'callback_data' => "account"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['Balance']['insufficientbalance'], $minbalance, $maxbalance), $bakinfos, 'HTML');
            step('getprice', $from_id);
            return;
        } else {
            if (intval($user['pricediscount']) != 0) {
                $result = ($volume * $user['pricediscount']) / 100;
                $volume = $volume - $result;
                sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
            }
            $Balance_prim = $volume - $user['Balance'];
            update("user", "Processing_value", $Balance_prim, "id", $from_id);
            sendmessage($from_id, $noCreditText, $step_payment, 'HTML');
            step('get_step_payment', $from_id);
            return;
        }
    }
    if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
        if (($user['Balance'] - $volume) < intval("-" . $user['maxbuyagent'])) {
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value_one'], "select");
    if ($marzban_list_get == false) {
        sendmessage($from_id, $textbotlang['users']['status']['error'], null, 'html');
        return;
    }
    $eextraprice = json_decode($marzban_list_get['priceextravolume'], true);
    $extrapricevalue = $eextraprice[$user['agent']];
    deletemessage($from_id, $message_id);
    if (intval($user['pricediscount']) != 0) {
        $result = ($volume * $user['pricediscount']) / 100;
        $volume = $volume - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }

    $DataUserOut = $ManagePanel->DataUser($user['Processing_value_one'], $user['Processing_value']);
    $data_limit = $DataUserOut['data_limit'] + ((float) $extrapricevalue > 0 ? (float) $volume / (float) $extrapricevalue * pow(1024, 3) : 0);
    $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username, value, type, time, price) VALUES (:id_user, :username, :value, :type, :time, :price)");
    $value = $data_limit;
    $dateacc = date('Y/m/d H:i:s');
    $type = "extra_not_user";
    $stmt->execute([
        ':id_user' => $from_id,
        ':username' => $user['Processing_value'],
        ':value' => $value,
        ':type' => $type,
        ':time' => $dateacc,
        ':price' => $admin_buy_free ? 0 : $volume,
    ]);
    $data_limit_new = (float) $extrapricevalue > 0 ? (float) $volume / (float) $extrapricevalue : 0;
    $extra_volume = $ManagePanel->extra_volume($user['Processing_value'], $marzban_list_get['code_panel'], $data_limit_new);
    if ($extra_volume['status'] == false) {
        $extra_volume['msg'] = json_encode($extra_volume['msg']);
        $textreports = sprintf($textbotlang['Admin']['reportgroup']['errorExtraVolume2'], $user['Processing_value_one'], $user['Processing_value'], $extra_volume['msg']);
        sendmessage($from_id, $textbotlang['users']['extraVolume']['serviceError'], null, 'HTML');
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $textreports,
                'parse_mode' => "HTML"
            ]);
        }
        return;
    }
    $Balance_Low_user = $user['Balance'] - ($admin_buy_free ? 0 : $volume);
    balance_add($from_id, -($admin_buy_free ? 0 : $volume));
    $back = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'backuser'],
            ]
        ]
    ]);
    sendmessage($from_id, $textbotlang['users']['extend']['thanks'], $back, 'HTML');
    $volumes = $volume / $extrapricevalue;
    $volumes = number_format($volumes, 0);
    $text_report = sprintf($textbotlang['Admin']['reportgroup']['volumePurchase'], $from_id, $volumes, $volume, wallet_amount_text($user), $user['Processing_value']);
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif ($datain == "searchservice") {
    sendmessage($from_id, $textbotlang['users']['search']['usernamgeget'], $backuser, 'HTML');
    step('getuseragnetservice', $from_id);
} elseif ($datain == "Responseuser") {
    step('getmessageAsuser', $from_id);
    sendmessage($from_id, $textbotlang['Admin']['manageUser']['getTextResponse'], $backuser, 'HTML');
} elseif ($user['step'] == "getmessageAsuser") {
    sendmessage($from_id, $textbotlang['users']['support']['sendmessageadmin'], $keyboard, 'HTML');
    $Response = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['support']['answermessage'], 'callback_data' => 'Response_' . $from_id],
            ],
        ]
    ]);
    foreach ($admin_ids as $id_admin) {
        $adminrulecheck = select("admin", "*", "id_admin", $id_admin, "select");
        if ($adminrulecheck['rule'] == "Seller")
            continue;
        if ($text) {
            $textsendadmin = sprintf($textbotlang['Admin']['messageBulk']['userMessage'], $from_id, $username, $caption . $text);
            sendmessage($id_admin, $textsendadmin, $Response, 'HTML');
        }
        if ($photo) {
            $textsendadmin = sprintf($textbotlang['Admin']['messageBulk']['userResponse'], $from_id, $username, $caption);
            telegram('sendphoto', [
                'chat_id' => $id_admin,
                'photo' => $photoid,
                'reply_markup' => $Response,
                'caption' => $textsendadmin,
                'parse_mode' => "HTML",
            ]);
        }
    }
    step('home', $from_id);
} elseif (($text == $textbotlang['textbot']['agentPanel'] || $datain == "agentpanel") && in_array($user['agent'] ?? '', ['n', 'n2'], true)) {
    if (glass_on($user['lang'] ?? 'fa', $setting)) {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['agent']['welcome'], $keyboardagent, 'HTML');
    } else {
        sendmessage($from_id, $textbotlang['users']['agent']['welcome'], $keyboardagent, 'HTML');
    }
} elseif ($text == $textbotlang['users']['agent']['customnameusername'] || $datain == "selectname") {
    sendmessage($from_id, $textbotlang['users']['selectusername'], $backuser, 'html');
    step('selectusernamecustom', $from_id);
} elseif ($user['step'] == "selectusernamecustom") {
    if (!preg_match('~(?!_)^[a-z][a-z\d_]{2,32}(?<!_)$~i', $text)) {
        sendmessage($from_id, $textbotlang['users']['invalidusername'], $backuser, 'HTML');
        return;
    }
    sendmessage($from_id, $textbotlang['users']['agent']['usernameSaved'], $keyboardagent, 'html');
    update("user", "namecustom", $text, "id", $from_id);
    step("home", $from_id);
} elseif ($text == $textbotlang['textbot']['requestAgent'] || $datain == "requestagent") {
    if ($user['Balance'] < $setting['agentreqprice']) {
        $priceagent = number_format($setting['agentreqprice']);
        sendmessage($from_id, sprintf($textbotlang['users']['agent']['insufficientbalanceagent'], $priceagent), $backuser, 'HTML');
        return;
    }
    $countagentrequest = select("Requestagent", "*", "id", $from_id, "count");
    if ($countagentrequest != 0) {
        sendmessage($from_id, $textbotlang['users']['agent']['requestreport'], null, 'html');
        return;
    }
    if ($user['agent'] != "f") {
        sendmessage($from_id, $textbotlang['users']['agent']['isagent'], null, 'html');
        return;
    }
    if ($datain == "requestagent") {
        Editmessagetext($from_id, $message_id, $textbotlang['textbot']['agentRequestDesc'], $backuser);
    } else {
        sendmessage($from_id, $textbotlang['textbot']['agentRequestDesc'], $backuser, 'html');
    }
    step("getagentrequest", $from_id);
} elseif ($user['step'] == "getagentrequest" && $text) {
    $balancelow = $user['Balance'] - $setting['agentreqprice'];
    balance_add($from_id, -$setting['agentreqprice']);
    sendmessage($from_id, $textbotlang['users']['agent']['endrequest'], $keyboard, 'html');
    step("home", $from_id);
    $stmt = $pdo->prepare("INSERT INTO Requestagent (id, username, time, Description, status, type) VALUES (:id, :username, :time, :description, :status, :type)");
    $status = "waiting";
    $type = "None";
    $current_time = time();
    $description = $text;
    $requestAgentInserted = false;
    try {
        $stmt->execute([
            ':id' => $from_id,
            ':username' => $username,
            ':time' => $current_time,
            ':description' => $description,
            ':status' => $status,
            ':type' => $type,
        ]);
        $requestAgentInserted = true;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Incorrect string value') !== false) {
            $tableConverted = ensureTableUtf8mb4('Requestagent');
            if ($tableConverted) {
                try {
                    $stmt->execute([
                        ':id' => $from_id,
                        ':username' => $username,
                        ':time' => $current_time,
                        ':description' => $description,
                        ':status' => $status,
                        ':type' => $type,
                    ]);
                    $requestAgentInserted = true;
                } catch (PDOException $retryException) {
                    error_log('Retry after charset conversion failed: ' . $retryException->getMessage());
                }
            }

            if (!$requestAgentInserted) {
                $sanitisedDescription = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $description);
                if ($sanitisedDescription !== $description) {
                    $stmt->execute([
                        ':id' => $from_id,
                        ':username' => $username,
                        ':time' => $current_time,
                        ':description' => $sanitisedDescription,
                        ':status' => $status,
                        ':type' => $type,
                    ]);
                    $requestAgentInserted = true;
                } else {
                    throw $e;
                }
            }
        } else {
            throw $e;
        }
    }

    if (!$requestAgentInserted) {
        throw new RuntimeException('Failed to persist agent request description.');
    }
    $textrequestagent = sprintf($textbotlang['users']['agent']['agentRequest'], $from_id, $username, $first_name, $text);
    $keyboardmanage = json_encode([
        'inline_keyboard' => [
            [['text' => $textbotlang['users']['agent']['acceptrequest'], 'callback_data' => "addagentrequest_" . $from_id], ['text' => $textbotlang['users']['agent']['rejectrequest'], 'callback_data' => "rejectrequesta_" . $from_id]],
            [
                ['text' => $textbotlang['users']['SendMessage'], 'callback_data' => 'Response_' . $from_id],
            ],
        ]
    ]);
    foreach ($admin_ids as $admin) {
        sendmessage($admin, $textrequestagent, $keyboardmanage, 'HTML');
    }
} elseif ($text == "/privacy") {
    sendmessage($from_id, $textbotlang['textbot']['rules'], null, 'HTML');
} elseif ($text == $textbotlang['textbot']['wheelLuck'] || $datain == "wheel_luck" || $text == "/gift") {
    if (!mainmenu_btn_active($user['lang'] ?? 'fa', "text_wheel_luck")) {
        sendmessage($from_id, $textbotlang['users']['buttonDisabled'], null, 'HTML');
        return;
    }
    if (feature_value('wheelagent', $user['lang'] ?? 'fa', $setting['wheelagent']) == "0" and $user['agent'] != "f") {
        sendmessage($from_id, $textbotlang['users']['buttonDisabledForYou'], null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE name_product != :name_product  AND id_user = :id_user AND status != 'Unpaid'");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->bindParam(':name_product', $textbotlang['common']['labels']['testServiceName']);
    $stmt->execute();
    $countinvoice = $stmt->rowCount();
    if (intval(feature_value('statusfirstwheel', $user['lang'] ?? 'fa', $setting['statusfirstwheel'])) == 1 and $countinvoice != 0) {
        sendmessage($from_id, $textbotlang['users']['sell']['noPurchaseUsersOnly'], null, 'HTML');
        return;
    }
    if (feature_value('wheelـluck', $user['lang'] ?? 'fa', $setting['wheelـluck']) == "0" or (feature_value('wheelagent', $user['lang'] ?? 'fa', $setting['wheelagent']) == "0" and $users['agent'] != "f")) {
        sendmessage($from_id, $textbotlang['users']['wheelLuck']['featureDisabled'], null, 'HTML');
        return;
    }
    $stmt = $pdo->prepare("SELECT * FROM wheel_list  WHERE id_user = :from_id ORDER BY time DESC LIMIT 1");
    $stmt->bindParam(':from_id', $from_id);
    $stmt->execute();
    $USER = $stmt->fetch(PDO::FETCH_ASSOC);
    $timelast = isset($USER['time']) ? strtotime($USER['time']) : false;
    if ($USER && $timelast !== false && (time() - $timelast) <= 86400) {
        sendmessage($from_id, $textbotlang['users']['wheelLuck']['alreadyParticipated'], null, 'HTML');
        return;
    }
    if (intval(feature_value('Dice', $user['lang'] ?? 'fa', $setting['Dice'])) == 1) {
        $diceResponse = telegram('sendDice', [
            'chat_id' => $from_id,
            'emoji' => "🎲",
        ]);
        sleep((int) 4.5);
    } else {
        $diceResponse = telegram('sendDice', [
            'chat_id' => $from_id,
            'emoji' => "🎰",
        ]);
        sleep(2);
    }
    if (!is_array($diceResponse) || empty($diceResponse['ok']) || !isset($diceResponse['result']['dice']['value'])) {
        $errorContext = is_array($diceResponse) ? json_encode($diceResponse) : (is_string($diceResponse) ? $diceResponse : 'empty response');
        error_log('Failed to receive dice value for wheel_luck: ' . $errorContext);
        sendmessage($from_id, $textbotlang['users']['wheelLuck']['resultError'], null, 'HTML');
        return;
    }
    $diceValue = (int) $diceResponse['result']['dice']['value'];
    $dateacc = date('Y/m/d H:i:s');
    $stmt = $pdo->prepare("SELECT * FROM wheel_list  WHERE id_user = :from_id ORDER BY time DESC LIMIT 1");
    $stmt->bindParam(':from_id', $from_id);
    $stmt->execute();
    $USER = $stmt->fetch(PDO::FETCH_ASSOC);
    $timelast = isset($USER['time']) ? strtotime($USER['time']) : false;
    if ($USER && $timelast !== false && (time() - $timelast) <= 86400) {
        sendmessage($from_id, $textbotlang['users']['wheelLuck']['alreadyParticipated'], null, 'HTML');
        return;
    }
    $status = false;
    if (intval(feature_value('Dice', $user['lang'] ?? 'fa', $setting['Dice'])) == 1) {
        if ($diceValue === 6) {
            $status = true;
        }
    } else {
        if (in_array($diceValue, [1, 43, 64, 22], true)) {
            $status = true;
        }
    }
    if ($status) {
        $wheel_prize = feature_setting_value('wheel_price', $user['lang'] ?? 'fa', $setting['wheelـluck_price']);
        $balance_last = $wheel_prize + $user['Balance'];
        balance_add($from_id, $wheel_prize);
        // the prize is denominated in this language's currency, so render it
        // with that currency's symbol instead of a bare "toman" number
        $price = money($wheel_prize, currency_for_user($user));
        sendmessage($from_id, sprintf($textbotlang['users']['wheelLuck']['winnerCongratulations'], $price), null, 'HTML');
        $pricelast = $wheel_prize;
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $otherreport,
                'text' => sprintf(panel_texts()['users']['wheelLuck']['wheelWinner'], $username, $from_id),
                'parse_mode' => "HTML"
            ]);
        }
    } else {
        sendmessage($from_id, $textbotlang['users']['wheelLuck']['notWinner'], null, 'HTML');
        $pricelast = 0;
    }
    $stmt = $pdo->prepare("INSERT IGNORE INTO wheel_list (id_user,first_name,wheel_code,time,price) VALUES (:id_user,:first_name,:wheel_code,:time,:price)");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->bindParam(':first_name', $first_name);
    $stmt->bindParam(':wheel_code', $diceValue);
    $stmt->bindParam(':time', $dateacc);
    $stmt->bindParam(':price', $pricelast);
    $stmt->execute();
} elseif ($text == "/tron") {
    $rates = rate_arze(['TRX']);
    if ($rates === null) {
        sendmessage($from_id, $textbotlang['users']['priceArze']['fetchError'], null, 'HTML');
        return;
    }
    $price = $rates['TRX'];
    sendmessage($from_id, sprintf($textbotlang['users']['priceArze']['tronPrice'], $price), null, 'HTML');
} elseif ($text == "/usd") {
    $rates = rate_arze(['USD']);
    if ($rates === null) {
        sendmessage($from_id, $textbotlang['users']['priceArze']['fetchError'], null, 'HTML');
        return;
    }
    $price = $rates['USD'];
    sendmessage($from_id, sprintf($textbotlang['users']['priceArze']['tetherPrice'], $price), null, 'HTML');
} elseif ($text == $textbotlang['textbot']['extend'] or $datain == "extendbtn") {
    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :id_user AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold')");
    $stmt->bindParam(':id_user', $from_id);
    $stmt->execute();
    $invoices = $stmt->rowCount();
    if ($invoices == 0) {
        sendmessage($from_id, $textbotlang['users']['extend']['emptyServiceforExtend'], null, 'html');
        return;
    }
    $pages = 1;
    update("user", "pagenumber", $pages, "id", $from_id);
    $page = 1;
    $items_per_page = 20;
    $start_index = ($page - 1) * $items_per_page;
    $result = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :from_id AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') ORDER BY time_sell DESC LIMIT :start_index, :items_per_page");
    $result->bindParam(':from_id', $from_id, PDO::PARAM_STR);
    $result->bindParam(':start_index', $start_index, PDO::PARAM_INT);
    $result->bindParam(':items_per_page', $items_per_page, PDO::PARAM_INT);
    $result->execute();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    if ($statusnote) {
        while ($row = ($result)->fetch(PDO::FETCH_ASSOC)) {
            if (affrw_renew_blocked($row)) {
                continue;
            }
            $data = "";
            if ($row != null)
                $data = " | {$row['note']}";
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . (affrw_list_label($row, $textbotlang) ?? $row['username']) . $data . "✨",
                    'callback_data' => "extend_" . $row['id_invoice']
                ],
            ];
        }
    } else {
        while ($row = ($result)->fetch(PDO::FETCH_ASSOC)) {
            if (affrw_renew_blocked($row)) {
                continue;
            }
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . (affrw_list_label($row, $textbotlang) ?? $row['username']) . "✨",
                    'callback_data' => "extend_" . $row['id_invoice']
                ],
            ];
        }
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_page_extends'
        ]
    ];
    $backuser = [
        [
            'text' => $textbotlang['users']['backbtn'],
            'callback_data' => 'backuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backuser;
    $keyboard_json = json_encode($keyboardlists);
    if ($datain == "backorder") {
        Editmessagetext($from_id, $message_id, $textbotlang['users']['extend']['selectOrderDirect'], $keyboard_json);
    } else {
        sendmessage($from_id, $textbotlang['users']['extend']['selectOrderDirect'], $keyboard_json, 'html');
    }
} elseif ($datain == 'next_page_extends') {
    $numpage = select("invoice", "id_user", "id_user", $from_id, "count");
    $page = $user['pagenumber'];
    $items_per_page = 20;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $next_page = 1;
    } else {
        $next_page = $page + 1;
    }
    $start_index = ($next_page - 1) * $items_per_page;
    $result = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :from_id AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') ORDER BY time_sell DESC LIMIT :start_index, :items_per_page");
    $result->bindParam(':from_id', $from_id, PDO::PARAM_STR);
    $result->bindParam(':start_index', $start_index, PDO::PARAM_INT);
    $result->bindParam(':items_per_page', $items_per_page, PDO::PARAM_INT);
    $result->execute();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    if ($statusnote) {
        while ($row = ($result)->fetch(PDO::FETCH_ASSOC)) {
            if (affrw_renew_blocked($row)) {
                continue;
            }
            $data = "";
            if ($row != null)
                $data = " | {$row['note']}";
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . (affrw_list_label($row, $textbotlang) ?? $row['username']) . $data . "✨",
                    'callback_data' => "extend_" . $row['id_invoice']
                ],
            ];
        }
    } else {
        while ($row = ($result)->fetch(PDO::FETCH_ASSOC)) {
            if (affrw_renew_blocked($row)) {
                continue;
            }
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . (affrw_list_label($row, $textbotlang) ?? $row['username']) . "✨",
                    'callback_data' => "extend_" . $row['id_invoice']
                ],
            ];
        }
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_page_extends'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_page_extends'
        ]
    ];
    $backuser = [
        [
            'text' => $textbotlang['users']['backbtn'],
            'callback_data' => 'backuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backuser;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $next_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['extend']['selectOrderDirect'], $keyboard_json);
} elseif ($datain == 'previous_page_extends') {
    $numpage = select("invoice", "id_user", "id_user", $from_id, "count");
    $page = $user['pagenumber'];
    $items_per_page = 20;
    $sum = $user['pagenumber'] * $items_per_page;
    if ($sum > $numpage) {
        $previous_page = 1;
    } else {
        $previous_page = $page - 1;
    }
    $start_index = ($previous_page - 1) * $items_per_page;
    $result = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :from_id AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') ORDER BY time_sell DESC LIMIT :start_index, :items_per_page");
    $result->bindParam(':from_id', $from_id, PDO::PARAM_STR);
    $result->bindParam(':start_index', $start_index, PDO::PARAM_INT);
    $result->bindParam(':items_per_page', $items_per_page, PDO::PARAM_INT);
    $result->execute();
    $keyboardlists = [
        'inline_keyboard' => [],
    ];
    if ($statusnote) {
        while ($row = ($result)->fetch(PDO::FETCH_ASSOC)) {
            if (affrw_renew_blocked($row)) {
                continue;
            }
            $data = "";
            if ($row != null)
                $data = " | {$row['note']}";
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . (affrw_list_label($row, $textbotlang) ?? $row['username']) . $data . "✨",
                    'callback_data' => "extend_" . $row['id_invoice']
                ],
            ];
        }
    } else {
        while ($row = ($result)->fetch(PDO::FETCH_ASSOC)) {
            if (affrw_renew_blocked($row)) {
                continue;
            }
            $keyboardlists['inline_keyboard'][] = [
                [
                    'text' => "✨" . (affrw_list_label($row, $textbotlang) ?? $row['username']) . "✨",
                    'callback_data' => "extend_" . $row['id_invoice']
                ],
            ];
        }
    }
    $pagination_buttons = [
        [
            'text' => $textbotlang['users']['page']['next'],
            'callback_data' => 'next_page_extends'
        ],
        [
            'text' => $textbotlang['users']['page']['previous'],
            'callback_data' => 'previous_page_extends'
        ]
    ];
    $backuser = [
        [
            'text' => $textbotlang['users']['backbtn'],
            'callback_data' => 'backuser'
        ]
    ];
    $keyboardlists['inline_keyboard'][] = $pagination_buttons;
    $keyboardlists['inline_keyboard'][] = $backuser;
    $keyboard_json = json_encode($keyboardlists);
    update("user", "pagenumber", $previous_page, "id", $from_id);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['extend']['selectOrderDirect'], $keyboard_json);
} elseif ($datain == "linkappdownlod") {
    $countapp = count(app_rows_for_lang($user['lang'] ?? 'fa'));
    if ($countapp == 0) {
        sendmessage($from_id, $textbotlang['users']['app']['appempty'], $json_list_helpـlink, "html");
        return;
    }
    sendmessage($from_id, $textbotlang['users']['app']['selectapp'], $json_list_helpـlink, "html");
} elseif (preg_match('/changenote_(\w+)/', $datain, $dataget)) {
    $id_invoice = $dataget[1];
    update("user", "Processing_value", $id_invoice, "id", $from_id);
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['status']['backinfo'], 'callback_data' => "product_" . $id_invoice],
            ]
        ]
    ]);
    Editmessagetext($from_id, $message_id, $textbotlang['users']['note']['sendNote'], $backinfoss);
    step("getnotedit", $from_id);
} elseif ($user['step'] == "getnotedit") {
    $invoice = select("invoice", "*", "id_invoice", $user['Processing_value'], "select");
    if (strlen($text) > 150) {
        sendmessage($from_id, $textbotlang['users']['note']['errorLongNote'], $keyboard, "html");
        return;
    }
    $text = sanitizeUserName($text);
    $id_invoice = $user['Processing_value'];
    $backinfoss = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['status']['backinfo'], 'callback_data' => "product_" . $id_invoice],
            ]
        ]
    ]);
    update("invoice", "note", $text, "id_invoice", $id_invoice);
    sendmessage($from_id, $textbotlang['users']['note']['changednote'], $backinfoss, "html");
    step("home", $from_id);
    $timejalali = jdate('Y/m/d H:i:s');
    $textreport = sprintf($textbotlang['Admin']['reportgroup']['noteChanged'], $invoice['username'], $invoice['note'], $text, $timejalali);
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherreport,
            'text' => $textreport,
            'reply_markup' => $Response,
            'parse_mode' => "HTML"
        ]);
    }
}
if (isset($update['pre_checkout_query'])) {
    $userid = $update['pre_checkout_query']['from']['id'];
    $id_order = $update['pre_checkout_query']['invoice_payload'];
    $Payment_report = select("Payment_report", "*", "id_order", $id_order, "select");
    if ($Payment_report == false) {
        return;
    } else {
        telegram('answerPreCheckoutQuery', [
            'pre_checkout_query_id' => $update['pre_checkout_query']['id'],
            'ok' => true,
        ]);
    }
    if ($Payment_report['payment_Status'] == "paid") {
        return;
    }
    update("Payment_report", "dec_not_confirmed", json_encode($update['pre_checkout_query']), "id_order", $Payment_report['id_order']);
}
if (isset($update['message']['successful_payment'])) {
    $id_order = $update['message']['successful_payment']['invoice_payload'];
    $Payment_report = select("Payment_report", "*", "id_order", $id_order, "select");
    if ($Payment_report == false) {
        return;
    }
    if ($Payment_report['payment_Status'] == "paid") {
        return;
    }
    update("Payment_report", "dec_not_confirmed", $Payment_report['dec_not_confirmed'] . json_encode($update['message']['successful_payment']), "id_order", $Payment_report['id_order']);
    DirectPayment($Payment_report['id_order']);
    $pricecashback = select("PaySetting", "ValuePay", "NamePay", "chashbackstar", "select")['ValuePay'];
    $Balance_id = select("user", "*", "id", $Payment_report['id_user'], "select");
    if ($pricecashback != "0") {
        $result = ($Payment_report['price'] * $pricecashback) / 100;
        // into the wallet the payment was made in
        wallet_credit($Balance_id['id'], $result, payment_currency($Payment_report));
        $text_report = sprintf($textbotlang['users']['Discount']['gift-deposit'], $result);
        sendmessage($Balance_id['id'], $text_report, null, 'HTML');
    }
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => sprintf($textbotlang['Admin']['reportgroup']['newPaymentStar'], $Balance_id['username'], $Balance_id['id'], $Payment_report['price'], $update['pre_checkout_query']['total_amount']),
            'parse_mode' => "HTML"
        ]);
    }
    update("Payment_report", "payment_Status", "paid", "id_order", $Payment_report['id_order']);
} elseif (preg_match('/extends_(\w+)_(.*)/', $datain, $dataget)) {
    $username = $dataget[1];
    // 🎁 a free config its tab does not let be renewed (an old button)
    if (affrw_renew_blocked(select("invoice", "*", "id_invoice", $dataget[2], "select"))) {
        bottext_extras_key_hint('users.affiliates.rewardNoRenew');
        sendmessage($from_id, $textbotlang['users']['affiliates']['rewardNoRenew'], null, 'HTML');
        return;
    }
    $location = select("marzban_panel", "*", "code_panel", $user['Processing_value_four'], "select");
    if ($location == false) {
        sendmessage($from_id, $textbotlang['users']['genericRestart2'], null, 'html');
        return;
    }
    $location = $location['name_panel'];
    update("user", "Processing_value", $location, "id", $from_id);
    $query = "SELECT * FROM product WHERE (Location = '$location' OR Location = '/all') AND agent= '{$user['agent']}' AND (FIND_IN_SET('{$user['lang']}', lang) OR lang = 'all' OR lang IS NULL OR lang = '')";
    $marzban_list_get = select("marzban_panel", "*", "code_panel", $location, "select");
    Editmessagetext($from_id, $message_id, $textbotlang['users']['extend']['selectservice'], KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], "serviceextendselects-", false, "backuser", $username));
} elseif (preg_match('/^serviceextendselects-(.*)-(.*)/', $datain, $dataget)) {
    deletemessage($from_id, $message_id);
    $codeproduct = $dataget[1];
    $username = $dataget[2];
    $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location = :processing_value OR Location = '/all') AND agent = :agent AND code_product = :code_product AND (FIND_IN_SET(:userlang, lang) OR lang = 'all' OR lang IS NULL OR lang = '')");
    $stmt->execute([
        ':processing_value' => $user['Processing_value'],
        ':agent' => $user['agent'],
        ':code_product' => $codeproduct,
        ':userlang' => $user['lang'] ?? 'fa',
    ]);
    $prodcut = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($prodcut == false) {
        sendmessage($from_id, $textbotlang['users']['erroroccurred'], $keyboard, 'html');
        return;
    }
    $keyboardextend = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['extend']['confirm'], 'callback_data' => "confirmserivces-" . $codeproduct . "-" . $username],
            ]
        ]
    ]);
    sendmessage($from_id, sprintf($textbotlang['users']['extend']['renewalinvoice'], $username, $prodcut['name_product'], $prodcut['price_product'], $prodcut['Service_time'], $prodcut['Volume_constraint'], $prodcut['note'], wallet_amount_text($user)), $keyboardextend, 'html');
} elseif (preg_match('/^confirmserivces-(.*)-(.*)/', $datain, $dataget)) {
    $codeproduct = $dataget[1];
    $usernamePanelExtends = $dataget[2];
    deletemessage($from_id, $message_id);
    $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location = :processing_value OR Location = '/all') AND agent = :agent AND code_product = :code_product AND (FIND_IN_SET(:userlang, lang) OR lang = 'all' OR lang IS NULL OR lang = '')");
    $stmt->execute([
        ':processing_value' => $user['Processing_value'],
        ':agent' => $user['agent'],
        ':code_product' => $codeproduct,
        ':userlang' => $user['lang'] ?? 'fa',
    ]);
    $prodcut = $stmt->fetch(PDO::FETCH_ASSOC);
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    $DataUserOut = $ManagePanel->DataUser($marzban_list_get['name_panel'], $usernamePanelExtends);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['extend']['renewalerror'], $keyboard, 'HTML');
        return;
    }
    if ($marzban_list_get == false) {
        sendmessage($from_id, $textbotlang['users']['extend']['renewalerror'], $keyboard, 'HTML');
        return;
    }
    if ($user['Balance'] < $prodcut['price_product'] && $user['agent'] != "n2" && !$admin_buy_free) {
        $marzbandirectpay = shop_feature_value('paydirect', $user['lang'] ?? 'fa', select('shopSetting', "*", "Namevalue", "statusdirectpabuy", "select")['value']);
        if ($marzbandirectpay == "offdirectbuy") {
            $minbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']]);
            $maxbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']]);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['status']['backinfo'], 'callback_data' => "account"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['Balance']['insufficientbalance'], $minbalance, $maxbalance), $bakinfos, 'HTML');
            step('getprice', $from_id);
            return;
        } else {
            if (intval($user['pricediscount']) != 0) {
                $result = ($prodcut['price_product'] * $user['pricediscount']) / 100;
                $prodcut['price_product'] = $prodcut['price_product'] - $result;
                sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
            }
            $Balance_prim = $prodcut['price_product'] - $user['Balance'];
            update("user", "Processing_value", $Balance_prim, "id", $from_id);
            sendmessage($from_id, $noCreditText, $step_payment, 'HTML');
            step('get_step_payment', $from_id);
            return;
        }
    }
    if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
        if (($user['Balance'] - $prodcut['price_product']) < intval("-" . $user['maxbuyagent'])) {
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }
    if (intval($user['pricediscount']) != 0) {
        $result = ($prodcut['price_product'] * $user['pricediscount']) / 100;
        $prodcut['price_product'] = $prodcut['price_product'] - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }
    if ($admin_buy_free) {
        $prodcut['price_product'] = 0;
    }
    $Balance_Low_user = $user['Balance'] - $prodcut['price_product'];
    balance_add($from_id, -$prodcut['price_product']);
    $extend = $ManagePanel->extend($marzban_list_get['Methodextend'], $prodcut['Volume_constraint'], $prodcut['Service_time'], $usernamePanelExtends, $prodcut['code_product'], $marzban_list_get['code_panel']);
    affrw_mark_renewed($extend, $usernamePanelExtends, $marzban_list_get['name_panel']);
    if ($extend['status'] == false) {
        $extend['msg'] = json_encode($extend['msg']);
        $textreports = sprintf($textbotlang['Admin']['reportgroup']['errorRenewService2'], $marzban_list_get['name_panel'], $usernamePanelExtends, $extend['msg']);
        sendmessage($from_id, $textbotlang['users']['extend']['errorSupport'], null, 'HTML');
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $textreports,
                'parse_mode' => "HTML"
            ]);
        }
        return;
    }
    $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username, value, type, time, price,output) VALUES (:id_user, :username, :value, :type, :time, :price,:output)");
    $value = json_encode(array(
        "volumebuy" => $prodcut['Volume_constraint'],
        "Service_time" => $prodcut['Service_time'],
        "oldvolume" => $DataUserOut['data_limit'],
        "oldtime" => $DataUserOut['expire'],
        'code_product' => $prodcut['code_product'],
    ));
    $dateacc = date('Y/m/d H:i:s');
    $type = "extends_not_user";
    $stmt->execute([
        ':id_user' => $from_id,
        ':username' => $usernamePanelExtends,
        ':value' => $value,
        ':type' => $type,
        ':time' => $dateacc,
        ':price' => $prodcut['price_product'],
        ':output' => json_encode($extend)
    ]);
    $prodcut['price_product'] = number_format($prodcut['price_product']);
    $balanceformatsell = money(select("user", "Balance", "id", $from_id, "select")['Balance'], currency_for_user($user), false);
    $textextend = sprintf($textbotlang['users']['extend']['success2'], $usernamePanelExtends, $prodcut['name_product'], $prodcut['price_product']);
    sendmessage($from_id, $textextend, $keyboard, 'HTML');
    $timejalali = jdate('Y/m/d H:i:s');
    $text_report = sprintf($textbotlang['Admin']['reportgroup']['renewalDetails'], $from_id, $username, $usernamePanelExtends, $first_name, $marzban_list_get['name_panel'], $prodcut['name_product'], $prodcut['Volume_constraint'], $prodcut['Service_time'], $prodcut['price_product'], $balanceformatsell, $timejalali);
    if (strlen($setting['Channel_Report']) > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
} elseif ($datain == "change_language") {
    list($lsw_caption_ch, $lsw_kb_ch) = language_picker_payload();
    Editmessagetext($from_id, $message_id, $lsw_caption_ch, $lsw_kb_ch);

} elseif (preg_match('/^setlang:(fa|en)$/', $datain, $dataget)) {
    $lang = $dataget[1];
    // 👥 someone who joined today and still has what their language gave a
    // new user: what the language they pick gives one (🆕 کاربرهای جدید).
    // Anyone older, or changed by an admin since, keeps their own.
    $sl_prevLang = in_array($user['lang'] ?? '', panel_langs(), true) ? $user['lang'] : 'fa';
    $sl_cardFollow = (time() - (int) ($user['register'] ?? 0)) < 86400 && (string) ($user['cardpayment'] ?? '') === card_new_default($sl_prevLang);
    // the wallet follows the language: tomans stay with Persian, dollars
    // with English, each waiting for the user to come back
    $ws_conv = wallet_switch_lang($from_id, $lang);
    if ($sl_cardFollow && $lang !== $sl_prevLang) {
        update("user", "cardpayment", card_new_default($lang), "id", $from_id);
    }
    clearSelectCache();
    // $users was read at the top of keyboard.php, before this switch happened,
    // so it still carries the OLD language - and build_main_keyboard() below
    // resolves the menu from exactly that row. Left stale, the first tap built
    // the menu in the language being left behind, and the switch appeared to
    // need tapping twice.
    $users['lang'] = $lang;
    $user['lang'] = $lang;
    // drop the picker and restart the bot so every button label picks up the new language
    deletemessage($from_id, $message_id);
    // re-read after the switch, so the welcome text below is already in the
    // language just chosen - the panel's own vocabulary stays Persian either
    // way, see ui_texts()
    $textbotlang = ui_texts();
    $keyboard = build_main_keyboard();
    sendmessage($from_id, strtr($textbotlang['users']['text_start'], bottext_user_placeholders($user, $from_id)), $keyboard, 'html');
    // 💱 their balance followed them into the new currency - say how much
    if (is_array($ws_conv)) {
        $ws_from = [];
        foreach ($ws_conv['from'] as $ws_c => $ws_a) {
            $ws_from[] = money($ws_a, $ws_c);
        }
        bottext_extras_key_hint('users.Balance.walletConverted');
        sendmessage($from_id, strtr($textbotlang['users']['Balance']['walletConverted'], [
            '{from}' => implode(' + ', $ws_from),
            '{balance}' => money($ws_conv['balance'], $ws_conv['to']),
        ]), null, 'HTML');
    }
    step('home', $from_id);
    return;
}
if (in_array($from_id, $admin_ids))
    require_once 'admin.php';
// admin.php answers in Persian and shares this scope, so put the reader's own
// language back before the shop's reply below.
$textbotlang = ui_texts();

//----------------[  unknown message reply  ]----------------
// if nothing in this update produced a bot response and the user sent plain text,
// reply with the customizable "unknown message" (text/sticker/reaction from 📝 manager)
//
// Never to someone holding the panel. This answer is written for a customer who
// tapped nothing the shop menu offers; an admin typing into the panel - a value
// for a setting, a name, an amount - would get it as noise on every keystroke
// the panel did not recognise. Same test that decides whether admin.php runs at
// all, so "has a panel" means one thing in both places.
//
// Consequence worth knowing: an admin account can no longer see this reply, so
// testing it needs an account that is not an admin.
if (empty($GLOBALS['bt_any_reply']) && !empty($text) && $datain == '' && !in_array($from_id, $admin_ids)) {
    // On the custom-amount step this is not an unknown message - it is an
    // amount that is not a number. Getting this far is what proves it was not
    // a menu button or a command: those all have their own branches, some of
    // them below the amount step, so a guard up there would have to enumerate
    // them and would rot the moment one was added.
    if (preg_match('/^topup_custom:([a-z0-9]+)$/', (string) ($user['step'] ?? ''), $unk_tp)) {
        $GLOBALS['topup_amount_origin_step'] = "topup_custom:{$unk_tp[1]}";
        topup_notnumber_notice($from_id, $user['lang'] ?? 'fa', $unk_tp[1], $textbotlang, (int) ($update['message']['message_id'] ?? 0));
        return;
    }
    // off until this language is switched on, from the item's own screen
    $unk_lang = (is_array($user) && !empty($user['lang'])) ? $user['lang'] : 'fa';
    if (!bt_item_enabled('users.unknownMsg', $unk_lang)) {
        return;
    }
    // Text only. The sticker and the reaction ride along inside sendmessage()
    // (bottext_send_extras), which matches the outgoing text to its key and
    // sends them in the recipient's own language. Sending them here as well -
    // which an earlier version of this block did, back when no text shipped and
    // sendmessage() was never reached - posts the sticker twice.
    $unk_text = bottext_resolve_key('users.unknownMsg');
    if ($unk_text !== '') {
        sendmessage($from_id, $unk_text, null, 'HTML');
    }
}

$pdo = null;