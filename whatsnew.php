<?php
// 🆕 What each update added - told to every admin once, right after the update
// (bot_whatsnew_announce(), called at the end of table.php).
//
// This is a HISTORY, oldest first. With every update, ADD an entry at the end:
// the next 'n', today's 'date', and what that update added and where to find
// it (Telegram HTML, one <blockquote> per item, no header - the bot adds it).
// Leave the earlier entries: an admin who skipped some updates is told about
// every one he missed, in order. Drop the oldest once there are more than ~15.
return [
    [
        'n' => 1,
        'date' => '2026-10-21',
        'fa' => "<blockquote>☠️ <b>مسدود کردن + حذف همهٔ سرویس‌ها</b>\n"
            . "📍 مدیریت کاربر ← 🔐 دسترسی و احراز\n"
            . "کاربر را در جا مسدود می‌کند و همهٔ سرویس‌هایش را هم از پنل و هم از ربات پاک می‌کند. پیش از اجرا یک بار تأیید می‌گیرد؛ سرویسی که پنل حذفش نکرد با دلیلش گزارش می‌شود.</blockquote>\n\n"
            . "<blockquote>🗑 <b>اصلاح حذف سرویس</b>\n"
            . "📍 مدیریت کاربر ← 🛍 سرویس‌ها و سفارش‌ها\n"
            . "قبلاً اگر پنل جواب نمی‌داد، سرویس در ربات «حذف‌شده» ثبت می‌شد ولی در پنل می‌ماند. حالا فقط وقتی پنل واقعاً حذفش کرد ثبت می‌شود، وگرنه دلیل خطا را می‌بینی و می‌توانی دوباره امتحان کنی.</blockquote>\n\n"
            . "<blockquote>📢 <b>ارسال همگانی هر نوع پیام</b>\n"
            . "📍 پنل ادمین ← ارسال همگانی\n"
            . "نظرسنجی، چک‌لیست، عکس و ویدیو با کپشن، استیکر و متن‌های با قالب‌بندی خاص همان‌طور که هستند برای همه فرستاده می‌شوند. قبل از شروع پیش‌نمایش می‌بینی و آخرش گزارش ارسال/بلاک/ناموفق می‌آید.</blockquote>\n\n"
            . "<blockquote>💵 <b>درگاه FrenzyEx</b>\n"
            . "📍 تنظیمات پرداخت ← 🌐 FrenzyEx\n"
            . "پرداختی که بعد از منقضی‌شدن فاکتور تأیید می‌شود هم دیگر شارژ می‌شود. هر پرداخت تأییدشده‌ای که شارژ نشود در گروه گزارش (تاپیک خطا) خبرش می‌آید.</blockquote>",
        'en' => "<blockquote>☠️ <b>Block + delete all services</b>\n"
            . "📍 Manage user → 🔐 Access\n"
            . "Blocks the user on the spot and removes every service of theirs from both the panel and the bot. Asks once before it runs; a service the panel would not delete is reported with the reason.</blockquote>\n\n"
            . "<blockquote>🗑 <b>Service deletion fixed</b>\n"
            . "📍 Manage user → 🛍 Services and orders\n"
            . "When the panel did not answer, the service used to be marked deleted in the bot but stay in the panel. Now it is marked only once the panel really removed it; otherwise you see the reason and can try again.</blockquote>\n\n"
            . "<blockquote>📢 <b>Broadcast any kind of message</b>\n"
            . "📍 Admin panel → Broadcast\n"
            . "Polls, checklists, photos and videos with captions, stickers and specially formatted texts go out exactly as they are. You see a preview first, and a sent / blocked / failed report at the end.</blockquote>\n\n"
            . "<blockquote>💵 <b>FrenzyEx gateway</b>\n"
            . "📍 Payment settings → 🌐 FrenzyEx\n"
            . "A payment confirmed after its invoice expired is now credited too. Any confirmed payment that is not credited is reported in the report group (errors topic).</blockquote>",
    ],
    [
        'n' => 2,
        'date' => '2026-10-22',
        'fa' => "<blockquote>✅ <b>«آخرین نسخه را داری»</b>\n"
            . "📍 /update یا پنل ادمین ← 🔄 آپدیت ربات\n"
            . "صفحهٔ آپدیت حالا نسخهٔ جدیدتر را از گیت‌هاب می‌پرسد: اگر ربات روی جدیدترین نسخه باشد همین را می‌گوید، و اگر نسخهٔ جدیدتری آماده باشد شمارهٔ آن را نشان می‌دهد.</blockquote>",
        'en' => "<blockquote>✅ <b>\"You're on the latest version\"</b>\n"
            . "📍 /update or Admin panel → 🔄 Update bot\n"
            . "The update screen now asks GitHub for the newest version: if the bot is on it, it says so, and if a newer one is ready it shows its number.</blockquote>",
    ],
    [
        'n' => 3,
        'date' => '2026-10-22',
        'fa' => "<blockquote>🔙 <b>اصلاح دکمهٔ بازگشت در «تنظیم نمایش و کپشن کانفیگ»</b>\n"
            . "📍 🎨 شخصی‌سازی پیام‌ها ← نحوهٔ نمایش کانفیگ (خرید سرویس / اکانت تست / هدیهٔ دعوت)\n"
            . "بعد از عوض‌کردن ترتیب ستون‌ها یا ویرایش یک دکمه، 🔙 گاهی به صفحهٔ «کپشن دریافت کانفیگ» می‌رفت. حالا همیشه به همان صفحه‌ای برمی‌گردد که از آن آمده‌ای.</blockquote>",
        'en' => "<blockquote>🔙 <b>Back button fixed in \"Config display and caption\"</b>\n"
            . "📍 🎨 Customize messages → Config display (purchase / test account / invite gift)\n"
            . "After changing the column order or editing a button, 🔙 sometimes went to the \"config caption\" page. It now always returns to the page you came from.</blockquote>",
    ],
    [
        'n' => 4,
        'date' => '2026-10-22',
        'fa' => "<blockquote>🏬 <b>دستور سریع /shop</b>\n"
            . "📍 هر جا بفرستی ← «🏬 تنظیمات فروشگاه» باز می‌شود. در راهنمای پنل مدیریت هم هست.</blockquote>\n\n"
            . "<blockquote>✅ <b>پیام پایان آپدیت</b>\n"
            . "📍 بعد از هر آپدیت\n"
            . "اعلان آپدیت (دامنه، نسخه، زمان، مسیر بکاپ) و همین «تغییرات این آپدیت» حالا در یک پیام می‌آید.</blockquote>",
        'en' => "<blockquote>🏬 <b>Quick command /shop</b>\n"
            . "📍 Send it anywhere → \"🏬 Shop settings\" opens. It is in the admin panel help too.</blockquote>\n\n"
            . "<blockquote>✅ <b>Update-finished message</b>\n"
            . "📍 After every update\n"
            . "The update notice (domain, version, time, backup path) and this \"What's new\" now arrive in one message.</blockquote>",
    ],
    [
        'n' => 5,
        'date' => '2026-10-22',
        'fa' => "<blockquote>🎁 <b>هدیهٔ شارژ در تأیید دستی رسید</b>\n"
            . "📍 💵 رسیدهای تأییدنشده ← ✅\n"
            . "فاکتور کارت‌به‌کارت (و ارز دیجیتال آفلاین) هدیه‌ای را که موقع ساخته‌شدنش وعده داده یادداشت می‌کند. اگر رسید دیر تأیید شود و تخفیف در این فاصله تمام شده یا سهمیه‌اش پر شده باشد، ✅ باز هم همان مبلغ + همان هدیه را اضافه می‌کند. «افزودن مبلغ دستی» مثل قبل دقیقاً همان مبلغ را بدون هدیه می‌دهد.</blockquote>",
        'en' => "<blockquote>🎁 <b>Top-up gift on manual receipt approval</b>\n"
            . "📍 💵 Unconfirmed receipts → ✅\n"
            . "A card-to-card (and offline crypto) invoice now remembers the gift it promised when it was created. If the receipt is approved late and the discount has ended or its quota is used up meanwhile, ✅ still adds the amount + that gift. \"Add a manual amount\" works as before: exactly the typed amount, no gift.</blockquote>",
    ],
    [
        'n' => 6,
        'date' => '2026-10-23',
        'fa' => "<blockquote>📦 <b>هدیهٔ خودکار فقط روی بسته‌ها</b>\n"
            . "📍 🏬 تنظیمات فروشگاه ← 🏦 بسته‌های شارژ ← 🎁 تخفیف شارژ ← (درگاه یا دسته) ← 🎯 تخفیف خودکار ← «📦 اعمال روی کدوم مبلغ‌ها»\n"
            . "هدیهٔ خودکار را می‌شود روی «همهٔ مبلغ‌ها»، «فقط بسته‌های آماده» یا «فقط یک بسته» گذاشت (هم برای هر درگاه، هم برای تخفیف گروهی و همگانی). کدهای تخفیف همان‌طور که بودند می‌مانند.\n"
            . "وقتی روشنش کنی کپشن‌های کاربر خودشان اعلام می‌کنند: کنار تخفیف در لیست بسته‌ها و روش پرداخت، و یادآوری برای مبلغ دلخواه و فاکتور.</blockquote>\n\n"
            . "<blockquote>🌐 <b>وب‌پنل و 🎨 شخصی‌سازی</b>\n"
            . "📍 وب‌پنل ← مدیریت ← تخفیف شارژ ← «اعمال روی»\n"
            . "📍 🎨 شخصی‌سازی ← 🎁 پیام‌های تخفیف ← «📦 جمله‌ی تخفیف — وقتی فقط روی بسته‌ها ست شده»\n"
            . "همین فیلتر در وب‌پنل هم هست و چهار متنی که کاربر می‌بیند از ربات و وب‌پنل قابل ویرایش است.</blockquote>",
        'en' => "<blockquote>📦 <b>Automatic gift on packages only</b>\n"
            . "📍 🏬 Shop settings → 🏦 Top-up packages → 🎁 Top-up discount → (gateway or category) → 🎯 Automatic discount → «📦 Applies to»\n"
            . "The automatic gift can be set to «every amount», «ready-made packages only» or «one package only» (per gateway, and for the category and all-gateways discounts). Discount codes stay as they were.\n"
            . "When it is on, the customer's captions say so: next to the discount in the packages list and on the payment-method screen, and a note on the custom amount screen and the invoice.</blockquote>\n\n"
            . "<blockquote>🌐 <b>Web panel and 🎨 customization</b>\n"
            . "📍 Web panel → Management → Top-up discount → «Applies to»\n"
            . "📍 🎨 Customize → 🎁 Discount messages → «📦 Discount sentence — when limited to packages»\n"
            . "The same filter is in the web panel, and the four texts the customer sees can be edited from the bot and the web panel.</blockquote>",
    ],
    [
        'n' => 7,
        'date' => '2026-10-24',
        'fa' => "<blockquote>🛠 <b>اصلاح پاپ‌آپ‌ها (Alert)</b>\n"
            . "📍 مثلاً «✅ تایید تمدید» وقتی موجودی کافی نیست\n"
            . "اگر متن پاپ‌آپ را در 🎨 شخصی‌سازی با ایموجی پریمیوم یا بولد نوشته بودی، تلگرام تگ‌هایش را (مثل <code>&lt;tg-emoji …&gt;</code>) عیناً نشان می‌داد. حالا فقط خود متن می‌آید و ایموجی پریمیوم به شکل ایموجی معمولی‌اش دیده می‌شود. روی صفحهٔ ویرایش (ربات و وب‌پنل) هم یادآوری گذاشتم که پاپ‌آپ فقط متن ساده می‌پذیرد و تا ۲۰۰ حرف.</blockquote>",
        'en' => "<blockquote>🛠 <b>Popups (alerts) fixed</b>\n"
            . "📍 e.g. «✅ Confirm renewal» when the balance is not enough\n"
            . "If a popup's text was written in 🎨 Customize with a premium emoji or bold, Telegram showed its tags (like <code>&lt;tg-emoji …&gt;</code>) as typed. Now only the text comes through, and a premium emoji shows as its ordinary emoji. The edit screens (bot and web panel) also remind that a popup takes plain text only, up to 200 characters.</blockquote>",
    ],
    [
        'n' => 8,
        'date' => '2026-10-24',
        'fa' => "<blockquote>🎁 <b>هدیهٔ شارژ در پرداخت مستقیم</b>\n"
            . "📍 مشتری: تمدید / حجم اضافه / زمان اضافه / خرید ← پرداخت از درگاه یا کارت‌به‌کارت\n"
            . "وقتی مشتری موجودی کم داشت و مبلغ را همان‌جا از بخش واریز می‌پرداخت، سرویس خودکار تمدید می‌شد (یا حجم/زمان اضافه می‌شد یا سرویس ساخته می‌شد) ولی هدیهٔ تخفیف شارژ را نمی‌گرفت، در حالی که فاکتور وعده‌اش را داده بود. حالا هدیه بعد از انجام موفق کار به کیف پولش اضافه می‌شود و پیام «🎁 تخفیف اعمال شد!» را می‌بیند. اگر انجام کار با خطا برگردد هدیه‌ای داده نمی‌شود.</blockquote>\n\n"
            . "<blockquote>🌐 <b>گزارش ادمین و 🎨 شخصی‌سازی</b>\n"
            . "📍 🏬 تنظیمات فروشگاه ← 🏦 بسته‌های شارژ ← 🎁 تخفیف شارژ\n"
            . "📍 🎨 شخصی‌سازی ← 💲 پیام و دکمه‌ی تایید شارژ کیف پول ← 🎁 ویرایش متن بلوک تخفیف\n"
            . "قوانین هدیه همان قوانین تخفیف خودکار است (درصد، سقف، محدودیت هر کاربر، فیلتر بسته‌ها) و گزارش مصرف برای ادمین هم می‌آید و در آن نوشته می‌شود که پرداخت مستقیم برای سرویس بوده و فقط هدیه به کیف پول اضافه شده. متن پیام همان متن تخفیف شارژ است که از ربات و وب‌پنل قابل ویرایش است.</blockquote>",
        'en' => "<blockquote>🎁 <b>Top-up gift on direct payment</b>\n"
            . "📍 Customer: renew / extra volume / extra time / purchase → pay from the gateway or card-to-card\n"
            . "When a customer was short of balance and paid the amount right there, the service was renewed (or extra volume/time added, or the service created) automatically, but the top-up gift was never given even though the invoice had promised it. Now the gift is added to their wallet once the job has gone through, and they see «🎁 Discount applied!». If the job fails, no gift is given.</blockquote>\n\n"
            . "<blockquote>🌐 <b>Admin report and 🎨 customization</b>\n"
            . "📍 🏬 Shop settings → 🏦 Top-up packages → 🎁 Top-up discount\n"
            . "📍 🎨 Customize → 💲 Wallet top-up confirmation message and button → 🎁 Edit discount block text\n"
            . "The gift follows the automatic discount rules (percent, cap, per-user limit, package filter), and the admin's per-use report says it was a direct payment for a service and only the gift went to the wallet. The message is the usual top-up discount text, editable from the bot and the web panel.</blockquote>",
    ],
    [
        'n' => 9,
        'date' => '2026-10-24',
        'fa' => "<blockquote>💎 <b>برگشت پول در حجم/زمان اضافهٔ ناموفق</b>\n"
            . "📍 مشتری: سرویس ← 🔋 حجم اضافه / ⏳ زمان اضافه ← پرداخت مستقیم از درگاه یا کارت‌به‌کارت\n"
            . "اگر پنل درخواست حجم یا زمان اضافه را قبول نمی‌کرد، کیف پول مشتری خالی شده بود و پولی برنمی‌گشت. حالا مثل تمدید، هم مبلغ پرداختی و هم موجودی قبلی به کیف پولش برمی‌گردد و پیامی می‌گیرد که مبلغ و موجودی فعلی را می‌گوید. در این حالت هدیهٔ شارژ هم داده نمی‌شود.</blockquote>\n\n"
            . "<blockquote>🌐 <b>وب‌پنل و 🎨 شخصی‌سازی</b>\n"
            . "گزارش خطا برای ادمین مثل قبل در کانال گزارش خطا می‌آید. متن پیام برگشت پول جزو متن‌های داخلی ربات است و تنظیم جدیدی ندارد.</blockquote>",
        'en' => "<blockquote>💎 <b>Refund when extra volume/time fails</b>\n"
            . "📍 Customer: service → 🔋 Extra volume / ⏳ Extra time → pay directly from the gateway or card-to-card\n"
            . "If the panel refused the extra volume or time, the customer's wallet had been emptied and nothing came back. Now, as with renewals, both the payment and the previous balance are returned to the wallet and the customer gets a message with the amount and current balance. No top-up gift is given in that case.</blockquote>\n\n"
            . "<blockquote>🌐 <b>Web panel and 🎨 customization</b>\n"
            . "The error report still goes to the error-report channel for admins. The refund message is one of the bot's built-in texts and has no setting of its own.</blockquote>",
    ],
    [
        'n' => 10,
        'date' => '2026-10-24',
        'fa' => "<blockquote>🛠 <b>گزارش خرید زمان اضافه با درگاه</b>\n"
            . "📍 کانال گزارش ← «کاربر زمان اضافه خریده است»\n"
            . "وقتی مشتری زمان اضافه را با درگاه می‌خرید، در گزارش ادمین تعداد روزِ خریداری‌شده خالی می‌آمد. حالا مثل کارت‌به‌کارت پر می‌شود.</blockquote>",
        'en' => "<blockquote>🛠 <b>Extra-time purchase report via gateway</b>\n"
            . "📍 Report channel → «A user bought extra time»\n"
            . "When a customer bought extra time through a gateway, the admin report left the number of days empty. It is now filled in, as with card-to-card.</blockquote>",
    ],
    [
        'n' => 11,
        'date' => '2026-10-24',
        'fa' => "<blockquote>🛒 <b>دکمه‌های مدیریت سرویس زیر پیام خرید</b>\n"
            . "📍 مشتری: بعد از خرید، پیامی که کانفیگ را می‌فرستد\n"
            . "زیر آن پیام حالا همان دکمه‌های «🛍 سرویس‌های من» هست: 🔗 لینک اشتراک، 🔰 دریافت کانفیگ، 💊 تمدید، ⚙️ تغییر لینک، ➕ حجم/⏳ زمان اضافه و ... — هر کدام فقط اگر برای آن پنل و تنظیمات فروشگاه کاربرد داشته باشد. دکمهٔ 📚 آموزش (اگر روشن باشد) زیر آن‌ها می‌آید.</blockquote>\n\n"
            . "<blockquote>🎨 <b>تنظیم جدا از «سرویس‌های من»</b>\n"
            . "📍 🎨 شخصی‌سازی ← 🛍️ پیام بعد از خرید ← «🔘 ویرایش دکمه‌های مدیریت سرویس»\n"
            . "📍 وب‌پنل ← پیام‌ها و دکمه‌های سرویس ← «دکمه‌های پیام خرید»\n"
            . "نام، رنگ، ایموجی، ترتیب و پنهان‌کردنِ هر دکمه برای پیام خرید جدا تنظیم می‌شود و با تنظیمات صفحهٔ «وضعیت سرویس» یکی نیست. کپشن همین پیام هم مثل قبل از «✏️ ویرایش کپشن» همان صفحه تنظیم می‌شود.</blockquote>",
        'en' => "<blockquote>🛒 <b>Service buttons under the purchase message</b>\n"
            . "📍 Customer: the message that sends the config after buying\n"
            . "That message now carries the same buttons as «🛍 My services»: 🔗 Subscription link, 🔰 Get config, 💊 Renew, ⚙️ Change link, ➕ Extra volume / ⏳ extra time and so on — each one only where it applies to that panel and the shop's settings. The 📚 tutorial button (when it is on) comes below them.</blockquote>\n\n"
            . "<blockquote>🎨 <b>Set apart from «My services»</b>\n"
            . "📍 🎨 Customize → 🛍️ Message after purchase → «🔘 Edit service buttons»\n"
            . "📍 Web panel → Messages and service buttons → «Purchase message buttons»\n"
            . "The name, colour, emoji, order and visibility of each button are set for the purchase message on their own and are not the ones on the service status screen. This message's caption is still edited from «✏️ Edit caption» on the same page.</blockquote>",
    ],
    [
        'n' => 12,
        'date' => '2026-10-24',
        'fa' => "<blockquote>🌐 <b>ورود به سابسکریپشن و 📲 افزودن به برنامه زیر پیام خرید</b>\n"
            . "📍 مشتری: پیامی که بعد از خرید کانفیگ را می‌فرستد\n"
            . "دو دکمهٔ بالای «🛍 سرویس‌های من» هم حالا بالای دکمه‌های مدیریت سرویس همین پیام آمده‌اند. با زدن 📲 پیام خرید (که کانفیگ را دارد) سر جایش می‌ماند و لیست برنامه‌ها پایینش فرستاده می‌شود.</blockquote>\n\n"
            . "<blockquote>🎨 <b>تنظیم جدا</b>\n"
            . "📍 🎨 شخصی‌سازی ← 🛍️ پیام بعد از خرید ← «🌐 دکمه‌های سابسکریپشن و 📲 افزودن به برنامه»\n"
            . "📍 وب‌پنل ← ظاهر دکمه‌ها ← «(پیام خرید)» (و لینکش در تب «دکمه‌های پیام خرید»)\n"
            . "نام، رنگ، ایموجی، چیدمان و پنهان‌کردنِ این دو دکمه برای پیام خرید جدا از دکمه‌های همین دو در «سرویس‌های من» تنظیم می‌شود. لیست برنامه‌هایی که 📲 باز می‌کند همان لیست «خرید» است.</blockquote>",
        'en' => "<blockquote>🌐 <b>Open subscription page and 📲 Add to app under the purchase message</b>\n"
            . "📍 Customer: the message that sends the config after buying\n"
            . "The two buttons on top of «🛍 My services» now sit above the service buttons of this message too. Tapping 📲 leaves the purchase message (which has the config) where it is and sends the app list below it.</blockquote>\n\n"
            . "<blockquote>🎨 <b>Set apart</b>\n"
            . "📍 🎨 Customize → 🛍️ Message after purchase → «🌐 Subscription and 📲 Add to app buttons»\n"
            . "📍 Web panel → Button looks → «(purchase message)» (linked from the «Purchase message buttons» tab)\n"
            . "The name, colour, emoji, layout and visibility of these two buttons are set for the purchase message on their own, apart from the same two in «My services». The app list 📲 opens is the «purchase» one.</blockquote>",
    ],
    [
        'n' => 13,
        'date' => '2026-10-24',
        'fa' => "<blockquote>🔘 <b>دکمه‌های یکسان در هر ۵ جا</b>\n"
            . "📍 پیام خرید، پیام اکانت تست، پیام هدیهٔ رفرال، صفحهٔ ساعتی و 🛍 سرویس‌های من\n"
            . "همه‌جا 🌐 ورود به سابسکریپشن، 📲 افزودن به برنامه، ♻️ بروزرسانی، 🔰 دریافت کانفیگ، 💡 روشن/خاموش و 📊 گزارش مصرف هست. پیام اکانت تست و هدیهٔ رفرال هم حالا دکمه‌های مدیریت سرویس را دارند. 🔗 لینک اشتراک از پیام خرید برداشته شد (خود پیام لینک را دارد).</blockquote>\n\n"
            . "<blockquote>⚙️ <b>پیش‌فرض‌های تازه</b>\n"
            . "📍 🏬 تنظیمات فروشگاه ← وضعیت قابلیت‌ها ← تغییر وضعیت اکانت\n"
            . "💡 روشن/خاموش حالا پیش‌فرض روشن است و یک‌بار برای همه روشن شد (اگر بعداً خاموشش کنی، خاموش می‌ماند). 📝 یادداشت، ❌ بازگشت وجه و ⚠️ گزارش اختلال پیش‌فرض پنهان‌اند و 📚 آموزش هم مثل قبل پیش‌فرض پنهان است؛ هرکدام را از 🎨 می‌شود نشان داد.</blockquote>\n\n"
            . "<blockquote>⏱ <b>💡 برای اشتراک ساعتی</b>\n"
            . "مشتری می‌تواند اشتراک ساعتی را خاموش کند؛ تا وقتی خاموش است هزینه‌ای کم نمی‌شود و حذف هم نمی‌شود. روشن‌کردن دوباره به همان موجودی‌ای نیاز دارد که برای شروع لازم است. 📊 گزارش مصرف هم به صفحه‌اش اضافه شد.</blockquote>\n\n"
            . "<blockquote>🎨 <b>تنظیم جدا و اصلاح نمایش</b>\n"
            . "📍 🎨 شخصی‌سازی ← پیام بعد از دریافت اکانت تست / پیام کامل کانفیگ هدیه ← «🔘 ویرایش دکمه‌های مدیریت سرویس» و «🌐 دکمه‌های سابسکریپشن»\n"
            . "📍 وب‌پنل ← پیام‌ها و دکمه‌های سرویس ← «دکمه‌های پیام دریافت» (خرید / اکانت تست / هدیهٔ دعوت)\n"
            . "در «✏️ نام و نمایش دکمه‌ها» علامت کنار هر دکمه حالا وضعیت واقعی را نشان می‌دهد: 👁 دیده می‌شود، 🚫 پنهان است. 🔒 یعنی قابلیتش در تنظیمات فروشگاه خاموش است.</blockquote>",
        'en' => "<blockquote>🔘 <b>The same buttons in all 5 places</b>\n"
            . "📍 Purchase message, test account message, referral gift message, hourly screen and 🛍 My services\n"
            . "Every one now has 🌐 Open subscription page, 📲 Add to app, ♻️ Update, 🔰 Get config, 💡 On/off and 📊 Usage report. The test account and referral gift messages now carry the service buttons too. 🔗 Subscription link is gone from the purchase message (the message itself has the link).</blockquote>\n\n"
            . "<blockquote>⚙️ <b>New defaults</b>\n"
            . "📍 🏬 Shop settings → Feature status → Change account status\n"
            . "💡 On/off is now on by default and was turned on once for everyone (turn it off later and it stays off). 📝 Note, ❌ Refund and ⚠️ Disruption report ship hidden, and 📚 Tutorial is still hidden by default; 🎨 can show any of them.</blockquote>\n\n"
            . "<blockquote>⏱ <b>💡 for hourly subscriptions</b>\n"
            . "Customers can turn an hourly subscription off; nothing is charged and it is not deleted while it is off. Turning it on again needs the same balance a start needs. 📊 Usage report was added to its screen too.</blockquote>\n\n"
            . "<blockquote>🎨 <b>Set apart, and shown right</b>\n"
            . "📍 🎨 Customize → Message after a test account / Full referral gift message → «🔘 Edit service buttons» and «🌐 Subscription buttons»\n"
            . "📍 Web panel → Messages and service buttons → «Delivery message buttons» (purchase / test account / referral gift)\n"
            . "In «✏️ Names and visibility» the mark next to each button now shows its real state: 👁 shown, 🚫 hidden. 🔒 means its feature is off in the shop settings.</blockquote>",
    ],
    [
        'n' => 14,
        'date' => '2026-10-24',
        'fa' => "<blockquote>💡 <b>رنگ دکمهٔ روشن/خاموش</b>\n"
            . "📍 هر ۵ جا: پیام خرید، اکانت تست، هدیهٔ رفرال، صفحهٔ ساعتی و 🛍 سرویس‌های من\n"
            . "وقتی اشتراک روشن است دکمه آبی است و وقتی خاموش شد قرمز می‌شود. اگر از 🎨 برای این دکمه رنگ دیگری انتخاب کرده باشی، همان رنگ می‌ماند.</blockquote>\n\n"
            . "<blockquote>🛍 <b>چیدمان تازهٔ «پیام‌ها و ظاهر سرویس‌های من»</b>\n"
            . "📍 🎨 شخصی‌سازی ← پیام‌ها و ظاهر سرویس‌های من\n"
            . "📍 وب‌پنل ← متن‌های ربات ← 🛍 پیام‌ها و ظاهر سرویس‌های من\n"
            . "موارد به ترتیبی که مشتری می‌بیند مرتب شدند: 1️⃣ لیست سرویس‌ها، 2️⃣ صفحهٔ هر سرویس (کپشن، دکمه‌ها و 🌐 / 📲)، 3️⃣ صفحه‌هایی که دکمه‌ها باز می‌کنند و ⚠️ خطاها و پاپ‌آپ‌ها.</blockquote>\n\n"
            . "<blockquote>🔙 <b>بازگشت به منوی قبل</b>\n"
            . "صفحه‌هایی که از دو جا باز می‌شوند (مثل کپشن‌های درگاه، فاکتور خرید و دکمه‌های دریافت کانفیگ) حالا با 🔙 به همان صفحه‌ای برمی‌گردند که از آن آمده بودی، حتی بعد از ذخیرهٔ تغییر.</blockquote>\n\n"
            . "<blockquote>🛠 <b>رفع اشکال‌های 🎨</b>\n"
            . "• متنی که با فرمت تلگرام (بولد، ایتالیک، لینک، نقل‌قول و...) فرستاده شود، با همان فرمت ذخیره می‌شود.\n"
            . "• ذخیرهٔ استیکر و ری‌اکشن روی بعضی پیام‌ها بی‌صدا شکست می‌خورد؛ درست شد.\n"
            . "• تغییر نام و ایموجی 🌐 / 📲 در پیام خرید، اکانت تست و هدیهٔ رفرال ذخیره نمی‌شد؛ درست شد.\n"
            . "• چهار دکمهٔ رنگ و نام دکمه‌های فاکتور کارت‌به‌کارت کار نمی‌کردند؛ درست شدند.\n"
            . "• وقتی تنظیمی بلند می‌شد ربات خطا می‌داد؛ حالا جای ذخیره خودش بزرگ‌تر می‌شود.</blockquote>",
        'en' => "<blockquote>💡 <b>On/off button colour</b>\n"
            . "📍 All 5 places: purchase message, test account, referral gift, hourly screen and 🛍 My services\n"
            . "The button is blue while the subscription is on and turns red once it is off. A colour you picked for it in 🎨 still wins.</blockquote>\n\n"
            . "<blockquote>🛍 <b>«My services messages and look», reordered</b>\n"
            . "📍 🎨 Customize → My services messages and look\n"
            . "📍 Web panel → Bot Texts → 🛍 My services messages and look\n"
            . "Items now follow what the customer sees: 1️⃣ the list of services, 2️⃣ one service's screen (caption, buttons and 🌐 / 📲), 3️⃣ the screens its buttons open, and ⚠️ errors and popups.</blockquote>\n\n"
            . "<blockquote>🔙 <b>Back to the previous menu</b>\n"
            . "Screens that open from two places (gateway captions, the purchase invoice, the get-config buttons and more) now go 🔙 back to the screen you came from, even after saving a change.</blockquote>\n\n"
            . "<blockquote>🛠 <b>🎨 fixes</b>\n"
            . "• Text sent with Telegram formatting (bold, italic, links, quotes...) is saved with that formatting.\n"
            . "• Saving a sticker or reaction on some messages failed silently; fixed.\n"
            . "• Renaming 🌐 / 📲 or changing their emoji on the purchase, test account and referral gift messages was not saved; fixed.\n"
            . "• Four colour and name buttons of the card-to-card invoice did nothing; fixed.\n"
            . "• The bot errored once a setting grew long; its storage now grows by itself.</blockquote>",
    ],
    [
        'n' => 15,
        'date' => '2026-10-24',
        'fa' => "<blockquote>💡 <b>روشن/خاموش روی سرویسی که هنوز وصل نشده</b>\n"
            . "📍 هر ۵ جا: پیام خرید، اکانت تست، هدیهٔ رفرال، صفحهٔ ساعتی و 🛍 سرویس‌های من\n"
            . "اگر مشتری روی «💡 روشن/خاموش» سرویسی بزند که هنوز بهش وصل نشده (OnHold)، دیگر پیام جدید زیر صفحه فرستاده نمی‌شود؛ یک پاپ‌آپ (Alert) روی همان صفحه نشان داده می‌شود که می‌گوید اول باید یک‌بار وصل شود و بعد می‌تواند استفاده کند. در اشتراک ساعتی هم همین حالت پاپ‌آپ دارد.</blockquote>\n\n"
            . "<blockquote>🎨 <b>متن پاپ‌آپ قابل تنظیم است</b>\n"
            . "📍 🎨 شخصی‌سازی ← پیام‌ها و ظاهر سرویس‌های من ← ⚠️ خطاها و پاپ‌آپ‌ها ← «⏳ Alert هنوز به سرویس وصل نشدی»\n"
            . "📍 وب‌پنل ← متن‌های ربات ← 🛍 پیام‌ها و ظاهر سرویس‌های من\n"
            . "پاپ‌آپ فقط متن ساده می‌گیرد و تا ۲۰۰ حرف نشان داده می‌شود. متن پیش‌فرض هم تازه و روان‌تر شد.</blockquote>",
        'en' => "<blockquote>💡 <b>On/off on a service that is not connected yet</b>\n"
            . "📍 All 5 places: purchase message, test account, referral gift, hourly screen and 🛍 My services\n"
            . "When a customer taps «💡 On/off» on a service they have not connected to yet (OnHold), no new message is sent under the screen any more; a popup (Alert) shows on that same screen telling them to connect once first and then use it. The hourly subscription has the same popup for this state.</blockquote>\n\n"
            . "<blockquote>🎨 <b>The popup text can be edited</b>\n"
            . "📍 🎨 Customize → My services messages and look → ⚠️ Errors and popups → «⏳ Alert: not connected yet»\n"
            . "📍 Web panel → Bot Texts → 🛍 My services messages and look\n"
            . "A popup takes plain text only and shows up to 200 characters. The default text is also new and friendlier.</blockquote>",
    ],
    [
        'n' => 16,
        'date' => '2026-10-24',
        'fa' => "<blockquote>🧹 <b>فقط فارسی و انگلیسی</b>\n"
            . "زبان‌های روسی، چینی و ترکمنی کامل از ربات و وب‌پنل برداشته شد: فایل‌های زبانشان، دکمه‌ها و گزینه‌های انتخاب زبان، پیش‌شماره‌ی تلفن، ارز پیش‌فرض و ساعت هر زبان.\n"
            . "کاربرانی که روی یکی از این زبان‌ها بودند خودکار روی فارسی می‌روند (زبانی که هر متن بدون ترجمه هم به آن برمی‌گردد). تنظیمات ذخیره‌شده‌ی این زبان‌ها دست نخورده می‌ماند و هیچ جا نشان داده نمی‌شود.</blockquote>",
        'en' => "<blockquote>🧹 <b>Persian and English only</b>\n"
            . "Russian, Chinese and Turkmen are fully removed from the bot and the web panel: their language files, the buttons and choices for picking a language, the phone dial codes, the default currency and the time zone of each.\n"
            . "Users who were on one of them move to Persian automatically (the language every untranslated text already falls back to). Settings saved for those languages are left in place and are not shown anywhere.</blockquote>",
    ],
    [
        'n' => 17,
        'date' => '2026-10-24',
        'fa' => "<blockquote>💡 <b>رنگ دکمهٔ روشن/خاموش: سبز، قرمز، آبی</b>\n"
            . "📍 هر ۵ جا: پیام خرید، اکانت تست، هدیهٔ رفرال، صفحهٔ ساعتی و 🛍 سرویس‌های من\n"
            . "🟢 سبز وقتی اشتراک روشن است، 🔴 قرمز وقتی خاموش شد (یا موجودی اشتراک ساعتی تمام شد) و 🔵 آبی تا وقتی هنوز به کانفیگ وصل نشده (OnHold). پیام‌های تحویل (خرید، تست، هدیه) هم حالا وضعیت واقعی سرویس را از پنل می‌پرسند، پس سرویس OnHold همان لحظهٔ اول آبی است.\n"
            . "رنگی که از 🎨 برای این دکمه انتخاب کرده باشی همان می‌ماند.</blockquote>",
        'en' => "<blockquote>💡 <b>On/off button colour: green, red, blue</b>\n"
            . "📍 All 5 places: purchase message, test account, referral gift, hourly screen and 🛍 My services\n"
            . "🟢 Green while the subscription is on, 🔴 red once it is off (or an hourly subscription ran out of balance) and 🔵 blue while it has not connected to the config yet (OnHold). The delivery messages (purchase, test, gift) now ask the panel for the real state too, so an OnHold service is blue from the first moment.\n"
            . "A colour you picked for this button in 🎨 still wins.</blockquote>",
    ],
    [
        'n' => 18,
        'date' => '2026-10-24',
        'fa' => "<blockquote>💡 <b>روشن/خاموش بدون تاییدیه</b>\n"
            . "📍 پیام خرید، اکانت تست، هدیهٔ رفرال و 🛍 سرویس‌های من (اشتراک ساعتی از قبل بدون تاییدیه بود)\n"
            . "با یک بار زدن روی «💡 روشن/خاموش» سرویس همان لحظه روشن یا خاموش می‌شود؛ سؤال «مطمئنی؟» و صفحهٔ جداگانهٔ تایید حذف شد. یک پیام کوتاه (روشن شد / خاموش شد) نشان داده می‌شود و خود دکمه همان‌جا عوض می‌شود: 🟢 سبز وقتی روشن است، 🔴 قرمز وقتی خاموش. در 🛍 سرویس‌های من کل صفحه (از جمله خط وضعیت) هم تازه می‌شود و در پیام خرید، تست و هدیه فقط خود دکمه عوض می‌شود.\n"
            . "دکمه‌های تایید در پیام‌های قدیمی هنوز کار می‌کنند. تایید در پنل مدیر (مدیریت سفارش کاربر) تغییری نکرده است.</blockquote>",
        'en' => "<blockquote>💡 <b>On/off without a confirmation</b>\n"
            . "📍 Purchase message, test account, referral gift and 🛍 My services (the hourly subscription already had none)\n"
            . "One tap on «💡 On/off» switches the service right away; the «are you sure?» question and its separate confirm screen are gone. A short note (turned on / turned off) is shown and the button itself changes on the spot: 🟢 green while it is on, 🔴 red while it is off. In 🛍 My services the whole screen (status line included) is refreshed too; in the purchase, test and gift messages only the button changes.\n"
            . "Confirm buttons on older messages still work. The confirmation in the admin panel (managing a user's order) is unchanged.</blockquote>",
    ],
    [
        'n' => 19,
        'date' => '2026-10-24',
        'fa' => "<blockquote>⚡️ <b>روشن/خاموش کردن اشتراک سریع‌تر شد</b>\n"
            . "📍 پیام خرید، اکانت تست، هدیهٔ رفرال و 🛍 سرویس‌های من\n"
            . "قبلاً هر بار زدن 💡 پنل را چند بار از اول صدا می‌زد (چهار بار وضعیت سرویس را می‌پرسید). حالا یک بار وضعیت را می‌پرسد، همان را برای تغییر و برای رسم دوبارهٔ صفحه به کار می‌برد و فقط یک درخواست تغییر می‌فرستد. پیام «روشن/خاموش شد» و رنگ تازهٔ دکمه اول می‌آید و بعد صفحهٔ کامل در همان پیام تازه می‌شود.\n"
            . "اگر پنل تغییر را قبول نکند، دکمه عوض نمی‌شود و پیام «امکان تغییر وجود ندارد» می‌آید (قبلاً گاهی خطای پنل دیده نمی‌شد).</blockquote>",
        'en' => "<blockquote>⚡️ <b>Turning a subscription on/off is faster</b>\n"
            . "📍 Purchase message, test account, referral gift and 🛍 My services\n"
            . "Every tap on 💡 used to call the panel over and over (it asked for the service's status four times). Now it asks once, uses that answer for the switch and for redrawing the screen, and sends a single change request. The «turned on/off» note and the button's new colour come first, then the full screen is refreshed in the same message.\n"
            . "If the panel refuses the change, the button stays as it was and a «could not be changed» note is shown (a panel error used to go unnoticed at times).</blockquote>",
    ],
    [
        'n' => 20,
        'date' => '2026-10-24',
        'fa' => "<blockquote>💡 <b>رنگ دکمهٔ روشن/خاموش: آبی و قرمز</b>\n"
            . "📍 هر ۵ جا: پیام خرید، اکانت تست، هدیهٔ رفرال، صفحهٔ ساعتی و 🛍 سرویس‌های من\n"
            . "🔵 آبی وقتی اشتراک روشن است (یا هنوز به کانفیگ وصل نشده، OnHold) و 🔴 قرمز وقتی خاموش شد. رنگ سبز برداشته شد. رنگی که از 🎨 برای این دکمه انتخاب کرده باشی همان می‌ماند.</blockquote>",
        'en' => "<blockquote>💡 <b>On/off button colour: blue and red</b>\n"
            . "📍 All 5 places: purchase message, test account, referral gift, hourly screen and 🛍 My services\n"
            . "🔵 Blue while the subscription is on (or has not connected to the config yet, OnHold) and 🔴 red once it is turned off. Green is gone. A colour you picked for this button in 🎨 still wins.</blockquote>",
    ],
    [
        'n' => 21,
        'date' => '2026-10-24',
        'fa' => "<blockquote>📲 <b>«ورود به سابسکریپشن» و «افزودن به برنامه»: یک تنظیم برای همه‌ی بخش‌ها</b>\n"
            . "📍 🎨 شخصی‌سازی ← پیام‌ها و ظاهر سرویس‌های من ← «🌐 دکمه‌های سابسکریپشن و 📲 افزودن به برنامه» و «📲 لیست برنامه‌ها»\n"
            . "📍 وب‌پنل ← ظاهر دکمه‌ها ← همان دو کارت\n"
            . "پیام خرید، پیام اکانت تست، پیام کانفیگ هدیهٔ رفرال، صفحهٔ ساعتی و 🛍 سرویس‌های من حالا همه یک جفت دکمه و یک لیست برنامه دارند. هرچه در تب فارسی عوض کنی (نام، ایموجی، رنگ، چیدمان، مخفی کردن، کپشن لیست و دکمه‌های برنامه‌ها) در هر ۵ جا با همان تغییر دیده می‌شود؛ تب انگلیسی تنظیم جدای خودش را دارد.\n"
            . "هر پیام (بعد از خرید، اکانت تست، هدیه، گروه رفرال و ساعتی) فقط یک ردیف دارد که به همین تنظیم مشترک می‌رود و 🔙 به همان صفحه‌ای برمی‌گردد که از آن آمده‌ای.\n"
            . "آنچه قبلاً برای هر بخش جدا ذخیره کرده بودی، به تنظیم مشترک همان زبان منتقل شد.</blockquote>",
        'en' => "<blockquote>📲 <b>«Open subscription page» and «Add to app»: one setting for every place</b>\n"
            . "📍 🎨 Customize → My services messages and look → «🌐 Subscription and 📲 Add to app buttons» and «📲 The app list»\n"
            . "📍 Web panel → Button looks → those two cards\n"
            . "The purchase message, the test account message, the referral gift config message, the hourly screen and 🛍 My services now all have one pair of buttons and one app list. Whatever you change on the Persian tab (name, emoji, colour, layout, hiding, the list's caption and the app buttons) shows in all 5 places; the English tab has a setting of its own.\n"
            . "Each message (after purchase, test account, gift, the referral group and the hourly one) has just one row that opens this shared setting, and 🔙 goes back to the screen you came from.\n"
            . "What you had saved separately for each place was moved to that language's shared setting.</blockquote>",
    ],
    [
        'n' => 22,
        'date' => '2026-10-24',
        'fa' => "<blockquote>🌐 <b>ظاهر دکمه‌های هشدار و اتمام: جدا برای هر زبان</b>\n"
            . "📍 🎨 شخصی‌سازی ← 🔋 پیام‌های هشدار و اتمام سرویس؛ وب‌پنل ← ظاهر سرویس ← هشدارها\n"
            . "رنگ، ایموجی، جای دکمه، حالت ساده و مخفی بودنِ دکمهٔ هر هشدار (و دکمهٔ «اکانت تست تمام شد») قبلاً بین فارسی و انگلیسی مشترک بود؛ حالا هر زبان ظاهر خودش را دارد. چیزی که تا حالا نمایش داده می‌شد برای هر دو زبان کپی شد، پس تا وقتی عوضش نکنی تغییری نمی‌بینی.\n"
            . "فقط آستانه‌ها (درصد، گیگ، روز) بین زبان‌ها مشترک مانده‌اند، چون تعیین می‌کنند پیام کی فرستاده شود.</blockquote>",
        'en' => "<blockquote>🌐 <b>Warning and end-of-service button looks: separate per language</b>\n"
            . "📍 🎨 Customize → 🔋 Warning and end-of-service messages; Web panel → Service look → Warnings\n"
            . "The colour, emoji, side, simple mode and hiding of each warning's button (and the «test account ended» button) used to be shared by Persian and English; each language has its own look now. What was showing was copied to both languages, so nothing changes until you change it.\n"
            . "Only the thresholds (percent, GB, days) stay shared between languages, since they decide when a message is sent.</blockquote>",
    ],
    [
        'n' => 23,
        'date' => '2026-10-24',
        'fa' => "<blockquote>📲 <b>صفحه‌ی باز کردن برنامه: طرح شیشه‌ای تازه</b>\n"
            . "📍 🎨 شخصی‌سازی ← 📲 افزودن سرویس به برنامه؛ وب‌پنل ← پیام‌های ربات ← 📲 افزودن سرویس به برنامه\n"
            . "صفحه‌ای که با زدن یک برنامه در «📲 افزودن به برنامه» باز می‌شود تازه شد: طرح شیشه‌ای با فونت فارسی «استعداد» (از خود سرور لود می‌شود، بدون فیلترشکن و CDN)، آیکون و رنگ مخصوص هر برنامه و انیمیشنِ رفتن اشتراک به داخل برنامه؛ اگر برنامه روی گوشی نبود صفحه همان‌جا نشان می‌دهد و راه‌حل می‌دهد.\n"
            . "زیر دکمه‌ی دانلود لینک فروشگاه‌ها هست: برای اندروید Google Play، و برای برنامه‌ای که در گوگل‌پلی نیست (مثل v2rayNG و Karing) آخرین نسخه‌ی GitHub؛ برای آیفون App Store؛ و سایت رسمی برنامه. متن‌های جدید صفحه (سرویس، برنامه روی گوشیت پیدا نشد، دوباره امتحان کن و …) هم برای فارسی و انگلیسی جدا قابل ویرایش‌اند.</blockquote>",
        'en' => "<blockquote>📲 <b>App-opening page: a new glass design</b>\n"
            . "📍 🎨 Customize → 📲 Add service to app; Web panel → Bot messages → Add service to app\n"
            . "The page that opens when a customer taps an app in «📲 Add to app» is redesigned: a glass look with the Persian font Estedad (served from your own server - no VPN or CDN needed), each app's own icon and colour, and an animation of the subscription travelling into the app; if the app is not on the phone the page says so right there and shows what to do.\n"
            . "Under the download button are the stores: Google Play on Android, the latest GitHub release for an app that is not on Google Play (v2rayNG, Karing), the App Store on iPhone, and the app's own site. The new page texts (service, app not found on your phone, try again, …) are editable separately for Persian and English.</blockquote>",
    ],
    [
        'n' => 24,
        'date' => '2026-10-24',
        'fa' => "<blockquote>📲 <b>«افزودن سرویس به برنامه» یک بخش جدا در 🎨 شخصی‌سازی دارد</b>\n"
            . "📍 🎨 شخصی‌سازی ← 📲 افزودن سرویس به برنامه؛ وب‌پنل ← پیام‌های ربات ← 📲 افزودن سرویس به برنامه\n"
            . "چون «🌐 سابسکریپشن / 📲 افزودن به برنامه» برای هر ۵ مسیر (خرید، تست، هدیه‌ی دعوت، اشتراک ساعتی و سرویس‌های من) یکی است، تنظیماتش از «🛍 سرویس‌های من» بیرون آمد و جای خودش را دارد: دکمه‌های زیر سرویس، پیام لیست برنامه‌ها و دکمه‌هایش، صفحه‌ی وبی که برنامه را باز می‌کند (باز شدن، برنامه پیدا نشد، دانلود و فروشگاه‌ها) و پاپ‌آپ‌ها، همه در یک جا و برای فارسی و انگلیسی جدا.\n"
            . "توی هر ۵ بخش (پیام بعد از خرید، اکانت تست، هدیه‌ی دعوت، اشتراک ساعتی و سرویس‌های من) هم زیر عنوان «🌐 سابسکریپشن و 📲 افزودن به برنامه» یک دکمه‌ی «📲 افزودن سرویس به برنامه — زبان» هست که همین بخش را باز می‌کند و 🔙 برت می‌گرداند همان‌جا که بودی. هیچ تنظیمی عوض نشد.</blockquote>",
        'en' => "<blockquote>📲 <b>«Add service to app» has its own section in 🎨 Customize</b>\n"
            . "📍 🎨 Customize → 📲 Add service to app; Web panel → Bot messages → Add service to app\n"
            . "«🌐 Subscription / 📲 Add to app» is one and the same for all 5 paths (purchase, test, invite gift, hourly subscription and My services), so its settings left «🛍 My services» and have a place of their own: the buttons under a service, the app-list message and its buttons, the web page that opens the app (opening, app not found, download and stores) and the popups - all in one place, separate for Persian and English.\n"
            . "Each of the 5 paths (purchase message, test account, invite gift, hourly subscription and My services) shows a «📲 Add service to app — language» button under the «🌐 Subscription and 📲 Add to app» heading that opens this section, and 🔙 takes you back to where you were. No setting changed.</blockquote>",
    ],
    [
        'n' => 25,
        'date' => '2026-10-24',
        'fa' => "<blockquote>🖼 <b>صفحه‌ی باز کردن برنامه: دو طرح، انتخاب با خودت</b>\n"
            . "📍 🎨 شخصی‌سازی ← 📲 افزودن سرویس به برنامه ← «🖼 طرح صفحه‌ی باز کردن برنامه»؛ وب‌پنل ← پیام‌های ربات ← 📲 افزودن سرویس به برنامه\n"
            . "صفحه‌ای که با زدن یک برنامه در «📲 افزودن به برنامه» باز می‌شود حالا دو طرح دارد: ✨ A شیشه‌ای (همان قبلی) و ⚪ B مینیمال با زمینه‌ی ساده، دکمه‌ی گرد و دانلود به شکل یک ردیف. فونت، متن‌ها و انیمیشن هر دو یکی است و فقط تم فرق می‌کند.\n"
            . "طرح برای هر زبان جدا انتخاب می‌شود (فارسی یکی، انگلیسی یکی)، و کنار هر طرح یک «👁 پیش‌نمایش» هست که همان صفحه را با اطلاعات نمونه باز می‌کند. پیش‌فرض همان A است و تا انتخاب نکنی چیزی عوض نمی‌شود.</blockquote>",
        'en' => "<blockquote>🖼 <b>App-opening page: two looks, you choose</b>\n"
            . "📍 🎨 Customize → 📲 Add service to app → «🖼 The look of the app-opening page»; Web panel → Bot messages → Add service to app\n"
            . "The page that opens when a customer taps an app in «📲 Add to app» now has two looks: ✨ A Glass (the one you had) and ⚪ B Minimal, with a plain background, a pill button and the download as a row. The font, the words and the motion are the same in both; only the theme differs.\n"
            . "The look is chosen per language (one for Persian, one for English), and each look has a «👁 Preview» that opens that page with sample data. A stays the default, so nothing changes until you choose.</blockquote>",
    ],
    [
        'n' => 26,
        'date' => '2026-10-24',
        'fa' => "<blockquote>🌐 <b>وب‌پنل: صفحه‌ی «افزودن سرویس به برنامه»</b>\n"
            . "📍 وب‌پنل ← افزودن سرویس به برنامه\n"
            . "همه‌ی تنظیمات «📲 افزودن به برنامه» حالا مثل بخش خودش در 🎨 شخصی‌سازی یک‌جاست: طرح صفحه (شیشه‌ای یا مینیمال، با پیش‌نمایش «باز می‌شود / برنامه پیدا نشد / باز شد»)، دکمه‌های زیر سرویس، دکمه‌ی هر برنامه در لیست، متن پیام لیست، متن‌های صفحه‌ی باز کردن برنامه (دانلود و فروشگاه‌ها هم) و پاپ‌آپ‌ها، با استیکر و ری‌اکشن. برای هر زبان جدا و یکی برای هر ۵ بخش.\n"
            . "یک دکمه‌ی «ریست» هم دارد که همه‌ی این بخش را برای همان زبان به پیش‌فرض برمی‌گرداند (در ربات هم «🔁 ریست همه به پیش‌فرض» حالا طرح صفحه را هم برمی‌گرداند). از صفحه‌های اکانت ساعتی، زیرمجموعه‌گیری و ظاهر سرویس هم به آن لینک هست.</blockquote>",
        'en' => "<blockquote>🌐 <b>Web panel: the «Add service to app» page</b>\n"
            . "📍 Web panel → Add service to app\n"
            . "Everything about «📲 Add to app» is now in one place, like its section in 🎨 Customize: the look of the page (Glass or Minimal, with previews of «opening / app not found / opened»), the buttons under a service, each app's button in the list, the list message, the words of the app-opening page (download and the stores too) and the popups, with stickers and reactions. Separate for each language and one for all 5 places.\n"
            . "A «Reset» button puts this whole section back to its defaults for that language (in the bot, «🔁 Reset all to default» now resets the page's look too). The hourly subscription, referral and service-look pages link to it.</blockquote>",
    ],
    [
        'n' => 27,
        'date' => '2026-10-24',
        'fa' => "<blockquote>🖼 <b>صفحه‌ی باز کردن برنامه: طرح A در حالت روشن واقعاً شیشه‌ای شد</b>\n"
            . "📍 🎨 شخصی‌سازی ← 📲 افزودن سرویس به برنامه ← «🖼 طرح صفحه»\n"
            . "روی گوشی‌هایی که حالت روشن دارند، طرح A تقریباً سفید بود و شبیه B دیده می‌شد. حالا رنگ خود برنامه مثل یک شفق روی صفحه پخش می‌شود و کارت شیشه‌ی مات روی آن می‌نشیند؛ B همچنان ساده و یک‌دست می‌ماند. حالت تیره تغییری نکرد.</blockquote>",
        'en' => "<blockquote>🖼 <b>App-opening page: look A is now truly glass in light mode</b>\n"
            . "📍 🎨 Customize → 📲 Add service to app → «🖼 The look of the page»\n"
            . "On phones in light mode, look A was almost white and looked like B. The app's own colour now washes across the page like an aurora with a frosted glass card over it; B stays plain and flat. Dark mode did not change.</blockquote>",
    ],
    [
        'n' => 28,
        'date' => '2026-10-24',
        'fa' => "<blockquote>⚪ <b>طرح B همیشه سفید است</b>\n"
            . "📍 🎨 شخصی‌سازی ← 📲 افزودن سرویس به برنامه ← «🖼 طرح صفحه»\n"
            . "طرح B مینیمال تا حالا مثل A پوسته‌ی تیره/روشنِ گوشی یا مرورگر را دنبال می‌کرد و روی دستگاهِ تیره مشکی می‌شد. حالا همیشه سفید است، حتی اگر دستگاه تیره باشد. A همچنان خودکار با تیره/روشنِ دستگاه عوض می‌شود.</blockquote>",
        'en' => "<blockquote>⚪ <b>Look B is always white</b>\n"
            . "📍 🎨 Customize → 📲 Add service to app → «🖼 The look of the page»\n"
            . "The Minimal look B used to follow the phone's or browser's light/dark theme like A, so it went black on a dark device. It is now always white, even when the device is dark. A still follows the device's light/dark by itself.</blockquote>",
    ],
    [
        'n' => 29,
        'date' => '2026-10-24',
        'fa' => "<blockquote>🌓 <b>تم صفحه‌ی باز کردن برنامه: مطابق دستگاه / تیره / روشن</b>\n"
            . "📍 🎨 شخصی‌سازی ← 📲 افزودن سرویس به برنامه ← «🖼 طرح صفحه»؛ وب‌پنل ← افزودن سرویس به برنامه\n"
            . "حالا برای هر زبان می‌توانی انتخاب کنی صفحه‌ی باز کردن برنامه (هر دو طرح A و B) تیره باشد، روشن باشد، یا مطابق تم مرورگر یا موبایل مشتری. پیش‌فرض «مطابق دستگاه» است. این جایگزین یادداشت قبلی می‌شود: B دیگر اجباراً سفید نیست و مثل A پیش‌فرض با دستگاه هماهنگ است؛ اگر سفید ثابت می‌خواهی، تم را «روشن» بگذار. «ریست همه به پیش‌فرض» تم را هم به «مطابق دستگاه» برمی‌گرداند.</blockquote>",
        'en' => "<blockquote>🌓 <b>Theme of the app-opening page: match the device / dark / light</b>\n"
            . "📍 🎨 Customize → 📲 Add service to app → «🖼 The look of the page»; Web panel → Add service to app\n"
            . "You can now choose, per language, whether the app-opening page (both looks, A and B) is dark, light, or follows the customer's browser or phone. The default is «Match the device». This replaces the previous note: B is no longer forced white and follows the device by default like A; for an always-white page, set the theme to «Light». «Reset all to default» puts the theme back to «Match the device» too.</blockquote>",
    ],
    [
        'n' => 30,
        'date' => '2026-10-24',
        'fa' => "<blockquote>⚡️ <b>وب‌پنل: ذخیره بدون رفرش صفحه</b>\n"
            . "📍 همه‌ی صفحه‌های وب‌پنل\n"
            . "دیگر با هر ذخیره یا انتخاب، کل صفحه دوباره باز نمی‌شود و اسکرول به بالا نمی‌پرد: فرم در پس‌زمینه ارسال می‌شود، پیام نتیجه به‌صورت یک نوار کوچک پایین صفحه می‌آید و فقط همان فرم با اطلاعات تازه‌ی سرور جایگزین می‌شود. انتخاب‌هایی مثل طرح و تم صفحه‌ی «افزودن سرویس به برنامه» همان لحظه‌ی انتخاب ذخیره می‌شوند و دکمه‌ی ذخیره ندارند. اگر ذخیره جایی صفحه‌ی دیگری را نشان بدهد یا ردیفی اضافه/حذف شود، مثل قبل صفحه باز می‌شود و پیام همراهش می‌آید. انیمیشن ورود صفحه‌ها هم کوتاه‌تر و آرام‌تر شد.</blockquote>",
        'en' => "<blockquote>⚡️ <b>Web panel: saving without a page refresh</b>\n"
            . "📍 Every page of the web panel\n"
            . "A save or a choice no longer reloads the whole page and throws the scroll back to the top: the form is sent in the background, the result comes up as a small bar at the bottom, and only that form is swapped for the server's fresh copy. Choices such as the look and theme of the «Add service to app» page are saved the moment you pick them and have no save button. If a save leads to another page, or a row is added or removed, the page loads as before with the message carried over. The pages' entrance animation is also shorter and calmer.</blockquote>",
    ],
    [
        'n' => 31,
        'date' => '2026-10-24',
        'fa' => "<blockquote>✏️ <b>ویرایش صفحه‌ی باز کردن برنامه، لینک برنامه‌ها و ورودی تازه در 🎨</b>\n"
            . "📍 وب‌پنل ← افزودن سرویس به برنامه ← «ویرایش زنده‌ی صفحه»؛ 🎨 شخصی‌سازی ← «📲 تنظیمات افزودن سرویس به برنامه»\n"
            . "🖌 <b>ویرایشگر زنده</b> (وب‌پنل): کنار یک پیش‌نمایش زنده‌ی صفحه، روی هر متن بزن و عوضش کن، لوگو را بزرگ‌وکوچک، گرد یا جابه‌جا کن، هر جزء (لوگو، انیمیشن، اسم برنامه، نام سرویس، خط وضعیت، راهنما، دکمه‌ی دانلود، لینک فروشگاه‌ها، «دوباره امتحان کن») را حذف کن و جای بخش‌ها را با ▲▼ عوض کن. تغییر همان لحظه روی پیش‌نمایش می‌افتد و با «ذخیره‌ی صفحه» روی صفحه‌ی مشتری می‌رود. دکمه‌ی «باز کردن برنامه» همیشه می‌ماند.\n"
            . "🔗 <b>لینک دانلود برنامه‌ها</b> (وب‌پنل و ربات): لینک Google Play، GitHub، App Store و سایت رسمی هر برنامه را عوض کن، به پیش‌فرض برگردان یا «-» بگذار تا آن لینک نمایش داده نشود.\n"
            . "🧩 در 🎨 هم «چیدمان صفحه» هست (جابه‌جایی بخش‌ها، نمایش/حذف اجزا، اندازه و شکل و جای لوگو، چینش متن).\n"
            . "ℹ️ همه برای هر زبان جدا و یکی برای هر ۵ مسیر است. ورودی «📲 تنظیمات افزودن سرویس به برنامه» هم از زیر 🛍 به زیر «🛡 دسترسی و محدودیت‌های ادمین» رفت و بالایش یک عنوان می‌گوید برای ۵ مسیر یکی است.</blockquote>",
        'en' => "<blockquote>✏️ <b>Editing the app-opening page, the apps' links, and a new entry in 🎨</b>\n"
            . "📍 Web panel → Add service to app → «Live page editor»; 🎨 Customize → «📲 Add service to app settings»\n"
            . "🖌 <b>Live editor</b> (web panel): next to a live preview of the page, tap any text to change it, resize, round or move the logo, remove any part (logo, animation, app name, service name, status line, hint, download button, store links, «Try again») and reorder the blocks with ▲▼. A change shows on the preview at once and goes to the customer's page with «Save the page». The «Open the app» button always stays.\n"
            . "🔗 <b>Download links of the apps</b> (web panel and bot): change each app's Google Play, GitHub, App Store and official-site links, put them back to the default, or enter «-» so that link is not shown.\n"
            . "🧩 🎨 also has «Page layout» (moving the parts, showing or removing them, the logo's size, shape and place, the text alignment).\n"
            . "ℹ️ All of it is separate for each language and one for all 5 places. The «📲 Add service to app settings» entry also moved from under 🛍 to under «🛡 Admin access and limits», with a heading above it saying it is one for all 5 places.</blockquote>",
    ],
    [
        'n' => 32,
        'date' => '2026-10-24',
        'fa' => "<blockquote>🖐 <b>ویرایشگر زنده‌ی صفحه‌ی باز کردن برنامه: بگیر و ببر، دوبار بزن</b>\n"
            . "📍 وب‌پنل ← افزودن سرویس به برنامه ← «ویرایش زنده‌ی صفحه»\n"
            . "✋ <b>بکش</b>: هر بخش را (لوگو، اسم، نام سرویس، خط وضعیت، دکمه‌ها) با موس یا انگشت بگیر و ببر: بالا و پایین = جای بخش، چپ و راست = سمتش. بخش‌های دیگر همان لحظه کنار می‌روند و جایش را باز می‌کنند.\n"
            . "↔️ <b>اندازه</b>: گوشه‌ی لوگو را بکش تا بزرگ یا کوچک شود (عدد را هم نشان می‌دهد).\n"
            . "✏️ <b>متن</b>: روی هر متن دوبار بزن و همان‌جا عوضش کن (Enter = تأیید، Esc = انصراف).\n"
            . "🗑 <b>حذف</b>: بخش انتخاب‌شده را با نوار کوچکش یا کلید Delete کم‌رنگ کن و با همان نوار برگردان. ↶ ↷ یا Ctrl+Z برای برگشت؛ کلیدهای جهت‌دار هم جابه‌جا می‌کنند.\n"
            . "ℹ️ نشانگر «ذخیره‌نشده» جا را همیشه نگه می‌دارد و پیش‌نمایش وسط کشیدن نمی‌پرد. همه برای هر زبان جدا ذخیره می‌شود.</blockquote>",
        'en' => "<blockquote>🖐 <b>Live editor for the app-opening page: grab, drag, double-tap</b>\n"
            . "📍 Web panel → Add service to app → «Live page editor»\n"
            . "✋ <b>Drag</b>: grab any part (logo, app name, service name, status line, buttons) with the mouse or a finger and move it: up and down = its place in the page, left and right = its side. The other parts make way at once.\n"
            . "↔️ <b>Size</b>: pull the logo's corner to make it bigger or smaller (the number is shown as you go).\n"
            . "✏️ <b>Text</b>: double-tap any text and change it right there (Enter = accept, Esc = cancel).\n"
            . "🗑 <b>Remove</b>: fade out the picked part with its small bar or the Delete key, and bring it back the same way. ↶ ↷ or Ctrl+Z undo; the arrow keys move too.\n"
            . "ℹ️ The «unsaved» marker keeps its place, so the preview no longer jumps mid-drag. All of it is saved separately for each language.</blockquote>",
    ],
    [
        'n' => 33,
        'date' => '2026-10-24',
        'fa' => "<blockquote>📲 <b>صفحه‌ی باز کردن برنامه: لینک دانلود تکراری نشان داده نمی‌شود</b>\n"
            . "📍 صفحه‌ای که مشتری با «📲 افزودن سرویس به برنامه» باز می‌کند\n"
            . "دکمه‌ی بزرگ «دانلود» همیشه اولین جای دانلود است (حالا اسم همان جا، مثلاً «Google Play»، کنارش نوشته می‌شود) و زیرش با «یا دانلود از» فقط جاهای <b>دیگر</b> می‌آیند. برنامه‌ای که برای گوشی مشتری فقط یک جا دارد (مثلاً v2rayNG روی اندروید: فقط GitHub) دیگر همان لینک را دوبار نشان نمی‌دهد و ردیف زیرش نمی‌آید.\n"
            . "ℹ️ متن‌های «یا دانلود از» و پیام «نسخه‌ی جدا ندارد» هم کمی ساده‌تر شد؛ هر متنی که خودت عوض کرده بودی همان می‌ماند.</blockquote>",
        'en' => "<blockquote>📲 <b>App-opening page: the same download link is no longer shown twice</b>\n"
            . "📍 The page a customer opens with «📲 Add service to app»\n"
            . "The big «Download» button is always the first place to get the app (it now also says where, e.g. «Google Play», beside it) and under it «Or get it from» lists only the <b>other</b> places. An app with just one place for the customer's phone (e.g. v2rayNG on Android: GitHub only) no longer shows that link twice and the row under it is gone.\n"
            . "ℹ️ The «Or get it from» line and the «no version for your phone» note were also made a little simpler; any text you changed yourself stays as it is.</blockquote>",
    ],
    [
        'n' => 34,
        'date' => '2026-10-24',
        'fa' => "<blockquote>🖼 <b>آیکن هر برنامه بالای مرورگر</b>\n"
            . "📍 صفحه‌ای که مشتری با «📲 افزودن سرویس به برنامه» باز می‌کند\n"
            . "صفحه‌ی باز کردن هر برنامه حالا آیکن خودِ همان برنامه را در تب مرورگر نشان می‌دهد (به جای آیکن کره‌ی زمین) و اگر مشتری صفحه را به صفحه‌ی اصلی گوشی اضافه کند، همان آیکن می‌آید. برای هر ۶ برنامه جدا.</blockquote>",
        'en' => "<blockquote>🖼 <b>Each app's icon in the browser tab</b>\n"
            . "📍 The page a customer opens with «📲 Add service to app»\n"
            . "The page of each app now shows that app's own icon in the browser tab (instead of the globe), and the same icon comes if the customer adds the page to the phone's home screen. Separate for each of the 6 apps.</blockquote>",
    ],
    [
        'n' => 35,
        'date' => '2026-10-24',
        'fa' => "<blockquote>⏳ <b>صفحه‌ی باز کردن برنامه: «در حال دانلود» و برگشت خودکار</b>\n"
            . "📍 صفحه‌ای که مشتری با «📲 افزودن سرویس به برنامه» باز می‌کند؛ متن‌ها: 🎨 شخصی‌سازی ← «📲 تنظیمات افزودن سرویس به برنامه» ← «⏳ وقتی مشتری داره برنامه رو دانلود می‌کنه» و وب‌پنل ← ویرایش زنده‌ی صفحه ← حالت «در حال دانلود»\n"
            . "مشتری که برنامه را ندارد و روی «دانلود» می‌زند، دیگر روی صفحه‌ی «برنامه پیدا نشد» نمی‌ماند: صفحه می‌شود «در حال دانلود …» با یک حلقه‌ی چرخان دور آیکن و سه مرحله (دانلود و نصب ← برگشت به اینجا ← باز شدن خودکار). وقتی مشتری از فروشگاه به همین صفحه برمی‌گردد، صفحه خودش باز کردن برنامه را امتحان می‌کند؛ اگر نصب بود برنامه با سرویس باز می‌شود و اگر نبود به «برنامه پیدا نشد» برمی‌گردد.\n"
            . "ℹ️ صفحه نمی‌تواند درصد دانلود یا لحظه‌ی تمام شدن نصب را ببیند؛ فقط برگشتن مشتری را می‌فهمد. اگر مرورگری باز شدن خودکار را نگذارد، همان «دوباره امتحان کن» کار می‌کند. متن‌ها و سه مرحله برای هر زبان جدا قابل تغییرند و سه مرحله را می‌شود از چیدمان صفحه برداشت.</blockquote>",
        'en' => "<blockquote>⏳ <b>App-opening page: «Downloading» and the automatic return</b>\n"
            . "📍 The page a customer opens with «📲 Add service to app»; the texts: 🎨 Customize → «📲 Add service to app settings» → «⏳ While the customer downloads the app» and Web panel → Live page editor → the «Downloading» view\n"
            . "A customer who does not have the app and taps «Download» no longer stays on the «App not found» page: it becomes «Downloading …», with a spinning ring around the icon and three steps (Download and install → Come back here → Opens by itself). When the customer returns to this page from the store, the page tries to open the app by itself; if it is installed the app opens with the service, if not the page goes back to «App not found».\n"
            . "ℹ️ The page cannot see the download's progress or the moment the install ends; it only knows the customer is back. If a browser does not allow the automatic opening, «Try again» still works. The texts and the three steps can be changed separately for each language, and the three steps can be taken off in the page's layout.</blockquote>",
    ],
    [
        'n' => 36,
        'date' => '2026-10-24',
        'fa' => "<blockquote>💰 <b>دکمه‌ی «افزایش موجودی» صفحه‌ی حساب کاربری حالا قابل شخصی‌سازی است</b>\n"
            . "📍 🎨 شخصی‌سازی ← «👤 پیام و دکمه‌های حساب کاربری» ← «💰 دکمه افزایش موجودی (حساب کاربری)»؛ وب‌پنل ← ظاهر دکمه‌ها (همان کارت) و لینکش بالای گروه حساب کاربری در «متن و دکمه‌های ربات»\n"
            . "این دکمه تا حالا ثابت بود و فقط ❌ بستن زیرش قابل تغییر بود. حالا اسم، ایموجی (حتی پرمیوم)، رنگ، جا (چپ/راست) و مخفی کردنش را برای هر زبان جدا تنظیم می‌کنی و با ریست برمی‌گردد به حالت قبل. اگر هر دو دکمه‌ی صفحه را مخفی کنی، پیام بدون دکمه می‌رود.\n"
            . "ℹ️ تا وقتی اسمش را عوض نکنی، همان اسم دکمه‌ی «افزایش موجودی» منوی اصلی را دارد و بی‌رنگ می‌ماند (مثل قبل).</blockquote>",
        'en' => "<blockquote>💰 <b>The account page's «Top up balance» button can now be customized</b>\n"
            . "📍 🎨 Customize → «👤 Account messages and buttons» → «💰 Top-up button (account)»; Web panel → Button looks (the same card) and its link at the top of the account group in «Bot texts and buttons»\n"
            . "This button used to be fixed; only the ❌ Close under it could be changed. Now its name, emoji (even premium), colour, side (left/right) and hiding are set separately for each language, and a reset puts it back. If both buttons of the page are hidden, the message goes out without buttons.\n"
            . "ℹ️ Until you rename it, it keeps the name of the main menu's «Top up balance» button and no colour (as before).</blockquote>",
    ],
    [
        'n' => 37,
        'date' => '2026-10-25',
        'fa' => "<blockquote>🗓 <b>فیلتر زمان در همه‌ی لیست‌های وب‌پنل + رفتن مستقیم به پروفایل کاربر</b>\n"
            . "📍 وب‌پنل ← داشبورد، کاربران، سفارشات، تراکنش‌ها، سرویس‌ها و صفحه‌ی هر کاربر؛ دکمه‌ی «همه‌ی زمان‌ها» بالای هر لیست\n"
            . "🗓 <b>فیلتر زمان</b>: یک ضربه روی «امروز»، «دیروز»، «۲ / ۷ / ۳۰ روز اخیر»، «این ماه» یا «ماه قبل»؛ یا «آخرین [N] روز» با هر عددی؛ یا یک بازه از یک روز تا روز دیگر روی تقویم (شمسی یا میلادی، هر روز هر دو تاریخ را دارد و انتخابت یادش می‌ماند). می‌شود فقط «از» یا فقط «تا» را داد. فیلتر با جستجو، وضعیت، ترتیب، زبان و صفحه‌بندی هم‌زمان کار می‌کند و آدرسش قابل ذخیره و فرستادن است. در داشبورد آمار و آخرین‌ها، و در صفحه‌ی هر کاربر لیست سفارش‌ها، تراکنش‌ها و زیرمجموعه‌هایش همان بازه را نشان می‌دهند (اعداد بالای صفحه‌ی کاربر همان کل می‌مانند).\n"
            . "👤 <b>پروفایل کاربر</b>: در لیست کاربران یک دکمه‌ی «پروفایل» با نوشته کنار هر ردیف آمد، آیدی و @یوزرنیم لینک‌اند، و وقتی در جستجو دقیقاً آیدی یا @یوزرنیم یک نفر را بنویسی، یک کارت «رفتن به پروفایل» بالای جدول می‌آید. آیدی کاربر در سفارشات، تراکنش‌ها، سرویس‌ها و داشبورد هم به پروفایلش می‌رود.</blockquote>",
        'en' => "<blockquote>🗓 <b>A time filter on every list of the web panel + straight to a user's profile</b>\n"
            . "📍 Web panel → Dashboard, Users, Orders, Transactions, Services and each user's page; the «All time» button above every list\n"
            . "🗓 <b>Time filter</b>: one tap on «Today», «Yesterday», «Last 2 / 7 / 30 days», «This month» or «Last month»; or «Last [N] days» with any number; or a range from one day to another on a calendar (Persian or Gregorian - every day carries both dates, and your choice is remembered). Only «from» or only «until» works too. It combines with search, status, order, language and paging, and its address can be saved or sent. On the dashboard the figures and the latest lists, and on a user's page the orders, transactions and referrals lists, follow the range (the totals at the top of a user's page stay the totals).\n"
            . "👤 <b>User profile</b>: the users list has a labelled «Profile» button on every row, the id and @username are links, and when what you search for is exactly one user's id or @username a «Go to the profile» card appears above the table. A user's id in Orders, Transactions, Services and the dashboard opens the profile too.</blockquote>",
    ],
    [
        'n' => 38,
        'date' => '2026-10-25',
        'fa' => "<blockquote>💬 <b>باز کردن پی‌وی تلگرام کاربر از وب‌پنل</b>\n"
            . "📍 وب‌پنل ← کاربران ← پروفایل هر کاربر ← کارت «عملیات»\n"
            . "یک دکمه‌ی «باز کردن پی‌وی تلگرام» زیر «افزایش موجودی» آمد. اگر کاربر @یوزرنیم دارد، چتش در تب تازه با لینک t.me باز می‌شود (هرجا کار می‌کند)؛ اگر یوزرنیم ندارد، لینک با آیدی را باز می‌کند که فقط وقتی کار می‌کند که تلگرامِ خودت قبلاً این کاربر را دیده باشد، و زیر دکمه همین را نوشته.</blockquote>",
        'en' => "<blockquote>💬 <b>Open a user's Telegram chat from the web panel</b>\n"
            . "📍 Web panel → Users → a user's page → the «Operations» card\n"
            . "A «Open the Telegram chat» button now sits under «Add balance». For a user with an @username it opens their chat in a new tab with a t.me link (works anywhere); for one without, it opens the link by id, which only works when your own Telegram has already seen that user - and the button says so.</blockquote>",
    ],
    [
        'n' => 41,
        'date' => '2026-10-25',
        'fa' => "<blockquote>📋 <b>دکمه‌ی «کپی QR» برداشته شد</b>\n"
            . "تلگرام به ربات اجازه نمی‌دهد عکس را با یک دکمه داخل خود تلگرام کپی کند و دکمه فقط یک صفحه‌ی وب باز می‌کرد، پس کنارش گذاشتیم. دکمه، صفحه‌ی QR و متن‌هایش از همه‌ی ۵ مسیر، 🎨 شخصی‌سازی و وب‌پنل رفتند؛ هرچه قبلاً برایش عوض کرده بودی بی‌اثر می‌شود. QR خود سرویس زیر همان پیام‌ها مثل قبل هست (در تلگرام دسکتاپ می‌شود روی عکسش راست‌کلیک و «Copy Image» زد).</blockquote>",
        'en' => "<blockquote>📋 <b>The «Copy QR» button was taken out</b>\n"
            . "Telegram does not let a bot copy a picture inside Telegram with a button, and the button only opened a web page, so it is gone. The button, its QR page and their texts left all 5 places, 🎨 Customize and the web panel; anything changed for them has no effect now. The service's own QR is still under the same messages as before (in Telegram Desktop, right-click the picture and choose «Copy Image»).</blockquote>",
    ],
    [
        'n' => 42,
        'date' => '2026-10-25',
        'fa' => "<blockquote>⚠️ <b>پیام «محدودیت ساخت اشتراک تست شما به پایان رسید» و دکمه‌ی «خرید اشتراک»ش قابل شخصی‌سازی شد</b>\n"
            . "📍 🎨 شخصی‌سازی ← «⚠️ پیام «محدودیت ساخت اکانت تست تموم شد»» (و وب‌پنل ← متن و دکمه‌های ربات، ظاهر دکمه‌ها)\n"
            . "پیامی که وقتی سهمیه‌ی اکانت تست کاربر تموم شده و دوباره «🎁 اکانت تست» را می‌زند می‌رود، تا حالا ثابت بود. حالا متنش (با استیکر و ریاکشن) و دکمه‌ی «🛍 خرید اشتراک» زیرش — اسم، ایموجی (حتی پرمیوم)، رنگ، مخفی بودن — برای هر زبان جدا قابل تغییر است. پیش‌فرض‌ها همان قبلی‌اند (دکمه بی‌رنگ).</blockquote>",
        'en' => "<blockquote>⚠️ <b>The «your test subscription limit has been reached» message and its «Buy a subscription» button can be customized</b>\n"
            . "📍 🎨 Customize → «⚠️ The «test account limit reached» message» (and Web panel → Bot texts and buttons, Button looks)\n"
            . "The message a user gets when their test-account allowance is used up and they tap «🎁 Test account» again was fixed until now. Its text (with sticker and reaction) and the «🛍 Buy subscription» button under it - name, emoji (even premium), colour, hiding - can now be changed separately for each language. The defaults are as before (the button has no colour).</blockquote>",
    ],
    [
        'n' => 43,
        'date' => '2026-10-25',
        'fa' => "<blockquote>💎 <b>ایموجی پریمیوم و استیکر توی همه‌ی 🎨 شخصی‌سازی درست شد</b>\n"
            . "• 🔘 دکمه‌های اکانت تست (انصراف / پیش‌فرض) حالا همون ابزارهای کامل بقیه‌ی دکمه‌ها رو دارن: اسم، 🎭 ایموجی (معمولی یا پریمیوم)، رنگ، 📐 چیدمان و مخفی کردن - برای هر زبان جدا، توی ربات و وب‌پنل (دکمه‌ها).\n"
            . "• جاهایی که ایموجی پریمیومِ اول اسم جدیدِ دکمه معمولی می‌شد درست شد؛ حالا ایموجی پریمیوم دکمه می‌شه: دکمه‌های منوی اصلی، دکمه‌های فاکتور کارت‌به‌کارت و درگاه‌ها، دسته‌های درگاه، بسته‌های شارژ و دکمه‌هاشون، دکمه‌های آموزش/دسته/پنل/محصول/زبان، و اسم آموزش و دسته‌ی آموزش.\n"
            . "• دکمه‌ی «دریافت کانفیگ»، ستون‌ها و «دریافت همه کانفیگ‌ها» (اکانت تست، خرید و هدیه‌ی دعوت) و دکمه‌های صفحه‌ی مبلغ (مبلغ دلخواه و بازگشت‌ها) ابزار 🎭 ایموجی (معمولی یا پریمیوم) گرفتن.\n"
            . "• پیام بعد از خرید و پیام اکانت تست حالا 🖼 استیکر می‌گیرن (قبل از عکس QR فرستاده می‌شه).\n"
            . "• ایموجی پریمیوم توی متن صفحه‌ی انتخاب زبان درست نشون داده می‌شه.\n"
            . "• ردیف‌های تکراری «📲 افزودن سرویس به برنامه» از داخل اکانت تست و بقیه‌ی ۵ مسیر برداشته شد؛ تنظیمش یه جا روی صفحه‌ی اول 🎨 هست.</blockquote>",
        'en' => "<blockquote>💎 <b>Premium emoji and stickers fixed across 🎨 Customize</b>\n"
            . "• 🔘 The test account buttons (Cancel / Default) now have the full button tools: name, 🎭 emoji (plain or premium), colour, 📐 layout and hiding - per language, in the bot and the web panel (Buttons).\n"
            . "• A premium emoji at the start of a new button name no longer turns plain; it becomes the button's premium emoji: main menu buttons, card-to-card and gateway invoice buttons, gateway groups, top-up packages and their buttons, tutorial/category/panel/product/language buttons, and tutorial and tutorial-category names.\n"
            . "• «Get config», the columns and «Get all configs» (test account, purchase and referral gift) and the amount screen's buttons (custom amount and the back buttons) got a 🎭 emoji tool (plain or premium).\n"
            . "• The post-purchase and test account messages now take a 🖼 sticker (sent before the QR photo).\n"
            . "• A premium emoji in the language picker's text now shows properly.\n"
            . "• The repeated «📲 Add service to app» rows were removed from the test account and the other paths; it is set in one place on 🎨's first screen.</blockquote>",
    ],
    [
        'n' => 44,
        'date' => '2026-10-25',
        'fa' => "<blockquote>🎨 <b>حدود ۱۶۰ پیام دیگه‌ی ربات به 🎨 شخصی‌سازی اضافه شد</b>\n"
            . "پیام‌هایی که تا حالا به مشتری می‌رسید ولی هیچ جا قابل تغییر نبود، حالا متن (با ایموجی پریمیوم)، استیکر و ری‌اکشنشون برای هر زبان جدا قابل تنظیمه - توی ربات و وب‌پنل (متن و دکمه‌های ربات):\n"
            . "• 🛒 مراحل خرید: انتخاب مدت زمان، یادداشت کانفیگ، حجم و زمان دلخواه، خرید عمده، پیام بعد از خرید برای پنل دستی / WGDashboard / IBSng و میکروتیک، و خطاهای خرید.\n"
            . "• 🛍 سرویس‌های من ← «♻️ تمدید، حجم اضافه، انتقال، لوکیشن و ...»: همه‌ی پیام‌های تمدید، حجم و زمان اضافه، تغییر لینک، انتقال، تغییر لوکیشن، یادداشت، حذف سرویس، روشن/خاموش، گزارش اختلال و جستجو.\n"
            . "• 💰 افزایش موجودی: پیام‌های رسید، پاپ‌آپ‌های وضعیت پرداخت و خطاهای پرداخت.\n"
            . "• 🔑 اکانت تست: «سرویس تست در دسترس نیست»، خطای ساخت، پیام WGDashboard و «محدودیت ساخت اکانت تست تموم شد» حالا توی صفحه‌ی خود اکانت تست‌ان.\n"
            . "• ☎️ بخش جدید «پشتیبانی، کد هدیه و پیام‌های دیگر»: پشتیبانی، کد هدیه، درخواست نمایندگی، لینک دانلود برنامه، عضویت کانال، بلاک، قرعه‌کشی و خطاهای عمومی.\n"
            . "• متن «⛔️ اکانت تست غیرفعال است» قبلاً ذخیره نمی‌شد؛ درست شد.</blockquote>",
        'en' => "<blockquote>🎨 <b>About 160 more bot messages added to 🎨 Customize</b>\n"
            . "Messages customers were getting that could not be changed anywhere now have their text (premium emoji included), sticker and reaction set per language - in the bot and the web panel (Bot texts and buttons):\n"
            . "• 🛒 Purchase steps: choosing the duration, the config note, custom volume and time, bulk purchase, the post-purchase message for manual / WGDashboard / IBSng and MikroTik panels, and purchase errors.\n"
            . "• 🛍 My services → «♻️ Renewal, extra volume, transfer, location ...»: every renewal, extra volume and time, link change, transfer, location change, note, service removal, on/off, outage report and search message.\n"
            . "• 💰 Top-up: receipt messages, payment status popups and payment errors.\n"
            . "• 🔑 Test account: «test service unavailable», the creation error, the WGDashboard message and «test account limit reached» are now on the test account's own screen.\n"
            . "• ☎️ New section «Support, gift code and other messages»: support, gift code, agent request, app download link, channel membership, blocking, lottery and general errors.\n"
            . "• The «⛔️ test account disabled» text did not save before; fixed.</blockquote>",
    ],
    [
        'n' => 45,
        'date' => '2026-10-25',
        'fa' => "<blockquote>🌐 <b>پیام‌های بعد از تایید پرداخت حالا به زبان خود مشتری‌ان</b>\n"
            . "وقتی ادمین رسید کارت‌به‌کارت رو تایید می‌کرد (یا درگاه و کرون پرداخت رو تایید می‌کرد)، پیام‌های مشتری - سرویس ساخته شد، تمدید شد، حجم/زمان اضافه شد، برگشت مبلغ و خطاها - به زبان همون کسی که تایید می‌کرد می‌رفت؛ مشتری انگلیسی متن فارسی می‌گرفت. حالا همه به زبان خود مشتری و با متنی که توی 🎨 برای همون زبان گذاشتی می‌رن، دکمه‌هاش هم.\n"
            . "• پیام تمدید موفق، حجم و زمان اضافه بعد از تایید پرداخت همون پیام‌های 🎨 ‏(✅ تمدید موفق، ✅ حجم اضافه شد، ✅ زمان اضافه شد) رو استفاده می‌کنن؛ یه بار تغییرشون بدی، هر دو مسیر عوض می‌شن.\n"
            . "• خطای خرید زمان اضافه به‌جای مشتری برای ادمینِ تاییدکننده می‌رفت و «حجم اضافه» می‌گفت؛ درست شد و متن خودش رو داره.\n"
            . "• پیام‌های برگشت مبلغ (سرویس ساخته/تمدید نشد)، خطای تمدید بعد از تایید و «حساب احراز هویت شد» متن انگلیسی گرفتن و به 🎨 اضافه شدن.</blockquote>",
        'en' => "<blockquote>🌐 <b>Messages after a payment is approved now go in the customer's own language</b>\n"
            . "When an admin approved a card-to-card receipt (or a gateway or the payment cron approved a payment), the customer's messages - service created, renewed, extra volume/time added, refunds and errors - went in the approver's language; an English customer got Persian text. Now they all go in the customer's language, with the text set in 🎨 for that language, buttons included.\n"
            . "• The renewal, extra volume and extra time messages after an approved payment use the same 🎨 messages (✅ Renewed, ✅ Volume added, ✅ Time added); change them once and both paths follow.\n"
            . "• The extra time error went to the approving admin instead of the customer and said «extra volume»; fixed, with a text of its own.\n"
            . "• The refund messages (service not created / not renewed), the renewal error after approval and «account verified» got English texts and were added to 🎨.</blockquote>",
    ],
    [
        'n' => 46,
        'date' => '2026-10-25',
        'fa' => "<blockquote>✨ <b>اسم ربات حالا Ciro است</b>\n"
            . "اسم پروژه به Ciro تغییر کرد: اسکریپت نصب، پنل وب، صفحه مستندات API و متن‌های ربات.\n"
            . "• دستور مدیریت روی سرور حالا <code>ciro</code> است (<code>ciro update</code>، <code>ciro backup</code> و...).\n"
            . "• صفحه گیت‌هاب پروژه کوتاه‌تر و خلاصه‌تر شد.</blockquote>",
        'en' => "<blockquote>✨ <b>The bot is now called Ciro</b>\n"
            . "The project was renamed to Ciro: the install script, the web panel, the API docs page and the bot's texts.\n"
            . "• The management command on the server is now <code>ciro</code> (<code>ciro update</code>, <code>ciro backup</code>, ...).\n"
            . "• The project's GitHub page is shorter.</blockquote>",
    ],
    [
        'n' => 47,
        'date' => '2026-10-25',
        'fa' => "<blockquote>🏷 <b>همه‌جا Ciro</b>\n"
            . "اسم قبلی از کد، اسکریپت نصب و صفحه گیت‌هاب کامل برداشته شد.\n"
            . "• روی سرورهایی که از قبل نصب بودن، فایل‌های ربات خودشون اسم Ciro می‌گیرن: پوشه ربات (<code>/var/www/html/ciro</code>)، تنظیمات اسکریپت (<code>/root/ciro</code>)، نسخه‌های پشتیبان (<code>/root/ciro-backups</code>)، کرون آپدیت و دستور. وبهوک، دیتابیس و تنظیمات دست نمی‌خورن؛ اگه Apache تغییر رو قبول نکنه، همه‌چیز همون‌طور که بود می‌مونه.\n"
            . "• دستور مدیریت فقط <code>ciro</code> است؛ دستور قبلی برداشته شد.\n"
            . "• نوع پنل «نمایندگی» حالا «نمایندگی Ciro» است و پنل‌هایی که قبلاً ساخته بودی خودکار منتقل می‌شن.\n"
            . "• آدرس گیت‌هاب پروژه: <code>github.com/DenTeeR/Ciro</code></blockquote>",
        'en' => "<blockquote>🏷 <b>Ciro everywhere</b>\n"
            . "The previous name is gone from the code, the install script and the GitHub page.\n"
            . "• On servers installed earlier, the bot's own files take the Ciro name: the bot folder (<code>/var/www/html/ciro</code>), the script's settings (<code>/root/ciro</code>), the backups (<code>/root/ciro-backups</code>), the update cron and the command. The webhook, the database and the settings are not touched; if Apache does not accept the change, everything stays as it was.\n"
            . "• The management command is <code>ciro</code> only; the previous one was removed.\n"
            . "• The «agent» panel type is now «Ciro agent», and panels you already added move over by themselves.\n"
            . "• The project's GitHub address: <code>github.com/DenTeeR/Ciro</code></blockquote>",
    ],
    [
        'n' => 48,
        'date' => '2026-10-26',
        'fa' => "<blockquote>🔠 <b>اسم و دستور، همه‌جا با C بزرگ: Ciro</b>\n"
            . "جایی که اسم با حروف کوچک نوشته شده بود - دستور مدیریت و پوشه‌های ربات روی سرور - حالا Ciro نوشته می‌شود؛ لینوکس حروف کوچک و بزرگ را فرق می‌گذارد.\n"
            . "• دستور روی سرور حالا <code>Ciro</code> است (<code>Ciro update</code>، <code>Ciro backup</code> و...)؛ حرف C آن باید بزرگ باشد.\n"
            . "• روی سرورهایی که با نسخه قبلی این اسکریپت نصب شده بودند، پوشه ربات (<code>/var/www/html/Ciro</code>)، تنظیمات اسکریپت (<code>/root/Ciro</code>) و نسخه‌های پشتیبان (<code>/root/Ciro-backups</code>) خودکار به همین اسم منتقل می‌شوند؛ وبهوک، دیتابیس و تنظیمات دست نمی‌خورند.</blockquote>",
        'en' => "<blockquote>🔠 <b>The name and the command, everywhere with a capital C: Ciro</b>\n"
            . "Wherever the name was written lowercase - the management command and the bot's folders on the server - it is now written Ciro; Linux tells upper and lower case apart.\n"
            . "• The command on the server is now <code>Ciro</code> (<code>Ciro update</code>, <code>Ciro backup</code>, ...) - the C has to be capital.\n"
            . "• On servers installed by an earlier copy of this script, the bot folder (<code>/var/www/html/Ciro</code>), the script's settings (<code>/root/Ciro</code>) and the backups (<code>/root/Ciro-backups</code>) move to this name automatically; the webhook, the database and the settings are not touched.</blockquote>",
    ],
    [
        'n' => 49,
        'date' => '2026-10-26',
        'fa' => "<blockquote>♻️ <b>بازیابی بکاپ در اسکریپت سرور درست و هوشمندتر شد</b>\n"
            . "• زیپ بکاپی که با رمز ساخته شده بود گاهی با پیام «رمز اشتباه است یا زیپ خراب است» باز نمی‌شد، حتی با رمز درست: زیپ‌هایی که با رمز AES (مثل 7-Zip) قفل شده‌اند را ابزار معمولی unzip اصلاً نمی‌خواند. حالا این زیپ‌ها هم باز می‌شوند و اگر زیپ واقعاً خراب باشد دلیلش را می‌گوید.\n"
            . "• اسکریپت داخل زیپ تشخیص می‌دهد کدام فایل دیتابیس است و کدام پوشه ربات، و پوشه را به دیتابیسش وصل می‌کند.\n"
            . "• از زیپ فقط دیتابیس ریخته می‌شود و بعدش جدول‌ها به نسخه فعلی آپدیت می‌شوند؛ تنظیمات داخل دیتابیس سر جایش می‌ماند.\n"
            . "• پوشه‌ای که برای نسخه قدیمی (ربات اصلی، بدون فایل نسخه) باشد روی ربات فعلی ریخته نمی‌شود؛ ربات همان آخرین نسخه می‌ماند. فقط داده‌های خود پوشه (ربات‌های نماینده و توکن API) نگه داشته می‌شود و فایل config.php دست نمی‌خورد.</blockquote>",
        'en' => "<blockquote>♻️ <b>Restoring a backup with the server script is fixed and smarter</b>\n"
            . "• A password-protected backup zip sometimes failed with «wrong password, or the zip is damaged» even with the right password: a zip locked with AES (7-Zip, for example) cannot be read by the usual unzip tool at all. These zips open now, and if a zip really is damaged the script says why.\n"
            . "• The script tells which file in the zip is a database and which is a bot folder, and pairs each folder with its database.\n"
            . "• Only the database is restored from a zip, and its tables are then brought up to this version; the settings in the database stay as they are.\n"
            . "• A folder of an old version (the original project, no version file) is not laid over the current bot; the bot keeps the newest files. Only the folder's own data (agent bots and the API token) is kept, and config.php is never touched.</blockquote>",
    ],
    [
        'n' => 50,
        'date' => '2026-10-26',
        'fa' => "<blockquote>🧹 <b>پاکسازی و ریست: شروع تازه برای فروشگاه</b>\n"
            . "• داشبورد پنل وب: روی «درآمد کل»، «سرویس فعال» و «تراکنش امروز» دکمهٔ ↺ هست. عدد از همین الان دوباره شمرده می‌شود؛ هیچ پرداخت یا سفارشی پاک نمی‌شود و با «بازگرداندن عدد قبلی» همان عدد قبلی برمی‌گردد.\n"
            . "• «صفر کردن موجودی کاربران»: موجودی همه (و کیف‌پول‌های ارز دیگرشان) صفر می‌شود، برای یک زبان یا همهٔ زبان‌ها. مقدارها اول ذخیره می‌شود و «برگرداندن موجودی‌ها» پسشان می‌دهد؛ پولی که بعد از صفر کردن آمده سر جایش می‌ماند.\n"
            . "• «صفر کردن زیرمجموعه‌ها»: دعوت‌ها، شمارش‌ها، هدیهٔ عضویتِ دریافت‌نشده و پیشرفت اکانت رایگان 🎁 از نو شروع می‌شود. تنظیمات پورسانت دست نمی‌خورد.\n"
            . "• «حذف سرویس‌های ربات از پنل»: فقط سرویس‌هایی که کاربرها از همین ربات خریده‌اند از پنل VPN پاک می‌شوند؛ اکانت‌هایی که خودت در پنل ساخته‌ای دست نمی‌خورند. برای تأیید باید کلمهٔ «حذف» را بنویسی. کار یکی‌یکی در پس‌زمینه انجام می‌شود، پیشرفتش دیده می‌شود، می‌شود متوقفش کرد، ناموفق‌ها را دوباره امتحان کرد و گزارش پایانی به ربات می‌آید.\n"
            . "• هم در پنل وب (منوی 🧹 پاکسازی و ریست، و لینک بالای لیست کاربران) و هم در ربات: 👤 مدیریت کاربر ← 🧹 (فقط ادمین).</blockquote>",
        'en' => "<blockquote>🧹 <b>Clean-up and reset: a fresh start for the shop</b>\n"
            . "• Web panel dashboard: «total income», «active services» and «today's transactions» each have a ↺ button. The figure counts again from now; no payment or order is deleted, and «restore the old figure» brings the old count back.\n"
            . "• «Zero all balances»: every balance (and the wallets in other currencies) goes to zero, for one language or all of them. The amounts are saved first and «give balances back» returns them; money that came in after the reset stays.\n"
            . "• «Zero all referrals»: invites, counts, join gifts not yet taken and the free-config 🎁 progress start over. Commission settings are not touched.\n"
            . "• «Delete the bot's services from the panels»: only services customers bought from this bot are removed from the VPN panel; accounts you made in a panel yourself are left alone. You confirm by typing the word «DELETE». It runs one by one in the background, shows its progress, can be stopped, retries the failed ones and sends its final report to the bot.\n"
            . "• In the web panel (the 🧹 Clean-up and reset menu, and a link above the user list) and in the bot: 👤 User management ← 🧹 (admins only).</blockquote>",
    ],
    [
        'n' => 51,
        'date' => '2026-10-26',
        'fa' => "<blockquote>🔢 <b>نسخهٔ دستور Ciro و نسخهٔ ربات یکی شد</b>\n"
            . "• دستور <code>Ciro</code> حالا نسخهٔ ربات را از همان فایلی نشان می‌دهد که صفحهٔ آپدیت ربات می‌خواند؛ کنارش هم نوشته که این جدیدترین نسخهٔ گیت‌هاب هست یا نسخهٔ تازه‌تری آمده.\n"
            . "• هنگام آپدیت یا نصب، به‌جای «Latest» شمارهٔ نسخه‌ای که گرفته می‌شود نوشته می‌شود.\n"
            . "• دستور تازهٔ <code>Ciro version</code>: نسخهٔ هر ربات روی سرور و جدیدترین نسخهٔ گیت‌هاب را می‌نویسد.</blockquote>",
        'en' => "<blockquote>🔢 <b>The Ciro command and the bot now show the same version</b>\n"
            . "• The <code>Ciro</code> command reads the bot's version from the very file the bot's update screen reads, and says next to it whether that is the newest on GitHub or a newer one is out.\n"
            . "• While updating or installing, the number of the version being fetched is shown instead of «Latest».\n"
            . "• New command <code>Ciro version</code>: prints each bot's version on the server and the newest one on GitHub.</blockquote>",
    ],
    [
        'n' => 52,
        'date' => '2026-10-26',
        'fa' => "<blockquote>🗑 <b>گزارش «حذف سرویس‌های ربات از پنل» حالا راستش را می‌گوید</b>\n"
            . "• قبلاً سرویسی که پنلش دیگر در ربات نبود (یا پنل جواب «پیدا نشد» می‌داد) هم «حذف‌شده» شمرده می‌شد، در حالی که هیچ اکانتی از روی پنل پاک نشده بود؛ فقط از لیست ربات پاک می‌شد.\n"
            . "• حالا گزارش جدا می‌گوید: چندتا واقعاً از روی پنل حذف شد، چندتا پنل «پیدا نشد» گفت، چندتا پنلشان دیگر در ربات نیست (فقط از ربات پاک شد) و چندتا ناموفق بود. اگر هیچ اکانتی از پنل حذف نشود، با یک هشدار صریح می‌گوید.\n"
            . "• پیش از شروع، تعداد سرویس‌هایی که پنلشان در ربات نیست نشان داده می‌شود.\n"
            . "• پنل هیدیفای: دیگر بدون خواندن جواب پنل «موفق» نمی‌شمارد؛ اگر جواب ندهد ناموفق ثبت می‌شود.\n"
            . "• خطای یک پنل دیگر کل کار را متوقف نمی‌کند؛ فقط همان سرویس ناموفق می‌شود.</blockquote>",
        'en' => "<blockquote>🗑 <b>The «delete the bot's services from the panels» report now tells the truth</b>\n"
            . "• A service whose panel was no longer in the bot (or whose panel answered «not found») used to be counted as removed although no account was deleted from any panel; it was only cleared from the bot's list.\n"
            . "• The report now says separately how many were really deleted from a panel, how many the panel said it could not find, how many belong to a panel the bot no longer has (cleared from the bot only), and how many failed. If no account was deleted from a panel it says so outright.\n"
            . "• Before starting, it shows how many of the services belong to a panel the bot no longer has.\n"
            . "• Hiddify panels: it no longer counts a removal as done without reading the panel's answer; no answer is a failure.\n"
            . "• An error from one panel no longer stops the whole run; only that service fails.</blockquote>",
    ],
    [
        'n' => 53,
        'date' => '2026-10-31',
        'fa' => "<blockquote>🛠 <b>«قابلیت های پنل» روی همهٔ پنل‌ها کار می‌کند</b>\n"
            . "• قبلاً با انتخاب پنل ربکا، رمناویو، Ciro agent، S-UI، IBSng، میکروتیک یا فروش دستی هیچ جوابی نمی‌آمد. حالا «🔧 ساخت کانفیگ دستی» برای همهٔ این پنل‌ها هست و «🖥 مدیریت نود ها» برای مرزبان.\n"
            . "• اگر پنلی گزینه‌ای در این بخش نداشته باشد یا نام پنل اشتباه باشد، ربات پیام روشن می‌دهد، نه سکوت.\n"
            . "• بعد از ساخت کانفیگ دستی، گزینه‌های همان پنل دوباره می‌آید (قبلاً همیشه دکمه‌های مرزبان می‌آمد) و اگر «نمایش کانفیگ» پنل روشن باشد، لینک کانفیگ‌ها هم در پیام می‌آید.</blockquote>",
        'en' => "<blockquote>🛠 <b>«Panel features» works on every panel</b>\n"
            . "• Picking a Rebecca, Remnawave, Ciro agent, S-UI, IBSng, MikroTik or manual-sale panel used to answer nothing at all. «🔧 Create a config by hand» is now there for all of them, and «🖥 Manage nodes» for Marzban.\n"
            . "• A panel with nothing to offer here, or a wrong panel name, now gets a clear message instead of silence.\n"
            . "• After a config is made by hand, that panel's own options come back (it always showed Marzban's before), and the config links are in the message when the panel's «show config» is on.</blockquote>",
    ],
    [
        'n' => 54,
        'date' => '2026-10-31',
        'fa' => "<blockquote>🖥 <b>مدیریت کامل نودهای پنل ربکا</b>\n"
            . "• از «🛠 قابلیت های پنل» ← پنل ربکا ← «🖥 مدیریت نود ها»، و در پنل وب «🖥 نودهای ربکا».\n"
            . "• لیست نودها با وضعیت، CPU، رم، سرعت الان، ترافیک کل، نسخهٔ Xray و سرویس نود، آدرس و پورت‌ها.\n"
            . "• کارهای هر نود: اتصال دوباره، همگام‌سازی، ری‌استارت هسته یا سرویس نود، آپدیت هستهٔ Xray (انتخاب نسخه)، آپدیت سرویس نود، خاموش و روشن، صفر کردن مصرف، ریبوت سرور نود، ویرایش (اسم، آدرس، پورت‌ها، ضریب) و حذف؛ کارهایی که کاربرها را قطع می‌کنند تأیید می‌خواهند.\n"
            . "• ➕ افزودن نود قدم‌به‌قدم؛ بستهٔ نصب نود (گواهی + کلید) به‌صورت فایل می‌آید، با دستور نصب.\n"
            . "• ⬆️ آپدیت سرویس همهٔ نودها با یک دکمه.\n"
            . "• 🔔 پایش هر ۲ دقیقه: قطع شدن هر نود (یا خود پنل) با اسم، وضعیت، پیام خطا و ساعت گزارش می‌شود و وصل شدن دوباره با مدت قطعی؛ «در حال اتصال» فقط اگر بیشتر از ۵ دقیقه طول بکشد. 📜 تاریخچهٔ همهٔ قطع و وصل‌ها با تاریخ.\n"
            . "• 📊 گزارش تصویری: یک عکس از همهٔ نودها (CPU، رم، سرعت، ترافیک، نسخه‌ها، آدرس) با نمودار ترافیک ۱۴ روز؛ و برای هر نود نمودار مصرف روزانهٔ ۳۰ روز.\n"
            . "• فقط برای پنل‌های ربکا؛ توکن پنل باید مال ادمین sudo باشد.</blockquote>",
        'en' => "<blockquote>🖥 <b>Full node management for Rebecca panels</b>\n"
            . "• From «🛠 Panel features» ← a Rebecca panel ← «🖥 Manage nodes», and «🖥 Rebecca nodes» in the web panel.\n"
            . "• The node list with status, CPU, RAM, current speed, total traffic, Xray and node service versions, address and ports.\n"
            . "• Per node: reconnect, sync, restart the core or the node service, update the Xray core (pick a version), update the node service, disable and enable, reset usage, reboot the node server, edit (name, address, ports, coefficient) and delete; the ones that drop users ask first.\n"
            . "• ➕ Add a node step by step; the node install bundle (certificate + key) comes as a file, with the install command.\n"
            . "• ⬆️ Update every node's service with one button.\n"
            . "• 🔔 A watch every 2 minutes: a node (or the panel itself) going down is reported with its name, status, error and time, and coming back with how long it was down; «connecting» only after 5 minutes. 📜 The full history of ups and downs, with dates.\n"
            . "• 📊 A report picture: every node (CPU, RAM, speed, traffic, versions, address) with a 14-day traffic chart; and a 30-day daily traffic chart per node.\n"
            . "• Rebecca panels only; the panel token must be a sudo admin's.</blockquote>",
    ],
    [
        'n' => 55,
        'date' => '2026-10-31',
        'fa' => "<blockquote>🖥 <b>نودهای ربکا با چند پنل: قاطی نمی‌شوند و گزارش تکراری نمی‌آید</b>\n"
            . "• هر گزارش قطع و وصل، اسم پنل و آدرسش را دارد؛ صفحهٔ نودها هم آدرس پنل را نشان می‌دهد.\n"
            . "• 📮 مقصد گزارش‌ها برای هر پنل جدا: گروه گزارش‌ها (تاپیک «❌ گزارش خطا ها»)، یک کانال یا گروه جدا (با پیام آزمایشی)، داخل خود ربات (پیوی ادمین‌ها)، یا هیچ‌جا (فقط تاریخچه). بار اولی که نودهای یک پنل را باز می‌کنی همین را می‌پرسد؛ بعداً از دکمهٔ «📮 مقصد گزارش‌ها» و در پنل وب عوض می‌شود.\n"
            . "• پنل‌های مشترک: اگر یک پنل ربکا دو بار (یا بیشتر) در ربات اضافه شده باشد - با همان آدرس، یا با آدرس دیگری ولی همان نودها - یک پنل حساب می‌شود: پایش یک بار، گزارش یک بار با اسم همهٔ آن‌ها، تاریخچه و مقصد گزارش مشترک.</blockquote>",
        'en' => "<blockquote>🖥 <b>Rebecca nodes with several panels: never mixed up, never reported twice</b>\n"
            . "• Every up/down report names the panel and its address; the node screen shows the panel's address too.\n"
            . "• 📮 Where reports go, per panel: the report group (the «❌ errors» topic), a channel or group of its own (with a test message), inside the bot (the admins' chats), or nowhere (history only). Asked the first time a panel's nodes are opened; changed later with «📮 Report destination» and in the web panel.\n"
            . "• Shared panels: one Rebecca panel added to the bot twice or more - on the same address, or another address with the very same nodes - counts as one: watched once, reported once with all its names, one history and one destination.</blockquote>",
    ],
    [
        'n' => 56,
        'date' => '2026-10-31',
        'fa' => "<blockquote>🚚 <b>انتقال ربات (گزینهٔ ۱۰ در Ciro): روی سروری که چیزهای دیگری دارد، بدون دست زدن به آن‌ها</b>\n"
            . "• سرور مقصد اول فقط خوانده می‌شود: ربات‌ها، سایت‌ها، دیتابیس‌ها، کانتینرها، پنل‌ها و پورت‌های درگیر نشان داده می‌شوند و بعد تصمیم گرفته می‌شود.\n"
            . "• کنار بقیه نصب می‌شود: پوشه و دیتابیس با اسم آزاد (Ciro، Ciro2، ...)، یوزر دیتابیس جدا با رمز تازه (به دیتابیس ربات‌های دیگر دسترسی ندارد)، رمز root در MySQL عوض نمی‌شود، نسخهٔ PHP و تنظیمات Apache و سایت‌های دیگر دست نمی‌خورند.\n"
            . "• پورت‌ها: اگر پورت ربات و phpMyAdmin در مقصد آزاد باشند بدون هیچ سؤالی همان‌ها می‌مانند. فقط اگر یکی درگیر باشد، جدولی می‌آید: پورت‌های آزاد سبز، پورت‌های درگیر قرمز (با اسم برنامه‌ای که گرفته)، و پورتی که وارد می‌کنی دوباره زنده چک می‌شود.\n"
            . "• هیچ پیامی گم نمی‌شود: ربات اینجا درست قبل از پشتیبان آخر متوقف می‌شود (کرون‌ها خاموش، وبهوک برداشته ولی پیام‌ها پیش تلگرام می‌مانند). پشتیبان کامل با checksum در مقصد چک می‌شود، تعداد جدول‌ها شمرده می‌شود و وبهوک فقط آخر کار، بعد از رسیدن کامل داده‌ها ست می‌شود.\n"
            . "• اگر وسط کار خطایی پیش بیاید، هر چیزی که این انتقال در مقصد ساخته بود پاک می‌شود و ربات اینجا دوباره روشن می‌شود. اگر همین ربات از دفعهٔ قبل در مقصد مانده باشد، اول از داده‌اش پشتیبان گرفته می‌شود و در صورت خطا همان برمی‌گردد.\n"
            . "• ربات‌های مختلف روی یک سرور دیگر کرون همدیگر را پاک نمی‌کنند و قفل کرون‌هایشان جداست.</blockquote>",
        'en' => "<blockquote>🚚 <b>Moving a bot (option 10 in Ciro): onto a server that runs other things, leaving them alone</b>\n"
            . "• The new server is only read first: its bots, sites, databases, containers, panels and busy ports are shown, then the plan is made.\n"
            . "• It goes in beside them: a free folder and database name (Ciro, Ciro2, ...), a database user of its own with a fresh password (it cannot see other bots' databases), MySQL's root password is never changed, and the PHP version, Apache's setup and the other sites stay as they are.\n"
            . "• Ports: when the bot's and phpMyAdmin's ports are free there, they are kept with no questions. Only when one is taken, a board comes up: free ports in green, busy ones in red (with what holds them), and the port you type is checked again, live.\n"
            . "• No message is lost: the bot here pauses just before the final backup (jobs off, webhook down - Telegram keeps the messages). The backup is checked by checksum on the other side, its tables are counted, and the webhook is set last, after all the data is in.\n"
            . "• If anything fails on the way, whatever this transfer made there is taken back out and the bot here starts again. A copy of this same bot left there by an earlier try is backed up first and put back if it fails.\n"
            . "• Bots on one server no longer delete each other's scheduled jobs, and their job locks are their own.</blockquote>",
    ],
    [
        'n' => 57,
        'date' => '2026-10-31',
        'fa' => "<blockquote>🧩 <b>بکاپ ربات میرزای اصلی: منو کامل و دیتابیس خودکار به‌روز</b>\n"
            . "• دکمه‌های 💰 افزایش موجودی و 🌏 تغییر زبان که در منوی ذخیره‌شدهٔ ربات‌های قدیمی نبودند، خودکار برمی‌گردند (افزایش موجودی روشن، تغییر زبان مثل نصب تازه پنهان؛ از ✏️ نام و نمایش دکمه‌ها روشنش کن). دکمه‌های قدیمی که این ربات ندارد (مثل text_agentpanel) دیگر نشان داده نمی‌شوند. رنگ و ترتیبی که روی دکمه‌ها گذاشته بودی می‌ماند.\n"
            . "• «🔄 ریست به پیش‌فرض» در ✏️ و 📐 حالا همهٔ دکمه‌ها را دارد؛ ریست 📐 دکمهٔ تغییر زبان را هم سر جایش می‌گذارد. هر بار که می‌زنی پیام کوتاهی بالای صفحه می‌آید: «به پیش‌فرض برگشت» یا «از قبل روی پیش‌فرض بود» - قبلاً اگر چیزی عوض نمی‌شد، هیچ اتفاقی نمی‌افتاد.\n"
            . "• دیتابیسی که دستی (مثلاً با phpMyAdmin) از یک ربات قدیمی‌تر ریخته شود، با اولین پیام به ربات خودش به‌روز می‌شود: جدول‌ها و ستون‌های لازم اضافه می‌شوند و چیزی پاک نمی‌شود. ریستور از اسکریپت (گزینهٔ ۷)، آپدیت (گزینهٔ ۲) و /update هم همین کار را می‌کنند.</blockquote>",
        'en' => "<blockquote>🧩 <b>Backups of the original Mirza bot: a whole menu and a database brought up to date by itself</b>\n"
            . "• The 💰 Increase balance and 🌏 Change language buttons, missing from older bots' saved menus, come back by themselves (Increase balance shown, Change language hidden as on a fresh install - show it from ✏️ button names and visibility). Old buttons this bot does not have (like text_agentpanel) are no longer shown. The colours and order you gave the buttons stay.\n"
            . "• «🔄 Reset to default» in ✏️ and 📐 now has every button; the 📐 reset puts the change-language button back in its place too. Each tap now shows a short note at the top: «back to default» or «already at default» - before, a tap that changed nothing did nothing at all.\n"
            . "• A database imported by hand (phpMyAdmin, say) from an older bot brings itself up to date on the first message to the bot: the tables and columns it needs are added, nothing is removed. Restoring from the script (option 7), updating (option 2) and /update do the same.</blockquote>",
    ],
    [
        'n' => 58,
        'date' => '2026-10-31',
        'fa' => "<blockquote>🗂 <b>تاپیک‌های گروه گزارش‌ها دیگر تکراری ساخته نمی‌شوند</b>\n"
            . "• وقتی گروه گزارش‌ها را دوباره تنظیم می‌کنی (مثلاً بعد از بردن ربات به سرور دیگر)، ربات اول تاپیک‌هایی را که دارد چک می‌کند: همان‌ها را نگه می‌دارد و فقط تاپیکی را که نیست (یا دستی پاک شده) می‌سازد. آخرش هم می‌گوید چند تا از قبل بود، چند تا ساخته شد و چند تا تکراری حذف شد.\n"
            . "• اگر تلگرام وسط ساختن تاپیک‌ها بگوید «زیادی سریع» (Too Many Requests)، ربات کمی صبر می‌کند و ادامه می‌دهد؛ اگر باز هم نشد، دلیلش را می‌گوید و دفعهٔ بعد فقط باقی‌مانده‌ها را می‌سازد.\n"
            . "• ربات تاپیک‌هایی را که خودش می‌سازد یادداشت می‌کند. اگر روی سرور تازه گروه را تنظیم کنی و بعد بکاپ قدیمی را بریزی، تاپیک‌های اضافه‌ای که روی سرور تازه ساخته شده بود خودکار حذف می‌شوند و تاپیک‌های اصلی می‌مانند. تاپیک‌هایی که خودت دستی ساخته‌ای هیچ‌وقت پاک نمی‌شوند.\n"
            . "• تاپیک‌های تکراری‌ای که قبل از این آپدیت ساخته شده‌اند را ربات نمی‌شناسد (تلگرام به ربات لیست تاپیک‌ها را نمی‌دهد)؛ آن‌ها را یک بار دستی در تلگرام پاک کن.</blockquote>",
        'en' => "<blockquote>🗂 <b>The report group's topics are no longer made twice</b>\n"
            . "• Setting the report group again (after moving the bot to another server, say) checks the topics the bot already has: those are kept, and only a missing one (or one deleted by hand) is made. It then says how many were there, how many were made and how many duplicates went.\n"
            . "• When Telegram says «Too Many Requests» half way, the bot waits and carries on; if it still cannot, it says why, and the next try makes only the rest.\n"
            . "• The bot keeps a note of the topics it makes. Set the group up on a fresh server and then put the old backup back, and the extra topics the fresh server made are deleted by themselves; the original ones stay. Topics you made by hand are never deleted.\n"
            . "• Duplicates made before this update are unknown to the bot (Telegram gives bots no list of topics) - delete those once, by hand, in Telegram.</blockquote>",
    ],
    [
        'n' => 59,
        'date' => '2026-10-31',
        'fa' => "<blockquote>🔄 <b>انتقال از میرزای اصلی خودکار شد</b>\n"
            . "• اگر روی سرور ربات میرزای اصلی نصب باشد و Ciro نباشد، زدن «نصب» (گزینهٔ ۱) یا «آپدیت» (گزینهٔ ۲) در اسکریپت خودش آن را پیدا می‌کند و به Ciro منتقل می‌کند. دیگر لازم نیست گزینهٔ «Migrate» را انتخاب کنی؛ برای همین از منو برداشته شد (شماره‌های بقیهٔ گزینه‌ها عوض نشده).\n"
            . "• دیتابیس قدیمی همان می‌ماند و فقط چیزهای لازم به آن اضافه می‌شود؛ قبلش از دیتابیس و فایل‌ها بکاپ گرفته می‌شود و اگر وسط کار خطایی پیش بیاید، ربات قدیمی سر جایش برمی‌گردد. ربات‌های نماینده هم با آن منتقل می‌شوند.\n"
            . "• کرون‌های ربات (چک پرداخت‌ها، اطلاع‌رسانی‌ها و ...) همان موقع ساخته می‌شوند؛ دیگر لازم نیست اول پنل ادمین را باز کنی.</blockquote>",
        'en' => "<blockquote>🔄 <b>Moving from the original Mirza bot is automatic</b>\n"
            . "• When a server runs the original Mirza bot and no Ciro bot, Install (option 1) or Update (option 2) in the script finds it and moves it to Ciro by itself. Choosing «Migrate» is no longer needed, so it left the menu (the other options keep their numbers).\n"
            . "• Its database stays and is only added to; the database and files are backed up first, and a failure on the way puts the old bot back. Agents' bots move along.\n"
            . "• The bot's scheduled jobs (payment checks, notices ...) are set up right away - no need to open the admin panel first.</blockquote>",
    ],
    [
        'n' => 60,
        'date' => '2026-10-31',
        'fa' => "<blockquote>🌐 <b>عوض کردن دامنهٔ هر ربات از اسکریپت</b>\n"
            . "• در گزینهٔ ۵ (Renew SSL) اول رباتی را که می‌خواهی انتخاب می‌کنی، بعد بین «تمدید گواهی همین دامنه» و «عوض کردن دامنهٔ این ربات» یکی را می‌زنی. روی سروری که چند ربات دارد، فقط همان ربات عوض می‌شود.\n"
            . "• اسکریپت دامنهٔ جدید را چک می‌کند (درست باشد، مال ربات یا سایت دیگری روی سرور نباشد، به همین سرور اشاره کند)، گواهی SSL تازه می‌گیرد، و بعد سایت Apache، config.php، وبهوک ربات، وبهوک ربات‌های نماینده، کرون‌ها و پورت phpMyAdmin را به دامنهٔ جدید می‌برد. پورت ربات همان می‌ماند. گواهی دامنهٔ قبلی هم پاک می‌شود.\n"
            . "• تا وقتی گواهی دامنهٔ جدید گرفته نشده، ربات روی دامنهٔ قبلی کار می‌کند. اگر گواهی گرفته نشود یا Apache تنظیمات جدید را قبول نکند، همه‌چیز همان‌طور که بود برمی‌گردد.\n"
            . "• لینک‌هایی که قبلاً با دامنهٔ قدیمی برای کاربرها فرستاده شده، بعد از تغییر دیگر باز نمی‌شوند.</blockquote>",
        'en' => "<blockquote>🌐 <b>Change any bot's domain from the script</b>\n"
            . "• Option 5 (Renew SSL) asks which bot first, then whether to renew its certificate or change its domain. On a server with several bots, only that bot changes.\n"
            . "• The new domain is checked (a real domain, not another bot's or another site's here, pointing at this server), gets its own certificate, and then the Apache site, config.php, the bot's webhook, its agents' bots' webhooks, its scheduled jobs and phpMyAdmin's port move to it. The bot keeps its port. The old domain's certificate is removed.\n"
            . "• The bot keeps answering on the old domain until the new one has its certificate; if no certificate comes, or Apache refuses the new site, everything is put back as it was.\n"
            . "• Links sent to customers with the old domain stop working after the change.</blockquote>",
    ],
    [
        'n' => 61,
        'date' => '2026-10-31',
        'fa' => "<blockquote>🖥 <b>مدیریت نودها (ربکا): مصرف لحظه‌ای، هشدار مصرف بالا و ظاهر تازه</b>\n"
            . "• اسم دکمه شد «🖥 مدیریت نودها (ربکا)» و فقط روی پنل‌های ربکا نشان داده می‌شود؛ در پنل وب هم صفحهٔ نودها فقط وقتی پنل ربکا هست می‌آید.\n"
            . "• باگ رفع شد: CPU، RAM و سرعت (پهنای باند) هر نود حالا در صفحهٔ نودها، صفحهٔ هر نود، گزارش تصویری (هم عکس و هم کپشن) و پنل وب نشان داده می‌شود؛ همان مقدار لحظه‌ای که موقع باز کردن صفحه دارد. (ربکا در فهرست کلی نودها این اعداد را نمی‌فرستد؛ حالا ربات از هر نود جدا و همزمان می‌پرسد.)\n"
            . "• 🚨 هشدار مصرف بالا (زیر «آپدیت سرویس همهٔ نودها»): اگر CPU یا RAM یک نود از حد تعیین‌شده بالاتر برود و حدود ۴ دقیقه بماند، ربات خبر می‌دهد و وقتی پایین آمد هم خبر می‌دهد. حد پیش‌فرض ۹۰٪ است و از دکمه‌های آماده (۷۰ تا ۹۵) یا با تایپ عدد دلخواه عوض می‌شود. این تنظیم برای هر ربات روی سرور جداست.\n"
            . "• اگر مقصد گزارش‌ها «گروه گزارش‌ها» باشد، گزارش نودها در تاپیک جدای «🖥 گزارش نودها (ربکا)» می‌آید.\n"
            . "• متن صفحه‌ها، کپشن گزارش تصویری و پیام‌های قطع و وصل مرتب شد: اسم نود بولد، IP جلوی آن، و هر نود در یک قاب نقل‌قول.</blockquote>",
        'en' => "<blockquote>🖥 <b>Node management (Rebecca): usage right now, a high-usage alert and a tidier look</b>\n"
            . "• The button is now «🖥 Node management (Rebecca)» and shows on Rebecca panels only; the web panel's node page appears only when there is a Rebecca panel.\n"
            . "• Fixed: each node's CPU, RAM and speed (bandwidth) now show on the node list, each node's screen, the report (picture and caption) and the web panel - as they are when the page opens. (Rebecca's node list leaves these out; the bot now asks each node, all at once.)\n"
            . "• 🚨 High usage alert (under «Update every node's service»): when a node's CPU or RAM stays over its limit for about 4 minutes the bot says so, and again when it is back down. The limit is 90% by default and can be changed with ready buttons (70 to 95) or any number typed in. Each bot on a server has its own setting.\n"
            . "• With «report group» as the destination, node reports go to a topic of their own: «🖥 گزارش نودها (ربکا)».\n"
            . "• The screens, the report caption and the up/down messages read better: the node's name in bold with its IP beside it, each node in its own quote block.</blockquote>",
    ],
    [
        'n' => 62,
        'date' => '2026-10-31',
        'fa' => "<blockquote>🔁 <b>یادآوری قطعی نود و مصرف بالا: با فاصله و تعداد دلخواه، بدون رگبار پیام</b>\n"
            . "• پایش هر ۲ دقیقه نودها را چک می‌کند. تا حالا برای هر قطعی فقط یک پیام «قطع شد» و یک پیام «وصل شد» می‌فرستاد؛ حالا اگر نودی قطع بماند، با فاصله‌ای که خودت تعیین می‌کنی دوباره یادآوری می‌فرستد، تا تعداد پیامی که تعیین می‌کنی؛ بعد ساکت می‌شود تا نود وصل شود.\n"
            . "• در «🖥 مدیریت نودها (ربکا)» دکمهٔ «🔁 یادآوری قطعی نود»: روشن/خاموش، فاصلهٔ پیام‌ها (۲ تا ۱۴۴۰ دقیقه، با دکمه‌های آماده یا عدد دلخواه) و تعداد کل پیام‌ها برای هر قطعی (۲ تا ۱۰۰ یا نامحدود تا وصل شدن؛ پیام اول هم حساب می‌شود). خاموش یعنی فقط یک پیام. پیش‌فرض: روشن، هر ۱۰ دقیقه، ۵ پیام. پیام آخر می‌گوید «این آخرین یادآوری بود». خود پنل ربکا هم اگر جواب ندهد همین یادآوری را دارد.\n"
            . "• همین تنظیم برای 🚨 هشدار مصرف بالای CPU و RAM هم هست (جدا از قطعی)، با دکمهٔ «🔁 یادآوری مصرف بالا» داخل همان صفحه. پیش‌فرض: روشن، هر ۳۰ دقیقه، ۳ پیام.\n"
            . "• در پنل وب هم دو کارت یادآوری هست. تنظیم‌ها برای هر ربات روی سرور جداست.</blockquote>",
        'en' => "<blockquote>🔁 <b>Reminders while a node is down or over its limit: your gap, your count, no burst of messages</b>\n"
            . "• The watch looks at the nodes every 2 minutes. Until now it sent one «went down» and one «back up» per outage; now a node that stays down is reminded about at the gap you set, up to the number of messages you set, and then it goes quiet until the node is back.\n"
            . "• In «🖥 Node management (Rebecca)», the «🔁 Down reminders» button: on/off, the gap between messages (2 to 1440 minutes, ready buttons or any number) and the messages in all for one outage (2 to 100, or unlimited until it is back; the first one counts). Off means a single message. Default: on, every 10 minutes, 5 messages. The last one says it is the last. The Rebecca panel itself has the same reminders when it stops answering.\n"
            . "• The same setting exists for the 🚨 high CPU/RAM alert (apart from the down one), with the «🔁 High usage reminders» button on that screen. Default: on, every 30 minutes, 3 messages.\n"
            . "• The web panel has both as two cards. Each bot on a server has its own settings.</blockquote>",
    ],
    [
        'n' => 63,
        'date' => '2026-10-31',
        'fa' => "<blockquote>⬆️ <b>نسخهٔ دلخواه Xray و بروزرسانی گزارش تصویری نودها (ربکا)</b>\n"
            . "• در «آپدیت هستهٔ Xray» کنار هشت نسخهٔ آخر یک دکمهٔ «✏️ نسخهٔ دلخواه (Custom version)» آمد. نسخه را می‌نویسی (مثل <code>25.10.15</code>؛ حرف v را ربات خودش می‌گذارد)، ربات چک می‌کند چنین نسخه‌ای از Xray وجود دارد: اگر نبود می‌گوید «وجود ندارد» و چند نسخهٔ آخر را نشان می‌دهد، اگر بود یک بار می‌پرسد «نصب بشه؟» و با «بله» هسته عوض می‌شود. نسخه‌های قدیمی‌تر از ۵۰ تای آخر را هم از GitHub می‌پرسد. اگر نتواند چک کند (مثلاً GitHub جواب نداد) نصب نمی‌کند و دلیلش را می‌گوید. چرا چک؟ ربکا هر نسخه‌ای را «قبول شد» می‌گوید و اگر نسخه نباشد بی‌صدا شکست می‌خورد.\n"
            . "• پنل وب هم همین را دارد: در بخش «بیشتر» هر نود، کادر نسخهٔ دلخواه؛ بعد از چک، بالای صفحه تأیید می‌گیرد.\n"
            . "• زیر «📊 گزارش تصویری نودها» دکمهٔ «🔄 بروزرسانی» آمد: عکس و کپشن همان پیام با اعداد تازهٔ CPU، RAM و سرعت عوض می‌شود (پیام جدید نمی‌آید)، و دکمهٔ «❌ بستن» پیام را پاک می‌کند. در پنل وب هم کنار گزارش دکمهٔ بروزرسانی هست.</blockquote>",
        'en' => "<blockquote>⬆️ <b>A custom Xray version, and a refresh for the node report picture (Rebecca)</b>\n"
            . "• «Update the Xray core» has a «✏️ Custom version» button next to the newest eight. Write the version (like <code>25.10.15</code>; the bot adds the v), and the bot checks that such an Xray release exists: if not it says so and shows the newest few, if it does it asks once «install it?» and a «yes» changes the core. Versions older than the last 50 are looked up at GitHub. When it cannot check (GitHub does not answer, say) it installs nothing and says why. Why check? Rebecca answers «accepted» to any version and a missing one fails silently.\n"
            . "• The web panel has it too: under «More» of each node, a custom version box; after the check it asks for a confirmation at the top of the page.\n"
            . "• «📊 Node report picture» got a «🔄 Refresh» button: the picture and caption of that very message change to the current CPU, RAM and speed (no new message), and «❌ Close» deletes it. The web panel has a refresh button beside the report as well.</blockquote>",
    ],
    [
        'n' => 64,
        'date' => '2026-10-31',
        'fa' => "<blockquote>🔐 <b>مراحل خرید: ادیت روی همان پیام و بازگشت درست به انتخاب پنل؛ پنل وب: اسکرول لمسی و لیست‌های همیشه تازه</b>\n"
            . "• خرید اشتراک (پنل ← دسته‌بندی ← سرویس): اگر مرحلهٔ مقصد استیکر نداشته باشد، کپشن و دکمه‌ها روی همان پیام ادیت می‌شوند و چیزی پاک یا دوباره فرستاده نمی‌شود. فقط وقتی مرحلهٔ مقصد استیکر دارد، پیام و استیکر قبلی می‌روند و استیکر و کپشن تازه می‌آیند. (قبلاً برگشتن از لیست سرویس‌ها به لیست پنل‌ها، وقتی دسته‌بندی خاموش بود، همیشه پیام را پاک می‌کرد و دوباره می‌فرستاد.) اگر موقع برگشتن، استیکرِ مرحله‌ای که جلوتر بوده روی صفحه مانده باشد همان استیکر برداشته می‌شود؛ وقتی جلو می‌روی استیکر می‌ماند.\n"
            . "• وقتی دسته‌بندی خاموش است، دکمهٔ زیر لیست سرویس‌ها «🔙 بازگشت به انتخاب پنل» است و واقعاً به انتخاب پنل برمی‌گردد (قبلاً نوشته بود «بازگشت به لیست دسته بندی»). این دکمه در 🎨 آیتم جدا دارد (اسم، ایموجی، رنگ، مخفی کردن؛ برای هر زبان جدا). اگر دکمهٔ «بازگشت به لیست دسته بندی» را قبلاً مخفی کرده بودی و این یکی را هم نمی‌خواهی، از 🎨 مخفی‌اش کن.\n"
            . "• پنل وب، اسکرول لمسی: روی تبلت یا گوشی خوابیده، جایی که جدول از عرض صفحه عریض‌تر است (محصولات، کیبورد، منو، ظاهر دکمه‌ها)، انگشت روی ردیف‌ها صفحه را اسکرول نمی‌کرد. درست شد؛ کشیدن افقی خود جدول مثل قبل کار می‌کند.\n"
            . "• پنل وب، بعد از افزودن یا ویرایش (محصول، دسته‌بندی و ...) لازم نیست صفحه را رفرش کنی: اگر ذخیره چیزی بیرون از فرم عوض کند (لیست، شمارنده، پیش‌نمایش)، صفحه همان‌جا و با همان اسکرول دوباره بارگذاری می‌شود. فرم‌های تنظیمات مثل قبل بدون بارگذاری ذخیره می‌شوند.</blockquote>",
        'en' => "<blockquote>🔐 <b>Purchase steps: edited in place, and a back button that says where it goes; web panel: touch scrolling and lists that are always current</b>\n"
            . "• Buying a subscription (panel → category → service): when the step you are going to has no sticker, its caption and buttons are edited on the same message - nothing is deleted or sent again. Only when that step has a sticker of its own do the old message and sticker go and the new sticker and caption come. (Before, going back from the service list to the panel list with categories off always deleted the message and sent a new one.) Going back past the step that put a sticker up removes that sticker; going forward keeps it.\n"
            . "• With categories off, the button under the service list is now «🔙 Back to panel selection» and really goes back to the panels (it used to say «Back to the category list»). It is an item of its own in 🎨 (name, emoji, colour, hide; separate per language). If you had hidden «Back to the category list» before and do not want this one either, hide it in 🎨.\n"
            . "• Web panel, touch scrolling: on a tablet or a phone held sideways, where a table is wider than the screen (products, keyboard, menu, button looks), a finger on the rows would not scroll the page. Fixed; dragging the table sideways works as before.\n"
            . "• Web panel, after adding or editing (a product, a category ...) there is no need to refresh: when a save changes anything outside the form (a list, a counter, a preview) the page loads again where it stands, with the same scroll. Settings forms still save without loading.</blockquote>",
    ],
    [
        'n' => 65,
        'date' => '2026-11-01',
        'fa' => "<blockquote>🎨 <b>پنل وب: آیکون به‌جای ایموجی، ظاهر و حرکت تازه</b>\n"
            . "• متن خود سایت دیگر ایموجی ندارد: عنوان‌ها، تب‌ها، برچسب‌ها، راهنماها و پیام‌ها آیکون‌های خطی یک‌دست دارند. متن و دکمه‌های خود ربات، پیش‌نمایش‌ها و نام‌هایی که خودت گذاشته‌ای (محصول، دسته‌بندی، کاربر) همان‌طور با ایموجی می‌مانند.\n"
            . "• هر بخش منو آیکون خودش را دارد و همان آیکون کنار عنوان صفحه می‌آید. عددهای داشبورد هم آیکون دارند و بار اولِ هر ورود از صفر بالا می‌روند.\n"
            . "• بعد از ذخیره، دکمه‌ی ذخیره یک لحظه تیک می‌شود. اعلان‌ها تیک یا هشدارشان را می‌کشند، دکمه‌ی بستن دارند و تا وقتی موس رویشان است نمی‌روند.\n"
            . "• فهرست خالی یک طرح آرام دارد، انتخاب رنگ دکمه رنگ انتخاب‌شده را داخل خودش نشان می‌دهد و فوکوس صفحه‌کلید همیشه پیداست.\n"
            . "• اگر در تنظیمات گوشی یا سیستم «حرکت کمتر»، «شفافیت کمتر» یا «کنتراست بیشتر» روشن باشد، پنل هم همان را رعایت می‌کند.</blockquote>",
        'en' => "<blockquote>🎨 <b>Web panel: icons instead of emoji, a fresher look and motion</b>\n"
            . "• The panel's own text has no emoji any more: headings, tabs, labels, hints and messages use one set of line icons. The bot's own texts and buttons, previews and the names you gave things (products, categories, users) keep their emoji.\n"
            . "• Every menu item has its own icon, shown beside the page title too. The dashboard figures have icons and count up from zero the first time in a session.\n"
            . "• After a save the save button turns into a tick for a moment. Notices draw their tick or warning, have a close button, and stay while the pointer is on them.\n"
            . "• An empty list shows a quiet drawing, a button colour picker shows the chosen colour inside it, and keyboard focus is always visible.\n"
            . "• With «reduce motion», «reduce transparency» or «increase contrast» on in your phone or system settings, the panel follows them.</blockquote>",
    ],
    [
        'n' => 66,
        'date' => '2026-11-01',
        'fa' => "<blockquote>📥 <b>ایمپورت بکاپ از داخل ربات و پنل وب</b>\n"
            . "• در «🗄 تنظیمات بکاپ» دکمهٔ «📥 ایمپورت بکاپ» آمد: فایل بکاپ دیتابیس (zip یا sql) و/یا زیپ فولدر ربات را همان‌جا بفرست؛ یکی یا هر دو، پشت سر هم. ربات خودش تشخیص می‌دهد هر فایل دیتابیس است یا فولدر ربات، و اگر زیپ رمز داشته باشد رمزش را می‌پرسد (پیام رمز بلافاصله پاک می‌شود؛ رمز اشتباه را می‌گوید). اگر یک زیپ چند فایل دیتابیس داشته باشد، انتخاب می‌کنی کدام.\n"
            . "• قبل از هر کاری خلاصه را می‌بینی (از کدام دیتابیس، کدام نسخه، دقیقاً چه چیزی عوض می‌شود) و یک تأیید آخر. بعد اسکریپت سرور، دقیقاً مثل گزینهٔ ۷ (Restore)، اول از وضعیت فعلی نسخهٔ امن می‌گیرد و بکاپ را جایگزین می‌کند؛ پیشرفت کار روی همان پیام با نوار درصد دیده می‌شود و آخرش نتیجهٔ هر فایل و مسیر نسخهٔ امن می‌آید. اگر وسط کار خطایی پیش بیاید همان مورد به حالت قبل برمی‌گردد.\n"
            . "• فولدرِ نسخهٔ قدیمی‌تر (یا پروژهٔ اصلی) روی کدهای فعلی ریخته نمی‌شود؛ فقط داده‌هایش (ربات‌های نمایندگی و توکن API) منتقل می‌شود. config.php همین سرور همیشه سر جایش می‌ماند.\n"
            . "• تلگرام به ربات اجازهٔ دریافت فایل بزرگ‌تر از ۲۰ مگابایت را نمی‌دهد؛ برای همین بکاپ فولدر ربات از این به بعد بدون پوشهٔ vendor گرفته می‌شود (این پوشه را نصب‌کننده خودش می‌سازد) و خیلی کوچک‌تر است. برای فایل بزرگ‌تر از پنل وب استفاده کن.\n"
            . "• پنل وب: صفحهٔ تازهٔ «بکاپ» همین کار را با آپلود فایل انجام می‌دهد (تا سقف آپلود سرور)، با همان تشخیص، رمز، انتخاب فایل دیتابیس، تأیید، نوار پیشرفت زنده و نتیجه.\n"
            . "• این قابلیت به اسکریپت سرور تازه نیاز دارد؛ با یک بار «🔄 آپدیت ربات» اسکریپت هم به‌روز می‌شود.</blockquote>",
        'en' => "<blockquote>📥 <b>Import a backup from the bot and the web panel</b>\n"
            . "• «🗄 Backup settings» has a «📥 Import a backup» button: send the database backup (zip or sql) and/or the bot folder zip right there, one or both, one after the other. The bot works out which file is the database and which the bot's folder, and asks for the password of a zip that has one (the password message is deleted at once; a wrong one is said so). A zip with several database files lets you choose which.\n"
            . "• Before anything happens you see a summary (which database it came from, which version, exactly what is replaced) and a last confirmation. Then the server script, exactly like option 7 (Restore), first takes a safe copy of what is there now and puts the backup in place; the progress shows on the same message as a percentage bar, and at the end the result of each file and where the safe copy is. Anything failing half way is put back as it was.\n"
            . "• A folder of an older version (or of the original project) is not laid over the current code; only its data (the agents' bots and the API token) is carried over. This server's config.php always stays.\n"
            . "• Telegram does not let a bot receive files larger than 20 MB, so the bot folder backup is now taken without the vendor folder (the installer builds it) and is much smaller. Use the web panel for a larger file.\n"
            . "• Web panel: a new «Backup» page does the same with an upload (up to the server's upload limit), with the same detection, password, database choice, confirmation, a live progress bar and the result.\n"
            . "• This needs the new server script; one «🔄 Update the bot» updates the script too.</blockquote>",
    ],
    [
        'n' => 67,
        'date' => '2026-11-01',
        'fa' => "<blockquote>👤 <b>پنل وب: عکس و اسم تلگرام ادمین</b>\n"
            . "• پایین منوی پنل وب، به‌جای شناسهٔ عددی و یک حرف، عکس پروفایل و اسم تلگرام ادمینی که وارد شده نشان داده می‌شود (روی آن بمانی، یوزرنیم تلگرامش هم می‌آید).\n"
            . "• ربات این‌ها را از تلگرام می‌گیرد و تا ۶ ساعت نگه می‌دارد؛ اگر اسم یا عکست را عوض کنی، حداکثر بعد از ۶ ساعت پنل هم عوض می‌شود. گرفتنش بعد از باز شدن صفحه انجام می‌شود، پس صفحه کند نمی‌شود.\n"
            . "• اگر تنظیمات حریم خصوصی تلگرام عکس را نشان ندهد، حرف اول اسم تلگرامت می‌آید. عکس فقط برای ادمینی که وارد پنل شده باز می‌شود.</blockquote>",
        'en' => "<blockquote>👤 <b>Web panel: the admin's Telegram photo and name</b>\n"
            . "• At the bottom of the web panel's menu, the signed-in admin's Telegram profile photo and name now show instead of a numeric id and a letter (hover it for their Telegram username).\n"
            . "• The bot fetches them from Telegram and keeps them for 6 hours; a new name or photo reaches the panel within 6 hours. The fetch happens after the page has opened, so pages are not slowed down.\n"
            . "• When Telegram's privacy settings hide the photo, the first letter of the Telegram name shows instead. The photo opens only for the admin signed in to the panel.</blockquote>",
    ],
    [
        'n' => 68,
        'date' => '2026-11-01',
        'fa' => "<blockquote>🧩 <b>حجم دلخواه (به‌جای «⚙️ سرویس دلخواه» قدیمی)</b>\n"
            . "• مشتری حجم و تعداد روز را خودش با ➖/➕، دکمه‌های عدد آماده یا تایپ عدد انتخاب می‌کند و قیمت همان لحظه در کپشن عوض می‌شود؛ با «✅ ادامه» خرید مثل یک بستهٔ معمولی جلو می‌رود (فاکتور، کد تخفیف، پرداخت). در خرید، خرید عمده و تمدید سرویس‌هایی که این‌طور خریده شده‌اند کار می‌کند.\n"
            . "• <b>پیش‌فرض برای همهٔ پنل‌ها خاموش است.</b>\n"
            . "• «⚙️ سرویس دلخواه» قدیمی کامل حذف شد (دکمه‌ها، قیمت‌ها و حداقل/حداکثرهایش). قیمت‌ها و محدوده‌هایش به‌عنوان تنظیمات اولیه منتقل شدند، ولی باید دوباره روشنش کنی. ربات نماینده‌ها هم از همین کلید پیروی می‌کنند.</blockquote>\n"
            . "<blockquote>📍 <b>کجاست؟</b>\n"
            . "• ✏️ مدیریت پنل ← انتخاب پنل ← 💰 قیمت‌گذاری ← 🧩 تنظیمات حجم دلخواه (منوی قیمت‌گذاری حالا دکمهٔ شیشه‌ای دارد)\n"
            . "• ✏️ مدیریت پنل ← انتخاب پنل ← ⚙️ وضعیت قابلیت ها پنل ← 🧩 حجم دلخواه (روشن/خاموش) و 🧩 تنظیمات حجم دلخواه\n"
            . "• 🏬 تنظیمات فروشگاه ← 🧩 حجم دلخواه (همهٔ پنل‌ها یک‌جا)\n"
            . "• 🎨 شخصی‌سازی ← 🛒 پیام‌ها و ظاهر مراحل خرید ← 🧩 حجم دلخواه (کپشن، دکمه‌ها و استیکر، برای هر زبان جدا)\n"
            . "• پنل وب: منوی «حجم دلخواه»</blockquote>\n"
            . "<blockquote>📖 <b>راه‌اندازی در ۵ قدم</b>\n"
            . "1️⃣ 💰 قیمت‌ها: برای هر زبان قیمت یک گیگ و یک روز را بگذار (هر زبان با واحد پول خودش). قیمت نماینده‌ها اگر خالی بماند، همان قیمت کاربر عادی است.\n"
            . "2️⃣ 🎛 حالت انتخاب: هر دو، فقط حجم (با مدت ثابت) یا فقط مدت (با حجم ثابت).\n"
            . "3️⃣ 📦 تنظیم حجم و 📅 تنظیم مدت: حداقل، حداکثر، گام ➖/➕، عدد شروع و دکمه‌های آماده؛ مدت می‌تواند چند گزینهٔ ثابت باشد (مثل ۳۰/۶۰/۹۰).\n"
            . "4️⃣ 👤 🤝 ⭐️ گروه‌ها: کاربر عادی، نماینده و نماینده سطح ۲ جدا.\n"
            . "5️⃣ دکمهٔ بالای صفحهٔ تنظیمات را بزن تا روشن شود.\n"
            . "قیمت = حجم × قیمت هر گیگ + روز × قیمت هر روز؛ تخفیف حساب مشتری و کد تخفیف مثل همیشه در فاکتور کم می‌شوند.</blockquote>\n"
            . "<blockquote>🧰 <b>فیلترها و امکانات</b>\n"
            . "👛 حداقل موجودی برای هر زبان (کمتر از آن، به‌جای صفحه پیام و دکمهٔ افزایش موجودی می‌آید) · 🔢 گرد کردن قیمت · 🎁 تخفیف پلکانی (مثلاً از ۵۰ گیگ به بالا ۱۰٪، تا ۵ پله) · 🔥 تخفیف ویژهٔ زمان‌دار · 💡 پیشنهاد بستهٔ آمادهٔ ارزان‌تر به مشتری · 🌐 انتخاب زبان‌ها · 📊 آمار فروش و پرفروش‌ترین انتخاب‌ها · ⚠️ هشدار وقتی زبانی قیمت ندارد یا حجم دلخواه از یک بستهٔ آماده ارزان‌تر درمی‌آید.\n"
            . "صفحه با آخرین انتخاب خود مشتری روی همان پنل باز می‌شود؛ در پنل وب یک ماشین‌حساب هم هست که قیمت هر گروه را قبل از ذخیره نشان می‌دهد.</blockquote>",
        'en' => "<blockquote>🧩 <b>Custom plans (in place of the old «⚙️ Custom service»)</b>\n"
            . "• The customer picks the volume and the number of days with ➖/➕, ready-number buttons or by typing, and the price changes in the caption as they go; «✅ Continue» carries on like any plan (invoice, discount code, payment). It works when buying, in a bulk purchase and when renewing services bought this way.\n"
            . "• <b>Off by default on every panel.</b>\n"
            . "• The old «⚙️ Custom service» is gone (its buttons, prices and minimums/maximums). Its prices and ranges were carried over as the starting settings, but it has to be turned on again. The resellers' bots follow the same switch.</blockquote>\n"
            . "<blockquote>📍 <b>Where is it?</b>\n"
            . "• ✏️ Panel management → the panel → 💰 Pricing → 🧩 Custom plan settings (the pricing menu now has glass buttons)\n"
            . "• ✏️ Panel management → the panel → ⚙️ Panel features → 🧩 Custom plan (on/off) and 🧩 Custom plan settings\n"
            . "• 🏬 Store settings → 🧩 Custom plan (every panel in one list)\n"
            . "• 🎨 Customization → 🛒 Purchase steps messages and look → 🧩 Custom plan (caption, buttons and sticker, per language)\n"
            . "• Web panel: the «Custom plan» menu</blockquote>\n"
            . "<blockquote>📖 <b>Set up in 5 steps</b>\n"
            . "1️⃣ 💰 Prices: per language, the price of one GB and one day (each language in its own currency). A reseller price left empty is the regular users' one.\n"
            . "2️⃣ 🎛 Mode: both, volume only (fixed days) or days only (fixed volume).\n"
            . "3️⃣ 📦 Volume and 📅 Days: minimum, maximum, ➖/➕ step, starting number and ready buttons; the days can be a few fixed options (like 30/60/90).\n"
            . "4️⃣ 👤 🤝 ⭐️ Groups: regular users, resellers and level-2 resellers, each on its own.\n"
            . "5️⃣ Tap the button at the top of the settings screen to turn it on.\n"
            . "Price = volume × price per GB + days × price per day; the customer's account discount and discount codes come off on the invoice as always.</blockquote>\n"
            . "<blockquote>🧰 <b>Filters and extras</b>\n"
            . "👛 Minimum balance per language (below it, a message and a top-up button instead of the screen) · 🔢 Price rounding · 🎁 Volume discounts (e.g. 10% from 50 GB up, up to 5 steps) · 🔥 A special sale with an end date · 💡 Suggesting a cheaper ready plan to the customer · 🌐 Languages · 📊 Sales and the most picked choices · ⚠️ Warnings when a language has no price or a custom plan undercuts a ready one.\n"
            . "The screen opens with the customer's own last choice on that panel; the web panel also has a calculator that shows each group's price before you save.</blockquote>",
    ],
    [
        'n' => 69,
        'date' => '2026-11-01',
        'fa' => "<blockquote>⚙️ <b>پنل ربکا: انتخاب سرویس از لیست، بدون نام کاربری</b>\n"
            . "• در پنل‌های ربکا، «⚙️ تنظیم پروتکل و اینباند» دیگر نام کاربری نمی‌خواهد: ربات سرویس‌های خود پنل ربکا را می‌گیرد و با دکمهٔ شیشه‌ای نشان می‌دهد (کنار هر کدام تعداد کاربرهایش). روی هر سرویس بزنی، همان ذخیره می‌شود.\n"
            . "• هر پنلِ ربات سرویس خودش را دارد؛ اگر دو پنل در ربات به یک پنل ربکا وصل باشند، می‌توانی برای پنل A یک سرویس و برای پنل B سرویس دیگری از همان پنل انتخاب کنی.\n"
            . "• سرویس اکانت تست جداست (تب «🎁 سرویس اکانت تست»)؛ پیش‌فرض «همون سرویس خرید» است.\n"
            . "• سرویسی که در پنل هاست فعال ندارد با ⚠️ نشان داده می‌شود و انتخاب نمی‌شود (کاربرش کانفیگ نمی‌گرفت). عوض کردن سرویس فقط روی کاربرهای جدید اثر دارد.\n"
            . "• اگر لیست از پنل گرفته نشود (پنل در دسترس نیست یا توکن دسترسی ندارد)، دلیلش نوشته می‌شود و روش قبلی (نام کاربری) به‌عنوان راه جایگزین هست. بقیهٔ نوع پنل‌ها مثل قبل با نام کاربری کار می‌کنند.\n"
            . "📍 مسیر: ✏️ مدیریت پنل ← انتخاب پنل ربکا ← ⚙️ تنظیم پروتکل و اینباند\n"
            . "🌐 پنل وب: «پنل‌ها (سرورها)» ← کارت «سرویس پنل‌های ربکا»</blockquote>",
        'en' => "<blockquote>⚙️ <b>Rebecca panels: pick the service from a list, no username</b>\n"
            . "• On Rebecca panels «⚙️ Set protocol and inbound» no longer asks for a username: the bot fetches the Rebecca panel's own services and shows them as glass buttons (each with its number of users). Tap one and it is saved.\n"
            . "• Each panel of the bot keeps its own service; when two panels of the bot point at one Rebecca panel, panel A can use one service and panel B another one of the same panel.\n"
            . "• Test accounts have a service of their own (the «🎁 Test account service» tab); by default it is the purchase service.\n"
            . "• A service with no working host in the panel shows ⚠️ and cannot be picked (its users would get no config). A new service applies to new users only.\n"
            . "• When the list cannot be read (the panel is unreachable or the token has no access), the reason is shown and the old way (a username) is there as a fallback. Other panel types keep the username way.\n"
            . "📍 Path: ✏️ Panel management → a Rebecca panel → ⚙️ Set protocol and inbound\n"
            . "🌐 Web panel: «Panels (servers)» → the «Rebecca panels' service» card</blockquote>",
    ],
    [
        'n' => 70,
        'date' => '2026-11-01',
        'fa' => "<blockquote>🧩 <b>حجم دلخواه: حذف گام ➖/➕ و توضیح روشن‌تر «حجم ثابت»</b>\n"
            . "• «👣 گام ➖/➕» حجم حالا قابل حذف است: عدد <b>0</b> بفرست (در پنل وب هم بنویس 0) تا دکمه‌های ➖/➕ از صفحهٔ مشتری برداشته شوند؛ مشتری حجم را از دکمه‌های آماده یا با «✏️ وارد کردن عدد» انتخاب می‌کند. اگر نه گامی بماند نه دکمهٔ آماده‌ای، دکمهٔ تایپ عدد همیشه نشان داده می‌شود (حتی اگر در 🎨 پنهانش کرده باشی) تا مشتری بن‌بست نخورد.\n"
            . "• «حجم ثابت» فقط برای حالت «📅 فقط مدت» است: همهٔ سرویس‌ها همان حجم را می‌گیرند و مشتری فقط تعداد روز را انتخاب می‌کند (مثلاً «۱۰۰ گیگ برای هر چند روز»). «مدت ثابت» هم همین‌طور برای «📦 فقط حجم». در حالت «هر دو» این دو عدد اثری ندارند، برای همین حالا فقط وقتی دیده می‌شوند که کاربرد دارند؛ در «🎛 حالت انتخاب» هم دکمهٔ تنظیم عدد ثابتِ همان حالت زیر دکمه‌ها آمده و توضیح هر حالت با مثال نوشته شده.\n"
            . "📍 مسیر: ✏️ مدیریت پنل ← انتخاب پنل ← 💰 قیمت‌گذاری ← 🧩 تنظیمات حجم دلخواه ← 📦 تنظیم حجم / 🎛 حالت انتخاب\n"
            . "🌐 پنل وب: «حجم دلخواه» ← کارت «حجم»</blockquote>",
        'en' => "<blockquote>🧩 <b>Custom plans: the ➖/➕ step can be removed, and «fixed volume» is explained</b>\n"
            . "• The volume's «👣 ➖/➕ step» can now be removed: send <b>0</b> (type 0 in the web panel too) and the ➖/➕ buttons leave the customer's screen; the customer picks the volume from the ready buttons or with «✏️ Type a number». When neither a step nor a ready button is left, the type-a-number button is always shown (even if you hid it in 🎨) so the customer is never stuck.\n"
            . "• «Fixed volume» belongs to the «📅 Days only» mode alone: every service gets that volume and the customer picks only the number of days (e.g. «100 GB for as many days as you like»). «Fixed days» is the same for «📦 Volume only». In «Both» mode these two numbers do nothing, so they now show only when they apply; the «🎛 Choice mode» screen also has a button for the fixed number of the current mode, and each mode is explained with an example.\n"
            . "📍 Path: ✏️ Panel management → the panel → 💰 Pricing → 🧩 Custom plan settings → 📦 Volume / 🎛 Choice mode\n"
            . "🌐 Web panel: «Custom plan» → the «Volume» card</blockquote>",
    ],
    [
        'n' => 71,
        'date' => '2026-11-01',
        'fa' => "<blockquote>🧩 <b>حجم دلخواه: چند گام، منوی مرتب‌تر، و 🎨 که دوباره باز می‌شود</b>\n"
            . "• «👣 گام‌ها»: برای حجم و برای مدت تا ۳ گام می‌گذاری (مثلاً ۱ و ۱۰)؛ هر گام یک جفت دکمهٔ −/+ زیر صفحهٔ مشتری می‌سازد و هر کدام را می‌توانی عوض یا حذف کنی. گام‌های قبلی (مثل ۱ و ۵) همان‌طور مانده‌اند و حالا قابل تغییرند؛ بدون هیچ گامی هم می‌شود (مشتری از دکمه‌های آماده یا با تایپ عدد انتخاب می‌کند).\n"
            . "• 🎨 ← «🛒 پیام‌ها و ظاهر مراحل خرید» بعد از آپدیت قبلی با ۸۴ دکمه شلوغ‌ترین صفحهٔ ربات شده بود و (به احتمال زیاد به همین دلیل) باز نمی‌شد. حجم دلخواه حالا صفحهٔ خودش را دارد (🎨 ← 🛒 ← 🧩 حجم دلخواه؛ تکه‌های متنش یک پله داخل‌تر) و آن صفحه به اندازهٔ قبل برگشته. اگر تلگرام صفحه‌ای از 🎨 را قبول نکند، حالا دلیلش را در یک پاپ‌آپ می‌نویسد.\n"
            . "• تنظیمات مرتب‌تر شد: «🔎 فیلترها و تخفیف‌ها» (حداقل موجودی، گرد کردن، تخفیف پلکانی، تخفیف ویژه، پیشنهاد بستهٔ ارزان‌تر)، «👥 گروه‌ها و زبان‌ها» و «👁 پیش‌نمایش» که صفحهٔ مشتری را برای هر زبان و گروه نشان می‌دهد. صفحهٔ «📅 تنظیم مدت» هم فقط چیزی را نشان می‌دهد که به روش انتخاب مربوط است.\n"
            . "📍 مسیر: ✏️ مدیریت پنل ← انتخاب پنل ← 💰 قیمت‌گذاری ← 🧩 تنظیمات حجم دلخواه ← 📦 تنظیم حجم ← 👣 گام‌ها\n"
            . "🌐 پنل وب: «حجم دلخواه» ← کارت «حجم» و «مدت» (فیلد «گام‌ها» با پیش‌نمایش دکمه‌ها)</blockquote>",
        'en' => "<blockquote>🧩 <b>Custom plans: several steps, a tidier menu, and 🎨 opens again</b>\n"
            . "• «👣 Steps»: for the volume and for the days you can set up to 3 steps (e.g. 1 and 10); each makes a −/+ pair of buttons on the customer's screen, and each can be changed or removed. The old steps (like 1 and 5) were kept as they were and can now be edited; no steps at all works too (the customer picks from the ready buttons or types a number).\n"
            . "• 🎨 → «🛒 Messages and look of the purchase steps» had become the busiest screen of the bot after the previous update (84 buttons) and, most likely for that reason, would not open. Custom plans now have a screen of their own (🎨 → 🛒 → 🧩 Custom plan; its text pieces one level deeper) and that screen is back to its old size. If Telegram refuses any 🎨 screen, the bot now says why in a popup.\n"
            . "• The settings are tidier: «🔎 Filters and discounts» (minimum balance, rounding, volume discounts, the special sale, the cheaper-plan suggestion), «👥 Groups and languages» and «👁 Preview», which shows the customer's screen for every language and group. The «📅 Days» screen shows only what the chosen way of picking uses.\n"
            . "📍 Path: ✏️ Panel management → the panel → 💰 Pricing → 🧩 Custom plan settings → 📦 Volume → 👣 Steps\n"
            . "🌐 Web panel: «Custom plan» → the «Volume» and «Days» cards (a «Steps» field with a preview of the buttons)</blockquote>",
    ],
    [
        'n' => 72,
        'date' => '2026-11-01',
        'fa' => "<blockquote>📐 <b>جای دکمهٔ «🧩 حجم دلخواه» را حالا می‌شود تعیین کرد</b>\n"
            . "• دکمهٔ حجم دلخواه همیشه ته لیست محصول‌ها می‌افتاد و در «📐 چیدمان» اصلاً نبود. حالا خودش یک آیتم لیست است: جابه‌جایش می‌کنی، کنار یک محصول می‌گذاری (نیم‌عرض)، مخفی می‌کنی، و اسم، ایموجی و رنگش را همان‌جا عوض می‌کنی. تا چیزی تنظیم نکنی همان‌جایی می‌ماند که بود (آخر لیست).\n"
            . "• آیتمش فقط وقتی در چیدمان هست که حجم دلخواه روی دست‌کم یک پنل روشن باشد؛ و «حداقل یک دکمه باید بماند» فقط محصول‌ها را می‌شمارد، چون همهٔ پنل‌ها حجم دلخواه ندارند.\n"
            . "• یک خطای خاموش هم درست شد: اگر آخرین چیزی که ربات برای یک کاربر نگه داشته بود فقط یک عدد بود (مثل مبلغ یک افزایش موجودی نیمه‌کاره)، زدن دکمهٔ حجم دلخواه خطای سرور می‌داد و جوابی نمی‌آمد.\n"
            . "📍 مسیر: 🎨 ← ظاهر دکمه‌های محصول ← 📐 چیدمان (یا 🛍 ✏️ ویرایش محصول ← 📐 چیدمان)\n"
            . "🌐 پنل وب: «ظاهر دکمه‌ها» ← «محصولات»</blockquote>",
        'en' => "<blockquote>📐 <b>The place of the «🧩 Custom plan» button can now be set</b>\n"
            . "• The custom plan button always landed at the end of the product list and was not in «📐 Layout» at all. It is now an item of that list: move it, put it beside a product (half width), hide it, and change its name, emoji and colour right there. Until you set something it stays where it was (last).\n"
            . "• Its item is in the layout only while custom plans are on for at least one panel; and «at least one button must stay» counts the products only, since not every panel offers custom plans.\n"
            . "• A silent error is fixed too: when the last thing the bot had kept for a user was just a number (like the amount of a top-up left half done), tapping the custom plan button gave a server error and no answer.\n"
            . "📍 Path: 🎨 → Product button looks → 📐 Layout (or 🛍 ✏️ Edit product → 📐 Layout)\n"
            . "🌐 Web panel: «Button looks» → «Products»</blockquote>",
    ],
    [
        'n' => 73,
        'date' => '2026-11-01',
        'fa' => "<blockquote>💰 <b>خرید با موجودی کم: فاکتور شارژ دقیقاً به اندازهٔ کمبود، و خرید خودکار بعد از پرداخت</b>\n"
            . "• وقتی مشتری برای خرید کانفیگ (چه محصول آماده، چه 🧩 حجم دلخواه) موجودی کم دارد و «تایید و پرداخت» را می‌زند، پیام «موجودی کافی نیست» حالا می‌گوید چقدر کم دارد. دکمهٔ «افزایش موجودی» او را به روش‌های پرداخت می‌برد و فاکتوری که می‌سازد دقیقاً همان کمبود است (مثل تمدید: ۸۰ هزار تومان قیمت، ۴۰ هزار موجودی ← فاکتور ۴۰ هزار).\n"
            . "• بعد از پرداخت لازم نیست مشتری دوباره راه را برود: مبلغ به موجودی اضافه می‌شود، سرویس خودکار ساخته می‌شود، قیمت از موجودی کم می‌شود و اگر چیزی اضافه مانده بود در موجودی می‌ماند.\n"
            . "• فیلتر «حداقل و حداکثر افزایش موجودی» هر درگاه رعایت می‌شود. اگر کمبود کمتر از حداقل (یا بیشتر از حداکثر) درگاهی که مشتری زد باشد، فاکتور همان مبلغ ساخته نمی‌شود؛ او به همان مسیر همیشگی «افزایش موجودی» (مبلغ‌های آماده و «مبلغ دلخواه») می‌رود و بالای صفحه یک نقل‌قول دلیلش را می‌گوید. هر مبلغی که شارژ کند، به شرط اینکه قیمت را بپوشاند، سرویس بعد از پرداخت خودکار ساخته می‌شود؛ اگر هنوز کم بود، کمبود باقی‌مانده را با دکمهٔ «افزایش موجودی» می‌بیند. این فیلتر برای تمدید هم همین‌طور است.\n"
            . "• تمدید با کش‌بک: کش‌بک تمدید حالا به موجودی اضافه می‌شود (قبلاً جایگزین آن می‌شد).\n"
            . "• صفحهٔ 🧩 مشتری دکمهٔ «💳 افزایش موجودی» ندارد؛ «✅ ادامه» همین مسیر را می‌رود و مبلغ کمبود را همراه می‌برد. (دکمهٔ افزایش موجودی صفحهٔ «حداقل موجودی» مثل قبل ماند.)\n"
            . "📍 مسیر مشتری: 🔐 خرید اشتراک ← انتخاب ← تایید و پرداخت ← 💰 افزایش موجودی\n"
            . "🎨 متن‌ها: 🎨 ← «پیام‌ها و ظاهر مراحل خرید» ← «💡 جملهٔ کمبود موجودی»؛ 🎨 ← «پیام‌ها و ظاهر افزایش موجودی» ← دو نقل‌قول فیلتر، «بعد از شارژ هنوز کم است» و دو پاپ‌آپ (هر زبان جدا)</blockquote>",
        'en' => "<blockquote>💰 <b>Buying with too little balance: a top-up invoice for exactly what is lacking, and the purchase made after the payment</b>\n"
            . "• When a customer is short of balance for a config (a ready product or a 🧩 custom plan) and taps «Confirm and pay», the «insufficient balance» message now says how much is lacking. «Increase balance» takes them to the payment methods and the invoice it makes is exactly that amount (like a renewal: price 80,000, balance 40,000 → an invoice of 40,000).\n"
            . "• After the payment the customer does not go the whole way again: the amount is credited, the service is made by itself, the price comes off the balance, and anything left over stays.\n"
            . "• Each gateway's «minimum and maximum top-up» still holds. If what is lacking is below the minimum (or above the maximum) of the gateway they picked, an invoice of that amount is not made; they go the usual «Increase balance» way (ready amounts and «Custom amount») with a quote at the top saying why. Whatever amount they pay, as long as it covers the price, the service is made by itself after the payment; if it is still short they see what is left with an «Increase balance» button. Renewals work the same way.\n"
            . "• Renewal cashback is now added to the balance (it used to replace it).\n"
            . "• The 🧩 customer screen no longer has a «💳 Increase balance» button; «✅ Continue» takes that way and carries the missing amount. (The button on the «minimum balance» screen stays as it was.)\n"
            . "📍 Customer path: 🔐 Buy → pick → Confirm and pay → 💰 Increase balance\n"
            . "🎨 Texts: 🎨 → «Messages and look of the purchase steps» → «💡 Short-of-balance line»; 🎨 → «Messages and look of top-up» → the two filter quotes, «still short after the top-up» and two popups (each language apart)</blockquote>",
    ],
    [
        'n' => 74,
        'date' => '2026-11-01',
        'fa' => "<blockquote>💳 <b>پرداخت مستقیم: یه دکمهٔ جدید کنار «تایید و پرداخت»</b>\n"
            . "<b>ماجرا چیه؟</b> تا حالا مشتری برای خرید باید اول کیف پولش رو شارژ می‌کرد و بعد خرید می‌کرد. حالا می‌تونی یه دکمهٔ «💳 پرداخت مستقیم» بذاری: مشتری همون لحظه از یکی از درگاه‌ها (مثلاً کارت‌به‌کارت یا زرین‌پال) پول سرویس رو می‌ده، و بعد از پرداخت سرویس خودکار ساخته (یا تمدید) می‌شه. اصلاً به کیف پولش دست نمی‌زنیم؛ هر چقدر داشته همون می‌مونه.\n"
            . "<b>توی صفحهٔ تایید چی می‌بینه؟</b> دکمهٔ «تایید و پرداخت» اسمش می‌شه «✅ تایید و پرداخت از کیف پول» و کنارش «💳 پرداخت مستقیم» میاد؛ «🔙 بازگشت» می‌ره زیرشون. این برای سه جا کار می‌کنه: خرید اشتراک، 🧩 حجم دلخواه و تمدید.\n"
            . "<b>چطور روشنش کنم؟</b> این قابلیت <b>خاموشه</b> تا خودت روشنش کنی:\n"
            . "1️⃣ 🏬 تنظیمات فروشگاه ← 🛒 وضعیت قابلیت‌های فروشگاه\n"
            . "2️⃣ بالا زبان رو انتخاب کن (هر زبان جدا تنظیم می‌شه) و «💳 دکمهٔ پرداخت مستقیم در صفحهٔ تایید» رو بزن تا ✅ بشه\n"
            . "3️⃣ دکمهٔ «⚙️ تنظیمات پرداخت مستقیم» که زیرش میاد رو باز کن و مسیرها رو انتخاب کن: فقط خرید اشتراک؟ فقط تمدید؟ هر سه؟ هر کدوم ✅ باشه، دکمه همونجا میاد\n"
            . "<b>حداقل و حداکثر شارژ</b> (🔎 توی همون صفحه): هر درگاه یه حداقل و حداکثر برای «افزایش موجودی» داره. پیش‌فرض این گزینه <b>خاموشه</b> یعنی پرداخت مستقیم به اون محدودیت‌ها کاری نداره و مشتری قیمت سرویس رو هر چقدر هست می‌پردازه. اگه روشنش کنی همون حداقل و حداکثر روی پرداخت مستقیم هم اعمال می‌شه.\n"
            . "<b>ظاهر و متن‌ها:</b> اسم، رنگ، ایموجی و جای دکمه‌ها (و دکمهٔ تایید) از 🎨 ← «دکمه‌های تأیید خرید» و «دکمه‌های فاکتور تمدید» عوض می‌شه، و متنی که بالای لیست درگاه‌ها میاد از 🎨 ← «پیام‌ها و ظاهر مراحل خرید» ← «💳 متن مرحلهٔ پرداخت مستقیم» (برای هر زبان جدا).\n"
            . "🌐 پنل وب: «قابلیت‌ها» ← کارت «پرداخت مستقیم» (روشن/خاموش، مسیرها و حداقل و حداکثر)؛ ظاهر دکمه‌ها از «دکمه‌ها» و متن از «متن‌های ربات»\n"
            . "💡 فرقش با «افزایش موجودی»: افزایش موجودی پول رو به کیف پول اضافه می‌کنه و بعد خرید از کیف پول انجام می‌شه؛ پرداخت مستقیم پول رو همون لحظه برای همون سرویس می‌گیره و کیف پول جدا می‌مونه.</blockquote>",
        'en' => "<blockquote>💳 <b>Pay directly: a new button beside «Confirm and pay»</b>\n"
            . "<b>What is it?</b> Until now a customer had to top up the wallet first and then buy. Now you can add a «💳 Pay directly» button: the customer pays for the service from a gateway right away (card to card, Zarinpal, ...), and once it is paid the service is made (or renewed) by itself. The wallet is not touched at all; whatever they had stays.\n"
            . "<b>What do they see on the confirm page?</b> «Confirm and pay» becomes «✅ Confirm and pay from wallet», «💳 Pay directly» comes beside it, and «🔙 Back» goes under them. It works in three places: buying a subscription, 🧩 custom plans and renewals.\n"
            . "<b>How do I turn it on?</b> It is <b>off</b> until you turn it on:\n"
            . "1️⃣ 🏬 Shop settings → 🛒 Shop feature status\n"
            . "2️⃣ Pick the language at the top (each language is set apart) and tap «💳 «Pay directly» button on the confirm page» until it is ✅\n"
            . "3️⃣ Open «⚙️ Pay directly settings» that appears under it and choose the paths: only buying? only renewals? all three? Each one that is ✅ gets the button\n"
            . "<b>Minimum and maximum top-up</b> (🔎 on the same screen): every gateway has a minimum and a maximum for «Increase balance». This option is <b>off</b> by default, so paying directly ignores those limits and the customer pays the service's price, whatever it is. Turn it on and each gateway's minimum and maximum apply to paying directly too.\n"
            . "<b>Look and texts:</b> the names, colours, emoji and place of the buttons (and the confirm button) are changed in 🎨 → «Confirm buttons» and «Renewal invoice buttons», and the text above the gateway list in 🎨 → «Messages and look of the purchase steps» → «💳 Pay directly step text» (each language apart).\n"
            . "🌐 Web panel: «Features» → the «Pay directly» card (on/off, paths, minimum and maximum); the buttons' look in «Buttons» and the text in «Bot texts»\n"
            . "💡 How it differs from «Increase balance»: increasing the balance adds money to the wallet and the purchase is then paid from it; paying directly takes the money for that very service and the wallet stays apart.</blockquote>",
    ],
    [
        'n' => 75,
        'date' => '2026-11-01',
        'fa' => "<blockquote>🎁 <b>هدیهٔ شارژ روی «پرداخت مستقیم»</b>\n"
            . "<b>ماجرا چیه؟</b> «هدیهٔ شارژ» همون تخفیف/هدیه‌ایه که برای هر درگاه می‌ذاری (مثلاً ۱۰٪ بیشتر وقتی کیف پول شارژ می‌کنن، خودکار یا با کد). تا حالا فقط وقتی مشتری کیف پولش رو شارژ می‌کرد کار می‌کرد. حالا می‌تونی بذاری وقتی با «💳 پرداخت مستقیم» سرویس می‌خره هم هدیه بگیره.\n"
            . "<b>مثال:</b> برای کارت‌به‌کارت ۱۰٪ هدیه گذاشتی و مشتری با پرداخت مستقیم یه سرویس ۱۵۰ هزار تومانی می‌خره. سرویس ساخته می‌شه و ۱۵ هزار تومان هدیه هم به کیف پولش اضافه می‌شه (و بهش پیام «تخفیف اعمال شد» می‌رسه).\n"
            . "<b>پیش‌فرض خاموشه:</b> یعنی پرداخت مستقیم هدیه‌ای نمی‌ده و روی فاکتورش هم حرفی از هدیه نیست. وقتی روشنش کنی دقیقاً همون قانون‌های هدیهٔ شارژ اجرا می‌شه: هر درگاه هدیهٔ خودش رو داره، حداقل مبلغ، تعداد دفعات برای هر کاربر، تاریخ انقضا، بسته‌های آماده و کدی که مشتری از قبل فعال کرده. هدیه بالای لیست درگاه‌ها و روی فاکتور نشون داده می‌شه و بعد از پرداخت به کیف پول اضافه می‌شه.\n"
            . "<b>چطور روشنش کنم؟</b>\n"
            . "1️⃣ 🏬 تنظیمات فروشگاه ← 🛒 وضعیت قابلیت‌های فروشگاه (زبان رو بالا انتخاب کن)\n"
            . "2️⃣ «💳 دکمهٔ پرداخت مستقیم» باید ✅ باشه ← «⚙️ تنظیمات پرداخت مستقیم»\n"
            . "3️⃣ «🎁 هدیهٔ شارژ روی پرداخت مستقیم» رو بزن تا ✅ بشه\n"
            . "ℹ️ خود هدیه‌ها (درصد، حداقل مبلغ، کد) مثل قبل از بخش هدیهٔ شارژ هر درگاه تنظیم می‌شن. «افزایش موجودی به اندازهٔ کمبود» (وقتی موجودی کمه) از قبل هم هدیه می‌گرفت و تغییری نکرده.\n"
            . "🌐 پنل وب: «قابلیت‌ها» ← کارت «پرداخت مستقیم» ← «هدیهٔ شارژ روی پرداخت مستقیم»</blockquote>",
        'en' => "<blockquote>🎁 <b>Top-up gift on «Pay directly»</b>\n"
            . "<b>What is it?</b> The «top-up gift» is the gift you set for each gateway (say 10% extra when the wallet is topped up, automatic or with a code). Until now it only worked when a customer topped up the wallet. Now you can have it work when they buy with «💳 Pay directly» too.\n"
            . "<b>Example:</b> you set a 10% gift on card to card and a customer pays directly for a 150,000 service. The service is made and 15,000 of gift is added to their wallet as well (with the «Discount applied» message).\n"
            . "<b>It is off by default:</b> paying directly earns no gift and its invoice says nothing about one. Turn it on and exactly the top-up gift rules run: each gateway has its own gift, the minimum amount, the uses per customer, the expiry, ready amounts and a code the customer activated earlier. The gift is shown above the gateway list and on the invoice and is added to the wallet after the payment.\n"
            . "<b>How do I turn it on?</b>\n"
            . "1️⃣ 🏬 Shop settings → 🛒 Shop feature status (pick the language at the top)\n"
            . "2️⃣ «💳 Pay directly button» must be ✅ → «⚙️ Pay directly settings»\n"
            . "3️⃣ Tap «🎁 Top-up gift on paying directly» until it is ✅\n"
            . "ℹ️ The gifts themselves (percent, minimum amount, codes) are set as before in each gateway's top-up gift. «Top up exactly what is lacking» (when the balance is short) already earned the gift and is unchanged.\n"
            . "🌐 Web panel: «Features» → the «Pay directly» card → «Top-up gift on paying directly»</blockquote>",
    ],
    [
        'n' => 76,
        'date' => '2026-11-07',
        'fa' => "<blockquote>🛠 <b>رفع دو مشکل در خرید سرویس</b>\n"
            . "<b>۱. سرویس روی پنل ساخته می‌شد ولی توی ربات نبود (یا unpaid می‌موند)</b>\n"
            . "وقتی مشتری با کارت‌به‌کارت، درگاه یا «پرداخت مستقیم» سرویس می‌خرید، ربات اول سرویس رو روی پنل می‌ساخت، پیام‌ها و گزارش‌ها رو می‌فرستاد، پول رو از کیف پول کم می‌کرد و تازه آخر کار فاکتور رو «فعال» می‌کرد. اگه وسط این کارها چیزی گیر می‌کرد، سرویس روی پنل بود، پول کم شده بود ولی فاکتور «unpaid» می‌موند و در «🛍 سرویس‌های من» دیده نمی‌شد.\n"
            . "الان همون لحظه‌ای که سرویس روی پنل ساخته می‌شه، فاکتور فعال می‌شه و حساب کیف پول و پرداخت بسته می‌شه؛ بعد پیام‌ها فرستاده می‌شن. اگه پنل خطا بده، فاکتور مثل قبل منتظر می‌مونه و مبلغ به کیف پول برمی‌گرده.\n"
            . "<b>چیزهای دیگه‌ای که درست شد:</b>\n"
            . "• تایید دوبارهٔ همون رسید (دو بار زدن ✅، دو ادمین با هم، یا تایید خودکار بعد از تایید دستی) دیگه سرویس رو دو بار نمی‌سازه و پول رو دو بار برنمی‌گردونه.\n"
            . "• اگه برای خریدی که قبلاً تحویل شده یه پرداخت دیگه برسه، سرویس دوباره ساخته نمی‌شه و مبلغ به کیف پول مشتری اضافه می‌شه (با پیام؛ متنش در 🎨 ← 💰 پیام‌ها و ظاهر افزایش موجودی).\n"
            . "• دو بار زدن پشت‌سرهم «✅ تایید و پرداخت از کیف پول» فقط یه سرویس می‌خره.\n"
            . "• خریدی که مشتری نیمه‌کاره ول کرده بود (مثلاً رفت پرداخت کنه و برگشت) دیگه همون نام کاربری رو قفل نمی‌کنه و پیام «مراحل رو از اول شروع کن» تکرار نمی‌شه.\n"
            . "<b>۲. حداقل و حداکثر «🏦 بسته‌های شارژ» روی «پرداخت مستقیم» اعمال نمی‌شد</b>\n"
            . "با کلید «🔎 رعایت حداقل و حداکثر شارژ درگاه» روشن، ربات به‌جای بازه‌ای که در 🏦 بسته‌های شارژ ← حداقل و حداکثر مبلغ دلخواه گذاشتی، فقط عدد 💎 مالی رو نگاه می‌کرد. الان همون بازهٔ بسته‌های شارژ اعمال می‌شه (اگه اونجا خالی باشه، عدد 💎 مالی). «افزایش موجودی به اندازهٔ کمبود» هم از همین بازه پیروی می‌کنه.\n"
            . "⚠️ این کلید پیش‌فرض خاموشه؛ وقتی خاموشه حداقل و حداکثر روی پرداخت مستقیم اعمال نمی‌شه. مسیر: 🏬 تنظیمات فروشگاه ← 🛒 وضعیت قابلیت‌های فروشگاه ← ⚙️ تنظیمات پرداخت مستقیم ← «🔎 رعایت حداقل و حداکثر شارژ درگاه» (پنل وب: «قابلیت‌ها» ← کارت «پرداخت مستقیم»).</blockquote>",
        'en' => "<blockquote>🛠 <b>Two purchase problems fixed</b>\n"
            . "<b>1. A service made on the panel but missing in the bot (or left «unpaid»)</b>\n"
            . "When a customer bought by card to card, a gateway or «Pay directly», the bot made the service on the panel, sent the messages and reports, took the money from the wallet, and only at the very end marked the invoice active. If anything stuck on the way, the service was on the panel and the money taken, but the invoice stayed «unpaid» and did not show in «🛍 My services».\n"
            . "Now the invoice turns active and the wallet and the payment are settled the moment the service exists on the panel; the messages come after. If the panel refuses, the invoice waits as before and the amount goes back to the wallet.\n"
            . "<b>Also fixed:</b>\n"
            . "• Approving the same receipt again (✅ twice, two admins at once, or the auto-approve after a manual one) no longer builds the service twice or refunds twice.\n"
            . "• A second payment for a purchase already delivered builds nothing again; the amount goes to the customer's wallet (with a message, editable in 🎨 → 💰 top-up messages and looks).\n"
            . "• Tapping «✅ Confirm and pay from wallet» twice quickly buys one service.\n"
            . "• A purchase the customer left halfway (went to pay and came back) no longer locks that username, so «start over» is not repeated.\n"
            . "<b>2. The «🏦 Top-up packages» min and max did not apply to «Pay directly»</b>\n"
            . "With «🔎 Keep the gateway's top-up min and max» on, the bot looked only at the 💎 Finance number instead of the range set in 🏦 Top-up packages → custom amount min and max. Now that range applies (the 💎 Finance number when it is empty). «Top up exactly what is lacking» follows the same range.\n"
            . "⚠️ The switch is off by default, and then no min or max applies to paying directly. Path: 🏬 Shop settings → 🛒 Shop feature status → ⚙️ Pay directly settings → «🔎 Keep the gateway's top-up min and max» (web panel: «Features» → the «Pay directly» card).</blockquote>",
    ],
    [
        'n' => 77,
        'date' => '2026-11-07',
        'fa' => "<blockquote>🔍 <b>دیباگ کامل ربات</b>\n"
            . "کل ربات یک دور کامل بررسی شد: کد همهٔ فایل‌ها، همهٔ کرون‌ها، همهٔ صفحه‌های پنل وب (فارسی و انگلیسی) و حدود ۲۷۰۰ ضربه روی منوهای ادمین و مشتری. این‌ها درست شد:\n"
            . "• <b>آموزش‌ها:</b> متن آموزشی که عکس یا ویدیو نداشت، ایموجی پریمیوم و فرمت‌هاش رو از دست می‌داد. الان همون‌طور که ذخیره کردی فرستاده می‌شه.\n"
            . "• <b>دکمه‌های قدیمی خرید:</b> اگه مشتری روی دکمهٔ پلن، دسته‌بندی یا مدت زمان یه پیام قدیمی می‌زد (بعد از اینکه خرید رو ول کرده بود)، به یه فاکتور خالی و خراب می‌رسید. الان پیام «مراحل خرید را مجددا انجام دهید» می‌گیره و از اول شروع می‌کنه.\n"
            . "• <b>لینک اشتراک:</b> موقع گرفتن لینک اشتراک، ربات بی‌دلیل یه ورود اشتباه به پنل می‌زد؛ حذف شد و سریع‌تر شد.\n"
            . "• <b>کرون on_hold:</b> وقتی یه پنل مرزبان جواب نمی‌داد، هر بار خطا توی لاگ می‌نوشت. الان اون پنل رد می‌شه و بقیه بررسی می‌شن.\n"
            . "• <b>اطلاعات سرویس:</b> بعضی نسخه‌های پنل چند فیلد (آخرین اتصال، آخرین به‌روزرسانی ساب) رو نمی‌فرستن؛ دیگه هشدار نمی‌ده و صفحهٔ سرویس درست نشون داده می‌شه.\n"
            . "ℹ️ نیازی به تنظیم نیست؛ همه‌چیز خودکار اعمال می‌شه.</blockquote>",
        'en' => "<blockquote>🔍 <b>A full debugging pass</b>\n"
            . "The whole bot was checked once over: the code of every file, every cron, every web panel page (Persian and English) and about 2,700 taps through the admin and customer menus. Fixed:\n"
            . "• <b>Tutorials:</b> a tutorial text without a photo or video lost its premium emoji and formatting. It is now sent exactly as saved.\n"
            . "• <b>Old purchase buttons:</b> a plan, category or duration button on an old message (after the customer left the purchase) led to an empty, broken invoice. Now the customer is told to start the purchase again.\n"
            . "• <b>Subscription link:</b> getting the subscription link made a needless, wrong login to the panel; it is gone, and the link comes faster.\n"
            . "• <b>on_hold cron:</b> a Marzban panel that did not answer wrote an error to the log on every run. That panel is now skipped and the others are checked.\n"
            . "• <b>Service details:</b> some panel versions leave out a few fields (last online, last subscription update); no more warnings, and the service page shows correctly.\n"
            . "ℹ️ Nothing to set up; it all applies by itself.</blockquote>",
    ],
    [
        'n' => 78,
        'date' => '2026-11-07',
        'fa' => "<blockquote>⚡ <b>درگاه جدید: ترونادو (ارزی ریالی)</b>\n"
            . "یه درگاه پشتیبان دیگه کنار FrenzyEx: مشتری فاکتور رو به تومان می‌سازه، توی خودِ تلگرام از صفحه‌ی ترونادو پرداخت می‌کنه و تو ترون (TRX) می‌گیری. کیف پول مشتری بعد از تایید ترونادو خودکار شارژ می‌شه.\n"
            . "مثال: مشتری ۲۰۰٬۰۰۰ تومان شارژ می‌خواد، ربات به قیمت خودِ ترونادو حساب می‌کنه چند ترون می‌شه و لینک پرداخت می‌ده.\n"
            . "🔴 پیش‌فرض: <b>خاموش</b>\n"
            . "📍 راه‌اندازی:\n"
            . "1️⃣ کلید API و کلید امضای IPN رو از پشتیبانی ترونادو (@TronadoSupp) بگیر و دامنه‌ی رباتت رو پیششون ثبت کن.\n"
            . "2️⃣ 🏬 تنظیمات فروشگاه ← 💎 مالی ← 💱 درگاه‌های ارزی ریالی (📂 باز کردن) ← «تنظیمات» ردیف ترونادو\n"
            . "3️⃣ کلید API، آدرس ولت TRX و کلید امضای IPN رو بزن (آدرس کال‌بک همون‌جا با دکمه‌ی کپی هست).\n"
            . "4️⃣ برگرد و ❌ ردیف ترونادو رو بزن تا ✅ بشه.\n"
            . "🌐 پنل وب: «درگاه‌ها» ← کارت ترونادو.</blockquote>",
        'en' => "<blockquote>⚡ <b>New gateway: Tronado (rial-to-crypto)</b>\n"
            . "Another backup gateway next to FrenzyEx: the customer makes a toman invoice, pays on Tronado's page right inside Telegram, and you receive TRX. The customer's wallet is topped up automatically once Tronado confirms.\n"
            . "Example: a customer wants a 200,000 toman top-up; the bot works out how much TRX that is at Tronado's own price and gives a payment link.\n"
            . "🔴 Default: <b>off</b>\n"
            . "📍 Setting it up:\n"
            . "1️⃣ Get an API key and an IPN signing key from Tronado's support (@TronadoSupp) and have them register your bot's domain.\n"
            . "2️⃣ 🏬 Store settings → 💎 Financial → 💱 Rial-to-crypto gateways (📂 open) → «Settings» on the Tronado row\n"
            . "3️⃣ Enter the API key, the TRX wallet address and the IPN signing key (the callback address is there with a copy button).\n"
            . "4️⃣ Go back and tap the ❌ on the Tronado row so it turns ✅.\n"
            . "🌐 Web panel: «Gateways» → the Tronado card.</blockquote>",
    ],
    [
        'n' => 79,
        'date' => '2026-11-07',
        'fa' => "<blockquote>💱 <b>درگاه FrenzyEx طبق راهنمای جدیدش (نسخه ۴)</b>\n"
            . "📍 🏬 تنظیمات فروشگاه ← 💎 مالی ← 💱 درگاه‌های ارزی ریالی ← 📂 باز کردن ← FrenzyEx ← تنظیمات\n"
            . "• <b>آدرس کال‌بک خودکار:</b> ربات آدرس کال‌بک رو همراه هر فاکتور به FrenzyEx می‌فرسته، پس دیگه لازم نیست اول از پشتیبانی FrenzyEx بخوای ثبتش کنه. اگه FrenzyEx قبولش نکنه، فاکتور بدون اون ساخته می‌شه و ربات توی گروه گزارش‌ها خبرت می‌کنه که آدرس رو دستی ثبت کنی.\n"
            . "• <b>جواب فوری به FrenzyEx:</b> FrenzyEx فقط ۵ ثانیه منتظر جواب می‌مونه. توی خرید مستقیم، ربات اول سرویس رو روی پنل می‌ساخت و بعد جواب می‌داد، و اگه طول می‌کشید توی پنل FrenzyEx «تحویل ناموفق» ثبت می‌شد. الان ربات همون لحظه جواب می‌ده و سرویس رو بعدش می‌سازه؛ هیچ پرداختی دوبار شارژ نمی‌شه.\n"
            . "ℹ️ چیزی برای تنظیم نیست؛ فقط کلید API و رمز کال‌بک FrenzyEx باید وارد شده باشن.</blockquote>",
        'en' => "<blockquote>💱 <b>The FrenzyEx gateway follows its new guide (version 4)</b>\n"
            . "📍 🏬 Store settings → 💎 Financial → 💱 Rial-to-crypto gateways → 📂 Open → FrenzyEx → Settings\n"
            . "• <b>Callback address sent by itself:</b> the bot sends its callback address with every invoice, so FrenzyEx support no longer has to register it first. If FrenzyEx does not accept it, the invoice is made without it and the bot tells you in the report group to register the address by hand.\n"
            . "• <b>FrenzyEx answered at once:</b> FrenzyEx waits only 5 seconds for an answer. On a direct purchase the bot used to build the service on the panel first and answer afterwards, and when that took long FrenzyEx logged a failed delivery. Now the bot answers right away and builds the service after; no payment is ever credited twice.\n"
            . "ℹ️ Nothing to set up; only the FrenzyEx API key and callback secret have to be entered.</blockquote>",
    ],
    [
        'n' => 80,
        'date' => '2026-11-07',
        'fa' => "<blockquote>🗑 <b>ترونادوی قدیمی حذف شد</b>\n"
            . "درگاه «درگاه پرداخت ریالی سوم» (ترونادوی قدیمی ربات توی 🏧 درگاه‌های ریالی) کامل از ربات و پنل وب برداشته شد، همراه با تنظیماتش. از این به بعد برای ترونادو فقط درگاه جدید رو داری.\n"
            . "📍 🏬 تنظیمات فروشگاه ← 💎 مالی ← 💱 درگاه‌های ارزی ریالی ← ترونادو\n"
            . "ℹ️ پرداخت‌های قبلی که با اون درگاه انجام شده بود توی گزارش‌ها می‌مونن.</blockquote>",
        'en' => "<blockquote>🗑 <b>The old Tronado gateway is gone</b>\n"
            . "«Third rial payment gateway» (the bot's old Tronado one, under 🏧 Rial gateways) has been removed from the bot and the web panel, settings included. For Tronado there is now only the new gateway.\n"
            . "📍 🏬 Store settings → 💎 Financial → 💱 Rial-to-crypto gateways → Tronado\n"
            . "ℹ️ Payments made through it before stay in the reports.</blockquote>",
    ],
    [
        'n' => 81,
        'date' => '2026-11-08',
        'fa' => "<blockquote>🛡 <b>پنل پاسارگارد طبق آخرین نسخه‌اش (v5.6.0)</b>\n"
            . "📍 برای همهٔ لوکیشن‌هایی که نوع پنلشون «پاسارگارد» هست؛ چیزی برای تنظیم نیست.\n"
            . "• <b>بدون هشدار توی لاگ پنل:</b> ربات برای ساخت، تمدید، ویرایش، ریست، تغییر لینک و حذف سرویس از مسیرهای قدیمی «با نام کاربری» استفاده می‌کرد. پاسارگارد این مسیرها رو منسوخ کرده، با هر بار استفاده یه هشدار توی لاگش می‌نویسه و از نسخهٔ ۶ کلاً حذفشون می‌کنه. الان ربات با شناسهٔ کاربر کار می‌کنه.\n"
            . "• <b>پیام «ورود ادمین» کمتر:</b> ربات هر ساعت یه بار دوباره وارد پنل می‌شد و هر ورود یه گزارش ورود توی پنل ثبت می‌کرد. الان تا وقتی ورود قبلی معتبره (پیش‌فرض ۲۴ ساعت) دوباره وارد نمی‌شه.\n"
            . "• <b>امنیت پروتکل‌ها:</b> «⚙️ تنظیم پروتکل و اینباند» از روی کاربر نمونه، رمز Hysteria و Masque اون کاربر رو هم برمی‌داشت و به همهٔ مشتری‌ها می‌داد. الان فقط تنظیمات (مثل flow) برداشته می‌شه و پنل برای هر مشتری رمز جدا می‌سازه.\n"
            . "• <b>«آخرین آپدیت لینک» درست شد:</b> خواندن کانفیگ‌ها توسط خود ربات دیگه توی پنل به‌عنوان آپدیت لینک مشتری ثبت نمی‌شه و از سهمیهٔ دستگاه (HWID) مشتری هم کم نمی‌کنه.\n"
            . "• <b>رفع خطاها:</b> حذف سرویس که با موفقیت انجام می‌شد ولی ربات «ناموفق» نشون می‌داد، گزارش مصرف به تفکیک نود که خالی می‌موند، سرویس با زمان نامحدود، و «تنظیم اینباند محصول» که کاربر نمونهٔ پاسارگارد رو «پیدا نشد» جواب می‌داد.</blockquote>",
        'en' => "<blockquote>🛡 <b>PasarGuard panels, following its latest version (v5.6.0)</b>\n"
            . "📍 Every location whose panel type is PasarGuard; nothing to set up.\n"
            . "• <b>No more warnings in the panel's log:</b> to create, renew, edit, reset, re-link and delete services the bot used the old by-username routes. PasarGuard has deprecated them, writes a warning to its log on every use and removes them in version 6. The bot now works with the user's id.\n"
            . "• <b>Fewer \"admin login\" notices:</b> the bot logged in to the panel again every hour, and every login was recorded by the panel. It now logs in again only when the previous login runs out (24 hours by default).\n"
            . "• <b>Protocol security:</b> \"⚙️ Set protocol and inbound\" from a sample user also copied that user's Hysteria and Masque password to every customer. Now only the settings (such as flow) are taken, and the panel makes a separate password for each customer.\n"
            . "• <b>\"Last link update\" is right again:</b> the bot reading the configs no longer counts on the panel as the customer's own link update, and no longer uses up one of the customer's devices (HWID).\n"
            . "• <b>Fixes:</b> a service delete that succeeded but showed as failed, the per-node usage report that stayed empty, services with unlimited time, and \"set product inbound\" answering \"not found\" for a PasarGuard sample user.</blockquote>",
    ],
    [
        'n' => 82,
        'date' => '2026-11-08',
        'fa' => "<blockquote>🧠 <b>درگاه جدید: هوش‌پی (ارزی ریالی)</b>\n"
            . "یه درگاه پشتیبان دیگه کنار FrenzyEx و ترونادو: مشتری کارت‌به‌کارت پرداخت می‌کنه، هوش‌پی با پیامک بانک همون لحظه تاییدش می‌کنه و کیف پول مشتری خودکار شارژ می‌شه. تسویه‌ی شما با تتره.\n"
            . "مثال: مشتری ۱۰۰٬۰۰۰ تومان شارژ می‌خواد، ربات یه مبلغ دقیق مثل ۱۲۰٬۰۱۷ تومان (با کارمزد) بهش می‌ده که باید دقیقاً همون رو واریز کنه.\n"
            . "🔴 پیش‌فرض: <b>خاموش</b>\n"
            . "📍 راه‌اندازی:\n"
            . "1️⃣ توی اپلیکیشن هوش‌پی، بخش «توسعه»، کلید API و کلید Secret رو بردار.\n"
            . "2️⃣ 🏬 تنظیمات فروشگاه ← 💎 مالی ← 💱 درگاه‌های ارزی ریالی (📂 باز کردن) ← «تنظیمات» ردیف هوش‌پی\n"
            . "3️⃣ کلید API و کلید Secret رو بزن. اگه خواستی بگو کارمزد با کی باشه (buyer / seller / split).\n"
            . "4️⃣ برگرد و ❌ ردیف هوش‌پی رو بزن تا ✅ بشه.\n"
            . "🌐 پنل وب: «درگاه‌ها» ← کارت هوش‌پی.</blockquote>",
        'en' => "<blockquote>🧠 <b>New gateway: HooshPay (rial-to-crypto)</b>\n"
            . "Another backup gateway next to FrenzyEx and Tronado: the customer pays card to card, HooshPay confirms it from the bank's SMS on the spot, and the customer's wallet is topped up automatically. You are settled in USDT.\n"
            . "Example: a customer wants a 100,000 toman top-up; the bot gives an exact figure such as 120,017 toman (fee included) to transfer.\n"
            . "🔴 Default: <b>off</b>\n"
            . "📍 Setting it up:\n"
            . "1️⃣ In the HooshPay app, under «Development», take the API key and the Secret.\n"
            . "2️⃣ 🏬 Store settings → 💎 Financial → 💱 Rial-to-crypto gateways (📂 open) → «Settings» on the HooshPay row\n"
            . "3️⃣ Enter the API key and the Secret, and if you like, who carries the fee (buyer / seller / split).\n"
            . "4️⃣ Go back and tap the ❌ on the HooshPay row so it turns ✅.\n"
            . "🌐 Web panel: «Gateways» → the HooshPay card.</blockquote>",
    ],
    [
        'n' => 83,
        'date' => '2026-11-08',
        'fa' => "<blockquote>🛡 <b>کارت‌به‌کارت فقط برای مشتری‌های قدیمی</b>\n"
            . "اگه روشنش کنی، فقط مشتری‌ای که بیشتر از ۳ خرید یا تمدید پرداخت‌شده داشته دکمه‌ی کارت‌به‌کارت رو می‌بینه؛ بقیه نمی‌بینن. به محض اینکه مشتری به این تعداد برسه، کارت‌به‌کارت خودکار براش روشن می‌شه. عدد ۳ قابل تغییره.\n"
            . "مثال: مشتری‌ای که ۲ تا سرویس خریده و ۱ بار تمدید کرده هنوز کارت‌به‌کارت نمی‌بینه؛ با خرید یا تمدید بعدی می‌بینه.\n"
            . "🔴 پیش‌فرض: <b>خاموش</b>\n"
            . "📍 1️⃣ 🏬 تنظیمات فروشگاه ← 💎 مالی ← «⚙️ تنظیمات» ردیف کارت به کارت\n"
            . "2️⃣ 🔧 تنظیمات فنی درگاه (این زبان) ← «🛡 فقط برای مشتری‌های قدیمی» رو بزن تا ✅ بشه\n"
            . "3️⃣ برای عوض کردن عدد: «✏️ تغییر تعداد خرید/تمدید لازم»\n"
            . "🌐 پنل وب: «درگاه‌ها» ← کارت «کارت به کارت».</blockquote>\n\n"
            . "<blockquote>⚡ <b>حداقل مبلغ ترونادو: ۱۵۰ هزار تومان</b>\n"
            . "ترونادو کمتر از ۱۵۰٬۰۰۰ تومان قبول نمی‌کنه، پس ربات هم برای مبلغ‌های کمتر ترونادو رو نشون نمی‌ده (تمدید، پرداخت مستقیم، کسری موجودی) و توی شارژ دلخواه حداقل رو ۱۵۰٬۰۰۰ می‌گه.\n"
            . "📍 اگه ترونادو عددش رو عوض کرد: 💎 مالی ← 💱 درگاه‌های ارزی ریالی ← «تنظیمات» ترونادو ← «⬇️ کمترین مبلغی که ترونادو قبول می‌کنه».</blockquote>",
        'en' => "<blockquote>🛡 <b>Card to card only for returning customers</b>\n"
            . "Turned on, only a customer with more than 3 paid purchases or renewals sees the card-to-card button; the others do not. The moment a customer gets there, card to card is switched on for them. The 3 can be changed.\n"
            . "Example: a customer who bought 2 services and renewed once does not see card to card yet; after the next purchase or renewal they do.\n"
            . "🔴 Default: <b>off</b>\n"
            . "📍 1️⃣ 🏬 Store settings → 💎 Financial → «⚙️ Settings» on the card-to-card row\n"
            . "2️⃣ 🔧 Gateway technical settings (this language) → tap «🛡 فقط برای مشتری‌های قدیمی» so it turns ✅\n"
            . "3️⃣ To change the number: «✏️ تغییر تعداد خرید/تمدید لازم»\n"
            . "🌐 Web panel: «Gateways» → the card-to-card card.</blockquote>\n\n"
            . "<blockquote>⚡ <b>Tronado minimum: 150,000 toman</b>\n"
            . "Tronado takes nothing under 150,000 toman, so the bot no longer offers Tronado for smaller amounts (renewals, direct payments, what a purchase lacks) and quotes 150,000 as the minimum of a typed top-up.\n"
            . "📍 If Tronado changes it: 💎 Financial → 💱 Rial-to-crypto gateways → Tronado «Settings» → «⬇️ کمترین مبلغی که ترونادو قبول می‌کنه».</blockquote>",
    ],
    [
        'n' => 84,
        'date' => '2026-11-08',
        'fa' => "<blockquote>🛠 <b>رفع خطای FrenzyEx: «Call to undefined function jdate()»</b>\n"
            . "<b>مشکل چی بود؟</b> وقتی مشتری با درگاه FrenzyEx سرویس می‌خرید یا تمدید می‌کرد، پرداخت تایید می‌شد ولی ربات وسط کار با این خطا متوقف می‌شد و پیام «❌ FrenzyEx: پرداخت تایید شد ولی شارژ با خطا روبه‌رو شد» توی گروه گزارش می‌اومد. علتش این بود که فایل تاریخ شمسی برای این درگاه بارگذاری نمی‌شد و ربات موقع نوشتن تاریخ گزارش خرید از کار می‌افتاد.\n"
            . "<b>نتیجه‌ش چی بود؟</b> سرویس ساخته می‌شد ولی گزارش خرید برای ادمین نمی‌اومد، هدیهٔ شارژ و کش‌بک داده نمی‌شد، و در تمدید، سرویس توی ربات ممکن بود هنوز «منقضی» نشون داده بشه.\n"
            . "<b>الان:</b> تاریخ شمسی همه‌جا خودکار بارگذاری می‌شه؛ برای FrenzyEx و هر درگاه، کرون یا بخش دیگه‌ای که همین مشکل رو می‌تونست داشته باشه. خرید و تمدید با FrenzyEx تا آخر انجام می‌شه.\n"
            . "ℹ️ نیازی به تنظیم نیست. برای سفارش‌هایی که قبلاً این خطا رو دادن، سرویس و کیف پول اون مشتری رو یک بار چک کن.</blockquote>",
        'en' => "<blockquote>🛠 <b>FrenzyEx error fixed: «Call to undefined function jdate()»</b>\n"
            . "<b>What was wrong?</b> When a customer bought or renewed a service through FrenzyEx, the payment was confirmed but the bot stopped half way with this error, and «❌ FrenzyEx: payment confirmed but crediting failed» arrived in the report group. The Persian-calendar file was not loaded for this gateway, so the bot failed while writing the date into the purchase report.\n"
            . "<b>What it caused:</b> the service was made, but the admin got no purchase report, the top-up gift and cashback were not given, and after a renewal the service could still show as expired in the bot.\n"
            . "<b>Now:</b> the Persian calendar is loaded everywhere by itself - for FrenzyEx and for any gateway, cron or other part that could have hit the same problem. Purchases and renewals through FrenzyEx finish completely.\n"
            . "ℹ️ Nothing to set up. For orders that already hit this error, check that customer's service and wallet once.</blockquote>",
    ],
    [
        'n' => 85,
        'date' => '2026-11-08',
        'fa' => "<blockquote>📨 <b>پیام «✅ ربات آپدیت شد» دوباره میاد</b>\n"
            . "بعد از آپدیت قبلی، فقط پیام نوار پیشرفت (۱۰۰٪) می‌اومد و پیام جدای «✅ ربات آپدیت شد» با لیست «تازه‌ها» نمی‌اومد. علتش این بود که اسکریپت سرور فایل تاریخ شمسی رو دوباره بارگذاری می‌کرد و همین باعث خطا می‌شد. حالا اون فایل هر چند بار هم بارگذاری بشه مشکلی پیش نمیاد.\n"
            . "ℹ️ نیازی به کاری نیست. همین آپدیت، یادداشت همهٔ آپدیت‌هایی رو که ندیده بودی یک‌جا برات می‌فرسته.</blockquote>",
        'en' => "<blockquote>📨 <b>The «✅ Bot updated» message is back</b>\n"
            . "After the previous update only the progress bar (100%) appeared, without the separate «✅ Bot updated» message and its «what's new» list. The server script loaded the Persian-calendar file a second time and that stopped it. The file can now be loaded any number of times.\n"
            . "ℹ️ Nothing to do. This update sends you the notes of every update you missed, all at once.</blockquote>",
    ],
    [
        'n' => 86,
        'date' => '2026-11-08',
        'fa' => "<blockquote>📅 <b>کارت‌به‌کارت برای عضوهای قدیمی، بر اساس تاریخ عضویت</b>\n"
            . "کنار شرط «🛡 فقط برای مشتری‌های قدیمی» حالا می‌تونی یه تاریخ میلادی هم بذاری. هر کاربری که تا آخر اون روز عضو ربات شده، کارت‌به‌کارت رو می‌بینه؛ حتی اگه هنوز به تعداد خرید/تمدید لازم نرسیده. کاربرهای بعد از اون روز مثل قبل باید به تعداد خرید/تمدید برسن.\n"
            . "مثال: تاریخ رو <code>2026-11-02</code> بذاری؛ هر کی تا آخر ۲ نوامبر (۱۱ آبان) عضو شده کارت‌به‌کارت داره، و کاربر جدید بعد از ۳ خرید یا تمدید پرداخت‌شده.\n"
            . "دفعه‌ی اولی که این کاربرها دکمه‌های پرداخت رو ببینن، کارت‌به‌کارت خودکار براشون روشن می‌شه. اگه بعدش برای یه نفر خاموشش کنی، خاموش می‌مونه.\n"
            . "🔴 پیش‌فرض: <b>بدون تاریخ</b>. فقط وقتی کار می‌کنه که «🛡 فقط برای مشتری‌های قدیمی» روشن باشه.\n"
            . "📍 1️⃣ 🏬 تنظیمات فروشگاه ← 💎 مالی ← «⚙️ تنظیمات» ردیف کارت به کارت\n"
            . "2️⃣ 🔧 تنظیمات فنی درگاه (این زبان) ← «🛡 فقط برای مشتری‌های قدیمی» باید ✅ باشه\n"
            . "3️⃣ «📅 عضوهای قبل از تاریخ» رو بزن و تاریخ رو بفرست؛ برای برداشتنش «🗑 حذف تاریخ»\n"
            . "🌐 پنل وب: «درگاه‌ها» ← کارت «کارت به کارت» ← «📅 عضوهای قبل از این تاریخ».</blockquote>",
        'en' => "<blockquote>📅 <b>Card to card for long-time members, by join date</b>\n"
            . "Next to «🛡 Only for returning customers» you can now set a Gregorian date. Anyone who joined the bot by the end of that day sees card to card, even before reaching the purchases/renewals needed. Users who joined later still need that number, as before.\n"
            . "Example: set <code>2026-11-02</code>; everyone who joined by the end of 2 November has card to card, and a new user gets it after 3 paid purchases or renewals.\n"
            . "The first time one of these users sees the payment buttons, card to card is switched on for them. If you then turn it off for someone, it stays off.\n"
            . "🔴 Default: <b>no date</b>. It only works while «🛡 Only for returning customers» is on.\n"
            . "📍 1️⃣ 🏬 Store settings → 💎 Financial → «⚙️ Settings» on the card-to-card row\n"
            . "2️⃣ 🔧 Gateway technical settings (this language) → «🛡 فقط برای مشتری‌های قدیمی» must be ✅\n"
            . "3️⃣ Tap «📅 عضوهای قبل از تاریخ» and send the date; to remove it, «🗑 حذف تاریخ»\n"
            . "🌐 Web panel: «Gateways» → the card-to-card card → «📅 Members who joined by this day».</blockquote>",
    ],
    [
        'n' => 87,
        'date' => '2026-11-10',
        'fa' => "<blockquote>🛠 <b>رفع چند خطا</b>\n"
            . "• <b>عکس به پشتیبانی نمی‌رسید:</b> مشتری‌ای که اسکرین‌شات یا ویدیو برای پشتیبانی می‌فرستاد، پیامش هیچ‌وقت به پشتیبان نمی‌رسید (فقط متن قبول می‌شد). حالا عکس، ویدیو، فایل و ویس با کپشنش می‌رسه و پشتیبان هم می‌تونه با عکس جواب بده.\n"
            . "• پیام مشتری که توش «&lt;» بود، به پشتیبان نمی‌رسید؛ درست شد.\n"
            . "• <b>نصب ربات دوم روی همون سرور:</b> اگه روی سرور یه ربات دیگه هم بود، نصب تازه بعضی جدول‌ها (کاربرها، ادمین‌ها، پشتیبانی) رو نمی‌ساخت. درست شد.\n"
            . "• فاکتور Plisio برای مشتری‌های انگلیسی به‌جای شماره و مبلغ، «%s» نشون می‌داد. درست شد.\n"
            . "ℹ️ نیازی به تنظیم نیست.</blockquote>",
        'en' => "<blockquote>🛠 <b>Bug fixes</b>\n"
            . "• <b>Photos never reached support:</b> a customer who sent a screenshot or video to support never got it delivered (only text was taken). Photos, videos, files and voice messages now arrive with their caption, and support can answer with a photo too.\n"
            . "• A customer message with a «&lt;» in it did not reach support; fixed.\n"
            . "• <b>A second bot on the same server:</b> with another bot already on the server, a fresh install skipped some tables (users, admins, support). Fixed.\n"
            . "• The Plisio invoice showed «%s» instead of the number and amount to English customers. Fixed.\n"
            . "ℹ️ Nothing to set up.</blockquote>",
    ],
    [
        'n' => 88,
        'date' => '2026-11-10',
        'fa' => "<blockquote>📨 <b>تیکت‌های من</b>\n"
            . "مشتری توی «☎️ پشتیبانی» دکمهٔ «📨 تیکت‌های من» رو می‌بینه: لیست تیکت‌هاش با وضعیت (🟡 در انتظار پاسخ، 🟢 پاسخ داده شد، ⚫️ بسته شد)، گفتگوی هر تیکت، «✍️ ارسال پیام در همین تیکت» و «🔒 بستن تیکت». پشتیبان هم زیر هر پیام، کنار «پاسخ به پیام»، دکمهٔ «🔒 بستن تیکت» داره.\n"
            . "🔴 پیش‌فرض: <b>خاموش</b> (برای هر زبان جدا).\n"
            . "📍 1️⃣ ⚙️ تنظیمات عمومی ← 🌐 وضعیت قابلیت‌ها (هر زبان)\n"
            . "2️⃣ ردیف «📨 تیکت‌های من» رو روشن کن\n"
            . "3️⃣ متن‌ها و دکمه‌ها: 🎨 ← ☎️ پشتیبانی، کد هدیه و پیام‌های دیگر\n"
            . "🌐 پنل وب: «تیکت‌ها» (همهٔ تیکت‌ها، جواب دادن و بستن) و «قابلیت‌ها ← قابلیت‌های تازه».</blockquote>",
        'en' => "<blockquote>📨 <b>My tickets</b>\n"
            . "Under «☎️ Support» the customer gets «📨 My tickets»: their tickets with a state (🟡 waiting, 🟢 answered, ⚫️ closed), each ticket's conversation, «✍️ Write in this ticket» and «🔒 Close ticket». Support also gets «🔒 بستن تیکت» next to «پاسخ به پیام» under every message.\n"
            . "🔴 Default: <b>off</b> (per language).\n"
            . "📍 1️⃣ ⚙️ تنظیمات عمومی → 🌐 وضعیت قابلیت‌ها (هر زبان)\n"
            . "2️⃣ Turn on the «📨 تیکت‌های من» row\n"
            . "3️⃣ Texts and buttons: 🎨 → ☎️ پشتیبانی، کد هدیه و پیام‌های دیگر\n"
            . "🌐 Web panel: «Tickets» (every ticket, answer and close) and «Features → New features».</blockquote>",
    ],
    [
        'n' => 89,
        'date' => '2026-11-10',
        'fa' => "<blockquote>🔁 <b>تمدید خودکار از کیف پول</b>\n"
            . "توی صفحهٔ هر سرویس («🛍 سرویس‌های من») دکمهٔ «🔁 تمدید خودکار: خاموش ❌» میاد. اگه مشتری روشنش کنه، وقتی سرویس داره تموم می‌شه یا حجمش تموم شد، ربات خودش با همون محصول از کیف پولش تمدید می‌کنه و بهش خبر می‌ده.\n"
            . "مثال: سرویس ۳۰ روزه که فردا تموم می‌شه و مشتری ۲۰۰ هزار تومان موجودی داره → ۲۴ ساعت قبل از پایان، خودکار تمدید می‌شه.\n"
            . "• پول اول کم می‌شه، بعد پنل تمدید می‌کنه؛ اگه پنل خطا بده پول برمی‌گرده و توی تاپیک خطا گزارش می‌شه.\n"
            . "• موجودی کم باشه: روزی یک بار به مشتری خبر می‌ده (قابل خاموش کردن).\n"
            . "• هر تمدید خودکار توی تاپیک «سایر خدمات» گزارش می‌شه.\n"
            . "🔴 پیش‌فرض: <b>خاموش</b> (برای هر زبان جدا).\n"
            . "📍 1️⃣ ⚙️ تنظیمات عمومی ← 🌐 وضعیت قابلیت‌ها (هر زبان)\n"
            . "2️⃣ ردیف «🔁 تمدید خودکار از کیف پول» رو روشن کن\n"
            . "3️⃣ «⚙️ تنظیمات» کنارش: چند ساعت قبل از پایان (پیش‌فرض ۲۴) و خبر موجودی کم\n"
            . "🌐 پنل وب: «قابلیت‌ها ← قابلیت‌های تازه».</blockquote>",
        'en' => "<blockquote>🔁 <b>Auto-renew from the wallet</b>\n"
            . "Each service's page («🛍 My services») gets «🔁 Auto-renew: off ❌». With it on, when the service is about to end or its volume runs out, the bot renews it with the same plan from the customer's wallet and tells them.\n"
            . "Example: a 30-day service ending tomorrow, 200,000 toman in the wallet → renewed by itself 24 hours before the end.\n"
            . "• The money comes off first, then the panel renews; if the panel fails, the money goes back and the error topic is told.\n"
            . "• Not enough balance: the customer is told once a day (can be turned off).\n"
            . "• Every auto-renewal is reported in the «other services» topic.\n"
            . "🔴 Default: <b>off</b> (per language).\n"
            . "📍 1️⃣ ⚙️ تنظیمات عمومی → 🌐 وضعیت قابلیت‌ها (هر زبان)\n"
            . "2️⃣ Turn on «🔁 تمدید خودکار از کیف پول»\n"
            . "3️⃣ «⚙️ تنظیمات» beside it: hours before the end (24 by default) and the low-balance notice\n"
            . "🌐 Web panel: «Features → New features».</blockquote>",
    ],
    [
        'n' => 90,
        'date' => '2026-11-10',
        'fa' => "<blockquote>💌 <b>پیام برگشت مشتری</b>\n"
            . "مشتری‌ای که سرویسش تموم شده و برنگشته، چند روز بعد یه پیام «دلمون برات تنگ شده» با تخفیف مخصوص خودش روی خرید یا تمدید بعدی می‌گیره. تخفیف خودکار روی فاکتور حساب می‌شه (کدی لازم نیست) و بعد از اولین خرید یا تمدید، یا وقتی روزهاش تموم شد، برمی‌گرده به قبل.\n"
            . "مثال: ۳ روز بعد از تموم شدن سرویس، ۱۵٪ تخفیف که ۷ روز اعتبار داره.\n"
            . "• فقط برای کسی که سرویس فعال دیگه‌ای نداره، هر نفر حداکثر ماهی یک بار.\n"
            . "• روشن کردنش به مشتری‌هایی که خیلی وقت پیش رفتن پیام نمی‌ده.\n"
            . "• هر برگشت توی تاپیک «سایر خدمات» گزارش می‌شه؛ آمار فرستاده و برگشته توی صفحهٔ تنظیماتش.\n"
            . "🔴 پیش‌فرض: <b>خاموش</b> (برای هر زبان جدا).\n"
            . "📍 1️⃣ ⚙️ تنظیمات عمومی ← 🌐 وضعیت قابلیت‌ها (هر زبان)\n"
            . "2️⃣ ردیف «💌 پیام برگشت مشتری» رو روشن کن\n"
            . "3️⃣ «⚙️ تنظیمات» کنارش: چند روز بعد، درصد تخفیف، اعتبار تخفیف\n"
            . "🌐 پنل وب: «قابلیت‌ها ← قابلیت‌های تازه».</blockquote>",
        'en' => "<blockquote>💌 <b>Win-back message</b>\n"
            . "A customer whose service ended and who did not come back gets a «we miss you» message a few days later, with a discount of their own on the next purchase or renewal. It is applied on the invoice by itself (no code to type) and goes back after the first purchase or renewal, or when its days end.\n"
            . "Example: 3 days after the service ended, 15% off for 7 days.\n"
            . "• Only for someone with no other live service, at most once a month per person.\n"
            . "• Turning it on does not message customers who left long ago.\n"
            . "• Every return is reported in the «other services» topic; sent and returned counts are on its settings screen.\n"
            . "🔴 Default: <b>off</b> (per language).\n"
            . "📍 1️⃣ ⚙️ تنظیمات عمومی → 🌐 وضعیت قابلیت‌ها (هر زبان)\n"
            . "2️⃣ Turn on «💌 پیام برگشت مشتری»\n"
            . "3️⃣ «⚙️ تنظیمات» beside it: after how many days, the percent, how long it lasts\n"
            . "🌐 Web panel: «Features → New features».</blockquote>",
    ],
    [
        'n' => 91,
        'date' => '2026-11-10',
        'fa' => "<blockquote>💸 <b>انتقال موجودی بین کاربرها</b>\n"
            . "توی «👤 حساب کاربری» دکمهٔ «💸 انتقال موجودی» میاد: مشتری آیدی عددی گیرنده رو می‌فرسته، مبلغ رو می‌نویسه و تایید می‌کنه؛ همون لحظه از کیف پول خودش کم و به کیف پول گیرنده اضافه می‌شه و به هر دو خبر داده می‌شه.\n"
            . "• فقط بین کیف پول‌های هم‌ارز (تومان به تومان، دلار به دلار) و فقط از موجودی واقعی.\n"
            . "• زدن دوبارهٔ «تایید» دوبار انتقال نمی‌ده.\n"
            . "• هر انتقال توی تاپیک «پرداخت‌ها» با دکمهٔ مدیریت هر دو کاربر گزارش می‌شه.\n"
            . "🔴 پیش‌فرض: <b>خاموش</b> (برای هر زبان جدا).\n"
            . "📍 1️⃣ ⚙️ تنظیمات عمومی ← 🌐 وضعیت قابلیت‌ها (هر زبان)\n"
            . "2️⃣ ردیف «💸 انتقال موجودی بین کاربرها» رو روشن کن\n"
            . "3️⃣ «⚙️ تنظیمات» کنارش: کمترین و بیشترین مبلغ هر انتقال\n"
            . "🌐 پنل وب: «قابلیت‌ها ← قابلیت‌های تازه».</blockquote>",
        'en' => "<blockquote>💸 <b>Balance transfer between users</b>\n"
            . "«👤 Account» gets «💸 Transfer balance»: the customer sends the receiver's numeric ID, types the amount and confirms; it comes off their wallet and onto the receiver's at once, and both are told.\n"
            . "• Only between wallets of the same currency (toman to toman, dollar to dollar) and only from real balance.\n"
            . "• Tapping «confirm» twice never transfers twice.\n"
            . "• Every transfer is reported in the «payments» topic with a manage button for both users.\n"
            . "🔴 Default: <b>off</b> (per language).\n"
            . "📍 1️⃣ ⚙️ تنظیمات عمومی → 🌐 وضعیت قابلیت‌ها (هر زبان)\n"
            . "2️⃣ Turn on «💸 انتقال موجودی بین کاربرها»\n"
            . "3️⃣ «⚙️ تنظیمات» beside it: the smallest and largest amount of one transfer\n"
            . "🌐 Web panel: «Features → New features».</blockquote>",
    ],
    [
        'n' => 92,
        'date' => '2026-11-10',
        'fa' => "<blockquote>🔐 <b>کد تایید تلگرامی برای ورود به پنل وب</b>\n"
            . "با روشن کردنش، بعد از وارد کردن رمز درست، یه کد ۶ رقمی از طرف همین ربات به تلگرامت میاد و پنل وب فقط با اون کد باز می‌شه. اگه کسی رمزت رو داشته باشه، بدون تلگرامت نمی‌تونه وارد بشه، و توی پیام کد، IP کسی که رمز رو زده نوشته می‌شه.\n"
            . "• کد ۵ دقیقه اعتبار داره و ۵ بار می‌شه امتحانش کرد.\n"
            . "🔴 پیش‌فرض: <b>خاموش</b>.\n"
            . "📍 1️⃣ ⚙️ تنظیمات عمومی ← پنل تحت وب ربات 🤖\n"
            . "2️⃣ «🔐 کد تایید تلگرامی هنگام ورود» رو بزن تا ✅ بشه\n"
            . "🌐 پنل وب: «تنظیمات ← امنیت».</blockquote>",
        'en' => "<blockquote>🔐 <b>A Telegram code to log in to the web panel</b>\n"
            . "With it on, after the right password a 6-digit code comes from this bot to your Telegram, and the web panel opens only with it. Someone with your password cannot get in without your Telegram, and the code message names the IP that typed the password.\n"
            . "• The code lasts 5 minutes and can be tried 5 times.\n"
            . "🔴 Default: <b>off</b>.\n"
            . "📍 1️⃣ ⚙️ تنظیمات عمومی → پنل تحت وب ربات 🤖\n"
            . "2️⃣ Tap «🔐 کد تایید تلگرامی هنگام ورود» until it shows ✅\n"
            . "🌐 Web panel: «Settings → Security».</blockquote>",
    ],
];
