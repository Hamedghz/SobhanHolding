# ماژول پیامکی سبحان

## قابلیت‌ها

ماژول پیامکی از SOAP سرویس BazyabPayam به‌عنوان تنها مسیر فعال استفاده می‌کند. تنظیمات امن، diagnostics مرحله‌ای، اعتبار پنل، ارسال دستی، قالب‌ها، batch حداکثر ۹۰ گیرنده، تاریخچه، bulk code، وضعیت تحویل گیرندگان و safe gateway logs پیاده‌سازی شده‌اند. اتصال خودکار به تیکت، پلنر و منابع انسانی عمداً انجام نشده و `SmsNotificationService::sendSystemSms()` برای اتصال‌های آینده آماده است.

## صفحات و مجوزها

- `/admin/sms-settings.php`: فقط Admin و Super Admin؛ ذخیره، SOAP/WSDL diagnostics، اعتبار و پیام تست.
- `/admin/sms-send.php`: مجوز `sms_send` با قابلیت create.
- `/admin/sms-templates.php`: مجوز `sms_template_manage`.
- `/admin/sms-messages.php`: مجوز `sms_report_view`؛ فیلتر، گیرندگان و CSV.
- `/admin/sms-delivery-sync.php`: مجوز edit روی `sms_delivery_sync`.
- `/install/sms_seed.php`: مجوز `system_maintenance`؛ repair و seed idempotent.

Admin و Super Admin طبق رفتار موجود `Auth::can()` همه این مجوزها را دارند. برای نقش‌های محدود باید ستون عملیاتی مناسب در `user_permissions` صریحاً فعال شود.

## راه‌اندازی و تست

1. افزونه‌های PHP `soap` و `openssl` را فعال کنید و دسترسی outbound سرور به WSDL را بررسی کنید.
2. ترجیحاً `SOBHAN_SMS_ENCRYPTION_KEY` را با یک secret پایدار تنظیم کنید. fallback محلی `config/sms.key` در git نادیده گرفته می‌شود و باید در backup امن سرور حفظ شود.
3. `/install/sms_seed.php` را اجرا کنید؛ اجرای تکراری قالب duplicate نمی‌سازد.
4. در `/admin/sms-settings.php` تنظیمات را ذخیره و «تست اتصال SOAP» یا «دریافت اعتبار» را اجرا کنید.
5. شماره تست را وارد و «ارسال پیام تست» را بزنید. این دکمه واقعاً یک پیام خارجی ارسال می‌کند.
6. ارسال دستی از `/admin/sms-send.php` انجام می‌شود و نتیجه هر batch جدا ثبت می‌گردد.
7. در `/admin/sms-delivery-sync.php` برای هر bulk code وضعیت تحویل را بروزرسانی کنید.

## امنیت و داده

رمز با AES-256-GCM ذخیره می‌شود، در UI برگردانده نمی‌شود و ارسال فیلد خالی رمز قبلی را حفظ می‌کند. POSTها CSRF دارند، خروجی‌ها escape می‌شوند و exception خام، username کامل، password یا متن پیام در gateway log نوشته نمی‌شود. URL API فقط یک فیلد غیرفعال است و هیچ درخواست URL-based اجرا نمی‌شود.

جداول `sms_settings`، `sms_templates`، `sms_messages`، `sms_message_recipients` و `sms_gateway_logs` افزایشی و idempotent هستند. rollback امن با غیرفعال‌کردن سرویس و برداشتن مجوزها انجام می‌شود؛ حذف جدول یا داده rollback پشتیبانی‌شده نیست.

## محدودیت‌ها

تست واقعی SOAP به extension، شبکه outbound، WSDL و credential معتبر وابسته است. سرویس‌دهنده تراکنش توزیع‌شده ندارد؛ اگر ارسال خارجی موفق و دیتابیس بلافاصله قطع شود، ثبت نتیجه آن batch تضمین‌شدنی نیست و باید با پنل سرویس‌دهنده تطبیق داده شود. زمان‌بندی cron برای delivery sync در این فاز اضافه نشده است.
