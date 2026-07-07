# ورود تجمیعی موجودی انبار

## دامنه Stage 03

این مرحله فقط import مستقل `tblanbar` را اضافه می‌کند. فروش تجمیعی، dashboardها، commission و AI sync تغییر نکرده‌اند. مسیر داده عبارت است از:

`XLSX/CSV خصوصی → تشخیص tblanbar/sheet/header → mapping → staging → validation → preview → commit/upsert`

## شناسایی و workbook واقعی

تشخیص به نام فایل وابسته نیست و به‌ترتیب Table Object با نام `tblanbar`، شیت `tblanbar` و سپس headerهای الزامی در شیت visible انجام می‌شود. workbook واقعی بررسی‌شده یک Table با محدوده `A1:BE2`، ۵۷ ستون و یک ردیف داده دارد. ردیف formula/footer خارج از Table است و وارد staging نمی‌شود. formulaها هرگز اجرا نمی‌شوند و reader فقط cached value را می‌خواند.

## قرارداد داده

headerهای الزامی: کد/نام کالا، یکی از موجودی دوره کل یا موجودی فعلی کل، تعداد در کارتن، برند، کد گروه و نام گروه. در سطح ردیف فقط کد و نام کالا اجباری‌اند؛ فیلدهای عددی خالی `NULL` و عدد نامعتبر row error است. تاریخ انقضا و آخرین خرید هم به‌شکل raw نگهداری و در صورت قابل تشخیص بودن به میلادی normalize می‌شوند.

کلید پایدار SHA1 ترکیب `product_code + index_code + expire_date_raw + manufacturer_code + brand_name` است. حالت‌های duplicate شامل skip، update و fail هستند. تمام ستون‌ها حتی مواردی که ستون اختصاصی ندارند در `raw_json` حفظ می‌شوند.

## امنیت

- permission: `sales_data_import` و CSRF برای همه POSTها.
- MIME/extension، سقف ۲۵MB، سقف unzip برابر ۱۰۰MB و سقف ۱۰۰هزار ردیف.
- storage خصوصی `storage/inventory-imports` با نام تصادفی و منع دسترسی Apache.
- مالکیت batch برای جلوگیری از IDOR، prepared statement، transaction و پیام خطای عمومی.

## نصب و آزمون

```bash
php install/sales_data_foundation_seed.php
php install/inventory_mapping_seed.php
php install/inventory_mapping_seed.php
php tests/inventory_import_contract_test.php
```

اجرای دوم seed duplicate نمی‌سازد. تست integration باید staging، commit، raw JSON، skip/update duplicate و rollback را روی MariaDB اجرا کند.

## Migration و rollback

ستون‌های مفقود فقط پس از بررسی metadata افزوده می‌شوند و unique index پس از بررسی duplicate موجود ساخته می‌شود. rollback خودکار هیچ جدول یا داده committed را حذف نمی‌کند؛ route قابل غیرفعال‌شدن است و batch باز به `cancelled` می‌رود.

## محدودیت و مرحله بعد

ستون‌های مبلغی اضافی workbook که در قرارداد final صریح نیستند در `normalized_json/raw_json` حفظ می‌شوند. قبل از استفاده مالی باید واحد مبلغ، معنای `sudozian` و سیاست تاریخ انقضا با مالک ERP تأیید شود. مرحله بعد می‌تواند import ساختار ویزیتورها را بدون تغییر این ماژول آغاز کند.
