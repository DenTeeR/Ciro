# Data model

Language-neutral. Any store works (a JSON column, a key-value table, files); what matters is the
shape and the lookup order. The examples use JSON.

## 1. Default texts (in the code base, one file per language)

```php
// lang/fa.php
return [
  'users' => [
    'Balance' => [
      'shortLine' => '💡 برای این خرید <b>{need}</b> کم داری.',
      'directPayGone' => '⛔️ پرداخت مستقیم الان برای این مرحله در دسترس نیست.',
    ],
  ],
];
```

A key is the dotted path: `users.Balance.shortLine`. Keys are stable forever; renaming a key
loses every admin override saved under it, so add a new key instead and migrate.

## 2. Overrides (in the database)

```json
{
  "texts":     { "fa": { "users.Balance.shortLine": "💡 فقط {need} مونده!" }, "en": {} },
  "stickers":  { "fa": { "users.text_start": "CAACAgIAAxkBAA…" } },
  "reactions": { "fa": { "users.text_start": "❤️" } },
  "buttons":   { "fa": { "users.sell.service_sell": { "0": {
                    "text": "بستن", "emoji": "❌", "emojiIcon": "5368324170671202286",
                    "pos": "right", "simple": false, "style": "danger", "hidden": false } } } },
  "layout":    { "fa": { "main_menu": { "order": ["buy","test","services"], "rows": [2,1] } } },
  "features":  { "fa": { "dpay_on": "0", "dpay_limits": "0" }, "en": { "dpay_on": "0" } }
}
```

- `emojiIcon` is a premium (custom) emoji id; Telegram shows it on an inline button through
  `icon_custom_emoji_id`, so the text itself carries no emoji then.
- `style` is the Bot API button colour: `success` (green), `danger` (red), `primary` (blue).
- Missing key = default. An empty string saved by mistake also means default.
- Reset of one item deletes its keys in all five maps for that language only.

## 3. Registry (in the code base)

One list drives the bot's 🎨 menu and the web panel's pages.

```php
'dp_1' => ['label' => '💳 متن مرحلهٔ «پرداخت مستقیم»', 'key' => 'users.Balance.directPayCaption',
           'group' => 'buyflow', 'section' => 'confirm'],
'dp_3' => ['label' => '💰 سرویس این پرداخت قبلاً تحویل شده', 'key' => 'users.Balance.paidInvoiceTaken',
           'group' => 'topup', 'section' => 'topup_done'],
```

- `label` is in the admin's language (it is an admin text).
- `group` = a 🎨 group screen; `section` = a white header inside it.
- Button groups get their own entries (a button group id plus the message key it belongs to).
- An item id is never reused for something else.

## 3b. Paths

```php
// one row per path; menus, web panel tabs, layout and switches are built from this list
'paths' => [
  'instagram' => ['label' => '📸 اینستاگرام', 'switch' => 'dl_instagram_on',
                  'texts' => ['dl.instagram.ask', 'dl.instagram.caption', 'dl.instagram.error'],
                  'buttons' => ['dl.instagram.result_buttons']],
  'tiktok'    => ['label' => '🎵 تیک‌تاک',   'switch' => 'dl_tiktok_on',  …],
  'x'         => ['label' => '🐦 توییتر',    'switch' => 'dl_x_on',       …],
],
```

Overrides keep the normal maps (`texts[lang][key]`, `buttons[lang][key]`…). The all-paths
override of a family of keys is stored under the key with `_all` in the path's place
(`dl._all.caption`), so "apply to every path" is one row, and a path can still override it.

## 4. Resolver

```
function text(key, lang, receiver):
    lang = receiver.lang if receiver else lang
    v = overrides.texts[lang][key]           -> if non-empty: return v
    v = defaults[lang][key]                  -> if non-empty: return v
    return defaults[PRIMARY][key] or key     # never empty
```

With paths, a key `family.path.name` is looked up in this order, stopping at the first
non-empty value:

```
overrides.texts[lang]["family.path.name"]      # this path, changed by the admin
overrides.texts[lang]["family._all.name"]      # all paths, changed by the admin
defaults[lang]["family.path.name"]             # this path's default text
defaults[lang]["family._all.name"]             # the shared default text
```

Buttons, stickers, reactions and layout follow the same chain.

Then, when sending: look up sticker/reaction for the same (lang, key), send the sticker first
(remember its message id so the screen can remove it later), then the text, then set the
reaction. Matching extras to a text by key is far more reliable than matching by content; if a
legacy code base only has the final text at send time, match on the literal segments around the
placeholders.

## 5. Button renderer

```
function render(def, ov, callback):
    text  = ov.text or def.text
    style = ov.style or def.style
    if ov.simple:       text = strip_leading_emoji(text)
    elif ov.emojiIcon:  text = strip_leading_emoji(text); icon = ov.emojiIcon
    elif ov.emoji:      text = place(ov.emoji, strip_leading_emoji(text), ov.pos)
    return {text, callback_data: callback, style, icon_custom_emoji_id: icon}
function visible(def, ov): return not ov.hidden or def.required   # back/close are required
```

## 6. Migrations

Add columns and keys idempotently ("add if missing") at install, at update and lazily at first
use. Never assume an upgrade ran. When the stored shape changes, keep reading the old shape.
