# Sobhan Messenger Desktop Agent

پیام‌رسان از برنامه‌ی موجود `SobhanNotificationHub` استفاده می‌کند. Agent با device token به `/api/notify/pending.php` وصل می‌شود و deep link پیام‌ها را در `/employee/messenger.php?conversation=...` باز می‌کند. مسیرهای سازگار `/api/messenger/windows/*` نیز به همان API امن متصل‌اند.
