<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
// whatsnew.php: every entry has a number, a date and both languages; numbers
// only grow and never repeat - a repeated one is never shown to anybody
// (bot_whatsnew_pending() tells about the numbers above the last one told).
$e = require __DIR__ . '/../whatsnew.php';
$bad = [];
if (!is_array($e) || !$e) {
    fwrite(STDERR, "whatsnew.php does not return a list\n");
    exit(1);
}
$prev = 0;
foreach ($e as $i => $row) {
    $n = (int) ($row['n'] ?? 0);
    if ($n <= $prev) {
        $bad[] = "entry #$i: n=$n does not come after $prev";
    }
    foreach (['date', 'fa', 'en'] as $k) {
        if (trim((string) ($row[$k] ?? '')) === '') {
            $bad[] = "entry n=$n: '$k' is empty";
        }
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($row['date'] ?? ''))) {
        $bad[] = "entry n=$n: date is not YYYY-MM-DD";
    }
    foreach (['fa', 'en'] as $k) {
        $t = (string) ($row[$k] ?? '');
        if (substr_count($t, '<blockquote>') !== substr_count($t, '</blockquote>') || substr_count($t, '<b>') !== substr_count($t, '</b>')) {
            $bad[] = "entry n=$n ($k): unbalanced <b> or <blockquote> - Telegram would refuse it";
        }
        // a «<» that is not one of Telegram's tags makes it refuse the whole message
        $rest = preg_replace('#</?(b|i|u|s|code|pre|blockquote|tg-emoji)\b[^>]*>|<a\s[^>]*>|</a>#i', '', $t);
        if (strpos($rest, '<') !== false) {
            $bad[] = "entry n=$n ($k): a «<» that is not a tag - write &lt;";
        }
    }
    $prev = $n;
}
echo $bad ? implode("\n", $bad) . "\n" : "whatsnew.php: " . count($e) . " entries, last n=$prev ✅\n";
exit($bad ? 1 : 0);
