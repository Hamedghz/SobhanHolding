# قواعد اجرای فازها با Codex

## دروازه هر task

Codex پیش از edit باید task/business goal، paths، route/service/repository/schema/permission/UI/test/doc dependencies و موارد ممنوع را مشخص کند و یک impact analysis کوتاه شامل فایل‌ها، ماژول‌ها، DB objects، امنیت، API/UI/docs، تست و risk ارائه دهد.

اسکن از فایل‌های مستقیم آغاز و فقط با نیاز dependency گسترش می‌یابد. تغییرات موجود کاربر حفظ می‌شوند. هر task فقط یک phase و acceptance criteria مصوب را اجرا می‌کند؛ Phase بعدی ضمنی آغاز نمی‌شود.

## قواعد پیاده‌سازی

- کوچک‌ترین diff افزایشی؛ reuse معماری و naming موجود؛ بدون refactor وسیع.
- قبل از افزودن route/table/service/permission، مشابه موجود جست‌وجو شود.
- schema فقط idempotent و در fresh-install + repair متناظر؛ بدون عملیات مخرب.
- auth، permission، `OrgAccess`، CSRF، prepared statement، escaping، upload safety و log امن اجباری است.
- پیام UI فارسی و بدون جزئیات فنی؛ JSON مطابق قانون اساسی.
- مستند مرتبط همان task به‌روز شود و migration/rollback/known limitations را توضیح دهد.
- تغییر high-risk در auth/permission/schema/public API/payroll/KPI/reporting فقط با task و تأیید صریح انسانی انجام می‌شود.

## تست و تحویل

ابتدا lint/contract/unit/integration هدفمند اجرا شود. full suite فقط برای shared logic، auth، scope، schema، API contract، گزارش/مالی یا CI لازم است. اگر runtime موجود نیست، blocker و validation جایگزین دقیقاً اعلام شود؛ موفقیت شبیه‌سازی نشود.

خروجی پایانی هر فاز باید شامل summary، changed files، اسناد، impact، test log و passed/failed/skipped، coverage موجود، risk، security، migration، rollback، PR title/description و next steps باشد. merge/deploy انجام نمی‌شود و review انسانی الزامی است.

## قواعد PR

عنوان: `feat(module): short description` (یا `docs(architecture): ...` برای این فاز). توضیح PR شامل دلیل، فایل‌ها، impact، تست/coverage، risk، security، migration/rollback، docs و checklist باشد.

Checklist حداقل: scope محدود، backward compatibility، no destructive SQL، CSRF/write validation، permission + OrgAccess، prepared SQL، escaped output، safe errors/logs، tests، docs و human review.

## وضعیت Phase 0

این اسناد خروجی Phase 0 هستند و مجوز اجرای Phase 1 محسوب نمی‌شوند. پیش از Phase 1 باید اختلاف مدل کاربران/روابط legacy، permission keys، queryهای scope و مصرف‌کننده‌های JSON inventory و به taskهای کوچک تقسیم شوند.

## قواعد API افزوده‌شده در Phase 2

- endpoint جدید JSON باید از `json_success()` و `json_error()` یا bootstrap سازگار دامنه استفاده کند.
- authentication endpoint نباید redirect HTML تولید کند؛ خطاهای 401/403/419 باید envelope کنترل‌شده داشته باشند.
- endpoint فهرست باید pagination محدود، prepared filters و query-level scope داشته باشد.
- قرارداد endpoint قدیمی فقط همراه consumer آن و regression test مهاجرت می‌کند؛ افزودن `/api/ui` مجوز تغییر planner، messenger، notify یا AI polling نیست.
- writeهای AJAX باید method و CSRF را بررسی کنند و در مالکیت session، `user_id` ورودی را نپذیرند.
