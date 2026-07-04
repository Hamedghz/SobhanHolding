# نوسازی تدریجی UI و API

## دامنه Phase 2

نوسازی UI در این پروژه progressive enhancement است. صفحات PHP و قراردادهای فعال planner، messenger، notification و AI polling حفظ می‌شوند. endpointهای جدید جدول/widget از `/api/ui/` و envelope مشترک `core/Response.php` استفاده می‌کنند؛ این فاز SPA یا جایگزینی صفحه‌های موجود نیست.

## قرارداد پاسخ

پاسخ موفق دارای `success=true`, `ok=true`, `data`, `meta`, `message` و `error=null` است. پاسخ خطا `data=null`، پیام فارسی قابل نمایش و error code کنترل‌شده دارد. statusهای اصلی: 401 نشست نامعتبر، 403 مجوز ناکافی، 419 CSRF نامعتبر، 422 ورودی نامعتبر و 500 خطای داخلی. exception فنی فقط در error log ثبت می‌شود.

## endpointهای اولیه

| مسیر | مجوز و scope | فیلترهای اصلی |
|---|---|---|
| `/api/ui/dashboard-ceo.php` | `view_ceo_dashboard` یا `ceo_dashboard` | `report_date`, `line_code` |
| `/api/ui/users-table.php` | `users`; سطرها با `OrgAccess` | pagination، search، واحد، نقش، وضعیت، لاین |
| `/api/ui/tickets-table.php` | `ticketing.view` یا `ticketing.manage`; کاربر عادی فقط requester/assignee | pagination، search، دسته، وضعیت، اولویت |
| `/api/ui/kpi-results-table.php` | `hr_kpi.results`; `OrgAccess` | pagination، search، کاربر، قالب، دوره، تاریخ، وضعیت |
| `/api/ui/assessment-results-table.php` | `hr_assessments.results`; `OrgAccess` | pagination، search، کاربر، آزمون، ریسک |
| `/api/ui/planner-events.php` | login؛ فقط user session | بازه حداکثر یک سال، status، search، pagination |

پارامترهای pagination عبارت‌اند از `page` و `per_page`. مقدار `per_page` برای tableها حداکثر 100 و برای تقویم planner حداکثر 500 است. endpointها read-only هستند و هیچ user ID ارسالی را برای planner نمی‌پذیرند.

## writeهای AJAX آینده

write باید method مناسب و `json_require_csrf()` داشته باشد. token از `X-CSRF-Token` یا فیلد `csrf_token`/`_csrf` خوانده می‌شود و failure با status 419 و `CSRF_EXPIRED` پاسخ داده می‌شود. شناسه کاربر مالک، هرجا session مرجع است، از request خوانده نمی‌شود.

## سازگاری و rollback

فرمت endpointهای قدیمی در Phase 2 تغییر نکرده است. برای rollback، مصرف `/api/ui` متوقف و فایل‌های این directory revert شوند؛ helperهای additive در `Response.php` با صفحات قدیمی تداخل ندارند. هیچ migration دیتابیس وجود ندارد.

## Phase 9 — نوسازی تدریجی جدول‌ها و داشبورد

لایه مشترک `assets/js/ui-modernization.js` فقط روی جدول اصلی مسیرهای کاربران، تیکت‌ها، نتایج KPI، نتایج و تخصیص ارزیابی، گزارشات مدیریت و فیش‌های حقوقی فعال می‌شود. جستجو، مرتب‌سازی keyboard-friendly، تعداد ردیف، صفحه‌بندی و CSV فقط روی ردیف‌هایی اجرا می‌شوند که PHP پس از کنترل permission و scope رندر کرده است. با غیرفعال بودن JavaScript، جدول و فیلترهای PHP بدون تغییر باقی می‌مانند.

ورودی‌های `.jalali-date-input` راهنمای فارسی، صفحه‌کلید عددی و برچسب دسترس‌پذیر می‌گیرند و inputهای native تاریخ/زمان جهت مناسب دارند. چون Tabulator و flatpickr در مخزن vendored نیستند، dependency یا CDN اجباری افزوده نشد؛ افزودن آن‌ها نیازمند بازبینی امنیتی و fallback محلی است.

`/api/ui/dashboard-ceo.php` علاوه بر کلیدهای قبلی، داده افزایشی `charts` با label و dataset مستقل از کتابخانه نمودار ارائه می‌کند. کارت‌های PHP و مصرف‌کنندگان قدیمی معتبر می‌مانند. رنگ نئون از تنظیم per-user و `--theme-accent` می‌آید و CSS مشترک کنتراست متن، کارت، فرم و دکمه اصلی را حفظ می‌کند.

### rollback Phase 9

بارگذاری دو asset مشترک از partialهای admin، attribute جدول کاربران و کلید `charts` endpoint قابل revert هستند. migration یا تغییر داده‌ای وجود ندارد و حذف assetها fallback کامل PHP را بازمی‌گرداند.
