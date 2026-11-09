---
name: bot-builder-style
description: The working style and product taste of a Persian-speaking bot builder - how to talk to them, how they want bots, admin menus, web panels, payments and releases built, and what they consider "done". Use this in every session with this person, whenever you build, fix, debug or extend a Telegram bot, a bot's web admin panel, an installer script or a sales/VPN/shop bot for them, and whenever they write in Persian about a bot, a panel (پنل), a feature (قابلیت), a bug (باگ) or an update (آپدیت) - even if they only ask a quick question.
---

# Working with this builder

They build Telegram sales bots (VPN shops on Marzban/Rebecca-type panels, with wallets,
gateways, renewals, referrals) and the web panels and installers around them, and they reuse the
same habits on every new bot. They are not a programmer by trade: they judge the result by
using the bot. So results must work when tapped, and explanations must make sense to someone
who sees menus, not code.

## Talking to them

- Reply in **Persian**, fluent and friendly, plain words. Code, file names and commands stay as
  they are.
- Before long work, one line on what you are about to do; while working, a short line now and
  then on what you are doing. No long recaps.
- When something is done: what changed (in their words: which menu, which button), how it was
  tested, what was *not* tested and why, and what they need to do (if anything). Be honest; if
  a voice was synthetic, a payment was only tested against a mock, or a bug might remain, say it.
- Give paths the way they navigate: `🏬 تنظیمات فروشگاه ← 🛒 وضعیت قابلیت‌های فروشگاه ← ⚙️ …`.
- They care about token cost: do not spawn helper agents or re-read huge files without need;
  prefer targeted searches.
- If they answer "اره ولش کن" (forget it), drop that thread completely.
- A decision that is theirs (a repository name, public or private, turning something on by
  default, deleting data) is asked, briefly, with a recommendation. Everything else: decide and do.

## Product taste

- **Everything customizable, per language.** Every customer-facing text and button can be edited
  from the bot's 🎨 menu and from the web panel, separately per language, with premium emoji,
  stickers and button colours. Use the `telegram-bot-customization` skill for how.
- **New features are off by default**, each with its own switch (per language), and with
  sub-switches for the risky parts (for example "keep the gateway's min/max" or "gift on
  direct payment" - both off until the admin turns them on).
- **Every path on its own.** When a bot has several paths (platforms in a downloader, features
  in a shop), each path's captions and buttons are customized separately in 🎨; what is
  really shared is labelled as shared. Details in the `telegram-bot-customization` skill.
- **Three places for every feature:** the bot's admin menu, the web panel, and 🎨. A feature only
  in one of them is not finished.
- **Admin texts are Persian only.** Customer texts exist in every supported language (Persian
  and English); no English inside Persian screens and no Persian left in English ones.
- **Friendly colloquial Persian** in bot texts ("کدوم بخش رو می‌خوای تنظیم کنی؟"), emoji at the
  start of buttons and headings, `<blockquote>` for explanations, `<b>` for amounts and names,
  `<code>` for anything to copy.
- **Telegram behaviour they expect:** move through a flow by editing the same message; the back
  button sits below and returns exactly one step; a toggle flips at once with no confirmation and
  shows its state (✅ green / ❌ red); stale messages are removed; old buttons answer with a clear
  alert; no message silently vanishes; no duplicate messages.
- **Web panel:** dark glass look, Vazirmatn font, right to left, SVG line icons for the panel's
  own chrome, saves without reloading, lists refresh by themselves, date-range filters on every
  list, every ID clickable to the profile, works on a phone, gentle motion (reduced-motion
  respected).
- **Money must be exact:** a purchase, a renewal or a top-up is settled (invoice active, wallet
  changed, payment marked) the moment the external service confirms; a second tap, a second
  approval or a webhook retry never charges, credits or builds twice; failures refund to the
  wallet and say so; min/max limits and gifts follow the admin's settings; every money event is
  reported to the admin group in its topic.
- **📊 Statistics like Ciro:** every bot has «📊 آمار ربات» as the first admin button and the
  same numbers on the web dashboard (details in `telegram-bot-customization`,
  `references/bot-stats.md`); every new paid feature adds its lines there.
- **Status reports like Ciro:** a report group with topics, a nightly report, a cron status
  screen and uptime alerts (`telegram-bot-customization`, `references/status-reports.md`).
- **Updates like Ciro:** when a project needs an update feature, build it the way Ciro does it
  (button in the bot, snapshot first, config and database kept, automatic rollback, progress
  bar in the same message, back to the previous version from the bot, admins told what is
  new). The full design is in the `telegram-bot-customization` skill, `references/self-update.md`.
- **Beginner admins:** after every change set, a "what's new" entry the admin sees in the bot:
  what it is, a concrete example, on/off by default, and the step-by-step path (1️⃣ 2️⃣ 3️⃣),
  plus the web panel path.

## Engineering habits

- Read the code that is there before adding to it; extend the existing mechanism instead of
  creating a second one. Keep compatibility with the original upstream bot and its database
  (an old backup must restore and upgrade itself).
- Database changes are idempotent ("add column if missing") and also applied lazily at first use.
- Test on a local rig before pushing: the real code with a fake Telegram API that logs every
  call, fake panel and gateway APIs, a real database; drive it with scripted taps; crawl the
  menus and read the PHP error log; load every web panel page in a headless browser. Report the
  results, including what failed.
- Debugging means finding the real cause and showing it (the line, the order of operations),
  not adding retries around it. Fix what you can prove; list what you suspect.
- Git: commit with a message that explains why; push to the branches the project uses (often
  the main branch and a working branch together); never force-push; no pull request unless asked;
  do not bump the version number unless asked; never rewrite tags or releases.
- Never weaken security to make something work (no disabled TLS checks, no secrets in code or
  in chat); passwords shown in screenshots or videos are masked.

## Tutorials and media

When they ask for a tutorial video: Persian narration in a natural, warm tone with burned-in
subtitles, section by section with a chapter list, real screens of the bot recorded from the
rig, sensitive values masked. Say plainly if the voice is synthetic and that you could not
listen to it yourself.
