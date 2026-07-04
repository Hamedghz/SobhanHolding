# گزارش نهایی اعتبارسنجی پلتفرم سازمانی

تاریخ ممیزی: ۱۴۰۵/۰۴/۱۳ (2026-07-04)

## نتیجه اجرایی

Phaseهای 0 تا 11 در working tree دارای پیاده‌سازی و مستندات متناظر هستند و Phase 12 قابلیت جدیدی اضافه نکرد. دوازده contract suite پاس شدند. پذیرش production هنوز **مشروط** است، زیرا PHP native و Docker daemon در این نشست در دسترس نبودند؛ در نتیجه سه تست PHP، اجرای migration برای بار دوم، seed دوباره، HTTP/API احراز‌شده، مرورگر نقش‌محور و restore drill اجرا نشدند.

## پوشش Phaseها

| Phase | دامنه | شاهد اصلی | وضعیت |
|---|---|---|---|
| 0 | قانون اساسی و معماری | اسناد enterprise/module/phases/rollback | مستند |
| 1 | سازمان و scope | `OrgModule`, `OrgAccess`, permission test | contract پاس؛ runtime مسدود |
| 2 | JSON و `/api/ui` | `Response.php`, API contract | کد موجود؛ PHP test این نوبت مسدود |
| 3 | اعلان سازمانی | NotificationService/Hub | contract پاس |
| 4 | Ticket/Messenger | serviceها و membership | هر دو contract پاس |
| 5 | Planner/reminder | planner services/worker | contract پاس |
| 6 | KPI/Assessment | scoring/templates/results | هر دو contract پاس |
| 7 | Attendance/Payroll | repository/pages | attendance contract پاس؛ calculation runtime مسدود؛ payroll static-only |
| 8 | Reports/Governance | repositories/pages | contract پاس |
| 9 | UI progressive enhancement | shared assets/API | contract پاس؛ visual smoke انجام نشد |
| 10 | Pull Sync | sync queue/API/docs | contract پاس؛ live HTTP انجام نشد |
| 11 | File Backup | backup service/API/docs | contract پاس؛ live download انجام نشد |
| 12 | پذیرش | این سند | تکمیل با پذیرش مشروط |

## نتایج تست

### پاس‌شده

- `ticketing_contract_test.ps1`
- `personal_planner_contract_test.ps1`
- `hr_attendance_contract_test.ps1`
- `management_governance_contract_test.ps1`
- `notification_flow_test.ps1`
- `sync_api_contract_test.ps1`
- `aion_theme_contract_test.ps1`
- `file_backup_contract_test.ps1`
- `hr_assessment_contract_test.ps1`
- `kpi_result_safe_load_test.ps1`
- `messenger_contract_test.ps1`
- `ui_modernization_contract_test.ps1`
- critical route-file inventory: 17/17
- destructive operation scan در seedها: 17 فایل، بدون DROP/TRUNCATE/RENAME TABLE
- `git diff --check`: بدون خطای whitespace

### اجرا‌نشده به‌دلیل محیط

| تست | علت | اقدام لازم |
|---|---|---|
| `hr_attendance_calculation_test.php` | PHP native موجود نیست و Docker daemon خاموش است | اجرا با PHP 8.1/8.3 در CI یا staging |
| `permissions_scope_test.php` | همان محدودیت runtime | اجرا با PHP و سپس سناریوی DB نقش‌ها |
| `api_json_contract_test.php` | همان محدودیت runtime | اجرا در CI؛ سپس HTTP 401/403/419 |

`php_delimiter_check.py` روی کل PHP دو هشدار برای `core/LetterModule.php` و `core/CeoDashboardExcel.php` داد. این ابزار parser واقعی PHP نیست و رشته‌های SQL/XML را کامل مدل نمی‌کند؛ تا اجرای `php -l` این دو فایل یک ریسک باز هستند، نه خطای قطعی syntax.

## ماتریس پذیرش

| معیار | وضعیت | شواهد/محدودیت |
|---|---|---|
| admin/employee/manager panel | جزئی | routeهای واقعی موجود؛ login و مرورگر staging اجرا نشد |
| routeهای بحرانی | تأیید ایستا | 17 فایل route موجود؛ crawl کامل HTTP انجام نشد |
| migration غیرمخرب | تأیید ایستا | scan بدون عملیات مخرب؛ اجرای دوباره migration انجام نشد |
| seed idempotent | جزئی | الگوهای `INSERT IGNORE`/وجودسنجی و scan امن؛ اجرای دو مرتبه انجام نشد |
| hierarchy فقط فروش | contract | validator مشترک موجود؛ سناریوی DB واقعی اجرا نشد |
| non-sales بدون supervisor فروش | contract | rule موجود؛ فرم واقعی اجرا نشد |
| employee/manager scope | contract | OrgAccess مرکزی؛ PHP test و IDOR HTTP مسدود |
| ticket/messenger notifications | contract پاس | delivery واقعی push/polling staging لازم است |
| Windows Hub pending/ack/action | contract پاس | device واقعی pair/poll نشده |
| planner reminder | contract پاس | worker زمان‌بندی‌شده واقعی اجرا نشده |
| KPI/assessment | contract پاس | data-volume و workflow مرورگر تست نشده |
| attendance | جزئی | contract پاس؛ calculation PHP اجرا نشده |
| payroll | ایستا | route/repository موجود؛ محاسبه و محرمانگی با fixture واقعی تست نشده |
| reports/governance | contract پاس | attachment و notification live تست نشده |
| AI settings | جزئی | UI masking و server-side use موجود؛ default URL قدیمی نیازمند review است |
| Sync/File Backup APIs | contract پاس | احراز هویت و download live اجرا نشده |
| heavy tables/RTL/theme | contract پاس | visual/mobile/browser QA اجرا نشده |
| raw errors و secret exposure | جزئی | contractها و controlled errors موجود؛ penetration/log review کامل انجام نشده |

## خلاصه تغییرات کل برنامه

تغییرات working tree در حدود 95 مسیر و چند Phase پخش شده‌اند: core module/serviceها، admin/employee pages، `/api/ui`, `/api/notify`, `/api/sync`, `/api/file-backup`, schema/seedها، assets RTL/theme، workerها، contract testها و اسناد. چون working tree از ابتدای Phase 12 پاک و phase-separated نبود، diff فعلی نباید بدون تفکیک و review انسانی به یک commit واحد تبدیل شود.

## ریسک‌های باقی‌مانده

1. **بالا — پذیرش runtime:** migration-twice، seed-twice، restore drill و full HTTP suite اجرا نشده‌اند.
2. **بالا — authorization واقعی:** تست IDOR برای employee/manager/admin با session و داده واقعی لازم است.
3. **بالا — حقوق و داده HR:** fixture واقعی payroll/KPI/assessment و بررسی عدم نشت نیاز است.
4. **متوسط — URL پیش‌فرض legacy:** `core/Database.php` هنوز یک `sobhan_api_base_url` عددی قدیمی دارد؛ پیش از production باید با تنظیم محیطی خالی/مصوب بررسی شود.
5. **متوسط — secret at rest legacy:** کلیدهای Sync/Backup hash هستند، اما `sobhan_api_key` outbound برای مصرف client سرور قابل بازیابی و در `site_settings` نگهداری می‌شود؛ دسترسی DB و backup باید محدود/رمزگذاری شود.
6. **متوسط — lint دو فایل:** LetterModule و CeoDashboardExcel باید با parser واقعی PHP lint شوند.
7. **متوسط — UI:** responsive/RTL/contrast و JavaScript fallback با مرورگر واقعی کامل نشده‌اند.
8. **متوسط — عملیات worker:** cron، retry، duplicate delivery، Windows Hub، Sync و Backup زیر بار واقعی تست نشده‌اند.
9. **فرایندی — working tree بزرگ:** تغییرات باید به PRهای phase-based تفکیک و هر کدام جدا review شوند.

## برنامه پذیرش staging

1. backup کامل DB و uploads؛ ثبت checksum و تست restore در محیط جدا.
2. اجرای `Database::repair()` دو مرتبه و تمام seedها دو مرتبه؛ مقایسه count/keyها قبل و بعد.
3. اجرای `php -l` روی همه فایل‌ها و سه تست PHP مسدودشده با PHP 8.1.
4. اجرای HTTP suite برای 401/403/419، API key/IP allowlist، traversal و raw-error leakage.
5. login با employee، manager، HR، admin و super admin؛ تست direct URL خارج از scope.
6. smoke lifecycle تیکت، پیام‌رسان، planner، KPI، assessment، attendance، payroll، governance و attachment.
7. تست Windows Hub، Sync و File Backup با worker آزمایشی و کلید موقت؛ سپس rotate کلید.
8. desktop/mobile RTL visual QA و ثبت sign-off مالک محصول، امنیت و عملیات.

## rollback

- deploy تا عبور staging متوقف بماند.
- backup پیش از migration الزامی است؛ rollback کد، داده‌های افزوده‌شده را حذف نمی‌کند.
- هیچ DROP/TRUNCATE/rename انجام نشود.
- در incident، worker/APIهای Sync، Backup، AI و Push ابتدا disable و کلیدهای مرتبط rotate شوند.
- restore فقط از backup آزمایش‌شده و با ثبت audit انجام شود.

## تصمیم پذیرش

**پذیرش مهندسی: مشروط. پذیرش production: رد تا تکمیل checklist staging و sign-off انسانی.**
