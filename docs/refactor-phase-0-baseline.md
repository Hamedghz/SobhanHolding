# گزارش خط مبنا و موجودی رگرسیون بازطراحی سامانه

## مشخصات گزارش

- فاز: `Phase 0 — Baseline and Regression Inventory`
- تاریخ بررسی: `2026-07-16`
- مخزن: سامانه داخلی شرکت پخش سبحان
- معماری جاری: PHP کلاسیک، MySQL/MariaDB، رابط فارسی RTL
- دامنه این فاز: فقط بررسی، ثبت وضعیت موجود، اجرای آزمون‌های قابل اجرا و تدوین نقشه مهاجرت
- تغییر عملکردی در این فاز: ندارد
- تغییر دیتابیس در این فاز: ندارد

این گزارش وضعیت واقعی worktree فعلی را ثبت می‌کند. پیش از شروع Phase 0، worktree دارای تغییرات و فایل‌های ثبت‌نشده مرتبط با OKR، صفحه اصلی، پلنر و چند فایل دیگر بود. Phase 0 آن تغییرات را بازنویسی یا پاک نکرده و فقط همین گزارش را افزوده است.

## جمع‌بندی مدیریتی

سامانه یک bootstrap و معماری مشترک قابل اتکا دارد:

- `core/Database.php` برای اتصال، migration و repair افزایشی
- `core/Auth.php` برای ورود، مجوز، CSRF و ثبت رخداد
- `lib/OrgAccess.php` برای محدودسازی سازمانی و سلسله‌مراتب
- partialهای مشترک `views/partials/admin-header.php`، `views/partials/admin-sidebar.php` و `views/partials/admin-footer.php`
- منوی متمرکز `lib/admin_menu.php`
- رابط فارسی RTL و helperهای escape، flash و پاسخ

با این حال، بنیادهای مشترک درخواست‌شده هنوز یکپارچه نیستند. مهم‌ترین شکاف‌های فعلی:

1. تاریخ شمسی helper دارد، اما component و service واحد سراسری ندارد.
2. ۳۶ ورودی native میلادی `type="date"` در ۲۰ فایل و ۴ ورودی `datetime-local` در ۳ فایل باقی مانده است.
3. حداقل دو موتور پلنر، چند مدل اقدام، چند مدل گزارش روزانه و چند مدل دوره مستقل وجود دارد.
4. سه مسیر متفاوت برای خواندن Excel/CSV وجود دارد.
5. داشبورد CEO، مدیر فروش و سرپرست renderer، تنظیمات و منبع داده مشترک ندارند.
6. JSON داخلی در چند صفحه مستقیماً به کاربر/ادمین نمایش داده می‌شود.
7. `database/schema.sql` سالم و idempotent است، ولی از repairهای runtime عقب‌تر است.
8. Viewهای گزارش‌گیری فعال محدودند و داشبوردها هنوز به لایه View واحد متکی نیستند.
9. صفحات placeholder برای ویزیتورها، ضرایب صنف، اولویت کالا، تارگت و Viewهای گزارش‌گیری در منو موجود است.
10. محیط محلی نصب نشده و PHP CLI افزونه‌های لازم برای اجرای کامل import و DB integration را ندارد.

ریسک کل برنامه بازطراحی: **زیاد**؛ زیرا احراز دسترسی، schema، import، گزارش فروش، فرمول‌های مالی/پورسانت، پلنر و چند ماژول سازمانی را درگیر می‌کند. اجرای مرحله‌ای و backward-compatible الزامی است.

## وضعیت محیط و وابستگی‌ها

### محیط بررسی

- PHP CLI: `8.5.8`
- Docker Server: `29.5.3`
- MariaDB آزمون schema: `mariadb:11`
- `config.php`: موجود نیست
- `installed.lock`: موجود نیست
- برنامه محلی نصب‌شده: خیر
- Composer manifest/lock: موجود نیست
- Node package manifest/lock: موجود نیست

### افزونه‌های PHP موجود

افزونه‌های مهم موجود شامل `PDO`, `mysqlnd`, `dom`, `SimpleXML`, `xmlreader`, `xmlwriter`, `json` و `iconv` است.

### افزونه‌های موردنیاز ولی ناموجود در PHP CLI فعلی

- `pdo_mysql`
- `mbstring`
- `zip` / `ZipArchive`
- `fileinfo`
- `curl`
- `gd`
- `soap`

نتیجه: syntax و contractهای مستقل از محیط قابل اجرا هستند، اما اتصال واقعی برنامه به MySQL، خواندن XLSX، برخی normalizationهای فارسی، SOAP، تصویر و API در PHP CLI فعلی قابل اثبات نیست.

### وابستگی‌های frontend

- build اجباری Node وجود ندارد.
- Quill 2 به‌صورت local در assets موجود نیست.
- `@majidh1/jalalidatepicker` به‌صورت local در assets موجود نیست.
- Motion/Framer Motion به‌صورت local در assets موجود نیست.
- ورودی‌های دارای کلاس `.jalali-date-input` فقط formatter عمومی دارند و date picker کامل سراسری ندارند.

## منابع الزامی طراحی UI/UX

بازبینی تکمیلی این baseline با سه منبع طراحی انجام شد.

### UI UX Pro Max

Skill `ui-ux-pro-max` برای تولید design system و بررسی accessibility/motion استفاده شد. خروجی مناسب برای سامانه سبحان:

- الگوی اصلی: **Data-Dense Dashboard**
- شخصیت بصری: حرفه‌ای، سازمانی، فشرده و داده‌محور
- اولویت: خوانایی KPI، جدول، فیلتر و workflow با حداقل فضای غیرضروری
- رنگ پایه پیشنهادی: آبی سازمانی با accent کهربایی، پس از تطبیق با theme فعلی
- تعامل: hover/focus روشن، row highlight، tooltip، loading state و transition کوتاه
- مدت micro-interaction: ۱۵۰ تا ۳۰۰ میلی‌ثانیه
- accessibility: focus-visible، keyboard navigation، contrast حداقل WCAG AA و `prefers-reduced-motion`
- responsive checkpoints: عرض‌های ۳۷۵، ۷۶۸، ۱۰۲۴ و ۱۴۴۰ پیکسل
- anti-patternها: animation فراگیر، scroll-jacking، hover-only action، layout shift روی hover، table بدون راهکار mobile و iconهای emoji

فونت لاتین پیشنهادی خود skill (`Fira Sans/Fira Code`) برای متن فارسی مستقیماً پذیرفته نمی‌شود. typography فارسی باید از font stack موجود سامانه تبعیت کند و monospace فقط برای کد، شناسه و داده فنی استفاده شود.

### Motion / Framer Motion

مستندات رسمی جدید، Framer Motion را با نام **Motion for React** معرفی می‌کنند. نسخه React به React 18.2+ وابسته است و واردکردن آن در این مخزن با قواعد «بدون React/SPA rewrite و بدون Node build اجباری» سازگار نیست.

Motion یک API مستقل JavaScript نیز دارد. تصمیم اجرایی:

- در این مخزن از Motion JavaScript یا `motion/mini` برای DOM موجود استفاده شود، نه `motion/react`.
- نسخه دقیق library باید pin و به‌صورت local در repository نگهداری شود؛ production نباید به `latest` یا CDN خارجی وابسته باشد.
- Motion فقط برای micro-interactionهای معنادار استفاده شود: drawer، filter panel، modal، تغییر state، loading، collapse/expand و transition محدود dashboard.
- animation نباید برای submit، authorization یا نمایش نتیجه ضروری باشد؛ بدون JavaScript یا در reduced-motion، workflow باید کامل باقی بماند.
- duration پیش‌فرض UI بین ۱۵۰ تا ۳۰۰ میلی‌ثانیه باشد.
- animation تکرارشونده فقط برای loading کنترل‌شده مجاز است.
- `transform` و `opacity` بر propertyهای layout-heavy اولویت دارند.
- `prefers-reduced-motion` و امکان توقف animation الزامی است.

منبع رسمی:

- <https://motion.dev/docs/react>
- <https://motion.dev/docs/animate>

### 21st.dev

21st.dev کتابخانه و registry طراحی برای componentها، screenها و themeهای React/Tailwind است. نصب مستقیم componentهای TSX، shadcn، Radix یا Tailwind آن در PHP کلاسیک این مخزن مجاز نیست؛ مگر اینکه تغییر stack بعداً صریحاً تأیید شود.

روش استفاده در این پروژه:

- 21st.dev مرجع pattern و visual benchmark برای dashboard، table، form، date picker، sidebar، modal، tabs، tooltip، empty state، upload و KPI card باشد.
- قبل از ساخت هر component مشترک در فاز ۲، حداقل یک reference مناسب از دسته مربوط در 21st.dev بررسی شود.
- hierarchy، spacing، stateها و interactionهای مفید به HTML/CSS/JavaScript repo-native ترجمه شوند.
- کد React/TSX بدون بازنویسی و بررسی dependency/license کپی نشود.
- theme و component جدید باید با RTL، متن فارسی، shared partialها و CSS فعلی ادغام شود.
- componentهای تزئینی یا marketing-heavy برای پنل داده‌محور انتخاب نشوند.
- accessibility و responsive behavior در خود سامانه مستقلاً تست شود.

منبع رسمی:

- <https://help.21st.dev/>
- <https://docs.21st.dev/>

### قرارداد استفاده مشترک

در فازهای UI، ترتیب تصمیم‌گیری:

1. UI UX Pro Max برای design system، UX guideline و anti-pattern
2. 21st.dev برای reference بصری و pattern component
3. Motion JavaScript برای interaction و transition محدود
4. ترجمه نهایی به PHP partial، HTML semantic، CSS/JS محلی و RTL موجود

این سه منبع مکمل معماری موجود هستند و مجوز ایجاد React island، SPA، Tailwind runtime یا dependency اجباری Node محسوب نمی‌شوند.

## معماری و dependencyهای اصلی

| حوزه | پیاده‌سازی موجود | وضعیت |
|---|---|---|
| Bootstrap/DB | `core/Database.php`, `core/Config.php` | مشترک و قابل استفاده |
| Authentication | `core/Auth.php` | مشترک |
| Permission | `Auth::can()`, `Auth::requirePermission()` | مشترک، ولی seedها بین فایل مرکزی و repair ماژول‌ها پخش‌اند |
| Organizational scope | `lib/OrgAccess.php` | مشترک و در چند ماژول فعال |
| CSRF | `Auth::csrfToken()`, `Auth::verifyCsrf()` | الگوی غالب |
| Layout | `views/partials/admin-header.php`, `views/partials/admin-sidebar.php`, `views/partials/admin-footer.php` | مشترک |
| Menu | `lib/admin_menu.php` | registry متمرکز |
| Database migration | `Database::migrate()` و `Database::repair()` | افزایشی، runtime-driven |
| Fresh install schema | `database/schema.sql` | سالم ولی ناقص‌تر از repair runtime |
| Persian date | `core/JalaliDate.php` و helperهای format | پراکنده و فاقد service واحد |
| Import | `SpreadsheetImportReader`, `CeoDashboardExcel`, `SpreadsheetRows` و parserهای صفحه‌ای | چندگانه |
| Planner | `work_planner_*` و `personal_planner_*` | دو subsystem |
| Reporting | repositoryها، جدول‌های گزارش و تعدادی View | فاقد View layer واحد |
| UI | `assets/css/app.css` و CSSهای ماژولی | تم مشترک دارد، component contract واحد ندارد |

`Database::migrate()` علاوه بر schema پایه، repair ماژول‌های Dashboard، HR، Org، Planner، OKR، Theme، Letters، Email، SMS، Workforce، Management Reports/Meetings، Attendance، Backup، Notification، Sales Offer، Sales Data و Sales Reference را فراخوانی می‌کند. در نتیجه بخشی از صحت fresh install به اجرای runtime repair وابسته است.

## موجودی مسیرها و فایل‌های مرتبط

### داشبوردها

- `admin/index.php`
- `admin/ceo-dashboard.php`
- `admin/ceo-dashboard-lines.php`
- `admin/ceo-dashboard-visitors.php`
- `admin/ceo-dashboard-settings.php`
- `admin/manager-dashboard.php`
- `admin/manager-dashboard-import.php`
- `admin/manager-dashboard-export.php`
- `admin/manager-dashboard-image-export.php`
- `admin/manager-dashboard-settings.php`
- `admin/supervisor-dashboard.php`
- `admin/supervisor-sales-report.php`
- `admin/supervisor-settings.php`
- `admin/employee-dashboard.php`

### پلنر و وظایف

- `admin/work-planner.php`
- `admin/work-planner-templates.php`
- `employee/work-planner.php`
- `employee/work-planner-simple.php`
- `views/partials/work-planner-widget.php`
- `services/WorkPlannerService.php`
- `core/WorkPlannerModule.php`
- `admin/personal-planner.php`
- `admin/personal-planner-settings.php`
- `admin/personal-planner-report.php`
- `admin/ajax/personal-planner.php`
- `admin/cron/personal_planner.php`
- `admin/includes/personal-planner-widget.php`
- `services/PersonalPlannerService.php`

### اقدام، اسکریپت و عملیات فروش

- `admin/sales-actions.php`
- `admin/sales-scripts.php`
- `admin/sales-script-fields.php`
- `admin/supervisor-actions.php`
- `admin/supervisor-action-view.php`
- `admin/sales-manager-daily-work-log.php`
- `admin/sales-manager-supervisor-reports.php`
- `admin/sales-purchase-suggestions.php`
- `services/SalesOperationsService.php`
- `core/SalesOperationsModule.php`

### گزارش‌های مدیریتی و جلسات

- `admin/management-report-prepare.php`
- `admin/management-report-template-settings.php`
- `admin/management-reports.php`
- `admin/management-report-view.php`
- `admin/management-report-attachment.php`
- `lib/ManagementReportsRepository.php`
- `admin/management-meetings.php`
- `admin/management-meeting-view.php`
- `admin/management-decisions.php`
- `admin/management-decision-view.php`
- `admin/management-decision-edit.php`
- `lib/ManagementMeetingsRepository.php`

### Import و داده فروش

- `admin/sales-aggregate-import.php`
- `admin/inventory-aggregate-import.php`
- `admin/sales-data-index.php`
- `admin/sales-data-batches.php`
- `admin/sales-data-errors.php`
- `admin/sales-data-mapping.php`
- `admin/sales-reference-batches.php`
- `admin/sales-reference-errors.php`
- `admin/sales-reference-status.php`
- `admin/manager-dashboard-import.php`
- `admin/users-import-export.php`
- `admin/payroll-import.php`
- `admin/pharmacy-settings.php`
- `admin/ceo-dashboard-settings.php`
- `core/SpreadsheetImportReader.php`
- `core/CeoDashboardExcel.php`
- `lib/SpreadsheetRows.php`
- `core/SalesAggregateImportService.php`
- `core/InventoryImportService.php`
- `core/SalesDataNormalizer.php`
- `core/SalesDataSchema.php`
- `core/SalesReferenceSchema.php`

### حضور و غیاب

- `admin/hr-attendance.php`
- `admin/hr-attendance-settings.php`
- `admin/hr-attendance-reports.php`
- `admin/hr-holidays.php`
- `employee/my-attendance.php`
- `core/HrAttendanceModule.php`
- `lib/HrAttendanceRepository.php`

### مکاتبات اداری

- `admin/letters.php`
- `admin/letter-create.php`
- `admin/letter-view.php`
- `admin/letter-templates.php`
- `admin/letter-letterheads.php`
- `admin/letter-signatures.php`
- `admin/letter-settings.php`
- `admin/letter-print.php`
- `admin/letter-pdf.php`
- `admin/letter-asset.php`
- `core/LetterModule.php`

### Sobhan AI

- `admin/sobhan-api-settings.php`
- `admin/ai-chat.php`
- `admin/ai-insights.php`
- `admin/actions/dashboard-refresh.php`
- `admin/actions/ai-test-connection.php`
- `admin/actions/ai-run-update.php`
- `admin/actions/ai-update-status.php`
- `admin/actions/knowledge-index-status.php`
- `core/AiUpdateService.php`
- `core/SobhanApiClient.php`

### کاربران و ساختار سازمانی

- `admin/users.php`
- `admin/users-import-export.php`
- `admin/sales-structure.php`
- `admin/hr-settings.php`
- `core/OrgModule.php`
- `lib/OrgAccess.php`

### OKR موجود در worktree

- `admin/okr.php`
- `admin/okr-cycles.php`
- `admin/okr-objective.php`
- `admin/okr-evidence.php`
- `core/OkrModule.php`
- `lib/OkrService.php`
- `assets/css/okr.css`
- `docs/okr.md`

این فایل‌ها پیش از Phase 0 در worktree وجود داشتند و در این فاز تغییر نکردند.

## موجودی دیتابیس

### نتیجه شمارش و اعتبارسنجی

- `database/schema.sql`: ۱۴۳ دستور `CREATE TABLE IF NOT EXISTS`
- View داخل fresh schema: ۱ مورد
- import اول schema در MariaDB 11: موفق
- import دوم همان schema روی همان دیتابیس: موفق
- تعداد نهایی جدول‌ها در آزمون isolated: ۱۴۳
- تعداد نهایی Viewها در آزمون isolated: ۱
- `DROP` یا `TRUNCATE` در schema: صفر
- نام‌های جدول/شیء مدیریت‌شده در schema و repairهای runtime: حدود ۱۸۹ مورد

### اختلاف fresh schema و runtime repair

بررسی ایستا ۴۶ جدول را شناسایی کرد که repairهای PHP برای آن‌ها تعریف دارند، ولی در `database/schema.sql` موجود نیستند:

`ai_insight_requests`, `ai_reporting_sources`, `ai_update_jobs`, `dashboard_data_cache`,
`hr_kpi_criteria`, `hr_kpi_employee_assignments`, `hr_kpi_form_templates`, `hr_kpi_forms`,
`hr_kpi_periods`, `hr_kpi_score_logs`, `hr_kpi_scores`, `hr_kpi_templates`,
`knowledge_index_jobs`, `maintenance_logs`,
`manager_dashboard_ai_logs`, `manager_dashboard_ai_skills`, `manager_dashboard_reports`,
`manager_dashboard_widget_settings`,
`sales_action_values`, `sales_actions`, `sales_geographies`, `sales_line_brands`, `sales_lines`,
`sales_manager_daily_work_logs`, `sales_purchase_suggestion_logs`, `sales_purchase_suggestions`,
`sales_reference_import_batches`, `sales_reference_import_errors`,
`sales_script_assignments`, `sales_script_fields`, `sales_scripts`,
`sales_structure_audit_logs`, `sales_supervisor_report_values`, `sales_supervisor_reports`,
`sales_team_assignments`, `sales_visitor_territories`, `staging_sales_reference_rows`,
`supervisor_action_field_templates`, `supervisor_action_logs`, `supervisor_actions`,
`supervisor_script_sections`, `work_planner_comments`, `work_planner_task_logs`,
`work_planner_tasks`, `work_planner_templates`, `work_planner_user_preferences`.

اثر: fresh install پس از import schema برای رسیدن به ساختار کامل به `Database::repair()` وابسته است. این اختلاف در فازهای بعد باید فقط با DDL افزایشی و idempotent برطرف شود.

### جدول‌های مرتبط با import

#### فروش و موجودی تجمیعی

- `sales_import_batches`
- `sales_import_errors`
- `staging_sales_aggregate_rows`
- `sales_aggregate_rows`
- `sales_column_mappings`
- `inventory_import_batches`
- `inventory_import_errors`
- `staging_inventory_aggregate_rows`
- `inventory_aggregate_rows`
- `inventory_column_mappings`

#### داده مرجع

- `sales_reference_import_batches`
- `sales_reference_import_errors`
- `staging_sales_reference_rows`
- جدول‌های مرجع خطوط، جغرافیا، برند و تخصیص تیم

### جدول‌های داشبورد

#### CEO

- `ceo_dashboard_lines`
- `ceo_dashboard_visitors`
- `ceo_dashboard_manual_metrics`
- `pharmacy_dashboard_metrics`
- `site_settings`

#### مدیر فروش

- `manager_dashboard_reports`
- `manager_dashboard_widget_settings`
- `manager_dashboard_ai_skills`
- `manager_dashboard_ai_logs`
- `manager_commission_summary`
- `manager_team_target_achievement`
- `manager_over_achievement_bonus`
- `manager_visitor_penalty_coefficients`
- `manager_supervisor_penalty_coefficients`
- `manager_customer_target_achievement`
- `manager_customer_coverage`
- `manager_brand_target_achievement`
- `manager_line_performance`
- `manager_supervisor_performance`
- `manager_sales_manager_performance`

### جدول‌های اقدام، اسکریپت، گزارش و پلنر

- `sales_actions`
- `sales_action_values`
- `supervisor_actions`
- `supervisor_action_field_templates`
- `supervisor_action_logs`
- `sales_scripts`
- `sales_script_fields`
- `sales_script_assignments`
- `sales_manager_daily_work_logs`
- `sales_supervisor_reports`
- `sales_supervisor_report_values`
- `management_report_templates`
- `management_report_sections`
- `management_report_fields`
- `management_report_submissions`
- `management_report_values`
- `management_report_review_logs`
- `work_planner_tasks`
- `work_planner_templates`
- `work_planner_task_logs`
- `work_planner_comments`
- `work_planner_user_preferences`
- `personal_planner_tasks`
- `personal_planner_notes`
- `personal_planner_check_items`
- `personal_planner_settings`
- `personal_planner_logs`

### جدول‌های دوره مستقل

- `ceo_dashboard_periods`
- `hr_kpi_periods`
- `payroll_periods`
- `management_report_periods`
- `okr_cycles`

جدول مشترک `system_periods` وجود ندارد.

### حضور و غیاب

- `hr_work_groups`
- `hr_attendance_settings`
- `hr_month_holidays`
- `hr_attendance_entries`
- `hr_attendance_logs`

### مکاتبات

جداول نامه، گیرنده، پیوست، قالب، سربرگ، امضا، ارجاع، رخداد و تنظیمات از طریق `LetterModule` مدیریت می‌شوند. upload و sanitization وجود دارد، ولی مدل فعلی سربرگ و editor با نیاز فاز ۱۳ کامل منطبق نیست.

### OKR

- `okr_cycles`
- `okr_objectives`
- `okr_key_results`
- `okr_alignments`
- `okr_checkins`
- `okr_initiatives`
- `okr_task_links`
- `okr_comments`
- `okr_approvals`
- `okr_evidence`
- `okr_score_history`
- `okr_settings`

## Viewهای گزارش‌گیری

### View موجود در fresh schema

- `vw_hr_attendance_monthly_summary`

### Viewهای ساخته‌شده توسط repair داده فروش

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

این Viewها به active committed batch متکی‌اند. View layer هدف فاز ۱۶ هنوز کامل نیست و نام‌ها/قراردادهای موردنیاز داشبوردها به‌صورت واحد پیاده نشده‌اند.

## مجوزهای جاری

مجوزها در seed مرکزی و repair ماژول‌ها پخش‌اند. گروه‌های مرتبط فعلی:

### Dashboard

- `manager_dashboard.view`
- `manager_dashboard.import`
- `manager_dashboard.export`
- `manager_dashboard.settings`
- مجوزهای CEO و dashboard عمومی موجود در seed پایه

### Sales Data

- `sales_data_view`
- `sales_data_import`
- `sales_data_manage_mapping`
- `sales_data_view_errors`
- `sales_data_sync_ai`
- `sales_data_manage_formulas`
- `sales_data_view_reports`
- `sales_data_run_commission`

### Sales Reference

- `sales_reference.upload`
- `sales_reference.commit`
- `sales_reference.view_batches`
- `sales_reference.view_errors`
- `sales_reference.manage_active_batch`
- `sales_reference.view_status`

### عملیات فروش

- `supervisor.panel.view`
- `supervisor.sales.view`
- `supervisor.actions.manage`
- `sales_manager.supervisors.view`
- `sales_manager.supervisor_actions.review`
- `sales_manager.scripts.manage`
- `sales_manager.actions.manage`
- `sales_manager.purchase_suggestions.manage`
- `sales_manager.daily_logs.manage`
- `admin.supervisor_settings.manage`

### Planner

- `work_planner.view`
- `work_planner.assign`
- `work_planner.manage`
- `work_planner.templates`
- `personal_planner`
- `personal_planner.reports_all`

### Management Reports

- مجوزهای جداگانه فروش، مالی، انبار، فناوری، تجمیع، review و template

### Attendance

- `hr_attendance`
- `hr_attendance.settings`
- `hr_attendance.reports`
- `hr_attendance.own`

### Letters

- `organizational_letters`
- `letters.sign`
- `letters.issue`
- `letters.archive`
- `letters.confidential`
- `letters.settings`

### OKR

- `okr.view`
- `okr.manage`
- `okr.approve`
- `okr.checkin`
- `okr.cycles`

### ریسک permission

- منو فقط لایه نمایش است و نباید مبنای authorization باشد.
- بیشتر مسیرهای بررسی‌شده server-side permission/CSRF دارند، ولی در هر فاز باید route و action مجدداً کنترل شوند.
- پراکندگی seed permission بین `install/seeds/007_permissions_seed.php` و repair ماژول‌ها می‌تواند fresh install و محیط upgrade را متفاوت کند.
- role scope در بخشی از عملیات با `OrgAccess` اعمال می‌شود، اما reporting repository واحد برای همه داشبوردها وجود ندارد.

## وضعیت صفحات: کارا، ناقص و تأییدنشده

### تأییدشده با syntax یا contract

- همه ۳۳۵ فایل PHP از `php -l` عبور کردند.
- contract داشبورد/پلنر پاس شد.
- contractهای Sales Data foundation، Sales Reference، Sales Operations، Sales Structure و Sales Offer Budget پاس شدند.
- contractهای OKR، SMS، Attendance، HR Assessment، Management Governance، Personal Planner، Homepage Slider و Messenger Removal پاس شدند.
- schema دو بار روی MariaDB 11 import شد.

### پیاده‌سازی‌شده ولی بدون اثبات runtime/browser در این محیط

- داشبوردهای CEO، Manager و Supervisor
- صفحات import با دیتابیس واقعی
- مکاتبات، حضور و غیاب، گزارش مدیریتی و OKR
- اتصال Sobhan AI، SOAP/SMS و Email
- permission matrix واقعی با چند کاربر و چند role

علت: برنامه نصب نیست، config و lock نصب موجود نیست، PHP CLI افزونه DB/XLSX ندارد و session/roleهای مرورگر آماده نیستند.

### صفحات یا جریان‌های صریحاً ناقص در کد

`admin/sales-data-index.php` برای این منابع فقط placeholder است:

- ورود اطلاعات ویزیتورها
- ضرایب صنف
- اولویت کالا
- تارگت فروش
- Viewهای گزارش‌گیری
- همگام‌سازی SobhanAI در همان صفحه

همچنین:

- importer خرید `tblbuy` وجود ندارد.
- importer حضور و غیاب وجود ندارد.
- Action Hub واحد وجود ندارد.
- Daily Work Report generic وجود ندارد.
- Formula Engine واحد وجود ندارد.
- dashboard renderer/settings واحد وجود ندارد.
- indicator فشرده Sobhan AI در header وجود ندارد.
- Quill 2 local برای نامه وجود ندارد.
- date picker شمسی local سراسری وجود ندارد.

### regression موجود

`tests/ticketing_contract_test.ps1` به `employee/message-inbox.php` وابسته است، در حالی که فایل وجود ندارد. تست قبل از رسیدن به assertionهای اصلی fail می‌شود. این موضوع در Phase 0 ثبت شد و چون خارج از محدوده refactor جاری است، تغییری در ticketing انجام نشد.

## ناسازگاری‌های تاریخ

### وضعیت موجود

- `core/JalaliDate.php` تبدیل، normalization و validation پایه را ارائه می‌کند.
- helperهای `format_jalali_date()` و `format_jalali_datetime()` در چند صفحه استفاده می‌شوند.
- کلاس `.jalali-date-input` در ۱۸ فایل و ۴۰ occurrence دیده شد.
- این کلاس در JavaScript فعلی عمدتاً formatter ورودی است، نه date picker واحد با range، min/max و event contract.
- service واحدی مانند `AppDate` برای تاریخ جاری، ابتدای/انتهای دوره، range و datetime وجود ندارد.

### native Gregorian inputs

۳۶ occurrence از `type="date"` در ۲۰ فایل:

- `admin/email-inbox.php`
- `admin/includes/personal-planner-widget.php`
- `admin/letter-create.php`
- `admin/letters.php`
- `admin/management-decision-edit.php`
- `admin/management-decision-view.php`
- `admin/management-decisions.php`
- `admin/management-meeting-view.php`
- `admin/management-reports.php`
- `admin/payroll-periods.php`
- `admin/personal-planner-report.php`
- `admin/sales-actions.php`
- `admin/sales-manager-daily-work-log.php`
- `admin/sales-manager-supervisor-reports.php`
- `admin/sales-offer-budget.php`
- `admin/sales-purchase-suggestions.php`
- `admin/sms-messages.php`
- `admin/supervisor-actions.php`
- `admin/supervisor-dashboard.php`
- `admin/supervisor-sales-report.php`

۴ occurrence از `datetime-local` در:

- `admin/email-compose.php`
- `admin/includes/personal-planner-widget.php`
- `employee/ticket-view.php`

### دوره‌های تکراری

دوره‌ها در چند جدول و چند قرارداد جدا تعریف می‌شوند. انتخاب دوره، تبدیل تاریخ و محاسبه start/end باید در فاز ۱ متمرکز شود، بدون تبدیل destructive داده‌های ذخیره‌شده.

## JSONهای نمایش‌داده‌شده در UI

JSON داخلی در ۷۲ فایل PHP مرجع دارد؛ وجود JSON داخلی به‌خودی‌خود مشکل نیست. مواردی که کاربر/ادمین اکنون مستقیماً JSON را ویرایش می‌کند:

- `admin/hr-assessment-tests.php`: `options_json`
- `admin/sales-script-fields.php`: `options_json`
- `admin/management-report-template-settings.php`: گزینه‌ها/ساختار JSON
- `admin/manager-dashboard-settings.php`: `input_schema_json` و `output_schema_json`
- `admin/email-providers.php`: `oauth_config_json`

در فازهای بعد JSON باید برای compatibility و snapshot داخلی حفظ شود، ولی UI باید builder، repeater، select یا فرم قابل فهم ارائه کند.

## وضعیت import

### پیاده‌سازی‌های مجزا

1. `core/SpreadsheetImportReader.php`
   - مورد استفاده فروش و موجودی تجمیعی
   - پشتیبانی XLSX/CSV
   - کنترل extension/MIME/size
   - table reference و sheet/header detection
2. `core/CeoDashboardExcel.php` و `lib/SpreadsheetRows.php`
   - مورد استفاده داشبوردها و چند import دیگر
3. parserهای CSV یا mapping صفحه‌ای
   - از جمله بخش‌هایی از تنظیمات CEO و importهای ماژولی

### قابلیت‌های فعلی فروش و موجودی

- تشخیص منبع به‌ترتیب table، sheet و header
- عدم وابستگی اصلی به filename
- normalization اعداد فارسی/عربی، ی/ک، فاصله‌ها و aliasهای header
- staging
- preview/validation
- `raw_json`
- batch history
- file hash
- duplicate mode
- commit
- active batch switching
- rollback قبل از commit
- Viewهای active batch

### محدودیت‌ها

- parser واحد برای همه importها وجود ندارد.
- پردازش chunk/stream عمومی وجود ندارد.
- `SpreadsheetImportReader` داده‌های populated sheet را در حافظه می‌خواند؛ رفتار workbookهای بسیار بزرگ بدون fixture واقعی اثبات نشده است.
- sample workbookهای ذکرشده در درخواست به این task پیوست نشده‌اند؛ profile واقعی Sales/Purchase/Inventory/Attendance قابل اجرای مستقیم نبود.
- خرید و حضور و غیاب importer ندارند.
- users و payroll batchهای مستقل دارند.
- سقف upload متمرکز نیست:
  - import اصلی حدود ۲۵ MB
  - users حدود ۸ MB
  - letter attachment حدود ۱۰ MB
  - letterhead حدود ۳ MB
- تنظیمات `max_excel_upload_mb` و `max_letter_attachment_mb` و نمایش effective PHP limit وجود ندارد.

### failureهای فعلی آزمون import

- `inventory_import_contract_test.php`: به‌دلیل نبود `mbstring`
- `sales_aggregate_import_contract_test.php`: به‌دلیل نبود `mbstring`
- `inventory_import_integration_test.php`: fixture موجود نیست
- `sales_aggregate_xlsx_integration_test.php`: fixture XLSX موجود نیست
- `sales_aggregate_import_integration_test.php`: برنامه نصب نیست

این failureها به‌عنوان baseline محیط ثبت شده‌اند و معادل خرابی قطعی production تلقی نمی‌شوند.

## منابع فعلی داشبورد

### CEO Dashboard

منابع اصلی:

- `ceo_dashboard_lines`
- `ceo_dashboard_visitors`
- `ceo_dashboard_manual_metrics`
- `pharmacy_dashboard_metrics`
- `site_settings`

ورود CSV/Excel و metric دستی مستقل دارد.

### Manager Dashboard

منابع اصلی:

- `manager_dashboard_reports`
- جدول‌های متعدد `manager_*`
- `manager_dashboard_widget_settings`
- skill/schemaهای AI
- `site_settings`

ورود Excel، export، تنظیمات widget، ویرایش/حذف ردیف و calculator مستقل دارد.

### Supervisor Dashboard

بخشی از summary را از `ceo_dashboard_visitors` و اقدامات را از `supervisor_actions` می‌خواند. تنظیمات `supervisor-settings.php` بیشتر mapping تیم است و engine مشترک widget settings نیست.

### Employee Dashboard

`admin/employee-dashboard.php` به dashboard عمومی هدایت می‌کند و dashboard renderer مستقل کامل ندارد.

### نتیجه

- renderer مشترک وجود ندارد.
- تنظیمات نقش‌محور مشترک وجود ندارد.
- dashboard source registry واحد وجود ندارد.
- منبع داده بخشی manual و بخشی module-specific است.
- داشبوردها هنوز به Viewهای active import به‌صورت یکپارچه متصل نیستند.

## پراکندگی فرمول‌ها

فرمول و محاسبه در چند محل مستقل است:

- `core/ManagerDashboardCalculator.php`
- `services/SalesOfferBudgetService.php`
- formula fieldهای payroll
- محاسبات OKR
- محاسبات attendance
- محاسبات KPI و گزارش‌های دیگر

موتور مشترکی با parser امن، allowlist متغیرها، version، snapshot، preview، test cases، audit log و rollback وجود ندارد. تغییر منطق پورسانت/هدف/مالی بدون تأیید انسانی و آزمون داده واقعی ممنوع است.

## پراکندگی اقدام، اسکریپت، گزارش روزانه و پلنر

### اقدام

- `sales_actions` + `sales_action_values`
- `supervisor_actions` + field template/log
- `sales_scripts` + fields + assignments
- تصمیمات جلسات مدیریتی
- initiativeهای OKR
- taskهای پلنر

`SalesOperationsService` می‌تواند بعضی sales/supervisor actionها را به `work_planner_tasks` لینک کند، اما source modelها همچنان جدا هستند.

### گزارش روزانه

- `sales_manager_daily_work_logs` با `fields_json`
- `sales_supervisor_reports` و value table
- management report submissions
- personal planner report
- work planner logs/comments

Generic Daily Work Report engine، template version و snapshot مشترک وجود ندارد.

### پلنر

دو subsystem موازی:

1. `work_planner_*`
2. `personal_planner_*`

پلنر کاری دارای وظیفه، template، scope، status، comment، log، recurrence و link به OKR است. پلنر شخصی دارای task/note/check/settings/log و AJAX مستقل است.

### رفتار Start

در `WorkPlannerService::updateStatus()` شروع وظیفه فقط status را به `in_progress` تغییر می‌دهد. ستون `started_at` در schema `work_planner_tasks` وجود ندارد؛ بنابراین زمان شروع واقعی ثبت نمی‌شود.

### visibility

widget، صفحه کارمند و صفحه مدیریت queryهای جداگانه دارند. contract فعلی integrity پاس شده، ولی visibility و ordering باید پس از افزودن `started_at` با roleهای واقعی مجدداً تست شود.

## حضور و غیاب

- employee relation به `users` متصل است و جدول کارمند موازی اصلی دیده نشد.
- `employee_no` وجود دارد؛ `kara_system_code` وجود ندارد.
- وضعیت‌های present, absent, leave, mission, holiday و half-day پشتیبانی می‌شوند.
- برای present ورود/خروج کنترل می‌شود.
- holiday هنوز به‌عنوان status قابل انتخاب دستی است.
- وضعیت مستقل «حضور در تعطیلی» و rule یکپارچه آن وجود ندارد.
- importer Excel و normalization عمومی زمان وجود ندارد.
- تعطیلات سازمانی/رسمی در جدول جدا ذخیره می‌شود و در محاسبات اثر دارد.

## کاربران و ساختار فروش

- `users.employee_no` موجود است.
- `users.kara_system_code` موجود نیست.
- `sales_line` در کاربران هنوز مقدار متنی است.
- `admin/users.php` برای sales line از input/datalist استفاده می‌کند، نه select اجباری از master table.
- manager/supervisor به‌صورت select کنترل‌شده‌تر هستند.
- `sales_team_assignments` بخشی از hierarchy را جداگانه نگه می‌دارد.
- master tableهای `sales_lines`, `sales_geographies`, `sales_line_brands` وجود دارند، ولی همه فرم‌ها به آن‌ها متصل نیستند.

## مکاتبات اداری

نقاط مثبت:

- private upload
- MIME/extension control
- permission checks
- HTML sanitization
- asset route کنترل‌شده

شکاف‌ها:

- editor فعلی contenteditable و `document.execCommand` است، نه Quill 2 local.
- تاریخ نامه و فیلترها native Gregorian هستند.
- سربرگ PDF پشتیبانی نمی‌شود.
- سقف و تنظیمات upload متمرکز نیست.
- تنظیمات پیش‌فرض سربرگ، margin و positioning مطابق مدل هدف کامل نیست.

## Sobhan AI

- health call، timeout، retry و cache در serviceهای AI وجود دارد.
- صفحات dashboard بعضی source/statusها را نشان می‌دهند.
- indicator کوچک سراسری در header با tooltip، زمان آخرین refresh و وضعیت cached وجود ندارد.
- در پیاده‌سازی فاز ۱۵ نباید secret، URL داخلی یا raw error در tooltip/UI نمایش داده شود.

## UI duplication

تم پایه مشترک است، اما ماژول‌ها CSS و layout مستقل زیادی دارند:

- Personal Planner
- Management Reports
- Letters
- Workforce/Payroll
- Attendance
- OKR
- Dashboardهای CEO/Manager

الگوهای تکراری:

- card/grid متفاوت برای فرم‌های مشابه
- header و action barهای ماژولی
- filter formهای متفاوت
- status badgeهای نامتجانس
- drawer/details/side panelهای مستقل
- responsive ruleهای محلی
- date inputهای متفاوت
- editor و dynamic-field UIهای متفاوت

فاز ۲ باید tokenها و componentهای کوچک مشترک را افزایشی تعریف کند و نباید CSS همه ماژول‌ها را یک‌باره بازنویسی کند.

## نتایج آزمون‌ها

### PHP syntax

- اجراشده: `php -l` روی همه فایل‌های PHP
- کل: ۳۳۵
- پاس: ۳۳۵
- fail: صفر

### PHP contract/integration tests

پاس‌شده: ۱۰

- `dashboard_planner_integrity_contract_test.php`
- `hr_attendance_calculation_test.php`
- `okr_module_contract_test.php`
- `sales_data_foundation_contract_test.php`
- `sales_offer_budget_contract_test.php`
- `sales_operations_contract_test.php`
- `sales_reference_import_contract_test.php`
- `sales_structure_contract_test.php`
- `sms_gateway_response_test.php`
- `sms_module_contract_test.php`

اجراشده ولی تکمیل‌نشده/مسدود: ۶

- `inventory_import_contract_test.php`: `mb_strtolower()` موجود نیست.
- `inventory_import_integration_test.php`: fixture موجود نیست.
- `sales_aggregate_import_contract_test.php`: `mb_strtolower()` موجود نیست.
- `sales_aggregate_import_integration_test.php`: application نصب نیست.
- `sales_aggregate_xlsx_integration_test.php`: fixture موجود نیست.
- `sms_module_db_integration_test.php`: متغیر `SMS_TEST_DSN` تنظیم نشده است.

`hr_attendance_calculation_test.php` پاس شد، ولی PHP 8.5 هشدار deprecation برای `ReflectionMethod::setAccessible()` نمایش داد.

### PowerShell contract tests

پاس‌شده: ۶

- `homepage_slider_contract_test.ps1`
- `hr_assessment_20_battery_contract_test.ps1`
- `hr_attendance_contract_test.ps1`
- `management_governance_contract_test.ps1`
- `messenger_removal_contract_test.ps1`
- `personal_planner_contract_test.ps1`

fail: ۱

- `ticketing_contract_test.ps1`: فایل `employee/message-inbox.php` موجود نیست.

### delimiter helper

`tests/php_delimiter_check.py` روی همه PHPها اجرا شد و دو false positive برای `core/LetterModule.php` و `core/CeoDashboardExcel.php` گزارش کرد. هر دو فایل به‌طور مستقل با parser واقعی PHP (`php -l`) پاس شدند. helper ساده delimiter در regex/quoted strings این فایل‌ها محدودیت دارد و نتیجه آن جایگزین PHP parser نیست.

### Schema

- MariaDB 11 isolated container
- import اول: پاس
- import دوم: پاس
- جدول نهایی: ۱۴۳
- View نهایی: ۱
- destructive DDL در schema: مشاهده نشد

### آزمون‌های اجرا‌نشده

- login/browser flow
- matrix نقش‌های مدیرعامل، مدیر فروش، سرپرست، ویزیتور و کارمند
- CSRF runtime با session واقعی
- DB integration برنامه
- import با sample workbookهای واقعی
- performance/memory import بزرگ
- Sobhan AI live health
- SOAP/SMS live provider
- email IMAP/SMTP
- rendering موبایل/RTL در مرورگر

## ملاحظات امنیتی

- الگوهای Auth، permission، CSRF، prepared statement و escaping در معماری موجود قابل استفاده‌اند.
- Phase 0 هیچ مسیر write، permission یا schema را تغییر نداده است.
- raw error نباید در فازهای بعد به UI منتقل شود.
- componentهای React/TSX متعلق به 21st.dev نباید مستقیم وارد runtime PHP شوند.
- Motion نباید از CDN با نسخه `latest` بارگذاری شود و باید fallback بدون animation داشته باشد.
- JSON config، formula، import mapping و AI source باید server-side allowlist و validation داشته باشند.
- scope هر dashboard/report/action باید علاوه بر UI در query/service اعمال شود.
- import باید MIME، extension، size، hash، row limits، staging و transaction کنترل‌شده را حفظ کند.
- formula و AI نباید امکان SQL یا expression دلخواه بدون parser محدود را بدهند.
- فایل‌های نامه و evidence باید خارج از public path یا از route کنترل‌شده ارائه شوند.

## نقشه مهاجرت پیشنهادی

ترتیب زیر همان ترتیب فازهای درخواست است. هر فاز باید migration افزایشی، compatibility adapter، تست هدفمند و rollback note مستقل داشته باشد.

### فاز ۱: تاریخ شمسی سراسری

- افزودن `AppDate` روی `JalaliDate`
- افزودن `system_periods`
- قراردادن datepicker local
- adapter برای ورودی‌های قدیمی
- نگهداری canonical date در DB
- migration تدریجی ۲۰ فایل native date

### فاز ۲: UI فشرده مشترک

- tokenهای spacing، typography، badge، card، filter، table و responsive
- تدوین component contract بر پایه خروجی UI UX Pro Max
- بررسی referenceهای مرتبط 21st.dev پیش از ساخت هر component مشترک
- افزودن نسخه pin‌شده و local از Motion JavaScript/`motion/mini`
- تعریف motion tokenهای duration، easing و reduced-motion
- استفاده تدریجی در صفحات هدف
- عدم حذف CSS ماژولی تا تکمیل regression

### فاز ۳: تنظیمات داشبورد نقش‌محور

- dashboard registry
- widget registry
- تنظیمات per-role/per-user
- renderer مشترک
- adapter برای جدول‌های تنظیمات فعلی

### فاز ۴: Formula Engine

- parser محدود و بدون `eval`
- variable/function allowlist
- version/snapshot/audit
- preview و test cases
- adapter برای فرمول‌های dashboard، offer، payroll و KPI

### فاز ۵: Excel Import Engine

- source profile registry
- table/sheet/header detection
- normalized headers
- staging/validation/preview/commit
- chunk processing
- batch identity/hash/deduplication
- upload settings مشترک
- compatibility با batchهای موجود

### فاز ۶: کاربران و داده سازمانی فروش

- `kara_system_code`
- اتصال sales line به master data
- mapping کاربر/ویزیتور/سرپرست/مدیر
- import ویزیتور واقعی
- جلوگیری از hierarchy تکراری

### فاز ۷: ضرایب، اولویت‌ها و تارگت‌ها

- جایگزینی placeholderها با table/form/import واقعی
- effective-date/version
- scope و audit
- اتصال به Formula Engine

### فاز ۸: Universal Action Hub

- action type/template/action/value/link
- adapter برای sales action، supervisor action، decision، OKR initiative و planner task
- عدم حذف جدول‌های قدیمی تا عبور compatibility gate

### فاز ۹: اقدامات داشبورد سرپرست

- نمایش Action Hub با scope تیم
- فیلتر، وضعیت، quick action و link به planner
- حفظ redirect/adapter مسیرهای قدیمی

### فاز ۱۰: Daily Work Report عمومی

- template/section/field/version
- submission/value/snapshot
- اتصال action
- adapter برای daily log و supervisor report فعلی

### فاز ۱۱: گزارش‌های مدیریتی

- استفاده از builder مشترک
- حذف نیاز UI به JSON
- source binding کنترل‌شده
- approval/review/audit

### فاز ۱۲: حضور و غیاب و کارکرد من

- import profile حضور و غیاب
- normalization زمان
- status rule واحد
- holiday و attendance-on-holiday
- mapping با `employee_no` و `kara_system_code`

### فاز ۱۳: مکاتبات اداری

- Quill 2 local
- date component مشترک
- سربرگ image/PDF
- تنظیمات margin/position/default
- upload limit مشترک
- حفظ sanitizer و routeهای فعلی

### فاز ۱۴: Planner

- افزودن `started_at`
- اصلاح start/visibility/order
- تعیین قرارداد نهایی بین work planner و personal planner
- adapter و migration غیرمخرب
- عدم حذف یکی از دو subsystem تا عبور acceptance

### فاز ۱۵: وضعیت Sobhan AI

- cache status مشترک
- indicator کوچک در header
- tooltip امن
- refresh زمان‌بندی‌شده با timeout
- عدم افشای URL، token یا raw error

### فاز ۱۶: Viewهای گزارش‌گیری

- Viewهای canonical و versioned
- active-batch-only sources
- scope-safe repositories
- قرارداد metric واحد
- مهاجرت تدریجی dashboardها از جدول‌های manual/module-specific

## وابستگی بین فازها

- فاز ۱ پیش‌نیاز تاریخ در همه فازهای UI و workflow است.
- فاز ۲ پیش‌نیاز UI builderهای فاز ۳، ۴، ۸، ۱۰ و ۱۱ است.
- UIهای فاز ۲ به بعد باید قرارداد UI UX Pro Max + 21st.dev reference + Motion JavaScript را رعایت کنند.
- فاز ۴ باید پیش از مهاجرت فرمول‌های حساس فاز ۷ تکمیل شود.
- فاز ۵ پیش‌نیاز importهای فاز ۶، ۷ و ۱۲ است.
- فاز ۸ پیش‌نیاز اتصال کامل فاز ۹، ۱۰، ۱۱ و ۱۴ است.
- فاز ۱۶ مقصد نهایی داده داشبورد است؛ تا آن زمان adapterهای منابع فعلی باید حفظ شوند.

## مواردی که نباید در فازهای بعد شکسته شوند

- routeهای فعلی بدون redirect سازگار
- `Auth`, `OrgAccess`, CSRF و session
- `Database::repair()` و fresh install
- active batch و تاریخچه import
- داده‌های تاریخی و `raw_json`
- RTL و متن فارسی
- پلنر و dashboard عمومی
- OKR و link آن به planner
- ticketing، letters، email، SMS و notificationهای مجاور
- permissionهای موجود
- report/exportهای جاری

## معیار پایان Phase 0

- مسیرها و فایل‌های مرتبط ثبت شد.
- جدول‌ها و Viewهای مرتبط ثبت شد.
- permissionهای مرتبط ثبت شد.
- date و JSON UI ثبت شد.
- importها و failureها ثبت شد.
- dashboard sourceها ثبت شد.
- action/report/planner duplication ثبت شد.
- syntax، contractهای موجود و schema بررسی شد.
- منابع UI UX Pro Max، Motion/Framer Motion و 21st.dev بررسی و قرارداد سازگاری آن‌ها ثبت شد.
- نقشه مهاجرت فازهای ۱ تا ۱۶ ثبت شد.
- هیچ عملکرد یا schema در Phase 0 تغییر نکرد.

## مرحله بعد پیشنهادی

فقط **Phase 1 — Global Persian Date System** آغاز شود: ابتدا قرارداد `AppDate`، جدول افزایشی `system_periods`، asset محلی datepicker و تست‌های conversion/range تعریف شوند؛ سپس یک یا دو صفحه کم‌ریسک به‌عنوان pilot مهاجرت کنند. مهاجرت همه ۲۰ فایل در یک patch انجام نشود.
