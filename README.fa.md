<div align="center" dir="rtl">

<img src=".github/readme/banner.jpg" alt="ربات تلگرامی فروش VPN با نام Ciro" width="100%"/>

**ربات تلگرامی برای فروش و مدیریت اشتراک VPN، با فروشگاه جداگانه برای مشتریان فارسی‌زبان و انگلیسی‌زبان.**

[انگلیسی](README.md) | **فارسی**

<p>
  <img src="https://img.shields.io/badge/PHP-8.2-777BB4?style=flat-square&logo=php&logoColor=white" alt="نسخهٔ پی‌اچ‌پی ۸٫۲"/>
  <img src="https://img.shields.io/badge/license-AGPL--3.0-blue?style=flat-square" alt="مجوز AGPL-3.0"/>
  <img src="https://img.shields.io/badge/Ubuntu%20%7C%20Debian-supported-E95420?style=flat-square&logo=ubuntu&logoColor=white" alt="پشتیبانی از اوبونتو و دبیان"/>
</p>

</div>

<div dir="rtl">

## نصب

روی یک سرور تمیز با Ubuntu 20.04 به بالا یا Debian 11 به بالا، و با کاربر root، این دستور را اجرا کنید:

</div>

```bash
bash <(curl -Ls https://raw.githubusercontent.com/DenTeeR/Ciro/master/install.sh)
```

<div dir="rtl">

پیش از شروع این‌ها را آماده داشته باشید: دامنه‌ای که از قبل به آی‌پی سرور وصل شده باشد، توکن ربات از [‎@BotFather](https://t.me/BotFather) و شناسهٔ عددی حساب تلگرام خودتان از [‎@userinfobot](https://t.me/userinfobot).

بعد از نصب، ربات را با دستور `Ciro` مدیریت می‌کنید (حرف C باید بزرگ باشد؛ لینوکس حروف کوچک و بزرگ را فرق می‌گذارد):

| دستور | کاری که انجام می‌دهد |
| --- | --- |
| `Ciro` | باز کردن منو |
| `Ciro update` | دریافت آخرین نسخه؛ اطلاعات شما و فایل `config.php` حفظ می‌شود |
| `Ciro version` | نمایش نسخهٔ ربات و جدیدترین نسخهٔ گیت‌هاب |
| `Ciro backup` | ارسال نسخهٔ پشتیبان پایگاه‌داده به تلگرام |
| `Ciro renew` | صدور مجدد گواهی SSL |
| `Ciro --help` | فهرست همهٔ دستورها و گزینه‌ها |

اگر از قبل ربات پروژهٔ اصلی یا نسخهٔ قدیمی‌تر همین پروژه را اجرا می‌کنید، همان دستور نصب را اجرا کنید و گزینهٔ **Update** یا **Migrate** را انتخاب کنید؛ چیزی از نو نصب نمی‌شود و هیچ اطلاعاتی از بین نمی‌رود.

## امکانات

<p align="center">
  <img src=".github/readme/bilingual.jpg" alt="ربات به زبان انگلیسی و فارسی، هر کدام با متن‌ها، قیمت‌ها و واحد پول جداگانه" width="100%"/>
</p>

- تنظیمات، متن‌ها، قیمت‌ها و واحد پول جداگانه برای هر زبان (فارسی / انگلیسی)
- همهٔ پیام‌ها و دکمه‌ها از منوی 🎨 ربات یا از پنل وب قابل ویرایش‌اند، با پشتیبانی از ایموجی پریمیوم و استیکر
- پنل مدیریت تحت وب

<p align="center">
  <img src=".github/readme/integrations.jpg" alt="پنل‌های VPN و روش‌های پرداخت پشتیبانی‌شده" width="100%"/>
</p>

- **پنل‌های پشتیبانی‌شده:** Marzban، Marzneshin، X-UI، Hiddify، S-UI، WGDashboard، Mikrotik، IBSng، Alireza، Rebecca، Remnawave
- **روش‌های پرداخت:** کارت‌به‌کارت، زرین‌پال، آقای پرداخت، IranPay، Plisio، NOWPayments، TRX، TON، USDT (BEP20)، ستارهٔ تلگرام

---

### دربارهٔ این پروژه

ربات Ciro نسخهٔ فورک‌شدهٔ ربات **[میرزا](https://github.com/mahdiMGF2/mirzabot)** است. ما هیچ‌گونه مسئولیتی در قبال فروش این ربات نداریم و این ربات به‌صورت رایگان در همین صفحه در دسترس است. با تشکر از سازندهٔ اصلی ربات میرزا، [‎@mahdiMGF2](https://github.com/mahdiMGF2).

این پروژه مانند پروژهٔ اصلی تحت مجوز **GNU AGPL-3.0** منتشر شده است.

---

</div>
