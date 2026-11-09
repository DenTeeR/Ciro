# Bot statistics: "📊 آمار ربات" (build it like Ciro's)

Every bot built with this skill gets an admin statistics screen, the first button of the admin
menu, and the same numbers on the web panel's dashboard. Below is Ciro's design, written
generally: "order" is whatever the bot sells or does (a subscription, a download, a booking),
"path" is a platform or feature (see Paths in SKILL.md).

## The screen

- Language tabs on top (`✅🇮🇷 فارسی | 🇬🇧 English`): each language's users, sales and wallets
  are counted on their own, in that language's currency. A rate between languages is never
  mixed into one total.
- Opens on the overall view; the buttons under it switch the period by editing the same
  message:

```
[✅🇮🇷 فارسی] [🇬🇧 English]
[⏱️ آمار کل]
[⏱️ یک ساعت اخیر]
[⛅️ امروز] [☀️ دیروز]
[☀️ ماه فعلی] [⛅️ ماه قبل]
[🗓 مشاهده آمار در تاریخ مشخص]      ← asks for a from-date and a to-date (Jalali for Persian)
```

## Overall view (Ciro's text)

```
🌐 آمار کاربران و فروش زبان: <b>🇮🇷 فارسی</b>
📊 <b>آمار کلی ربات</b>
━━━━━━━━━━━━━━━━━━
👥 <b>تعداد کل کاربران:</b> <code>1,240</code> نفر
💳 <b>کاربران دارای خرید:</b> <code>310</code> نفر
🧪 <b>اکانت‌های تست:</b> <code>95</code> نفر
💰 <b>موجودی کل کاربران:</b> <code>4,200,000 تومان</code>

🧾 <b>تعداد کل فروش:</b> <code>620</code> عدد
🧾 <b>تعداد کل فروش سرویس های فعال:</b> <code>280</code> عدد
💵 <b>جمع کل فروش :</b> <code>93,000,000 تومان</code>
💵 <b>جمع کل فروش سرویس های فعال:</b> <code>42,000,000 تومان</code>
🔄 <b>جمع کل تمدید:</b> <code>18,500,000 تومان</code>
📈 <b>نرخ تبدیل به مشتری:</b> <code>25</code>٪
💳 <b>میانگین خرید هر مشتری:</b> <code>135,000 تومان</code>
📅 <b>درآمد پیش‌بینی‌شده ماهانه:</b> <code>36,000,000 تومان</code>
📊 <b>درصد تمدید از فروش:</b> <code>44</code>٪

👨‍💼 <b>تعداد کل نمایندگان:</b> <code>12</code> نفر
🔹 <b>نمایندگان نوع N:</b> <code>9</code> نفر
🔸 <b>نمایندگان نوع N2:</b> <code>3</code> نفر
🧩 <b>تعداد پنل‌ها:</b> <code>5</code> عدد
💳 کارت به کارت: 410 عدد · 61,000,000 تومان       ← one line per gateway that was used
```

How each number is made:
- **buyers:** distinct users with at least one paid order. **Conversion:** buyers ÷ users.
- **Average per customer:** active sales ÷ buyers.
- **Monthly forecast:** yesterday's sales × 30.
- **Renewal share:** renewals ÷ active sales, capped at 100.
- **Per gateway:** paid payments only. Manual credits by an admin are not income and are left
  out.

Lines for the bot's other paid features are appended the same way (Ciro adds a line for its
hourly accounts).

## A period (one hour, today, yesterday, this month, last month, chosen dates)

```
🕐 <b>آمار روز فعلی</b>
⏳ بازه تایم  : 1405/08/16 00:00 تا 1405/08/16 14:20

🛍 تعداد سفارشات : 18 عدد
💸 جمع مبلغ سفارشات  : 2,700,000 تومان
🧲 تعداد تمدید  : 7 عدد
💰 جمع مبلغ تمدید: 1,050,000 تومان
📦 حجم‌های اضافه  : 3 عدد · 💰 210,000 تومان
⏱️ زمان‌های اضافه  : 1 عدد · 💰 60,000 تومان
📍 تغییر لوکیشن  : 0 عدد · 💰 0 تومان
🔑 اکانت‌های تست  : 11 عدد
👤 تعداد کاربران  : 26 نفر                     ← new users in the period
```

Every paid action the bot sells gets its own pair of lines: count and sum.

## Counting rules

- Unpaid invoices never count; test accounts are counted on their own line, never as sales.
- A period includes both ends; store times as unix timestamps (convert legacy text dates
  once in the query).
- Queries take parameters, never string-built values. Per-language filtering is one shared
  helper (where-clause + params) used by every query, so the tabs always agree.
- With paths: a per-path block (uses and sales of each platform or feature) under the totals,
  in the registry's order.

## Per panel / server

On each panel's management screen: connection state, number and sum of sales on that panel,
user group. Shows the admin at once which server sells and which one is down.

## Web panel dashboard

- **Cards:**
  - کل کاربران
  - درآمد کل (each currency on its own line)
  - سرویس فعال
  - پرداخت در انتظار, with a link to them
- **Two short tables:** latest orders and latest users, every ID clickable to the profile.
- **Filters:**
  - a language filter (همه / each language with its count)
  - a date-range filter: today, 7 days, 30 days, custom from-to
- **Resetting counters.** Buttons to reset the dashboard's income, today's transactions and
  active-services counters. A reset stores a *baseline* (a start time or offset) and the cards
  count from there; it never deletes payments or orders, so the bot's own statistics and every
  report stay complete. Each reset asks once to confirm and can be undone.
