<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
// i18n_validate.php compares every English text's placeholders with Persian's.
// Some differences are known and harmless (English counts days and gigabytes
// in words of its own, a «50% for» read as a format code, admin-only texts);
// those are listed in tests/i18n_known.txt. Anything new fails - an English
// customer would see a «%s» or a {token} instead of a value.
$out = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../i18n_validate.php') . ' 2>&1');
preg_match_all('/^ {4}(\S+)\s/m', (string) $out, $m);
$found = array_unique($m[1]);
$known = array_filter(array_map('trim', file(__DIR__ . '/i18n_known.txt', FILE_IGNORE_NEW_LINES) ?: []), fn($l) => $l !== '' && $l[0] !== '#');
$new = array_values(array_diff($found, $known));
if ($new) {
    echo "New placeholder differences between lang/fa.php and lang/en.php:\n";
    foreach ($new as $k) {
        preg_match('/^ {4}' . preg_quote($k, '/') . '\s.*$/m', $out, $line);
        echo $line[0] ?? $k, "\n";
    }
    exit(1);
}
echo "i18n: " . count($found) . " known differences, nothing new ✅\n";
