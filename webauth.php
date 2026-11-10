<?php
// 🔐 کد تایید تلگرامی برای ورود به پنل وب - the web panel holds the money and
// every customer's data; a password alone opened it. With this on, a right
// password is not enough: a 6-digit code goes to the admin's own Telegram
// (from this bot) and the panel opens only once it is typed.
//
// Off by default, for the whole bot (not per language - it guards the admin,
// not a customer): «پنل تحت وب ربات 🤖» in the bot, the security tab of the
// web panel's settings. The code: random, 5 minutes, 5 tries, kept only as a
// hash in the login's own session; a wrong 5th try or an old code goes back
// to the password. The message names the IP that asked, so an admin who did
// not ask knows his password is out.

if (!function_exists('web2fa_on')) {
    function web2fa_on()
    {
        return (string) feature_setting_value('web2fa', 'all', '0') === '1';
    }
    function web2fa_set($on)
    {
        feature_setting_set('web2fa', 'all', $on ? '1' : '0');
    }
    // a new code for this admin, sent from the bot: true when Telegram took it
    function web2fa_start(array $admin, $remember, $storedHash, $ip)
    {
        if (!function_exists('telegram')) {
            require_once __DIR__ . '/botapi.php';
        }
        $chat = trim((string) ($admin['id_admin'] ?? ''));
        if ($chat === '' || !preg_match('/^-?\d+$/', $chat)) {
            return false;
        }
        $code = (string) random_int(100000, 999999);
        $t = panel_texts()['Admin']['webpanel'];
        $r = telegram('sendmessage', [
            'chat_id' => $chat,
            'text' => strtr($t['codeMsg'], ['{code}' => $code, '{ip}' => htmlspecialchars((string) $ip), '{time}' => jdate('Y/m/d H:i:s')]),
            'parse_mode' => 'HTML',
        ]);
        if (!is_array($r) || empty($r['ok'])) {
            return false;
        }
        $_SESSION['web2fa'] = [
            'user' => (string) $admin['username'],
            'hash' => password_hash($code, PASSWORD_DEFAULT),
            'exp' => time() + 300,
            'tries' => 0,
            'remember' => (bool) $remember,
            'stored' => (string) $storedHash,
            'ip' => (string) $ip,
        ];
        return true;
    }
    // the code typed: 'ok', 'wrong' (tries left), 'gone' (back to the password)
    function web2fa_check($typed, $ip)
    {
        $p = $_SESSION['web2fa'] ?? null;
        if (!is_array($p) || time() > (int) $p['exp'] || (string) $p['ip'] !== (string) $ip) {
            unset($_SESSION['web2fa']);
            return 'gone';
        }
        $typed = strtr(trim((string) $typed), ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']);
        if (preg_match('/^\d{6}$/', $typed) && password_verify($typed, (string) $p['hash'])) {
            return 'ok';
        }
        $_SESSION['web2fa']['tries'] = (int) $p['tries'] + 1;
        if ($_SESSION['web2fa']['tries'] >= 5) {
            unset($_SESSION['web2fa']);
            return 'gone';
        }
        return 'wrong';
    }
    function web2fa_pending()
    {
        $p = $_SESSION['web2fa'] ?? null;
        return is_array($p) && time() <= (int) $p['exp'];
    }
}
