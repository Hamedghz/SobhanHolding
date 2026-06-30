# پلنر کاری شخصی

مسیر اصلی پلنر `/employee/work-planner.php` است. مسیرهای قدیمی `admin/personal-planner*.php` برای سازگاری به این مسیر هدایت می‌شوند. ویجت فقط در `/admin/employee-dashboard.php` نمایش داده می‌شود و به `employee_panel_enabled` وابسته نیست.

نماهای روزانه، هفتگی، ماهانه و فهرست بر اساس `due_date` فیلتر واقعی دارند. ایجاد، ویرایش، شروع، تکمیل، انتقال یک‌روزه مهلت و تکرار روزانه/هفتگی/ماهانه پشتیبانی می‌شود. تکمیل کار تکرارشونده، نمونه بعدی را idempotent می‌سازد. تولید قالب‌های نقش فقط از دکمه صفحه یا اجرای زمان‌بندی‌شده `WorkPlannerService::generateDailyTasksForUser` انجام می‌شود.

برای نصب امن `php install/work_planner_seed.php` اجرا شود. Contract test: `tests/personal_planner_contract_test.ps1`. برای rollback کد، redirectها و صفحه ساده جدید بازگردانده شوند؛ جدول یا داده حذف نشود.
