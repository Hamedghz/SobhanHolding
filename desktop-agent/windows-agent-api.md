# Windows Agent API

- `POST /api/notify/windows/register-device.php`
- `POST /api/notify/windows/heartbeat.php`
- `GET /api/notify/windows/poll.php`
- `POST /api/notify/windows/ack.php`

ثبت اولیه با pairing code و ادامه‌ی ارتباط با device token هش‌شده در زیرساخت Sobhan Notification Hub انجام می‌شود. توکن باید فقط در Windows Credential Manager نگهداری شود.
