INSERT INTO site_settings (setting_key, setting_value, setting_type) VALUES
('company_name','شرکت پخش سبحان','text'),
('site_title','شرکت پخش سبحان','text'),
('hero_subtitle','سامانه هلدینگ سبحان و بخش های وابسته.','textarea'),
('meta_description','سامانه داخلی سبک برای مدیریت شاخص‌ها، نظرسنجی‌ها و فایل‌های شرکت پخش سبحان','textarea'),
('footer_text','© شرکت پخش سبحان - تمامی حقوق محفوظ است.','text'),
('primary_color','#2563eb','color'),
('logo_path','','image'),
('pwa_name','شرکت پخش سبحان','text'),
('pwa_short_name','سبحان','text'),
('pwa_description','سامانه هلدینگ سبحان','textarea'),
('pwa_theme_color','#004647','color'),
('pwa_background_color','#ffffff','color'),
('pwa_start_url','/','text'),
('pwa_display','standalone','select'),
('pwa_orientation','portrait','select'),
('pwa_icon_192','','image'),
('pwa_icon_512','','image'),
('pwa_favicon','','image'),
('ceo_dashboard_page_title','داشبورد مدیرعامل','text'),
('ceo_dashboard_gross_sales_title','فروش ناخالص','text'),
('ceo_dashboard_discounts_title','تخفیفات','text'),
('ceo_dashboard_discount_percent_title','درصد','text'),
('ceo_dashboard_net_sales_title','فروش خالص','text'),
('ceo_dashboard_line_sales_chart_title','ریال فروش لاین','text'),
('ceo_dashboard_line_table_title','اطلاعات لاین','text'),
('ceo_dashboard_visitor_table_title','اطلاعات ویزیتورها','text'),
('ceo_dashboard_line_share_chart_title','سهم فروش هر لاین','text'),
('ceo_dashboard_line_achievement_chart_title','درصد تحقق لاین','text'),
('ceo_dashboard_visitor_achievement_chart_title','درصد تحقق ویزیتور','text'),
('ceo_dashboard_discounts_amount','0','number'),
('ceo_dashboard_show_charts','1','boolean'),
('ceo_dashboard_show_line_table','1','boolean'),
('ceo_dashboard_show_visitor_table','1','boolean'),
('sobhan_api_base_url','http://178.131.83.26:18000','text'),
('sobhan_api_timeout','10','number'),
('sobhan_api_enabled','0','boolean'),
('sobhan_distribution_data_mode','import_file','select'),
('sobhan_ai_autofill_enabled','0','boolean'),
('sobhan_ai_overwrite_manual_data','0','boolean'),
('sobhan_static_pharmacy_mode','1','boolean'),
('knowledge_upload_max_mb','10','number')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type);

INSERT INTO site_settings (setting_key, setting_value, setting_type) VALUES
('sobhan_api_key','','password')
ON DUPLICATE KEY UPDATE setting_type = VALUES(setting_type);

INSERT INTO modules (module_key, module_title, sort_order, status) VALUES
('dashboard','داشبورد',10,'active'),
('users','کاربران',20,'active'),
('kpis','شاخص‌ها',30,'active'),
('surveys','نظرسنجی‌ها',40,'active'),
('survey_results','نتایج ارزیابی',50,'active'),
('files','فایل‌ها',60,'active'),
('accounting','حسابداری',65,'active'),
('ceo_dashboard','داشبورد مدیرعامل',68,'active'),
('view_ceo_dashboard','مشاهده داشبورد مدیرعامل',681,'active'),
('view_sobhan_api_settings','مشاهده تنظیمات API سبحان',682,'active'),
('manage_sobhan_api_settings','مدیریت تنظیمات API سبحان',683,'active'),
('use_ai_assistant','استفاده از دستیار هوش مصنوعی',684,'active'),
('view_ai_chat','مشاهده گفتگوی هوش مصنوعی',685,'active'),
('manage_ai_chat_settings','مدیریت تنظیمات گفتگوی هوش مصنوعی',686,'active'),
('manage_knowledge','مدیریت منابع دانش هوش مصنوعی',6865,'active'),
('view_data_source_settings','مشاهده تنظیمات منبع داده',687,'active'),
('manage_data_source_settings','مدیریت تنظیمات منبع داده',688,'active'),
('toggle_ai_autofill','فعال‌سازی تکمیل خودکار هوش مصنوعی',689,'active'),
('allow_ai_overwrite_manual_data','اجازه بازنویسی داده دستی با هوش مصنوعی',690,'active'),
('pharmacy_settings','تنظیمات داروخانه',69,'active'),
('carousel','اسلایدر صفحه اصلی',70,'active'),
('settings','تنظیمات سایت',80,'active')
ON DUPLICATE KEY UPDATE module_title = VALUES(module_title), sort_order = VALUES(sort_order), status = VALUES(status);

INSERT INTO accounting_roles (title, sort_order, status) VALUES
('موزع',10,'active'),
('تحصیلدار',20,'active'),
('ویزیتور',30,'active')
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order), status = VALUES(status);

INSERT INTO pharmacies (title, slug, sort_order, active) VALUES
('داروخانه سبحان','sobhan',10,1),
('داروخانه سنجری','sanjari',20,1),
('داروخانه اعلایی','alaei',30,1)
ON DUPLICATE KEY UPDATE title = VALUES(title), sort_order = VALUES(sort_order), active = VALUES(active);

INSERT INTO accounting_cities (title, sort_order, status) VALUES
('تهران',10,'active')
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order), status = VALUES(status);

INSERT INTO carousel_items (title, description, image_path, button_text, button_link, sort_order, status) VALUES
('توزیع هوشمند و منظم','تمرکز بر سرعت، دقت و شفافیت در شبکه پخش محصولات.','','ورود به سامانه','/login.php',1,'active'),
('ارزیابی عملکرد تیم‌ها','مدیریت KPI و نظرسنجی‌های داخلی با گزارش‌های ساده و کاربردی.','','مشاهده داشبورد','/login.php',2,'active'),
('آرشیو امن فایل‌ها','نگهداری فایل‌های کاری کاربران در یک فضای شخصی سبک و قابل مدیریت.','','شروع کنید','/login.php',3,'active');
