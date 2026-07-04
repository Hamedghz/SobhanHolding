# صورتجلسات و مصوبات

مسئولیت‌ها با چهار مرز `MeetingRepository`، `DecisionRepository`، `RuleRepository` و `FollowupRepository` جدا شده‌اند؛ facade قدیمی برای backward compatibility باقی مانده است. فرم جلسه روی عنوان، تاریخ، برگزارکننده، حاضرین، دستور جلسه، خلاصه و پیوست تمرکز دارد. سایر فیلدها و تنظیمات قانون زیر «تنظیمات پیشرفته» قرار می‌گیرند.

UI وضعیت‌ها را به `new`, `in_progress`, `waiting`, `done`, `verified`, `cancelled`, `overdue` محدود می‌کند و statusهای قدیمی فقط برای داده‌های موجود نگهداری شده‌اند. `RuleRepository` تنها مصوبات `is_rule=1` را که جلسه نهایی یا مصوبه verified است می‌پذیرد.

تغییر schema مخرب وجود ندارد. تست: `tests/management_governance_contract_test.ps1`. rollback فقط بازگردانی UI/Repositoryهای جدید است؛ نسخه قانون یا مصوبه حذف نشود.
# یکپارچگی گزارش‌های مدیریتی و حاکمیت — Phase 8

گزارش‌های `sales`, `finance`, `warehouse`, `technology` در `ManagementReportsRepository` نگهداری می‌شوند. هر مدیر فقط نوع مجاز خود را آماده می‌کند؛ reviewer گزارش ثبت‌شده را بررسی می‌کند و CEO/aggregate فقط گزارش تأییدشده یا بایگانی‌شده را می‌بیند. attachment تنها از endpoint کنترل‌شده با permission مجدد، containment مسیر، منع symlink و cache خصوصی تحویل می‌شود.

قالب فروش فیلدهای مستقل What / Why / Action دارد. گزارش فروش تأییدشده می‌تواند از صفحه مشاهده برای کاربران دارای مجوز review/aggregate به Messenger فرستاده شود؛ پیام واقعی `message_type=report_card` است و ticket مصنوعی ایجاد نمی‌شود.

Meeting، Decision، Followup و Rule repositoryهای جدا دارند. وضعیت‌های رسمی مصوبه `new`, `in_progress`, `waiting`, `done`, `verified`, `cancelled` و وضعیت محاسباتی `overdue` هستند. فقط مصوبه دارای `followup_status=verified` و `verification_status=verified` به نسخه قانون تبدیل می‌شود. انتشار نسخه جدید، نسخه فعال قبلی را غیرفعال می‌کند ولی هیچ نسخه‌ای حذف نمی‌شود.

`workers/management_governance_worker.php 200` مصوبات سررسیدگذشته را پیدا می‌کند. اعلان مسئول از `NotificationService` عبور می‌کند و `overdue_notified_at` فقط پس از موفقیت ثبت می‌شود. تخصیص follow-up و انتشار rule نیز helper اعلان اختصاصی دارند.

Rollback با توقف worker و بازگردانی code انجام می‌شود؛ marker، گزارش‌ها، پیوست‌ها، جلسات، مصوبات، follow-upها و نسخه‌های rule حذف نمی‌شوند.
