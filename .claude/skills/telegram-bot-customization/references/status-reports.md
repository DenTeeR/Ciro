# Status reports: «گزارش وضعیت» (build them like Ciro's)

The owner should know what the bot is doing without opening it: every sale, every failure,
a summary each night, and an alarm when something stops. Ciro does this with a Telegram
**report group with topics**, a **nightly report**, a **cron status screen** and **uptime
alerts**. Build all four into every bot.

## 1. The report group (📣 گزارشات ربات)

- Admin path: ⚙️ تنظیمات عمومی ← 📣 گزارشات ربات. The screen explains the setup step by step:
  1️⃣ make a group, 2️⃣ get its numeric id (for example with a helper bot), 3️⃣ turn on
  Topics in the group settings, 4️⃣ make the bot an admin, 5️⃣ send the id here.
- The bot then creates one **forum topic per kind of report**, so each kind stays readable:

| Topic | What goes there |
|---|---|
| 🛍 گزارش های خرید | every purchase: user, service, panel, price, wallet before/after, invoice id, first purchase or not |
| 📌 گزارش خرید خدمات | renewals, extra volume, extra time, location change |
| 🔑 گزارش اکانت تست | every test account given |
| 💰 گزارش مالی | top-ups, receipts, gateway payments, refunds |
| 👥 پورسانت | referral commissions paid |
| ❌ گزارش خطا ها | anything that failed: panel refused, gateway paid but crediting failed, a cron error |
| ⚙️ سایر گزارشات | the rest (blocks, admin actions, settings changes) |
| 🌙 گزارش شبانه | the nightly report |
| 📝 گزارش اطلاع رسانی ها | notices the crons sent (expiry warnings, reminders) |
| 🤖 بکاپ ربات | the backup files |

  Topics for later features (a node monitor, hourly accounts, gifts…) are created the first
  time such a report is sent.
- **Remember your own topics.** Telegram cannot list a group's topics, so the bot keeps its
  own record (a file, not a table, so it survives a database restore): group, topic id, report
  kind. Setting the group again reuses the topics it made, creates only the missing ones,
  removes its own duplicates and never touches topics it did not make.
- **Every report is useful on its own:** who (id, @username, a `⚙️ مدیریت کاربر` button),
  what, where (panel / path), how much, the order or invoice id in `<code>`, and the time in
  Jalali for Persian. Error reports also say what to do ("open the order and approve it again",
  "the panel is down").
- **Reports never break the action.** Sending a report is best effort: if the group is not set
  or Telegram refuses, the purchase still completes. But a money event that failed after the
  customer paid is always reported, with the order id, so the admin can finish it by hand.
- Reports are in the admin language; amounts in the payer's currency.

## 2. The nightly report (🌙 گزارش شبانه)

Sent once a day (Ciro: 23:45) to its topic:

```
📌 گزارش روزانه کارکرد ربات :

🧲 تعداد تمدید امروز : 7 عدد
💰 جمع تمدید امروز : 1,050,000 تومان
🛍 تعداد سفارشات امروز : 18 عدد
🛍 جمع مبلغ سفارشات امروز : 2,700,000 تومان
🔑 اکانت های تست امروز : 11 عدد
🔋 جمع حجم های فروخته شده : 900 گیگابایت
تعداد کاربرانی که امروز به ربات پیوستند : 26 نفر
```

Followed by the day's **top agents** (id, @username, total bought) and a **per panel /
per path** block (orders, sum, volume). Same counting rules as the statistics screen
(`bot-stats.md`): unpaid never counts, tests on their own line.

## 3. Cron status (⏱ وضعیت کرون‌ها)

Admin path: ⚙️ تنظیمات عمومی ← ⏱ وضعیت کرون‌ها. Every cron job writes a heartbeat (its name and
the time it ran) each run; the screen reads them:

```
⏱ <b>وضعیت کرون‌ها</b>

✅ کرون‌ها در حال اجرا هستند                 ← or: ❌ در ۳ دقیقهٔ گذشته هیچ کرونی اجرا نشده…
🕐 آخرین اجرا: 1405/08/17 14:20:01
🗓 خطوط کرون در crontab: 21 از 21

✅ بررسی رسیدهای کارت به کارت — ۱ دقیقه پیش
✅ هشدار حجم و زمان سرویس‌ها — ۲ دقیقه پیش
❌ پرداخت TON — هرگز
…
[🔄 بروزرسانی] [🔧 تنظیم مجدد کرون]
```

- One line per job, with its human name, ✅/❌ and when it last ran.
- `🔧 تنظیم مجدد کرون` re-registers every line in the crontab (only the missing ones; never
  duplicates a hand-edited line).
- If no cron ran for a few minutes, say it plainly at the top: nothing automatic is happening.

## 4. Uptime alerts (🎛 آپتایم پنل / نود)

- Switches in the global feature list: `🎛 آپتایم پنل`, `🎛 آپتایم نود` (off by default).
- A cron checks each panel / server / node; when one stops answering, every admin gets
  `🚨 ادمین عزیز پنل با اسم <code>آلمان</code> متصل نیست.` and the error topic gets the same.
  When it comes back, one "✅ دوباره وصل شد" message.
- **No flood:** while it stays down, reminders follow an interval and a maximum count the admin
  sets (Ciro: «یادآوری قطعی» with interval and count); a resource alarm (CPU / RAM over a
  threshold, default 90%) follows the same rule.
- Each panel's own screen shows its connection state too (see `bot-stats.md`).

## Checklist for a new feature

A new paid or automatic feature gets: its report in the right topic (or a new topic created
on first use), its error report when it fails after payment, its line in the nightly report if
it sells, and a heartbeat + a line on the cron status screen if it has a cron.
