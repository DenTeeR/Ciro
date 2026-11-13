<div align="center">

<img src=".github/readme/banner.jpg" alt="Ciro — Telegram VPN bot. Sell. Manage. Connect." width="100%"/>

**A Telegram bot that sells and manages VPN subscriptions, with a separate shop for Persian and English customers.**

**English** | [Persian](README.fa.md)

<p>
  <img src="https://img.shields.io/badge/PHP-8.2-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.2"/>
  <img src="https://img.shields.io/badge/license-AGPL--3.0-blue?style=flat-square" alt="AGPL-3.0"/>
  <img src="https://img.shields.io/badge/Ubuntu%20%7C%20Debian-supported-E95420?style=flat-square&logo=ubuntu&logoColor=white" alt="Ubuntu / Debian"/>
</p>

</div>

## Install

On a clean Ubuntu 20.04+ or Debian 11+ server, as root:

```bash
bash <(curl -Ls https://raw.githubusercontent.com/DenTeeR/Ciro/master/install.sh)
```

Have ready: a domain already pointed at the server, a bot token from [@BotFather](https://t.me/BotFather) and your numeric id from [@userinfobot](https://t.me/userinfobot).

After installing, manage it with the `Ciro` command (capital C — Linux commands are case-sensitive):

| Command | What it does |
| --- | --- |
| `Ciro` | Open the menu |
| `Ciro update` | Get the newest version; your data and `config.php` are kept |
| `Ciro version` | Show the bot's version and the newest one on GitHub |
| `Ciro backup` | Send a database backup to Telegram |
| `Ciro renew` | Reissue the SSL certificate |
| `Ciro --help` | Every command and option |

Already running a bot of the original project, or an older version of this one? Run the same install command and choose **Update** or **Migrate** — nothing is reinstalled and no data is lost.

## Features

<p align="center">
  <img src=".github/readme/bilingual.jpg" alt="The bot in English and in Persian, each with its own texts, prices and currency" width="100%"/>
</p>

- Separate settings, texts, prices and currency for each language (fa / en)
- Every message and button can be reworded from the bot's 🎨 menu or the web panel, with premium emoji and stickers
- Web admin panel

<p align="center">
  <img src=".github/readme/integrations.jpg" alt="Supported VPN panels and payment methods" width="100%"/>
</p>

- **Panels:** Marzban, Marzneshin, X-UI, Hiddify, S-UI, WGDashboard, Mikrotik, IBSng, Alireza, Rebecca, Remnawave
- **Payments:** card-to-card, Zarinpal, AqayePardakht, IranPay, Plisio, NOWPayments, TRX, TON, USDT (BEP20), Telegram Stars

---

### About this project

Ciro is a fork of the **[Mirza bot](https://github.com/mahdiMGF2/mirzabot)**. We accept no responsibility for any sale of this bot; it is available free of charge on this page. Thanks to the original creator of Mirza, [@mahdiMGF2](https://github.com/mahdiMGF2).

Licensed under **GNU AGPL-3.0**, like the original project.
