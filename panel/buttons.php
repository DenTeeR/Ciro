<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/langsync.php';
require_once __DIR__ . '/inc/genbtn_card.php';
require_auth();

// Every other button 🎨 can restyle (genbtn_*: cancel/confirm, close and back
// buttons, the buttons under a message...), one language at a time: its own
// text, colour, emoji and side, simple mode, and - where the bot allows it -
// hidden. Saved into setting.button_edit through the bot's own setters.
$t = $textbotlang['panel'];
$lang = web_lang_pick();
$tx = lang_tab_texts($lang);
$colors = ['' => $t['menuColorDefault'], 'primary' => $t['menuColorBlue'], 'success' => $t['menuColorGreen'], 'danger' => $t['menuColorRed']];
$itemLabels = [];
foreach ($textbotlang['bottext']['items'] as $it) {
    if (!empty($it['key'])) {
        $itemLabels[$it['key']] = $it['label'] ?? $it['key'];
    }
}
// alias => [key, defs]
$groups = [];
foreach (genbtn_alias_map() as $alias => $key) {
    // the 🌐 / 📲 pair and its app list are one setting for every place: one card each
    if (genbtn_canonical_alias($alias) !== $alias) {
        continue;
    }
    $defs = genbtn_defs($alias, $tx);
    if ($defs) {
        $groups[$alias] = [$key, $defs];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $alias = genbtn_canonical_alias((string) ($_POST['alias'] ?? ''));
    $err = null;
    if (!isset($groups[$alias])) {
        $err = $t['btnGroupMissing'];
    } else {
        [$key, $defs] = $groups[$alias];
        $posted = (array) ($_POST['b'] ?? []);
        // checked first, written after: a mistake leaves the group as it was
        foreach ($defs as $idx => $def) {
            $p = (array) ($posted[$idx] ?? []);
            $text = trim((string) ($p['text'] ?? ''));
            $emoji = trim((string) ($p['emoji'] ?? ''));
            if (containsHtmlMarkup($text) || mb_strlen($text) > 64) {
                $err = $t['menuNameInvalid'];
            } elseif ($emoji !== '' && (!preg_match('/^\X$/u', $emoji) || preg_match('/^[0-9a-zA-Z]$/', $emoji))) {
                $err = $t['menuEmojiInvalid'];
            }
        }
        if ($err === null) {
            foreach ($defs as $idx => $def) {
                $p = (array) ($posted[$idx] ?? []);
                $ov = genbtn_override($lang, $key, $idx);
                if ($alias === 'sf' && (int) $idx === 1) {
                    // 💳 under «موجودی کافی نیست»: its look is the confirm page's, only shown/hidden here
                    $wantHidden = ($p['hidden'] ?? '') === '1';
                    if ($wantHidden !== !empty($ov['hidden'])) {
                        genbtn_set_style($lang, $key, $idx, null, null, null, null, null, $wantHidden);
                    }
                    continue;
                }
                $text = trim((string) ($p['text'] ?? ''));
                if ($text === '' || $text === (string) $def['text']) {
                    if (isset($ov['text'])) {
                        genbtn_set_text($lang, $key, $idx, null);
                    }
                } elseif ($text !== ($ov['text'] ?? '')) {
                    genbtn_set_text($lang, $key, $idx, $text);
                }
                if (genbtn_text_only($alias)) {
                    continue;
                }
                $style = (string) ($p['style'] ?? '');
                $emoji = trim((string) ($p['emoji'] ?? ''));
                $pos = ($p['pos'] ?? '') === 'left' ? 'left' : 'right';
                $simple = ($p['simple'] ?? '') === '1';
                $hidden = null;
                if (genbtn_hideable($alias, $idx)) {
                    $wantHidden = ($p['hidden'] ?? '') === '1';
                    $isHidden = array_key_exists('hidden', $ov) ? (bool) $ov['hidden'] : !empty($def['hidden']);
                    if ($wantHidden !== $isHidden) {
                        // a button that ships hidden needs «shown» said out loud
                        $hidden = $wantHidden ? true : (!empty($def['hidden']) ? 'shown' : false);
                    }
                }
                $curStyle = (string) ($ov['style'] ?? '');
                $args = [
                    $style !== $curStyle ? (in_array($style, ['primary', 'success', 'danger'], true) ? $style : '') : null,
                    $emoji !== (string) ($ov['emoji'] ?? '') ? $emoji : null,
                    null,
                    $pos !== ((($ov['pos'] ?? '') === 'left') ? 'left' : 'right') ? $pos : null,
                    $simple !== !empty($ov['simple']) ? $simple : null,
                    $hidden
                ];
                // nothing changed: nothing written
                if (array_filter($args, fn($a) => $a !== null)) {
                    genbtn_set_style($lang, $key, $idx, ...$args);
                }
            }
        }
    }
    flash($err === null ? 'success' : 'error', $err ?? $t['refSaved']);
    // saved from ./subapps.php: back to it
    if (($_POST['return'] ?? '') === 'subapps') {
        header('Location: subapps.php?' . http_build_query(['lang' => $lang]) . '#g-' . $alias);
        exit;
    }
    header('Location: buttons.php?' . http_build_query(['lang' => $lang]) . '#g-' . $alias);
    exit;
}

$pageTitle = $t['btnPageTitle'];
$pageLede = $t['btnPageLede'];
$activeNav = 'buttons';
include __DIR__ . '/inc/layout_head.php';
echo web_lang_assets();
?>

<?= web_lang_tabs($lang, fn($code) => 'buttons.php?' . http_build_query(['lang' => $code])) ?>

<?php foreach ($groups as $alias => [$key, $defs]) {
  genbtn_card($alias, $key, $defs, $lang, $t, $colors, $itemLabels);
} ?>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>