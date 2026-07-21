# سامانه تاریخ جلالی و دوره‌های گزارش

## هدف

این تغییر یک قرارداد مشترک برای ورود و نمایش تاریخ در پنل فارسی ایجاد می‌کند. کاربر تاریخ را به‌صورت جلالی می‌بیند و وارد می‌کند، اما مقدار ذخیره‌شده در MySQL همچنان `DATE` یا `DATETIME` میلادی استاندارد است. هیچ تاریخ جلالی برای مرتب‌سازی یا مقایسه در دیتابیس ذخیره نمی‌شود.

## اجزای اصلی

- `lib/AppDate.php`: تبدیل، اعتبارسنجی، نرمال‌سازی ارقام فارسی و عربی، فرمت نمایش و محاسبه مرز دوره‌ها.
- `core/AppDateModule.php`: ساخت و تکمیل تکرارپذیر جدول `system_periods`.
- `core/Response.php`: دو helper مشترک `app_date_input()` و `app_period_select()`.
- `assets/vendor/jalalidatepicker/`: نسخه محلی `@majidh1/jalalidatepicker` با مجوز MIT.
- `assets/vendor/motion/`: نسخه محلی Motion با مجوز MIT؛ این بسته ادامه رسمی مسیر Framer Motion برای JavaScript است.
- `assets/js/app-jalali-date.js`: راه‌اندازی ورودی‌های ثابت و داینامیک، دوره سفارشی، min/max و تاریخ‌های غیرفعال.
- `assets/js/app-motion.js`: موشن کوتاه و غیرمزاحم برای تقویم، بازه سفارشی و کارت‌ها.

## قرارداد ورودی

برای یک تاریخ معمولی:

```php
<?= app_date_input('due_date', $row['due_date'] ?? null, ['required' => true]) ?>
```

برای تاریخ و ساعت:

```php
<?= app_date_input('reminder_at', $row['reminder_at'] ?? null, ['datetime' => true]) ?>
```

گزینه‌های پشتیبانی‌شده شامل `min`، `max`، `disabled_dates`، `datetime`، `month`، `range`، `required`، `readonly` و `disabled` است.

در لایه سرور، ورودی باید با `AppDate::toGregorian()` یا `AppDate::toGregorianDateTime()` تبدیل شود. helperهای `app_date_to_iso()` و `app_datetime_to_iso()` برای routeهای کلاسیک در دسترس‌اند.

## دوره‌های مرکزی

جدول `system_periods` دوره‌های روزانه، هفتگی، ماهانه، فصلی، شش‌ماهه، سالانه و سفارشی را نگه می‌دارد. `Database::repair()` در هر اجرا:

1. جدول را در صورت نبودن می‌سازد.
2. ۵۳ دوره سیستمی جاری و اخیر را با کلید پایدار upsert می‌کند.
3. دوره‌های سفارشی کاربران یا ماژول‌ها را حذف یا بازنویسی نمی‌کند.

گزارش‌های مدیریتی و گزارش‌های فروش از selector مشترک دوره استفاده می‌کنند. بازه دستی برای سازگاری و گزارش‌های خاص به‌عنوان گزینه پیشرفته باقی مانده است.

## UI/UX و موشن

چیدمان ورودی و presetهای دوره از الگوهای date picker و range preset در 21st.dev، با ترجمه به HTML/PHP بومی پروژه الهام گرفته است. هیچ React، Tailwind یا وابستگی build به پروژه اضافه نشده است. طراحی نهایی مطابق الگوی Data-Dense Dashboard در UI UX Pro Max، RTL و mobile-first است.

موشن‌ها با Motion اجرا می‌شوند، کوتاه‌اند و در حالت `prefers-reduced-motion` غیرفعال می‌شوند. عملکرد فرم و انتخاب تاریخ به اجرای انیمیشن وابسته نیست.

## مهاجرت و دیتابیس

- migration مخرب وجود ندارد.
- `DROP` یا `TRUNCATE` در کد runtime اجرا نمی‌شود.
- DDL نصب تازه در `database/schema.sql` نیز ثبت شده است.
- سایت‌های موجود با اولین اجرای `Database::repair()` جدول و دوره‌ها را دریافت می‌کنند.
- rollback منطقی: حذف ارجاع assetها و helperهای جدید از UI. برای حفظ داده و سازگاری، جدول `system_periods` لازم نیست حذف شود.

## اعتبارسنجی

- `php tests/app_date_contract_test.php`
- `powershell -File tests/app_date_ui_contract_test.ps1`
- تست دیتابیس ایزوله با `tests/app_date_module_integration_test.php` و متغیرهای `SOBHAN_TEST_DSN`، `SOBHAN_TEST_DB_USER` و `SOBHAN_TEST_DB_PASSWORD`
- `php -l` برای تمام فایل‌های PHP تغییرکرده
- `node --check` برای فایل‌های JavaScript مشترک

## محدودیت

تست مرورگر زنده به یک نصب محلی دارای `config.php`، دیتابیس آماده و کاربر قابل ورود نیاز دارد. در نبود این موارد، تست‌های قرارداد، syntax و دیتابیس ایزوله جایگزین می‌شوند و نباید به‌عنوان تأیید محیط production گزارش شوند.
