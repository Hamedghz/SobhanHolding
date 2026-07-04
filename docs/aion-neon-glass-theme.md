# تم AION Neon Glass

## هدف

پروفایل `aion_neon_glass` یک ظاهر Dark Neon Glass سراسری برای پنل ادمین و کاربری فراهم می‌کند. تم‌های `white_neon`، `frost` و `minimal` همچنان موجود و قابل انتخاب‌اند.

## انتخاب تم پنل

هر کاربر از `/admin/theme-settings.php` پروفایل، `accent_color` و حالت افکت را برای حساب خودش انتخاب می‌کند. حالت `reduced` blur، shadow، transition و حرکت hover را کاهش می‌دهد. این تنظیمات فقط در `user_theme_preferences` ذخیره می‌شوند.

## تنظیمات عمومی سایت

مسیر `/admin/settings.php` مستقل باقی مانده و همچنان موارد زیر را در `site_settings` ذخیره می‌کند:

- نام و عنوان سایت، متن هیرو، متا و فوتر
- `primary_color` و لوگوی سایت
- نام، رنگ‌ها، آیکون‌ها و سایر تنظیمات PWA

`primary_color` روی متغیر `--primary` سایت عمومی اعمال می‌شود. `accent_color` فقط پنل را کنترل می‌کند و `pwa_theme_color` و `pwa_background_color` فقط در manifest/PWA مصرف می‌شوند. هیچ همگام‌سازی اجباری میان این رنگ‌ها وجود ندارد.

## Migration و Rollback

این تغییر migration دیتابیس ندارد. Rollback با بازگرداندن فایل‌های ThemeProfile، CSS و markup ظاهری انجام می‌شود؛ داده‌های تنظیمات و پروفایل‌های ذخیره‌شده حذف نمی‌شوند.

## اعتبارسنجی

`tests/aion_theme_contract_test.ps1` حفظ permission، CSRF، فیلدهای سایت/PWA، UPSERT، مسیرهای منو، استقلال رنگ‌ها، design tokenها، موبایل و reduced effects را کنترل می‌کند.
