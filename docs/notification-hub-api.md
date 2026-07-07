# API قرارداد Sobhan Notification Hub

تمام endpointهای device-authenticated به سه header زیر نیاز دارند:

```http
X-Device-Uid: UUID
X-Device-Token: SECRET
X-App-Version: 1.0.0
```

APIها:

- `POST /api/notify/pair-device.php` — اتصال با کد موقت
- `POST /api/notify/register-device.php` — بروزرسانی metadata دستگاه متصل
- `GET /api/notify/client-config.php` — کاربر، poll و تنظیم ماژول‌ها
- `GET /api/notify/pending.php?since_id=0&limit=20` — اعلان‌های تحویل‌نشده دستگاه
- `POST /api/notify/ack.php` — ثبت تحویل
- `POST /api/notify/action.php` — ثبت کلیک/اکشن و اعتبارسنجی سمت سایت
- `POST /api/notify/unregister-device.php` — revoke دستگاه
- `GET /api/notify/app-version.php` — اطلاعات نسخه جدید

نمونه Pair:

```json
{"pairing_code":"123456","device_name":"Hamed-PC","device_type":"windows","app_version":"1.0.0","machine_fingerprint":"SHA256_INPUT_OR_HASH"}
```

نمونه پاسخ:

```json
{"success":true,"data":{"device_uid":"uuid","device_token":"one-time-secret"},"error":null}
```

توکن فقط همین یک بار برگردانده می‌شود و سایت صرفاً SHA-256 آن را نگهداری می‌کند.

## Migrationهای مکمل

- ستون‌های `module`, `type`, `safe_body`, `sender_user_id`, `conversation_id`, `related_module`, `actions_json`, `is_read` روی `sobhan_notifications`
- `sobhan_notification_devices`
- `sobhan_notification_delivery_logs`
- `sobhan_user_notification_module_settings`
- `sobhan_notification_pairing_codes`
- `sobhan_notification_pairing_attempts` برای rate limit بدون ذخیره IP خام

جدول قدیمی `sobhan_user_notification_settings` حفظ شده است؛ تنظیمات row-per-module در جدول مکمل ذخیره می‌شوند تا Web Push و مرکز اعلان فعلی خراب نشوند.

## Hookهای اختیاری

برای اتصال approval به ماژول مقصد می‌توان این handler را تعریف کرد:

```php
sobhan_notification_direct_action(array $notification, string $action, int $userId): void
```

نبودن handler باعث پاسخ کنترل‌شده `action_not_supported` می‌شود و هیچ اکشنی صرفاً در کلاینت نهایی نخواهد شد.
