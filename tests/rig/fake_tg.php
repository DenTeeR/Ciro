<?php
// A stand-in for the Telegram Bot API (tests/rig/run.sh points the bot at it):
// logs every call as one JSON line to $RIG_LOG and answers the way Telegram would.
// Only on the rig: run.sh starts it with RIG_LOG set; on a bot's server (the
// updater copies tests/ too) it answers nothing.
$log = getenv('RIG_LOG');
if (!$log) {
    http_response_code(404);
    exit;
}
preg_match('#/bot[^/]+/([A-Za-z]+)#', $_SERVER['REQUEST_URI'], $m);
$method = $m[1] ?? 'unknown';
$params = $_POST ?: [];
if (!$params) {
    $j = json_decode((string) file_get_contents('php://input'), true);
    $params = is_array($j) ? $j : [];
}
$ctr = dirname($log) . '/tg_msgid.txt';
$id = (int) @file_get_contents($ctr) + 1;
file_put_contents($ctr, $id);
file_put_contents($log, json_encode(['m' => $method, 'p' => $params], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
header('Content-Type: application/json');
$chat = $params['chat_id'] ?? 0;
switch (strtolower($method)) {
    case 'getme': $r = ['id' => 999, 'is_bot' => true, 'first_name' => 'RigBot', 'username' => 'rigbot']; break;
    case 'getchatmember': $r = ['status' => 'member', 'user' => ['id' => (int) ($params['user_id'] ?? 0)]]; break;
    case 'getchat': $r = ['id' => (int) $chat, 'type' => 'private', 'first_name' => 'User']; break;
    case 'getfile': $r = ['file_id' => $params['file_id'] ?? '', 'file_path' => 'photos/x.jpg']; break;
    case 'answercallbackquery': case 'setwebhook': case 'deletemessage': case 'setmessagereaction':
    case 'setmycommands': case 'sendchataction': case 'editmessagereplymarkup': $r = true; break;
    case 'getwebhookinfo': $r = ['url' => '', 'pending_update_count' => 0]; break;
    case 'createforumtopic': $r = ['message_thread_id' => $id, 'name' => $params['name'] ?? '']; break;
    default: $r = ['message_id' => $id, 'date' => time(), 'chat' => ['id' => (int) $chat, 'type' => 'private'], 'text' => $params['text'] ?? ''];
}
echo json_encode(['ok' => true, 'result' => $r]);
