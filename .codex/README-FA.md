# پکیج Codex سایت سبحان

این پکیج برای ریپوی سایت سبحان طراحی شده است.

## ساختار اصلی

- `AGENTS.md`: دستور اصلی که Codex از ریشه ریپو می‌خواند.
- `.codex/config.toml`: تنظیمات امن پروژه.
- `.codex/environments/environment.toml`: محیط Local/Worktree.
- `.codex/scripts/`: بررسی محیط، lint، validation و backup.
- `.codex/references/`: مرجع معماری، دیتابیس، دسترسی‌ها و دامنه فروش.
- `.agents/skills/`: Skillهای اختصاصی سایت سبحان.

## اجرای اولیه

در PowerShell و داخل ریشه پروژه:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .codex/scripts/setup.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File .codex/scripts/doctor.ps1
```

## نکته مهم

- فایل‌های تنظیمات و مستندات را می‌توان Commit کرد.
- فایل‌های `.env`، بکاپ دیتابیس، خروجی‌ها و اطلاعات محرمانه نباید Commit شوند.
- اگر Codex Skillها را نشان نداد، پروژه را دوباره باز یا Codex را Restart کن.

## قرارداد اجرای فازها

- اگر پرامپت شامل «اجرا»، «پیاده‌سازی»، «اصلاح»، «ادامه» یا نام یک فاز باشد، Codex باید همان فاز را واقعاً در کد/تنظیمات/اسکریپت/تست اجرا و اعتبارسنجی کند.
- نام‌گذاری فاز به‌تنهایی به معنی مستندسازی نیست.
- فقط وقتی صریحاً گفته شود «فقط بررسی»، «فقط گزارش»، «فقط برنامه‌ریزی»، «docs-only» یا «کد تغییر نکند»، کار باید بدون ویرایش بماند.
- خطر بالا باعث بررسی و تست بیشتر می‌شود، نه اینکه کار به ساخت چند فایل `*.md` محدود شود.
- مستندات باید در کنار تغییر واقعی و در صورت نیاز به‌روزرسانی شوند، نه به جای آن.
- هر فاز فقط در محدوده خودش اجرا می‌شود و Codex بدون درخواست، وارد فاز بعدی نمی‌شود.

برای بررسی سالم‌ماندن این قرارداد:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .codex/scripts/policy-contract-test.ps1
```
