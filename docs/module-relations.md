# نقشه روابط ماژول‌ها

## مدل رسمی هویت و سازمان

`users` لایه هویت واحد است. فیلدهای پایه عبارت‌اند از: `id`, `name`, `username`, `email`, `employee_no`, `mobile`, `role`, `status`, `department`, `role_key`, `sales_line`, `supervisor_id`, `organization_manager_id`, `org_unit_id`, `org_role_id`, `parent_user_id`, `access_scope`, `employee_panel_enabled`, `admin_panel_enabled`.

هیچ ماژولی جدول هویت کارمند جدا نمی‌سازد؛ جدول دامنه باید به `users.id` متصل باشد. `OrgAccess` تنها منبع حقیقت scope است و `Auth::can()` مجوز عملیات را تعیین می‌کند؛ هر دو کنترل لازم‌اند.

### سلسله‌مراتب فروش

```text
Sales Manager
└── Sales Supervisor
    └── Visitor / Sales Representative
```

- فقط کاربران فروش به `sales_line` و سلسله‌مراتب فروش ملزم‌اند.
- ویزیتور واحد فروش: `sales_line` و `supervisor_id`/`parent_user_id` متصل به سرپرست؛ `organization_manager_id` از مدیر سرپرست استنتاج شود.
- سرپرست فروش: `sales_line` و `organization_manager_id`/`parent_user_id` متصل به مدیر فروش.
- مدیر فروش supervisor لازم ندارد.
- کاربر غیرفروش به supervisor، sales line یا sales manager اجبار نشود؛ سایر واحدها فقط در صورت نیاز از `parent_user_id` استفاده کنند.
- ناسازگاری بین فیلدهای legacy و رابطه اصلی باید در Phase 1 audit و بدون حذف داده reconcile شود.

### قواعد اجرایی تثبیت‌شده در Phase 1

- `OrgModule::normalizeUserOrganization()` مرجع مشترک اعتبارسنجی فرم کاربران و تخصیص ساختار در تنظیمات منابع انسانی است.
- کاربر غیرفروش می‌تواند بدون لاین/سرپرست/مدیر فروش ذخیره شود؛ سه فیلد فروش برای او پاک می‌شوند و `parent_user_id` عمومی در صورت معتبر بودن حفظ می‌شود.
- ویزیتور فقط با سرپرست فعال دارای نقش `SALES_SUPERVISOR` در شاخه فروش ذخیره می‌شود و مدیر سازمانی معتبر از رابطه سرپرست استنتاج می‌شود.
- سرپرست فروش فقط با مدیر فعال دارای نقش `SALES_MANAGER` در شاخه فروش ذخیره می‌شود. مدیر فروش parent یا supervisor اجباری ندارد.
- `OrgAccess::accessibleUserIds()` برای employee صرفاً شناسه خود را برمی‌گرداند؛ manager از روابط مستقیم/تیم و در صورت تنظیم scope از واحد یا لاین خودش استفاده می‌کند؛ admin و super admin تمام کاربران را مدیریت می‌کنند.

## وابستگی پایه

```text
users + org_units + org_roles + manager/sales relations
└── KPI, Attendance, Planner, Tickets, Messenger, Payroll,
    Assessments, Notifications, Reports, Permissions, AI jobs/audit
```

هر read باید scope و هر write باید auth + permission + CSRF + ownership/scope داشته باشد.

## جریان‌های دامنه

1. **HR / KPI:** Users → Org Unit/Role/Sales Line/Manager → KPI Template → Criteria → Period → Score → Result Dashboard → Notification/Report/AI Insight.
2. **ارزیابی سازمانی:** Test Template → Question Bank → Dimension Mapping → Assignment → Employee Response → Scoring Engine → Result History → HR Analytics → Notification/AI Insight.
3. **حضور و غیاب:** Users → Attendance Group/Work Schedule → Daily Entry → Holiday Calendar → Leave/Mission/Overtime → Attendance Report → Payroll (رابطه اختیاری).
4. **حقوق و دستمزد:** Users → Payroll Period → Dynamic Fields → Imported Excel Rows → Payroll Slip → Employee Slip View → PDF/Print/Export. داده حقوق high-risk و فقط برای مالک رکورد و permissionهای صریح قابل مشاهده است.
5. **برنامه‌ریز شخصی:** Users → Role Defaults → Tasks → Daily/Weekly/Monthly Views → Recurring Rule → Reminder → Notification → Report.
6. **تیکتینگ:** Users → Category → Ticket → Message → Attachment → Assignment → SLA/Status → Notification → Audit/Report.
7. **پیام‌رسان:** Users → Conversation → Members → Messages → Attachments → Forwarded Report Card → Notification Queue → optional Realtime Server → Audit.
8. **اعلان:** Domain Event → NotificationService → `sobhan_notifications` → Web UI / Service Worker-Web Push / Windows Notification Hub → Delivery Log/Ack/Action. کانال خارجی enhancement است و اعلان داخل پنل baseline می‌ماند.

در Phase 3، helperهای استاندارد ticket، messenger، planner، meeting/decision، assessment، KPI و payroll به این مسیر متصل شدند. queueهای دامنه فقط staging هستند و درج نهایی اعلان سازمانی منحصراً در `NotificationService` انجام می‌شود. Windows Hub با token hash، module settings، allowlist action و delivery log کار می‌کند و WebSocket پیش‌نیاز تحویل پایه نیست.
9. **گزارش مدیریت:** Users/Managers → sales|finance|warehouse|technology Report Type → Template → Prepared Report → Attachments → Aggregation → Review/AI Insight/Messenger Forward.
10. **حاکمیت مدیریت:** Meeting → Decision → Follow-up → approved Rule → Responsible User → Status/Due Date → Notification/Report.
11. **AI / Reporting / ERP:** Website MySQL → Controlled API → Windows AI/Reporting Server → Local Reporting DB → Views/Stored Procedures → AI Insight/ERP/Dashboard. وب‌سایت command یا DB credential به client نمی‌دهد.
12. **پشتیبان فایل:** Uploaded Files → Queue/Metadata → Windows/Internal Pull → Backup Log → Manual Deletion Policy. حذف نسخه host فقط با سیاست صریح و اثبات backup مجاز است؛ حذف metadata/backup خودکار نیست.

## Phase 10 و 11 — Pull Integration

- `users` یا گزارش مجاز → `SyncQueueService::enqueueOnce()` → `sync_queue` → Windows worker → Reporting DB → ack/error. allowlist کدشده مانع table/column injection است و سایت هیچ اتصال outbound به LAN ندارد.
- فایل ثبت‌شده → `FileBackupService` → metadata/download کنترل‌شده → Windows backup worker → ack/log. حذف دستی نسخه host هیچ رویداد حذف مقصد تولید نمی‌کند.

## نقاط اتصال و مالکیت

| منبع | مصرف‌کننده‌ها | قاعده اتصال |
|---|---|---|
| `users.id` | همه دامنه‌ها | FK/شناسه پایدار؛ بدون هویت موازی |
| OrgAccess | list, dashboard, export, API | scope در query/service، نه فقط UI |
| permission matrix | menu, page, action, API | کلید موجود reuse؛ مجوز جدید additive |
| domain event | notification, audit, report | رویداد پس از write موفق/commit |
| attachment metadata | tickets, messenger, reports, governance | download مجدداً permission و ownership را بررسی کند |
| reporting/AI API | dashboards, insights, sync | API key server-side، allowlist، timeout و log امن |

## لایه UI/API استاندارد

widgetها و جدول‌های جدید از `/api/ui` به service/repository یا query محدود دامنه متصل می‌شوند. `core/Response.php` envelope مشترک را می‌سازد؛ `Auth` مجوز route و `OrgAccess` دامنه سطر را تعیین می‌کند. endpointهای UI هیچ هویت موازی ایجاد نمی‌کنند و planner همیشه user session را مالک داده می‌داند. قراردادهای legacy پیام‌رسان، اعلان، planner و polling هوش مصنوعی تا مهاجرت هماهنگ consumer حفظ می‌شوند.

## مرزهای ممنوع

- KPI، attendance، payroll و assessment نباید کاربر را با نام/کد آزاد به‌جای `users.id` مالک کنند.
- Messenger جایگزین Ticket workflow و Ticket جایگزین Conversation نمی‌شود؛ فقط link/forward کنترل‌شده دارند.
- گزارش تجمیعی نباید scope مدیر را گسترش دهد.
- AI insight مرجع write مستقیم یا تصمیم نهایی منابع انسانی/حقوق نیست.
- relation اختیاری Payroll-Attendance تا تصویب mapping رسمی، نباید محاسبه حقوق را خودکار تغییر دهد.

## تثبیت مرز Ticketing و Messenger در Phase 4

- Ticketing فقط از جدول‌های ticket استفاده می‌کند. درخواست‌کننده و مسئول تخصیص‌یافته فقط تیکت مجاز خود را می‌بینند و مشاهده سراسری به مجوز `ticketing.manage` محدود است؛ دانلود پیوست نیز همان scope را دوباره بررسی می‌کند.
- Messenger مستقل از Ticketing است. مسیر گفتگوی واقعی `/employee/messenger.php` و `/messenger/index.php` آرشیو سازگار گزارش‌هاست.
- عضویت فعال شرط خواندن گفتگو، جست‌وجوی پیام و دریافت فایل خصوصی است. Socket.IO اختیاری است و polling و worker اعلان بدون آن کار می‌کنند.
- گزارش فروش فورواردشده در chat و آرشیو با `message_type=report_card` ثبت می‌شود و هیچ ticket مصنوعی ایجاد نمی‌کند.

## برنامه‌ریز شخصی در Phase 5

- مالک همه taskهای صفحه `/employee/work-planner.php` کاربر session است؛ widget فقط در `/admin/employee-dashboard.php` قرار دارد و در داشبورد CEO یا مدیر فروش مصرف نمی‌شود.
- Daily/weekly/monthly/list و گزارش بازه از `work_planner_tasks` خوانده می‌شوند. recurrence با root مشترک، تاریخ نمونه بعدی و lock پایگاه داده dedupe می‌شود.
- سررسید task → `WorkPlannerService::sendDueReminders()` → `NotificationService` → کانال‌های اعلان. `reminder_sent_at` marker تحویل به سرویس اعلان و guard اجرای مجدد است.

## KPI و ارزیابی سازمانی در Phase 6

- KPI: `users`/OrgAccess → template متصل به unit/role/line → معیارهای پویا → period → score/log → نتیجه شخصی یا گزارش scope‌شده → NotificationService.
- قالب نامرتبط با unit، role یا sales line کارمند حتی با POST مستقیم پذیرفته نمی‌شود. نتایج بر اساس کارمند، واحد، نقش، لاین، سرپرست، مدیر، دوره و ثبت‌کننده فیلتر می‌شوند.
- ارزیابی: test/dimension/question → assignment scope‌شده → response JSON → ثبت نهایی atomic → `TestScoringService` → نتیجه تاریخی → اعلان تکمیل. کارمند فقط assignment متعلق به شناسه session را اجرا می‌کند.
- MBTI و DISC صرفاً زبان توسعه فردی‌اند؛ رضایت، تعهد و فرسودگی برای بهبود محیط کار تفسیر می‌شوند و هیچ خروجی تشخیص بالینی یا تصمیم استخدامی خودکار نیست.

## گزارش‌های مدیریتی و حاکمیت در Phase 8

- Manager/type permission → report template → submission/value/attachment → review/approval → scoped aggregation → optional Messenger `report_card`.
- Sales report از قرارداد What / Why / Action استفاده می‌کند و فقط پس از تأیید برای گیرنده دارای مجوز گزارش ارسال می‌شود.
- Meeting → Decision → Followup history → verified Decision → immutable Rule Version. نهایی‌شدن جلسه به‌تنهایی مجوز انتشار rule نیست.
- Due Decision → governance worker → `NotificationService::notifyDecisionOverdue()` → `overdue_notified_at`. تغییر دوباره به وضعیت فعال marker را برای سررسید بعدی reset می‌کند.
