# استعلام بودجه آفر

این ماژول برای ثبت و بررسی بودجه مقدماتی آفر توسط مدیران فروش است. محاسبه فعلی `provisional_v1` بوده و جایگزین فرمول نهایی Excel نیست.

## استفاده و دسترسی

- کاربران دارای مجوز `sales_manager.offer_budget.manage` استعلام ثبت و سوابق مجاز را مشاهده می‌کنند.
- مدیر فروش فقط رکورد خود یا لاین خودش را می‌بیند و فقط پیش‌نویس خودش را ویرایش می‌کند.
- admin و super_admin همه رکوردها را می‌بینند و نتیجه بررسی را ثبت می‌کنند.
- تنظیمات نرخ پیش‌فرض فقط برای admin و super_admin است. نرخ هر استعلام صریحاً در فرم و snapshot ذخیره می‌شود.

## محاسبه مقدماتی

`purchase_base = purchase_price * requested_offer_qty`

`sales_ratio = sold_amount / purchase_base` (اگر پایه خرید بزرگ‌تر از صفر باشد)

`provisional_budget = purchase_base * provisional_offer_rate`

نسخه فرمول، ورودی‌ها، خروجی‌ها و زمان محاسبه در `formula_snapshot_json` نگهداری می‌شود.

## نصب، اعتبارسنجی و بازگشت

فایل `install/sales_offer_budget_repair.php` را با مجوز ابزارهای سیستم اجرا کنید. اجرای مکرر امن و idempotent است. تست قراردادی: `php tests/sales_offer_budget_contract_test.php`.

ماژول destructive migration ندارد. rollback کد با بازگرداندن فایل‌های این تغییر انجام می‌شود؛ جداول و داده‌ها برای جلوگیری از حذف اطلاعات باقی می‌مانند و حذف آن‌ها فقط با تأیید صریح انسانی مجاز است.

## محدودیت

اتصال خودکار به `sales_aggregate_rows` اجباری نیست و مقادیر فروش فعلاً دستی هستند. فرمول نهایی پس از دریافت فایل Excel افزوده می‌شود.
