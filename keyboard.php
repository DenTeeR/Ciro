<?php
require_once 'config.php';
$setting = select("setting", "*", null, null, "select");
// This file builds both surfaces, so it needs both vocabularies.
//
// $textbotlang is the PANEL's: Persian for every reader, the same copy
// admin.php answers from, because a panel button built here is matched there
// by its text. Nearly everything below is panel chrome, which is why it gets
// the plain name.
//
// $customer_texts is the SHOP's: the reader's own language, the same copy
// index.php answers from. The handful of keyboards a customer actually sees
// are built from it and are marked as such where they are defined. Getting one
// of those two wrong is what kills a button - it gets printed in one language
// and compared in the other.
$textbotlang = panel_texts();
$customer_texts = ui_texts();
if (!function_exists('getPaySettingValue')) {
    function getPaySettingValue($name)
    {
        $result = select("PaySetting", "ValuePay", "NamePay", $name, "select");
        return $result['ValuePay'] ?? null;
    }
}
//-----------------------------[  text panel  ]-------------------------------
$adminrulecheck = select("admin", "*", "id_admin", $from_id, "select");
if (!$adminrulecheck) {
    $adminrulecheck = array(
        'rule' => '',
    );
}
$users = select("user", "*", "id", $from_id, "select");
if ($users == false) {
    $users = array();
    $users = array(
        'step' => '',
        'agent' => '',
        'limit_usertest' => '',
        'Processing_value' => '',
        'Processing_value_four' => '',
        'cardpayment' => ""
    );
}
// SHOP surface: these are the customer's own main-menu button names, and
// index.php matches a tap against the same strings.
$replacements = [
    'text_usertest' => $customer_texts['textbot']['userTest'],
    'text_Purchased_services' => $customer_texts['textbot']['purchasedServices'],
    'text_support' => $customer_texts['textbot']['support'],
    'text_help' => $customer_texts['textbot']['help'],
    'accountwallet' => $customer_texts['textbot']['accountWallet'],
    'addbalance' => $customer_texts['textbot']['addBalance'],
    'text_sell' => $customer_texts['textbot']['sell'],
    'text_Tariff_list' => $customer_texts['textbot']['tariffList'],
    'text_affiliates' => $customer_texts['textbot']['affiliates'],
    'text_wheel_luck' => $customer_texts['textbot']['wheelLuck'],
    'text_extend' => $customer_texts['textbot']['extend'],
    'text_change_language' => $customer_texts['language']['changeButton']
];
$admin_idss = select("admin", "*", "id_admin", $from_id, "count");
$temp_addtional_key = [];
//----------------[  helper: strip leading emoji  ]----------------
if (!function_exists('strip_leading_emoji')) {
    function strip_leading_emoji($s)
    {
        return trim(preg_replace('/^[\x{203C}\x{2049}\x{2139}\x{2194}-\x{2199}\x{21A9}-\x{21AA}\x{231A}-\x{231B}\x{23E9}-\x{23EC}\x{23F0}\x{23F3}\x{24C2}\x{25AA}-\x{25AB}\x{25B6}\x{25C0}\x{25FB}-\x{25FE}\x{2600}-\x{27BF}\x{2934}-\x{2935}\x{2B05}-\x{2B07}\x{2B1B}-\x{2B1C}\x{2B50}\x{2B55}\x{3030}\x{303D}\x{3297}\x{3299}\x{FE0E}\x{FE0F}\x{200D}\x{2764}\x{20E3}\x{1F000}-\x{1FAFF}\x{1F1E6}-\x{1F1FF}\s]*/u', '', (string) $s));
    }
}
//----------------[  helper: split leading emoji from text  ]----------------
if (!function_exists('split_leading_emoji')) {
    function split_leading_emoji($s)
    {
        $s = (string) $s;
        $rest = strip_leading_emoji($s);
        if ($rest === '' || $rest === $s) {
            return ['', $s];
        }
        $emoji = trim(mb_substr($s, 0, mb_strlen($s) - mb_strlen($rest)));
        return [$emoji, $rest];
    }
}
// The reader's own menu, not Persian's: colour, emoji, premium emoji, rename,
// hidden flag and the grid itself are stored per language now. simple_emoji is
// the "hide default emojis" switch, emoji_pos_global puts the emoji before or
// after the label - both belong to one menu, so both travel with it.
$mm_render = mainmenu_layout_render($users['lang'] ?? 'fa');
$keyboardRows = $mm_render['rows'];
$simple_emoji_mode = $mm_render['simple'];
$global_emoji_pos = $mm_render['pos'];

if (!function_exists('build_main_keyboard')) {
    // builds the user's main menu keyboard; call it again after a language switch
    // so the button labels follow the newly selected language
    function build_main_keyboard()
    {
        global $setting, $users, $admin_idss;
        // The customer's own menu, in the customer's own language - always.
        //
        // Both the words AND the grid are resolved from the row rather than
        // taken from the global copies, so that a language just switched in
        // this same request is already in effect: the caller rebuilds the menu
        // straight after the switch, and the globals were read before it. The
        // grid matters as much as the words now that each language owns its
        // own buttons, colours and emoji.
        $mm_lang = $users['lang'] ?? 'fa';
        $textbotlang = lang_tab_texts($mm_lang);
        $mm_render = mainmenu_layout_render($mm_lang);
        $keyboardRows = $mm_render['rows'];
        $simple_emoji_mode = $mm_render['simple'];
        $global_emoji_pos = $mm_render['pos'];
        $temp_addtional_key = [];
        $replacements = [
            'text_usertest' => $textbotlang['textbot']['userTest'],
            'text_Purchased_services' => $textbotlang['textbot']['purchasedServices'],
            'text_support' => $textbotlang['textbot']['support'],
            'text_help' => $textbotlang['textbot']['help'],
            'accountwallet' => $textbotlang['textbot']['accountWallet'],
            'addbalance' => $textbotlang['textbot']['addBalance'],
            'text_sell' => $textbotlang['textbot']['sell'],
            'text_Tariff_list' => $textbotlang['textbot']['tariffList'],
            'text_affiliates' => $textbotlang['textbot']['affiliates'],
            'text_wheel_luck' => $textbotlang['textbot']['wheelLuck'],
            'text_extend' => $textbotlang['textbot']['extend'],
            'text_change_language' => $textbotlang['language']['changeButton']
        ];
        if (glass_on($mm_lang, $setting) && !empty($keyboardRows)) {
            $trace_keyboard = $keyboardRows;
            foreach ($trace_keyboard as $key => $callback_set) {
                foreach ($callback_set as $keyboard_key => $keyboard) {
                    if ($keyboard['text'] == "text_sell") {
                        $trace_keyboard[$key][$keyboard_key]['callback_data'] = "buy";
                    }
                    if ($keyboard['text'] == "accountwallet") {
                        $trace_keyboard[$key][$keyboard_key]['callback_data'] = "account";
                    }
                    if ($keyboard['text'] == "addbalance") {
                        $trace_keyboard[$key][$keyboard_key]['callback_data'] = "Add_Balance";
                    }
                    if ($keyboard['text'] == "text_Tariff_list") {
                        $trace_keyboard[$key][$keyboard_key]['callback_data'] = "Tariff_list";
                    }
                    if ($keyboard['text'] == "text_wheel_luck") {
                        $trace_keyboard[$key][$keyboard_key]['callback_data'] = "wheel_luck";
                    }
                    if ($keyboard['text'] == "text_affiliates") {
                        $trace_keyboard[$key][$keyboard_key]['callback_data'] = "affiliatesbtn";
                    }
                    if ($keyboard['text'] == "text_extend") {
                        $trace_keyboard[$key][$keyboard_key]['callback_data'] = "extendbtn";
                    }
                    if ($keyboard['text'] == "text_support") {
                        $trace_keyboard[$key][$keyboard_key]['callback_data'] = "supportbtns";
                    }
                    if ($keyboard['text'] == "text_Purchased_services") {
                        $trace_keyboard[$key][$keyboard_key]['callback_data'] = "backorder";
                    }
                    if ($keyboard['text'] == "text_help") {
                        $trace_keyboard[$key][$keyboard_key]['callback_data'] = "helpbtns";
                    }
                    if ($keyboard['text'] == "text_usertest") {
                        $trace_keyboard[$key][$keyboard_key]['callback_data'] = "usertestbtn";
                    }
                    if ($keyboard['text'] == "text_change_language") {
                        $trace_keyboard[$key][$keyboard_key]['callback_data'] = "change_language";
                    }
                }
            }
            if ($admin_idss != 0) {
                // Persian in every language, on purpose: the panel is Persian,
                // and admin.php recognises this button by its text
                $temp_addtional_key[] = ['text' => lang_tab_texts('fa')['Admin']['panelAdmin'], 'callback_data' => "admin"];
            }
            // agents only - a newcomer not in the database yet has no agent
            // type at all, and "not f" used to show it to them
            if (in_array($users['agent'] ?? '', ['n', 'n2'], true)) {
                $temp_addtional_key[] = ['text' => $textbotlang['textbot']['agentPanel'], 'callback_data' => "agentpanel"];
            }
            if ($users['agent'] == "f" && feature_value('statusagentrequest', $users['lang'] ?? 'fa', $setting['statusagentrequest']) == "onrequestagent") {
                $temp_addtional_key[] = ['text' => $textbotlang['textbot']['requestAgent'], 'callback_data' => "requestagent"];
            }
            $keyboard = ['inline_keyboard' => []];
            $keyboardcustom = $trace_keyboard;
            // the education section's own switch (📚 مدیریت آموزش‌ها ← 🔌 وضعیت
            // بخش آموزش). Reuses the hidden flag the renderer already honours,
            // so there is one code path deciding what reaches the customer.
            if (function_exists('help_section_on') && !help_section_on()) {
                foreach ($keyboardcustom as $kb_hrow_i => $kb_hrow) {
                    if (!is_array($kb_hrow)) {
                        continue;
                    }
                    foreach ($kb_hrow as $kb_hbtn_i => $kb_hbtn) {
                        if (is_array($kb_hbtn) && ($kb_hbtn['text'] ?? '') === 'text_help') {
                            $keyboardcustom[$kb_hrow_i][$kb_hbtn_i]['hidden'] = 1;
                        }
                    }
                }
            }
            $keyboardcustom = json_decode(strtr(strval(json_encode($keyboardcustom)), $replacements), true);
            $keyboardfiltered = [];
            foreach ($keyboardcustom as $kb_r => $kb_row) {
                if (!is_array($kb_row)) {
                    continue;
                }
                $kb_newrow = [];
                foreach ($kb_row as $kb_c => $kb_btn) {
                    if (!is_array($kb_btn)) {
                        continue;
                    }
                    if (!empty($kb_btn['hidden'])) {
                        continue;
                    }
                    if (isset($kb_btn['custom_text']) && $kb_btn['custom_text'] !== '') {
                        $kb_btn['text'] = $kb_btn['custom_text'];
                    }
                    if ($simple_emoji_mode && empty($kb_btn['icon_emoji']) && empty($kb_btn['emoji'])) {
                        $kb_btn['text'] = strip_leading_emoji($kb_btn['text']);
                    }
                    if (isset($kb_btn['icon_emoji']) && $kb_btn['icon_emoji'] !== '') {
                        $kb_btn['icon_custom_emoji_id'] = $kb_btn['icon_emoji'];
                        $kb_btn['text'] = strip_leading_emoji($kb_btn['text']);
                    } elseif (isset($kb_btn['emoji']) && $kb_btn['emoji'] !== '') {
                        if ($global_emoji_pos === 'left') {
                            $kb_btn['text'] = strip_leading_emoji($kb_btn['text']) . ' ' . $kb_btn['emoji'];
                        } else {
                            $kb_btn['text'] = $kb_btn['emoji'] . ' ' . strip_leading_emoji($kb_btn['text']);
                        }
                    } else {
                        // no custom emoji: reposition the default text emoji per global setting
                        list($kb_def_emoji, $kb_def_rest) = split_leading_emoji($kb_btn['text']);
                        if ($kb_def_emoji !== '') {
                            $kb_btn['text'] = ($global_emoji_pos === 'left') ? $kb_def_rest . ' ' . $kb_def_emoji : $kb_def_emoji . ' ' . $kb_def_rest;
                        }
                    }
                    unset($kb_btn['style_reply'], $kb_btn['emoji'], $kb_btn['sticker'], $kb_btn['icon_emoji'], $kb_btn['hidden'], $kb_btn['custom_text'], $kb_btn['emoji_pos']);
                    $kb_newrow[] = $kb_btn;
                }
                if (!empty($kb_newrow)) {
                    $keyboardfiltered[] = $kb_newrow;
                }
            }
            $keyboardfiltered[] = $temp_addtional_key;
            $keyboard['inline_keyboard'] = $keyboardfiltered;
            $keyboard = json_encode($keyboard);
        } else {
            if ($admin_idss != 0) {
                // same reason as the inline variant above
                $temp_addtional_key[] = ['text' => lang_tab_texts('fa')['Admin']['panelAdmin']];
            }
            if (in_array($users['agent'] ?? '', ['n', 'n2'], true)) {
                $temp_addtional_key[] = ['text' => $textbotlang['textbot']['agentPanel']];
            }
            if ($users['agent'] == "f" && feature_value('statusagentrequest', $users['lang'] ?? 'fa', $setting['statusagentrequest']) == "onrequestagent") {
                $temp_addtional_key[] = ['text' => $textbotlang['textbot']['requestAgent']];
            }
            $keyboard = ['keyboard' => [], 'resize_keyboard' => true];
            $keyboardcustom = $keyboardRows;
            // same education switch as the reply keyboard above
            if (function_exists('help_section_on') && !help_section_on()) {
                foreach ($keyboardcustom as $kb_hrow_i => $kb_hrow) {
                    if (!is_array($kb_hrow)) {
                        continue;
                    }
                    foreach ($kb_hrow as $kb_hbtn_i => $kb_hbtn) {
                        if (is_array($kb_hbtn) && ($kb_hbtn['text'] ?? '') === 'text_help') {
                            $keyboardcustom[$kb_hrow_i][$kb_hbtn_i]['hidden'] = 1;
                        }
                    }
                }
            }
            $keyboardcustom = json_decode(strtr(strval(json_encode($keyboardcustom)), $replacements), true);
            $keyboardfiltered = [];
            foreach ($keyboardcustom as $kb_r => $kb_row) {
                if (!is_array($kb_row)) {
                    continue;
                }
                $kb_newrow = [];
                foreach ($kb_row as $kb_c => $kb_btn) {
                    if (!is_array($kb_btn)) {
                        continue;
                    }
                    if (!empty($kb_btn['hidden'])) {
                        continue;
                    }
                    if (isset($kb_btn['style_reply'])) {
                        $kb_btn['style'] = $kb_btn['style_reply'];
                    } else {
                        unset($kb_btn['style']);
                    }
                    if (isset($kb_btn['custom_text']) && $kb_btn['custom_text'] !== '') {
                        $kb_btn['text'] = $kb_btn['custom_text'];
                    }
                    if ($simple_emoji_mode && empty($kb_btn['icon_emoji']) && empty($kb_btn['emoji'])) {
                        $kb_btn['text'] = strip_leading_emoji($kb_btn['text']);
                    }
                    if (isset($kb_btn['icon_emoji']) && $kb_btn['icon_emoji'] !== '') {
                        $kb_btn['icon_custom_emoji_id'] = $kb_btn['icon_emoji'];
                        $kb_btn['text'] = strip_leading_emoji($kb_btn['text']);
                    } elseif (isset($kb_btn['emoji']) && $kb_btn['emoji'] !== '') {
                        if ($global_emoji_pos === 'left') {
                            $kb_btn['text'] = strip_leading_emoji($kb_btn['text']) . ' ' . $kb_btn['emoji'];
                        } else {
                            $kb_btn['text'] = $kb_btn['emoji'] . ' ' . strip_leading_emoji($kb_btn['text']);
                        }
                    } else {
                        // no custom emoji: reposition the default text emoji per global setting
                        list($kb_def_emoji, $kb_def_rest) = split_leading_emoji($kb_btn['text']);
                        if ($kb_def_emoji !== '') {
                            $kb_btn['text'] = ($global_emoji_pos === 'left') ? $kb_def_rest . ' ' . $kb_def_emoji : $kb_def_emoji . ' ' . $kb_def_rest;
                        }
                    }
                    unset($kb_btn['style_reply'], $kb_btn['emoji'], $kb_btn['sticker'], $kb_btn['icon_emoji'], $kb_btn['hidden'], $kb_btn['custom_text'], $kb_btn['emoji_pos']);
                    $kb_newrow[] = $kb_btn;
                }
                if (!empty($kb_newrow)) {
                    $keyboardfiltered[] = $kb_newrow;
                }
            }
            $keyboardfiltered[] = $temp_addtional_key;
            $keyboard['keyboard'] = $keyboardfiltered;
            $keyboard = json_encode($keyboard);
        }
        return $keyboard;
    }
}
$keyboard = build_main_keyboard();

// SHOP surface: the customer's own account screen.
$kp_accountRows = [];
// 🚫 مخفی کردن این دکمه (🎨 شخصی‌سازی) drops the whole row - a row holding
// nothing is not something Telegram accepts
if (!bt_button_hidden($users['lang'] ?? 'fa', 'bottext.btnTopupAccount')) {
    // its own callback, so the top-up screen knows it came from this page; unstyled
    // unless the shop gives it a colour (🎨 → 👤 → 💰)
    $kp_accountRows[] = [bt_button($users['lang'] ?? 'fa', 'bottext.btnTopupAccount', $customer_texts['textbot']['addBalance'], 'Add_Balance_ac', '')];
}
// 💸 انتقال موجودی (wtransfer.php), where this language has it on
if (function_exists('wt_account_row') && is_array($users) && ($kp_wt = wt_account_row($users)) !== null) {
    $kp_accountRows[] = $kp_wt;
}
if (!bt_button_hidden($users['lang'] ?? 'fa', 'bottext.btnCloseAccount')) {
    $kp_accountRows[] = [bt_button($users['lang'] ?? 'fa', 'bottext.btnCloseAccount', $customer_texts['bottext']['btnCloseAccount'] ?? $customer_texts['bottext']['btn_close'], 'mmclose:ac')];
}
// both buttons hidden: the screen goes out with no keyboard at all
$keyboardPanel = $kp_accountRows ? json_encode([
    'inline_keyboard' => $kp_accountRows,
    'resize_keyboard' => true
]) : null;
if ($adminrulecheck['rule'] == "administrator") {
    $keyboardadmin = json_encode([
        'keyboard' => [
            [['text' => $textbotlang['Admin']['Status']['btn']]],
            [['text' => $textbotlang['Admin']['btnKeyboard']['managementPanel']], ['text' => $textbotlang['Admin']['btnKeyboard']['addPanel']]],
            [['text' => $textbotlang['Admin']['btnKeyboard']['manageUser']], ['text' => $textbotlang['keyboard']['shopSettings']]],
            // 📚 بخش آموزش moved into 🎨 شخصی‌سازی پیام‌های ربات ← 📚 پیام و دکمه‌های
            // آموزش, so it is no longer a second entry point of its own here
            [['text' => $textbotlang['keyboard']['supportSection']]],
            [['text' => $textbotlang['keyboard']['botReport']], ['text' => $textbotlang['keyboard']['panelFeatures']]],
            [['text' => $textbotlang['keyboard']['generalSettings']], ['text' => $textbotlang['keyboard']['pendingReceipts']]],
            [['text' => $textbotlang['bottext']['open_button']]],
            [['text' => $textbotlang['users']['backbtn']]]
        ],
        'resize_keyboard' => true
    ]);
}
if ($adminrulecheck['rule'] == "Seller") {
    $keyboardadmin = json_encode([
        'keyboard' => [
            [['text' => $textbotlang['Admin']['Status']['btn']]],
            [['text' => $textbotlang['keyboard']['manageUser']]],
            [['text' => $textbotlang['users']['backbtn']]]
        ],
        'resize_keyboard' => true
    ]);
}
if ($adminrulecheck['rule'] == "support") {
    $keyboardadmin = json_encode([
        'keyboard' => [
            [['text' => $textbotlang['keyboard']['manageUser']]],
            [['text' => $textbotlang['users']['backbtn']]]
        ],
        'resize_keyboard' => true
    ]);
}
$CartManage = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['setCardNumber']], ['text' => $textbotlang['keyboard']['deleteCardNumber']]],
        [['text' => $textbotlang['keyboard']['offlineGatewayPv']]],
        [['text' => $textbotlang['keyboard']['cardAccess']]],
        [['text' => $textbotlang['keyboard']['showCartAfterFirstPay']]],
        [['text' => $textbotlang['keyboard']['setEducationCartToCart']]],
        [['text' => $textbotlang['keyboard']['autoConfirmNoCheck']]],
        [['text' => $textbotlang['keyboard']['excludeUserAutoConfirm']]],
        [['text' => $textbotlang['keyboard']['autoConfirmNoCheckTime']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$keyboardzarinpal = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['setEducationZarinPal']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$keyboardfrenzyex = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['frenzyExApiKey']], ['text' => $textbotlang['keyboard']['frenzyExCallbackSecret']]],
        [['text' => $textbotlang['keyboard']['setEducationFrenzyEx']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$aqayepardakht = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['setEducationAqayePardakht']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$NowPaymentsManage = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['setEducationPlisio']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$setting_panel = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['featureStatus']], ['text' => $textbotlang['keyboard']['featureStatusLang']]],
        [['text' => $textbotlang['keyboard']['botReports']], ['text' => $textbotlang['keyboard']['channelSettings']]],
        [['text' => $textbotlang['keyboard']['activateWebPanel']]],
        [['text' => $textbotlang['keyboard']['miniAppSettingsBtn']]],
        [['text' => $textbotlang['keyboard']['optimizeBot']]],
        [['text' => $textbotlang['keyboard']['adminSection']]],
        [['text' => $textbotlang['keyboard']['setTestAccountLimitAll']]],
        [['text' => $textbotlang['keyboard']['backupSettingsBtn']], ['text' => $textbotlang['keyboard']['phpMyAdminBtn']]],
        [['text' => $textbotlang['keyboard']['agentMembershipFee']], ['text' => $textbotlang['keyboard']['qrBackground']]],
        [['text' => $textbotlang['keyboard']['reWebhookAgentBots']]],
        [['text' => $textbotlang['keyboard']['cronStatus']], ['text' => $textbotlang['keyboard']['updateBotBtn']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
//----------------[  color manage keyboard  ]----------------
$keyboard_colormanage = json_encode([
    'keyboard' => [
        [['text' => '🎛 رنگ دکمه‌های شیشه‌ای']],
        [['text' => '⌨️ رنگ دکمه‌های کیبوردی']],
        [['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
//----------------[  emoji & sticker keyboard  ]----------------
$keyboard_emojisticker = json_encode([
    'keyboard' => [
        [['text' => '😀 ایموجی دکمه‌ها']],
        [['text' => '✨ استیکر پریمیوم دکمه‌ها']],
        [['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
//----------------[  button settings keyboard  ]----------------
$keyboard_btnsettings = json_encode([
    'keyboard' => [
        [['text' => '🎨 رنگ‌بندی دکمه‌ها']],
        [['text' => '🎭 ایموجی و استیکر دکمه‌ها']],
        [['text' => '📐 چیدمان دکمه‌ها']],
        [['text' => '✏️ نام و نمایش دکمه‌ها']],
        [['text' => '🌐 تنظیمات تغییر زبان']],
        [['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$PaySettingcard = getPaySettingValue("Cartstatus");
$PaySettingnow = getPaySettingValue("nowpaymentstatus");
$PaySettingaqayepardakht = getPaySettingValue("statusaqayepardakht");
$PaySettingpv = pay_value("Cartstatuspv", $users['lang'] ?? null, 'offcardpv');
$usernamecart = getPaySettingValue("CartDirect");
$Swapino = getPaySettingValue("statusSwapWallet");
$paymentverify = pay_value("checkpaycartfirst", $users['lang'] ?? null, 'offpayverify');
$stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_user = :user_id AND payment_Status = 'paid' ");
$stmt->bindValue(':user_id', $from_id);
$stmt->execute();
$paymentexits = $stmt->rowCount();
$zarinpal = getPaySettingValue("zarinpalstatus");
$frenzyex = getPaySettingValue("frenzyexstatus");
$affilnecurrency = getPaySettingValue("digistatus");
$arzireyali3 = getPaySettingValue("statusiranpay3");
$paymentstatussnotverify = getPaySettingValue("paymentstatussnotverify");
$paymentsstartelegram = getPaySettingValue("statusstar");
$payment_status_nowpayment = getPaySettingValue("statusnowpayment");
$step_payment = [
    'inline_keyboard' => []
];
// the customer's own switch, and with 🛡 «فقط برای مشتری‌های قدیمی» on, more
// than N paid purchases/renewals (card_visible_for())
if ($PaySettingcard == "oncard" && card_visible_for($users, $users['lang'] ?? 'fa')) {
    if ($PaySettingpv == "oncardpv") {
        $step_payment['inline_keyboard'][] = [
            ['text' => $customer_texts['textbot']['cartToCart'], 'url' => "https://t.me/$usernamecart"],
        ];
    } else {
        $step_payment['inline_keyboard'][] = [
            ['text' => $customer_texts['textbot']['cartToCart'], 'callback_data' => "cart_to_offline"],
        ];
    }
}
if (($paymentexits == 0 && $paymentverify == "onpayverify"))
    unset($step_payment['inline_keyboard']);
if ($PaySettingnow == "onnowpayment") {
    $step_payment['inline_keyboard'][] = [
        ['text' => $customer_texts['textbot']['nowPayment'], 'callback_data' => "plisio"]
    ];
}
if ($payment_status_nowpayment == "1") {
    $step_payment['inline_keyboard'][] = [
        ['text' => $customer_texts['textbot']['cryptoPayment'], 'callback_data' => "nowpayment"]
    ];
}
if ($affilnecurrency == "ondigi") {
    $step_payment['inline_keyboard'][] = [
        ['text' => $customer_texts['textbot']['nowPaymentTron'], 'callback_data' => "digitaltron"]
    ];
}
if ($Swapino == "onSwapinoBot") {
    $step_payment['inline_keyboard'][] = [
        ['text' => $customer_texts['textbot']['iranPay2'], 'callback_data' => "iranpay1"]
    ];
}
if ($arzireyali3 == "oniranpay3" && $paymentexits >= 2) {
    $step_payment['inline_keyboard'][] = [
        ['text' => $customer_texts['textbot']['iranPay1'], 'callback_data' => "iranpay3"]
    ];
}
if ($PaySettingaqayepardakht == "onaqayepardakht") {
    $step_payment['inline_keyboard'][] = [
        ['text' => $customer_texts['textbot']['aqayePardakht'], 'callback_data' => "aqayepardakht"]
    ];
}
if ($zarinpal == "onzarinpal") {
    $step_payment['inline_keyboard'][] = [
        ['text' => $customer_texts['textbot']['zarinPal'], 'callback_data' => "zarinpal"]
    ];
}
if ($frenzyex == "onfrenzyex") {
    $step_payment['inline_keyboard'][] = [
        ['text' => $customer_texts['textbot']['frenzyEx'], 'callback_data' => "frenzyex"]
    ];
}
if (getPaySettingValue("statustronado") == "ontronado") {
    $step_payment['inline_keyboard'][] = [
        ['text' => $customer_texts['textbot']['tronadoPay'], 'callback_data' => "tronado"]
    ];
}
if (getPaySettingValue("statushooshpay") == "onhooshpay") {
    $step_payment['inline_keyboard'][] = [
        ['text' => $customer_texts['textbot']['hooshPay'], 'callback_data' => "hooshpay"]
    ];
}
if (getPaySettingValue("statuscubepay") == "oncubepay") {
    $step_payment['inline_keyboard'][] = [
        ['text' => $customer_texts['textbot']['cubePay'], 'callback_data' => "cubepay"]
    ];
}
if (getPaySettingValue("statusabangateway") == "onabangateway") {
    $step_payment['inline_keyboard'][] = [
        ['text' => $customer_texts['textbot']['abanGateway'], 'callback_data' => "abangateway"]
    ];
}
if (getPaySettingValue("statusvariza") == "onvariza") {
    $step_payment['inline_keyboard'][] = [
        ['text' => $customer_texts['textbot']['varizaPay'], 'callback_data' => "variza"]
    ];
}
if ($paymentstatussnotverify == "onverifypay") {
    $step_payment['inline_keyboard'][] = [
        ['text' => $customer_texts['textbot']['paymentNotVerify'], 'callback_data' => "paymentnotverify"]
    ];
}
if (intval($paymentsstartelegram) == 1) {
    $step_payment['inline_keyboard'][] = [
        ['text' => $customer_texts['textbot']['starTelegram'], 'callback_data' => "startelegrams"]
    ];
}
if (intval(getPaySettingValue('statuston')) == 1) {
    $step_payment['inline_keyboard'][] = [
        ['text' => $customer_texts['textbot']['tonPayment'], 'callback_data' => "ton"]
    ];
}
if (intval(getPaySettingValue('statustrx')) == 1) {
    $step_payment['inline_keyboard'][] = [
        ['text' => $customer_texts['textbot']['trxPayment'], 'callback_data' => "trx"]
    ];
}
if (intval(getPaySettingValue('statususdtbep')) == 1) {
    $step_payment['inline_keyboard'][] = [
        ['text' => $customer_texts['textbot']['usdtbepPayment'], 'callback_data' => "usdtbep"]
    ];
}
// keep only the gateways this user's language is allowed to see (set in
// 💎 Financial -> gateways per language); an unrestricted language keeps all
$step_payment['inline_keyboard'] = gateway_filter_rows(
    $step_payment['inline_keyboard'] ?? [],
    $users['lang'] ?? 'fa',
    $customer_texts['textbot']['cartToCart'] ?? null
);
// per-language button styling (order/width/emoji/color/rename), set via
// 🎨 شخصی‌سازی نمایش دکمه‌ها -> 💳 روش‌های پرداخت
$step_payment['inline_keyboard'] = gateway_apply_button_style(
    $step_payment['inline_keyboard'],
    $users['lang'] ?? 'fa',
    $customer_texts['textbot']['cartToCart'] ?? null
);
// If the routing left nothing, telling the buyer to "pick a method below" is
// worse than useless - every call site uses $noCreditText so they all say the
// honest thing instead.
$step_payment_none = empty($step_payment['inline_keyboard']);
$noCreditText = $step_payment_none
    ? $customer_texts['users']['sell']['noPaymentMethod']
    : $customer_texts['users']['sell']['noCredit'];
$step_payment['inline_keyboard'][] = [
    ['text' => $customer_texts['keyboard']['closeList'], 'callback_data' => "colselist", 'style' => 'danger']
];
$step_payment = json_encode($step_payment);
$keyboardhelpadmin = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['addEducation']], ['text' => $textbotlang['keyboard']['deleteEducation']]],
        [['text' => $textbotlang['keyboard']['editEducation']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$shopkeyboard = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['shopFeatureStatus']]],
        [['text' => $textbotlang['keyboard']['manageProducts']], ['text' => $textbotlang['keyboard']['manageCategory']]],
        [['text' => $textbotlang['keyboard']['financial']], ['text' => $textbotlang['Admin']['LangScope']['hubBtn']]],
        // ⏱ اکانت ساعتی is a way of selling, so its settings live with the shop's
        [['text' => $textbotlang['keyboard']['topupPackages']], ['text' => $textbotlang['Admin']['Payg']['rowLabel']]],
        // 🧩 حجم دلخواه: every panel's custom plans, from the shop too
        [['text' => $textbotlang['Admin']['Cvol']['btn']]],
        // 🎁 حجم یا زمان گروهی: what the shop sold, topped up for many at once;
        // 🚀 سرعت گروهی beside it only while a panel can take a speed (Rebecca)
        array_merge([['text' => $textbotlang['Admin']['BulkGift']['btn']]], bulkspeed_available() ? [['text' => $textbotlang['Admin']['BulkSpeed']['btn']]] : []),
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$keyboard_Category_manage = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['addCategory']], ['text' => $textbotlang['keyboard']['deleteCategory']]],
        [['text' => $textbotlang['keyboard']['editCategoryMenu']]],
        [['text' => $textbotlang['keyboard']['backToShopMenu']]]
    ],
    'resize_keyboard' => true
]);
$keyboard_shop_manage = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['addProduct']], ['text' => $textbotlang['Admin']['BulkProduct']['btn']]],
        [['text' => $textbotlang['keyboard']['editProduct']], ['text' => $textbotlang['keyboard']['deleteProduct']]],
        [['text' => $textbotlang['keyboard']['increaseGroupPrice']], ['text' => $textbotlang['keyboard']['decreaseGroupPrice']]],
        [['text' => $textbotlang['Admin']['MoveProd']['hubBtn']]],
        [['text' => $textbotlang['keyboard']['backToShopMenu']]]
    ],
    'resize_keyboard' => true
]);
// the single add-product flow gets the same skip affordance the bulk flow has
$addproduct_note_keyboard = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['Admin']['BulkProduct']['skipNoteBtn']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
// 🚀 a product's speed limit: a number, or none
$speedlimit_keyboard = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['Admin']['Product']['speedNoneBtn']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$bulkproduct_note_keyboard = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['Admin']['BulkProduct']['skipNoteBtn']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$kb_userlang = $users['lang'] ?? 'fa';
if (glass_on($kb_userlang, $setting)) {
    $confrimrolls = json_encode([
        'inline_keyboard' => [
            [
                bt_button($kb_userlang, 'keyboard.acceptRules', $customer_texts['keyboard']['acceptRules'], "acceptrule", 'success'),
            ],
        ]
    ]);
} else {
    // reply keyboard: only the label can be overridden, Telegram has no
    // colour/callback here
    $confrimrolls = json_encode([
        'keyboard' => [
            [['text' => bt_reply_label($kb_userlang, 'keyboard.acceptRules', $customer_texts['keyboard']['acceptRules'])]],
        ],
        'resize_keyboard' => true
    ]);
}
// SHOP surface: phone verification is something a customer is asked for.
$request_contact = json_encode([
    'keyboard' => [
        [['text' => bt_reply_label($kb_userlang, 'keyboard.sendPhoneNumber', $customer_texts['keyboard']['sendPhoneNumber']), 'request_contact' => true]],
        [['text' => $customer_texts['users']['backbtn']]]
    ],
    'resize_keyboard' => true
]);
$Feature_status = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['viewAccountInfoFeature']]],
        [['text' => $textbotlang['keyboard']['testAccountFeature']], ['text' => $textbotlang['keyboard']['educationFeature']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$channelkeyboard = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['addChannel']], ['text' => $textbotlang['keyboard']['deleteChannel']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
if (glass_on($kb_userlang, $setting)) {
    $backuser = json_encode([
        'inline_keyboard' => [
            [['text' => $customer_texts['users']['backbtn'], 'callback_data' => "backuser"]]
        ],
    ]);
} else {
    $backuser = json_encode([
        'keyboard' => [
            [['text' => $customer_texts['users']['backbtn']]]
        ],
        'resize_keyboard' => true,
    ]);
}
$backadmin = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true,
]);
// purchase flow: pick a custom service name, use a bot-generated random one, or
// cancel back to the plan list - always inline regardless of the main-menu
// glass/reply toggle, same as every other purchase-flow action button
$selectUsernameKb = sell_selectUsername_kb($user['lang'] ?? 'fa', $customer_texts);
//------------------  [ list panel ]----------------//
$stmt = $pdo->prepare("SHOW TABLES LIKE 'marzban_panel'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
$namepanel = [];
if ($table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM marzban_panel");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $namepanel[] = [$row['name_panel']];
    }
    $list_marzban_panel = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($namepanel as $button) {
        $list_marzban_panel['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $list_marzban_panel['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backAdminBtn']],
        ['text' => $textbotlang['Admin']['backMenuBtn']]
    ];
    $json_list_marzban_panel = json_encode($list_marzban_panel);
    //------------------  [ list panel inline ]----------------//
    $stmt = $pdo->prepare("SELECT * FROM marzban_panel");
    $stmt->execute();
    $list_marzban_panel_edit_product = ['inline_keyboard' => []];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $list_marzban_panel_edit_product['inline_keyboard'][] = [['text' => $row['name_panel'], 'callback_data' => 'locationedit_' . $row['code_panel']]];
    }
    $list_marzban_panel_edit_product['inline_keyboard'][] = [['text' => $textbotlang['keyboard']['allPanels'], 'callback_data' => 'locationedit_all']];
    $list_marzban_panel_edit_product['inline_keyboard'][] = [['text' => $textbotlang['keyboard']['backToPreviousMenu'], 'callback_data' => 'backproductadmin']];
    $list_marzban_panel_edit_product = json_encode($list_marzban_panel_edit_product);
}
//------------------  [ list channel ]----------------//
$stmt = $pdo->prepare("SHOW TABLES LIKE 'channels'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
$list_channels = [];
if ($table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM channels");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $list_channels[] = [$row['link']];
    }
    $list_channels_join = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($list_channels as $button) {
        $list_channels_join['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $list_channels_join['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backAdminBtn']],
        ['text' => $textbotlang['Admin']['backMenuBtn']]
    ];
    $list_channels_joins = json_encode($list_channels_join);
}
//------------------  [ list card ]----------------//
$stmt = $pdo->prepare("SHOW TABLES LIKE 'card_number'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
$list_card = [];
if ($table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM card_number");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $list_card[] = [$row['cardnumber']];
    }
    $list_card_remove = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($list_card as $button) {
        $list_card_remove['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $list_card_remove['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backAdminBtn']],
        ['text' => $textbotlang['Admin']['backMenuBtn']]
    ];
    $list_card_remove = json_encode($list_card_remove);
}
//------------------  [ help list ]----------------//
$stmt = $pdo->prepare("SHOW TABLES LIKE 'help'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
if ($table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM help");
    $stmt->execute();
    $helpkey = [];
    $stmt = $pdo->prepare("SELECT * FROM help");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $helpkey[] = [$row['name_os']];
    }
    $help_arrke = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($helpkey as $button) {
        $help_arrke['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $help_arrke['keyboard'][] = [
        ['text' => $textbotlang['users']['backbtn']],
    ];
    $json_list_helpkey = json_encode($help_arrke);
}
//------------------  [ help list ]----------------//
// same filter the tutorial screens use: with 📦 آموزش‌های آماده switched off, a
// category that only holds shipped tutorials must not appear either
$help_cat_rows = function_exists('help_rows_for_user') ? help_rows_for_user() : (select("help", "*", null, null, "fetchAll") ?: []);
$helpcwtgory = ['inline_keyboard' => []];
$datahelp = [];
$help_cat_disp_lang = $users['lang'] ?? 'fa';
$help_cat_buttons_byfa = [];
// tutorials the admin left out of every category ("هیچ‌کدام") - they sit under
// the category rows on the same screen instead of being unreachable
$help_uncat_rows = [];
foreach ($help_cat_rows as $result) {
    $help_cat_raw = trim((string) ($result['category'] ?? ''));
    if ($help_cat_raw === '' || $help_cat_raw === '0') {
        $help_uncat_view = help_resolve_lang($result, $help_cat_disp_lang);
        $help_uncat_rows[] = ['text' => $help_uncat_view['name'], 'callback_data' => "helpos_{$result['id']}"];
        continue;
    }
    if (in_array($result['category'], $datahelp))
        continue;
    if ($result['category'] == null)
        continue;
    $datahelp[] = $result['category'];
    $help_cat_view = help_resolve_lang($result, $help_cat_disp_lang);
    $help_cat_label = ($help_cat_view['category'] !== '') ? $help_cat_view['category'] : $result['category'];
    $help_cat_buttons_byfa[$result['category']] = ['text' => $help_cat_label, 'callback_data' => "helpctgoryـ{$result['category']}"];
}
// apply the admin's per-language order/width/emoji (📐 چیدمان / 🎭 ایموجی
// under 🎨 نمایش دسته‌بندی و آموزش‌ها), keyed by the base (fa) category
// string — see help_layout_items()'s docblock for why
$help_cat_section = help_layout_section($help_cat_disp_lang, 'categories');
$help_cat_ordered = help_layout_visible(help_layout_apply_order(array_keys($help_cat_buttons_byfa), $help_cat_section['order']), $help_cat_section);
foreach ($help_cat_ordered as $help_cat_fa_key) {
    $help_cat_emoji = $help_cat_section['emoji'][$help_cat_fa_key] ?? '';
    if ($help_cat_emoji !== '') {
        $help_cat_buttons_byfa[$help_cat_fa_key]['text'] = $help_cat_emoji . ' ' . $help_cat_buttons_byfa[$help_cat_fa_key]['text'];
    }
    // categories are blue out of the box (tutorials are green)
    $help_cat_color = $help_cat_section['color'][$help_cat_fa_key] ?? '';
    $help_cat_buttons_byfa[$help_cat_fa_key]['style'] = in_array($help_cat_color, ['primary', 'success', 'danger'], true) ? $help_cat_color : 'primary';
}
$helpcwtgory['inline_keyboard'] = array_merge($helpcwtgory['inline_keyboard'], help_layout_chunk_rows($help_cat_ordered, $help_cat_buttons_byfa, $help_cat_section['width']));
foreach ($help_uncat_rows as $help_uncat_btn) {
    $helpcwtgory['inline_keyboard'][] = [$help_uncat_btn];
}
if (feature_value('linkappstatus', $help_cat_disp_lang, $setting['linkappstatus']) == "1") {
    $helpcwtgory['inline_keyboard'][] = [
        ['text' => $textbotlang['keyboard']['appDownloadLink'], 'callback_data' => "linkappdownlod"],
    ];
}
if (!bt_button_hidden($help_cat_disp_lang, 'bottext.btnCloseHelp')) {
    $helpcwtgory['inline_keyboard'][] = [
        bt_button($help_cat_disp_lang, 'bottext.btnCloseHelp', $textbotlang['bottext']['btnCloseHelp'] ?? $textbotlang['bottext']['btn_close'], 'mmclose:he'),
    ];
}
$json_list_helpـcategory = json_encode($helpcwtgory);


//------------------  [ help app ]----------------//
// only the rows that belong to this customer's language (a row with no
// language of its own belongs to every language) - managed per language from
// 🌐 وضعیت قابلیت‌ها (هر زبان) -> 🔗 لینک دانلود برنامه
$helpapp = ['inline_keyboard' => []];
foreach (app_rows_for_lang($users['lang'] ?? 'fa') as $result) {
    $helpapp['inline_keyboard'][] = [
        ['text' => $result['name'], 'url' => $result['link']]
    ];
}
$helpapp['inline_keyboard'][] = [
    ['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"],
];
$json_list_helpـlink = json_encode($helpapp);
//------------------  [ help app admin ]----------------//
$stmt = $pdo->prepare("SELECT * FROM app");
$stmt->execute();
$helpappremove = ['keyboard' => [], 'resize_keyboard' => true];
while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $helpappremove['keyboard'][] = [
        ['text' => $result['name']],
    ];
}
$helpappremove['keyboard'][] = [
    ['text' => $textbotlang['Admin']['backAdminBtn']],
];
$json_list_remove_helpـlink = json_encode($helpappremove);
//------------------  [ listpanelusers ]----------------//
$stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE status = 'active' AND (agent = :agent OR agent = 'all') AND (FIND_IN_SET(:userlang, lang) OR lang = 'all' OR lang IS NULL OR lang = '')");
$stmt->bindParam(':agent', $users['agent']);
$stmt->bindValue(':userlang', $users['lang'] ?? 'fa', PDO::PARAM_STR);
$stmt->execute();
$list_marzban_panel_users = ['inline_keyboard' => []];
$panelcount = select("marzban_panel", "*", "status", "active", "count");
// per-language button styling (order/width/emoji/color/rename), set via
// 🎨 شخصی‌سازی نمایش دکمه‌ها -> پنل‌ها in the admin panel
$lp_userlang = $users['lang'] ?? 'fa';
$lp_section = help_layout_section($lp_userlang, 'panel');
$lp_cbprefix = ($users['step'] == "getusernameinfo") ? "locationnotuser_" : "location_";
$lp_buttons = [];
while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($result['hide_user'] != null && in_array($from_id, json_decode($result['hide_user'], true)))
        continue;
    if ($result['type'] == "Manualsale") {
        $stmts = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = :codepanel AND status = 'active'");
        $stmts->bindParam(':codepanel', $result['code_panel']);
        $stmts->execute();
        $configexits = $stmts->rowCount();
        if (intval($configexits) == 0)
            continue;
    }
    $lp_key = (string) $result['code_panel'];
    $lp_name = $lp_section['rename'][$lp_key] ?? $result['name_panel'];
    $lp_emo = help_layout_emoji_prefix($lp_key, $lp_section);
    $lp_name = $lp_emo['prefix'] . $lp_name;
    $lp_btn = ['text' => $lp_name, 'callback_data' => "{$lp_cbprefix}{$result['code_panel']}"];
    if ($lp_emo['icon'] !== '') {
        $lp_btn['icon_custom_emoji_id'] = $lp_emo['icon'];
    }
    $lp_color = $lp_section['color'][$lp_key] ?? '';
    if ($lp_color !== '' && in_array($lp_color, ['primary', 'success', 'danger'], true)) {
        $lp_btn['style'] = $lp_color;
    }
    $lp_buttons[$lp_key] = $lp_btn;
}
$lp_ordered = help_layout_visible(help_layout_apply_order(array_keys($lp_buttons), $lp_section['order']), $lp_section);
$list_marzban_panel_users['inline_keyboard'] = array_merge($list_marzban_panel_users['inline_keyboard'], help_layout_chunk_rows($lp_ordered, $lp_buttons, $lp_section['width']));
$statusnote = false;
if (feature_value('statusnamecustom', $lp_userlang, $setting['statusnamecustom']) == 'onnamecustom')
    $statusnote = true;
if (feature_value('statusnoteforf', $lp_userlang, $setting['statusnoteforf']) == "0" && $users['agent'] == "f")
    $statusnote = false;
if (!bt_button_hidden($lp_userlang, 'bottext.btnCloseBuy')) {
    $list_marzban_panel_users['inline_keyboard'][] = [
        bt_button($lp_userlang, 'bottext.btnCloseBuy', $customer_texts['bottext']['btnCloseBuy'] ?? $customer_texts['bottext']['btn_close'], 'sellclose'),
    ];
}
$list_marzban_panel_user = json_encode($list_marzban_panel_users);


//------------------  [ listpanelusers omdhe ]----------------//
$stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE status = 'active' AND (agent = :agent OR agent = 'all') AND (FIND_IN_SET(:userlang, lang) OR lang = 'all' OR lang IS NULL OR lang = '')");
$stmt->bindParam(':agent', $users['agent']);
$stmt->bindValue(':userlang', $users['lang'] ?? 'fa', PDO::PARAM_STR);
$stmt->execute();
$list_marzban_panel_users_om = ['inline_keyboard' => []];
while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($result['hide_user'] != null and in_array($from_id, json_decode($result['hide_user'], true)))
        continue;
    $list_marzban_panel_users_om['inline_keyboard'][] = [
        ['text' => $result['name_panel'], 'callback_data' => "locationom_{$result['code_panel']}"]
    ];
}
$list_marzban_panel_users_om['inline_keyboard'][] = [
    ['text' => $customer_texts['users']['backbtn'], 'callback_data' => "backuser"],
];
$list_marzban_panel_userom = json_encode($list_marzban_panel_users_om);

//------------------  [ change location ]----------------//
$stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE status = 'active' AND (agent = :agent OR agent = 'all') AND name_panel != :name_panel AND (FIND_IN_SET(:userlang, lang) OR lang = 'all' OR lang IS NULL OR lang = '')");
$stmt->bindValue(':name_panel', $users['Processing_value_four'], PDO::PARAM_STR);
$stmt->bindValue(':agent', $users['agent'], PDO::PARAM_STR);
$stmt->bindValue(':userlang', $users['lang'] ?? 'fa', PDO::PARAM_STR);
$stmt->execute();
$list_marzban_panel_users_change = ['inline_keyboard' => []];
$panelcount = select("marzban_panel", "*", "status", "active", "count");
if ($panelcount > 10) {
    $temp_row = [];
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($result['hide_user'] != null && in_array($from_id, json_decode($result['hide_user'], true)))
            continue;

        $temp_row[] = ['text' => $result['name_panel'], 'callback_data' => "changelocselectlo-{$result['code_panel']}"];
        if (count($temp_row) == 2) {
            $list_marzban_panel_users_change['inline_keyboard'][] = $temp_row;
            $temp_row = [];
        }
    }
    if (!empty($temp_row)) {
        $list_marzban_panel_users_change['inline_keyboard'][] = $temp_row;
    }
} else {
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($result['hide_user'] != null and in_array($from_id, json_decode($result['hide_user'], true)))
            continue;
        $list_marzban_panel_users_change['inline_keyboard'][] = [
            ['text' => $result['name_panel'], 'callback_data' => "changelocselectlo-{$result['code_panel']}"]
        ];
    }
}
$list_marzban_panel_users_change['inline_keyboard'][] = [
    ['text' => $customer_texts['users']['backbtn'], 'callback_data' => "backorder"],
];
$list_marzban_panel_userschange = json_encode($list_marzban_panel_users_change);


//------------------  [ listpanelusers test ]----------------//
// Same language condition the purchase list above already applies: a panel
// assigned to specific languages must not appear for a customer using another
// one. Without it, a shop that set its panels up for Persian only still offered
// every one of them on the test-account screen in every language.
$stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE TestAccount = 'ONTestAccount' AND (agent = :agent OR agent = 'all') AND (FIND_IN_SET(:userlang, lang) OR lang = 'all' OR lang IS NULL OR lang = '')");
$stmt->bindValue(':agent', $users['agent'], PDO::PARAM_STR);
$stmt->bindValue(':userlang', $users['lang'] ?? 'fa', PDO::PARAM_STR);
$stmt->execute();
$list_marzban_panel_usertest = ['inline_keyboard' => []];
// the same per-language panel styling the purchase list uses (order, width,
// emoji, colour, rename - set in 🎨 شخصی‌سازی نمایش دکمه‌ها ← پنل‌ها). These are
// the same panels under a different callback, so a colour the admin picked
// once applied on the buy screen and nowhere else until now.
$ut_section = help_layout_section($users['lang'] ?? 'fa', 'panel');
$ut_buttons = [];
while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($result['hide_user'] != null and in_array($from_id, json_decode($result['hide_user'], true)))
        continue;
    $ut_key = (string) $result['code_panel'];
    $ut_name = $ut_section['rename'][$ut_key] ?? $result['name_panel'];
    $ut_emo = help_layout_emoji_prefix($ut_key, $ut_section);
    $ut_btn = ['text' => $ut_emo['prefix'] . $ut_name, 'callback_data' => "locationtest_{$ut_key}"];
    if ($ut_emo['icon'] !== '') {
        $ut_btn['icon_custom_emoji_id'] = $ut_emo['icon'];
    }
    $ut_color = $ut_section['color'][$ut_key] ?? '';
    if ($ut_color !== '' && in_array($ut_color, ['primary', 'success', 'danger'], true)) {
        $ut_btn['style'] = $ut_color;
    }
    $ut_buttons[$ut_key] = $ut_btn;
}
$ut_ordered = help_layout_visible(help_layout_apply_order(array_keys($ut_buttons), $ut_section['order']), $ut_section);
$list_marzban_panel_usertest['inline_keyboard'] = array_merge(
    $list_marzban_panel_usertest['inline_keyboard'],
    help_layout_chunk_rows($ut_ordered, $ut_buttons, $ut_section['width'])
);
// its own close button, separate from the purchase flow's: this list is the
// test-account journey, so wording it there should not reword the shop's
if (!bt_button_hidden($users['lang'] ?? 'fa', 'bottext.btnCloseTest')) {
    $list_marzban_panel_usertest['inline_keyboard'][] = [
        bt_button($users['lang'] ?? 'fa', 'bottext.btnCloseTest', $customer_texts['bottext']['btnCloseTest'] ?? $customer_texts['bottext']['btn_close'], 'mmclose:te'),
    ];
}
$list_marzban_usertest = json_encode($list_marzban_panel_usertest);


//--------------------------------------------------
$stmt = $pdo->prepare("SHOW TABLES LIKE 'protocol'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
if ($table_exists) {
    $getdataprotocol = select("protocol", "*", null, null, "fetchAll");
    $protocol = [];
    foreach ($getdataprotocol as $result) {
        $protocol[] = [['text' => $result['NameProtocol']]];
    }
    $protocol[] = [['text' => $textbotlang['Admin']['backAdminBtn']]];
    $keyboardprotocollist = json_encode(['resize_keyboard' => true, 'keyboard' => $protocol]);
}
//--------------------------------------------------
$stmt = $pdo->prepare("SHOW TABLES LIKE 'product'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
if ($table_exists) {
    $product = [];
    $stmt = $pdo->prepare("SELECT * FROM product WHERE Location = :text or Location = '/all' ");
    $stmt->bindParam(':text', $text, PDO::PARAM_STR);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $product[] = [$row['name_product']];
    }
    $list_product = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    $list_product['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backAdminBtn']],
    ];
    foreach ($product as $button) {
        $list_product['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $json_list_product_list_admin = json_encode($list_product);
}
//--------------------------------------------------
$stmt = $pdo->prepare("SHOW TABLES LIKE 'Discount'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
if ($table_exists) {
    $Discount = [];
    $stmt = $pdo->prepare("SELECT * FROM Discount");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $Discount[] = [$row['code']];
    }
    $list_Discount = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    $list_Discount['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backAdminBtn']],
    ];
    foreach ($Discount as $button) {
        $list_Discount['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $json_list_Discount_list_admin = json_encode($list_Discount);
}
//--------------------------------------------------
$stmt = $pdo->prepare("SHOW TABLES LIKE 'Inbound'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
if ($table_exists) {
    $Inboundkeyboard = [];
    $stmt = $pdo->prepare("SELECT * FROM Inbound WHERE location = :Processing_value AND protocol = :text");
    $stmt->bindParam(':text', $text, PDO::PARAM_STR);
    $stmt->bindParam(':Processing_value', $users['Processing_value'], PDO::PARAM_STR);
    $stmt->execute();
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $Inboundkeyboard[] = [$row['NameInbound']];
        }

    }
    $list_Inbound = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($Inboundkeyboard as $button) {
        $list_Inbound['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $list_Inbound['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backAdminBtn']],
    ];
    $json_list_Inbound_list_admin = json_encode($list_Inbound);
}
//--------------------------------------------------
// 🌐 تأیید خرید goes BACK into the purchase flow rather than out to the main
// menu. 'buybacktow' is the flow's own re-entry callback (the category and
// duration screens already use it): it re-runs the panel step, which shows the
// panel picker when there is one, and falls through to the category screen when
// 🖥 نمایش انتخاب پنل is off and a single panel is auto-picked. It is also the
// one buy-callback that deliberately skips the نام دلخواه note step, so going
// back never re-asks a question the user already answered.
$payment = sell_confirm_kb($user['lang'] ?? 'fa', $customer_texts, "confirmandgetservice", "buybacktow");
$paymentom = sell_confirm_kb($user['lang'] ?? 'fa', $customer_texts, "confirmandgetservice");
$change_product = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['price']], ['text' => $textbotlang['keyboard']['volume']], ['text' => $textbotlang['keyboard']['time']]],
        [['text' => $textbotlang['keyboard']['productName']], ['text' => $textbotlang['keyboard']['userType']]],
        [['text' => $textbotlang['keyboard']['volumeResetType']], ['text' => $textbotlang['keyboard']['note']]],
        [['text' => $textbotlang['keyboard']['productLocation']], ['text' => $textbotlang['keyboard']['category']]],
        [['text' => $textbotlang['keyboard']['setInbound']], ['text' => $textbotlang['keyboard']['showFirstPurchase']]],
        [['text' => $textbotlang['keyboard']['hidePanel']], ['text' => $textbotlang['keyboard']['deleteAllHiddenPanels']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);

$keyboardprotocol = json_encode([
    'keyboard' => [
        [['text' => "vless"], ['text' => "vmess"], ['text' => "trojan"]],
        [['text' => "shadowsocks"]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$MethodUsername = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['usernameSequential']]],
        [['text' => $textbotlang['keyboard']['numericIdRandom']]],
        [['text' => $textbotlang['keyboard']['customUsername']]],
        [['text' => $textbotlang['keyboard']['customUsernameRandom']]],
        [['text' => $textbotlang['keyboard']['customTextRandom']]],
        [['text' => $textbotlang['keyboard']['customTextSequential']]],
        [['text' => $textbotlang['keyboard']['numericIdSequential']]],
        [['text' => $textbotlang['keyboard']['agentCustomTextSequential']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
if (!function_exists('panel_menu_groups')) {
    // Themed grouping for the panel-settings menu. The membership lists below
    // are a superset across every panel type; which of them actually appear is
    // decided per type by panel_menu_split(), reading the type's own existing
    // flat keyboard. That way a button can never silently disappear when a
    // panel type has an unusual button set - see the "other" bucket.
    function panel_menu_groups($textbotlang)
    {
        $k = $textbotlang['keyboard'];
        $g = function ($keys) use ($k) {
            $o = [];
            foreach ($keys as $key) {
                $v = $k[$key] ?? '';
                if ($v !== '') {
                    $o[] = $v;
                }
            }
            return $o;
        };
        return [
            'connection' => [
                'label' => $textbotlang['Admin']['PanelMenu']['connectionBtn'],
                'items' => $g(['panelName', 'editPanelUrl', 'editUsername', 'editPassword', 'subLinkDomain', 'panelSetting', 'duplicatePanel', 'deletePanel']),
            ],
            'account' => [
                'label' => $textbotlang['Admin']['PanelMenu']['accountBtn'],
                'items' => $g(['usernameMethod', 'renewalMethod', 'accountCreateLimit', 'changeUserGroup', 'setProtocolInbound', 'setInboundId', 'setGroupName', 'serviceSettings', 'inboundDeactivate', 'addConfig', 'editConfig', 'deleteConfig']),
            ],
            'pricing' => [
                'label' => $textbotlang['Admin']['PanelMenu']['pricingBtn'],
                // 🧩 حجم دلخواه's own hub first (cvol.php); this group is glass buttons now
                'items' => array_merge(array_filter([$textbotlang['Admin']['Cvol']['featureSettingsBtn'] ?? '']), $g(['extraVolumePrice', 'extraTimePrice', 'changeLocationPrice'])),
            ],
            'test' => [
                'label' => $textbotlang['Admin']['PanelMenu']['testBtn'],
                'items' => $g(['testServiceTime', 'testAccountVolume', 'testDeleteTime']),
            ],
            'visibility' => [
                'label' => $textbotlang['Admin']['PanelMenu']['visibilityBtn'],
                'items' => $g(['hidePanelForUser', 'removeFromHiddenList']),
            ],
        ];
    }
}
if (!function_exists('panel_menu_split')) {
    function panel_menu_split($flatJson, $textbotlang)
    {
        $kb = json_decode($flatJson, true);
        $labels = [];
        foreach (($kb['keyboard'] ?? []) as $row) {
            foreach ($row as $b) {
                if (!empty($b['text'])) {
                    $labels[] = $b['text'];
                }
            }
        }
        // these keep their place on the top level and are not grouped -
        // تنظیم پروتکل و اینباند too: without it the panel cannot build a
        // service at all
        $pinned = [
            $textbotlang['keyboard']['panelFeatureStatus'],
            $textbotlang['keyboard']['setProtocolInbound'],
            $textbotlang['Admin']['backAdminBtn'],
            $textbotlang['Admin']['backMenuBtn'],
        ];
        $labels = array_values(array_diff($labels, $pinned));
        $out = [];
        foreach (panel_menu_groups($textbotlang) as $id => $grp) {
            $picked = [];
            foreach ($grp['items'] as $item) {
                if (in_array($item, $labels, true)) {
                    $picked[] = $item;
                    $labels = array_values(array_diff($labels, [$item]));
                }
            }
            if (!empty($picked)) {
                $out[$id] = ['label' => $grp['label'], 'items' => $picked];
            }
        }
        // anything this panel type has that no group claimed
        if (!empty($labels)) {
            $out['other'] = [
                'label' => $textbotlang['Admin']['PanelMenu']['otherBtn'],
                'items' => array_values($labels),
            ];
        }
        return $out;
    }
}
if (!function_exists('panel_menu_group_labels')) {
    function panel_menu_group_labels($textbotlang)
    {
        $p = $textbotlang['Admin']['PanelMenu'];
        return [$p['connectionBtn'], $p['accountBtn'], $p['pricingBtn'], $p['testBtn'], $p['visibilityBtn'], $p['otherBtn']];
    }
}
if (!function_exists('panel_menu_rows')) {
    function panel_menu_rows($items)
    {
        $rows = [];
        $pair = [];
        foreach ($items as $it) {
            $pair[] = ['text' => $it];
            if (count($pair) === 2) {
                $rows[] = $pair;
                $pair = [];
            }
        }
        if (!empty($pair)) {
            $rows[] = $pair;
        }
        return $rows;
    }
}
if (!function_exists('panel_menu_top_json')) {
    function panel_menu_top_json($flatJson, $textbotlang)
    {
        $kb = json_decode($flatJson, true);
        $all = [];
        foreach (($kb['keyboard'] ?? []) as $row) {
            foreach ($row as $b) {
                if (!empty($b['text'])) {
                    $all[] = $b['text'];
                }
            }
        }
        $rows = [];
        if (in_array($textbotlang['keyboard']['panelFeatureStatus'], $all, true)) {
            $rows[] = [['text' => $textbotlang['keyboard']['panelFeatureStatus']]];
        }
        if (in_array($textbotlang['keyboard']['setProtocolInbound'], $all, true)) {
            $rows[] = [['text' => $textbotlang['keyboard']['setProtocolInbound']]];
        }
        $labels = [];
        foreach (panel_menu_split($flatJson, $textbotlang) as $grp) {
            $labels[] = $grp['label'];
        }
        foreach (panel_menu_rows($labels) as $r) {
            $rows[] = $r;
        }
        $back = [];
        if (in_array($textbotlang['Admin']['backAdminBtn'], $all, true)) {
            $back[] = ['text' => $textbotlang['Admin']['backAdminBtn']];
        }
        if (in_array($textbotlang['Admin']['backMenuBtn'], $all, true)) {
            $back[] = ['text' => $textbotlang['Admin']['backMenuBtn']];
        }
        if (!empty($back)) {
            $rows[] = $back;
        }
        return json_encode(['keyboard' => $rows, 'resize_keyboard' => true]);
    }
}
if (!function_exists('panel_menu_submenu_json')) {
    function panel_menu_submenu_json($flatJson, $groupLabel, $textbotlang)
    {
        foreach (panel_menu_split($flatJson, $textbotlang) as $grp) {
            if ($grp['label'] !== $groupLabel) {
                continue;
            }
            $rows = panel_menu_rows($grp['items']);
            $rows[] = [['text' => $textbotlang['Admin']['PanelMenu']['backToPanelBtn']]];
            return json_encode(['keyboard' => $rows, 'resize_keyboard' => true]);
        }
        return null;
    }
}
$optionMarzban = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['panelFeatureStatus']]],
        // first thing under the status row: without it the panel cannot build a
        // service at all, so it belongs above the settings that only tune one
        [['text' => $textbotlang['keyboard']['setProtocolInbound']]],
        [['text' => $textbotlang['keyboard']['panelName']], ['text' => $textbotlang['keyboard']['deletePanel']]],
        [['text' => $textbotlang['keyboard']['duplicatePanel']]],
        [['text' => $textbotlang['keyboard']['editPassword']], ['text' => $textbotlang['keyboard']['editUsername']]],
        [['text' => $textbotlang['keyboard']['editPanelUrl']]],
        [['text' => $textbotlang['keyboard']['renewalMethod']], ['text' => $textbotlang['keyboard']['usernameMethod']]],
        [['text' => $textbotlang['keyboard']['accountCreateLimit']], ['text' => $textbotlang['keyboard']['changeUserGroup']]],
        [['text' => $textbotlang['keyboard']['testServiceTime']], ['text' => $textbotlang['keyboard']['testAccountVolume']]],
        [['text' => $textbotlang['keyboard']['testDeleteTime']]],
        [['text' => $textbotlang['Admin']['Cvol']['featureSettingsBtn']]],
        [['text' => $textbotlang['keyboard']['extraVolumePrice']]],
        [['text' => $textbotlang['keyboard']['extraTimePrice']]],
        [['text' => $textbotlang['keyboard']['changeLocationPrice']]],
        [['text' => $textbotlang['keyboard']['inboundDeactivate']]],
        [['text' => $textbotlang['keyboard']['hidePanelForUser']]],
        [['text' => $textbotlang['keyboard']['removeFromHiddenList']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$optionrebecca = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['panelFeatureStatus']]],
        // first thing under the status row: without it the panel cannot build a
        // service at all, so it belongs above the settings that only tune one
        [['text' => $textbotlang['keyboard']['setProtocolInbound']]],
        [['text' => $textbotlang['keyboard']['panelName']], ['text' => $textbotlang['keyboard']['deletePanel']]],
        [['text' => $textbotlang['keyboard']['duplicatePanel']]],
        [['text' => $textbotlang['keyboard']['editPassword']]],
        [['text' => $textbotlang['keyboard']['editPanelUrl']]],
        [['text' => $textbotlang['keyboard']['renewalMethod']], ['text' => $textbotlang['keyboard']['usernameMethod']]],
        [['text' => $textbotlang['keyboard']['accountCreateLimit']], ['text' => $textbotlang['keyboard']['changeUserGroup']]],
        [['text' => $textbotlang['keyboard']['testServiceTime']], ['text' => $textbotlang['keyboard']['testAccountVolume']]],
        [['text' => $textbotlang['keyboard']['testDeleteTime']]],
        [['text' => $textbotlang['Admin']['Cvol']['featureSettingsBtn']]],
        [['text' => $textbotlang['keyboard']['extraVolumePrice']]],
        [['text' => $textbotlang['keyboard']['extraTimePrice']]],
        [['text' => $textbotlang['keyboard']['changeLocationPrice']]],
        [['text' => $textbotlang['keyboard']['inboundDeactivate']]],
        [['text' => $textbotlang['keyboard']['hidePanelForUser']]],
        [['text' => $textbotlang['keyboard']['removeFromHiddenList']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
// Remnawave: Rebecca's menu (its API token is the password) without «غیرفعال
// کردن اینباند», a Marzban-only switch
$optionremnawave = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['panelFeatureStatus']]],
        [['text' => $textbotlang['keyboard']['setProtocolInbound']]],
        [['text' => $textbotlang['keyboard']['panelName']], ['text' => $textbotlang['keyboard']['deletePanel']]],
        [['text' => $textbotlang['keyboard']['duplicatePanel']]],
        [['text' => $textbotlang['keyboard']['editPassword']]],
        [['text' => $textbotlang['keyboard']['editPanelUrl']]],
        [['text' => $textbotlang['keyboard']['renewalMethod']], ['text' => $textbotlang['keyboard']['usernameMethod']]],
        [['text' => $textbotlang['keyboard']['accountCreateLimit']], ['text' => $textbotlang['keyboard']['changeUserGroup']]],
        [['text' => $textbotlang['keyboard']['testServiceTime']], ['text' => $textbotlang['keyboard']['testAccountVolume']]],
        [['text' => $textbotlang['keyboard']['testDeleteTime']]],
        [['text' => $textbotlang['Admin']['Cvol']['featureSettingsBtn']]],
        [['text' => $textbotlang['keyboard']['extraVolumePrice']]],
        [['text' => $textbotlang['keyboard']['extraTimePrice']]],
        [['text' => $textbotlang['keyboard']['changeLocationPrice']]],
        [['text' => $textbotlang['keyboard']['hidePanelForUser']]],
        [['text' => $textbotlang['keyboard']['removeFromHiddenList']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$optionibsng = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['panelFeatureStatus']]],
        [['text' => $textbotlang['keyboard']['panelName']], ['text' => $textbotlang['keyboard']['deletePanel']]],
        [['text' => $textbotlang['keyboard']['duplicatePanel']]],
        [['text' => $textbotlang['keyboard']['editPassword']], ['text' => $textbotlang['keyboard']['editUsername']]],
        [['text' => $textbotlang['keyboard']['editPanelUrl']], ['text' => $textbotlang['keyboard']['setGroupName']]],
        [['text' => $textbotlang['keyboard']['renewalMethod']], ['text' => $textbotlang['keyboard']['usernameMethod']]],
        [['text' => $textbotlang['keyboard']['accountCreateLimit']], ['text' => $textbotlang['keyboard']['changeUserGroup']]],
        [['text' => $textbotlang['Admin']['Cvol']['featureSettingsBtn']]],
        [['text' => $textbotlang['keyboard']['extraVolumePrice']]],
        [['text' => $textbotlang['keyboard']['extraTimePrice']]],
        [['text' => $textbotlang['keyboard']['hidePanelForUser']]],
        [['text' => $textbotlang['keyboard']['removeFromHiddenList']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$option_mikrotik = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['panelFeatureStatus']]],
        [['text' => $textbotlang['keyboard']['panelName']], ['text' => $textbotlang['keyboard']['deletePanel']]],
        [['text' => $textbotlang['keyboard']['duplicatePanel']]],
        [['text' => $textbotlang['keyboard']['editPassword']], ['text' => $textbotlang['keyboard']['editUsername']]],
        [['text' => $textbotlang['keyboard']['editPanelUrl']], ['text' => $textbotlang['keyboard']['setGroupName']]],
        [['text' => $textbotlang['keyboard']['renewalMethod']], ['text' => $textbotlang['keyboard']['usernameMethod']]],
        [['text' => $textbotlang['keyboard']['accountCreateLimit']], ['text' => $textbotlang['keyboard']['changeUserGroup']]],
        [['text' => $textbotlang['Admin']['Cvol']['featureSettingsBtn']]],
        [['text' => $textbotlang['keyboard']['extraVolumePrice']]],
        [['text' => $textbotlang['keyboard']['extraTimePrice']]],
        [['text' => $textbotlang['keyboard']['hidePanelForUser']]],
        [['text' => $textbotlang['keyboard']['removeFromHiddenList']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$options_ui = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['panelFeatureStatus']]],
        // first thing under the status row: without it the panel cannot build a
        // service at all, so it belongs above the settings that only tune one
        [['text' => $textbotlang['keyboard']['setProtocolInbound']]],
        [['text' => $textbotlang['keyboard']['panelName']], ['text' => $textbotlang['keyboard']['deletePanel']]],
        [['text' => $textbotlang['keyboard']['duplicatePanel']]],
        [['text' => $textbotlang['keyboard']['editPassword']], ['text' => $textbotlang['keyboard']['editUsername']]],
        [['text' => $textbotlang['keyboard']['editPanelUrl']]],
        [['text' => $textbotlang['keyboard']['renewalMethod']], ['text' => $textbotlang['keyboard']['usernameMethod']]],
        [['text' => $textbotlang['keyboard']['accountCreateLimit']], ['text' => $textbotlang['keyboard']['changeUserGroup']]],
        [['text' => $textbotlang['keyboard']['testServiceTime']], ['text' => $textbotlang['keyboard']['testAccountVolume']]],
        [['text' => $textbotlang['keyboard']['testDeleteTime']]],
        [['text' => $textbotlang['Admin']['Cvol']['featureSettingsBtn']]],
        [['text' => $textbotlang['keyboard']['extraVolumePrice']]],
        [['text' => $textbotlang['keyboard']['extraTimePrice']]],
        [['text' => $textbotlang['keyboard']['changeLocationPrice']]],
        [['text' => $textbotlang['keyboard']['inboundDeactivate']]],
        [['text' => $textbotlang['keyboard']['hidePanelForUser']]],
        [['text' => $textbotlang['keyboard']['removeFromHiddenList']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$optionwg = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['panelFeatureStatus']]],
        [['text' => $textbotlang['keyboard']['panelName']], ['text' => $textbotlang['keyboard']['deletePanel']]],
        [['text' => $textbotlang['keyboard']['duplicatePanel']]],
        [['text' => $textbotlang['keyboard']['editPassword']]],
        [['text' => $textbotlang['keyboard']['editPanelUrl']], ['text' => $textbotlang['keyboard']['setInboundId']]],
        [['text' => $textbotlang['keyboard']['renewalMethod']], ['text' => $textbotlang['keyboard']['usernameMethod']]],
        [['text' => $textbotlang['keyboard']['accountCreateLimit']], ['text' => $textbotlang['keyboard']['changeUserGroup']]],
        [['text' => $textbotlang['keyboard']['testServiceTime']], ['text' => $textbotlang['keyboard']['testAccountVolume']]],
        [['text' => $textbotlang['keyboard']['testDeleteTime']]],
        [['text' => $textbotlang['Admin']['Cvol']['featureSettingsBtn']]],
        [['text' => $textbotlang['keyboard']['extraVolumePrice']]],
        [['text' => $textbotlang['keyboard']['extraTimePrice']]],
        [['text' => $textbotlang['keyboard']['changeLocationPrice']]],
        [['text' => $textbotlang['keyboard']['inboundDeactivate']]],
        [['text' => $textbotlang['keyboard']['hidePanelForUser']]],
        [['text' => $textbotlang['keyboard']['removeFromHiddenList']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$optionmarzneshin = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['panelFeatureStatus']]],
        [['text' => $textbotlang['keyboard']['panelName']], ['text' => $textbotlang['keyboard']['deletePanel']]],
        [['text' => $textbotlang['keyboard']['duplicatePanel']]],
        [['text' => $textbotlang['keyboard']['editPassword']], ['text' => $textbotlang['keyboard']['editUsername']]],
        [['text' => $textbotlang['keyboard']['editPanelUrl']], ['text' => $textbotlang['keyboard']['renewalMethod']]],
        [['text' => $textbotlang['keyboard']['usernameMethod']]],
        [['text' => $textbotlang['keyboard']['serviceSettings']], ['text' => $textbotlang['keyboard']['accountCreateLimit']]],
        [['text' => $textbotlang['keyboard']['changeUserGroup']]],
        [['text' => $textbotlang['keyboard']['testServiceTime']], ['text' => $textbotlang['keyboard']['testAccountVolume']]],
        [['text' => $textbotlang['keyboard']['testDeleteTime']]],
        [['text' => $textbotlang['keyboard']['changeLocationPrice']], ['text' => $textbotlang['keyboard']['extraVolumePrice']]],
        [['text' => $textbotlang['Admin']['Cvol']['featureSettingsBtn']]],
        [['text' => $textbotlang['keyboard']['extraTimePrice']]],
        [['text' => $textbotlang['keyboard']['hidePanelForUser']]],
        [['text' => $textbotlang['keyboard']['removeFromHiddenList']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$optionManualsale = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['panelFeatureStatus']]],
        [['text' => $textbotlang['keyboard']['panelName']], ['text' => $textbotlang['keyboard']['deletePanel']]],
        [['text' => $textbotlang['keyboard']['duplicatePanel']]],
        [['text' => $textbotlang['keyboard']['usernameMethod']]],
        [['text' => $textbotlang['keyboard']['accountCreateLimit']], ['text' => $textbotlang['keyboard']['changeUserGroup']]],
        [['text' => $textbotlang['keyboard']['addConfig']], ['text' => $textbotlang['keyboard']['deleteConfig']]],
        [['text' => $textbotlang['keyboard']['editConfig']]],
        [['text' => $textbotlang['keyboard']['hidePanelForUser']]],
        [['text' => $textbotlang['keyboard']['removeFromHiddenList']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$optionX_ui_single = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['panelFeatureStatus']]],
        // first thing under the status row: without it the panel cannot build a
        // service at all, so it belongs above the settings that only tune one
        [['text' => $textbotlang['keyboard']['setProtocolInbound']]],
        [['text' => $textbotlang['keyboard']['panelName']], ['text' => $textbotlang['keyboard']['deletePanel']]],
        [['text' => $textbotlang['keyboard']['duplicatePanel']]],
        [['text' => $textbotlang['keyboard']['editPassword']]],
        [['text' => $textbotlang['keyboard']['editPanelUrl']], ['text' => $textbotlang['keyboard']['renewalMethod']]],
        [['text' => $textbotlang['keyboard']['usernameMethod']], ['text' => $textbotlang['keyboard']['subLinkDomain']]],
        [['text' => $textbotlang['keyboard']['changeUserGroup']], ['text' => $textbotlang['keyboard']['accountCreateLimit']]],
        [['text' => $textbotlang['keyboard']['testServiceTime']], ['text' => $textbotlang['keyboard']['testAccountVolume']]],
        [['text' => $textbotlang['keyboard']['testDeleteTime']]],
        [['text' => $textbotlang['keyboard']['changeLocationPrice']], ['text' => $textbotlang['keyboard']['extraVolumePrice']]],
        [['text' => $textbotlang['Admin']['Cvol']['featureSettingsBtn']]],
        [['text' => $textbotlang['keyboard']['extraTimePrice']]],
        [['text' => $textbotlang['keyboard']['hidePanelForUser']]],
        [['text' => $textbotlang['keyboard']['removeFromHiddenList']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$optionalireza_single = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['panelFeatureStatus']]],
        [['text' => $textbotlang['keyboard']['panelName']], ['text' => $textbotlang['keyboard']['deletePanel']]],
        [['text' => $textbotlang['keyboard']['duplicatePanel']]],
        [['text' => $textbotlang['keyboard']['editPassword']], ['text' => $textbotlang['keyboard']['editUsername']]],
        [['text' => $textbotlang['keyboard']['editPanelUrl']], ['text' => $textbotlang['keyboard']['renewalMethod']]],
        [['text' => $textbotlang['keyboard']['setInboundId']]],
        [['text' => $textbotlang['keyboard']['usernameMethod']]],
        [['text' => $textbotlang['keyboard']['subLinkDomain']]],
        [['text' => $textbotlang['keyboard']['changeUserGroup']], ['text' => $textbotlang['keyboard']['accountCreateLimit']]],
        [['text' => $textbotlang['keyboard']['testServiceTime']], ['text' => $textbotlang['keyboard']['testAccountVolume']]],
        [['text' => $textbotlang['keyboard']['testDeleteTime']]],
        [['text' => $textbotlang['keyboard']['changeLocationPrice']], ['text' => $textbotlang['keyboard']['extraVolumePrice']]],
        [['text' => $textbotlang['Admin']['Cvol']['featureSettingsBtn']]],
        [['text' => $textbotlang['keyboard']['extraTimePrice']]],
        [['text' => $textbotlang['keyboard']['hidePanelForUser']]],
        [['text' => $textbotlang['keyboard']['removeFromHiddenList']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$optionhiddfy = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['panelFeatureStatus']]],
        [['text' => $textbotlang['keyboard']['panelName']], ['text' => $textbotlang['keyboard']['deletePanel']]],
        [['text' => $textbotlang['keyboard']['duplicatePanel']]],
        [['text' => $textbotlang['keyboard']['editPanelUrl']], ['text' => $textbotlang['keyboard']['renewalMethod']]],
        [['text' => $textbotlang['keyboard']['changeUserGroup']]],
        [['text' => $textbotlang['keyboard']['usernameMethod']]],
        [['text' => $textbotlang['keyboard']['subLinkDomain']]],
        [['text' => $textbotlang['keyboard']['accountCreateLimit']], ['text' => "🔗 uuid admin"]],
        [['text' => $textbotlang['keyboard']['testServiceTime']], ['text' => $textbotlang['keyboard']['testAccountVolume']]],
        [['text' => $textbotlang['keyboard']['testDeleteTime']]],
        [['text' => $textbotlang['keyboard']['changeLocationPrice']], ['text' => $textbotlang['keyboard']['extraVolumePrice']]],
        [['text' => $textbotlang['Admin']['Cvol']['featureSettingsBtn']]],
        [['text' => $textbotlang['keyboard']['extraTimePrice']]],
        [['text' => $textbotlang['keyboard']['hidePanelForUser']]],
        [['text' => $textbotlang['keyboard']['removeFromHiddenList']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
if (feature_value('statussupportpv', $users['lang'] ?? 'fa', $setting['statussupportpv']) == "onpvsupport") {
    $supportoption = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $customer_texts['textbot']['faq'], 'callback_data' => "fqQuestions"],
                ['text' => $customer_texts['keyboard']['sendMessageToSupport'], 'url' => "https://t.me/{$setting['id_support']}"],
            ],
            [
                ['text' => $customer_texts['keyboard']['backToMainMenu'], 'callback_data' => "backuser"]
            ],

        ]
    ]);
} else {
    $supportoption = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $customer_texts['textbot']['faq'], 'callback_data' => "fqQuestions"],
                ['text' => $customer_texts['keyboard']['sendMessageToSupport'], 'callback_data' => "support"],
            ],
            [
                ['text' => $customer_texts['keyboard']['backToMainMenu'], 'callback_data' => "backuser"]
            ],

        ]
    ]);
}
$adminrule = json_encode([
    'keyboard' => [
        [['text' => "administrator"], ['text' => "Seller"], ['text' => "support"]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$affiliates = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['setAffiliatePercent']]],
        [['text' => $textbotlang['keyboard']['setAffiliateBanner']]],
        [['text' => $textbotlang['keyboard']['purchaseCommission']], ['text' => $textbotlang['keyboard']['startGift']]],
        [['text' => $textbotlang['keyboard']['firstPurchaseCommission']]],
        [['text' => $textbotlang['keyboard']['startGiftAmount']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$keyboardexportdata = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['exportUsers']], ['text' => $textbotlang['keyboard']['exportOrders']]],
        [['text' => $textbotlang['keyboard']['exportPayments']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$helpedit = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['editName']], ['text' => $textbotlang['keyboard']['editDescription']]],
        [['text' => $textbotlang['keyboard']['editMedia']], ['text' => $textbotlang['keyboard']['editCategory']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$Methodextend = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['resetVolumeTime']]],
        [['text' => $textbotlang['keyboard']['addTimeVolumeNextMonth']]],
        [['text' => $textbotlang['keyboard']['resetTimeAddVolume']]],
        [['text' => $textbotlang['keyboard']['resetVolumeAddTime']]],
        [['text' => $textbotlang['keyboard']['addTimeConvertVolume']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$keyboardtimereset = json_encode([
    'keyboard' => [
        [['text' => "no_reset"], ['text' => "day"], ['text' => "week"]],
        [['text' => "month"], ['text' => "year"]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$keyboardtypepanel = json_encode([
    'inline_keyboard' => [
        [
            ['text' => $textbotlang['keyboard']['marzban'], 'callback_data' => "typepanel#marzban"],
            ['text' => $textbotlang['keyboard']['marzneshin'], 'callback_data' => "typepanel#marzneshin"]
        ],
        [
            ['text' => $textbotlang['keyboard']['passargadPanel'], 'callback_data' => "typepanel#pasarguard"],
            ['text' => $textbotlang['keyboard']['ciroAgentPanel'], 'callback_data' => "typepanel#ciro_agent"]
        ],
        [
            ['text' => $textbotlang['keyboard']['panelTypeSanaei'], 'callback_data' => 'typepanel#x-ui_single'],
            ['text' => $textbotlang['keyboard']['panelTypeAlireza'], 'callback_data' => 'typepanel#alireza_single']
        ],
        [
            ['text' => $textbotlang['keyboard']['manualSale'], 'callback_data' => 'typepanel#Manualsale'],
            ['text' => $textbotlang['keyboard']['hiddify'], 'callback_data' => 'typepanel#hiddify'],
        ],
        [
            ['text' => "WGDashboard", 'callback_data' => 'typepanel#WGDashboard'],
            ['text' => "s_ui", 'callback_data' => 'typepanel#s_ui']
        ],
        [
            ['text' => "ibsng", 'callback_data' => 'typepanel#ibsng'],
            ['text' => $textbotlang['keyboard']['mikrotik'], 'callback_data' => 'typepanel#mikrotik']
        ],
        [
            ['text' => $textbotlang['keyboard']['rebecca'], 'callback_data' => 'typepanel#rebecca'],
            ['text' => $textbotlang['keyboard']['remnawave'], 'callback_data' => 'typepanel#remnawave']
        ],
        [
            ['text' => $textbotlang['Admin']['backAdminBtn'], 'callback_data' => 'admin']
        ]
    ],
]);

$panelechekc = select("marzban_panel", "*", "MethodUsername", $textbotlang['keyboard']['usernameMethodAgentCustom'], "count");
if (glass_on($users['lang'] ?? 'fa', $setting)) {
    $keyboardagent = [
        'inline_keyboard' => [
            [
                ['text' => $customer_texts['keyboard']['bulkPurchase'], 'callback_data' => "kharidanbuh"],
                ['text' => $customer_texts['keyboard']['selectCustomName'], 'callback_data' => "selectname"]
            ],
            [
                ['text' => $customer_texts['users']['backbtn'], 'callback_data' => "backuser"]
            ]
        ],
        'resize_keyboard' => true
    ];
    if ($panelechekc == 0) {
        unset($keyboardagent['inline_keyboard'][0][1]);
    }
} else {
    $keyboardagent = [
        'keyboard' => [
            [['text' => $customer_texts['keyboard']['bulkPurchase']], ['text' => $customer_texts['keyboard']['selectCustomName']]],
            [['text' => $customer_texts['users']['backbtn']]]
        ],
        'resize_keyboard' => true
    ];
    if ($panelechekc == 0) {
        unset($keyboardagent['keyboard'][0][1]);
    }
}
$keyboardagent = json_encode($keyboardagent);
$Swapinokey = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['setEducationIranPay1']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);

$tronnowpayments = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['setEducationCryptoOffline']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$optionathmarzban = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['manualCreateConfig']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$optionathx_ui = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['manualCreateConfig']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$configedit = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['configDetails']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$iranpaykeyboard = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['setEducationIranPay3']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$supportcenter = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['setSupportId']]],
        [['text' => $textbotlang['keyboard']['addDepartment']], ['text' => $textbotlang['keyboard']['deleteDepartment']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
//------------------  [ list departeman ]----------------//
$stmt = $pdo->prepare("SHOW TABLES LIKE 'departman'");
$stmt->execute();
$result = $stmt->fetchAll();
$table_exists = count($result) > 0;
$departeman = [];
if ($table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM departman");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $departeman[] = [$row['name_departman']];
    }
    $departemans = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    foreach ($departeman as $button) {
        $departemans['keyboard'][] = [
            ['text' => $button[0]]
        ];
    }
    $departemans['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backAdminBtn']],
        ['text' => $textbotlang['Admin']['backMenuBtn']]
    ];
    $departemanslist = json_encode($departemans);
}
// list departeman
$list_departman = ['inline_keyboard' => []];
$stmt = $pdo->prepare("SELECT * FROM departman");
$stmt->execute();
while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $list_departman['inline_keyboard'][] = [
        ['text' => $result['name_departman'], 'callback_data' => "departman_{$result['id']}"]
    ];
}
$list_departman['inline_keyboard'][] = [
    ['text' => $customer_texts['users']['backbtn'], 'callback_data' => "backuser"],
];
$list_departman = json_encode($list_departman);
$wheelkeyboard = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['lotteryWinAmount']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']]]
    ],
    'resize_keyboard' => true
]);
$keyboardlinkapp = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['addApp']], ['text' => $textbotlang['keyboard']['deleteApp']]],
        [['text' => $textbotlang['keyboard']['editApp']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
function KeyboardProduct($location, $query, $pricediscount, $datakeyboard, $statuscustom = false, $backuser = "backuser", $valuetow = null, $customvolume = "customsellvolume")
{
    global $pdo, $textbotlang, $from_id, $user;
    $product = ['inline_keyboard' => []];
    $statusshowprice = shop_feature_value('showprice', $user['lang'] ?? 'fa', select("shopSetting", "*", "Namevalue", "statusshowprice", "select")['value']);
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    if ($valuetow != null) {
        $valuetow = "-$valuetow";
    } else {
        $valuetow = "";
    }
    // per-language button styling (order/width/emoji/color/rename), set via
    // 🎨 شخصی‌سازی نمایش دکمه‌ها -> محصولات in the admin panel
    $kp_userlang = $user['lang'] ?? 'fa';
    $kp_section = help_layout_section($kp_userlang, 'product');
    $kp_buttons = [];
    while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $hide_panel = json_decode($result['hide_panel'], true);
        if (in_array($location, $hide_panel))
            continue;
        $stmts2 = $pdo->prepare("SELECT * FROM invoice WHERE Status != 'Unpaid' AND id_user = :id_user");
        $stmts2->bindValue(':id_user', $from_id);
        $stmts2->execute();
        $countorder = $stmts2->rowCount();
        if ($result['one_buy_status'] == "1" && $countorder != 0)
            continue;
        if (intval($pricediscount) != 0) {
            $resultper = ($result['price_product'] * $pricediscount) / 100;
            $result['price_product'] = $result['price_product'] - $resultper;
        }
        $kp_key = (string) $result['id'];
        $kp_name = $kp_section['rename'][$kp_key] ?? $result['name_product'];
        $namekeyboard = $kp_name . " - " . money($result['price_product'], $result['currency'] ?? null);
        if ($statusshowprice == "onshowprice") {
            $kp_name = $namekeyboard;
        }
        $kp_emo = help_layout_emoji_prefix($kp_key, $kp_section);
        $kp_name = $kp_emo['prefix'] . $kp_name;
        $kp_btn = ['text' => $kp_name, 'callback_data' => "{$datakeyboard}{$result['code_product']}{$valuetow}"];
        if ($kp_emo['icon'] !== '') {
            $kp_btn['icon_custom_emoji_id'] = $kp_emo['icon'];
        }
        $kp_color = $kp_section['color'][$kp_key] ?? '';
        if ($kp_color !== '' && in_array($kp_color, ['primary', 'success', 'danger'], true)) {
            $kp_btn['style'] = $kp_color;
        }
        $kp_buttons[$kp_key] = $kp_btn;
    }
    // 🧩 حجم دلخواه (cvol.php): $customvolume is the callback that opens it. Its
    // button is an item of 📐 چیدمان like the plans (key 'cvol'): placed, paired,
    // hidden and styled there; last in the list until the admin moves it.
    if ($statuscustom && ($kp_cv = cvol_entry_button($kp_userlang, $customvolume))) {
        $kp_buttons['cvol'] = cvol_layout_button($kp_cv, $kp_section);
    }
    $kp_ordered = help_layout_visible(help_layout_apply_order(array_keys($kp_buttons), $kp_section['order']), $kp_section);
    $product['inline_keyboard'] = array_merge($product['inline_keyboard'], help_layout_chunk_rows($kp_ordered, $kp_buttons, $kp_section['width']));
    // dedicated label, not the shared users.status.backinfo (~30 other sites use
    // that one for unrelated "back to invoice/account" screens) - this button
    // specifically returns to whichever step preceded the product list, and its
    // own caption+sticker are retired automatically by Editmessagetext()/
    // deletemessage() the moment the target screen replaces this one
    // red by default like every other "go back" in the bot, and recolourable
    // from 🎨 شخصی‌سازی like every other button item
    //
    // Named for where it goes: the category list, or - with the category step
    // off, when the panels are the step before the products - the panel
    // selection ('buybacktow' is the panel list's own re-entry). It said
    // «بازگشت به لیست دسته بندی» on a trip back to the panels.
    $kp_backItem = in_array($backuser, ['buybacktow', 'buyfresh'], true) ? 'backToPanelSelectBtn' : 'backToPreviousBtn';
    if (!bt_button_hidden($kp_userlang, 'users.sell.' . $kp_backItem)) {
        $product['inline_keyboard'][] = [
            bt_button($kp_userlang, 'users.sell.' . $kp_backItem, $textbotlang['users']['sell'][$kp_backItem], $backuser),
        ];
    }
    return json_encode($product);
}
function KeyboardCategory($location, $agent, $backuser = "backuser")
{
    global $pdo, $textbotlang, $user;
    $ls_userlang = $user['lang'] ?? 'fa';
    $stmt = $pdo->prepare("SELECT * FROM category");
    $stmt->execute();
    $list_category = ['inline_keyboard' => [],];
    // per-language button styling (order/width/emoji/color/rename), set via
    // 🎨 شخصی‌سازی نمایش دکمه‌ها -> دسته‌بندی‌ها in the admin panel
    $lc_section = help_layout_section($ls_userlang, 'category');
    $lc_buttons = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!lang_scope_matches($row['lang'] ?? 'all', $ls_userlang)) {
            continue;
        }
        $stmts = $pdo->prepare("SELECT * FROM product WHERE (Location = :location OR Location = '/all') AND category = :category AND agent = :agent AND (FIND_IN_SET(:userlang, lang) OR lang = 'all' OR lang IS NULL OR lang = '')");
        $stmts->bindParam(':location', $location, PDO::PARAM_STR);
        $stmts->bindParam(':category', $row['remark'], PDO::PARAM_STR);
        $stmts->bindParam(':agent', $agent);
        $stmts->bindValue(':userlang', $ls_userlang, PDO::PARAM_STR);
        $stmts->execute();
        if ($stmts->rowCount() == 0)
            continue;
        $lc_key = (string) $row['id'];
        $lc_name = $lc_section['rename'][$lc_key] ?? $row['remark'];
        $lc_emo = help_layout_emoji_prefix($lc_key, $lc_section);
        $lc_name = $lc_emo['prefix'] . $lc_name;
        $lc_btn = ['text' => $lc_name, 'callback_data' => "categorynames_" . $row['id']];
        if ($lc_emo['icon'] !== '') {
            $lc_btn['icon_custom_emoji_id'] = $lc_emo['icon'];
        }
        $lc_color = $lc_section['color'][$lc_key] ?? '';
        if ($lc_color !== '' && in_array($lc_color, ['primary', 'success', 'danger'], true)) {
            $lc_btn['style'] = $lc_color;
        }
        $lc_buttons[$lc_key] = $lc_btn;
    }
    $lc_ordered = help_layout_visible(help_layout_apply_order(array_keys($lc_buttons), $lc_section['order']), $lc_section);
    $list_category['inline_keyboard'] = array_merge($list_category['inline_keyboard'], help_layout_chunk_rows($lc_ordered, $lc_buttons, $lc_section['width']));
    // $backuser has always been accepted here but was silently dropped - every
    // sibling picker (KeyboardProduct, keyboardTimeCategory) renders it as a row.
    // When 🖥 نمایش انتخاب پنل is OFF the panel screen this would return to was
    // never shown in the first place, so a close button takes its place instead,
    // matching every other screen this session that got the same treatment.
    $lc_panelshow = shop_feature_value('panelshow', $ls_userlang, select("setting", "statuspanelshow", null, null, "select")['statuspanelshow'] ?? 'onpanelshow') == 'onpanelshow';
    if ($lc_panelshow) {
        if (!bt_button_hidden($ls_userlang, 'users.sell.backToPanelListBtn')) {
            $list_category['inline_keyboard'][] = [
                bt_button($ls_userlang, 'users.sell.backToPanelListBtn', $textbotlang['users']['sell']['backToPanelListBtn'], $backuser),
            ];
        }
    } elseif (!bt_button_hidden($ls_userlang, 'bottext.btnCloseBuy')) {
        $list_category['inline_keyboard'][] = [
            bt_button($ls_userlang, 'bottext.btnCloseBuy', $textbotlang['bottext']['btnCloseBuy'] ?? $textbotlang['bottext']['btn_close'], 'mmclose:bu'),
        ];
    }
    return json_encode($list_category);
}

function keyboardTimeCategory($name_panel, $agent, $callback_data = "producttime_", $callback_data_back = "backuser", $statuscustomvolume = false, $statusbtnextend = false)
{
    global $pdo, $textbotlang;
    $stmt = $pdo->prepare("SELECT (Service_time) FROM product WHERE (Location = :name_panel OR Location = '/all') AND  agent = :agent");
    $stmt->bindValue(':name_panel', $name_panel, PDO::PARAM_STR);
    $stmt->bindValue(':agent', $agent, PDO::PARAM_STR);
    $stmt->execute();
    $montheproduct = array_flip(array_flip($stmt->fetchAll(PDO::FETCH_COLUMN)));
    $monthkeyboard = ['inline_keyboard' => []];
    if (in_array("1", $montheproduct)) {
        $monthkeyboard['inline_keyboard'][] = [
            ['text' => $textbotlang['common']['duration']['1day'], 'callback_data' => "{$callback_data}1"]
        ];
    }
    if (in_array("7", $montheproduct)) {
        $monthkeyboard['inline_keyboard'][] = [
            ['text' => $textbotlang['common']['duration']['7day'], 'callback_data' => "{$callback_data}7"]
        ];
    }
    if (in_array("31", $montheproduct)) {
        $monthkeyboard['inline_keyboard'][] = [
            ['text' => $textbotlang['common']['duration']['1'], 'callback_data' => "{$callback_data}31"]
        ];
    }
    if (in_array("30", $montheproduct)) {
        $monthkeyboard['inline_keyboard'][] = [
            ['text' => $textbotlang['common']['duration']['1'], 'callback_data' => "{$callback_data}30"]
        ];
    }
    if (in_array("61", $montheproduct)) {
        $monthkeyboard['inline_keyboard'][] = [
            ['text' => $textbotlang['common']['duration']['2'], 'callback_data' => "{$callback_data}61"]
        ];
    }
    if (in_array("60", $montheproduct)) {
        $monthkeyboard['inline_keyboard'][] = [
            ['text' => $textbotlang['common']['duration']['2'], 'callback_data' => "{$callback_data}60"]
        ];
    }
    if (in_array("91", $montheproduct)) {
        $monthkeyboard['inline_keyboard'][] = [
            ['text' => $textbotlang['common']['duration']['3'], 'callback_data' => "{$callback_data}91"]
        ];
    }
    if (in_array("90", $montheproduct)) {
        $monthkeyboard['inline_keyboard'][] = [
            ['text' => $textbotlang['common']['duration']['3'], 'callback_data' => "{$callback_data}90"]
        ];
    }
    if (in_array("121", $montheproduct)) {
        $monthkeyboard['inline_keyboard'][] = [
            ['text' => $textbotlang['common']['duration']['4'], 'callback_data' => "{$callback_data}121"]
        ];
    }
    if (in_array("120", $montheproduct)) {
        $monthkeyboard['inline_keyboard'][] = [
            ['text' => $textbotlang['common']['duration']['4'], 'callback_data' => "{$callback_data}120"]
        ];
    }
    if (in_array("181", $montheproduct)) {
        $monthkeyboard['inline_keyboard'][] = [
            ['text' => $textbotlang['common']['duration']['6'], 'callback_data' => "{$callback_data}181"]
        ];
    }
    if (in_array("180", $montheproduct)) {
        $monthkeyboard['inline_keyboard'][] = [
            ['text' => $textbotlang['common']['duration']['6'], 'callback_data' => "{$callback_data}180"]
        ];
    }
    if (in_array("365", $montheproduct)) {
        $monthkeyboard['inline_keyboard'][] = [
            ['text' => $textbotlang['common']['duration']['365'], 'callback_data' => "{$callback_data}365"]
        ];
    }
    if (in_array("0", $montheproduct)) {
        $monthkeyboard['inline_keyboard'][] = [
            ['text' => $textbotlang['common']['duration']['byVolume'], 'callback_data' => "{$callback_data}0"]
        ];
    }
    if ($statusbtnextend)
        $monthkeyboard['inline_keyboard'][] = [['text' => $textbotlang['keyboard']['renewCurrentPlan'], 'callback_data' => "exntedagei"]];
    // 🧩 حجم دلخواه: $statuscustomvolume is the callback that opens it (false: none)
    if (is_string($statuscustomvolume) && $statuscustomvolume !== '' && ($tc_cv = cvol_entry_button($user['lang'] ?? 'fa', $statuscustomvolume))) {
        $monthkeyboard['inline_keyboard'][] = [$tc_cv];
    }
    $monthkeyboard['inline_keyboard'][] = [
        ['text' => $textbotlang['users']['status']['backinfo'], 'callback_data' => $callback_data_back]
    ];
    return json_encode($monthkeyboard);
}
$Startelegram = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['setEducationStar']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$keyboardchangelimit = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['freeLimit']], ['text' => $textbotlang['keyboard']['generalLimit']]],
        [['text' => $textbotlang['keyboard']['resetAllUsersLimit']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']]]
    ],
    'resize_keyboard' => true
]);
function KeyboardCategoryadmin()
{
    global $pdo, $textbotlang;
    $stmt = $pdo->prepare("SELECT * FROM category");
    $stmt->execute();
    $list_category = [
        'keyboard' => [],
        'resize_keyboard' => true,
    ];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $list_category['keyboard'][] = [['text' => $row['remark']]];
    }
    $list_category['keyboard'][] = [
        ['text' => $textbotlang['Admin']['backAdminBtn']],
    ];
    return json_encode($list_category);
}
$nowpayment_setting_keyboard = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['setEducationNowPayment']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
$Exception_auto_cart_keyboard = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['excludeUser']], ['text' => $textbotlang['keyboard']['removeUserFromList']]],
        [['text' => $textbotlang['keyboard']['showUserList']]],
        [['text' => $textbotlang['keyboard']['backToCardSettings']]]
    ],
    'resize_keyboard' => true
]);
function keyboard_config($config_split, $id_invoice, $back_active = true, $kind = 'usertest', $lang = null)
{
    global $textbotlang, $user;
    // $lang: whose page it is, when that is not the current user (a service
    // an admin made or approved for someone)
    $cc_lang = $lang ?? ($user['lang'] ?? 'fa');
    $cc_nameFirst = config_col_name_first($cc_lang, $kind);
    $cc_get = configdisplay_element_current($cc_lang, 0, $textbotlang, $kind);
    $cc_hConfig = configdisplay_element_current($cc_lang, 1, $textbotlang, $kind);
    $cc_hName = configdisplay_element_current($cc_lang, 2, $textbotlang, $kind);
    $keyboard_config = ['inline_keyboard' => []];
    $cc_headerConfig = $cc_hConfig['btn'];
    $cc_headerName = $cc_hName['btn'];
    $keyboard_config['inline_keyboard'][] = $cc_nameFirst ? [$cc_headerName, $cc_headerConfig] : [$cc_headerConfig, $cc_headerName];
    for ($i = 0; $i < count($config_split); $i++) {
        $config = $config_split[$i];
        $split_config = explode("://", $config);
        $type_prtocol = $split_config[0];
        $split_config = $split_config[1];
        if (isBase64($split_config)) {
            $split_config = base64_decode($split_config);
        }
        if ($type_prtocol == "vmess") {
            $split_config = json_decode($split_config, true)['ps'];
        } elseif ($type_prtocol == "ss") {
            $split_config = explode("#", $split_config)[1];
        } else {
            $split_config = explode("#", $split_config)[1];
        }
        $cc_getBtn = array_merge($cc_get['btn'], ['callback_data' => "configget_{$id_invoice}_$i"]);
        $cc_nameBtn = ['text' => urldecode($split_config), 'callback_data' => "none"];
        $keyboard_config['inline_keyboard'][] = $cc_nameFirst ? [$cc_nameBtn, $cc_getBtn] : [$cc_getBtn, $cc_nameBtn];

    }
    $cc_getAll = configdisplay_element_current($cc_lang, 3, $textbotlang, $kind);
    $cc_getAllBtn = array_merge($cc_getAll['btn'], ['callback_data' => "configget_$id_invoice" . "_1520"]);
    $keyboard_config['inline_keyboard'][] = [$cc_getAllBtn];
    if ($back_active) {
        // the buy path got its own red "بازگشت به منوی قبل" per admin request -
        // usertest's back button is untouched, still the shared "↪️ بازگشت"
        if ($kind === 'buy') {
            $keyboard_config['inline_keyboard'][] = [['text' => $textbotlang['users']['status']['backToPreviousMenuBtn'], 'callback_data' => "product_$id_invoice", 'style' => 'danger']];
        } else {
            $keyboard_config['inline_keyboard'][] = [['text' => $textbotlang['users']['status']['backinfo'], 'callback_data' => "product_$id_invoice"]];
        }
    }
    return json_encode($keyboard_config);
}
// SHOP surface: offered to a customer whose test-account limit ran out.
// (its one button is 🎨's: name, colour, emoji, hidden - see test_limit_kb)
$keyboard_buy = test_limit_kb($users['lang'] ?? 'fa', $customer_texts);
$option_ciro = json_encode([
    'keyboard' => [
        [['text' => $textbotlang['keyboard']['panelFeatureStatus']]],
        [['text' => $textbotlang['keyboard']['panelName']], ['text' => $textbotlang['keyboard']['deletePanel']]],
        [['text' => $textbotlang['keyboard']['duplicatePanel']]],
        [['text' => $textbotlang['keyboard']['editPassword']]],
        [['text' => $textbotlang['keyboard']['editPanelUrl']], ['text' => $textbotlang['keyboard']['panelSetting']]],
        [['text' => $textbotlang['keyboard']['accountCreateLimit']], ['text' => $textbotlang['keyboard']['changeUserGroup']]],
        [['text' => $textbotlang['Admin']['Cvol']['featureSettingsBtn']]],
        [['text' => $textbotlang['keyboard']['extraVolumePrice']]],
        [['text' => $textbotlang['keyboard']['extraTimePrice']]],
        [['text' => $textbotlang['keyboard']['hidePanelForUser']]],
        [['text' => $textbotlang['keyboard']['removeFromHiddenList']]],
        [['text' => $textbotlang['Admin']['backAdminBtn']], ['text' => $textbotlang['Admin']['backMenuBtn']]]
    ],
    'resize_keyboard' => true
]);
if (!function_exists('language_button_labels')) {
    // every localized label of the "change language" reply button, so a tap is
    // recognised whatever language the user is currently using
    function language_button_labels()
    {
        return [
            '🌏 تغییر زبان',
            '🌏 Change language',
            '🌏 Сменить язык',
            '🌏 切换语言',
            '🌏 Dili üýtgetmek',
            '🌐 تغییر زبان',
        ];
    }
}

if (!function_exists('language_picker_payload')) {
    // The screen the bot shows before the user has a language. Its caption is
    // admin-editable (bottext.langPickerCaption) and its buttons carry the same
    // order/width/colour/emoji/rename styling every other button family has -
    // both were hardcoded here until now.
    //
    // Styling is always read from the 'fa' scope: this is one global screen
    // shown to a user whose language is not yet known, so there is no per-
    // language variant of it to store.
    function language_picker_payload()
    {
        $lsw_meta = [
            'fa' => ['label' => '🇮🇷 فارسی', 'line' => '🌐 لطفاً زبان خود را انتخاب کنید'],
            'en' => ['label' => '🇬🇧 English', 'line' => '🌐 Please select your language'],
        ];
        $lp_section = help_layout_section('fa', 'langpick');
        $lsw_lines = [];
        $lp_buttons = [];
        foreach (lang_switch_enabled_langs() as $code) {
            if (!isset($lsw_meta[$code])) {
                continue;
            }
            $lsw_lines[] = $lsw_meta[$code]['line'];
            $name = $lp_section['rename'][$code] ?? $lsw_meta[$code]['label'];
            $emo = help_layout_emoji_prefix($code, $lp_section);
            $btn = ['text' => $emo['prefix'] . $name, 'callback_data' => "setlang:{$code}"];
            if ($emo['icon'] !== '') {
                $btn['icon_custom_emoji_id'] = $emo['icon'];
            }
            $color = $lp_section['color'][$code] ?? '';
            if ($color !== '' && in_array($color, ['primary', 'success', 'danger'], true)) {
                $btn['style'] = $color;
            }
            $lp_buttons[$code] = $btn;
        }
        $lsw_caption = strtr(lang_picker_caption(), ['{lines}' => implode("\n", $lsw_lines)]);
        $lp_ordered = help_layout_visible(help_layout_apply_order(array_keys($lp_buttons), $lp_section['order']), $lp_section);
        // two per row was hardcoded; the width map decides now, defaulting to
        // 'half' so an untouched shop looks exactly as it did. The map speaks
        // 'half'/'full', not a column count - a number here silently means
        // 'full' and puts every language on its own row.
        $lp_width = $lp_section['width'];
        foreach (array_keys($lp_buttons) as $code) {
            if (!isset($lp_width[$code])) {
                $lp_width[$code] = 'half';
            }
        }
        return [$lsw_caption, json_encode(['inline_keyboard' => help_layout_chunk_rows($lp_ordered, $lp_buttons, $lp_width)])];
    }
    // the caption template, override-aware. {lines} is replaced with one
    // "please choose" line per language the shop offers.
    function lang_picker_caption()
    {
        // One screen for everyone, like its button styling: the copy that
        // 🌐 تنظیمات تغییر زبان edits (the Persian one), whatever language the
        // reader has so far - read in the reader's language, an edit never
        // reached anyone but Persian users.
        $v = function_exists('bottext_resolve_key') ? trim((string) bottext_resolve_key('bottext.langPickerCaption', 'fa')) : '';
        global $textbotlang;
        if ($v === '') {
            $v = (string) ($textbotlang['bottext']['langPickerCaption'] ?? '');
        }
        // the exact frame this screen has always had, kept byte-for-byte -
        // only the middle became a placeholder so it stays admin-editable
        return $v !== ''
            ? $v
            : "═══════════════════════\n       🌍 WELCOME\n═══════════════════════\n\n{lines}\n\n━━━━━━━━━━━━━━━━━━━━━━━\n👇 Tap to continue:";
    }
}

function keyboard_list_text($lang, $groupFilter = null)
{
    global $textbotlang;
    $keyboard_text = ['inline_keyboard' => []];
    // These labels name WHICH message is being edited - they are panel chrome,
    // not content, so they stay Persian like the rest of the panel whatever tab
    // is open. They used to be loaded from the tab's own language file, which
    // made a screen that was half English and half Persian. What the tab's own
    // language actually says is shown by the preview inside each item.
    $keyboard_list_text = $textbotlang['bottext']['items'];
    $bt_unknown_label = $textbotlang['bottext']['unknownMsgLabel'] ?? '💬 پیام ناشناس';
    // language is chosen exactly once, here on the flat home list - group
    // screens no longer show this row at all, so there is nothing left to
    // "re-select"; the row's own dispatcher (admin.php's bt_lang: handler)
    // still accepts an optional :{group} suffix from round 27, kept as a
    // harmless unused capability rather than reverted
    if ($groupFilter === null) {
        $keyboard_text['inline_keyboard'][] = panel_lang_tabs($lang, "bt_lang:%s");
    }
    // buttons are renamed via the ✏️ نام و نمایش دکمه‌ها manager — keep this section for messages only.
    // textbot.testExpired is also skipped here on purpose: it's only reachable
    // from inside 🔑 تنظیم اکانت تست (see $bt_home_sections below and
    // bottext_item_menu_payload() in admin.php) - it used to ALSO have its own
    // home-list row, which gave it two different entry points and made its
    // "back" button ambiguous (it could only point to one of them). Now there
    // is exactly one way in, so "back" is always correct.
    $bt_skip_keys = ['textbot.sell', 'textbot.purchasedServices', 'textbot.extend', 'textbot.userTest', 'textbot.accountWallet', 'textbot.addBalance', 'textbot.tariffList', 'textbot.support', 'textbot.help', 'textbot.affiliates', 'textbot.discount', 'textbot.wheelLuck', 'textbot.faq', 'textbot.testExpired',
        // same reason as textbot.testExpired above: they live inside
        // 🔑 تنظیم اکانت تست now, and a second row here would give them two ways
        // in and an ambiguous "back" - textbot.afterText's own back button has
        // pointed at that screen all along, while its only row was out here
        'bottext.btnCloseTest', 'textbot.selectLocationTest', 'textbot.afterText', 'textbot.afterPay', 'textbot.preInvoice', 'textbot.getConfigHintBuy', 'textbot.getConfigHintTest', 'users.affiliates.rewardAfterPay', 'users.affiliates.rewardConfigHint', 'users.status.infoFull', 'users.Balance.sendReceipt', 'users.Balance.chargeSuccess', 'users.Balance.chargeSuccessDiscount',
        // owned by 🌐 تنظیمات تغییر زبان کاربر, which edits them in place - a
        // second row here would make their back button ambiguous
        'bottext.langPickerCaption',
        // opened from inside 📯 پیام عضویت اجباری کانال, whose back it uses
        'users.channel.left_channel',
        // the test account's other messages: on 🔑 تنظیم اکانت تست's own screen
        'users.usertest.noPanel', 'users.usertest.unavailable', 'users.usertest.errorcreat', 'users.usertest.limitwarning', 'users.sell.created'];
    $bt_can_react_keys = bt_react_keys();
    $bt_list_setting = select("setting", "*", null, null, "select");
    $bt_list_edit = json_decode((string) ($bt_list_setting['text_edit'] ?? ''), true);
    $bt_list_layout = json_decode((string) ($bt_list_setting['keyboardmain'] ?? ''), true);
    $bt_list_st = (is_array($bt_list_layout) && isset($bt_list_layout['text_stickers']) && is_array($bt_list_layout['text_stickers'])) ? $bt_list_layout['text_stickers'] : [];
    $bt_list_re = (is_array($bt_list_layout) && isset($bt_list_layout['text_reactions']) && is_array($bt_list_layout['text_reactions'])) ? $bt_list_layout['text_reactions'] : [];
    // returns [decorated label, telegram button "style" ('success' = real green
    // button, Bot API 9.4+; '' = no style key at all, Telegram's own default]
    $bt_list_be = json_decode((string) ($bt_list_setting['button_edit'] ?? ''), true);
    // A few items were later given stores of their own beyond text/sticker/reaction.
    // Per-button label and colour overrides live in setting.button_edit under the
    // item's own dotted key, which the generic test below already covers; anything
    // that does NOT follow that shape needs an entry here, or the item can be fully
    // configured and still render as untouched - the exact complaint this fixes.
    $bt_extra_stores = [
        // 🔑 تنظیم اکانت تست also owns the config-column display settings -
        // this language's own, the column order included (config_col_name_first)
        'users.usertest.selectUsernamePrompt' => function ($lang, $be, $setting) {
            return !empty($be[$lang]['configDisplay']);
        },
        // the confirm/cancel row is shared by both "تأیید خرید" screens -
        // whichever one is customized, the other should show it too
        'users.sell.preInvoice2' => function ($lang, $be, $setting) {
            return !empty($be[$lang]['users.sell.confirmButtons']);
        },
        'textbot.preInvoice' => function ($lang, $be, $setting) {
            return !empty($be[$lang]['users.sell.confirmButtons']);
        },
        // the buy-only config-column display settings (separate from usertest's above)
        // same reason as the usertest row above
        'users.status.getConfigHintBuy' => function ($lang, $be, $setting) {
            return !empty($be[$lang]['configDisplayBuy']);
        },
        // 📯 also owns its channel buttons (a store of their own) and the
        // "you left the channel" message opened from inside it
        'textbot.channel' => function ($lang, $be, $setting) {
            $te = json_decode((string) ($setting['text_edit'] ?? ''), true);
            return channel_buttons_any_customized($lang) || !empty($be[$lang]['users.channel.left_channel'])
                || (is_array($te) && bottext_dotted_isset($te[$lang] ?? null, 'users.channel.left_channel'));
        },
    ];
    $bt_decorate = function ($key, $label) use ($lang, $bt_list_edit, $bt_list_st, $bt_list_re, $bt_can_react_keys, $bt_list_be, $bt_list_setting, $bt_extra_stores) {
        // a real dotted lookup, not [$parts[0]][$parts[1]]: three-part keys such as
        // users.sell.serviceSelect and users.sell.selectCategory share their first
        // two segments, so the old test turned every sibling green the moment one
        // of them was edited - and missed nothing only because it over-matched
        $custom = is_array($bt_list_edit) && bottext_dotted_isset($bt_list_edit[$lang] ?? null, $key);
        // a message that merely ships with its factory sticker is not customized
        // - and neither is one whose sticker is the shared pre-per-language one,
        // which is why this asks for THIS language's own value and nothing else
        $sticker = bt_media_lookup_own($bt_list_st, $key, $lang);
        if ($sticker !== '' && function_exists('bt_default_sticker') && $sticker === bt_default_sticker($key)) {
            $sticker = '';
        }
        // nor is one stored on a message that never sends it (bt_nosticker_keys())
        if ($sticker !== '' && function_exists('bt_nosticker_keys') && in_array($key, bt_nosticker_keys(), true)) {
            $sticker = '';
        }
        $react = in_array($key, $bt_can_react_keys, true) ? bt_media_lookup_own($bt_list_re, $key, $lang) : '';
        $buttons = is_array($bt_list_be) && !empty($bt_list_be[$lang][$key]);
        if (!$buttons && isset($bt_extra_stores[$key])) {
            $buttons = (bool) $bt_extra_stores[$key]($lang, is_array($bt_list_be) ? $bt_list_be : [], $bt_list_setting);
        }
        $suffix = '';
        if ($custom) {
            $suffix .= ' ✏️';
        }
        if ($react !== '') {
            $suffix .= ' ❤️';
        }
        if ($sticker !== '') {
            $suffix .= ' 🖼';
        }
        if ($buttons) {
            $suffix .= ' 🔘';
        }
        $style = ($custom || $buttons || $sticker !== '' || $react !== '') ? 'success' : '';
        return [$label . $suffix, $style];
    };
    // items tagged with a 'group' live in their own submenu instead of the
    // main list - collect them here so a single summary row can open it
    $bt_grouped = [];
    foreach ($keyboard_list_text as $data) {
        if (!empty($data['group'])) {
            $bt_grouped[$data['group']][] = $data;
        }
    }
    if ($groupFilter !== null) {
        // 👤 مدیریت کاربر's screen is opened from there, not from this list:
        // it carries its own language tabs, since nothing above it chose one
        if ($groupFilter === 'usermgmt') {
            $keyboard_text['inline_keyboard'][] = panel_lang_tabs($lang, "bt_group|%s|usermgmt", null);
        }
        // 🛍 in the customer's own order (bt_myservices_plan()); anything not
        // named there keeps its place after them
        if ($groupFilter === 'myservices') {
            $bt_ms_plan = bt_myservices_plan();
            $bt_ms_pos = array_flip(array_keys($bt_ms_plan));
            $bt_ms_items = [];
            foreach (array_values($bt_grouped['myservices'] ?? []) as $bt_ms_i => $bt_ms_it) {
                if (isset($bt_ms_plan[$bt_ms_it['key']])) {
                    $bt_ms_it['section'] = $bt_ms_plan[$bt_ms_it['key']];
                }
                $bt_ms_items[] = [$bt_ms_pos[$bt_ms_it['key']] ?? (1000 + $bt_ms_i), $bt_ms_it];
            }
            // the service screen's section has no item of its own any more - the
            // bot draws its rows by hand - so a stand-in holds its place
            $bt_ms_items[] = [$bt_ms_pos['users.status.infoFull'] ?? 1000, ['key' => 'users.status.infoFull', 'label' => '', 'section' => 'myservices_screen', 'stand_in' => true]];
            usort($bt_ms_items, fn($a, $b) => $a[0] <=> $b[0]);
            $bt_grouped['myservices'] = array_column($bt_ms_items, 1);
        }
        // 📲's group opens with the look of its page: one row, the picker behind it
        if ($groupFilter === 'subapps') {
            $bt_look = subapp_look($lang);
            $bt_theme = subapp_theme($lang);
            $keyboard_text['inline_keyboard'][] = [['text' => bt_section_meta('subapps_look')['label'], 'callback_data' => 'bt_sep|subapps_look']];
            $keyboard_text['inline_keyboard'][] = [['text' => strtr($textbotlang['bottext']['saLookRow'], ['{look}' => subapp_looks()[$bt_look], '{theme}' => subapp_themes()[$bt_theme]]), 'callback_data' => "salook|{$lang}", 'style' => ($bt_look === 'glass' && $bt_theme === 'auto') ? 'primary' : 'success']];
        }
        if ($groupFilter === 'subapps') {
            // the page's layout and the apps' links, one row each (green once changed)
            $keyboard_text['inline_keyboard'][] = [['text' => $textbotlang['bottext']['saLayRow'], 'callback_data' => "salay|{$lang}", 'style' => subapp_layout_changed($lang) ? 'success' : 'primary'],
                ['text' => $textbotlang['bottext']['saLinksRow'], 'callback_data' => "salinks|{$lang}", 'style' => subapp_links($lang) ? 'success' : 'primary']];
        }
        $bt_cur_section = null;
        foreach (($bt_grouped[$groupFilter] ?? []) as $data) {
            // Items a group screen no longer lists because they moved onto a
            // sub-screen of their own. Declared here, in one place, so the
            // renderer and the reachability audit read the same list instead of
            // disagreeing about whether an item is still reachable.
            $bt_group_moved_keys = ['topup' => [
                'textbot.cardRandomAmountNotice',
                // the card-to-card receipt exchange, now grouped on that
                // gateway's own screen under its own heading
                'users.Balance.askReceiptImage',
                'users.Balance.receiptNeedsPhotoOrText',
                'users.Balance.sendReceipt',
                // now worded per gateway, on each gateway's own screen
                'users.Balance.linkpayments',
            ]];
            if (in_array($data['key'], $bt_group_moved_keys[$groupFilter] ?? [], true)) {
                continue;
            }
            $bt_section = $data['section'] ?? null;
            if ($bt_section !== null && $bt_section !== $bt_cur_section) {
                // white/default style - a non-navigating label, not an action
                $keyboard_text['inline_keyboard'][] = [['text' => bt_section_meta($bt_section)['label'], 'callback_data' => "bt_sep|{$bt_section}"]];
                $bt_cur_section = $bt_section;
                // a service's own screen leads its section: its caption, then
                // its buttons, before the 🌐 / 📲 pair on top of them
                if ($bt_section === 'myservices_screen') {
                    list($bt_sf_label, $bt_sf_style) = $bt_decorate('users.status.infoFull', '📊 کپشن صفحه‌ی سرویس');
                    $keyboard_text['inline_keyboard'][] = [['text' => $bt_sf_label, 'callback_data' => "bt_edit|$lang|users.status.infoFull", 'style' => ($bt_sf_style !== '' ? $bt_sf_style : 'primary')]];
                    $keyboard_text['inline_keyboard'][] = [['text' => '🔘 دکمه‌های صفحه‌ی سرویس (💊 تمدید، 💡 روشن/خاموش، ...)', 'callback_data' => "statusbtn|list|{$lang}", 'style' => !empty($bt_list_be[$lang][statusbtn_bucket('mine')]) ? 'success' : 'primary']];
                    // the 🌐 / 📲 pair is one group for all five places, on the
                    // home list (📲 افزودن سرویس به برنامه) - not repeated here
                }
            }
            if (!empty($data['stand_in'])) {
                continue;
            }
            // a button item shows the button itself, as this tab has it now
            $bt_live = in_array($data['key'], bt_btnitem_keys(), true) ? genbtn_row_label($data['key'], $lang) : null;
            // ...and so does a button whose only store is its text ('live'):
            // its words in this tab, edits included
            if ($bt_live === null && !empty($data['live'])) {
                $bt_cur = lang_tab_texts($lang);
                foreach (explode('.', $data['key']) as $bt_part) {
                    $bt_cur = is_array($bt_cur) ? ($bt_cur[$bt_part] ?? null) : null;
                }
                if (is_string($bt_cur) && trim($bt_cur) !== '') {
                    $bt_live = ['🔘 ' . $bt_cur, ''];
                }
            }
            list($bt_label, $bt_style) = $bt_decorate($data['key'], $bt_live !== null ? $bt_live[0] : $data['label']);
            // blue by default, green (from $bt_decorate) once something is customized
            $bt_btn = ['text' => $bt_label, 'callback_data' => "bt_edit|$lang|{$data['key']}", 'style' => ($bt_style !== '' ? $bt_style : 'primary')];
            if ($bt_live !== null && $bt_live[1] !== '') {
                $bt_btn['icon_custom_emoji_id'] = $bt_live[1];
            }
            $keyboard_text['inline_keyboard'][] = [$bt_btn];
            if ($groupFilter === 'myservices' && $data['key'] === 'users.status.getConfigHintBuy') {
                // caption is edited via the normal bt_edit button above - this
                // extra row opens the buy-only column-order/button-styling hub,
                // a full independent clone of usertest's "🗂 تنظیم نمایش و کپشن
                // کانفیگ" (function.php: config_col_order_payload($kind='buy'))
                $keyboard_text['inline_keyboard'][] = [['text' => '🗂 تنظیم ترتیب و رنگ دکمه‌های دریافت کانفیگ', 'callback_data' => "btact|cfgcolbuy|{$lang}|users.status.getConfigHintBuy", 'style' => 'primary']];
            }
            if ($groupFilter === 'buyflow' && $data['key'] === 'users.sell.serviceSelectFirst') {
                // the 3 sub-screens BtnStyle's own hub offered are exposed
                // directly here now, under their own white divider, instead of
                // behind one more tap into a separate hub screen. Each still
                // targets its existing, already-working btnstyle_kindhub:
                // callback - no new dispatch handler needed for any of them.
                //
                // They carry {$lang}, not a hardcoded 'fa'. These three styles
                // are stored per language like everything else on this screen
                // (help_layout_get()[$lang][$kind]), so pinning them to Persian
                // meant the English tab opened the Persian ones - and said
                // "زبان فعلی: فارسی" while doing it.
                $keyboard_text['inline_keyboard'][] = [['text' => bt_section_meta('btnstyle')['label'], 'callback_data' => 'bt_sep|btnstyle']];
                $keyboard_text['inline_keyboard'][] = [['text' => $textbotlang['Admin']['LangScope']['panelStyleBtn'], 'callback_data' => "btnstyle_kindhub:panel:{$lang}", 'style' => 'primary']];
                $keyboard_text['inline_keyboard'][] = [['text' => $textbotlang['Admin']['LangScope']['categoryStyleBtn'], 'callback_data' => "btnstyle_kindhub:category:{$lang}", 'style' => 'primary']];
                $keyboard_text['inline_keyboard'][] = [['text' => $textbotlang['Admin']['LangScope']['productStyleBtn'], 'callback_data' => "btnstyle_kindhub:product:{$lang}", 'style' => 'primary']];
            }
        }
        // what the service screen's buttons lead to (renewal, extra volume,
        // transfer ...) is a page of its own: one heading and one row here
        if ($groupFilter === 'myservices' && !empty($bt_grouped['msmore'])) {
            $bt_mm_custom = false;
            foreach ($bt_grouped['msmore'] as $bt_g) {
                list(, $bt_g_style) = $bt_decorate($bt_g['key'], $bt_g['label']);
                if ($bt_g_style !== '') {
                    $bt_mm_custom = true;
                    break;
                }
            }
            $keyboard_text['inline_keyboard'][] = [['text' => bt_section_meta('myservices_more')['label'], 'callback_data' => 'bt_sep|myservices_more']];
            $keyboard_text['inline_keyboard'][] = [['text' => $textbotlang['bottext']['groupMsMoreLabel'], 'callback_data' => "bt_group|$lang|msmore", 'style' => $bt_mm_custom ? 'success' : 'primary']];
        }
        if ($groupFilter === 'referral') {
            // the gift config itself - how it is shown, and its account's
            // rules - under a white heading of its own
            $keyboard_text['inline_keyboard'][] = [['text' => bt_section_meta('referral_cfg')['label'], 'callback_data' => 'bt_sep|referral_cfg']];
            $keyboard_text['inline_keyboard'][] = [['text' => '📌 نحوه‌ی نمایش کانفیگ (هدیه‌ی دعوت)', 'callback_data' => "cfgdeliv|list|{$lang}|r", 'style' => 'primary']];
            $keyboard_text['inline_keyboard'][] = [['text' => '⚙️ قوانین اکانت کانفیگ رایگان', 'callback_data' => "affrwrule|{$lang}|open", 'style' => 'primary']];
        }
        // 🧩 حجم دلخواه has screens and messages of its own: one heading and one
        // row here (its 27 items made this screen the biggest in the bot)
        if ($groupFilter === 'buyflow' && !empty($bt_grouped['cvol'])) {
            $bt_cv_custom = false;
            foreach (array_merge($bt_grouped['cvol'], $bt_grouped['cvolpieces'] ?? []) as $bt_g) {
                list(, $bt_g_style) = $bt_decorate($bt_g['key'], $bt_g['label']);
                if ($bt_g_style !== '') {
                    $bt_cv_custom = true;
                    break;
                }
            }
            $keyboard_text['inline_keyboard'][] = [['text' => bt_section_meta('buy_custom')['label'], 'callback_data' => 'bt_sep|buy_custom']];
            $keyboard_text['inline_keyboard'][] = [['text' => $textbotlang['bottext']['groupCvolLabel'], 'callback_data' => "bt_group|$lang|cvol", 'style' => $bt_cv_custom ? 'success' : 'primary']];
        }
        if ($groupFilter === 'cvol') {
            // the text pieces are a screen of their own; the feature's own settings (prices, steps, filters) are one tap away
            if (!empty($bt_grouped['cvolpieces'])) {
                $bt_cp_custom = false;
                foreach ($bt_grouped['cvolpieces'] as $bt_g) {
                    list(, $bt_g_style) = $bt_decorate($bt_g['key'], $bt_g['label']);
                    if ($bt_g_style !== '') {
                        $bt_cp_custom = true;
                        break;
                    }
                }
                $keyboard_text['inline_keyboard'][] = [['text' => $textbotlang['bottext']['groupCvolPiecesLabel'], 'callback_data' => "bt_group|$lang|cvolpieces", 'style' => $bt_cp_custom ? 'success' : 'primary']];
            }
            $keyboard_text['inline_keyboard'][] = [['text' => $textbotlang['bottext']['cvolSettingsRow'], 'callback_data' => 'cvl', 'style' => 'primary']];
        }
        if ($groupFilter === 'buyflow') {
            $keyboard_text['inline_keyboard'][] = [['text' => bt_section_meta('cfgdeliv_link')['label'], 'callback_data' => 'bt_sep|cfgdeliv_link']];
            $keyboard_text['inline_keyboard'][] = [['text' => '📌 نحوه‌ی نمایش کانفیگ', 'callback_data' => "cfgdeliv|list|{$lang}|b", 'style' => 'primary']];
        }
        // ⏱'s screen is only an index - its 71 items on one screen were too
        // many to find anything in. One row per sub-screen (each a group of
        // its own), green once anything on it is customized
        $bt_payg_subs = $textbotlang['bottext']['paygSubs'] ?? [];
        if ($groupFilter === 'payg') {
            foreach ($bt_payg_subs as $bt_sub => $bt_sub_meta) {
                $bt_sub_custom = false;
                foreach (($bt_grouped[$bt_sub] ?? []) as $bt_g) {
                    list(, $bt_g_style) = $bt_decorate($bt_g['key'], $bt_g['label']);
                    if ($bt_g_style !== '') {
                        $bt_sub_custom = true;
                        break;
                    }
                }
                $keyboard_text['inline_keyboard'][] = [['text' => $bt_sub_meta['label'], 'callback_data' => "bt_group|$lang|$bt_sub", 'style' => $bt_sub_custom ? 'success' : 'primary']];
            }
            $keyboard_text['inline_keyboard'][] = [['text' => '⚙️ تنظیمات اکانت ساعتی', 'callback_data' => "paygsec:{$lang}", 'style' => 'primary']];
        }
        if ($groupFilter === 'topup') {
            // The card-to-card caption/button rows, moved here from
            // 🏬 تنظیمات فروشگاه → 🏦 بسته‌های شارژ → کارت به کارت. They keep
            // their original callbacks (nothing new to dispatch); only where
            // those flows return to changed - see topup_after_edit_screen().
            // Every gateway's caption/button rows - card-to-card included - now
            // live on their own screens behind this one button, together with
            // the caption previews that belong to them. This screen stays a
            // short index instead of one long mixed list.
            $keyboard_text['inline_keyboard'][] = [[
                'text' => $textbotlang['Admin']['TopupPkg']['gwListBtn'],
                'callback_data' => "topupgwlist:{$lang}",
                'style' => 'primary',
            ]];
            // 🎁 پیام‌های تخفیف - nine texts that are one subject. They stay in
            // this section rather than moving onto the gateway screens because
            // not one of them is per-gateway: the same sentence is produced for
            // every gateway, so editing it on one would silently edit it on all.
            if (!empty($bt_grouped['topupdisc'])) {
                $bt_disc_custom = false;
                foreach ($bt_grouped['topupdisc'] as $bt_g) {
                    list(, $bt_g_style) = $bt_decorate($bt_g['key'], $bt_g['label']);
                    if ($bt_g_style !== '') {
                        $bt_disc_custom = true;
                        break;
                    }
                }
                $keyboard_text['inline_keyboard'][] = [[
                    'text' => $textbotlang['bottext']['groupTopupDiscLabel'],
                    'callback_data' => "bt_group|$lang|topupdisc",
                    'style' => $bt_disc_custom ? 'success' : 'primary',
                ]];
            }
            // 🎨 ظاهر نمایش درگاه ها - the styling of the payment-method buttons
            // the customer picks from, moved off the 🏦 بسته‌های شارژ hub
            $keyboard_text['inline_keyboard'][] = [['text' => $textbotlang['Admin']['LangScope']['gatewaysBtn'], 'callback_data' => "btnstyle_kindhub:gateway:{$lang}", 'style' => 'primary']];
        }
        if ($groupFilter === 'help') {
            // the actual tutorial content (add/edit/delete/translate) used to be
            // reachable only from 👨‍💼 پنل مدیریت's own 📚 بخش آموزش button - this
            // is the second, more discoverable way in, right next to the
            // messages/buttons that describe this same feature.
            $keyboard_text['inline_keyboard'][] = [['text' => bt_section_meta('help_manage')['label'], 'callback_data' => 'bt_sep|help_manage']];
            $keyboard_text['inline_keyboard'][] = [['text' => '📚 مدیریت آموزش‌ها (افزودن/ویرایش/حذف)', 'callback_data' => "help_lang:{$lang}", 'style' => 'primary']];
            // the appearance of the tutorial buttons themselves (order, width,
            // colour, emoji, and now show/hide). These two hubs already existed
            // under 📚 آموزش in the admin panel - this is a second way in, from
            // the screen that owns the rest of this section's look.
            $keyboard_text['inline_keyboard'][] = [['text' => bt_section_meta('help_style')['label'], 'callback_data' => 'bt_sep|help_style']];
            $keyboard_text['inline_keyboard'][] = [['text' => $textbotlang['Admin']['Help']['categoriesBtn'], 'callback_data' => "help_disp_cat:{$lang}:bt", 'style' => 'primary']];
            $keyboard_text['inline_keyboard'][] = [['text' => $textbotlang['Admin']['Help']['tutorialsBtn'], 'callback_data' => "help_disp_tut:{$lang}:bt", 'style' => 'primary']];
        }
        if ($groupFilter === 'usermgmt') {
            // the reply button is not an item of its own (its look is owned by
            // the admin's message) - listed with the other buttons, as it is
            $bt_ma = genbtn_row_label('users.support.messageFromAdminAlt', $lang);
            $bt_ma_custom = is_array($bt_list_be) && !empty($bt_list_be[$lang]['users.support.messageFromAdminAlt']);
            $bt_ma_btn = ['text' => $bt_ma[0] . ($bt_ma_custom ? ' 🔘' : ''), 'callback_data' => "gbs|hub|{$lang}|ma", 'style' => $bt_ma_custom ? 'success' : 'primary'];
            if ($bt_ma[1] !== '') {
                $bt_ma_btn['icon_custom_emoji_id'] = $bt_ma[1];
            }
            $keyboard_text['inline_keyboard'][] = [$bt_ma_btn];
        }
        $keyboard_text['inline_keyboard'][] = [['text' => $textbotlang['bottext']['resetAllLabel'], 'callback_data' => "bt_group_resetall|$lang|$groupFilter", 'style' => 'danger']];
        // a submenu of another group needs one step back to its parent - the
        // shared "برگشت به لیست" row below jumps all the way out to the home list
        if ($groupFilter === 'topupdisc') {
            $keyboard_text['inline_keyboard'][] = [['text' => '🔙 بازگشت به منوی قبل', 'callback_data' => "bt_group|$lang|topup", 'style' => 'danger']];
        }
        // ⏱'s sub-screens go back to its index only - the index has the way out
        if (isset($bt_payg_subs[$groupFilter])) {
            $keyboard_text['inline_keyboard'][] = [['text' => '🔙 بازگشت به منوی قبل', 'callback_data' => "bt_group|$lang|payg", 'style' => 'danger']];
        } else {
            // 📲's group is opened from the five places that show it: back there
            global $from_id;
            $bt_sa_from = $groupFilter === 'subapps' ? bt_origin_get($from_id, "bt_group|$lang|subapps") : null;
            if ($bt_sa_from !== null) {
                $keyboard_text['inline_keyboard'][] = [['text' => '🔙 بازگشت به منوی قبل', 'callback_data' => $bt_sa_from, 'style' => 'danger']];
            }
            // 🧩's screens go back up the same way: the pieces to the screens, those to 🛒
            if ($groupFilter === 'cvol' || $groupFilter === 'cvolpieces') {
                $keyboard_text['inline_keyboard'][] = [['text' => '🔙 بازگشت به منوی قبل', 'callback_data' => "bt_group|$lang|" . ($groupFilter === 'cvol' ? 'buyflow' : 'cvol'), 'style' => 'danger']];
            }
            // the service actions' messages are a page of 🛍 سرویس‌های من
            if ($groupFilter === 'msmore') {
                $keyboard_text['inline_keyboard'][] = [['text' => '🔙 بازگشت به منوی قبل', 'callback_data' => "bt_group|$lang|myservices", 'style' => 'danger']];
            }
            // 👤's screen goes back to 👤 مدیریت کاربر, on this same tab
            $keyboard_text['inline_keyboard'][] = $groupFilter === 'usermgmt'
                ? [['text' => '🔙 بازگشت به مدیریت کاربر', 'callback_data' => "umlang:$lang", 'style' => 'danger']]
                : [['text' => $textbotlang['bottext']['backToListLabel'], 'callback_data' => "btact|back|$lang", 'style' => 'danger']];
        }
        $keyboard_text['inline_keyboard'][] = [['text' => $textbotlang['bottext']['btn_close'], 'callback_data' => 'bt_close', 'style' => 'danger']];
        $bt_captionKey = [
            'myservices' => 'groupServicesCaption',
            'subapps' => 'groupSubappsCaption',
            'topup' => 'groupTopupCaption',
            'topupdisc' => 'groupTopupDiscCaption',
            'account' => 'groupAccountCaption',
            'help' => 'groupHelpCaption',
            'verify' => 'groupVerifyCaption',
            'wheel' => 'groupWheelCaption',
            'referral' => 'groupReferralCaption',
            'usermgmt' => 'groupUserMgmtCaption',
            'payg' => 'groupPaygCaption',
            'misc' => 'groupMiscCaption',
            'msmore' => 'groupMsMoreCaption',
            'cvol' => 'groupCvolCaption',
            'cvolpieces' => 'groupCvolPiecesCaption',
        ][$groupFilter] ?? 'groupBuyflowCaption';
        $bt_caption_tpl = isset($bt_payg_subs[$groupFilter]) ? $bt_payg_subs[$groupFilter]['caption'] : $textbotlang['bottext'][$bt_captionKey];
        $bt_caption = strtr($bt_caption_tpl, ['{lang}' => $textbotlang['bottext']['langs'][$lang] ?? $lang]);
        // the card-to-card previews moved onto that gateway's own screen, next
        // to the buttons that actually edit them
        return [$bt_caption, json_encode($keyboard_text)];
    }
    // items with a genuinely separate purpose get grouped under a white,
    // non-navigating section label (bt_sep|) - mirrors the exact pattern
    // already used inside the 🛒 پیام‌های مراحل خرید group submenu. Defined
    // here (not as a bottext.items 'section' tag) so this stays self-contained
    // and independent of the underlying array's storage order.
    $bt_home_sections = [
        // users.back sits next to the welcome text on purpose: they are the two
        // messages the main-menu keyboard arrives with
        // textbot.channel joins them: it is an independent bot message like the
        // rest of this section, and with nowhere of its own it used to fall
        // through to the bottom of the screen, under whatever heading happened
        // to be last
        'home_general' => ['users.text_start', 'users.back', 'textbot.faqDesc', 'textbot.tariffListDesc', 'textbot.rules', 'textbot.channel', 'textbot.botOff'],
        // preInvoice/afterPay used to be listed directly here; they now live
        // inside the 🛒 پیام‌های مراحل خرید group itself (section
        // 'preinvoice_afterpay') - this divider now only leads into the two
        // ready-made submenus below, so its item list stays empty
        'home_purchase' => [],
        'home_usertest' => ['users.usertest.selectUsernamePrompt'],
        // ⏱ اکانت ساعتی: a heading of its own under the test account, its one
        // row (the ⏱ group) drawn below
        'home_payg' => [],
        // two screens that had nothing of their own here: the account page's
        // caption and close button used to fall through to the ungrouped
        // leftovers at the bottom, and the tutorial section had no row at all
        'home_account' => [],
        'home_help' => [],
        // the three features that 🌐 وضعیت قابلیت‌ها (هر زبان) switches on and
        // off - their messages had no row here at all until now, so they were
        // the only customer-facing flows with no way to reword them
        'home_features' => [],
    ];
    $bt_home_sectioned_keys = array_merge(...array_values($bt_home_sections));
    foreach ($bt_home_sections as $bt_sec_key => $bt_sec_items) {
        $keyboard_text['inline_keyboard'][] = [['text' => bt_section_meta($bt_sec_key)['label'], 'callback_data' => "bt_sep|{$bt_sec_key}"]];
        foreach ($bt_sec_items as $bt_item_key) {
            $bt_data = null;
            foreach ($keyboard_list_text as $d) {
                if (($d['key'] ?? '') === $bt_item_key) {
                    $bt_data = $d;
                    break;
                }
            }
            if ($bt_data === null) {
                continue;
            }
            list($bt_label, $bt_style) = $bt_decorate($bt_data['key'], $bt_data['label']);
            // blue by default (home list), green once customized
            $bt_btn = ['text' => $bt_label, 'callback_data' => "bt_edit|$lang|{$bt_data['key']}", 'style' => ($bt_style !== '' ? $bt_style : 'primary')];
            $keyboard_text['inline_keyboard'][] = [$bt_btn];
        }
        if ($bt_sec_key === 'home_purchase') {
            // the two ready-made submenus belong in this section - each row
            // has to answer for its own children: an item customized inside
            // the submenu otherwise leaves no trace at all on this screen.
            // Fires once per render regardless of $bt_sec_items (now empty -
            // preInvoice/afterPay moved into the buyflow group itself)
            if (!empty($bt_grouped['buyflow'])) {
                $bt_group_custom = false;
                foreach ($bt_grouped['buyflow'] as $bt_g) {
                    list(, $bt_g_style) = $bt_decorate($bt_g['key'], $bt_g['label']);
                    if ($bt_g_style !== '') {
                        $bt_group_custom = true;
                        break;
                    }
                }
                $keyboard_text['inline_keyboard'][] = [['text' => $textbotlang['bottext']['groupBuyflowLabel'], 'callback_data' => "bt_group|$lang|buyflow", 'style' => 'primary']];
            }
            if (!empty($bt_grouped['myservices'])) {
                $bt_svc_custom = false;
                foreach ($bt_grouped['myservices'] as $bt_g) {
                    list(, $bt_g_style) = $bt_decorate($bt_g['key'], $bt_g['label']);
                    if ($bt_g_style !== '') {
                        $bt_svc_custom = true;
                        break;
                    }
                }
                $keyboard_text['inline_keyboard'][] = [['text' => $textbotlang['bottext']['groupServicesLabel'], 'callback_data' => "bt_group|$lang|myservices", 'style' => 'primary']];
            }
            // 💰 افزایش موجودی gets its own divider + entry directly under the
            // services row: its messages used to sit inside 🛒 مراحل خرید even
            // though topping the wallet up is a separate journey from buying a
            // service, which made that group a mixed bag.
            if (!empty($bt_grouped['topup'])) {
                $keyboard_text['inline_keyboard'][] = [['text' => bt_section_meta('home_topup')['label'], 'callback_data' => 'bt_sep|home_topup']];
                $keyboard_text['inline_keyboard'][] = [['text' => $textbotlang['bottext']['groupTopupLabel'], 'callback_data' => "bt_group|$lang|topup", 'style' => 'primary']];
            }
        }
        if ($bt_sec_key === 'home_payg') {
            // ⏱ اکانت ساعتی's one row (its settings are in 🏬 تنظیمات فروشگاه).
            // Its messages live on its sub-screens (bottext.paygSubs), so the
            // row answers for those
            $bt_pg_items = [];
            foreach (array_keys($textbotlang['bottext']['paygSubs'] ?? []) as $bt_sub) {
                $bt_pg_items = array_merge($bt_pg_items, $bt_grouped[$bt_sub] ?? []);
            }
            if ($bt_pg_items) {
                $bt_pg_custom = false;
                foreach ($bt_pg_items as $bt_g) {
                    list(, $bt_g_style) = $bt_decorate($bt_g['key'], $bt_g['label']);
                    if ($bt_g_style !== '') {
                        $bt_pg_custom = true;
                        break;
                    }
                }
                $keyboard_text['inline_keyboard'][] = [['text' => $textbotlang['bottext']['groupPaygLabel'], 'callback_data' => "bt_group|$lang|payg", 'style' => $bt_pg_custom ? 'success' : 'primary']];
            }
        }
        // 👤 حساب کاربری and 📚 آموزش: one row each into their own submenu,
        // answering for their children exactly like the rows above do.
        // 🎯 قابلیت‌های ربات carries three of them, one per feature.
        foreach ([
            'home_account' => ['account', 'groupAccountLabel'],
            'home_help' => ['help', 'groupHelpLabel'],
            'home_features:verify' => ['verify', 'groupVerifyLabel'],
            'home_features:wheel' => ['wheel', 'groupWheelLabel'],
            'home_features:referral' => ['referral', 'groupReferralLabel'],
        ] as $bt_sec_owner => $bt_sec_group) {
            // one section may own several group rows, so the key carries the
            // section before the ":" and stays unique in this map
            $bt_sec_owner = explode(':', $bt_sec_owner)[0];
            if ($bt_sec_key !== $bt_sec_owner || empty($bt_grouped[$bt_sec_group[0]])) {
                continue;
            }
            $bt_sub_custom = false;
            foreach ($bt_grouped[$bt_sec_group[0]] as $bt_g) {
                list(, $bt_g_style) = $bt_decorate($bt_g['key'], $bt_g['label']);
                if ($bt_g_style !== '') {
                    $bt_sub_custom = true;
                    break;
                }
            }
            $keyboard_text['inline_keyboard'][] = [[
                'text' => $textbotlang['bottext'][$bt_sec_group[1]],
                'callback_data' => "bt_group|$lang|{$bt_sec_group[0]}",
                'style' => $bt_sub_custom ? 'success' : 'primary',
            ]];
        }
        if ($bt_sec_key === 'home_general') {
            // the unknown-message reply sits with the other messages the bot
            // sends on its own, right after the channel join-gate one, instead
            // of alone at the foot of the screen
            list($bt_um_label, $bt_um_style) = $bt_decorate('users.unknownMsg', $bt_unknown_label);
            $keyboard_text['inline_keyboard'][] = [[
                'text' => $bt_um_label,
                'callback_data' => "bt_edit|$lang|users.unknownMsg",
                'style' => ($bt_um_style !== '' ? $bt_um_style : 'primary'),
            ]];
            // ☎️ support, the gift code, the agent request, app downloads and the
            // bot's own error messages: one row into their group
            if (!empty($bt_grouped['misc'])) {
                $bt_misc_custom = false;
                foreach ($bt_grouped['misc'] as $bt_g) {
                    list(, $bt_g_style) = $bt_decorate($bt_g['key'], $bt_g['label']);
                    if ($bt_g_style !== '') {
                        $bt_misc_custom = true;
                        break;
                    }
                }
                $keyboard_text['inline_keyboard'][] = [['text' => $textbotlang['bottext']['groupMiscLabel'], 'callback_data' => "bt_group|$lang|misc", 'style' => $bt_misc_custom ? 'success' : 'primary']];
            }
        }
    }
    // Anything ungrouped that isn't in one of the sections above still needs to
    // render somewhere, or a future item silently vanishes from this screen.
    //
    // It gets a heading of its own now. Without one these rows simply continued
    // the LAST section drawn, so an unrelated message read as a child of
    // whichever heading happened to be last - which is exactly what
    // "📦 پیام بعد از دریافت اکانت تست" looked like under 📚 آموزش. The heading
    // is drawn only when something lands under it, so a tidy registry shows no
    // empty label.
    $bt_leftovers = [];
    foreach ($keyboard_list_text as $data) {
        if (in_array($data['key'], $bt_skip_keys, true) || !empty($data['group']) || (isset($data['label']) && mb_strpos($data['label'], 'دکمه:') === 0) || in_array($data['key'], $bt_home_sectioned_keys, true)) {
            continue;
        }
        list($bt_label, $bt_style) = $bt_decorate($data['key'], $data['label']);
        $bt_leftovers[] = ['text' => $bt_label, 'callback_data' => "bt_edit|$lang|{$data['key']}", 'style' => ($bt_style !== '' ? $bt_style : 'primary')];
    }
    if (!empty($bt_leftovers)) {
        $keyboard_text['inline_keyboard'][] = [['text' => bt_section_meta('home_other')['label'], 'callback_data' => 'bt_sep|home_other']];
        foreach ($bt_leftovers as $bt_btn) {
            $keyboard_text['inline_keyboard'][] = [$bt_btn];
        }
    }
    // relocated here from its old home under ⚙️ تنظیمات عمومی so both sticker
    // systems live under one umbrella - this hub controls the main-menu
    // reply-keyboard buttons themselves (color/emoji/sticker/layout/rename),
    // a different data model from the per-message items above
    $keyboard_text['inline_keyboard'][] = [['text' => bt_section_meta('home_tools')['label'], 'callback_data' => 'bt_sep|home_tools']];
    // carries the tab: this whole tree edits one language's menu, and the tab
    // was already chosen at the top of this screen - asking again inside every
    // editor was a second place to get it wrong
    $keyboard_text['inline_keyboard'][] = [['text' => $textbotlang['bottext']['btnSettingsLabel'], 'callback_data' => "bt_btnsettings|$lang", 'style' => 'primary']];
    // promoted out of the button-settings hub into its own direct row - it
    // controls the end-user language-picker menu, unrelated to button appearance
    $keyboard_text['inline_keyboard'][] = [['text' => $textbotlang['bottext']['langSwitchLabel'], 'callback_data' => 'bt_langswitch', 'style' => 'primary']];
    // Sits with the other side tools now, and says on the button what its own
    // section heading used to say above it. Never green: both switches behind
    // it (admin_test_unlimited, admin_buy_free) are single bot-wide settings
    // with no language of their own - there is one set of admins, not one per
    // language - so colouring it from them made the row look edited on a tab it
    // was never edited on, which is what green means everywhere else here.
    $keyboard_text['inline_keyboard'][] = [['text' => '🛡 دسترسی و محدودیت‌های ادمین', 'callback_data' => "admperm|open|$lang", 'style' => 'primary']];
    // 📲 one setting for the five places that show it: a heading that says so,
    // and the button under it, so no one reads it as a part of just one of them
    if (!empty($bt_grouped['subapps'])) {
        $bt_sa_custom = subapps_customized($lang);
        foreach ($bt_grouped['subapps'] as $bt_g) {
            list(, $bt_g_style) = $bt_decorate($bt_g['key'], $bt_g['label']);
            if ($bt_g_style !== '') {
                $bt_sa_custom = true;
                break;
            }
        }
        $keyboard_text['inline_keyboard'][] = [['text' => bt_section_meta('home_subapps')['label'], 'callback_data' => 'bt_sep|home_subapps']];
        $keyboard_text['inline_keyboard'][] = [['text' => $textbotlang['bottext']['saHomeBtn'], 'callback_data' => "bt_group|$lang|subapps", 'style' => $bt_sa_custom ? 'success' : 'primary']];
    }
    // 🖼 استیکر دکمه بستن moved from here into each section's own ❌ بستن item
    // (خرید اشتراک, افزایش موجودی, ...) - it is per-section now, not one
    // shared bot-wide switch, so a single row here would no longer mean
    // anything coherent.
    $keyboard_text['inline_keyboard'][] = [['text' => bt_section_meta('home_alerts')['label'], 'callback_data' => 'bt_sep|home_alerts']];
    // the warning tiers live in their own column and have no bottext key, so this
    // row does its own check instead of going through $bt_decorate. It asks
    // "is anything customized?", NOT "does a threshold exist?" - a bare threshold
    // is structure, and its own reset button never removes one, so keying the
    // green off the array being non-empty made the row impossible to clear.
    $bt_volpct_btn = ['text' => '🔋 پیام‌های هشدار و اتمام سرویس', 'callback_data' => "volpct|hub|$lang", 'style' => 'primary'];
    // the section also holds the two cron warnings and the test-account
    // ending, and an on/off per message - any of them counts
    if (function_exists('notice_section_customized') && notice_section_customized($lang)) {
        $bt_volpct_btn['style'] = 'success';
    }
    $keyboard_text['inline_keyboard'][] = [$bt_volpct_btn];
    $keyboard_text['inline_keyboard'][] = [['text' => $textbotlang['bottext']['resetAllLabel'], 'callback_data' => "bt_resetall|$lang", 'style' => 'danger']];
    $keyboard_text['inline_keyboard'][] = [['text' => $textbotlang['bottext']['btn_close'], 'callback_data' => 'bt_close', 'style' => 'danger']];
    $bt_caption_tpl = $textbotlang['bottext']['home_text'];
    $bt_caption = strtr($bt_caption_tpl, ['{lang}' => $textbotlang['bottext']['langs'][$lang] ?? $lang]);
    return [$bt_caption, json_encode($keyboard_text)];
}

//----------------[  normalize custom-emoji button text  ]----------------
// if user taps a reply button whose text was customized with an emoji,
// rewrite $text back to the default label so bot matching keeps working
if (!empty($text) && !empty($keyboardRows)) {
    foreach ($keyboardRows as $ne_row) {
        if (!is_array($ne_row)) {
            continue;
        }
        foreach ($ne_row as $ne_btn) {
            if (is_array($ne_btn) && isset($ne_btn['text'], $replacements[$ne_btn['text']])) {
                if (!empty($ne_btn['custom_text'])) {
                    $ne_base = $ne_btn['custom_text'];
                    if (!empty($ne_btn['emoji'])) {
                        if ($global_emoji_pos === 'left') {
                            $ne_base = strip_leading_emoji($ne_btn['custom_text']) . ' ' . $ne_btn['emoji'];
                        } else {
                            $ne_base = $ne_btn['emoji'] . ' ' . strip_leading_emoji($ne_btn['custom_text']);
                        }
                    } elseif (!empty($ne_btn['icon_emoji'])) {
                        $ne_base = strip_leading_emoji($ne_btn['custom_text']);
                    } else {
                        list($ne_ct_emoji, $ne_ct_rest) = split_leading_emoji($ne_btn['custom_text']);
                        if ($ne_ct_emoji !== '') {
                            $ne_base = ($global_emoji_pos === 'left') ? $ne_ct_rest . ' ' . $ne_ct_emoji : $ne_ct_emoji . ' ' . $ne_ct_rest;
                        }
                    }
                    if ($text === $ne_base) {
                        $text = $replacements[$ne_btn['text']];
                        break 2;
                    }
                }
                if (!empty($ne_btn['emoji'])) {
                    if ($global_emoji_pos === 'left') {
                        $ne_constructed = strip_leading_emoji($replacements[$ne_btn['text']]) . ' ' . $ne_btn['emoji'];
                    } else {
                        $ne_constructed = $ne_btn['emoji'] . ' ' . strip_leading_emoji($replacements[$ne_btn['text']]);
                    }
                    if ($text === $ne_constructed) {
                        $text = $replacements[$ne_btn['text']];
                        break 2;
                    }
                }
                if (!empty($ne_btn['icon_emoji'])) {
                    $ne_constructed = strip_leading_emoji($replacements[$ne_btn['text']]);
                    if ($text === $ne_constructed) {
                        $text = $replacements[$ne_btn['text']];
                        break 2;
                    }
                }
                list($ne_def_emoji, $ne_def_rest) = split_leading_emoji($replacements[$ne_btn['text']]);
                if ($ne_def_emoji !== '') {
                    $ne_repos = ($global_emoji_pos === 'left') ? $ne_def_rest . ' ' . $ne_def_emoji : $ne_def_emoji . ' ' . $ne_def_rest;
                    if ($text === $ne_repos) {
                        $text = $replacements[$ne_btn['text']];
                        break 2;
                    }
                }
                if ($text === strip_leading_emoji($replacements[$ne_btn['text']])) {
                    $text = $replacements[$ne_btn['text']];
                    break 2;
                }
            }
        }
    }
}

//----------------[  button premium sticker sender  ]----------------
// when a user taps a main-menu button, send its custom sticker before the response
$sticker_callback_map = [
    'buy' => 'text_sell',
    'buyfresh' => 'text_sell',
    'account' => 'accountwallet',
    'Add_Balance' => 'addbalance',
    'Add_Balance_ac' => 'addbalance',
    'Tariff_list' => 'text_Tariff_list',
    'wheel_luck' => 'text_wheel_luck',
    'affiliatesbtn' => 'text_affiliates',
    'extendbtn' => 'text_extend',
    'supportbtns' => 'text_support',
    'helpbtns' => 'text_help',
    'usertestbtn' => 'text_usertest',
    'change_language' => 'text_change_language',
];
// a button under a message from 👤 مدیریت کاربر with a sticker of its own on
// this tab sends that one - and then not the main menu's for the same action
$bcb_sent = false;
if (!empty($bcb_tap) && function_exists('genbtn_tap_sticker') && function_exists('telegram')) {
    $bcb_key = genbtn_alias_to_key($bcb_tap);
    $bcb_file = $bcb_key !== null ? genbtn_tap_sticker(in_array($users['lang'] ?? '', panel_langs(), true) ? $users['lang'] : 'fa', $bcb_key) : '';
    if ($bcb_file !== '') {
        $bcb_res = sticker_value_send($from_id, $bcb_file);
        $bcb_sent = true;
        // the screen it opens can take it away with its own ❌ بستن
        $bcb_msId = (int) ($bcb_res['result']['message_id'] ?? 0);
        update("user", "menu_sticker_id", $bcb_msId > 0 ? (string) $bcb_msId : "0", "id", $from_id);
    }
}
$sticker_btn_key = null;
$menu_tap_from_text = false;
if (!empty($datain) && isset($sticker_callback_map[$datain]) && !$bcb_sent) {
    $sticker_btn_key = $sticker_callback_map[$datain];
} elseif (!empty($text)) {
    $text_stripped = strip_leading_emoji($text);
    foreach ($replacements as $rep_key => $rep_val) {
        if ($text === $rep_val || $text_stripped === strip_leading_emoji($rep_val)) {
            $sticker_btn_key = $rep_key;
            $menu_tap_from_text = true;
            break;
        }
    }
}
// Tapping a main-menu button on the reply keyboard posts the label itself as a
// message FROM the user, which then sits in the chat forever. Remember its id so
// the screen it opened can take it away from its own close button, together with
// the caption and the sticker. Callback taps are deliberately excluded: there
// $message_id is the bot's own glass main menu, which has to survive.
if ($menu_tap_from_text) {
    $mt_msId = (int) ($message_id ?? 0);
    // the section is stored with the id ("123:text_help") so a later glass tap
    // can tell "same section, keep it" from "different section, drop it"
    update("user", "menu_tap_id", $mt_msId > 0 ? $mt_msId . ':' . $sticker_btn_key : "0", "id", $from_id);
} elseif ($sticker_btn_key !== null) {
    // a glass tap that RE-OPENS the same section (📚 آموزش -> a category ->
    // بازگشت, which comes back as helpbtns) still shows the screen that tap
    // message opened, so its id has to survive - dropping it here is what left
    // the user's own "📚 آموزش" message behind when ❌ بستن was finally tapped.
    // A glass tap into a DIFFERENT section still drops it, as before.
    // $users, not $user: index.php loads $user only AFTER requiring this file
    $mt_prev = explode(':', (string) ($users['menu_tap_id'] ?? ''), 2);
    if (($mt_prev[1] ?? '') !== $sticker_btn_key) {
        update("user", "menu_tap_id", "0", "id", $from_id);
    }
}
if ($sticker_btn_key !== null) {
    // only the sticker sent on THIS tap may be closed later: a button with no
    // sticker of its own would otherwise inherit the previous one's id and its
    // ❌ بستن would delete a sticker belonging to an older screen
    update("user", "menu_sticker_id", "0", "id", $from_id);
}
if ($sticker_btn_key === 'text_sell') {
    // a fresh buy attempt starts clean - never let a leftover id from an
    // earlier purchase get mistaken for this run's sticker by KeyboardCategory()
    update("user", "Processing_value_tow", "", "id", $from_id);
}
// 👥 زیرمجموعه‌گیری: its sticker comes first only when one of its two kinds
// is on for this customer; with neither, the «nothing is active» message
// goes out alone - and so does 🎁's «used up» one, with its own sticker
$st_wait = $sticker_btn_key === 'text_affiliates' && function_exists('aff_any_on')
    && (!aff_any_on($users['lang'] ?? 'fa') || aff_tap_sticker_yields($from_id, $users['lang'] ?? 'fa'));
if ($sticker_btn_key !== null && !$st_wait && !empty($keyboardRows) && function_exists('telegram')) {
    foreach ($keyboardRows as $st_row) {
        if (!is_array($st_row)) {
            continue;
        }
        foreach ($st_row as $st_btn) {
            // its own sticker, or the one it ships with (mainmenu_default_stickers)
            $st_file = is_array($st_btn) ? mainmenu_btn_sticker($st_btn) : '';
            if (is_array($st_btn) && ($st_btn['text'] ?? '') === $sticker_btn_key && $st_file !== '') {
                $st_sent = sticker_value_send($from_id, $st_file);
                // stashed so the location_ handler in index.php can delete this
                // sticker once a panel is picked and the next screen replaces the
                // panel list - only for the buy button, never other main-menu ones
                if ($sticker_btn_key === 'text_sell') {
                    $st_stickerId = (int) ($st_sent['result']['message_id'] ?? 0);
                    if ($st_stickerId > 0) {
                        update("user", "Processing_value_tow", (string) $st_stickerId, "id", $from_id);
                    }
                }
                // every main-menu sticker is remembered here so whichever screen
                // it accompanied can remove it from its own ❌ بستن button. Kept
                // in a dedicated column rather than the heavily-overloaded
                // Processing_value_tow, which other flows re-purpose mid-request.
                $st_msId = (int) ($st_sent['result']['message_id'] ?? 0);
                update("user", "menu_sticker_id", $st_msId > 0 ? (string) $st_msId : "0", "id", $from_id);
                break 2;
            }
        }
    }
}

// ---- panel settings menu: flat definitions above are kept as the source of
// truth for the submenus; the $option* variables that ~50 call sites already
// send now carry the grouped top-level menu instead of one long flat list ----
$panel_menu_flat = [
    'marzban' => $optionMarzban,
    'rebecca' => $optionrebecca,
    'remnawave' => $optionremnawave,
    'ibsng' => $optionibsng,
    'mikrotik' => $option_mikrotik,
    's_ui' => $options_ui,
    'WGDashboard' => $optionwg,
    'marzneshin' => $optionmarzneshin,
    'Manualsale' => $optionManualsale,
    'x-ui_single' => $optionX_ui_single,
    'alireza_single' => $optionalireza_single,
    'hiddify' => $optionhiddfy,
    'ciro_agent' => $option_ciro,
];
$panel_menu_top = [];
foreach ($panel_menu_flat as $__pmtype => $__pmflat) {
    $panel_menu_top[$__pmtype] = panel_menu_top_json($__pmflat, $textbotlang);
}
$optionMarzban = $panel_menu_top['marzban'];
$optionrebecca = $panel_menu_top['rebecca'];
$optionremnawave = $panel_menu_top['remnawave'];
$optionibsng = $panel_menu_top['ibsng'];
$option_mikrotik = $panel_menu_top['mikrotik'];
$options_ui = $panel_menu_top['s_ui'];
$optionwg = $panel_menu_top['WGDashboard'];
$optionmarzneshin = $panel_menu_top['marzneshin'];
$optionManualsale = $panel_menu_top['Manualsale'];
$optionX_ui_single = $panel_menu_top['x-ui_single'];
$optionalireza_single = $panel_menu_top['alireza_single'];
$optionhiddfy = $panel_menu_top['hiddify'];
$option_ciro = $panel_menu_top['ciro_agent'];
// If the admin is currently sitting inside one of the panel submenus, every
// "action finished" message should land them back in that submenu instead of
// bouncing them out to the top menu. The ~50 call sites all send one of the
// $option* variables, so overriding them here covers every one of them without
// touching a single call site. Opening a panel afresh resets this (admin.php,
// the GetLocationEdit branch).
if (!empty($user['panel_submenu']) && !empty($user['Processing_value'])) {
    $__pmpanel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if (!empty($__pmpanel)) {
        $__pmtype2 = $__pmpanel['type'] ?? 'marzban';
        $__pmflat2 = $panel_menu_flat[$__pmtype2] ?? $panel_menu_flat['marzban'];
        $__pmsub = panel_menu_submenu_json($__pmflat2, $user['panel_submenu'], $textbotlang);
        if ($__pmsub !== null) {
            $optionMarzban = $__pmsub;
            $optionrebecca = $__pmsub;
            $optionibsng = $__pmsub;
            $option_mikrotik = $__pmsub;
            $options_ui = $__pmsub;
            $optionwg = $__pmsub;
            $optionmarzneshin = $__pmsub;
            $optionManualsale = $__pmsub;
            $optionX_ui_single = $__pmsub;
            $optionalireza_single = $__pmsub;
            $optionhiddfy = $__pmsub;
            $option_ciro = $__pmsub;
            $optionremnawave = $__pmsub;
        }
    }
}
