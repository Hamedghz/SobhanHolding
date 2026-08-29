# ورود فروش تجمیعی

## قرارداد Canonical خروجی ERP

پروفایل پیش‌فرض فروش تجمیعی `ERP_SALES_AGGREGATE_V1` است:

- شیت: `گزارش تجمیعی فروش` (تشخیص پس از trim)
- Excel Table: `tbltajmii`
- سرستون: دقیقاً ۱۰۹ ستون از `ردیف` تا `مصرف کننده کالا` طبق `SalesDataNormalizer::CANONICAL_HEADERS`
- قالب دانلودی: یک شیت، یک Table، فقط ردیف Header، بدون نمونه و فرمول اجباری

قالب قدیمی سایت ۱۴۱ ستون داشت. نسبت به ERP، `ردیف` و `موبایل` را نداشت، `Mobile` را خروجی می‌داد و ۳۴ ستون قدیمی/داخلی مانند `gn`، `pn`، `Code P1`، `formula Num11`، `ضریب`، `ضریب فروش` و `اولویت` را مطالبه می‌کرد. علت اصلی، تولید قالب مستقیم از همه aliasهای رجیستری سازگاری بود. اکنون aliasها فقط برای خواندن فایل‌های قدیمی‌اند و خروجی قالب فقط از فهرست Canonical ساخته می‌شود.

مسیر اجرا:

`XLSX/CSV خصوصی → تشخیص منبع → نگاشت header → staging → validation/duplicate → preview → commit تراکنشی`

برای فایل‌های بزرگ، worksheet با نمونه تشخیصی محدود و stream کامل خوانده می‌شود. درج staging در یک تراکنش اتمیک انجام می‌شود و Commit نهایی ردیف‌های معتبر را در chunkهای حداکثر ۵۰۰تایی می‌خواند. همگام‌سازی ستون‌های سازگاری Batch پس از پایان ردیف‌ها یک‌بار اجرا می‌شود تا زمان پردازش با تعداد ردیف‌ها رشد درجه‌دو نداشته باشد.

## تشخیص منبع

نام فایل فقط metadata است. ترتیب تشخیص:

1. Table/List Object با نام `tbltajmii`؛
2. شیت visible که نام trim‌شده آن `گزارش تجمیعی فروش` است؛
3. مجموعه و ترتیب ۱۰۹ سرستون Canonical؛
4. سپس aliasهای قدیمی `tbltajmi`، `tblsales` و شیت `تجمیعی` برای سازگاری.

اگر در بالاترین اولویت چند candidate وجود داشته باشد، کاربر باید در UI فارسی منبع را انتخاب کند. شیت hidden در scan خودکار پذیرفته نمی‌شود. parser هیچ `<f>` یا formula اکسل را اجرا نمی‌کند و فقط cached cell value را می‌خواند.

## امنیت upload

- permission: `sales_data_import`؛ تمام POSTها CSRF دارند.
- فقط `.xlsx` و `.csv`، MIME allowlist، سقف ۲۵ مگابایت و سقف ۱۰۰ مگابایت محتوای unzipشده.
- حداکثر ۱۰۰٬۰۰۰ ردیف و ۲٬۰۰۰ entry در XLSX.
- CSV باید UTF-8 باشد و delimiter از comma/semicolon/tab تشخیص داده می‌شود.
- فایل با نام تصادفی در `storage/sales-imports` و permission محدود ذخیره می‌شود؛ `.htaccess` دسترسی مستقیم را منع می‌کند.
- خطاهای فنی فقط log و پیام UI عمومی است. batch فقط برای سازنده یا admin قابل commit/cancel است.

## نگاشت و normalization

`install/sales_aggregate_mapping_seed.php` نگاشت‌ها را با unique key `(source_module, source_header)` ثبت می‌کند و اجرای مجدد duplicate نمی‌سازد. هر ۱۰۹ سرستون وضعیت `mapped` و دلیل نگهداری دارد؛ aliasهای قدیمی وضعیت `optional` دارند. `raw_json` همه ورودی‌ها را حفظ می‌کند.

در workbook واقعی `231-14050430104947.xlsx`، شیت خام با یک فاصله ابتدایی، Table با نام `tbltajmii` و محدوده واقعی `A1:DE10328` شناسایی شد. این محدوده شامل یک Header و ۱۰٬۳۲۷ ردیف داده است. عدد ۱۰٬۳۲۸ ردیف داده و محدوده `A1:DE10329` در متن اولیه با خود فایل پیوست یک ردیف اختلاف دارد؛ importer مرز واقعی Table را مبنا می‌گیرد. aliasهای `Mobile` و `موبایل` هر دو هنگام خواندن به `mobile` نگاشت می‌شوند، اما قالب فقط `موبایل` را خروجی می‌دهد.

- ارقام فارسی/عربی به انگلیسی تبدیل می‌شوند.
- جداکننده هزارگان حذف می‌شود؛ مقدار عددی خالی `NULL` است.
- تاریخ جلالی، میلادی و serial معتبر Excel به `invoice_date` میلادی تبدیل و مقدار اصلی در `invoice_date_raw` حفظ می‌شود.
- تمام ستون‌های ورودی، از جمله ستون ناشناخته، در `raw_json` باقی می‌مانند؛ داده canonical در `normalized_json` است.
- `ردیف` فقط در `source_row_number` staging نگهداری می‌شود و در کلید upsert دخالت ندارد.
- `ماه گردش` بدون بازنویسی در `turnover_month` ذخیره می‌شود؛ `turnover_year` از سال تاریخ جلالی و `period_key` از سال و شماره ماه گردش ساخته می‌شود.
- اختلاف ماه تاریخ فاکتور و ماه گردش با `PERIOD_MISMATCH` و severity هشدار ثبت می‌شود و ردیف را حذف یا اصلاح نمی‌کند.
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
php tests/sales_aggregate_erp_contract_test.php
SOBHAN_ERP_SALES_XLSX=/path/to/erp.xlsx php tests/sales_aggregate_real_erp_integration_test.php
```

پس از upload، summary کل/معتبر/نامعتبر/تکراری/آماده ورود نمایش داده می‌شود. فقط تایید نهایی، ردیف‌های valid را در `sales_aggregate_rows` درج یا بروزرسانی می‌کند. لغو batch ردیف‌های staging را `cancelled` می‌کند و تاریخچه audit حذف نمی‌شود.

## Migration و rollback

ستون‌های `turnover_month`، `turnover_year` و `period_key` به‌صورت additive و پس از بررسی وجود ستون افزوده می‌شوند؛ index دوره گردش نیز idempotent است. rollback کد با بازگرداندن فایل‌های این تغییر انجام می‌شود. rollback داده هیچ حذف خودکاری ندارد: Batch فعال قبلی باید از مسیر کنترل‌شده فعال شود و داده committed فقط با فرآیند اصلاحی جدا و تایید انسانی تغییر کند.

## محدودیت‌ها و مرحله بعد

- encodingهای legacy CSV تبدیل خودکار نمی‌شوند.
- cached value formula فقط به‌عنوان value خوانده می‌شود؛ formula محاسبه نمی‌شود.
- تشخیص نوع فاکتور برگشتی نیازمند تایید convention ERP است.
- اعتبارسنجی production و حجم‌های بزرگ‌تر از فایل مرجع همچنان نیازمند بازبینی انسانی است.
