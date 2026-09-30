![GitHub stars](https://img.shields.io/github/stars/voipiran/AsteriskGrafana?style=for-the-badge)
![GitHub last commit](https://img.shields.io/github/last-commit/voipiran/AsteriskGrafana?style=for-the-badge)
![License](https://img.shields.io/github/license/voipiran/AsteriskGrafana?style=for-the-badge)

![Issabel Call Monitoring - VoipIran](https://raw.githubusercontent.com/voipiran/IssabelCallMonitoring/main/image.jpg)
![Issabel Call Monitoring - VoipIran](https://raw.githubusercontent.com/voipiran/IssabelCallMonitoring/main/image-en.png)
## IssabelCallMonitoring
Issabel5 Free Call Monitoring Panel

### جایگزینی قدرتمند و کاملاً فارسی برای Operator Panel ایزابل

اگر با Issabel 4 کار کرده باشید، حتماً با ماژول محبوب **Operator Panel** آشنا هستید؛ ابزاری که امکان مشاهده و مدیریت زندهٔ تماس‌های داخلی، شهری، صف‌ها و ترانک‌ها را فراهم می‌کرد.  
متأسفانه در **Issabel 5** این ماژول به‌طور کامل حذف شده و کاربران زیادی به‌دنبال جایگزینی مطمئن، پایدار و قابل توسعه بوده‌اند.

تیم **VOIPIRAN** دقیقاً برای پر کردن این خلأ، پروژهٔ **Issabel Call Monitoring** را توسعه داده است؛ یک ماژول **کاملاً رایگان، کدباز و بومی‌سازی‌شده** که:

- تمام قابلیت‌های Operator Panel قدیمی را با دقت بازسازی کرده  
- کاملاً با **Issabel 5** سازگار است  
- دارای **ظاهر مدرن، کارت‌مانند و کاملاً فارسی** است  
- **نسخه انگلیسی LTR** نیز به‌صورت کامل اضافه شده است  
- اسکریپت نصب هوشمند دارد که در ابتدای نصب از شما می‌پرسد:  
  **«نسخه فارسی می‌خواهید یا انگلیسی؟»** و به‌صورت خودکار نسخه مناسب را نصب می‌کند

این پروژه توسط **حامد کوه‌فلاح** — مدرس و توسعه‌دهنده تخصصی Asterisk و Issabel — طراحی و توسعه داده شده و به‌صورت **کاملاً رایگان** در اختیار تمام مدیران شبکه و سیستم‌های تلفنی و علاقه‌مندان قرار گرفته است.

---
## Issabel Call Monitoring
Free, open-source replacement for the removed Operator Panel in Issabel 5.

Fully recreates classic features
100% compatible with Issabel 5
Modern card-based UI (full Persian RTL + full English LTR)
Smart installer asks: “Persian or English?” and auto-installs
Developed by Hamed Koohfallah (VOIPIRAN) – completely free for everyone.


> با ❤️ توسعه‌یافته توسط [VoipIran.io](https://voipiran.io)
---
## Installation
Just Copy and Paste on your Linux CLI:
```
curl -L -o callmonitoring.zip https://github.com/voipiran/IssabelCallMonitoring/archive/master.zip && \
unzip -o callmonitoring.zip && \
cd IssabelCallMonitoring-main && \
chmod 755 install2.sh && \
./install2.sh
```

## Give a Star! ⭐ یک ستاره با ما بدهید
If you like this project or plan to use it in the future, please give it a star. Thanks 🙏

## One-second snapshot polling

The dashboard now requests a fresh, complete PBX snapshot using ordinary HTTP
JSON requests (`action=pbxSnapshot`). It does not open an EventSource connection
or depend on live AMI change notifications. AMI login disables unsolicited events;
responses to explicit status queries still provide the requested snapshot data.

Each successful snapshot updates extensions, active calls, caller identity,
waiting queue callers, queue members, queue statistics, and configured auxiliary
panels. Missing objects and calls are removed. Unchanged cards are retained to
avoid rebuilding the page on every poll. Call and wait timers run locally.

The browser targets one request per second, with only one request in flight.
If collecting a snapshot takes longer than a second, the next request waits for
completion. A failed request preserves the previous display, marks the connection
as disconnected, and retries after three seconds. The browser timeout is seven
seconds. AMI connection, commands and enumeration share a five-second startup
budget, preventing an absent completion event from leaving a request waiting
indefinitely. Database and web-server delays are outside that AMI budget.

Initial queue callers and caller-ID handling from the earlier fixes are retained.
Mailbox requests use batches of up to 32; optional status queries are restricted
to configured technologies/panels. Queue statistics are included in every snapshot,
so there is no separate five-minute polling timer. The old streaming endpoint is
retained for compatibility with older clients, but the updated browser does not
use it.

Full snapshots necessarily use more bytes as the number of extensions and calls
grows. The old accidental PHP state dumps remain removed. The earlier 279-byte
single-call delta benchmark describes the legacy stream and is not a bandwidth
estimate for this polling mode. Measure actual traffic on your PBX.

### Updating an existing installation

Back up `/var/www/html/modules/control_panel/`, then replace these four files
**together** from your selected language folder (`control_panel` for Persian,
`control_panel_en` for English) into the matching installed paths:

- `index.php`
- `libs/AGI_AsteriskManager2.class.php`
- `libs/paloControlPanelStatus.class.php`
- `themes/default/js/javascript.js`

Preserve your installed `libs/config.php`. Close all existing dashboard tabs and
hard-refresh after updating. No Asterisk restart or database migration is needed.
Running `install2.sh` replaces the entire installed directory, including config
and customizations, so targeted copying is recommended for an existing install.

### Verification

Run from the repository root with PHP CLI and Node.js; no PBX is required:

```sh
php tests/startup-regression.php control_panel
php tests/startup-regression.php control_panel_en
node tests/browser-regression.js
```

Tests exercise mailbox response correlation over local sockets, complete snapshots
with 100 extensions and 10 waiting callers, caller ID and answer states, queue
isolation, object removal, one-second polling, non-overlapping requests, retry
behavior, and deadline handling. The startup tests also run the legacy backend
regressions. These are local simulations, not measurements of your live PBX.

After deployment, check the browser Network tab for short `pbxSnapshot` requests
roughly once a second, with no `serverevents=true` stream. With callers already
waiting, open the page, answer a call, and hang up without reloading. Verify both
the extension and queue-agent indicators. If snapshots time out, inspect their
JSON error response and measure PBX/database response times rather than increasing
the number of overlapping requests.
