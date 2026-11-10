<?php
// php tap.php <user_id> text "<text>" | cb "<data>" [msg_id] | photo "<caption>"
// Sends one update to the rig's bot and prints the Telegram calls it made.
// Exit code 1 when the bot did not answer 200 or made no call at all.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
[$_, $uid, $kind] = $argv;
$arg = $argv[3] ?? '';
$mid = (int) ($argv[4] ?? 5);
$dir = getenv('RIG_DIR') ?: __DIR__;
$log = getenv('RIG_LOG') ?: $dir . '/tg_calls.jsonl';
$before = is_file($log) ? count(file($log)) : 0;
$uidf = $dir . '/update_id.txt';
$upd = (int) @file_get_contents($uidf) + 1;
file_put_contents($uidf, $upd);
$from = ['id' => (int) $uid, 'is_bot' => false, 'first_name' => 'U' . $uid, 'username' => 'user' . $uid, 'language_code' => 'fa'];
$chat = ['id' => (int) $uid, 'type' => 'private'];
if ($kind === 'cb') {
    $u = ['update_id' => $upd, 'callback_query' => ['id' => 'cq' . $upd, 'from' => $from, 'data' => $arg, 'message' => ['message_id' => $mid, 'chat' => $chat, 'date' => time(), 'text' => 'x']]];
} else {
    $msg = ['message_id' => 100 + $upd, 'from' => $from, 'chat' => $chat, 'date' => time()];
    if ($kind === 'text') {
        $msg['text'] = $arg;
    } elseif ($kind === 'photo') {
        $msg['photo'] = [['file_id' => 'PHOTO_small', 'width' => 90], ['file_id' => 'PHOTO_big_' . $upd, 'width' => 800]];
        if ($arg !== '') {
            $msg['caption'] = $arg;
        }
    }
    $u = ['update_id' => $upd, 'message' => $msg];
}
$pdo = new PDO('mysql:host=' . (getenv('DB_HOST') ?: '127.0.0.1') . ';dbname=' . (getenv('DB_NAME') ?: 'ciro_rig') . ';charset=utf8mb4', getenv('DB_USER') ?: 'rig', getenv('DB_PASS') ?: 'rigpass');
$secret = (string) $pdo->query("SELECT webhook_secret FROM setting LIMIT 1")->fetchColumn();
$ch = curl_init('http://127.0.0.1:' . (getenv('BOT_PORT') ?: '18080') . '/index.php');
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($u), CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '', CURLOPT_TIMEOUT => 60,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Telegram-Bot-Api-Secret-Token: ' . $secret]]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$lines = is_file($log) ? array_slice(file($log), $before) : [];
foreach ($lines as $l) {
    $c = json_decode($l, true);
    $p = $c['p'];
    $t = preg_replace('/\s+/u', ' ', strip_tags((string) ($p['text'] ?? $p['caption'] ?? '')));
    echo "  → {$c['m']} chat=" . ($p['chat_id'] ?? '-') . ': ' . mb_substr($t, 0, 140) . "\n";
}
if ($code != 200 || !$lines) {
    echo "  ✗ HTTP $code, " . count($lines) . " Telegram calls" . (trim((string) $body) !== '' ? ': ' . substr((string) $body, 0, 300) : '') . "\n";
    exit(1);
}
