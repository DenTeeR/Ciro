<?php
ini_set('error_log', 'error_log');
date_default_timezone_set('Asia/Tehran');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../function.php';
class ServiceMonitor
{
    private $Panel;
    private $pdo;
    private $setting;
    private $reportCron;
    private $status_cron;
    const SECONDS_PER_DAY = 86400;
    private $textBotLang;

    public function __construct()
    {
        global $pdo;
        $this->pdo = $pdo;
        $this->Panel = new ManagePanel();
        $this->reportCron = select("topicid", "idreport", "report", "reportcron", "select")['idreport'];
        // the old fixed low-volume / low-time warnings become 🔋 tiers - once
        notice_migrate_legacy();
        $this->setting = select("setting", "*");
        $this->status_cron = json_decode($this->setting['cron_status'], true);
        $this->textBotLang = languagechange(dirname(__DIR__));
        // formatBytes() reads the global - without it the volume notice lost its unit ("1.5 " not "1.5 گیگابایت")
        $GLOBALS['textbotlang'] = $this->textBotLang;
    }

    public function RunNotifactions()
    {
        $invoices = $this->getActiveInvoices();
        if ($invoices == false)
            return;
        foreach ($invoices as $invoice) {
            if ($invoice['time_cron'] != null) {
                $time_cron = time() - $invoice['time_cron'];
                if ($time_cron < 1600)
                    continue;
            }
            update("invoice", "time_cron", time(), "id_invoice", $invoice['id_invoice']);
            $data = $this->processInvoice($invoice);
            if (!is_array($data))
                continue;
            // 💌 پیام برگشت مشتری (winback.php): when it ended, or that it lives again
            if (function_exists('wb_track'))
                wb_track($data['invoice'], $data['userData']);
            // 🔁 تمدید خودکار (autorenew.php): renewed from the wallet now - the
            // warnings below would speak of the service as it was before
            if (function_exists('ar_cron_check') && ar_cron_check($data['invoice'], $data['user'], $data['userData'], $data['panel'], $this->Panel))
                continue;
            // every low-volume / low-time / ended warning - 🔋's tiers
            $this->checkCustomNotices($data['invoice'], $data['user'], $data['userData'], $invoice['username']);
            // 🎁 a free config not renewed has its own 🗑 rule, never the
            // bot-wide one (⚙️ قوانین اکانت کانفیگ رایگان)
            if ((int) ($data['invoice']['affrw'] ?? 0) === 1) {
                affrw_cron_ended($data['invoice'], $data['user'], $data['userData'], $this->Panel);
            } else {
                if ($this->status_cron['remove'])
                    $this->shouldRemoveService($data['invoice'], $data['user'], $data['userData'], $invoice['username']);
                if ($this->status_cron['remove_volume'])
                    $this->shouldRemoveServiceـvolume($data['invoice'], $data['user'], $data['userData'], $invoice['username']);
            }
            if ($data['panel']['inboundstatus'] == "oninbounddisable" && $data['panel']['type'] == "marzban")
                $this->active_inbound_expire($data['invoice'], $data['userData'], $data['panel']);
        }
    }


    private function getActiveInvoices()
    {
        $time_hours = time() - 3600;
        // ⏱ an hourly service has no end of time or volume to warn about - its
        // own cron (payg.php) watches its wallet instead
        payg_ensure_schema();
        $QUERY = "SELECT * FROM invoice WHERE (Status = 'active' OR Status = 'end_of_time' OR Status = 'end_of_volume' OR Status = 'sendedwarn' OR Status = 'send_on_hold') AND payg = 0 AND name_product != '{$this->textBotLang['Admin']['adminphp']['db_test_service_name']}' AND (time_cron <= '$time_hours' OR time_cron IS NULL) ORDER BY time_cron  LIMIT 30";
        $stmt = $this->pdo->prepare($QUERY);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function processInvoice($invoice)
    {
        $username = $invoice['username'];

        // Get panel information
        $panelInfo = select("marzban_panel", "*", "name_panel", $invoice['Service_location'], "select");
        if (!$panelInfo)
            return false;

        if ($panelInfo['status'] == "disabled")
            return false;
        // Get user information
        $user = select("user", "*", "id", $invoice['id_user'], "select");
        if ($user == false)
            return false;

        // Get username data from panel
        $userData = $this->Panel->DataUser($invoice['Service_location'], $username);
        if (!$userData || $userData['status'] == "Unsuccessful")
            return;
        return [
            'invoice' => $invoice,
            'panel' => $panelInfo,
            'user' => $user,
            'userData' => $userData
        ];
    }

    private function shouldRemoveService($invoice, $user, $userData, $username)
    {
        if (!in_array($userData['status'], ['limited', 'expired']))
            return false;
        $timeService = $userData['expire'] - time();
        $daysRemaining = intval($timeService / 86400);
        $removalThreshold = intval("-" . $this->setting['removedayc']);
        $result = $daysRemaining <= $removalThreshold;
        $statusText = $statusMap = [
            'active' => $this->textBotLang['users']['status']['active'],
            'limited' => $this->textBotLang['users']['status']['limited'],
            'disabled' => $this->textBotLang['users']['status']['disabled'],
            'expired' => $this->textBotLang['users']['status']['expired'],
            'on_hold' => $this->textBotLang['users']['status']['on_hold'],
            'Unknown' => $this->textBotLang['users']['status']['unknown']
        ][$userData['status']];
        $remainingVolume = formatBytes($userData['data_limit'] - $userData['used_traffic']);
        if ($result) {
            update("invoice", "status", "removeTime", "username", $username);
            $this->Panel->RemoveUser($invoice['Service_location'], $username);
            $message = sprintf($this->textBotLang['hardcoded']['notifServiceDeleted'], $invoice['username']);
            $reportMessage = sprintf($this->textBotLang['hardcoded']['notifDeleteCronInfo'], $invoice['username'], $statusText, $daysRemaining, $remainingVolume);
            $this->send_notifactions($invoice, $user, $message, false, $invoice['bottype']);
            $this->sendReportNotification($reportMessage);
        }
    }
    private function shouldRemoveServiceـvolume($invoice, $user, $userData, $username)
    {
        if (!in_array($userData['status'], ['limited', 'expired']))
            return false;
        $panel = select("marzban_panel", "*", "name_panel", $invoice['Service_location'], "select");
        if ($panel['type'] != "marzban")
            return;
        if ($userData['data_limit_reset'] != "no_reset")
            return;
        if ($userData['status'] == "Unsuccessful")
            return;
        if (in_array($userData['status'], ['Unknown', 'active', 'on_hold', 'disabled', 'expired']))
            return;
        if (empty($userData['online_at']) or $userData['online_at'] == null) {
            $timelastconect = 0;
        } else {
            $time = strtotime($userData['online_at']);
            $timelastconect = (time() - $time) / 86400;
        }
        if ($timelastconect == 0)
            return;
        $timeService = $userData['expire'] - time();
        $daysRemaining = intval($timeService / 86400);
        $removalThreshold = intval($this->setting['cronvolumere']);
        $result = $timelastconect >= $removalThreshold;
        $statusText = [
            'active' => $this->textBotLang['users']['status']['active'],
            'limited' => $this->textBotLang['users']['status']['limited'],
            'disabled' => $this->textBotLang['users']['status']['disabled'],
            'expired' => $this->textBotLang['users']['status']['expired'],
            'on_hold' => $this->textBotLang['users']['status']['on_hold'],
            'Unknown' => $this->textBotLang['users']['status']['unknown']
        ][$userData['status']];
        $remainingVolume = formatBytes($userData['data_limit'] - $userData['used_traffic']);
        if ($result) {
            update("invoice", "status", "removevolume", "username", $username);
            $this->Panel->RemoveUser($invoice['Service_location'], $username);
            $message = sprintf($this->textBotLang['hardcoded']['notifServiceDeleted2'], $username);
            $reportMessage = sprintf($this->textBotLang['hardcoded']['notifVolumeDeleteCronInfo'], $username, $statusText, $daysRemaining, $remainingVolume, $userData['online_at']);
            $this->send_notifactions($invoice, $user, $message, false, $invoice['bottype']);
            $this->sendReportNotification($reportMessage);
        }
    }
    private function active_inbound_expire($invoice, $userData, $panel_info)
    {
        if ($invoice['uuid'] != null || $userData['data_limit_reset'] != "no_reset")
            return;
        $inbound = explode("*", $panel_info['inbound_deactive']);
        update("invoice", "uuid", json_encode($userData['uuid']), "id_invoice", $invoice['id_invoice']);
        $proxies = [];
        $proxies[$inbound[0]] = new stdClass();
        ;
        $inbounds[$inbound[0]][] = $inbound[1];
        $configs = array(
            "proxies" => $proxies,
            "inbounds" => $inbounds
        );
        $this->Panel->Modifyuser($invoice['username'], $panel_info['code_panel'], $configs);
    }
    // admin-defined "at X% used / X GB left / X days left, send this custom
    // message/sticker/button" tiers (see volumepct_tier_* in function.php) -
    // every service warning there is, doing nothing when no tiers are
    // configured. Tracks the highest tier already notified per
    // invoice in notifctions.volumePctSent so re-crossing a lower tier (or the
    // same one again) never re-sends; a usage jump that skips straight past
    // several tiers between cron runs only sends the single highest one reached.
    private function checkCustomNotices($invoice, $user, $userData, $username)
    {
        if (empty($user) || empty(volumepct_tiers_map())) {
            return;
        }
        $notif = json_decode((string) $invoice['notifctions'], true);
        if (!is_array($notif)) {
            $notif = [];
        }
        $usedPercent = 0;
        if (!empty($userData['data_limit']) && $userData['data_limit'] > 0) {
            $usedPercent = min(100, max(0, ($userData['used_traffic'] / $userData['data_limit']) * 100));
        }
        // most-terminal first, and each returns after sending, so a single cron
        // pass never fires more than one custom notice for the same invoice
        if ($this->noticeVolumeEnded($invoice, $user, $userData, $notif, $usedPercent)) {
            return;
        }
        if ($this->noticeTimeEnded($invoice, $user, $userData, $notif, $usedPercent)) {
            return;
        }
        if ($this->noticeVolumeTier($invoice, $user, $userData, $notif, $usedPercent)) {
            return;
        }
        if ($this->noticeVolumeGbTier($invoice, $user, $userData, $notif, $usedPercent)) {
            return;
        }
        $this->noticeTimeTier($invoice, $user, $userData, $notif, $usedPercent);
    }

    // shared delivery for all 4 notice kinds: optional sticker first, then the
    // caption with that tier's own customized button, then persist the updated
    // notifctions tracking blob
    private function sendCustomNotice($tierIndex, $invoice, $user, $userData, array $notif, $usedPercent)
    {
        $lang = $user['lang'] ?? 'fa';
        // switched off on this customer's tab: the moment still counts as
        // handled, so turning it back on later does not send a backlog
        if (!bt_item_enabled('volpct.' . volumepct_tier_kind(volumepct_tier_get($tierIndex)), $lang)) {
            update("invoice", "notifctions", json_encode($notif), "id_invoice", $invoice['id_invoice']);
            $this->reportCustomNotice($tierIndex, $invoice, $userData);
            return;
        }
        // the customer's own language - its defaults and its button label
        $t = lang_tab_texts($lang);
        $caption = volumepct_tier_caption($tierIndex, $lang, $t, $invoice, $user, $userData, (string) round($usedPercent));
        // its renew button - not on a free config that can't be renewed
        $keyboard = affrw_renew_blocked($invoice) ? null : volumepct_tier_kb($tierIndex, $lang, $t, $invoice['id_invoice']);
        $sticker = volumepct_tier_sticker($tierIndex, $lang);
        if ($sticker !== '') {
            sticker_value_send($invoice['id_user'], $sticker, $invoice['bottype']);
        }
        sendmessage($invoice['id_user'], $caption, $keyboard, 'HTML', $invoice['bottype']);
        update("invoice", "notifctions", json_encode($notif), "id_invoice", $invoice['id_invoice']);
        $this->reportCustomNotice($tierIndex, $invoice, $userData);
    }

    // The report channel's note, as the old fixed volume/time warnings sent
    // it - now for every GB and day tier. In the panel's language.
    private function reportCustomNotice($tierIndex, $invoice, $userData)
    {
        $kind = volumepct_tier_kind(volumepct_tier_get($tierIndex));
        $h = $this->textBotLang['hardcoded'];
        $raw = (string) ($userData['status'] ?? '');
        $status = $this->textBotLang['users']['status'][$raw === 'Unknown' ? 'unknown' : $raw] ?? $raw;
        if ($kind === 'volgb') {
            $left = max(0, ($userData['data_limit'] ?? 0) - ($userData['used_traffic'] ?? 0));
            $this->sendReportNotification($h['notifVolumeCronTitle']
                . sprintf($h['notifServiceUsername'], $invoice['username'])
                . sprintf($h['notifServiceStatus'], $status)
                . sprintf($h['notifRemainingVolume'], notice_format_bytes($left, $this->textBotLang)));
        } elseif ($kind === 'time') {
            $this->sendReportNotification($h['notifTimeCronTitle']
                . sprintf($h['notifServiceUsername2'], $invoice['username'])
                . sprintf($h['notifServiceStatus2'], $status)
                . sprintf($h['notifRemainingDays'], notice_time_left_text(($userData['expire'] ?? 0) - time(), $this->textBotLang)));
        }
    }

    // volume fully consumed - fires once ever per invoice
    private function noticeVolumeEnded($invoice, $user, $userData, $notif, $usedPercent)
    {
        if (!empty($notif['volEndSent'])) {
            return false;
        }
        $exhausted = ($userData['status'] === 'limited')
            || (!empty($userData['data_limit']) && $userData['data_limit'] > 0 && $userData['used_traffic'] >= $userData['data_limit']);
        if (!$exhausted) {
            return false;
        }
        $tiers = volumepct_tiers_for_kind('volend');
        if (empty($tiers)) {
            return false;
        }
        $notif['volEndSent'] = true;
        $this->sendCustomNotice(array_key_first($tiers), $invoice, $user, $userData, $notif, $usedPercent);
        return true;
    }

    // subscription time fully elapsed - fires once ever per invoice
    private function noticeTimeEnded($invoice, $user, $userData, $notif, $usedPercent)
    {
        if (!empty($notif['timeEndSent'])) {
            return false;
        }
        $ended = ($userData['status'] === 'expired')
            || (!empty($userData['expire']) && $userData['expire'] > 0 && $userData['expire'] <= time());
        if (!$ended) {
            return false;
        }
        $tiers = volumepct_tiers_for_kind('timeend');
        if (empty($tiers)) {
            return false;
        }
        $notif['timeEndSent'] = true;
        $this->sendCustomNotice(array_key_first($tiers), $invoice, $user, $userData, $notif, $usedPercent);
        return true;
    }

    // "X% of your volume is used" tiers - picks the HIGHEST threshold crossed
    // that is above whatever was last sent, so a usage jump between cron runs
    // sends only the single most-relevant one, never a backlog
    private function noticeVolumeTier($invoice, $user, $userData, $notif, $usedPercent)
    {
        if (!in_array($userData['status'], ['active', 'Unknown'], true)) {
            return false;
        }
        if (empty($userData['data_limit']) || $userData['data_limit'] <= 0) {
            return false;
        }
        $alreadySent = intval($notif['volumePctSent'] ?? 0);
        $bestPct = null;
        $bestIndex = null;
        foreach (volumepct_tiers_for_kind('vol') as $i => $tier) {
            $pct = intval($tier['pct'] ?? -1);
            if ($pct < 0 || $pct > 100) {
                continue;
            }
            if ($usedPercent >= $pct && $pct > $alreadySent) {
                if ($bestPct === null || $pct > $bestPct) {
                    $bestPct = $pct;
                    $bestIndex = $i;
                }
            }
        }
        if ($bestIndex === null) {
            return false;
        }
        $notif['volumePctSent'] = $bestPct;
        $this->sendCustomNotice($bestIndex, $invoice, $user, $userData, $notif, $usedPercent);
        return true;
    }

    // "X GB left" tiers - like the days below, crossed on the way DOWN: the
    // LOWEST threshold reached that is below whatever was last sent
    private function noticeVolumeGbTier($invoice, $user, $userData, $notif, $usedPercent)
    {
        if (!in_array($userData['status'], ['active', 'Unknown'], true)) {
            return false;
        }
        if (empty($userData['data_limit']) || $userData['data_limit'] <= 0) {
            return false;
        }
        $gbLeft = ($userData['data_limit'] - $userData['used_traffic']) / pow(1024, 3);
        // nothing left is 🔚 پایان حجم's, not a warning
        if ($gbLeft <= 0) {
            return false;
        }
        $lastSent = isset($notif['volGbSent']) ? intval($notif['volGbSent']) : PHP_INT_MAX;
        // warned by the old fixed "حجم هشدار" (now a tier here): not again at
        // or above what it warned at
        if (!isset($notif['volGbSent']) && !empty($notif['volume'])) {
            $lastSent = intval($this->setting['volumewarn'] ?? 0);
        }
        $bestGb = null;
        $bestIndex = null;
        foreach (volumepct_tiers_for_kind('volgb') as $i => $tier) {
            $gb = intval($tier['pct'] ?? -1);
            if ($gb <= 0) {
                continue;
            }
            if ($gbLeft <= $gb && $gb < $lastSent) {
                if ($bestGb === null || $gb < $bestGb) {
                    $bestGb = $gb;
                    $bestIndex = $i;
                }
            }
        }
        if ($bestIndex === null) {
            return false;
        }
        $notif['volGbSent'] = $bestGb;
        $this->sendCustomNotice($bestIndex, $invoice, $user, $userData, $notif, $usedPercent);
        return true;
    }

    // "X days left" tiers - mirror image of the volume logic: thresholds are
    // crossed on the way DOWN, so it picks the LOWEST (most urgent) threshold
    // crossed that is below whatever was last sent
    private function noticeTimeTier($invoice, $user, $userData, $notif, $usedPercent)
    {
        if (!in_array($userData['status'], ['active', 'Unknown'], true)) {
            return false;
        }
        if (empty($userData['expire']) || $userData['expire'] <= 0) {
            return false;
        }
        $daysLeft = ($userData['expire'] - time()) / 86400;
        if ($daysLeft < 0) {
            return false;
        }
        $lastSent = isset($notif['timeTierSent']) ? intval($notif['timeTierSent']) : PHP_INT_MAX;
        // warned by the old fixed "زمان هشدار" (now a tier here): not again at
        // or above what it warned at
        if (!isset($notif['timeTierSent']) && !empty($notif['time'])) {
            $lastSent = intval($this->setting['daywarn'] ?? 0);
        }
        $bestDays = null;
        $bestIndex = null;
        foreach (volumepct_tiers_for_kind('time') as $i => $tier) {
            $days = intval($tier['pct'] ?? -1);
            if ($days < 0) {
                continue;
            }
            if ($daysLeft <= $days && $days < $lastSent) {
                if ($bestDays === null || $days < $bestDays) {
                    $bestDays = $days;
                    $bestIndex = $i;
                }
            }
        }
        if ($bestIndex === null) {
            return false;
        }
        $notif['timeTierSent'] = $bestDays;
        $this->sendCustomNotice($bestIndex, $invoice, $user, $userData, $notif, $usedPercent);
        return true;
    }

    // $keyboard: the JSON to send under the message, or false/null for none
    private function send_notifactions($invoice, $status_cron_user, $message, $keyboard, $bot_token)
    {
        if (intval($status_cron_user) == 0)
            return;
        sendmessage($invoice['id_user'], $message, $keyboard ?: null, 'HTML', $bot_token);
    }

    private function sendReportNotification($reportMessage)
    {
        if (empty($this->setting['Channel_Report']))
            return;


        telegram('sendmessage', [
            'chat_id' => $this->setting['Channel_Report'],
            'message_thread_id' => $this->reportCron,
            'text' => $reportMessage,
            'parse_mode' => "HTML"
        ]);
    }

    private function updateInvoiceStatus($type, $invoice)
    {
        $data = json_decode($invoice['notifctions'], true);
        $data[$type] = true;
        $data = json_encode($data);
        update("invoice", "notifctions", $data, "id_invoice", $invoice['id_invoice']);
    }
}

// fires the "code finished" report for codes that simply ran out of time
// without a final use to trigger it inline
if (function_exists('topup_disc_sweep_expired')) {
    topup_disc_sweep_expired();
}
// periodic discount usage summary - self-throttling, decides internally
// whether enough time has passed since the previous one
if (function_exists('topup_disc_periodic_report')) {
    topup_disc_periodic_report();
}

// Execute the volume monitoring
$volumeMonitor = new ServiceMonitor();
$volumeMonitor->RunNotifactions();