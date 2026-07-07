# بانک ۲۰ آزمون سازمانی سبحان

این ماژول بانک سؤال مرجع «بانک سؤال ۲۰ آزمون سازمانی شرکت پخش سبحان» را به‌صورت ساختاری در فایل [install/data/sobhan_assessment_20_battery.json](/E:/Site/SobhanHolding/install/data/sobhan_assessment_20_battery.json) نگه می‌دارد و در زمان Seed فقط همان JSON را وارد دیتابیس می‌کند.

## چه چیزی تغییر کرد

- ۲۰ تست سازمانی رسمی سبحان با ۴۰۰ سؤال دقیق از DOCX مرجع جایگزین Seed قدیمی شد.
- metadata نسخه‌بندی با `seed_key = sobhan_20_assessment_battery` و `seed_version = 2026-07-07-v1` اضافه شد.
- سؤالات دارای `[R]` با `reverse_score = 1` ذخیره می‌شوند و marker در متن نمایش به کارمند حذف می‌شود.
- تست‌های دانشی بدون گزینه/کلید صحیح، به‌صورت `template_pending_key` ثبت می‌شوند و نمره ساختگی تولید نمی‌کنند.
- اگر پاسخ/نتیجه/assignment ثبت‌شده‌ای وجود نداشته باشد، Seedهای قبلی فقط برای رکوردهای seeded حذف می‌شوند؛ در غیر این صورت آرشیو می‌شوند.

## فایل‌های مهم

- [core/HrModule.php](/E:/Site/SobhanHolding/core/HrModule.php)
- [services/TestScoringService.php](/E:/Site/SobhanHolding/services/TestScoringService.php)
- [install/seeds/004_hr_assessment_seed.php](/E:/Site/SobhanHolding/install/seeds/004_hr_assessment_seed.php)
- [install/data/sobhan_assessment_20_battery.json](/E:/Site/SobhanHolding/install/data/sobhan_assessment_20_battery.json)
- [admin/employee-assessments.php](/E:/Site/SobhanHolding/admin/employee-assessments.php)

## نحوه اجرا

از یکی از دو مسیر زیر استفاده کنید:

1. صفحه [install/sobhan_hr_seed.php](/E:/Site/SobhanHolding/install/sobhan_hr_seed.php)
2. دکمه «بازسازی امن بانک سوالات» در [admin/employee-assessments.php](/E:/Site/SobhanHolding/admin/employee-assessments.php)

حالت `safe` رفتار پیش‌فرض است. `dry_run` فقط شمارش هدف را نشان می‌دهد. `repair` همان ساختار را بدون رفتار destructive بررسی می‌کند.

## رفتار امن جایگزینی

- فقط داده‌های seeded قبلی بررسی می‌شوند.
- تست/سؤال سفارشی non-seeded حفظ می‌شود.
- هیچ `DROP` یا `TRUNCATE` وجود ندارد.
- در صورت وجود داده عملیاتی، تست‌های seeded قدیمی `inactive/archive` می‌شوند تا با بانک فعال جدید مخلوط نشوند.

## تست‌های development-only

- `DISC_ORG`
- `MBTI_ORG_INFORMAL`
- `EQ_ORG`
- `JOB_SATISFACTION_ORG`

این خروجی‌ها برای توسعه، کوچینگ، چیدمان نقش و گفت‌وگوی HR هستند و مبنای رد استخدامی نیستند.

## تست‌های نیازمند تکمیل گزینه و کلید

- `PRODUCT_KNOWLEDGE_DISTRIBUTION`
- `SALES_CATALOG_KNOWLEDGE`
- `SERVICE_STANDARDS`
- `HEALTH_SAFETY`
- `UPSELL_READINESS`

تا قبل از تکمیل `options_json` و `correct_answer_json` برای این تست‌ها، نتیجه رسمی به‌صورت `template_pending_key` ثبت می‌شود.

## پکیج‌های نقش‌محور

- `visitor_sales_rep`
- `sales_supervisor`
- `sales_manager`
- `warehouse`
- `finance_admin`
- `it_planning`
- `driver_delivery`

## reverse scoring

برای سؤالات Likert:

`reverse value = 6 - original value`

نمونه‌ها: `DISC-S4`, `MBTI-EI2`, `MBTI-EI4`, `JS3`, `BO12`, `IN8`, `IN12`, `IN17`

## اعتبارسنجی نصب

- باید دقیقاً ۲۰ تست فعال/مدیریت‌شده وجود داشته باشد.
- هر تست باید ۲۰ سؤال داشته باشد.
- نسخه جاری Seed باید `2026-07-07-v1` باشد.
- متن سؤال‌ها نباید با شماره تکراری `1.` ذخیره شده باشد.
- سؤالات دانشی بدون کلید باید در dashboard به‌عنوان pending دیده شوند.
