# Sobhan Notification Hub

اپلیکیشن WPF مرکزی اعلان‌های سازمانی سبحان برای Windows 10 (19041+) و Windows 11.

## قابلیت‌ها

- Windows App SDK App Notifications و ثبت در Notification Center
- Actionهای `open`، `mark_read`، `reply`، `approve`، `reject` و `mute` با اعتبارسنجی سمت سایت
- Tray Icon با دسترسی به پنل، پیام‌رسان، کارتابل، تیکت‌ها، تاریخچه و تنظیمات
- Polling داینامیک با تنظیم سایت و backoff سی/شصت/صدوبیست ثانیه
- SQLite cache برای ۵۰۰ اعلان آخر و جلوگیری از نمایش تکراری
- ذخیره توکن با Windows DPAPI و scope کاربر جاری
- Single Instance و Auto Start در HKCU
- fallback محدود به NotifyIcon BalloonTip فقط هنگام شکست AppNotification API
- لاگ امن در `%LOCALAPPDATA%\SobhanNotificationHub\logs\app.log`

## اتصال دستگاه

1. کاربر وارد `/admin/notification-devices.php` می‌شود.
2. «اتصال این ویندوز به پنل من» را می‌زند و کد شش‌رقمی یک‌بارمصرف می‌گیرد.
3. آدرس HTTPS سایت را در `appsettings.json` تنظیم و برنامه را اجرا می‌کند.
4. کد را در پنجره اتصال وارد می‌کند.
5. برنامه از `/api/notify/pair-device.php` شناسه و token می‌گیرد؛ token فقط در فایل DPAPI ذخیره می‌شود.

## Build

پیش‌نیاز: Visual Studio 2022 با workload «.NET desktop development» یا .NET 8 SDK روی Windows 10/11.

```powershell
dotnet restore
dotnet build -c Release -p:Platform=x64
```

## Publish win-x64

```powershell
dotnet publish -c Release -r win-x64 --self-contained true /p:PublishSingleFile=true /p:IncludeNativeLibrariesForSelfExtract=true -p:Platform=x64
```

خروجی در `bin\Release\net8.0-windows10.0.19041.0\win-x64\publish` ایجاد می‌شود. فایل `appsettings.json` فقط شامل URL و تنظیمات محلی غیرحساس است.

## نکات استقرار

- `site_url` باید HTTPS باشد؛ HTTP فقط با `development_allow_http=true` برای توسعه پذیرفته می‌شود.
- در محیط production برای فایل اجرایی Code Signing معتبر تنظیم شود.
- ساختار Windows App SDK self-contained است، اما publish نهایی باید روی Windows 10 و Windows 11 آزمایش smoke شود.
- اکشن‌های مستقیم approval و quick reply تنها زمانی اجرا می‌شوند که هم تنظیم سایت اجازه دهد و هم handler متناظر سمت سایت نصب باشد.

## منابع رسمی

- [App notifications برای برنامه‌های .NET](https://learn.microsoft.com/windows/apps/develop/notifications/app-notifications/app-notifications-dotnet)
- [AppNotificationManager.Register](https://learn.microsoft.com/windows/windows-app-sdk/api/winrt/microsoft.windows.appnotifications.appnotificationmanager.register)
