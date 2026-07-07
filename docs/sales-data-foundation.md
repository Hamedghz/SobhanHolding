# زیرساخت پایه مدیریت داده فروش

## هدف و دامنه Stage 01

این مرحله فقط schema امن، permissionها، منوی مدیریت، repository خواندنی و صفحات placeholder را اضافه می‌کند. برخلاف ترتیب پیشنهادی اولیه در master plan، اجرای این foundation با prompt صریح Stage 01 مجاز شده است؛ با این حال قرارداد header، natural key و قواعد کسب‌وکار همچنان باید پیش از فعال‌سازی import تکمیل شود.

در این مرحله Excel/CSV خوانده نمی‌شود، formula اجرا نمی‌شود، اتصال SobhanAI برقرار نمی‌شود، View گزارش‌گیری ساخته نمی‌شود و هیچ dashboard فعلی به جدول‌های جدید منتقل نشده است.

## اجزای ایجادشده

- `core/SalesDataSchema.php`: repair افزایشی ۱۳ جدول و seed هشت permission.
- `core/SalesDataRepository.php`: queryهای read-only برای batch، خطا و mapping.
- `install/sales_data_foundation_seed.php`: اجرای CLI یا اجرای وبِ محدود به مدیر سیستم همراه CSRF.
- `database/schema.sql`: mirror ساختار برای نصب تازه.
- `lib/admin_menu.php`: گروه مستقل «مدیریت داده فروش» با ۱۱ زیرمنو.
- چهار صفحه placeholder در `admin/sales-data-*.php` با layout مشترک و empty state فارسی.

## جدول‌ها

کنترل import شامل `sales_import_batches`, `sales_import_errors`, `sales_import_column_mappings` و `staging_sales_data` است. جدول‌های مقصد خالی شامل فروش تجمیعی، موجودی، تیم فروش، ضریب صنف، اولویت کالا، هدف فروش، تنظیمات نسخه‌ای فرمول، run محاسبه و نتیجه محاسبه هستند.

تمام جدول‌های مقصد `raw_json` دارند. staging به batch وابسته است و حذف cascade تعریف نشده است. در این مرحله برای factهای خالی unique business key تحمیل نشده، زیرا natural keyها باید با نمونه‌های واقعی تأیید شوند؛ `source_unique_key` و index آن برای مرحله بعد آماده است.

## permissionها

- `sales_data_view`
- `sales_data_import`
- `sales_data_manage_mapping`
- `sales_data_view_errors`
- `sales_data_sync_ai`
- `sales_data_manage_formulas`
- `sales_data_view_reports`
- `sales_data_run_commission`

کلیدها با upsert بدون تغییر title/status/accessهای موجود ثبت می‌شوند. کاربران admin و super_admin طبق رفتار فعلی `Auth` دسترسی دارند؛ سایر کاربران فقط با رکورد صریح `user_permissions` مجازند. هیچ permission کاربری به‌صورت خودکار اعطا نمی‌شود.

## اجرا و اعتبارسنجی

از CLI:

```bash
php install/sales_data_foundation_seed.php
php install/sales_data_foundation_seed.php
php tests/sales_data_foundation_contract_test.php
```

اجرای دوم باید بدون duplicate و بدون تغییر داده موجود موفق شود. اجرای وب فقط برای کاربری که `Auth::canManageSystemTools()` دارد و با POST/CSRF معتبر مجاز است. خطای فنی در `error_log` ثبت و در UI فقط پیام فارسی عمومی نمایش داده می‌شود.

## امنیت و محدودیت‌ها

- DDL فقط `CREATE TABLE IF NOT EXISTS` است؛ `DROP`, `TRUNCATE`, rename و ALTER مخرب وجود ندارد.
- هیچ upload endpoint یا مسیر فایل عمومی ایجاد نشده است.
- صفحات placeholder عملیات نوشتن ندارند و raw JSON یا raw error نمایش نمی‌دهند.
- repository limitها را عددی و محدود می‌کند و queryهای فعلی ورودی متنی ندارند.
- ستون‌های actor از نوع BIGINT و بدون foreign key به `users` نگه داشته شده‌اند تا با قرارداد درخواست سازگار و در نصب‌های قدیمی backward-compatible باشند.

## migration و rollback

Migration additive است و با `Database::repair()` در bootstrap و با seed مستقل قابل اجراست. rollback عملیاتی این مرحله، حذف آیتم‌های منو/صفحات و متوقف‌کردن readerهای جدید است؛ جدول‌ها یا داده‌ها نباید در rollback خودکار حذف شوند. حذف فیزیکی فقط با طرح جداگانه و تأیید انسانی ممکن است.

## مرحله بعد

پیش از parser یا sync، audit نمونه‌های واقعی و قرارداد canonical انجام شود: aliasهای header، type/unit، natural key، duplicate policy، formula-cell policy، تقویم و cross-source reference. سپس import باید از upload خصوصی به batch → staging → validation → commit تراکنشی عبور کند.
