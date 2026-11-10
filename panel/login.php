<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
session_start();
// «🤖 پنل تحت وب: خاموش ❌» in the bot: no form and no login, only how to turn it on
$panelOff = !webpanel_enabled();

// a saved login (the «ذخیره ورود» box, 24 hours, same IP) skips the form
if (!$panelOff && empty($_SESSION['admin_user']) && ($saved = remember_restore()) !== null) {
    session_regenerate_id(true);
    $_SESSION['admin_user'] = $saved;
    $_SESSION['login_time'] = time();
}
if (!$panelOff && !empty($_SESSION['admin_user'])) {
  header('Location: index.php');
  exit;
}

$error = '';
$info = '';
// 🔐 the login's second step (webauth.php): the code the bot sent to Telegram
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!$panelOff && ($_GET['tfa'] ?? '') === 'cancel') {
  unset($_SESSION['web2fa']);
  header('Location: login.php');
  exit;
}
if (!$panelOff && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tfa_code'])) {
  $tfa = $_SESSION['web2fa'] ?? null;
  if (!csrf_check_value($_POST['_csrf'] ?? '')) {
    $error = $textbotlang['panel']['login2faWrong'];
  } elseif (!check_login_rate($ip)) {
    $error = $textbotlang['panel']['loginTooManyAttempts'];
  } else {
    $res = web2fa_check($_POST['tfa_code'], $ip);
    if ($res === 'ok' && is_array($tfa)) {
      unset($_SESSION['web2fa']);
      clear_login_rate($ip);
      session_regenerate_id(true);
      $_SESSION['admin_user'] = $tfa['user'];
      $_SESSION['login_time'] = time();
      if (!empty($tfa['remember'])) {
        remember_issue($tfa['user'], $tfa['stored']);
      } else {
        remember_forget();
      }
      flash('success', $textbotlang['panel']['loginWelcomeBack'] . $tfa['user']);
      header('Location: index.php');
      exit;
    }
    $error = $res === 'wrong' ? $textbotlang['panel']['login2faWrong'] : $textbotlang['panel']['login2faExpired'];
    error_log("Wrong web panel login code from IP: $ip");
  }
} elseif (!$panelOff && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';
  $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

  if (!csrf_check_value($_POST['_csrf'] ?? '')) {
    $error = $textbotlang['panel']['loginWrongCredentials'];
  } elseif ($username === '' || $password === '') {
    $error = $textbotlang['panel']['loginEnterCredentials'];
  } elseif (!check_login_rate($ip)) {

    $error = $textbotlang['panel']['loginTooManyAttempts'];
    error_log("Login rate limit hit for IP: $ip username: $username");
  } else {

    $admin = select("admin", "*", "username", $username, "select");

    $dummyHash = '$2y$10$dummy.hash.for.timing.attack.prevention.xxxxxxxxxxxxxxxx';
    $storedHash = $admin ? (string) $admin['password'] : $dummyHash;

    $isCorrect = false;
    $storedIsHash = str_starts_with($storedHash, '$2') || str_starts_with($storedHash, '$argon2');
    if ($storedIsHash) {
      $isCorrect = password_verify($password, $storedHash);
    } elseif ($admin) {
      $isCorrect = hash_equals($storedHash, $password);
    }

    if ($isCorrect && $admin && $admin['rule'] === 'administrator') {

      $storedNow = (string) $admin['password'];
      if (!str_starts_with($admin['password'], '$2')) {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        update("admin", "password", $hash, "username", $username);
        $storedNow = $hash;
      }
      // 🔐 a code to Telegram first, when the bot asks for one (webauth.php)
      if (web2fa_on()) {
        unset($_SESSION['web2fa']);
        if (web2fa_start($admin, !empty($_POST['remember']), $storedNow, $ip)) {
          $info = $textbotlang['panel']['login2faSent'];
        } else {
          $error = $textbotlang['panel']['login2faSendFailed'];
          error_log("Web panel login code could not be sent for username: $username");
        }
      }
    }
    if ($isCorrect && $admin && $admin['rule'] === 'administrator' && !web2fa_on()) {
      clear_login_rate($ip);
      session_regenerate_id(true);
      $_SESSION['admin_user'] = $admin['username'];
      $_SESSION['login_time'] = time();
      if (!empty($_POST['remember'])) {
        remember_issue($admin['username'], $storedNow);
      } else {
        remember_forget();
      }
      flash('success', $textbotlang['panel']['loginWelcomeBack'] . $admin['username']);
      header('Location: index.php');
      exit;
    } elseif (!($isCorrect && $admin && $admin['rule'] === 'administrator')) {
      $error = $textbotlang['panel']['loginWrongCredentials'];
      error_log("Failed login for username: $username from IP: $ip");
    }
  }
}
?>
<?php
// the title's last word is the one that glows gold
$title = trim((string) $textbotlang['panel']['loginButton']);
$cut = mb_strrpos($title, ' ');
$titleHtml = $cut === false ? '<span>' . htmlspecialchars($title) . '</span>'
  : htmlspecialchars(mb_substr($title, 0, $cut)) . ' <span>' . htmlspecialchars(mb_substr($title, $cut + 1)) . '</span>';
$L = $textbotlang['panel'];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#09080a">
  <title><?= $L['loginPanelTitle'] ?></title>
  <link rel="icon" href="img/icon.svg" type="image/svg+xml">
  <link rel="preload" href="fonts/vazirmatn.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="css/fonts.css?v=<?= (int) @filemtime(__DIR__ . '/css/fonts.css') ?>">
  <link rel="stylesheet" href="css/login.css?v=<?= (int) @filemtime(__DIR__ . '/css/login.css') ?>">
</head>

<body>
  <svg class="backdrop" viewBox="0 0 1500 1000" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
<defs>
<linearGradient id="gold" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#0c0805"/><stop offset=".22" stop-color="#453018"/><stop offset=".39" stop-color="#a07438"/><stop offset=".48" stop-color="#f4d28b"/><stop offset=".54" stop-color="#9d6a29"/><stop offset=".64" stop-color="#22150a"/><stop offset=".78" stop-color="#8b5b27"/><stop offset=".83" stop-color="#d7a05a"/><stop offset=".88" stop-color="#4c3014"/><stop offset="1" stop-color="#050405"/></linearGradient>
<linearGradient id="silver" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#020204"/><stop offset=".26" stop-color="#16171c"/><stop offset=".42" stop-color="#63646c"/><stop offset=".48" stop-color="#c4c4ca"/><stop offset=".53" stop-color="#44454d"/><stop offset=".62" stop-color="#08090c"/><stop offset=".8" stop-color="#767880"/><stop offset=".85" stop-color="#282930"/><stop offset="1" stop-color="#020203"/></linearGradient>
<radialGradient id="glow"><stop stop-color="#5d2d04" stop-opacity=".4"/><stop offset="1" stop-color="#000" stop-opacity="0"/></radialGradient>
<filter id="soft"><feGaussianBlur stdDeviation="5"/></filter>
</defs>
<path fill="#050405" d="M0 0h1500v1000H0z"/>
<ellipse cx="1350" cy="760" rx="500" ry="280" fill="url(#glow)"/>
<path d="M240 -140 Q790 355 1610 390" fill="none" stroke="#0b0806" stroke-width="160"/>
<path d="M240 -140 Q790 355 1610 390" fill="none" stroke="url(#gold)" stroke-width="112"/>
<path d="M242 -110 Q850 405 1610 414" fill="none" stroke="#dca955" stroke-opacity=".55" stroke-width="3"/>
<path d="M265 -125 Q850 380 1610 392" fill="none" stroke="#f0d39a" stroke-opacity=".5" stroke-width="2"/>
<path d="M715 -180 Q1060 90 1590 99" fill="none" stroke="url(#silver)" stroke-width="66"/>
<path d="M730 -190 Q1090 118 1570 120" fill="none" stroke="#a4a1a3" stroke-opacity=".5" stroke-width="2"/>
<path d="M-150 0 Q600 800 1250 1120" fill="none" stroke="#1c1108" stroke-width="54"/>
<path d="M-150 0 Q600 800 1250 1120" fill="none" stroke="url(#gold)" stroke-width="40"/>
<path d="M-155 4 Q595 805 1245 1125" fill="none" stroke="#d5a05b" stroke-opacity=".65" stroke-width="2"/>
<path d="M-300 330 Q90 610 580 1190" fill="none" stroke="url(#silver)" stroke-width="137"/>
<path d="M-300 410 Q90 690 580 1270" fill="none" stroke="url(#silver)" stroke-width="13"/>
<path d="M-300 300 Q90 580 580 1160" fill="none" stroke="#b3b5c0" stroke-opacity=".38" stroke-width="2"/>
</svg>
  <main class="shell" id="shell">
    <svg class="frame" id="frame" aria-hidden="true">
      <defs>
        <linearGradient id="edge" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#dbdadd" stop-opacity=".7"/><stop offset=".3" stop-color="#a29d94" stop-opacity=".23"/><stop offset=".7" stop-color="#b6a889" stop-opacity=".3"/><stop offset="1" stop-color="#e0cf9e" stop-opacity=".7"/></linearGradient>
        <linearGradient id="glass" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#d9d7e5" stop-opacity=".15"/><stop offset=".48" stop-color="#514954" stop-opacity=".13"/><stop offset="1" stop-color="#2b202b" stop-opacity=".22"/></linearGradient>
      </defs>
      <path id="frame-path" fill="url(#glass)" stroke="url(#edge)" stroke-width="1.7"/>
    </svg>
    <div class="piece piece-one" aria-hidden="true"><svg viewBox="0 0 100 100"><path d="M26 3H88Q103 3 91 17L17 91Q3 104 3 86V27Q3 3 26 3Z"/></svg></div>
    <div class="piece piece-two" aria-hidden="true"><svg viewBox="0 0 100 100"><path d="M74 97H12Q-3 97 9 83L83 9Q97-4 97 14V73Q97 97 74 97Z"/></svg></div>
    <div class="glow" aria-hidden="true"></div>

    <section class="content">
      <header>
        <h1><?= $titleHtml ?></h1>
        <p class="lede"><?= htmlspecialchars($L['loginRememberMe']) ?></p>
      </header>
      <?php if ($panelOff): ?>
        <p class="note off" role="status"><?= htmlspecialchars($L['loginPanelOff']) ?></p>
      <?php else: ?>
        <?php if ($error): ?>
          <p class="note" role="alert"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <?php if ($info): ?>
          <p class="note off" role="status"><?= htmlspecialchars($info) ?></p>
        <?php endif; ?>
        <?php if (web2fa_pending()): ?>
        <form id="login-form" method="POST" autocomplete="off">
          <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
          <div class="field">
            <label for="tfa_code"><?= htmlspecialchars($L['login2faLabel']) ?></label>
            <div class="input">
              <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
              <input type="text" id="tfa_code" name="tfa_code" placeholder="••••••" dir="ltr" inputmode="numeric"
                autocomplete="one-time-code" pattern="[0-9۰-۹]{6}" maxlength="6" required autofocus>
            </div>
          </div>
          <p class="lede"><?= htmlspecialchars($L['login2faHint']) ?> · <a href="login.php?tfa=cancel"><?= htmlspecialchars($L['login2faCancel']) ?></a></p>
          <button type="submit" class="submit" id="submit">
            <span><?= htmlspecialchars($L['login2faSubmit']) ?></span>
            <span class="spin" aria-hidden="true"></span>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5m6-6-6 6 6 6"/></svg>
          </button>
        </form>
        <?php else: ?>
        <form id="login-form" method="POST" autocomplete="on">
          <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
          <div class="field">
            <label for="username"><?= htmlspecialchars($L['loginFooter']) ?></label>
            <div class="input">
              <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="7" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg>
              <input type="text" id="username" name="username" placeholder="admin" dir="ltr"
                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" autocomplete="username" autocapitalize="none"
                spellcheck="false" required autofocus maxlength="100">
            </div>
          </div>
          <div class="field">
            <label for="password"><?= htmlspecialchars($L['loginErrorTitle']) ?></label>
            <div class="input">
              <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
              <input type="password" id="password" name="password" placeholder="••••••••" dir="ltr"
                autocomplete="current-password" required maxlength="200">
              <button type="button" class="eye" id="eye" aria-pressed="false"
                aria-label="<?= htmlspecialchars($L['loginPwShow']) ?>"
                data-show="<?= htmlspecialchars($L['loginPwShow']) ?>" data-hide="<?= htmlspecialchars($L['loginPwHide']) ?>">
                <svg class="on" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="off" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 6.1A10 10 0 0 1 12 6c6.5 0 10 6 10 6a17 17 0 0 1-3.2 3.9M6.6 6.6C3.6 8.4 2 12 2 12s3.5 6 10 6c1.7 0 3.2-.4 4.5-1M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
              </button>
            </div>
          </div>
          <label class="remember">
            <input type="checkbox" name="remember" value="1"<?= ($_SERVER['REQUEST_METHOD'] !== 'POST' || !empty($_POST['remember'])) ? ' checked' : '' ?>>
            <span class="box" aria-hidden="true"></span>
            <span><?= htmlspecialchars($L['loginRemember']) ?></span>
          </label>
          <button type="submit" class="submit" id="submit">
            <span><?= htmlspecialchars($L['loginShowPassword']) ?></span>
            <span class="spin" aria-hidden="true"></span>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5m6-6-6 6 6 6"/></svg>
          </button>
        </form>
        <?php endif; ?>
      <?php endif; ?>
      <p class="foot"><?= htmlspecialchars($L['loginHidePassword']) ?></p>
    </section>
  </main>
  <p class="aside credit"><?= htmlspecialchars(trim($L['loginSubtitle'])) ?>
    <a href="https://github.com/DenTeeR/Ciro" target="_blank" rel="noopener"><?= htmlspecialchars($L['loginUsernameLabel']) ?></a>
    <?= htmlspecialchars(trim($L['loginUsernamePlaceholder'])) ?> · © <?= date('Y') ?> <?= htmlspecialchars(trim($L['loginPasswordLabel'])) ?></p>
  <script src="js/login.js?v=<?= (int) @filemtime(__DIR__ . '/js/login.js') ?>"></script>
</body>

</html>
