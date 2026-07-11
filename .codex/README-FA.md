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
