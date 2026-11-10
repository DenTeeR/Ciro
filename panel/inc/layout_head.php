<?php
require_once __DIR__ . '/icons.php';
// icons in place of the ⟦i:…⟧ tokens of the page's text (icons.php)
ob_start('ui_rich_filter');
$pageLede = $pageLede ?? '';
$activeNav = $activeNav ?? '';
$showPageHead = $showPageHead ?? true;
$currentUser = $_SESSION['admin_user'] ?? $textbotlang['panel']['layoutDefaultAdminName'];
// the sidebar's admin: their Telegram name and photo (admin_profile.php), the login name until Telegram has answered
require_once __DIR__ . '/admin_profile.php';
$adminProfile = panel_admin_profile();
$adminName = $adminProfile['name'] !== '' ? $adminProfile['name'] : $currentUser;
$adminTip = $adminName . ($adminProfile['username'] !== '' ? ' (@' . $adminProfile['username'] . ')' : '');
$initials = mb_strtoupper(mb_substr($adminName, 0, 1, 'UTF-8'), 'UTF-8');
$navT = $textbotlang['panel'];
// [href, activeNav key, title (tooltip / search), icon, label]
$navItem = fn(string $href, string $key, string $title, string $icon, string $label) =>
  ['href' => $href, 'key' => $key, 'title' => ui_plain($title), 'icon' => $icon, 'label' => ui_plain($label)];
$pageTitlePlain = ui_plain((string) $pageTitle);
$manageItems = [
  $navItem('users.php', 'users', $navT['layoutPageTitleUsers'], 'users', $navT['layoutMenuSectionMain']),
  $navItem('invoice.php', 'invoice', $navT['layoutPageTitleInvoice'], 'invoice', $navT['layoutMenuSectionManagement']),
  $navItem('service.php', 'service', $navT['layoutPageTitleService'], 'shield-check', $navT['layoutMenuSectionFinancial']),
  $navItem('product.php', 'product', $navT['layoutPageTitleProduct'], 'package', $navT['layoutMenuSectionSystem']),
  $navItem('servers.php', 'servers', $navT['srvPageTitle'], 'server', $navT['srvPageTitle']),
  // 🖥 نودهای ربکا: only where there is a Rebecca panel
  ...((function_exists('rbn_panels') && rbn_panels()) ? [$navItem('nodes.php', 'nodes', $navT['rbnPageTitle'], 'network', $navT['rbnPageTitle'])] : []),
  $navItem('category.php', 'category', $navT['categoryPageTitle'], 'folders', $navT['categoryPageTitle']),
  $navItem('payg.php', 'payg', $navT['pgPageTitle'], 'timer', $navT['pgPageTitle']),
  $navItem('customvol.php', 'customvol', $navT['cvPageTitle'], 'puzzle', $navT['cvPageTitle']),
  $navItem('bulkgift.php', 'bulkgift', $navT['bgPageTitle'], 'gift', $navT['bgPageTitle']),
];
if (bulkspeed_available()) { /* 🚀 only while a panel can hold a speed limit (Rebecca) */
  $manageItems[] = $navItem('bulkspeed.php', 'bulkspeed', $navT['bsPageTitle'], 'gauge', $navT['bsPageTitle']);
}
array_push(
  $manageItems,
  $navItem('features.php', 'features', $navT['featPageTitle'], 'toggle-right', $navT['featPageTitle']),
  $navItem('referral.php', 'referral', $navT['refPageTitle'], 'user-plus', $navT['refPageTitle']),
  $navItem('tickets.php', 'tickets', $navT['tkPageTitle'], 'ticket', $navT['tkPageTitle']),
  $navItem('payment.php', 'payment', $navT['layoutPageTitlePayment'], 'card', $navT['layoutSearchBoxPlaceholder']),
  $navItem('channels.php', 'channels', $navT['chnPageTitle'], 'megaphone', $navT['chnPageTitle']),
  $navItem('help.php', 'help', $navT['helpPageTitle'], 'book-open', $navT['helpPageTitle']),
  $navItem('gateways.php', 'gateways', $navT['gwPageTitle'], 'wallet', $navT['gwPageTitle']),
  $navItem('topuplook.php', 'topuplook', $navT['tlPageTitle'], 'message-square-text', $navT['tlPageTitle']),
  $navItem('discounts.php', 'discounts', $navT['discPageTitle'], 'ticket-percent', $navT['discPageTitle']),
  $navItem('menu.php', 'menu', $navT['menuPageTitle'], 'layout-grid', $navT['menuPageTitle']),
  $navItem('buttons.php', 'buttons', $navT['btnPageTitle'], 'mouse-pointer-click', $navT['btnPageTitle']),
  $navItem('looks.php', 'looks', $navT['looksPageTitle'], 'palette', $navT['looksPageTitle']),
  $navItem('svclook.php', 'svclook', $navT['svcPageTitle'], 'messages-square', $navT['svcPageTitle']),
  $navItem('subapps.php', 'subapps', $navT['saPageTitle'], 'smartphone', $navT['saPageTitle']),
  $navItem('bottext.php', 'bottext', $navT['bottextPageTitle'], 'file-pen', $navT['bottextPageTitle']),
  $navItem('tools.php', 'tools', $navT['toolsPageTitle'], 'wrench', $navT['toolsPageTitle']),
  $navItem('cleanup.php', 'cleanup', $navT['clnPageTitle'], 'brush-cleaning', $navT['clnPageTitle']),
  $navItem('backup.php', 'backup', $navT['bkpPageTitle'], 'database-backup', $navT['bkpPageTitle'])
);
$navSections = [
  ['heading' => $navT['layoutNavKeyboard'], 'items' => [
    $navItem('index.php', 'dashboard', $navT['layoutPageTitleDashboard'], 'dashboard', $navT['layoutNavSettings']),
  ]],
  ['heading' => $navT['layoutNavLogout'], 'items' => $manageItems],
  ['heading' => $navT['layoutSidebarToggleLabel'], 'items' => [
    $navItem('settings.php', 'settings', $navT['layoutPageTitleSettings'], 'settings', $navT['layoutProfileMenuLabel']),
    $navItem('logout.php', '', $navT['layoutPageTitleLogout'], 'logout', $navT['layoutNotificationsLabel']),
  ]],
];
$brandMark = panel_brand_mark();
$pageIcon = '';
foreach ($navSections as $navGroup) {
  foreach ($navGroup['items'] as $navEntry) {
    if ($navEntry['key'] !== '' && $navEntry['key'] === $activeNav) {
      $pageIcon = $navEntry['icon'];
    }
  }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#0f0f13" id="mtc">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title><?= $textbotlang['panel']['layoutBrandName'] ?></title>
  <link rel="icon" href="img/icon.svg" type="image/svg+xml">
  <link rel="preload" href="fonts/vazirmatn.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="css/fonts.css?v=<?= (int) @filemtime(__DIR__ . '/../css/fonts.css') ?>">
  <link rel="stylesheet" href="css/style.css?v=<?= (int) @filemtime(__DIR__ . '/../css/style.css') ?>">
  <link rel="stylesheet" href="css/shab.css?v=<?= (int) @filemtime(__DIR__ . '/../css/shab.css') ?>">
  <link rel="stylesheet" href="css/daterange.css?v=<?= (int) @filemtime(__DIR__ . '/../css/daterange.css') ?>">
  <link rel="stylesheet" href="css/ui.css?v=<?= (int) @filemtime(__DIR__ . '/../css/ui.css') ?>">
  <script src="js/theme-init.js?v=<?= (int) @filemtime(__DIR__ . '/../js/theme-init.js') ?>"></script>
  <script>
    window.PANEL_I18N = <?= json_encode(
      array_filter(
        $textbotlang['panel'] ?? [],
        fn($k) => preg_match('/^js[A-Z_]/', (string) $k) === 1,
        ARRAY_FILTER_USE_KEY
      ),
      JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>;
    window.t = function (key, vars) {
      var s = (window.PANEL_I18N && window.PANEL_I18N[key]) || key;
      if (vars) {
        for (var k in vars) {
          if (Object.prototype.hasOwnProperty.call(vars, k)) {
            s = s.replace('{' + k + '}', vars[k]);
          }
        }
      }
      return s;
    };
  </script>
</head>

<body>

  <div id="load-bar"></div>
  <div id="toast-area" role="status" aria-live="polite"></div>

  <div class="confirm-veil" id="confirm-veil">
    <div class="confirm-box">
      <div class="confirm-icon"><?= icon('block', 26) ?></div>
      <h4 id="confirm-title"><?= $textbotlang['panel']['layoutNavDashboard'] ?></h4>
      <p id="confirm-msg"><?= $textbotlang['panel']['layoutNavUsers'] ?></p>
      <div class="confirm-btns">
        <button class="btn btn-no" id="confirm-ok"><?= $textbotlang['panel']['layoutNavOrders'] ?></button>
        <button class="btn btn-ghost"
          onclick="closeConfirm()"><?= $textbotlang['panel']['layoutNavServices'] ?></button>
      </div>
    </div>
  </div>

  <div class="scenery" aria-hidden="true"><div class="ribbon ribbon-one"></div><div class="ribbon ribbon-two"></div><div class="ribbon ribbon-three"></div><div class="grain"></div></div>

  <div class="app">
    <div class="sidebar-backdrop" id="backdrop"></div>

    <aside class="sidebar" id="sidebar" aria-label="<?= htmlspecialchars($textbotlang['panel']['layoutBrandName']) ?>">
      <button type="button" class="sb-collapse" id="sb-collapse" aria-controls="sidebar" aria-expanded="true"
        aria-label="<?= htmlspecialchars($textbotlang['panel']['jsNavCollapse']) ?>"
        title="<?= htmlspecialchars($textbotlang['panel']['jsNavCollapse']) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg></button>
      <div class="sidebar-brand">
        <a class="brand-link" href="index.php">
          <span class="brand-mark"><?= $brandMark ?></span>
          <span class="brand-name"><?= $textbotlang['panel']['layoutNavProducts'] ?><span>
            <?= $textbotlang['panel']['layoutNavPayments'] ?></span></span>
        </a>
      </div>
      <div class="nav-search" id="nav-search">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.7" cy="10.7" r="7.3"/><path d="m16 16 4.6 4.6"/></svg>
        <input type="search" id="nav-search-input" autocomplete="off" spellcheck="false"
          placeholder="<?= htmlspecialchars($textbotlang['panel']['layoutNavSearch']) ?>"
          aria-label="<?= htmlspecialchars($textbotlang['panel']['layoutNavSearch']) ?>">
        <kbd id="nav-search-kbd">Ctrl K</kbd>
      </div>
      <nav class="sidebar-nav" id="sidebar-nav">
        <div class="nav-track" id="nav-track" aria-hidden="true"></div>
        <?php foreach ($navSections as $navGroup): ?>
          <div class="nav-section">
            <div class="nav-heading"><?= $navGroup['heading'] ?></div>
            <?php foreach ($navGroup['items'] as $navEntry):
              $navActive = $navEntry['key'] !== '' && $activeNav === $navEntry['key']; ?>
              <a href="<?= $navEntry['href'] ?>" class="nav-item<?= $navActive ? ' active' : '' ?>"<?= $navActive ? ' aria-current="page"' : '' ?>
                data-title="<?= htmlspecialchars(strip_tags($navEntry['title'])) ?>">
                <span class="nav-icon"><?= icon($navEntry['icon']) ?></span><span
                  class="nav-label"><?= $navEntry['label'] ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
        <p class="nav-empty" id="nav-empty" hidden><?= htmlspecialchars($textbotlang['panel']['layoutNavNoResults']) ?></p>
      </nav>
      <div class="sidebar-foot">
        <a href="settings.php" class="user-pill" id="user-pill" data-title="<?= htmlspecialchars($adminTip) ?>"<?= $adminProfile['stale'] ? ' data-refresh="1"' : '' ?>>
          <div class="user-mono<?= $adminProfile['photo'] !== '' ? ' has-photo' : '' ?>"><?php if ($adminProfile['photo'] !== ''): ?><img src="avatar.php?v=<?= (int) $adminProfile['v'] ?>" alt="" width="36" height="36" decoding="async"><?php else: ?><?= htmlspecialchars($initials) ?><?php endif; ?></div>
          <div class="user-info">
            <div class="uname"><bdi><?= htmlspecialchars($adminName) ?></bdi></div>
            <div class="urole"><?= $textbotlang['panel']['layoutMobileMenuLabel'] ?></div>
          </div>
        </a>
        <?php if ($adminProfile['stale']): ?>
        <script>
          // the Telegram name and photo, asked for once the page is up (Telegram may be slow)
          (function () {
            var pill = document.getElementById('user-pill');
            if (!pill || !window.fetch) { return; }
            fetch('avatar.php?info=1', { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.ok ? r.json() : null; }).then(function (p) {
              if (!p || !p.name) { return; }
              var name = pill.querySelector('.uname bdi'), mono = pill.querySelector('.user-mono');
              name.textContent = p.name;
              pill.setAttribute('data-title', p.name + (p.username ? ' (@' + p.username + ')' : ''));
              if (p.src) {
                var img = new Image(36, 36);
                img.alt = '';
                img.onload = function () { mono.textContent = ''; mono.classList.add('has-photo', 'photo-in'); mono.appendChild(img); };
                img.src = p.src;
              } else {
                mono.textContent = Array.from(p.name)[0].toUpperCase();
              }
            }).catch(function () {});
          })();
        </script>
        <?php endif; ?>
      </div>
    </aside>

    <div class="main">
      <header class="topbar">
        <button type="button" class="icon-btn menu-toggle" onclick="openSidebar()"
          aria-label="<?= htmlspecialchars($textbotlang['panel']['jsNavExpand']) ?>"><?= icon('menu', 18) ?></button>
        <h1 class="topbar-title"><?php if ($pageIcon !== ''): ?><span class="page-ico" aria-hidden="true"><?= icon($pageIcon, 18) ?></span><?php endif; ?><bdi><?= htmlspecialchars($pageTitlePlain) ?></bdi></h1>
      </header>
      <main class="content">
        <?php if ($showPageHead && $pageLede): ?>
          <p class="page-lede fade-up"><?= htmlspecialchars($pageLede) ?></p>
        <?php endif; ?>
        <?php
        $flashOk = get_flash('success');
        $flashNo = get_flash('error');
        $flashWarn = get_flash('warning');
        if ($flashOk): ?>
          <div class="notice notice-ok"><?= htmlspecialchars($flashOk) ?></div><?php endif;
        if ($flashNo): ?>
          <div class="notice notice-no"><?= htmlspecialchars($flashNo) ?></div><?php endif;
        if ($flashWarn): ?>
          <div class="notice notice-warn"><?= htmlspecialchars($flashWarn) ?></div><?php endif; ?>