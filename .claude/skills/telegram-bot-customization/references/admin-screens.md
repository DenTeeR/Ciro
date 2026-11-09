# Admin screens

All admin screens are edited in place (one message that changes), carry the language tabs when
the content is per language, and end with a way back and `❌ بستن`.

## Callback naming

Short, prefixed, `|`-separated, ≤ 64 bytes (Telegram's limit): `bt_edit|fa|users.text_start`,
`btact|sticker|fa|users.text_start`, `gbs|hub|fa|cf`, `dpay|tog|fa|limits`. One dispatcher per
prefix, early in the update handler, returning "handled" so nothing else runs.

## 🎨 main list

```
🎨 <b>شخصی‌سازی پیام‌های ربات</b>
متنی که می‌خواهی تغییر دهی را انتخاب کن.
⬜️ دکمه‌های سفید فقط تیتر بخش‌ان…
🟢 دکمه سبز یعنی این مورد سفارشی شده: ✏️ متن  ❤️ ری‌اکشن  🖼 استیکر  🔘 دکمه‌ها
[✅🇮🇷 فارسی] [🇬🇧 English]
[💬 پیام‌های عمومی ربات]            ← white header (alert only)
[👋 متن خوش‌آمدید] …               ← items, green when customized
[🛒 پیام‌ها و ظاهر مراحل خرید]      ← a group with its own screen
…
[🔁 ریست همه به پیش‌فرض] [❌ بستن]
```

## Group screen

Explains in a short quote what belongs here and any rule ("product has two captions: with and
without categories; only one is active"), then sections and items. Buttons that edit how the
*buttons* of a flow look ("🎨 ظاهر دکمه‌های …") sit under their own header, apart from texts.

## Path picker (when the bot has paths)

One button per path from the path registry, green when anything in it is customized, then the
shared items:

```
🧭 <b>شخصی‌سازی مسیرها</b>
هر مسیر کپشن و دکمه‌های خودش رو داره؛ یکی رو انتخاب کن.
[✅🇮🇷 فارسی] [🇬🇧 English]
[📸 اینستاگرام] [🎵 تیک‌تاک]       ← green = customized
[🐦 توییتر]     [▶️ یوتیوب]
[🔗 تنظیمات مشترک همهٔ مسیرها]
[🔙 بازگشت] [❌ بستن]
```

## Path group

The group screen for one path: its texts (ask for link, waiting, result caption, error), its
button groups, its sticker. Each item shows where a change applies:

```
🎵 <b>تیک‌تاک</b> — 🇮🇷 فارسی
[📝 کپشن نتیجه]   ← 📍 فقط برای تیک‌تاک
[⏳ پیام «در حال دانلود…»]
[❌ پیام خطا]
[🔘 دکمه‌های زیر نتیجه]
[📋 کپی از مسیر دیگه] [🔗 اعمال روی همهٔ مسیرها]
[🔙 مسیرها]
```

On the item screen of a shared item the status block carries one more line:
`🔗 مشترک بین ۳ مسیر: اینستاگرام، تیک‌تاک، توییتر`.

## Item screen

See SKILL.md. Lines that show state: `✏️ متن: پیش‌فرض / سفارشی`, `🖼 استیکر: دارد ✅ / ندارد ❌`,
`❤️ ری‌اکشن: …`, plus a toggle where the item has one (`✅ استیکر خوش‌آمد روشنه`).

## Text edit prompt

```
✏️ متن جدید رو بفرست.
می‌تونی از ایموجی پریمیوم و فرمت تلگرام استفاده کنی.
<blockquote>متغیرها: {price} قیمت · {balance} موجودی</blockquote>
[🔙 انصراف]
```

On a text that lost a placeholder: save it, but say which placeholder is now missing and that
the value will not appear.

## Button group screen

```
🔘 <b>دکمه‌های صفحهٔ تایید خرید</b> — 🇮🇷 فارسی
[✅ تایید و پرداخت از کیف پول]  ← tap = that button's editor
[💳 پرداخت مستقیم]
[🔙 بازگشت]
[📐 چیدمان] [🔁 ریست]
```

Button editor: `✏️ نام`, `😀 ایموجی`, `💎 ایموجی پریمیوم`, `↔️ سمت ایموجی`, `🎨 رنگ: سبز/قرمز/آبی/پیش‌فرض`,
`🙈 پنهان / 👁 نمایان`, `🔁 ریست این دکمه`. Each tap redraws with the new state visible.

## Main menu buttons (reply keyboard)

The fixed main-menu buttons get their own hub: colour (separately for inline "glass" style and
reply keyboard), emoji or a sticker sent on tap, layout (order and width), name and show/hide.
Renaming a reply-keyboard button must keep the handler matching (match by key, not by the text
the admin typed).

## Features hub (per language)

```
📌 <b>وضعیت قابلیت‌های فروشگاه</b>
هر دکمه یه قابلیت رو روشن یا خاموش می‌کنه؛ کافیه روی دکمه‌اش بزنی.
✅ فعال | ❌ غیرفعال
🌐 این تنظیمات برای هر زبان جداست…
[✅🇮🇷 فارسی] [🇬🇧 English]
[❌ 🔋 وضعیت حجم اضافه]
[✅ 💳 دکمهٔ «پرداخت مستقیم» در صفحهٔ تایید]
[⚙️ تنظیمات پرداخت مستقیم]
```

## What's-new screen

A list of short entries the admin sees after an update: what changed, an example, whether it is
on or off by default, and the exact path (`1️⃣ 🏬 تنظیمات فروشگاه ← 2️⃣ … ← 3️⃣ …`) plus the web
panel path. Written for a beginner.
