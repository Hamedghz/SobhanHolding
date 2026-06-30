# Ticketing مستقل

Ticketing داده‌های خود را در `tickets`, `ticket_categories`, `ticket_messages`, `ticket_attachments`, `ticket_status_logs`, `ticket_assignments`, `ticket_sla_rules` نگه می‌دارد. مسیرهای کاربر `/employee/tickets.php`, `/employee/ticket-create.php`, `/employee/ticket-view.php` و مسیرهای مدیریت `/admin/tickets.php`, `/admin/ticket-categories.php`, `/admin/ticket-settings.php` هستند.

وضعیت‌ها `open`, `assigned`, `in_progress`, `waiting_user`, `waiting_admin`, `resolved`, `closed`, `cancelled` هستند. اعلان ایجاد، پاسخ، ارجاع و تغییر وضعیت با `NotificationService` ساخته می‌شود؛ Messenger محل مدیریت Ticket نیست.

پس از backup، `php install/ticketing_seed.php` اجرا شود. Migration فقط `CREATE TABLE IF NOT EXISTS`، seed شرطی و upsert مجوزها دارد. فرم‌ها CSRF دارند، رکورد برای درخواست‌کننده/مسئول scope می‌شود و upload دارای allowlist و سقف ۱۰MB است. تست: `tests/ticketing_contract_test.ps1`. برای rollback مسیرها از منو خارج و کد بازگردانده شود؛ جدول‌ها و داده‌ها نگه داشته شوند.
