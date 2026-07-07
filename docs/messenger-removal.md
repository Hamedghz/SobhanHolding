# حذف Messenger از سایت

این تغییر ماژول و صفحه‌های `Messenger` را از کدبیس فعال سایت حذف می‌کند، اما تیکتینگ، نامه‌های سازمانی، ایمیل و Notification Hub را نگه می‌دارد.

## چه چیزهایی حذف شد

- صفحه‌های کاربری و مدیریتی Messenger
- APIها، worker، realtime server و assetهای اختصاصی Messenger
- دکمه‌ها و سرویس فوروارد گزارش فروش به Messenger
- ثبت ماژول Messenger در `Database::repair()` و DDL نصب تازه در `database/schema.sql`

## چه چیزهایی حفظ شد

- `Ticketing`
- `Letters`
- `Email Hub`
- `Sobhan Notification Hub`

## نکات مهاجرت

- در دیتابیس‌های موجود هیچ `DROP` یا `TRUNCATE` اجرا نشده است.
- اگر قبلاً جدول‌ها یا داده‌های Messenger ساخته شده باشند، این تغییر آن‌ها را پاک نمی‌کند؛ فقط دیگر از سمت سایت استفاده نمی‌شوند.
- برای نصب تازه، ساختار Messenger دیگر در `database/schema.sql` و `Database::repair()` ایجاد نمی‌شود.

## اعتبارسنجی

- بررسی ارجاع‌های مشترک منو، اعلان، داشبورد فروش و اسناد ویندوز
- تست قراردادی `tests/messenger_removal_contract_test.ps1`

## محدودیت فعلی

- اگر در دیتابیس فعلی notification یا permission قدیمی مرتبط با Messenger وجود داشته باشد، رکوردها به‌صورت ایمن باقی می‌مانند و فقط UI/route فعالی برای آن‌ها وجود ندارد.
