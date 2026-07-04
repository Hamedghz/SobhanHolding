# جریان یکپارچه اعلان‌های سازمانی

## مسیر استاندارد

```text
Domain event
  -> NotificationService event helper
  -> sobhan_notifications
  -> In-app Web UI (baseline)
  -> Browser Web Push / PWA (optional)
  -> Windows Notification Hub polling (optional)
  -> delivery ack / action log
```

هیچ صفحه یا service نباید مستقیماً در `sobhan_notifications` درج کند. queue تخصصی messenger و planner مجاز است، اما worker آن باید در نهایت helper متناظر `NotificationService` را فراخوانی کند. خراب‌بودن Push یا Windows Hub نباید ثبت اعلان داخل پنل را متوقف کند.

## رویدادهای پوشش‌داده‌شده

- تیکت: ایجاد، پاسخ، تخصیص و تغییر وضعیت
- پیام‌رسان: پیام و اطلاعیه رسمی از worker صف
- برنامه‌ریز: رسیدن زمان reminder
- مدیریت: تخصیص پیگیری مصوبه و helper تأخیر مصوبه
- HR: تخصیص آزمون و ثبت امتیاز KPI
- حقوق: انتشار فیش حقوقی

هر helper باید URL داخلی، body امن برای دستگاه قفل‌شده، module، related type/id و priority مناسب بسازد. محتوای HR و مدیریت در Windows به‌صورت پیش‌فرض با safe body نمایش داده می‌شود.

## کانال‌ها و تنظیمات

`sobhan_user_notification_settings` تنظیم کلی کانال و رویداد را نگهداری می‌کند. `sobhan_user_notification_module_settings` تنظیم Windows را برای cartable، ticketing، messenger، group/channel، approval، planner، HR، sales، management و system نگهداری می‌کند. نبود row به default امن تبدیل می‌شود و ذخیره تنظیمات idempotent است.

## امنیت و action

- device endpointها به `X-Device-Uid`, `X-Device-Token`, `X-App-Version` نیاز دارند.
- token فقط هنگام pair برگردانده و در سرور به‌صورت SHA-256 ذخیره می‌شود.
- pending فقط اعلان همان user/device و moduleهای فعال را برمی‌گرداند.
- action ابتدا با allowlist اعلان و تنظیم module تطبیق داده می‌شود. reply و approve/reject فقط handler سمت سرور را صدا می‌زنند؛ نبود handler برابر `action_not_supported` است.
- URL action فقط HTTPS هم‌مبدأ با URL برنامه یا path داخلی است.
- ack و action موفق/ناموفق در `sobhan_notification_delivery_logs` ثبت می‌شوند. exception فنی فقط در log داخلی می‌رود.

## Rollback

call-siteهای جدید می‌توانند revert شوند و اعلان داخل پنل قدیمی باقی بماند. جدول‌ها، ستون‌ها، deviceها، token hashها، settingها و delivery logها حذف نشوند. در incident، polling/push خارجی متوقف شود؛ `sobhan_notifications` و وضعیت خوانده‌شدن حفظ شوند.
# یادآوری برنامه‌ریز شخصی در Phase 5

`workers/planner_reminder_worker.php` کارهای سررسیدشده و تکمیل‌نشده را به‌صورت batch می‌خواند و برای مالک task از `NotificationService::notifyWorkPlannerReminder()` استفاده می‌کند. marker فنی `work_planner_tasks.reminder_sent_at` فقط پس از ساخت موفق اعلان ثبت می‌شود؛ اجرای دوباره worker اعلان تکراری ایجاد نمی‌کند و خطای فنی فقط در log ثبت می‌شود.

برنامه‌ریز شخصی روی داشبورد CEO یا مدیر فروش نمایش داده نمی‌شود و اعلان آن نیز فقط برای مالک task است. Rollback با توقف worker انجام می‌شود و queue، اعلان و marker موجود حذف نمی‌شوند.

## اعلان‌های حاکمیت در Phase 8

- تخصیص مسئول مصوبه: `notifyMeetingFollowupAssigned` پس از commit ایجاد مصوبه.
- مصوبه عقب‌افتاده: worker حاکمیت `notifyDecisionOverdue` را صدا می‌زند و پس از موفقیت marker ثبت می‌کند.
- انتشار قانون: `notifyRulePublished` پس از commit نسخه فعال، برای مسئول مصوبه ارسال می‌شود.

خطای کانال اعلان، transaction اصلی governance را rollback نمی‌کند و فقط با context امن log می‌شود. اجرای دوباره worker برای marker ثبت‌شده اعلان تکراری نمی‌سازد.
