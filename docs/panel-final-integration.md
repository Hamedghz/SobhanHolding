# یکپارچه‌سازی نهایی پنل سبحان

## دامنه و علت تغییر

این فاز هفت مسیر موجود را بدون افزودن Framework یا Runtime جدید اصلاح می‌کند: بارگذاری قالب KPI پرسنل، اسلایدر صفحه اصلی، SMS، Gmail/Email Hub، مجوزهای کاربران، اعلان مشترک و جست‌وجوی منو. منبع‌های فعلی `Auth`، `Database`، `OrgAccess`، `flash()`، Admin Menu Registry و سرویس‌های Email/SMS حفظ شده‌اند.

## اصلاحات اجراشده

- صفحه امتیازدهی KPI پس از انتخاب پرسنل، فقط قالب‌های فعال و تخصیص‌یافته را از Endpoint مجاز دریافت می‌کند. Endpoint مجوز، Scope پرسنل و CSRF را دوباره کنترل می‌کند. قالب دستکاری‌شده Load نمی‌شود و داده فرم پس از خطای امتیاز حفظ می‌شود.
- `carousel_items` منبع مستقیم صفحه اصلی باقی مانده است. تصویر موبایل، Alt، Target لینک، موقعیت، نوع و بازه انتشار به‌صورت افزایشی اضافه شده‌اند. حذف مخرب با غیرفعال‌سازی جایگزین شده و لینک/مسیر تصویر نامعتبر در خروجی عمومی رد می‌شود.
- ارسال دستی SMS دارای Request Key، قفل کوتاه دیتابیس و کلید یکتا است تا Double Submit دوباره به Provider ارسال نشود. محدودیت ۲۰۰۰ نویسه و شمارش Segment در Backend و UI اعمال می‌شود.
- Email Hub برای OAuth2 از Refresh Token ذخیره‌شده و تنظیمات Provider استفاده می‌کند و Refresh Token جدید را فقط در صورت ارائه Provider جایگزین می‌کند. Sync دارای قفل انقضادار است، خطای یک پیام را مستقل ثبت می‌کند و وضعیت ناقص/نیازمند تمدید اتصال را نگه می‌دارد. Message-ID در سطح حساب جلوی کپی تکراری پوشه‌ها را می‌گیرد.
- ذخیره مجوزها دیگر ردیف‌های `user_permissions` را حذف نمی‌کند؛ ابتدا مقدارها صفر و سپس کلیدهای Canonical به‌صورت Upsert ثبت می‌شوند. Aliasهای قدیمی در `Auth` خوانده می‌شوند ولی در UI تکراری نمایش داده نمی‌شوند.
- Sidebar، فیلتر Permission و جست‌وجوی منو از یک Registry تغذیه می‌شوند. جست‌وجو فقط روی آیتم‌های مجاز اجرا می‌شود و ی/ي، ک/ك، حرکات و نیم‌فاصله را نرمال می‌کند. `Ctrl+K`، جهت‌ها، Enter و Escape پشتیبانی می‌شوند.
- Flash و اعلان‌های JavaScript از چهار وضعیت success/error/warning/info، ناحیه `aria-live`، بستن با دکمه و جلوگیری از Toast تکراری استفاده می‌کنند.

## دیتابیس و Idempotency

- `carousel_items`: ستون‌های `mobile_image_path`, `alt_text`, `link_target`, `placement`, `item_type`, `starts_at`, `ends_at` و Index انتشار.
- `sms_messages`: ستون‌های `request_key`, `segment_count` و Unique Index کلید درخواست.
- `email_accounts`: ستون‌های `access_token_expires_at`, `sync_lock_token`, `sync_lock_expires_at` و وضعیت‌های افزایشی Sync.

همه تغییرات با بررسی وجود ستون/Index در Repair فعلی یا `CREATE TABLE IF NOT EXISTS` اعمال می‌شوند. داده موجود حذف نمی‌شود و هیچ `DROP` یا `TRUNCATE` وجود ندارد.

## امنیت و راه‌اندازی

- تمام POSTهای اصلاح‌شده CSRF و مجوز Server-side دارند.
- Queryهای ورودی Prepared هستند و URLهای Carousel فقط مسیر داخلی یا HTTP/HTTPS معتبر را می‌پذیرند.
- Password و Token دوباره در UI نمایش داده نمی‌شوند. لاگ Email کلیدهای token/password/secret/body را Redact می‌کند.
- پیوست ورودی Gmail فقط با پسوندهای غیرقابل‌اجرا ذخیره می‌شود؛ پسوند ناشناخته یا اجرایی با `.bin` نگهداری و فقط از endpoint مجاز با `Content-Disposition: attachment` دریافت می‌شود.
- OAuth Refresh فقط با Token URL امن، Client ID، Client Secret رمزنگاری‌شده و Refresh Token قابل اجراست. اگر این تنظیم‌ها ناقص یا Refresh رد شود، حساب به وضعیت «نیازمند تمدید اتصال» می‌رود.
- تست واقعی Provider پیامک و Gmail به شبکه، Extension و Credential محیط وابسته است و باید روی سرور مقصد انجام شود.

## Validation و بازگشت

تست قراردادی اصلی: `php tests/panel_final_integration_contract_test.php`. همچنین PHP lint، تست‌های SMS و Homepage Slider باید اجرا شوند. بازگشت کد با برگرداندن فایل‌های این فاز انجام می‌شود؛ ستون‌های افزوده می‌توانند بدون استفاده باقی بمانند و برای Rollback حذف نمی‌شوند. برای توقف عملیاتی، اسلاید/SMS/Sync را از UI غیرفعال کنید.
