# Sobhan Messenger

ماژول PHP مرجع نهایی مجوزها، عضویت، پیام‌ها، فایل خصوصی، audit و اعلان است. سرور Socket.IO تنها پس از توکن کوتاه‌عمر و تأیید عضویت در سایت، کاربر را وارد اتاق گفتگو می‌کند.

## راه‌اندازی

1. با Super Admin مسیر `/install/messenger_seed.php` را یک بار باز کنید.
2. مقدارهای محرمانه `messenger.realtime_secret` و `messenger.realtime_internal_key` را از دیتابیس به `.env` سرور بلادرنگ منتقل کنید؛ آن‌ها را در Git ثبت نکنید.
3. در `realtime-server` دستورهای `npm install` و `npm start` را اجرا و reverse proxy دارای TLS تنظیم کنید.
4. نشانی عمومی Socket.IO را در تنظیمات پیام‌رسان وارد کنید.
5. worker را هر دقیقه اجرا کنید: `php workers/messenger_worker.php 200`.

فایل‌ها بیرون از web root در `storage/private/messenger` ذخیره می‌شوند و فقط endpoint مجاز آن‌ها را پس از کنترل عضویت و hash تحویل می‌دهد. Worker صف اعلان را به NotificationService می‌فرستد؛ بنابراین Web Push و Sobhan Notification Hub ویندوز از همان زیرساخت فعلی استفاده می‌کنند.
