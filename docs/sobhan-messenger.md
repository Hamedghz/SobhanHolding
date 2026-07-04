# Sobhan Messenger

مسیر اصلی گفتگوی واقعی `/employee/messenger.php` است. `/messenger/index.php` فقط آرشیو گزارش‌های فورواردشده برای داده‌های legacy است و مدل‌های `messenger_messages` و `sales_report_shares` با chat مخلوط نمی‌شوند. فوروارد گزارش فروش در گفتگوی واقعی و رکورد آرشیوی، `message_type=report_card` می‌سازد و هرگز تیکت جعلی ایجاد نمی‌کند.

APIهای اصلی `/api/messenger/conversations.php` و `/api/messenger/messages.php` هستند و خطا در نوار داخل UI نمایش داده می‌شود. مجوزهای پایه `messenger.view`, `messenger.private.send`, `messenger.group.create`, `messenger.channel.create`, `messenger.broadcast.send`, `messenger.admin.dashboard` به‌شکل idempotent ثبت می‌شوند و seed دسترسی کاربر را خودکار افزایش نمی‌دهد.

ماژول PHP مرجع نهایی مجوزها، عضویت، پیام‌ها، فایل خصوصی، audit و اعلان است. سرور Socket.IO تنها پس از توکن کوتاه‌عمر و تأیید عضویت در سایت، کاربر را وارد اتاق گفتگو می‌کند.

## راه‌اندازی

1. با Super Admin مسیر `/install/messenger_seed.php` را یک بار باز کنید.
2. مقدارهای محرمانه `messenger.realtime_secret` و `messenger.realtime_internal_key` را از دیتابیس به `.env` سرور بلادرنگ منتقل کنید؛ آن‌ها را در Git ثبت نکنید.
3. در `realtime-server` دستورهای `npm install` و `npm start` را اجرا و reverse proxy دارای TLS تنظیم کنید.
4. نشانی عمومی Socket.IO را در تنظیمات پیام‌رسان وارد کنید.
5. worker را هر دقیقه اجرا کنید: `php workers/messenger_worker.php 200`.

فایل‌ها بیرون از web root در `storage/private/messenger` ذخیره می‌شوند و فقط endpoint مجاز آن‌ها را پس از کنترل عضویت فعال، مسیر امن و hash تحویل می‌دهد. لیست گفتگو، پیام‌ها، جست‌وجو و فایل، عضو حذف‌شده یا خارج‌شده را نمی‌پذیرند. Worker صف اعلان را به `NotificationService` می‌فرستد؛ بنابراین Web Push و Sobhan Notification Hub ویندوز از همان زیرساخت فعلی استفاده می‌کنند و polling در نبود Socket.IO همچنان مسیر پایه است.

Rollback این فاز صرفاً بازگردانی کد سرویس‌ها و قراردادهاست؛ داده‌های گفتگو، کارت گزارش، صف اعلان و فایل‌های خصوصی حذف نمی‌شوند.
