<?php
// 💌 پیام برگشت مشتری, every 10 minutes: sends the ones that are due and takes
// back the discount of those who bought or whose days ran out (winback.php,
// wb_cron). Database only - it asks no panel anything.
ini_set('error_log', 'error_log');
date_default_timezone_set('Asia/Tehran');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../function.php';
$textbotlang = languagechange();

wb_cron();
