<?php
// The newer customer features - 📨 تیکت‌های من (tickets.php), 🔁 تمدید خودکار
// (autorenew.php), 💌 پیام برگشت مشتری (winback.php) and 💸 انتقال موجودی
// (wtransfer.php) - in the two places every feature has to be:
//
//   - 🎨: their buttons are 🎨 button items like ❌ بستن (rename, colour, emoji,
//     premium emoji, hide), through the hooks in genbtn_alias_map(),
//     genbtn_defs(), genbtn_hideable(), bt_btnitem_keys() and
//     bt_btnitem_back_cb(). Their texts are ordinary 🎨 items (lang/fa.php,
//     bottext.items).
//   - 🌐 وضعیت قابلیت‌ها (هر زبان): one row each - on/off for this language (all
//     off until the admin turns them on) and ⚙️ for the ones with settings.
//
// Settings live in setting.feature_lang_settings (feature_setting_value()),
// per language.

if (!function_exists('xf_button_registry')) {
    // alias => [🎨 key, admin name, path of its default text, default colour, 🎨 group, never hidden]
    function xf_button_registry()
    {
        return [
            // 📨 تیکت‌های من
            'x1' => ['users.support.tkMyBtn', '📨 دکمهٔ «تیکت‌های من» (صفحهٔ پشتیبانی)', ['users', 'support', 'tkMyBtn'], 'primary', 'misc', false],
            'x2' => ['users.support.tkWriteBtn', '✍️ دکمهٔ «ارسال پیام در همین تیکت»', ['users', 'support', 'tkWriteBtn'], 'success', 'misc', false],
            'x3' => ['users.support.tkCloseBtn', '🔒 دکمهٔ «بستن تیکت»', ['users', 'support', 'tkCloseBtn'], 'danger', 'misc', false],
            'x4' => ['users.support.tkBackBtn', '🔙 دکمهٔ «برگشت به تیکت‌ها»', ['users', 'support', 'tkBackBtn'], 'danger', 'misc', true],
            'x5' => ['users.support.tkBackSupportBtn', '🔙 دکمهٔ برگشت از لیست تیکت‌ها', ['users', 'support', 'tkBackSupportBtn'], 'danger', 'misc', true],
            'x6' => ['users.support.tkOpenBtn', '📨 دکمهٔ «دیدن تیکت» (زیر جواب پشتیبانی)', ['users', 'support', 'tkOpenBtn'], 'primary', 'misc', false],
            // 🔁 تمدید خودکار
            'x7' => ['users.autorenew.btnOn', '🔁 دکمهٔ تمدید خودکار وقتی روشنه (صفحهٔ سرویس)', ['users', 'autorenew', 'btnOn'], 'success', 'msmore', false],
            'x8' => ['users.autorenew.btnOff', '🔁 دکمهٔ تمدید خودکار وقتی خاموشه (صفحهٔ سرویس)', ['users', 'autorenew', 'btnOff'], 'danger', 'msmore', false],
            'x9' => ['users.autorenew.btnTopup', '💰 دکمهٔ «افزایش موجودی» (زیر پیام موجودی کم برای تمدید خودکار)', ['users', 'autorenew', 'btnTopup'], 'success', 'msmore', false],
            // 💌 پیام برگشت مشتری
            'xa' => ['users.winback.btnBuy', '🛍 دکمهٔ «خرید اشتراک» (زیر پیام برگشت مشتری)', ['users', 'winback', 'btnBuy'], 'success', 'msmore', false],
            // 💸 انتقال موجودی
            'xb' => ['users.wtransfer.entryBtn', '💸 دکمهٔ «انتقال موجودی» (حساب کاربری)', ['users', 'wtransfer', 'entryBtn'], 'primary', 'account', false],
            'xc' => ['users.wtransfer.confirmBtn', '✅ دکمهٔ تایید انتقال', ['users', 'wtransfer', 'confirmBtn'], 'success', 'account', false],
            'xd' => ['users.wtransfer.cancelBtn', '❌ دکمهٔ انصراف از انتقال', ['users', 'wtransfer', 'cancelBtn'], 'danger', 'account', true],
        ];
    }
    // the 🎨 sections they add (bt_section_meta())
    function xf_section_meta()
    {
        return [
            'ms_autorenew' => [
                'label' => '🔁 تمدید خودکار',
                'alert' => 'دکمهٔ تمدید خودکار توی صفحهٔ سرویس، و پیام‌هایی که بعد از تمدید خودکار یا وقتی موجودی کافی نیست به مشتری می‌رسه.',
            ],
            'ms_winback' => [
                'label' => '💌 پیام برگشت مشتری',
                'alert' => 'پیامی که چند روز بعد از تموم شدن سرویس، با یه کد تخفیف برای مشتری فرستاده می‌شه، و دکمهٔ زیرش.',
            ],
            'account_wtransfer' => [
                'label' => '💸 انتقال موجودی',
                'alert' => 'دکمهٔ «انتقال موجودی» توی حساب کاربری و پیام‌های مراحل انتقال: آیدی گیرنده، مبلغ، تایید، و پیامی که به گیرنده می‌رسه.',
            ],
        ];
    }
    function xf_genbtn_aliases()
    {
        return array_map(fn($r) => $r[0], xf_button_registry());
    }
    function xf_btnitem_keys()
    {
        return array_values(xf_genbtn_aliases());
    }
    function xf_btnitem_groups()
    {
        $out = [];
        foreach (xf_button_registry() as $r) {
            $out[$r[0]] = $r[4];
        }
        return $out;
    }
    function xf_genbtn_nohide($alias)
    {
        $r = xf_button_registry()[$alias] ?? null;
        return $r !== null && $r[5];
    }
    function xf_genbtn_defs($alias, $textbotlang)
    {
        $r = xf_button_registry()[$alias] ?? null;
        if ($r === null) {
            return null;
        }
        $text = $textbotlang;
        foreach ($r[2] as $k) {
            $text = is_array($text) ? ($text[$k] ?? null) : null;
        }
        if (!is_string($text)) {
            $text = lang_tab_texts('fa');
            foreach ($r[2] as $k) {
                $text = is_array($text) ? ($text[$k] ?? '') : '';
            }
        }
        return [0 => ['name' => $r[1], 'text' => (string) $text, 'style' => $r[3], 'callback_data' => 'none']];
    }

    // ---- the switches in 🌐 وضعیت قابلیت‌ها (هر زبان) ----

    // id => [setting key, Admin texts section, settings hub callback or '']
    function xf_features()
    {
        return [
            'tk' => ['tk_on', 'Tickets', ''],
            'ar' => ['ar_on', 'AutoRenew', 'arh|hub|%s'],
            'wb' => ['wb_on', 'WinBack', 'wbh|hub|%s'],
            'wt' => ['wt_on', 'WalletTransfer', 'wth|hub|%s'],
        ];
    }
    function xf_on($feat, $lang)
    {
        $f = xf_features()[$feat] ?? null;
        return $f !== null && (string) feature_setting_value($f[0], $lang, '0') === '1';
    }
    // the rows of this language's 🌐 screen
    function xf_rows($lang)
    {
        $tx = panel_texts();
        $on = $tx['Admin']['Status']['statuson'];
        $off = $tx['Admin']['Status']['statusoff'];
        $rows = [];
        foreach (xf_features() as $id => $f) {
            $isOn = xf_on($id, $lang);
            $row = [];
            if ($f[2] !== '') {
                $row[] = ['text' => $tx['keyboard']['settings'], 'callback_data' => sprintf($f[2], $lang)];
            }
            $row[] = ['text' => $isOn ? $on : $off, 'callback_data' => "xf|tog|{$lang}|{$id}", 'style' => $isOn ? 'success' : 'danger'];
            $row[] = ['text' => $tx['Admin'][$f[1]]['rowLabel'], 'callback_data' => "xf|info|{$lang}|{$id}"];
            $rows[] = $row;
        }
        return $rows;
    }
    // the screen's taps: true when handled
    function xf_admin_dispatch()
    {
        global $from_id, $datain, $message_id, $textbotlang, $adminrulecheck, $callback_query_id;
        if (!preg_match('/^xf\|(tog|info)\|([a-z]{2})\|([a-z]{2})$/', (string) $datain, $m)) {
            return false;
        }
        if (($adminrulecheck['rule'] ?? '') !== 'administrator') {
            return false;
        }
        [, $what, $lang, $id] = $m;
        $f = xf_features()[$id] ?? null;
        if ($f === null || !in_array($lang, panel_langs(), true)) {
            return true;
        }
        if ($what === 'info') {
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => panel_texts()['Admin'][$f[1]]['info'], 'show_alert' => true]);
            return true;
        }
        feature_setting_set($f[0], $lang, xf_on($id, $lang) ? '0' : '1');
        telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 1]);
        Editmessagetext($from_id, $message_id, feature_status_lang_caption($textbotlang, $lang), feature_status_lang_payload($textbotlang, $lang));
        return true;
    }
}
