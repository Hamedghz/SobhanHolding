# بکاپ Pull فایل‌های سایت

Windows Backup Worker با `X-API-Key` فایل‌ها را یک‌به‌یک pull می‌کند. header قدیمی `X-Backup-Api-Key` برای سازگاری حفظ شده است.

جریان: `health.php` → `pending.php?batch_size=20` → `metadata.php?queue_id=X` → `download.php?queue_id=X` → بررسی size/checksum → `ack.php`. شکست worker از `error.php` گزارش و تا سقف تنظیم‌شده retry می‌شود.

API هیچ مسیر absolute یا پارامتر path نمی‌پذیرد. download فقط شناسه ثبت‌شده را server-side resolve می‌کند، symlink و traversal را رد می‌کند و یک فایل را stream می‌کند. عملیات admin فقط queue می‌سازد و انتقال حجیم synchronous انجام نمی‌دهد.

> حذف فایل از سایت باعث حذف نسخه پشتیبان داخلی نمی‌شود.

حذف نسخه host فقط دستی، با CSRF و پس از تأیید بکاپ است و هیچ فرمان delete به Windows Server ارسال نمی‌شود. نسخه داخلی retention مستقل دارد.

```powershell
$h=@{'X-API-Key'=$env:SOBHAN_BACKUP_API_KEY}
$items=(Invoke-RestMethod 'https://example.com/api/file-backup/pending.php?batch_size=20' -Headers $h).data.items
foreach($item in $items){
  $meta=Invoke-RestMethod "https://example.com/api/file-backup/metadata.php?queue_id=$($item.queue_id)" -Headers $h
  Invoke-WebRequest "https://example.com/api/file-backup/download.php?queue_id=$($item.queue_id)" -Headers $h -OutFile (Join-Path $env:BACKUP_ROOT $meta.data.storage_key)
  Invoke-RestMethod 'https://example.com/api/file-backup/ack.php' -Method Post -Headers $h -ContentType 'application/json' -Body (@{queue_id=$item.queue_id;checksum=$meta.data.checksum}|ConvertTo-Json)
}
```

Rollback غیرمخرب است: API را غیرفعال و کد را revert کنید؛ metadata و logهای backup نگه داشته شوند.
