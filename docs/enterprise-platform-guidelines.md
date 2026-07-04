# قانون اساسی پلتفرم سازمانی سبحان

## جایگاه سند

این سامانه یک پلتفرم داخلی سازمانی و **نه MVP** است. مبنای فنی آن PHP 8.1، MySQL، صفحات server-rendered و استقرار مستقیم روی DirectAdmin/cPanel است. این سند برای تمام تغییرات آینده الزام‌آور است؛ هر استثنا باید در یک task مستقل، با دلیل، تحلیل اثر و تأیید انسانی ثبت شود.

## اصول تغییر

- هیچ صفحه یا قابلیت مستقلی بدون تعیین ارتباط آن با هویت کاربر، ساختار سازمانی، مجوز، داده، اعلان، گزارش و audit ساخته نشود.
- ماژول، route، فایل، جدول، ستون یا داده موجود حذف یا تغییرنام داده نشود و بازنویسی کل پروژه ممنوع است.
- Laravel، Symfony، Composer dependency، backend مبتنی بر Node.js، React SPA، React Router و Redux افزوده نشود.
- تغییر باید کوچک، افزایشی، سازگار با گذشته و قابل review باشد و از `Auth`، `Database`، `Response`، CSRF، flash، layout مشترک مدیریت، `lib/admin_menu.php`، service/repositoryهای موجود و تم فارسی RTL استفاده کند.
- dependency جدید فقط در task صریح، با توجیه، ارزیابی hosting و تأیید انسانی مجاز است. merge و deployment خودکار ممنوع است.

## داده و migration

- اجرای `DROP`، `TRUNCATE`، destructive `ALTER`، rename جدول/ستون یا حذف داده ممنوع است.
- migrationها idempotent هستند: جدول با `CREATE TABLE IF NOT EXISTS` و ستون فقط پس از `Database::columnExists()` با `ALTER TABLE ... ADD` ایجاد می‌شود.
- schema نصب تازه در `database/schema.sql` و repair افزایشی در مسیر موجود `Database::repair()`/bootstrap متناظر نگهداری شود.
- رکوردها، کلیدها و permissionهای موجود معتبر باقی بمانند. seedها تکرارپذیر و دارای کلید پایدار/بررسی وجود باشند.
- هر تغییر schema باید migration note، backup prerequisite و rollback غیرمخرب داشته باشد؛ rollback پیش‌فرض کد را برمی‌گرداند و داده افزوده‌شده را نگه می‌دارد.

## امنیت، خطا و دامنه دسترسی

- هر POST یا عملیات هم‌ارز write باید method را بررسی و `Auth::verifyCsrf()` را اجرا کند. تمام writeهای SQL باید prepared statement باشند.
- احراز هویت و permission با `Auth` و دامنه رکورد با `OrgAccess` کنترل شود. صرف مخفی‌کردن دکمه مجوز محسوب نمی‌شود.
- هر فهرست، export، count، dashboard، attachment و endpoint باید در query/service با دامنه مجاز محدود شود؛ کنترل فقط در UI کافی نیست.
- کارمند فقط داده خود را می‌بیند. مدیر فقط تیم، خط یا واحد مجاز خود را می‌بیند. HR/Admin/Super Admin فقط با permission صریح دسترسی گسترده دارند.
- ورودی validate، خروجی HTML با `e()` escape و فایل با قواعد upload موجود بررسی شود. IDOR، SQL injection، XSS، CSRF، افشای secret و path traversal در review کنترل شود.
- PHP/SQL خام، stack trace، مسیر Windows/host، command، API key، token یا exception فنی در UI/API user-facing نمایش داده نشود. پیام کاربر فارسی، تمیز و عملی باشد؛ جزئیات فنی با context امن و بدون secret در log داخلی ثبت شود.

## قرارداد واحد JSON

endpointهای جدید از ابتدا و endpointهای قدیمی در Phase 2 به‌صورت سازگار به envelope زیر منتقل می‌شوند. HTTP status متناسب نیز الزامی است.

```json
{
  "success": true,
  "ok": true,
  "data": {},
  "meta": {},
  "message": "",
  "error": null
}
```

```json
{
  "success": false,
  "ok": false,
  "data": null,
  "meta": {},
  "message": "پیام قابل نمایش برای کاربر",
  "error": "CONTROLLED_ERROR_CODE"
}
```

`error` فقط code کنترل‌شده و غیرحساس است. migration قراردادهای قدیمی باید با inventory مصرف‌کننده‌ها، regression test و در صورت نیاز دوره سازگاری انجام شود.

## معماری رسمی هدف

```text
PHP Admin Panel / MySQL
├── Admin Pages
├── Employee Pages
├── Manager Pages
├── AJAX/API Layer
│   ├── /admin/ajax/...
│   ├── /admin/actions/...
│   ├── /api/messenger/...
│   ├── /api/notify/...
│   ├── /api/file-backup/...
│   ├── /api/ui/...
│   └── /api/sync/...
├── Services
│   ├── PersonalPlannerService / WorkPlannerService / TicketService
│   ├── NotificationService / PushNotificationService / NotificationHubService
│   ├── Messenger services / TestScoringService
│   ├── ManagerDashboardCalculator / Payroll services
│   ├── FileBackupService / AI/API services
├── Repositories
│   ├── HR / Attendance / Payroll repositories
│   ├── ManagementReportsRepository
│   ├── ManagementMeetings repositories
│   └── domain repositories
├── Workers
│   ├── messenger_worker.php
│   ├── notification_worker.php
│   ├── planner_reminder_worker.php
│   ├── file_backup_worker.php
│   └── sync_worker.php
└── External Systems
    ├── Windows Notification Hub
    ├── Windows AI Server / FastAPI
    ├── Reporting Database
    └── ERP
```

این نمودار target است، نه ادعای موجودبودن همه اجزا. جزء جدید فقط در phase مصوب و با reuse جزء هم‌نام موجود ساخته می‌شود.

## سیاست frontend

مسیر رسمی progressive enhancement است: PHP فعلی ← Vanilla JS/Fetch ← Tabulator برای جدول‌های سنگین و flatpickr برای تاریخ/زمان ← React Island فقط برای chart داشبورد، تقویم planner، popup هوش مصنوعی یا editor غنی جلسه/قانون. full SPA shell، build سنگین برای صفحه ساده، React Router و Redux بدون درخواست صریح ممنوع است. تم مشترک RTL، دسترس‌پذیری، responsive بودن و appearance هر کاربر حفظ می‌شود.

- جدول سنگین فقط پس از اعمال permission و `OrgAccess` در PHP مجاز به enhancement است؛ جستجو یا export سمت کاربر نباید سطر جدید یا خارج از scope دریافت کند.
- جدول و ورودی تاریخ enhanced باید در نبود JavaScript یا widget اختیاری fallback قابل استفاده PHP/native داشته باشد.
- UI باید متغیرهای `--theme-*` را مصرف کند. accent/neon تنظیم per-user است و کنتراست متن و کنترل نباید برای یک پس‌زمینه خاص hardcode شود.
- React Island بدون business case محدود و build موجود مجاز نیست و نباید routing، global state یا جایگزینی صفحه PHP ایجاد کند.

## تعریف Done

هر task باید پیش از تغییر impact analysis و پس از آن changed files، مستندات، تست هدفمند، نتیجه/محدودیت، risk، security، migration و rollback notes و PR پیشنهادی داشته باشد. تغییر بدون review انسانی آماده production نیست.

## سیاست KPI و ارزیابی سازمانی

- معیار KPI از template و ساختار سازمانی خوانده می‌شود و در UI hardcode نمی‌شود. نتیجه فردی، گزارش تجمیعی و export همگی باید `OrgAccess` و permission صریح را رعایت کنند.
- پرسش‌های seedشده باید فارسی، اصیل، شغلی، غیرکلینیکی و بدون ادعای تشخیص باشند. ابزارهای تیپ‌شناسی مانند MBTI/DISC صرفاً توسعه‌ای‌اند و نباید به‌تنهایی مبنای استخدام، اخراج یا تنبیه قرار گیرند.
- پاسخ خام ارزیابی محرمانه است؛ ثبت نهایی باید atomic، غیرقابل تکرار بدون `allow_retake` و دارای تاریخچه نتیجه باشد. scoring فقط از `TestScoringService` عبور می‌کند.
