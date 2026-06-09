INSERT INTO site_settings (setting_key, setting_value, setting_type) VALUES
('company_name','شرکت پخش سبحان','text'),
('site_title','شرکت پخش سبحان','text'),
('meta_description','سامانه داخلی سبک برای مدیریت شاخص‌ها، نظرسنجی‌ها و فایل‌های شرکت پخش سبحان','textarea'),
('footer_text','© شرکت پخش سبحان - تمامی حقوق محفوظ است.','text'),
('primary_color','#2563eb','color'),
('logo_path','','image')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type);

INSERT INTO carousel_items (title, description, image_path, button_text, button_link, sort_order, status) VALUES
('توزیع هوشمند و منظم','تمرکز بر سرعت، دقت و شفافیت در شبکه پخش محصولات.','','ورود به سامانه','/login.php',1,'active'),
('ارزیابی عملکرد تیم‌ها','مدیریت KPI و نظرسنجی‌های داخلی با گزارش‌های ساده و کاربردی.','','مشاهده داشبورد','/login.php',2,'active'),
('آرشیو امن فایل‌ها','نگهداری فایل‌های کاری کاربران در یک فضای شخصی سبک و قابل مدیریت.','','شروع کنید','/login.php',3,'active');
