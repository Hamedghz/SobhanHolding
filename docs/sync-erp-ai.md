# Pull Sync امن AI / ERP

## معماری

```text
Website PHP/MySQL --HTTPS controlled API--> Windows AI/Reporting Worker --> Reporting DB / ERP adapter
```

اتصال همیشه توسط Windows Server آغاز می‌شود. وب‌سایت هرگز به LAN، SQL Server، ERP یا IP داخلی درخواست نمی‌فرستد و dump دیتابیس ارائه نمی‌کند.

## راه‌اندازی

در «تنظیمات API سبحان» بخش Pull Sync را فعال، allowlist موجودیت/IP و batch/retry را تنظیم و کلید را تولید کنید. کلید فقط یک‌بار نمایش داده و در دیتابیس به‌صورت SHA-256 نگهداری می‌شود. header رسمی تمام درخواست‌ها `X-API-Key` است.

موجودیت‌های اولیه `users` و `reports` هستند. payload کاربران password، token، session، secret و یادداشت خصوصی ندارد. نوع جدول یا ستون از request پذیرفته نمی‌شود.

## جریان worker

1. `GET /api/sync/health.php`
2. `GET /api/sync/pending.php?batch_size=100`
3. برای هر آیتم: `GET /api/sync/record.php?entity_type=X&entity_id=Y&queue_id=Z`
4. upsert در Reporting DB داخلی
5. `POST /api/sync/ack.php` با JSON شامل `queue_id`
6. در خطا `POST /api/sync/error.php` با `queue_id` و پیام کوتاه

`ack` idempotent است. آیتم خطادار تا `sync_max_attempts` دوباره در pending دیده می‌شود. صف تکراری pending/error برای همان entity/operation ساخته نمی‌شود.

```powershell
$h = @{ 'X-API-Key' = $env:SOBHAN_SYNC_API_KEY }
Invoke-RestMethod 'https://example.com/api/sync/health.php' -Headers $h
$batch = Invoke-RestMethod 'https://example.com/api/sync/pending.php?batch_size=100' -Headers $h
foreach ($item in $batch.data.items) {
  try {
    $record = Invoke-RestMethod "https://example.com/api/sync/record.php?entity_type=$($item.entity_type)&entity_id=$($item.entity_id)&queue_id=$($item.queue_id)" -Headers $h
    # Upsert-SafelyIntoReportingDb $record.data
    Invoke-RestMethod 'https://example.com/api/sync/ack.php' -Method Post -Headers $h -ContentType 'application/json' -Body (@{queue_id=$item.queue_id}|ConvertTo-Json)
  } catch {
    Invoke-RestMethod 'https://example.com/api/sync/error.php' -Method Post -Headers $h -ContentType 'application/json' -Body (@{queue_id=$item.queue_id;error_message='worker_failed'}|ConvertTo-Json)
  }
}
```

خطاهای فنی در log داخلی ثبت می‌شوند؛ API فقط code کنترل‌شده برمی‌گرداند. برای rollback endpointها غیرفعال و کد revert می‌شود؛ جدول و تاریخچه صف حذف نمی‌شوند.
