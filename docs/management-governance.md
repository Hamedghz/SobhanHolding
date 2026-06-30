# صورتجلسات و مصوبات

مسئولیت‌ها با چهار مرز `MeetingRepository`، `DecisionRepository`، `RuleRepository` و `FollowupRepository` جدا شده‌اند؛ facade قدیمی برای backward compatibility باقی مانده است. فرم جلسه روی عنوان، تاریخ، برگزارکننده، حاضرین، دستور جلسه، خلاصه و پیوست تمرکز دارد. سایر فیلدها و تنظیمات قانون زیر «تنظیمات پیشرفته» قرار می‌گیرند.

UI وضعیت‌ها را به `new`, `in_progress`, `waiting`, `done`, `verified`, `cancelled`, `overdue` محدود می‌کند و statusهای قدیمی فقط برای داده‌های موجود نگهداری شده‌اند. `RuleRepository` تنها مصوبات `is_rule=1` را که جلسه نهایی یا مصوبه verified است می‌پذیرد.

تغییر schema مخرب وجود ندارد. تست: `tests/management_governance_contract_test.ps1`. rollback فقط بازگردانی UI/Repositoryهای جدید است؛ نسخه قانون یا مصوبه حذف نشود.
