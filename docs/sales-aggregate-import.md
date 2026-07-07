# ورود فروش تجمیعی

## هدف و دامنه Stage 02

این مرحله فقط import فروش تجمیعی مرکزی را پیاده‌سازی می‌کند. موجودی، فرمول پورسانت، AI sync و readerهای dashboard تغییری نکرده‌اند.

مسیر اجرا:

`XLSX/CSV خصوصی → تشخیص منبع → نگاشت header → staging → validation/duplicate → preview → commit تراکنشی`

## تشخیص منبع

نام فایل فقط metadata است. ترتیب تشخیص:

1. Table/List Object با نام `tbltajmi` در شیت visible؛
2. شیت visible با نام `تجمیعی`؛
3. شیت visible دارای تمام ۱۲ سرستون الزامی.

اگر در بالاترین اولویت چند candidate وجود داشته باشد، کاربر باید در UI فارسی منبع را انتخاب کند. شیت hidden در scan خودکار پذیرفته نمی‌شود. parser هیچ `<f>` یا formula اکسل را اجرا نمی‌کند و فقط cached cell value را می‌خواند.

## امنیت upload

- permission: `sales_data_import`؛ تمام POSTها CSRF دارند.
- فقط `.xlsx` و `.csv`، MIME allowlist، سقف ۲۵ مگابایت و سقف ۱۰۰ مگابایت محتوای unzipشده.
- حداکثر ۱۰۰٬۰۰۰ ردیف و ۲٬۰۰۰ entry در XLSX.
- CSV باید UTF-8 باشد و delimiter از comma/semicolon/tab تشخیص داده می‌شود.
- فایل با نام تصادفی در `storage/sales-imports` و permission محدود ذخیره می‌شود؛ `.htaccess` دسترسی مستقیم را منع می‌کند.
- خطاهای فنی فقط log و پیام UI عمومی است. batch فقط برای سازنده یا admin قابل commit/cancel است.

## نگاشت و normalization

`install/sales_aggregate_mapping_seed.php` سرستون‌های شناخته‌شده tbltajmi را با unique key `(source_module, source_header)` ثبت می‌کند و اجرای مجدد duplicate نمی‌سازد.

در workbook واقعی بررسی‌شده، منبع فاقد Table Object بود و شیت با عنوان ` گزارش تجمیعی فروش` از مسیر header scan شناسایی شد. aliasهای `Mobile` و `موبایل` هر دو به کلید canonical `mobile` نگاشت می‌شوند.

- ارقام فارسی/عربی به انگلیسی تبدیل می‌شوند.
- جداکننده هزارگان حذف می‌شود؛ مقدار عددی خالی `NULL` است.
- تاریخ جلالی، میلادی و serial معتبر Excel به `invoice_date` میلادی تبدیل و مقدار اصلی در `invoice_date_raw` حفظ می‌شود.
- تمام ستون‌های ورودی، از جمله ستون ناشناخته، در `raw_json` باقی می‌مانند؛ داده canonical در `normalized_json` است.
- تاریخ یا عدد نامطمئن row را invalid می‌کند و raw exception نمایش داده نمی‌شود.

## کلید و duplicate mode

اگر `کد یکتا` موجود باشد کلید با آن ساخته می‌شود؛ در غیر این صورت SHA1 ترکیب شماره فاکتور، نوع فاکتور، شماره فاکتور فرعی، کد کالا، کد مشتری، کد فروشنده و تاریخ normalized است.

- `skip_duplicates`: تکراری‌ها commit نمی‌شوند.
- `update_existing`: رکورد نهایی دارای همان source key بروزرسانی می‌شود.
- `fail_on_duplicate`: duplicate در validation خطا می‌گیرد و recheck داخل transaction نیز commit را متوقف می‌کند.

مقدار منفی فقط وقتی نوع فاکتور شامل برگشت/مرجوع/return باشد پذیرفته می‌شود. این convention باید با ERP واقعی بازبینی شود.

## نصب و تست

```bash
php install/sales_data_foundation_seed.php
php install/sales_aggregate_mapping_seed.php
php install/sales_aggregate_mapping_seed.php
php tests/sales_aggregate_import_contract_test.php
```

پس از upload، summary کل/معتبر/نامعتبر/تکراری/آماده ورود نمایش داده می‌شود. فقط تایید نهایی، ردیف‌های valid را در `sales_aggregate_rows` درج یا بروزرسانی می‌کند. لغو batch ردیف‌های staging را `cancelled` می‌کند و تاریخچه audit حذف نمی‌شود.

## Migration و rollback

ستون‌های `unique_code`, `invoice_type`, `sub_invoice_number`, `invoice_date_raw` فقط پس از بررسی وجود ستون افزوده می‌شوند. unique index نیز پس از بررسی index و نبود duplicate موجود ساخته می‌شود. rollback خودکار هیچ جدول یا داده‌ای حذف نمی‌کند: route منو غیرفعال و batchهای باز لغو می‌شوند؛ داده committed فقط با فرآیند اصلاحی جدا و تایید انسانی تغییر می‌کند.

## محدودیت‌ها و مرحله بعد

- encodingهای legacy CSV تبدیل خودکار نمی‌شوند.
- cached value formula فقط به‌عنوان value خوانده می‌شود؛ formula محاسبه نمی‌شود.
- تشخیص نوع فاکتور برگشتی نیازمند تایید convention ERP است.
- مرحله بعد باید import موجودی را با adapter مستقل و همین pipeline مشترک پیاده‌سازی کند، بدون تغییر این fact table.
