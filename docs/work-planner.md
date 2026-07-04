# پلنر کاری شخصی

مسیر اصلی پلنر `/employee/work-planner.php` است. مسیرهای قدیمی `admin/personal-planner*.php` برای سازگاری به این مسیر هدایت می‌شوند. ویجت فقط در `/admin/employee-dashboard.php` نمایش داده می‌شود و به `employee_panel_enabled` وابسته نیست.

نماهای روزانه، هفتگی، ماهانه و فهرست بر اساس `due_date` فیلتر واقعی دارند. افزودن سریع یک‌خطی، ویرایش inline، تکمیل یک‌کلیکی، انتقال تکی یا همه کارهای انجام‌نشده به فردا و علامت مهم پشتیبانی می‌شود. خلاصه بازه شامل تعداد انجام‌شده، در انتظار، عقب‌افتاده و درصد تکمیل است.

تکرار روزانه، هفتگی و ماهانه با root ثابت و lock دیتابیس نمونه بعدی را idempotent می‌سازد. تولید قالب‌های نقش فقط از دکمه صفحه یا اجرای زمان‌بندی‌شده `WorkPlannerService::generateDailyTasksForUser` انجام می‌شود. یادآوری‌های سررسید با `php workers/planner_reminder_worker.php 200` پردازش می‌شوند؛ اعلان فقط از `NotificationService` ساخته و پس از موفقیت، `reminder_sent_at` ثبت می‌شود تا تکراری نباشد.

برای نصب امن `php install/work_planner_seed.php` اجرا شود و `Database::repair()` ستون‌ها و index افزایشی را idempotent می‌سازد. Contract test: `tests/personal_planner_contract_test.ps1`. برای rollback ابتدا worker یادآوری متوقف و کد UI/service بازگردانده شود؛ ستون‌ها، index، taskها و markerهای ثبت‌شده حذف نشوند.
