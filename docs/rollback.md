# سیاست rollback و بازیابی

## اصل

rollback در این پروژه غیرمخرب است: کد/route/config تغییر یافته به نسخه قبلی برمی‌گردد، feature flag یا menu entry جدید غیرفعال می‌شود و داده یا schema افزوده‌شده باقی می‌ماند. `DROP`، `TRUNCATE`، rename و حذف فایل/داده rollback محسوب نمی‌شود و بدون تأیید انسانی ممنوع است.

## پیش از هر تغییر

1. scope، مالک، risk و فایل/جدول‌های درگیر ثبت شود.
2. برای medium/high risk از DB و فایل‌های مرتبط backup قابل بازیابی گرفته و محل/زمان آن ثبت شود؛ secret در repo قرار نگیرد.
3. migration روی نسخه مشابه staging اجرا و اجرای دوم آن برای idempotency آزموده شود.
4. baseline تست و قرارداد API/UI ثبت شود.

## الگوی بازگشت

- **فقط مستندات/UI:** commit مرتبط revert؛ asset cache پاک/نسخه‌گذاری شود.
- **کد/route/service:** کد به نسخه قبلی برگردد؛ schema additive و داده حفظ شود؛ worker جدید متوقف و queue نگه‌داری شود.
- **schema additive:** reader/writer جدید rollback شود، ستون/جدول افزوده حذف نشود. کد قدیمی باید نبود/وجود فیلد جدید را تحمل کند.
- **seed:** seed باید idempotent باشد؛ رکورد سازمانی/permission ایجادشده خودکار حذف نشود و در صورت لزوم با `status=disabled` و تأیید مالک غیرفعال شود.
- **API:** consumer به قرارداد قبلی برگردد یا compatibility adapter فعال شود؛ endpoint عمومی حذف نشود.
- **external sync/AI/ERP:** job جدید pause، credential rotate در محل امن در صورت incident، cursor/checkpoint حفظ و replay فقط پس از reconciliation انجام شود.
- **notification/worker:** dispatch متوقف، pendingها حفظ، duplicate guard بررسی و delivery log برای reconciliation استفاده شود.

## Runbook incident

1. تغییر را freeze و زمان/نسخه/دامنه اثر را ثبت کنید.
2. feature/worker مشکل‌دار را بدون حذف داده غیرفعال کنید.
3. log امن، HTTP status، queue و DB consistency را جمع‌آوری کنید؛ raw error را به UI نفرستید.
4. آخرین نسخه سالم کد را با review انسانی بازگردانید.
5. smoke test احراز هویت، permission، scope، write و صفحات وابسته را اجرا کنید.
6. داده‌های طی بازه incident را reconcile و نتیجه را مستند کنید.

## معیار موفقیت rollback

صفحات قبلی کار می‌کنند، مجوز و scope ضعیف‌تر نشده، داده‌ای از دست نرفته، job تکراری ایجاد نشده و محدودیت/اقدام بعدی ثبت شده است. restore واقعی backup باید دوره‌ای جداگانه آزمایش شود؛ داشتن فایل backup به‌تنهایی تضمین بازیابی نیست.

## یادداشت rollback فاز ساختار سازمانی

برای بازگشت Phase 1، تغییرات `admin/users.php`، `admin/hr-settings.php`، `core/OrgModule.php` و `lib/OrgAccess.php` به نسخه قبلی برگردند. indexهای افزوده‌شده روی `users` حذف نشوند؛ وجود آن‌ها با کد قبلی سازگار است. هیچ‌یک از مقادیر سازمانی کاربران به‌صورت خودکار بازنویسی یا حذف نشود. پیش از rollback، دسترسی employee، manager و super admin با direct URL دوباره smoke-test شود.

## یادداشت rollback فاز KPI و ارزیابی

برای rollback Phase 6، کد validation قالب، transaction ثبت نهایی، اعلان تکمیل و فیلترهای جدید revert شود. ستون افزوده `hr_kpi_templates.sales_line`، assignmentها، response JSON، نتایج تاریخی، score log و اعلان‌های ثبت‌شده حذف نشوند. پیش از بازگشت، ثبت نهایی جدید freeze و assignmentهای در حال انجام reconcile شوند؛ seed rollback با حذف داده انجام نمی‌شود.
# Phase 10 و 11

- پیش از deploy از دیتابیس backup بگیرید و `Database::repair()` را یک‌بار اجرا کنید.
- rollback کد: `sync_api_enabled` و `file_backup_enabled` را صفر کنید، سپس endpoint/serviceهای Phase 10/11 را revert کنید.
- جدول‌های `sync_queue`, `sync_api_logs` و `uploaded_files_backup*` و logها حذف نشوند. هیچ DROP/TRUNCATE بخشی از rollback نیست.
- rotate کلید API، workerهای Windows را تا توزیع کلید جدید متوقف می‌کند؛ کلید خام قابل بازیابی نیست.
