# قرارداد ویوهای گزارش‌گیری — فاز ۱۶

## هدف

ویوهای گزارش‌گیری پس از بازطراحی Import Center یک نام‌گذاری ثابت دارند و داده‌های واردشده را فقط از Batch فعال و `committed` می‌خوانند. داده‌های دستی فعال، مانند تارگت‌های دستی، همچنان حفظ می‌شوند.

## منابع استاندارد

- فروش: `vw_sales_active` و ویوهای دوره، ویزیتور، سرپرست، مدیر، لاین، مشتری، محصول و برند
- خرید: `vw_purchase_active` و `vw_purchase_by_supplier`
- موجودی: `vw_inventory_current` و `vw_inventory_by_product`
- تارگت و پورسانت: `vw_target_achievement`، `vw_target_by_visitor`، `vw_target_by_line` و `vw_commission_inputs`
- عملیات سازمانی: `vw_attendance_period_summary`، `vw_action_workload` و `vw_daily_report_completion`

ویوهای تجمیعی فروش، ابعاد ویزیتور، سرپرست، مدیر و لاین را تا حد لازم نگه می‌دارند. این تصمیم اجازه می‌دهد محدودسازی دسترسی پیش از تجمیع نهایی انجام شود.

## محدودسازی دسترسی

مصرف مستقیم نام ویو از ورودی کاربر مجاز نیست. `ReportingViewRepository` فقط نام‌های ثبت‌شده را می‌پذیرد و سپس محدوده را در سمت سرور اعمال می‌کند:

- مدیرعامل و مدیران سامانه: همه داده‌ها
- مدیر فروش و سرپرست: کاربران و لاین‌های قابل دسترس از `OrgAccess`
- کارمند: فقط رکوردهای متعلق به شناسه خود
- منابع انسانی و مدیر واحد: کاربران واقع در محدوده سازمانی مجاز
- موجودی سراسری: فقط نقش مدیریتی

فیلترهای رابط کاربری جایگزین این کنترل نیستند.

## اتصال داشبورد مدیر فروش

داشبورد مدیر فروش در حالت پیش‌فرض از `CanonicalSalesDashboardService` استفاده می‌کند. مسیر محاسبه این حالت به‌صورت زیر است:

`Batch فعال و committed → Reporting Views → ReportingViewRepository با دامنه سازمانی → ManagerDashboardCalculator → FormulaRuntime`

داده فروش از `vw_sales_by_period`، تارگت و تحقق از `vw_target_by_visitor` و جزئیات برند از `vw_target_achievement` خوانده می‌شود. داشبورد فقط آخرین دوره دارای داده قابل مشاهده برای کاربر را انتخاب می‌کند و Batch غیرفعال یا کاربر خارج از دامنه سازمانی در جمع‌ها وارد نمی‌شود.

گزارش‌های قدیمی `manager_dashboard_reports` و جداول وابسته حذف نشده‌اند. انتخاب صریح یکی از گزارش‌های قدیمی در فهرست دوره، همان مسیر سازگار قبلی را نمایش می‌دهد. ورود دستی قدیمی نیز همچنان فقط در حالت محدود Super Admin در دسترس است.

خروجی‌های قدیمی Excel و تصویر برای نمای canonical نمایش داده نمی‌شوند، چون endpointهای آنها بر اساس `report_id` قدیمی کار می‌کنند. این محدودیت از تولید فایل خالی یا گمراه‌کننده جلوگیری می‌کند.

## مهاجرت و سازگاری

`ReportingViewsModule::repair()` با `CREATE OR REPLACE VIEW` اجرا می‌شود، داده‌ای حذف نمی‌کند و پس از آماده‌شدن جداول Import، تارگت، حضور و غیاب، Action Hub و گزارش روزانه فراخوانی می‌شود. نام‌های قدیمی برای سازگاری ماژول‌های موجود حفظ شده‌اند.

## اعتبارسنجی

```text
php tests/reporting_views_phase16_contract_test.php
php tests/reporting_views_phase16_schema_integration_test.php
php tests/canonical_manager_dashboard_contract_test.php
php tests/canonical_manager_dashboard_integration_test.php
php -l core/ReportingViewsModule.php
php -l services/ReportingViewRepository.php
php -l services/CanonicalSalesDashboardService.php
```

تست‌های یکپارچه به MySQL/MariaDB آزمایشی و متغیر `SOBHAN_TEST_DSN` نیاز دارند. تست adapter مدیر فروش یک Batch فعال، یک Batch غیرفعال، کاربر مجاز و خارج از دامنه، تارگت و فرمول منتشرشده می‌سازد و عدم نشت داده و نتیجه عددی فرمول را کنترل می‌کند. تطبیق عددی با فایل‌های واقعی باید در همان پایگاه آزمایشی و با Batchهای نمونه انجام شود.
