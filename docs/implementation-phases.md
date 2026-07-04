# برنامه فازبندی پیاده‌سازی

## قواعد عمومی

هر فاز task و review مستقل دارد، migrationها additive/idempotent هستند و معیارهای فاز قبل باید برقرار بماند. فایل‌های زیر «محتمل»اند و قبل از edit دوباره با repository truth تطبیق داده می‌شوند. rollback همه فازها از [سیاست rollback](rollback.md) پیروی می‌کند.

## Phase 0 — Repository and Architecture Audit

- **هدف:** تثبیت قانون اساسی، معماری هدف، روابط و شکاف‌های واقعی بدون feature logic.
- **فایل‌ها:** `README.md`, `core/`, `lib/`, `services/`, `admin/`, `employee/`, `api/`, `workers/`, `database/schema.sql`, `docs/`.
- **روابط داده:** inventory وابستگی جدول‌ها به `users`, org, permissions و domain records.
- **امنیت:** ثبت نقاط auth/CSRF/scope/JSON/error exposure؛ بدون تغییر permission.
- **پذیرش:** پنج سند معماری سازگار با مخزن؛ current/target تفکیک؛ هیچ منطق اجرایی تغییر نکرده.
- **rollback:** revert اسناد؛ هیچ DB rollback لازم نیست.

## Phase 1 — Core Organization Relations and Permission Scope

**وضعیت:** پیاده‌سازی شده؛ نیازمند اجرای migration و آزمون integration روی staging دارای MySQL.

- **هدف:** اعتبارسنجی و یکدست‌سازی افزایشی روابط sales/non-sales و scope با `OrgAccess`.
- **فایل‌ها:** `core/Auth.php`, `core/OrgModule.php`, `lib/OrgAccess.php`, `admin/users*.php`, `database/schema.sql`, repair/seed و تست‌ها.
- **روابط داده:** `users` ↔ `org_units`/`org_roles`/manager hierarchy؛ reconcile فیلدهای supervisor/manager/parent.
- **امنیت:** جلوگیری IDOR؛ employee=self، manager=own scope، HR/Admin=permission صریح؛ query-level scope.
- **پذیرش:** سناریوهای sales manager/supervisor/visitor و non-sales تست شوند؛ داده/مجوز قدیمی معتبر بماند.
- **rollback:** کد validation/scope revert؛ ستون/رابطه افزوده حذف نشود.
- **خروجی Phase 1:** validator مشترک روابط فروش، scope مرکزی employee/manager/admin، helperهای team/unit/line، indexهای idempotent کاربران و contract test اضافه شد. مسیر import گروهی کاربران برای یکپارچه‌سازی کامل invariantهای فروش همچنان باید پیش از اتکا به import برای ساخت hierarchy در یک اصلاح محدود Phase 1 بررسی شود.

## Phase 2 — Unified AJAX/API Response Layer

- **هدف:** helper واحد envelope و مهاجرت مرحله‌ای endpointها بدون شکستن client موجود.
- **فایل‌ها:** `core/Response.php`, `/admin/ajax`, `/admin/actions`, `/api` و JS مصرف‌کننده.
- **روابط داده:** ندارد؛ inventory endpoint-consumer و error codes.
- **امنیت:** status درست، عدم افشای exception/secret، auth/CSRF پیش از پاسخ.
- **پذیرش:** success/error contract tests؛ endpointهای منتخب و clientهایشان سازگار؛ legacy adapter مستند.
- **rollback:** endpoint/client batch revert؛ helper additive باقی بماند.

## Phase 3 — Notification Integrity

- **هدف:** اتصال قابل‌اعتماد domain event تا web/push/Windows با dedupe، ack و fallback داخل پنل.
- **فایل‌ها:** `lib/NotificationService.php`, `lib/PushNotificationService.php`, `services/NotificationHubService.php`, `api/notify`, notification pages، service worker و worker هدف.
- **روابط داده:** event → `sobhan_notifications` → subscriptions/devices → delivery logs.
- **امنیت:** recipient scope، same-origin action URL، token حفاظت‌شده، payload حداقلی.
- **پذیرش:** unread/read، تنظیمات، retry/dedupe، unsupported push fallback و ack تست شوند.
- **rollback:** dispatch خارجی pause؛ اعلان web و queue/log حفظ شود.

## Phase 4 — Ticketing and Messenger Relationship Fix

- **هدف:** مرز مستقل Ticket workflow و Messenger conversation با link/forward امن.
- **فایل‌ها:** `services/TicketService.php`, `core/TicketingModule.php`, messenger services/API/pages و docs.
- **روابط داده:** ticket/category/message/attachment/assignment/SLA ↔ users؛ conversation/member/message ↔ forward/audit.
- **امنیت:** requester/assignee/member checks، attachment authorization، internal-message isolation.
- **پذیرش:** lifecycle، SLA، forward بدون duplication و همه IDOR cases تست شوند.
- **rollback:** integration link غیرفعال؛ هر ماژول مستقل و داده‌ها محفوظ.

## Phase 5 — Planner UX and Reminder Notification

- **هدف:** تکمیل نماها، recurrence و reminder متصل به اعلان.
- **فایل‌ها:** Personal/Work planner services/pages/AJAX، cron/worker، assets و docs.
- **روابط داده:** users/role defaults → tasks/recurrence → reminders → notifications/report.
- **امنیت:** task ownership/assignment scope، CSRF و dedupe reminder.
- **پذیرش:** روز/هفته/ماه، timezone تهران، recurrence idempotent و reminder once تست شود.
- **rollback:** reminder worker pause؛ taskها و UI قبلی فعال بمانند.

## Phase 6 — KPI and Assessment Completeness

- **هدف:** تکمیل template تا scoring/history/analytics بدون تغییر ناخواسته فرمول‌ها.
- **فایل‌ها:** HR module/pages، `services/TestScoringService.php`, KPI/assessment schema/seed/tests/docs.
- **روابط داده:** user/org/role → template/criteria/period/assignment/response/score/result.
- **امنیت:** employee own result، manager scoped aggregate، HR permission؛ پاسخ خام محدود.
- **پذیرش:** deterministic scoring، assignment eligibility، history و notification/report integration تست شود.
- **rollback:** UI/service جدید revert؛ score/schema حذف نشود؛ فرمول نسخه‌دار باقی بماند.

## Phase 7 — Attendance and Payroll Validation

- **هدف:** اعتبارسنجی حضور و payroll و تعریف رابطه اختیاری کنترل‌شده، نه تغییر خودکار حقوق.
- **فایل‌ها:** `lib/HrAttendanceRepository.php`, payroll repositories/services/pages/import/export/schema/docs.
- **روابط داده:** schedule/holiday/leave/mission/overtime → report؛ payroll period/fields/import/slip؛ mapping اختیاری.
- **امنیت:** payroll high-risk، مالکیت slip، HR permission، import validation و formula injection protection.
- **پذیرش:** محاسبات مرزی، import replay، totals، PDF/export scope و عدم اثر attendance بدون opt-in تست شود.
- **rollback:** mapping/integration disable؛ slips و attendance حفظ شوند.

## Phase 8 — Management Reports and Governance Integration

- **هدف:** اتصال reports، meeting، decision، follow-up و approved rule با اعلان/پیام‌رسان.
- **فایل‌ها:** management repositories/modules/pages، attachments، notifications و docs.
- **روابط داده:** report templates/submissions/reviews؛ meeting → decision → follow-up → rule/version/responsible.
- **امنیت:** manager scope، confidential attachment، approval permission، immutable audit/version.
- **پذیرش:** ownership، aggregation، approval/version، due notification و forward کنترل‌شده تست شود.
- **rollback:** cross-module automation pause؛ records/versionها محفوظ.

## Phase 9 — Dashboard, Tables, and UI Modernization

- **هدف:** بهبود تدریجی UI با theme contract؛ بدون SPA.
- **فایل‌ها:** shared partials/theme assets، dashboard pages، table JS/CSS و docs.
- **روابط داده:** read-only view models و scoped aggregates.
- **امنیت:** pagination/filter server-side scoped؛ HTML escape و accessible controls.
- **پذیرش:** RTL/responsive/accessibility، appearance کاربر، performance و no-JS fallback بررسی شود.
- **rollback:** asset/island جدید حذف از load؛ صفحات PHP قبلی باقی بمانند.

## Phase 10 — AI / ERP Pull Sync

- **هدف:** pull sync کنترل‌شده و job-based بین سایت، Windows API، reporting DB و ERP.
- **فایل‌ها:** `core/SobhanApiClient.php`, AI/update services/actions، `/api/sync`, worker، config/docs.
- **روابط داده:** job/cursor/idempotency key → reporting views/procedures → insight/dashboard.
- **امنیت:** server-side keys، allowlist/TLS، least privilege، timeout، schema validation و audit؛ بدون shell command.
- **پذیرش:** retry/idempotency، partial failure، stale status، secret redaction و reconciliation تست شود.
- **rollback:** sync pause و last-good snapshot مصرف شود؛ cursor/data حذف نشود.

## Phase 11 — File Backup Completion

- **هدف:** inventory تا pull/ack/log و سیاست حذف دستی قابل اثبات.
- **فایل‌ها:** `lib/FileBackupService.php`, `core/FileBackupModule.php`, `/api/file-backup`, admin page، worker/docs.
- **روابط داده:** upload metadata/hash → pending → download → ack → log → explicit host deletion.
- **امنیت:** hashed key، IP/CIDR allowlist، path containment، download authorization و integrity hash.
- **پذیرش:** scan idempotent، resume/retry، hash verify، ack و منع deletion قبل از backup تست شود.
- **rollback:** pull/delete disable؛ فایل و metadata/log حفظ شود.

## Phase 12 — Final Tests, Documentation, and Acceptance

- **هدف:** regression، امنیت، migration rehearsal، restore drill و پذیرش انسانی.
- **فایل‌ها:** tests/CI/setup docs، تمام docs ماژول و release checklist؛ کد فقط برای defectهای جداگانه مصوب.
- **روابط داده:** end-to-end identity/scope/event/report/sync integrity.
- **امنیت:** auth bypass، IDOR، CSRF، SQLi، XSS، upload، secret/log و destructive SQL review.
- **پذیرش:** targeted + full suite، migration twice، rollback/restore drill، hosting smoke test و sign-off انسانی.
- **rollback:** release متوقف و runbook اجرا شود؛ production deploy خارج از اختیار Codex است.

## وضعیت اجرای برنامه

Phaseهای 0 تا 11 در working tree فعلی دارای کد، مستندات یا contract test متناظر هستند. Phase 12 ممیزی نهایی را در `docs/final-enterprise-validation.md` ثبت می‌کند. این وضعیت به‌معنای تأیید production نیست؛ migration-twice، restore drill، تست HTTP احراز‌شده و smoke test نقش‌ها باید در staging و با sign-off انسانی انجام شوند.
