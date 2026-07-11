# ورود اطلاعات مرجع فروش و موجودی

## هدف

این مرحله دو دیتاست رسمی مرجع سایت را ایجاد می‌کند:

- `sales_aggregate_rows` برای شیت `تجمیعی` / Table `tbltajmi`
- `inventory_aggregate_rows` برای شیت یا Table `tblanbar`

داشبوردها و گزارش‌های مرحله‌های بعد باید از دیتابیس و Viewهای مرجع بخوانند، نه از فایل Excel. فایل فقط ورودی کنترل‌شده است؛ داده ابتدا staging، سپس validation و فقط بعد از تایید مدیر وارد جدول نهایی و Batch فعال می‌شود.

## صفحات ادمین

- `/admin/sales-aggregate-import.php` با عنوان «ورود اطلاعات فروش تجمیعی»
- `/admin/inventory-aggregate-import.php` با عنوان «آپدیت موجودی انبار»
- `/admin/sales-reference-batches.php` برای تاریخچه ورود اطلاعات مرجع
- `/admin/sales-reference-errors.php` برای خطاهای ورود اطلاعات مرجع
- `/admin/sales-reference-status.php` برای وضعیت دیتای مرجع سایت

## تشخیص فایل

تشخیص به نام فایل وابسته نیست.

فروش تجمیعی به‌ترتیب از `tbltajmi`، شیت `تجمیعی` یا سرستون‌های الزامی تشخیص داده می‌شود. موجودی به‌ترتیب از `tblanbar`، شیت `tblanbar` یا سرستون‌های الزامی تشخیص داده می‌شود. اگر چند candidate معتبر پیدا شود، ادمین در UI فارسی منبع صحیح را انتخاب می‌کند.

فرمول‌های Excel اجرا نمی‌شوند و فقط مقدار cached/text خوانده می‌شود. ستون‌های ناشناخته در `raw_json` حفظ می‌شوند.

## Batch فعال

هر import یک Batch در `sales_reference_import_batches` دارد. بعد از تایید، Batch با status `committed` به‌عنوان مرجع فعال همان `source_module + period_key` ثبت می‌شود و Batch فعال قبلی همان منبع و دوره غیرفعال می‌شود.

Helperهای مرجع:

- `SalesReferenceRepository::getActiveReferenceBatch($sourceModule, $periodKey = null)`
- `SalesReferenceRepository::setActiveReferenceBatch($batchId)`
- `SalesReferenceRepository::getActiveSalesAggregateQueryScope($periodKey = null)`
- `SalesReferenceRepository::getActiveInventoryAggregateQueryScope($periodKey = null)`

## Validation

فروش تجمیعی فیلدهای اصلی فاکتور، مشتری، کالا، تعداد، مبلغ ناخالص، تخفیف و مبلغ خالص را کنترل می‌کند. مقدار منفی فقط برای فاکتورهای برگشتی/مرجوع پذیرفته می‌شود.

موجودی کد و نام کالا، تعداد در کارتن، برند، گروه و یکی از موجودی دوره کل یا موجودی فعلی کل را کنترل می‌کند. موجودی منفی خطای سخت نیست و برای بررسی بعدی در raw/normalized داده حفظ می‌شود.

اعداد فارسی/عربی و جداکننده هزارگان normalize می‌شوند. مقدار عددی خالی `NULL` است. تاریخ raw همیشه حفظ می‌شود و اگر helper جلالی بتواند تبدیل کند، مقدار DATE میلادی هم ذخیره می‌شود.

## Viewهای مرجع

Seed این Viewها را ایجاد یا بروزرسانی می‌کند:

- `vw_active_sales_aggregate_rows`
- `vw_active_inventory_aggregate_rows`
- `vw_sales_reference_summary`
- `vw_inventory_reference_summary`
- `vw_sales_by_manager_reference`
- `vw_sales_by_supervisor_reference`
- `vw_sales_by_visitor_reference`
- `vw_sales_by_line_reference`
- `vw_sales_by_brand_reference`
- `vw_sales_by_customer_reference`
- `vw_sales_by_product_reference`
- `vw_inventory_by_brand_reference`
- `vw_inventory_by_product_reference`

داشبوردهای مرحله بعد باید از این Viewها یا helperهای active scope استفاده کنند و نباید batch id را hardcode کنند.

## نصب و اعتبارسنجی

```bash
php install/sales_reference_import_seed.php
php install/sales_reference_import_seed.php
php tests/sales_aggregate_import_contract_test.php
php tests/inventory_import_contract_test.php
```

اجرای دوباره seed نباید جدول، permission، menu یا mapping تکراری ایجاد کند.

## خارج از دامنه این مرحله

در این مرحله dashboard refactor، محاسبه پورسانت، SobhanAI sync، target import، visitor import، zarib import و olaviyat import انجام نشده است. این مرحله فقط منبع مرجع upload/staging/validation/commit/activation و Viewهای پایه را آماده می‌کند.

## Rollback

هیچ DROP/TRUNCATE یا حذف داده‌ای در این مسیر وجود ندارد. برای rollback عملیاتی، menu/page جدید غیرفعال می‌شود یا Batchهای باز cancel می‌شوند. داده committed فقط با فرآیند اصلاحی جداگانه و تایید انسانی تغییر می‌کند.
