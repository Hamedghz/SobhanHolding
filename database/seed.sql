INSERT INTO site_settings (setting_key, setting_value, setting_type) VALUES
('company_name','شرکت پخش سبحان','text'),
('site_title','شرکت پخش سبحان','text'),
('hero_subtitle','سامانه هلدینگ سبحان و بخش های وابسته.','textarea'),
('meta_description','سامانه داخلی سبک برای مدیریت شاخص‌ها، نظرسنجی‌ها و فایل‌های شرکت پخش سبحان','textarea'),
('footer_text','© شرکت پخش سبحان - تمامی حقوق محفوظ است.','text'),
('primary_color','#2563eb','color'),
('logo_path','','image'),
('page_title','داشبورد مدیرعامل','text'),
('gross_sales_title','فروش ناخالص','text'),
('discounts_title','تخفیفات','text'),
('discount_percent_title','درصد','text'),
('net_sales_title','فروش خالص','text'),
('line_sales_chart_title','ریال فروش لاین','text'),
('line_table_title','اطلاعات لاین','text'),
('visitor_table_title','اطلاعات ویزیتورها','text'),
('line_share_chart_title','سهم فروش هر لاین','text'),
('line_achievement_chart_title','درصد تحقق لاین','text'),
('visitor_achievement_chart_title','درصد تحقق ویزیتور','text'),
('ceo_dashboard_discounts_amount','0','number')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type);

INSERT INTO modules (module_key, module_title, sort_order, status) VALUES
('dashboard','داشبورد',10,'active'),
('users','کاربران',20,'active'),
('kpis','شاخص‌ها',30,'active'),
('surveys','نظرسنجی‌ها',40,'active'),
('survey_results','نتایج ارزیابی',50,'active'),
('files','فایل‌ها',60,'active'),
('accounting','حسابداری',65,'active'),
('ceo_dashboard','داشبورد مدیرعامل',68,'active'),
('carousel','اسلایدر صفحه اصلی',70,'active'),
('settings','تنظیمات سایت',80,'active')
ON DUPLICATE KEY UPDATE module_title = VALUES(module_title), sort_order = VALUES(sort_order), status = VALUES(status);

INSERT INTO accounting_roles (title, sort_order, status) VALUES
('موزع',10,'active'),
('تحصیلدار',20,'active'),
('ویزیتور',30,'active')
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order), status = VALUES(status);

INSERT INTO accounting_cities (title, sort_order, status) VALUES
('تهران',10,'active')
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order), status = VALUES(status);

INSERT INTO carousel_items (title, description, image_path, button_text, button_link, sort_order, status) VALUES
('توزیع هوشمند و منظم','تمرکز بر سرعت، دقت و شفافیت در شبکه پخش محصولات.','','ورود به سامانه','/login.php',1,'active'),
('ارزیابی عملکرد تیم‌ها','مدیریت KPI و نظرسنجی‌های داخلی با گزارش‌های ساده و کاربردی.','','مشاهده داشبورد','/login.php',2,'active'),
('آرشیو امن فایل‌ها','نگهداری فایل‌های کاری کاربران در یک فضای شخصی سبک و قابل مدیریت.','','شروع کنید','/login.php',3,'active');
