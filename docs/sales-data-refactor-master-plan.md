# طرح کلان بازطراحی داده‌های فروش، موجودی و پورسانت

## وضعیت و دامنه

این سند «قفل معماری» بازطراحی مرحله‌ای فروش است. در این مرحله هیچ جدول، route، صفحه یا منطق محاسباتی پیاده‌سازی نمی‌شود. هدف، انتقال تدریجی جریان Excelمحور به ماژول پایگاه‌داده‌محور در همان معماری سبک PHP 8.1 و MySQL پروژه است؛ صفحات و داده‌های فعلی تا پایان مهاجرت حفظ می‌شوند.

## الگوهای موجود که باید حفظ شوند

- bootstrap و اتصال پایگاه‌داده فعلی، `Database::repair()` و mirror ساختار نصب تازه در `database/schema.sql`.
- `Auth::requirePermission()` / `Auth::can()`، جدول‌های `modules` و `user_permissions`، نشست فعلی و توکن `Auth::csrfToken()` / `Auth::verifyCsrf()`.
- registry منوی `lib/admin_menu.php` و partialهای مشترک RTL در `views/partials/`.
- helperهای `redirect()`، `flash()`، `setting()` و escaping با `e()`؛ جزئیات فنی فقط در log ثبت شود.
- الگوی batch/preview/commit و نگهداری JSON خام در `UserImportService` و `PayrollImportService`.
- `site_settings` برای تنظیمات ساده، repair ماژول مستقل برای schema، prepared statement و transaction برای commit.
- `CeoDashboardExcel`/`SpreadsheetRows` فقط به‌عنوان قابلیت خواندن ورودی فعلی؛ اجرای formulaهای Excel ممنوع است.
- `SobhanApiClient` و الگوی job/status موجود برای ارتباط امن و قابل رهگیری با SobhanAI/Windows Server.

## معماری مقصد

`Excel/CSV Upload | SobhanAI Server View → Source Adapter → Source Detection → Header Mapping → Staging → Validation → Commit/Upsert → Final Tables → SQL Views → Services → Dashboard/Report/AI`

نام فایل هرگز شناسه منبع نیست. تشخیص با اولویت نام table، نام sheet و سپس امضای headerهای الزامی انجام می‌شود. aliasهای فارسی/عربی، فاصله، نیم‌فاصله، ارقام و نویسه‌های `ي/ی` و `ك/ک` پیش از مقایسه normalize می‌شوند. نتیجه تشخیص و نسخه mapping داخل batch ثبت می‌شود.

## برنامه مرحله‌ای

1. **قرارداد و audit داده**: نمونه فایل‌ها و headerها، کلیدهای طبیعی، واحد پول/تعداد، تقویم، قواعد null/duplicate و وابستگی صفحات فعلی مستند و با fixtureهای ناشناس‌سازی‌شده تثبیت شوند.
2. **زیرساخت import مشترک**: ماژول repair افزایشی، batch/source/staging/error/mapping، storage خصوصی، checksum، preview و commit تراکنشی ساخته شود؛ CSV/XLSX بدون اجرای formula پشتیبانی شود.
3. **داده‌های مرجع**: adapterهای `tblvizit`، `tblzarib`، `tblolaviyat` و موجودیت‌های سازمان فروش/مشتری/کالا/برند با upsert صریح و history لازم متصل شوند.
4. **فروش و موجودی**: `tbltajmi` و `tblanbar` وارد staging و سپس factهای نهایی شوند؛ idempotency با source key + period + batch/checksum تضمین شود.
5. **هدف‌ها**: `tbltargrt` با ابعاد سال، ماه، لاین، کالا، اولویت، ویزیتور و سرپرست وارد شود و خطاهای reference پیش از commit نمایش داده شوند.
6. **تنظیمات فرمول**: نسخه‌بندی، effective period، draft/published/retired، متغیرهای مجاز و evaluator امن پیاده شود؛ هیچ PHP/SQL/eval یا formula اکسل اجرا نشود.
7. **موتور محاسبه و snapshot**: محاسبات اولیه، ضرایب کاهش، جوایز برند/مشتری، پوشش، مرجوعی و پورسانت نهایی ویزیتور/سرپرست به‌صورت run قابل بازتولید و audit ایجاد شوند.
8. **viewها و گزارش‌ها**: viewهای پایدار و serviceهای scope-aware ساخته و داشبوردهای CEO، مدیر فروش، سرپرست، گزارش فروش و 3-3-3 تدریجی به آن‌ها منتقل شوند.
9. **همگام‌سازی AI**: pull از view مجاز سرور یا push snapshot حداقلی، checkpoint، retry، checksum، schema version و audit اضافه شود؛ AI فقط insight تولید کند و منبع محاسبه مالی نباشد.
10. **مهاجرت سازگار و تثبیت**: مقایسه خروجی قدیم/جدید برای چند دوره، feature flag و rollback به reader قدیمی؛ routes و جدول‌های قدیمی حذف نشوند.

هر مرحله prompt و پذیرش مستقل دارد و باید حلقه چهارعمقی: دامنه همان مرحله، کنترل امنیت/نحو/idempotency، تطبیق معماری، و گزارش تغییر/آزمون/ریسک را طی کند.

## گروه جدول‌های نهایی (نام‌های پیشنهادی، نه DDL مصوب)

- **Import control**: `sales_import_batches`, `sales_import_sources`, `sales_import_mappings`, `sales_import_staging_rows`, `sales_import_errors`, `sales_import_events`. هر staging row دارای `batch_id`, source/table/sheet، شماره ردیف، `raw_json`, normalized JSON، status و error code باشد.
- **Master dimensions**: مشتری/صنف، کالا/برند/اولویت، لاین/منطقه، ویزیتور/سرپرست/مدیر فروش و نگاشت به `users`/ساختار سازمانی موجود. جدول موازی کاربران ساخته نشود.
- **Facts**: ردیف فروش/فاکتور، موجودی snapshot، سهم ساختار فروش و هدف‌ها. مبالغ DECIMAL، کلید منبع و batch lineage حفظ شوند.
- **Formula/config**: formula set/version، rule، variable، condition و publication audit. تنظیمات UI ساده می‌توانند در `site_settings` بمانند؛ قواعد محاسباتی ساختاریافته در جدول‌های نسخه‌دار باشند.
- **Calculation/audit**: commission runs، run inputs، visitor results، supervisor results، component breakdown، warnings و approvals. نتیجه هر run immutable یا superseded باشد.
- **AI sync**: sync jobs، checkpoints، payload hash، response/insight و audit؛ secret فقط در سازوکار تنظیمات امن فعلی و هرگز در payload/log خام ذخیره نشود.

نام نهایی جدول/کلید خارجی فقط پس از audit مرحله ۱ تصویب می‌شود. همه DDLها `CREATE TABLE IF NOT EXISTS` و هر column/index جدید پس از بررسی metadata افزوده و در `database/schema.sql` نیز mirror می‌شوند.

## قرارداد منابع شناخته‌شده

- فروش مرکزی: sheet/table `تجمیعی` یا `tbltajmi`.
- موجودی: `tblanbar`.
- ساختار فروش: `ویزیتور` یا `tblvizit`.
- ضریب صنف: `zarib` یا `tblzarib`.
- اولویت کالا: `olaviyat` یا `tblolaviyat`.
- هدف: `target` یا alias فعلی `tbltargrt` (غلط املایی برای سازگاری پذیرفته شود، canonical key مستقل باشد).

برای هر منبع، required header set، optional aliases، type rules و mapping version تعریف می‌شود. ستون ناشناخته reject نمی‌شود و در `raw_json` باقی می‌ماند. نبود header الزامی مانع commit است. formula cell به‌عنوان مقدار cache‌شده قابل اعتماد تلقی نشود؛ در صورت formula بودن، ردیف warning/error سیاست‌پذیر بگیرد و formula اجرا نشود.

## pipeline ورود فایل

1. کنترل permission و CSRF، اندازه/پسوند/MIME واقعی و `is_uploaded_file`.
2. انتقال با نام تصادفی به storage خصوصی خارج از web root (یا مسیر denyشده و تأییدشده)، permission محدود و hash فایل؛ نام اصلی فقط metadata است.
3. ساخت batch در وضعیت `uploaded` و ثبت actor/time/source/checksum.
4. خواندن امن workbook/CSV با سقف row/cell/size/time؛ بدون macro، external link یا formula execution.
5. تشخیص source، normalize header، انتخاب mapping version و ثبت ambiguity به‌عنوان خطای قابل حل.
6. درج staging به‌صورت chunk با `raw_json`، validation ردیفی و summary؛ preview فارسی بدون raw exception.
7. commit فقط برای batch معتبر، با lock/status check، transaction و upsert تعریف‌شده؛ retry همان batch نباید duplicate بسازد.
8. ثبت counts، lineage، خطاها و audit؛ failure rollback شود و فایل/ردیف‌ها برای بررسی طبق retention policy باقی بمانند.

## pipeline همگام‌سازی SobhanAI

- adapter جداگانه همان contract ورودی را از server view دریافت می‌کند؛ bypass مستقیم staging/validation ممنوع است.
- allowlist view و column، service credential خارج از repository، TLS، timeout، pagination و حد payload الزامی است.
- cursor/checkpoint + stable source row key + payload checksum، اجرای incremental و retry idempotent را تضمین می‌کنند.
- job stateها: queued/running/validated/committed/failed/cancelled؛ پیام UI عمومی و جزئیات sanitize‌شده در log/audit.
- برای insight، فقط view/snapshot مجاز و scope‌شده ارسال شود؛ PII و secret حداقل‌سازی و prompt/response با retention مشخص ثبت شود.
- پاسخ AI هیچ fact یا commission نهایی را خودکار تغییر نمی‌دهد؛ insight با batch/run/schema version و زمان تولید نمایش داده می‌شود.

## راهبرد view و گزارش

- factها canonical و viewها قرارداد خواندن پایدار هستند؛ منطق امنیت و scope در service/`OrgAccess` باقی می‌ماند، نه صرفاً منو یا UI.
- viewهای پیشنهادی: sales normalized، inventory latest، target achievement، 3-3-3، visitor/supervisor commission breakdown و dashboard summaries.
- viewها بدون `SELECT *`، با نام ستون ثابت، period key استاندارد و فقط محاسبات deterministic ساخته شوند؛ منطق نسخه‌دار پورسانت در run/service ذخیره و snapshot شود.
- indexها از queryهای واقعی و `EXPLAIN` استخراج شوند. viewهای سنگین در صورت نیاز با snapshot table و refresh صریح جایگزین شوند، نه با وابستگی پنهان.
- صفحات موجود ابتدا پشت feature flag reader جدید می‌گیرند؛ مقایسه shadow و reconciliation پیش از تغییر default الزامی است.

## راهبرد منو و permission

- گروه فعلی «فروش و تحلیل عملکرد» در `lib/admin_menu.php` حفظ و به‌تدریج مرتب شود؛ URLهای فعلی redirect یا حذف نشوند.
- آیتم‌های آینده: مرکز ورود داده، batchها/خطاها، موجودی، اهداف، تنظیمات فرمول، محاسبات پورسانت، گزارش‌ها و AI Insight.
- permissionهای ریزدانه پیشنهادی: `sales_data.import`, `.review`, `.commit`, `.view`, `inventory.import`, `sales_targets.manage`, `commission.formulas.manage`, `commission.run`, `commission.approve`, `sales_reports.view`, `sales_ai.view/run`.
- هر route مجوز خود را server-side بررسی کند؛ مجوز import از commit و مجوز formula edit از publish/approve جدا باشد. seed permissionها idempotent و default-deny برای عملیات حساس باشد.

## راهبرد تنظیمات فرمول

- formula set به دوره مؤثر و نسخه متصل و پس از publish immutable شود؛ تغییر بعدی نسخه جدید می‌سازد.
- متغیرها allowlist و typed؛ parser محدود مشابه `SafeFormula` پس از hardening و آزمون استفاده شود. `eval`, SQL expression، function دلخواه، reference سلول و formula اکسل ممنوع است.
- هر component (اولیه، کاهش، برند، هدف مشتری، پوشش، مرجوعی) rule/condition/priority/rounding/cap/floor و توضیح فارسی دارد.
- preview با fixture، dry-run، چهارچشم برای publish، audit قبل/بعد و امکان rollback با انتخاب نسخه قبلی (بدون حذف history) الزامی است.
- rounding، واحد پول/تعداد، null/zero، مرجوعی و ترتیب اعمال قواعد باید پیش از کدنویسی با مالک کسب‌وکار مصوب شود.

## قواعد ایمنی و عدم تغییر

- بدون حذف فایل/route/table/column/data و بدون `DROP`, `TRUNCATE`, rename یا ALTER مخرب.
- بدون تغییر معماری Auth/permission، API عمومی، منطق مالی/پورسانت موجود یا default dashboard تا تأیید انسانی مرحله مربوط.
- prepared statement، validation، escaping، CSRF، transaction، IDOR/scope check و logging بدون secret/PII خام.
- فایل upload عمومی نباشد؛ download فقط controller مجاز. archive bomb، zip traversal، macro و external relationship رد شوند.
- خطای PHP/SQL در UI نمایش داده نشود. هر batch و row lineage و `raw_json` داشته باشد.
- هیچ dependency یا framework جدید بدون توجیه و تأیید انسانی اضافه نشود. merge و deployment دستی و پس از review است.

## چک‌لیست پذیرش کلان

- [ ] audit ورودی و قواعد کسب‌وکار با نمونه‌های واقعی/edge case تأیید شده است.
- [ ] تشخیص بدون وابستگی به filename و با table/sheet/header و aliasها آزمون دارد.
- [ ] batch، row lineage، `raw_json`، checksum، preview، validation و commit idempotent اثبات شده‌اند.
- [ ] فایل خصوصی، formula غیرفعال، محدودیت resource، CSRF، permission، scope/IDOR، SQLi و XSS آزمون شده‌اند.
- [ ] schema repair و fresh install هر دو idempotent و بدون عملیات مخرب‌اند؛ migration/rollback note موجود است.
- [ ] import فایل و AI adapter از یک staging/validation contract عبور می‌کنند.
- [ ] formula versioning، dry-run، approval، audit و reproducible commission run آزمون شده‌اند.
- [ ] view/serviceها خروجی‌های CEO، مدیر فروش، سرپرست، گزارش فروش، 3-3-3 و اجزای پورسانت را پوشش می‌دهند.
- [ ] reconciliation چند دوره با خروجی Excel/صفحات قدیمی و tolerance مصوب انجام شده است.
- [ ] feature flag و rollback reader وجود دارد و route/module قدیمی حذف نشده است.
- [ ] آزمون‌های targeted، schema contract، import fixtures، permission/security و regression اجرا و نتیجه مستند شده‌اند.
- [ ] مستندات استفاده، تنظیمات، migration، rollback، محدودیت‌ها و review انسانی کامل‌اند.

## مرحله بعدی مجاز

فقط «مرحله ۱: audit و قرارداد داده» اجرا شود: ساخت inventory دقیق فایل‌ها/کلاس‌ها/جداول فعلی، استخراج headerهای workbookهای در دسترس، تعریف canonical field dictionary و natural keys، ماتریس mapping/validation و test fixture plan. در آن مرحله نیز DDL، UI و تغییر منطق محاسباتی مجاز نیست.
