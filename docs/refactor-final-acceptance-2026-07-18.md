# ممیزی نهایی بازطراحی افزایشی — ۱۴۰۵/۰۴/۲۷

## نتیجه

هر ۳۲ معیار پذیرش متن اصلی با کد، تست قراردادی و در موارد دیتابیس‌محور با اجرای واقعی روی MariaDB تازه بررسی شد. این گزارش جایگزین تست نیست؛ فقط مسیر شواهد اجراشده را ثبت می‌کند.

وضعیت بهره‌برداری این checkout هنوز «نصب‌نشده» است: `install.lock` وجود ندارد و تنظیمات host/name/user دیتابیس خالی است. بنابراین پذیرش پیاده‌سازی قبول شده، اما فعال‌سازی خود سایت تا ثبت اطلاعات واقعی دیتابیس و حساب مدیر در `install.php` باز می‌ماند.

پس از ثبت این وضعیت، مسیر نصب نیز end-to-end روی MariaDB موقت و مرورگر واقعی آزمایش شد؛ checkout بعد از آزمون عمداً به وضعیت نصب‌نشده بازگردانده شد تا اطلاعات آزمایشی جای اطلاعات واقعی کاربر را نگیرد.

## ماتریس معیارهای پذیرش

| # | معیار | شاهد معتبر | نتیجه |
|---:|---|---|---|
| 1 | موتور مشترک تنظیمات داشبوردها | `dashboard_preferences_contract_test.php`، `dashboard_preferences_module_integration_test.php`، `canonical_manager_dashboard_integration_test.php` | قبول |
| 2 | داده داشبورد از Import/View/Formula | تست‌های Formula، مرکز ایمپورت، داشبورد Canonical و Reporting Views | قبول |
| 3 | حذف ویرایش JSON از UI | `configuration_ui_contract_test.php` و قراردادهای Action، گزارش روزانه و گزارش مدیریتی | قبول |
| 4 | Formula تعاملی و نسخه‌دار | `formula_engine_contract_test.php` و `formula_module_integration_test.php` | قبول |
| 5 | ورودی تاریخ شمسی مشترک | `app_date_ui_contract_test.ps1` تمام ورودی‌های تاریخ در admin/employee/views را اسکن کرد | قبول |
| 6 | ذخیره ISO/Gregorian | `app_date_contract_test.php` و `app_date_module_integration_test.php` | قبول |
| 7 | ورود نمونه فروش و خرید | `provided_samples_acceptance_test.php`، تست‌های integration فروش و خرید و پذیرش فایل واقعی بزرگ | قبول |
| 8 | موجودی به‌صورت snapshot | `inventory_import_integration_test.php` و قرارداد مرکز ایمپورت | قبول |
| 9 | worksheet با dimension متورم بدون اتمام حافظه | قرارداد crop مستقل از dimension و تست استریم؛ اجرای واقعی با سقف 128MB و اوج 22MB | قبول |
| 10 | عدم تشخیص اشتباه حضور و غیاب به‌عنوان موجودی | `provided_samples_acceptance_test.php` و `unified_import_center_contract_test.php` | قبول |
| 11 | نگاشت خودکار و fallback پیشرفته | قرارداد مرکز ایمپورت و محدودیت منوی نگاشت پیشرفته به Super Admin | قبول |
| 12 | ویزیتور از کاربران و تخصیص سازمانی | `user_organization_contract_test.php` و integration دیتابیس | قبول |
| 13 | کد سیستم کارا | تست‌های ساختار کاربران و ورود حضور و غیاب | قبول |
| 14 | انتخاب لاین از فهرست مرکزی | تست‌های ساختار کاربران و scope سرپرست | قبول |
| 15 | ضرایب صنف؛ ورود دستی و Excel | قرارداد و integration برنامه‌ریزی فروش | قبول |
| 16 | اولویت کالا؛ ورود Excel | قرارداد و integration برنامه‌ریزی فروش | قبول |
| 17 | جمع تارگت لاین از تارگت ویزیتور/کالا | `sales_planning_schema_integration_test.php` | قبول |
| 18 | تبدیل Sales Script به قالب اقدام عمومی | قرارداد Action Hub و `legacy_action_template_backfill_integration_test.php` | قبول |
| 19 | اقدام مشترک برای کاربران و ماژول‌ها | قرارداد و integration دیتابیس Action Hub | قبول |
| 20 | اقدام سرپرست از Action Hub مشترک | قرارداد Supervisor Action Hub، integration داده قدیمی و تست scope | قبول |
| 21 | گزارش کار روزانه برای کاربران مجاز | قرارداد و integration گزارش روزانه | قبول |
| 22 | ساخت اقدام لینک‌شده از گزارش | قراردادهای گزارش روزانه/مدیریتی و integration آن‌ها | قبول |
| 23 | قواعد وضعیت حضور و غیاب | `hr_attendance_status_integration_test.php` | قبول |
| 24 | حضور روز تعطیل با ورود و خروج | همان integration وضعیت حضور و غیاب | قبول |
| 25 | نامه با تاریخ شمسی و ویرایشگر امن | قرارداد Letter، integration schema و تست sanitizer HTML | قبول |
| 26 | Quick-add پلنر از داشبورد | قرارداد یکپارچگی dashboard/planner و پذیرش مرورگر نقش‌ها | قبول |
| 27 | باقی‌ماندن تسک شروع‌شده در فهرست روز | قرارداد/Integration پلنر و پذیرش مرورگر | قبول |
| 28 | Planner فشرده و موبایل‌محور | قرارداد Compact UI، قرارداد Planner و پذیرش مرورگر | قبول |
| 29 | نشانگر فشرده Sobhan AI در header | `sobhan_ai_header_status_contract_test.php` | قبول |
| 30 | Viewهای گزارش و scope صحیح | قرارداد و integration Reporting Views، تست supervisor scope و داشبورد Canonical | قبول |
| 31 | حفظ داده تاریخی | migrationهای افزایشی/idempotent، اجرای دوباره repair روی DB تازه، نبود عملیات مخرب و عدم اجرای migration روی دیتابیس عملیاتی | قبول |
| 32 | عدم نمایش خطای خام در UI | `ui_error_sanitization_contract_test.php` و بررسی مرورگر بدون خطای console در مسیرهای پذیرش | قبول |

## پذیرش فایل واقعی بزرگ

- فایل: `145-140504271026.xlsx`
- اندازه: 19,652,710 بایت
- ردیف‌های populated: 44,150 شامل header
- ردیف‌های staging: 44,149
- ردیف‌های valid: 44,149
- ردیف‌های نهایی: 44,149
- وضعیت Batch: `committed / activated / is_active_reference=1`
- شمارش `vw_active_sales_aggregate_rows`: 44,149
- اوج حافظه PHP: 22MB با `memory_limit=128M`
- ورود دوباره همان محتوا با نام متفاوت: مسدود و بدون Batch یا فایل موقت اضافه

## کنترل‌های نهایی اجراشده

- lint همه 224 فایل PHP تغییرکرده: قبول
- `git diff --check`: قبول
- استریم XLSX، قالب‌های Import و نمونه‌های ارائه‌شده: قبول
- integration فروش، خرید، موجودی، حضور و غیاب و برنامه‌ریزی فروش روی DBهای تازه: قبول
- integration نصب تازه، seed registry و OKR: قبول
- integration تاریخ، داشبورد، Formula، Action Hub، گزارش‌ها، نامه، Planner، View و ساختار کاربران: قبول
- نصب واقعی از فرم دارای CSRF با host/port مستقل: قبول؛ ایجاد schema و seedها حدود 9 ثانیه
- ورود حساب مدیر ساخته‌شده و نمایش داشبورد: قبول؛ console مرورگر صفر خطا/هشدار
- دانلود مرورگری قالب همه منابع: قبول؛ workbook شامل 7 شیت منبع و یک شیت راهنما
- قفل نصب مجدد: `install.php` پس از نصب پاسخ HTTP 403 داد
- مرکز Import در viewportهای 929x929 و 390x844 بدون overflow افقی صفحه و با console پاک بررسی شد؛ tab و table فقط در container خود scroll می‌شوند

## داده پایه نصب end-to-end

- کاربران مدیر: 1
- ماژول‌ها: 148
- دوره‌های سیستمی: 53
- نوع اقدام عمومی: 1
- تنظیمات ویجت داشبورد: 34
- لاین‌های فروش: 4
- آزمون‌های سازمانی: 20
- فروش، خرید، موجودی و تردد ساختگی: همگی 0

## محدودیت اجرای این ممیزی

هیچ deploy یا migration روی دیتابیس عملیاتی انجام نشد. اتصال واقعی سرویس بیرونی Sobhan AI نیز در این ممیزی فراخوانی نشد؛ رفتار نشانگر، timeout، cache و پاک‌سازی خطا با قرارداد کد بررسی شد. پذیرش مرورگر نقش‌محور برای Action Hub، گزارش روزانه و Planner اجرا شده است؛ سایر صفحه‌ها با قرارداد UI و integration دیتابیس بررسی شدند.

فرم نصب CSRF مستقل دارد، خطای خام PDO را نمایش نمی‌دهد و secret اتصال را در `config/local.php` gitignored ذخیره می‌کند. قالب tracked یعنی `config/config.php` بدون secret باقی می‌ماند.

## Rollback

تغییرها افزایشی هستند. برای بازگشت کد، فایل‌های همین فاز باید با بازبینی انسانی revert شوند. جدول، ستون یا داده‌ای حذف نشده است و Batchهای قبلی تا پیش از فعال‌شدن Batch جدید دست‌نخورده می‌مانند.
