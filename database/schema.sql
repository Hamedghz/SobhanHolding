CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  username VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','manager','employee') NOT NULL DEFAULT 'employee',
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  description TEXT NULL,
  upload_quota_mb INT NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS manager_employees (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  manager_id INT UNSIGNED NOT NULL,
  employee_id INT UNSIGNED NOT NULL,
  assigned_by INT UNSIGNED NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_manager_employee (manager_id, employee_id),
  CONSTRAINT fk_manager_employees_manager FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_manager_employees_employee FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_manager_employees_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS modules (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  module_key VARCHAR(100) NOT NULL UNIQUE,
  module_title VARCHAR(190) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_permissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  module_key VARCHAR(100) NOT NULL,
  can_view TINYINT(1) NOT NULL DEFAULT 0,
  can_create TINYINT(1) NOT NULL DEFAULT 0,
  can_edit TINYINT(1) NOT NULL DEFAULT 0,
  can_delete TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_module (user_id, module_key),
  CONSTRAINT fk_permissions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kpis (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  weight DECIMAL(8,2) NOT NULL DEFAULT 1,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_kpis_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS surveys (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_surveys_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS survey_kpis (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  survey_id INT UNSIGNED NOT NULL,
  kpi_id INT UNSIGNED NOT NULL,
  weight DECIMAL(8,2) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_survey_kpi (survey_id, kpi_id),
  CONSTRAINT fk_survey_kpis_survey FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
  CONSTRAINT fk_survey_kpis_kpi FOREIGN KEY (kpi_id) REFERENCES kpis(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS survey_assignments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  survey_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  assigned_by INT UNSIGNED NULL,
  status ENUM('assigned','completed') NOT NULL DEFAULT 'assigned',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_survey_user (survey_id, user_id),
  CONSTRAINT fk_assign_survey FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
  CONSTRAINT fk_assign_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_assign_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS survey_results (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  survey_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  employee_id INT UNSIGNED NULL,
  employee_name VARCHAR(190) NULL,
  final_score DECIMAL(8,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_result_survey FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
  CONSTRAINT fk_result_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_result_employee FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS survey_result_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  result_id INT UNSIGNED NOT NULL,
  kpi_id INT UNSIGNED NOT NULL,
  score DECIMAL(8,2) NOT NULL DEFAULT 0,
  weighted_score DECIMAL(8,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_item_result FOREIGN KEY (result_id) REFERENCES survey_results(id) ON DELETE CASCADE,
  CONSTRAINT fk_item_kpi FOREIGN KEY (kpi_id) REFERENCES kpis(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_files (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size INT UNSIGNED NOT NULL,
  visibility ENUM('private','shared') NOT NULL DEFAULT 'private',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_file_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS file_shares (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  file_id INT UNSIGNED NOT NULL,
  shared_with_user_id INT UNSIGNED NOT NULL,
  shared_by INT UNSIGNED NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_file_share_user (file_id, shared_with_user_id),
  CONSTRAINT fk_file_shares_file FOREIGN KEY (file_id) REFERENCES user_files(id) ON DELETE CASCADE,
  CONSTRAINT fk_file_shares_user FOREIGN KEY (shared_with_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_file_shares_by FOREIGN KEY (shared_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounting_collections (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  collector_role VARCHAR(80) NOT NULL,
  full_name VARCHAR(190) NOT NULL,
  invoice_number VARCHAR(120) NOT NULL,
  description TEXT NULL,
  city VARCHAR(120) NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size INT UNSIGNED NOT NULL,
  status ENUM('sent','registered','needs_followup') NOT NULL DEFAULT 'sent',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_accounting_status (status),
  INDEX idx_accounting_invoice (invoice_number),
  INDEX idx_accounting_city (city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounting_roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(120) NOT NULL UNIQUE,
  sort_order INT NOT NULL DEFAULT 0,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounting_cities (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(120) NOT NULL UNIQUE,
  sort_order INT NOT NULL DEFAULT 0,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ceo_dashboard_lines (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_date DATE NULL,
  line_code VARCHAR(10) NOT NULL,
  line_title VARCHAR(100) NULL,
  sales_amount BIGINT NOT NULL DEFAULT 0,
  qty INT NOT NULL DEFAULT 0,
  target_qty INT NOT NULL DEFAULT 0,
  target_amount BIGINT NOT NULL DEFAULT 0,
  supervisor_name VARCHAR(150) NULL,
  sales_manager_name VARCHAR(150) NULL,
  supervisor_user_id INT UNSIGNED NULL,
  sales_manager_user_id INT UNSIGNED NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ceo_lines_report (report_date),
  INDEX idx_ceo_lines_code (line_code),
  INDEX idx_ceo_lines_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ceo_dashboard_visitors (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_date DATE NULL,
  line_code VARCHAR(10) NOT NULL,
  visitor_name VARCHAR(150) NOT NULL,
  target_qty INT NOT NULL DEFAULT 0,
  qty INT NOT NULL DEFAULT 0,
  target_amount BIGINT NOT NULL DEFAULT 0,
  sales_amount BIGINT NOT NULL DEFAULT 0,
  user_id INT UNSIGNED NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ceo_visitors_report (report_date),
  INDEX idx_ceo_visitors_code (line_code),
  INDEX idx_ceo_visitors_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ceo_dashboard_periods (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  from_date DATE NULL,
  to_date DATE NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ceo_periods_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pharmacies (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_pharmacies_active (active),
  INDEX idx_pharmacies_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pharmacy_dashboard_metrics (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pharmacy_id INT UNSIGNED NOT NULL,
  report_date DATE NULL,
  daily_sales BIGINT NOT NULL DEFAULT 0,
  monthly_sales BIGINT NOT NULL DEFAULT 0,
  supplier_purchase_amount BIGINT NOT NULL DEFAULT 0,
  supplier_sales_amount BIGINT NOT NULL DEFAULT 0,
  open_invoice_amount BIGINT NOT NULL DEFAULT 0,
  expenses_amount BIGINT NOT NULL DEFAULT 0,
  pending_checks_amount BIGINT NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_pharmacy_metrics_pharmacy (pharmacy_id),
  INDEX idx_pharmacy_metrics_report (report_date),
  INDEX idx_pharmacy_metrics_active (active),
  CONSTRAINT fk_pharmacy_metrics_pharmacy FOREIGN KEY (pharmacy_id) REFERENCES pharmacies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS knowledge_documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uploaded_by INT UNSIGNED NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  extension VARCHAR(10) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_knowledge_documents_uploaded_by (uploaded_by),
  INDEX idx_knowledge_documents_created_at (created_at),
  CONSTRAINT fk_knowledge_documents_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS carousel_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  image_path VARCHAR(255) NULL,
  button_text VARCHAR(100) NULL,
  button_link VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT NULL,
  setting_type VARCHAR(50) NOT NULL DEFAULT 'text',
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO modules (module_key,module_title,sort_order,status,created_at) VALUES
('view_ceo_dashboard','مشاهده داشبورد مدیرعامل',681,'active',NOW()),
('view_sobhan_api_settings','مشاهده تنظیمات API سبحان',682,'active',NOW()),
('manage_sobhan_api_settings','مدیریت تنظیمات API سبحان',683,'active',NOW()),
('use_ai_assistant','استفاده از دستیار هوش مصنوعی',684,'active',NOW()),
('view_ai_chat','مشاهده گفتگوی هوش مصنوعی',685,'active',NOW()),
('manage_ai_chat_settings','مدیریت تنظیمات گفتگوی هوش مصنوعی',686,'active',NOW()),
('manage_knowledge','مدیریت منابع دانش هوش مصنوعی',6865,'active',NOW()),
('view_data_source_settings','مشاهده تنظیمات منبع داده',687,'active',NOW()),
('manage_data_source_settings','مدیریت تنظیمات منبع داده',688,'active',NOW()),
('toggle_ai_autofill','فعال‌سازی تکمیل خودکار هوش مصنوعی',689,'active',NOW()),
('allow_ai_overwrite_manual_data','اجازه بازنویسی داده دستی با هوش مصنوعی',690,'active',NOW())
ON DUPLICATE KEY UPDATE module_title=VALUES(module_title), sort_order=VALUES(sort_order), status=VALUES(status);

INSERT INTO site_settings (setting_key,setting_value,setting_type,updated_at) VALUES
('sobhan_api_base_url','http://178.131.83.26:18000','text',NOW()),
('sobhan_api_key','','password',NOW()),
('sobhan_api_timeout','10','number',NOW()),
('sobhan_api_enabled','0','boolean',NOW()),
('sobhan_distribution_data_mode','import_file','select',NOW()),
('sobhan_ai_autofill_enabled','0','boolean',NOW()),
('sobhan_ai_overwrite_manual_data','0','boolean',NOW()),
('sobhan_static_pharmacy_mode','1','boolean',NOW()),
('knowledge_upload_max_mb','10','number',NOW())
ON DUPLICATE KEY UPDATE setting_type=VALUES(setting_type);

CREATE TABLE IF NOT EXISTS activity_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  module VARCHAR(100) NOT NULL,
  record_id INT UNSIGNED NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_activity_user (user_id),
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
