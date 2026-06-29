# Windows Agent API

- `POST /api/messenger/windows/register-device.php`
- `POST /api/messenger/windows/heartbeat.php`
- `GET /api/messenger/windows/poll.php`
- `POST /api/messenger/windows/ack.php`

ثبت اولیه با pairing code و ادامه‌ی ارتباط با device token هش‌شده در زیرساخت Sobhan Notification Hub انجام می‌شود. توکن باید فقط در Windows Credential Manager نگهداری شود.
