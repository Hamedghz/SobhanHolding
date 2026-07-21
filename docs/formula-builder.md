# فرمول‌ساز تصویری

## هدف

فرمول‌ساز فاز ۴ یک موتور مشترک، نسخه‌دار و قابل آزمون برای قواعد پورسانت، ضریب کاهنده، تارگت، پاداش برند، پوشش مشتری، مرجوعی، ۳-۳-۳، KPI، کارکرد، گزارشات مدیریتی و بودجه آفر است.

کاربر فقط از کنترل‌های فرم استفاده می‌کند. JSON داخلی، SQL، کد PHP و expression اجرایی در UI قابل ورود نیست.

## مسیرها

- `/admin/formula-builder.php`: ساخت نسخه Draft، مشاهده نسخه‌ها، انتشار و rollback
- `/admin/formula-test.php`: آزمون بدون اثر روی داده
- مسیر قدیمی `/admin/sales-offer-formula-settings.php` به فرمول‌ساز هدایت می‌شود.

## مدل داده

- `formula_definitions`: هویت پایدار فرمول
- `formula_versions`: snapshot کامل هر نسخه، بازه اعتبار، وضعیت و اولویت
- `formula_filters`: فیلترهای ساختاریافته
- `formula_dependencies`: وابستگی بین فرمول‌ها
- `formula_audit_logs`: رخدادهای ساخت، انتشار و بازیابی
- `formula_test_runs`: ورودی، trace و نتیجه آزمون

تغییر schema کاملاً افزایشی است و هیچ جدول یا داده قدیمی حذف نمی‌شود.

## چرخه نسخه

1. هر ذخیره یک نسخه `draft` جدید می‌سازد.
2. Draft روی محاسبات فعال اثر ندارد.
3. پیش از انتشار، تداخل بازه/منبع/شاخص/اولویت و وابستگی دوری کنترل می‌شود.
4. نسخه فعال قبلی به `retired` می‌رود و immutable باقی می‌ماند.
5. rollback نسخه قدیمی را تغییر نمی‌دهد؛ یک Draft جدید از snapshot آن می‌سازد.

## DSL ساختاریافته

قاعده داخلی از انتخاب‌های allowlist ساخته می‌شود:

`source -> filters -> metric -> aggregation -> condition -> result`

تجمیع‌های مجاز: `SUM`, `COUNT`, `COUNT_DISTINCT`, `AVERAGE`, `MIN`, `MAX`, `PERCENT`, `RATIO`.

عملگرهای مجاز: `=`, `!=`, `>`, `>=`, `<`, `<=`, `BETWEEN`, `IN`, `NOT_IN`.

هیچ `eval()` و هیچ SQL ساخته‌شده از متن کاربر وجود ندارد.

## آزمون و trace

پنل تست می‌تواند دوره، کاربر، لاین، کالا، مشتری و نسخه فرمول را دریافت کند. برای منبع نمونه، مقادیر عددی کنترل‌شده وارد می‌شوند؛ برای منبع دیتابیس فقط جدول و ستون ثبت‌شده در `FormulaSourceRegistry` خوانده می‌شود و حداکثر ۱۰۰۰ ردیف وارد تست می‌گردد.

خروجی شامل تعداد ورودی، تعداد پس از فیلتر، مقدار تجمیع، نتیجه شرط و نتیجه نهایی است.

## سازگاری

فرمول‌های قدیمی در `site_settings`, `manager_dashboard`, `payroll_fields`, `commission_formula_settings` و `sales_offer_formula_settings` حذف نشده‌اند. اتصال مصرف‌کننده‌ها باید به‌صورت adapter و با fallback قدیمی انجام شود تا تأیید داده واقعی تکمیل گردد.

Adapterهای افزوده‌شده:

- بودجه آفر: نسخه فعال `offer_budget_provisional` نتیجه بودجه را می‌دهد؛ در نبود نسخه فعال، `provisional_v1` قبلی اجرا می‌شود.
- داشبورد مدیر فروش: کلیدهای `manager_penalty`, `manager_commission`, `manager_customer_coverage`, `manager_brand_bonus` در صورت انتشار نتیجه قدیمی را override می‌کنند؛ در غیر این صورت محاسبه قبلی بدون تغییر ادامه دارد.
- حقوق و دستمزد: `payroll_fields.formula_definition_id` یک فرمول منتشرشده از دسته `payroll` را انتخاب می‌کند؛ فرمول متنی قدیمی فقط fallback خواندنی است و دیگر از UI ویرایش نمی‌شود. اگر فیلد به فرمول‌ساز متصل باشد اما نسخه فعال معتبری برای تاریخ دوره وجود نداشته باشد، ثبت کل batch متوقف و تراکنش rollback می‌شود تا مبلغ صفرِ خاموش تولید نشود.

برای چهار adapter مدیر فروش (`manager_penalty`, `manager_commission`, `manager_customer_coverage`, `manager_brand_bonus`) Draft سازگار به‌صورت idempotent ساخته می‌شود. این Draftها تا انتشار بی‌اثرند و اگر بدون تغییر منتشر شوند همان خروجی محاسبه قدیمی را حفظ می‌کنند.

## محدودیت محیط محلی

نسخه محلی repository فاقد تنظیم اتصال DB و install lock است. آزمون schema، versioning، conflict، cycle، test trace و rollback روی MariaDB موقت انجام می‌شود؛ تست زنده با داده واقعی پس از نصب/اتصال و Import معتبر لازم است.
