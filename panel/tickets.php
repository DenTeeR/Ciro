<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/daterange.php';
require_auth();

// 📨 the customers' support tickets (tickets.php): every conversation with its
// state, one opened to read, answer and close. An answer from here reaches the
// customer exactly like one from the bot - in their language, with their reply
// button - and is saved the same way.
$t = $textbotlang['panel'];
tk_ensure_schema();

$thread = preg_replace('/[^A-Za-z0-9]/', '', (string) ($_GET['t'] ?? ''));
// a saved «Y/m/d H:i:s» the way the panel shows dates (Jalali)
function tk_web_time($raw)
{
    $ts = strtotime((string) $raw);
    return $ts ? format_datetime('Y/m/d H:i', $ts, 'fa') : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    require_once __DIR__ . '/../botapi.php';
    $thread = preg_replace('/[^A-Za-z0-9]/', '', (string) ($_POST['t'] ?? ''));
    $rows = $thread !== '' ? tk_thread_rows($thread) : [];
    $action = (string) ($_POST['action'] ?? '');
    if (!$rows) {
        flash('error', $t['tkNotFound']);
    } elseif ($action === 'close') {
        if (tk_state($rows) !== 'closed') {
            tk_close($thread);
            tk_tell_customer_closed($rows[0]['iduser'], $thread);
        }
        flash('success', $t['tkClosedOk']);
    } elseif ($action === 'answer') {
        $answer = trim((string) ($_POST['answer'] ?? ''));
        if ($answer === '' || mb_strlen($answer) > 3500) {
            flash('error', $t['tkAnswerInvalid']);
        } else {
            // the customer's last message takes the answer; one already answered
            // gets a row of its own, so no earlier answer is overwritten
            $last = end($rows);
            $hasAnswer = (string) $last['result'] !== '' && (string) $last['result'] !== '0';
            if ($hasAnswer || (string) $last['status'] === 'close') {
                $row = tk_insert_answer_only($last, $answer);
            } else {
                tk_answer($last['Tracking'], $answer, null);
                $row = tk_row($last['Tracking']);
            }
            $sent = is_array($row) && tk_send_answer($row, htmlspecialchars($answer));
            flash($sent ? 'success' : 'error', $sent ? $t['tkAnswerSent'] : $t['tkAnswerFailed']);
        }
    }
    header('Location: tickets.php?' . http_build_query(['t' => $thread]));
    exit;
}

$stateTags = [
    'open' => ['tag-warn', $t['tkStateOpen']],
    'answered' => ['tag-ok', $t['tkStateAnswered']],
    'closed' => ['tag-plain', $t['tkStateClosed']],
];
$mediaNames = ['photo' => $t['tkMediaPhoto'], 'video' => $t['tkMediaVideo'], 'document' => $t['tkMediaFile'], 'voice' => $t['tkMediaVoice'], 'audio' => $t['tkMediaVoice'], 'animation' => $t['tkMediaVideo']];

$pageTitle = $t['tkPageTitle'];
$pageLede = $t['tkPageLede'];
$activeNav = 'tickets';

if ($thread !== '') {
    $rows = tk_thread_rows($thread);
    include __DIR__ . '/inc/layout_head.php';
    if (!$rows) {
        echo '<div class="notice notice-warn">' . $t['tkNotFound'] . '</div>';
        include __DIR__ . '/inc/layout_foot.php';
        exit;
    }
    $state = tk_state($rows);
    $first = $rows[0];
    $bubble = function ($who, $time, $text, $media, $mine) use ($mediaNames) {
        $text = trim((string) $text);
        $out = '<div class="tk-msg ' . ($mine ? 'tk-user' : 'tk-support') . '"><div class="tk-head">' . $who . ' · <span class="cf">' . htmlspecialchars(tk_web_time($time)) . '</span></div>';
        if ($media) {
            $out .= '<div class="tk-att">⟦i:link⟧ ' . htmlspecialchars($mediaNames[$media['t']] ?? $media['t']) . '</div>';
        }
        if ($text !== '' && $text !== '0') {
            $out .= '<div class="tk-text" dir="auto">' . nl2br(htmlspecialchars($text)) . '</div>';
        }
        return $out . '</div>';
    };
    ?>
<style>
.tk-conv{display:flex;flex-direction:column;gap:10px}
.tk-msg{max-width:min(560px,92%);padding:10px 12px;border-radius:12px;border:1px solid var(--bd);font-size:.86rem;line-height:1.7}
.tk-user{align-self:flex-start;background:var(--sf2)}
.tk-support{align-self:flex-end;background:var(--sf3)}
.tk-head{font-size:.74rem;opacity:.75;margin-bottom:4px}
.tk-att{font-size:.78rem;margin-bottom:4px;opacity:.85}
.tk-text{white-space:normal;word-break:break-word}
</style>
<div class="card fade-up" style="margin-bottom:14px">
  <div class="card-head"><div>
    <div class="card-title">⟦i:ticket⟧ <?= htmlspecialchars(tk_short_id($thread)) ?> · <?= htmlspecialchars((string) $first['name_departman']) ?></div>
    <div class="card-subtitle"><?= $t['tkColUser'] ?>: <?= user_link($first['iduser']) ?> · <span class="tag <?= $stateTags[$state][0] ?>"><?= $stateTags[$state][1] ?></span></div>
  </div><div><a href="tickets.php" class="btn-link"><?= $t['tkBackToList'] ?></a></div></div>
  <div class="card-body"><div class="tk-conv">
    <?php foreach ($rows as $m):
      $mm = tk_media_decode($m['media'] ?? null);
      if (trim((string) $m['text']) !== '' || $mm) {
          echo $bubble('⟦i:user⟧ ' . $t['tkFromUser'], $m['time'], $m['text'], $mm, true);
      }
      $rm = tk_media_decode($m['result_media'] ?? null);
      if (((string) $m['result'] !== '' && (string) $m['result'] !== '0') || $rm) {
          echo $bubble('⟦i:headphones⟧ ' . $t['tkFromSupport'], $m['result_time'] ?? '', $m['result'], $rm, false);
      }
    endforeach; ?>
  </div></div>
</div>

<div class="card fade-up">
  <div class="card-head"><div><div class="card-title">⟦i:send⟧ <?= $t['tkAnswerTitle'] ?></div><div class="card-subtitle"><?= $t['tkAnswerSub'] ?></div></div></div>
  <div class="card-body">
    <form method="POST" action="tickets.php">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="t" value="<?= htmlspecialchars($thread) ?>">
      <input type="hidden" name="action" value="answer">
      <textarea class="textarea" name="answer" rows="4" dir="auto" maxlength="3500" required placeholder="<?= htmlspecialchars($t['tkAnswerPlaceholder']) ?>"></textarea>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
        <button type="submit" class="btn btn-primary"><?= $t['tkAnswerBtn'] ?></button>
      </div>
    </form>
    <?php if ($state !== 'closed'): ?>
    <form method="POST" action="tickets.php" style="margin-top:10px">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="t" value="<?= htmlspecialchars($thread) ?>">
      <input type="hidden" name="action" value="close">
      <button type="submit" class="btn btn-no"><?= $t['tkCloseBtn'] ?></button>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php
    include __DIR__ . '/inc/layout_foot.php';
    exit;
}

// ---- the list ----
$search = trim((string) ($_GET['q'] ?? ''));
$state = (string) ($_GET['state'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;
$dr = dr_read();
$drParams = [];
$drSQL = dr_cond($dr, dr_pay_secs('l.time'), $drParams);

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(l.iduser LIKE ? OR g.th LIKE ?)';
    array_push($params, "%$search%", '%' . strtolower(ltrim($search, '#')) . '%');
}
if ($state === 'open') {
    $where[] = "l.status IN ('Unseen', 'Pending', 'Customerresponse')";
} elseif ($state === 'answered') {
    $where[] = "l.status = 'Answered'";
} elseif ($state === 'closed') {
    $where[] = "l.status = 'close'";
}
if ($drSQL !== '') {
    $where[] = $drSQL;
    $params = array_merge($params, $drParams);
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$base = "FROM (SELECT th, MAX(id) AS last_id, COUNT(*) AS n FROM (SELECT COALESCE(NULLIF(thread, ''), Tracking) AS th, id FROM support_message) x GROUP BY th) g JOIN support_message l ON l.id = g.last_id";
try {
    $total = db_count($pdo, "SELECT COUNT(*) $base $whereSQL", $params);
    $list = db_fetchAll($pdo, "SELECT g.th, g.n, l.iduser, l.name_departman, l.status, l.time $base $whereSQL ORDER BY g.last_id DESC LIMIT $perPage OFFSET $offset", $params);
    $openCount = db_count($pdo, "SELECT COUNT(*) $base WHERE l.status IN ('Unseen', 'Pending', 'Customerresponse')");
} catch (Exception $e) {
    $total = 0;
    $list = [];
    $openCount = 0;
    flash('error', $t['tkDbError'] . $e->getMessage());
}
$totalPages = max(1, (int) ceil($total / $perPage));
include __DIR__ . '/inc/layout_head.php';
?>
<div class="card fade-up">
  <div class="toolbar">
    <div class="toolbar-title"><?= $t['tkListTitle'] ?> <small>(<?= number_format($total) ?>)</small> <?php if ($openCount > 0): ?><span class="tag tag-warn"><?= sprintf($t['tkOpenCount'], number_format($openCount)) ?></span><?php endif; ?></div>
    <form method="GET" id="tkForm" class="toolbar-end">
      <select name="state" class="select" style="width:auto" onchange="document.getElementById('tkForm').submit()">
        <option value=""><?= $t['tkAllStates'] ?></option>
        <?php foreach ($stateTags as $k => [$_, $lbl]): ?><option value="<?= $k ?>"<?= $state === $k ? ' selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?>
      </select>
      <?= dr_control($dr) ?>
      <div class="search-box" style="min-width:220px">
        <?= icon('search', 14) ?>
        <input type="text" name="q" placeholder="<?= htmlspecialchars($t['tkSearchPlaceholder']) ?>" value="<?= htmlspecialchars($search) ?>" autocomplete="off">
        <button type="button" class="search-clear">✕</button>
        <button type="submit" class="search-btn"><?= $t['invoiceSearchBtn'] ?></button>
      </div>
      <?php if ($search !== '' || $state !== '' || $dr['active']): ?><a href="tickets.php" class="btn-link" style="font-size:.78rem"><?= $t['invoiceClearBtn'] ?></a><?php endif; ?>
    </form>
  </div>
  <div class="tbl-wrap">
    <table class="tbl-md">
      <thead><tr><th><?= $t['tkColTicket'] ?></th><th><?= $t['tkColUser'] ?></th><th><?= $t['tkColDept'] ?></th><th><?= $t['tkColMessages'] ?></th><th><?= $t['tkColState'] ?></th><th><?= $t['tkColLast'] ?></th></tr></thead>
      <tbody>
      <?php if (!$list): ?>
        <tr><td colspan="6"><div class="empty"><p><?= $dr['active'] ? $t['drNoneInRange'] : $t['tkEmpty'] ?></p></div></td></tr>
      <?php else: foreach ($list as $r):
        $st = $r['status'] === 'close' ? 'closed' : ($r['status'] === 'Answered' ? 'answered' : 'open'); ?>
        <tr>
          <td class="cm"><a href="tickets.php?<?= http_build_query(['t' => $r['th']]) ?>"><?= htmlspecialchars(tk_short_id($r['th'])) ?></a></td>
          <td class="cm"><?= user_link($r['iduser']) ?></td>
          <td class="cs"><?= htmlspecialchars(trunc((string) $r['name_departman'], 24)) ?></td>
          <td class="cn"><?= (int) $r['n'] ?></td>
          <td><span class="tag <?= $stateTags[$st][0] ?>"><?= $stateTags[$st][1] ?></span></td>
          <td class="cf"><?= htmlspecialchars(tk_web_time($r['time'])) ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <div class="tbl-foot">
    <span><?= number_format($total) ?></span>
    <div class="pager">
      <?php $qs = fn($p) => '?' . http_build_query(['q' => $search, 'state' => $state, 'page' => $p] + dr_params($dr)); ?>
      <a class="<?= $page <= 1 ? 'dis' : '' ?>" href="<?= $qs(max(1, $page - 1)) ?>">‹</a>
      <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
        <a class="<?= $p === $page ? 'cur' : '' ?>" href="<?= $qs($p) ?>"><?= $p ?></a>
      <?php endfor; ?>
      <a class="<?= $page >= $totalPages ? 'dis' : '' ?>" href="<?= $qs(min($totalPages, $page + 1)) ?>">›</a>
    </div>
  </div>
</div>
<?php include __DIR__ . '/inc/layout_foot.php'; ?>
