CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  username VARCHAR(100) NOT NULL UNIQUE,
  employee_no VARCHAR(50) NULL,
  mobile VARCHAR(30) NULL,
  force_password_change TINYINT(1) NOT NULL DEFAULT 0,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('super_admin','admin','manager','employee') NOT NULL DEFAULT 'employee',
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  description TEXT NULL,
  upload_quota_mb INT NULL DEFAULT NULL,
  department VARCHAR(150) NULL,
  role_key VARCHAR(100) NULL,
  sales_line VARCHAR(50) NULL,
  supervisor_id INT UNSIGNED NULL,
  organization_manager_id INT UNSIGNED NULL,
  org_unit_id INT UNSIGNED NULL,
  org_role_id INT UNSIGNED NULL,
  parent_user_id INT UNSIGNED NULL,
  access_scope VARCHAR(30) NOT NULL DEFAULT 'self',
  employee_panel_enabled TINYINT(1) NOT NULL DEFAULT 1,
  admin_panel_enabled TINYINT(1) NOT NULL DEFAULT 0,
  display_order INT NOT NULL DEFAULT 0,
  last_login_at DATETIME NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_employee_no(employee_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sms_settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, provider_name VARCHAR(100) NOT NULL DEFAULT 'bazyabpayam',
  wsdl_url VARCHAR(255) NOT NULL, url_api_base VARCHAR(255) NULL, username VARCHAR(100) NOT NULL,
  password_encrypted LONGTEXT NOT NULL, default_sender VARCHAR(50) NOT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_sms_settings_active(is_active), CONSTRAINT fk_sms_settings_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sms_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, provider_name VARCHAR(100) NOT NULL, sender VARCHAR(50) NOT NULL,
  message_body TEXT NOT NULL, recipients_count INT UNSIGNED NOT NULL DEFAULT 0, bulk_code VARCHAR(100) NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'queued', source_module VARCHAR(100) NULL, source_id BIGINT UNSIGNED NULL,
  created_by INT UNSIGNED NULL, sent_at DATETIME NULL, last_checked_at DATETIME NULL, error_code VARCHAR(50) NULL,
  error_message TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sms_messages_status(status), INDEX idx_sms_messages_bulk(bulk_code), INDEX idx_sms_messages_source(source_module,source_id),
  CONSTRAINT fk_sms_messages_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sms_message_recipients (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, message_id BIGINT UNSIGNED NOT NULL, mobile VARCHAR(20) NOT NULL,
  normalized_mobile VARCHAR(20) NOT NULL, delivery_status VARCHAR(50) NULL, provider_message_id VARCHAR(100) NULL,
  checked_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sms_recipient_message(message_id), INDEX idx_sms_recipient_mobile(normalized_mobile),
  CONSTRAINT fk_sms_recipient_message FOREIGN KEY(message_id) REFERENCES sms_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_theme_preferences (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  profile_key VARCHAR(40) NOT NULL DEFAULT 'white_neon',
  accent_color VARCHAR(7) NOT NULL DEFAULT '#00D5FF',
  effects_mode ENUM('standard','reduced') NOT NULL DEFAULT 'standard',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_theme_preference(user_id),
  CONSTRAINT fk_user_theme_preference_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS org_units (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id INT UNSIGNED NULL,
  title VARCHAR(190) NOT NULL,
  code VARCHAR(100) NOT NULL UNIQUE,
  unit_type VARCHAR(50) NOT NULL DEFAULT 'general',
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  description TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_org_units_parent(parent_id),
  INDEX idx_org_units_active(active),
  CONSTRAINT fk_org_units_parent FOREIGN KEY(parent_id) REFERENCES org_units(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS org_roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  code VARCHAR(100) NOT NULL UNIQUE,
  org_unit_id INT UNSIGNED NULL,
  parent_role_id INT UNSIGNED NULL,
  role_type VARCHAR(50) NOT NULL DEFAULT 'staff',
  is_sales_role TINYINT(1) NOT NULL DEFAULT 0,
  hierarchy_level INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  description TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_org_roles_active(active),
  INDEX idx_org_roles_unit(org_unit_id),
  INDEX idx_org_roles_parent(parent_role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_kpi_template_roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_id INT UNSIGNED NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_hr_kpi_template_role(template_id, role_id),
  INDEX idx_hr_kpi_template_roles_role(role_id),
  INDEX idx_hr_kpi_template_roles_active(active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_assessment_tests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  code VARCHAR(100) NOT NULL UNIQUE,
  category VARCHAR(100) NULL,
  description TEXT NULL,
  age_range VARCHAR(80) NULL,
  scoring_type VARCHAR(50) NOT NULL DEFAULT 'dimensions',
  time_limit_minutes INT NOT NULL DEFAULT 20,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  seeded TINYINT(1) NOT NULL DEFAULT 0,
  seed_key VARCHAR(100) NULL,
  seed_version VARCHAR(50) NULL,
  is_seeded TINYINT(1) NOT NULL DEFAULT 0,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_hr_assessment_seed(seed_key, seed_version),
  INDEX idx_hr_assessment_active(active, is_archived)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_assessment_dimensions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  test_id INT UNSIGNED NOT NULL,
  dimension_key VARCHAR(100) NOT NULL,
  dimension_label VARCHAR(190) NOT NULL,
  description TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  seed_key VARCHAR(100) NULL,
  seed_version VARCHAR(50) NULL,
  is_seeded TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_hr_dimension(test_id, dimension_key),
  CONSTRAINT fk_hr_dimension_test FOREIGN KEY(test_id) REFERENCES hr_assessment_tests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_assessment_questions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  test_id INT UNSIGNED NOT NULL,
  question_code VARCHAR(100) NULL,
  question_text TEXT NOT NULL,
  question_hash CHAR(64) NOT NULL,
  answer_type VARCHAR(30) NOT NULL,
  options_json TEXT NULL,
  correct_answer_json TEXT NULL,
  dimension_key VARCHAR(100) NULL,
  secondary_dimension_key VARCHAR(100) NULL,
  weight DECIMAL(8,2) NOT NULL DEFAULT 1,
  reverse_score TINYINT(1) NOT NULL DEFAULT 0,
  correct_answer VARCHAR(190) NULL,
  admin_note TEXT NULL,
  required TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  seeded TINYINT(1) NOT NULL DEFAULT 0,
  seed_key VARCHAR(100) NULL,
  seed_version VARCHAR(50) NULL,
  is_seeded TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_hr_question(test_id, question_hash),
  UNIQUE KEY uq_hr_question_code(test_id, question_code),
  INDEX idx_hr_question_test(test_id),
  CONSTRAINT fk_hr_question_test FOREIGN KEY(test_id) REFERENCES hr_assessment_tests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_assessment_assignments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  test_id INT UNSIGNED NOT NULL,
  employee_id INT UNSIGNED NOT NULL,
  department VARCHAR(150) NULL,
  role_key VARCHAR(100) NULL,
  sales_line VARCHAR(50) NULL,
  supervisor_id INT UNSIGNED NULL,
  manager_id INT UNSIGNED NULL,
  assigned_by INT UNSIGNED NULL,
  assignment_scope VARCHAR(40) NOT NULL DEFAULT 'employee',
  batch_key VARCHAR(80) NULL,
  scope_type VARCHAR(40) NULL,
  scope_value VARCHAR(190) NULL,
  period_key VARCHAR(100) NULL,
  due_date DATE NULL,
  allow_retake TINYINT(1) NOT NULL DEFAULT 0,
  show_result_to_employee TINYINT(1) NOT NULL DEFAULT 0,
  initial_status VARCHAR(30) NOT NULL DEFAULT 'assigned',
  status VARCHAR(30) NOT NULL DEFAULT 'assigned',
  notes TEXT NULL,
  cancel_reason TEXT NULL,
  cancelled_by INT UNSIGNED NULL,
  cancelled_at DATETIME NULL,
  archived_at DATETIME NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_hr_assignment_employee(employee_id, status),
  INDEX idx_hr_assignment_team(supervisor_id, manager_id),
  CONSTRAINT fk_hr_assignment_test FOREIGN KEY(test_id) REFERENCES hr_assessment_tests(id) ON DELETE CASCADE,
  CONSTRAINT fk_hr_assignment_employee FOREIGN KEY(employee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_assessment_responses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assignment_id INT UNSIGNED NOT NULL,
  employee_id INT UNSIGNED NOT NULL,
  test_id INT UNSIGNED NOT NULL,
  answers_json LONGTEXT NULL,
  progress_json TEXT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'in_progress',
  started_at DATETIME NULL,
  submitted_at DATETIME NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_hr_response_assignment(assignment_id),
  CONSTRAINT fk_hr_response_assignment FOREIGN KEY(assignment_id) REFERENCES hr_assessment_assignments(id) ON DELETE CASCADE,
  CONSTRAINT fk_hr_response_employee FOREIGN KEY(employee_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_hr_response_test FOREIGN KEY(test_id) REFERENCES hr_assessment_tests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_assessment_results (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assignment_id INT UNSIGNED NOT NULL,
  employee_id INT UNSIGNED NOT NULL,
  test_id INT UNSIGNED NOT NULL,
  raw_answers_json LONGTEXT NULL,
  calculated_scores_json LONGTEXT NULL,
  normalized_scores_json LONGTEXT NULL,
  final_result TEXT NULL,
  risk_level VARCHAR(40) NULL,
  profile_summary TEXT NULL,
  recommendation_text TEXT NULL,
  calculated_at DATETIME NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_hr_result_employee(employee_id),
  INDEX idx_hr_result_test(test_id),
  CONSTRAINT fk_hr_result_assignment FOREIGN KEY(assignment_id) REFERENCES hr_assessment_assignments(id) ON DELETE CASCADE,
  CONSTRAINT fk_hr_result_employee FOREIGN KEY(employee_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_hr_result_test FOREIGN KEY(test_id) REFERENCES hr_assessment_tests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_assessment_result_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  result_id INT UNSIGNED NULL,
  action VARCHAR(30) NOT NULL,
  performed_by INT UNSIGNED NULL,
  old_value_json LONGTEXT NULL,
  new_value_json LONGTEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_hr_result_log FOREIGN KEY(result_id) REFERENCES hr_assessment_results(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_assessment_seed_versions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  seed_key VARCHAR(100) NOT NULL,
  version VARCHAR(50) NOT NULL,
  source_title VARCHAR(255) NULL,
  source_file VARCHAR(255) NULL,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  applied_by BIGINT NULL,
  notes TEXT NULL,
  UNIQUE KEY uq_seed_version(seed_key, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_assessment_packages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  code VARCHAR(100) NOT NULL UNIQUE,
  role_key VARCHAR(100) NULL,
  description TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  seed_key VARCHAR(100) NULL,
  seed_version VARCHAR(50) NULL,
  is_seeded TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_assessment_package_tests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  package_id INT UNSIGNED NOT NULL,
  test_id INT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_hr_package_test(package_id, test_id),
  CONSTRAINT fk_hr_package_test_package FOREIGN KEY(package_id) REFERENCES hr_assessment_packages(id) ON DELETE CASCADE,
  CONSTRAINT fk_hr_package_test_test FOREIGN KEY(test_id) REFERENCES hr_assessment_tests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_assessment_assignment_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assignment_id INT UNSIGNED NULL,
  action VARCHAR(40) NOT NULL,
  reason TEXT NULL,
  performed_by INT UNSIGNED NULL,
  old_status VARCHAR(30) NULL,
  new_status VARCHAR(30) NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_assignment_logs_assignment (assignment_id),
  INDEX idx_assignment_logs_actor (performed_by)
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
  deleted_at DATETIME NULL,
  deleted_by INT UNSIGNED NULL,
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

CREATE TABLE IF NOT EXISTS ceo_dashboard_manual_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    period_key VARCHAR(50) NOT NULL,
    gross_sales DECIMAL(18,2) DEFAULT 0,
    discounts DECIMAL(18,2) DEFAULT 0,
    net_sales DECIMAL(18,2) DEFAULT 0,
    source VARCHAR(50) DEFAULT 'excel_import',
    uploaded_file_name VARCHAR(255) NULL,
    imported_by INT NULL,
    imported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_ceo_dashboard_manual_period (period_key)
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

CREATE TABLE IF NOT EXISTS schema_migrations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  migration_key VARCHAR(150) NOT NULL UNIQUE,
  version VARCHAR(40) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'completed',
  message TEXT NULL,
  applied_at DATETIME NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seed_runs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  seed_group VARCHAR(100) NOT NULL,
  mode VARCHAR(30) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'pending',
  requested_by INT UNSIGNED NULL,
  started_at DATETIME NULL,
  finished_at DATETIME NULL,
  inserted_count INT NOT NULL DEFAULT 0,
  updated_count INT NOT NULL DEFAULT 0,
  skipped_count INT NOT NULL DEFAULT 0,
  error_count INT NOT NULL DEFAULT 0,
  message TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_seed_runs_group(seed_group),
  INDEX idx_seed_runs_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seed_run_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  seed_run_id BIGINT UNSIGNED NOT NULL,
  seed_key VARCHAR(150) NOT NULL,
  action VARCHAR(40) NOT NULL,
  status VARCHAR(30) NOT NULL,
  table_name VARCHAR(150) NULL,
  record_key VARCHAR(190) NULL,
  message TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_seed_items_run(seed_run_id),
  CONSTRAINT fk_seed_items_run FOREIGN KEY(seed_run_id) REFERENCES seed_runs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS personal_planner_tasks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  task_date DATE NOT NULL,
  due_at DATETIME NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'todo',
  priority VARCHAR(30) NOT NULL DEFAULT 'normal',
  is_important TINYINT(1) NOT NULL DEFAULT 0,
  is_recurring TINYINT(1) NOT NULL DEFAULT 0,
  recurrence_type VARCHAR(30) NULL,
  recurrence_interval INT NULL,
  recurrence_days VARCHAR(100) NULL,
  recurrence_month_day INT NULL,
  recurrence_end_date DATE NULL,
  parent_task_id BIGINT UNSIGNED NULL,
  moved_from_date DATE NULL,
  moved_to_date DATE NULL,
  reminder_enabled TINYINT(1) NOT NULL DEFAULT 0,
  reminder_at DATETIME NULL,
  notification_sent_at DATETIME NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  completed_at DATETIME NULL,
  deleted_at DATETIME NULL,
  INDEX idx_user_date(user_id,task_date), INDEX idx_user_status(user_id,status),
  INDEX idx_reminder(reminder_enabled,reminder_at,notification_sent_at), INDEX idx_recurring(is_recurring,parent_task_id),
  CONSTRAINT fk_personal_planner_tasks_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS personal_planner_notes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  note_date DATE NOT NULL,
  note_text TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  UNIQUE KEY uniq_user_note_date(user_id,note_date),
  CONSTRAINT fk_personal_planner_notes_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS personal_planner_checks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  check_date DATE NOT NULL,
  title VARCHAR(255) NOT NULL,
  is_checked TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  deleted_at DATETIME NULL,
  INDEX idx_personal_planner_check_day(user_id,check_date,deleted_at),
  CONSTRAINT fk_personal_planner_checks_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS personal_planner_notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, task_id BIGINT UNSIGNED NULL,
  notification_type VARCHAR(50) NOT NULL, title VARCHAR(255) NOT NULL, message TEXT NULL, scheduled_at DATETIME NOT NULL,
  sent_at DATETIME NULL, status VARCHAR(30) NOT NULL DEFAULT 'pending', error_message TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
  INDEX idx_user_notification(user_id,scheduled_at), INDEX idx_status_schedule(status,scheduled_at),
  UNIQUE KEY uq_planner_task_notification(task_id,notification_type,scheduled_at),
  CONSTRAINT fk_planner_notification_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS personal_planner_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, task_id BIGINT UNSIGNED NULL,
  action VARCHAR(50) NOT NULL, old_value_json LONGTEXT NULL, new_value_json LONGTEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_task_log(task_id), INDEX idx_user_log(user_id),
  CONSTRAINT fk_planner_log_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS personal_planner_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, widget_enabled TINYINT(1) NOT NULL DEFAULT 1,
  default_view VARCHAR(30) NOT NULL DEFAULT 'daily', notifications_enabled TINYINT(1) NOT NULL DEFAULT 0,
  default_reminder_minutes INT NULL, unfinished_behavior VARCHAR(30) NOT NULL DEFAULT 'keep_overdue',
  compact_mode TINYINT(1) NOT NULL DEFAULT 0, show_done_tasks TINYINT(1) NOT NULL DEFAULT 1, seeded_suggestions_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL, UNIQUE KEY uniq_user_planner_settings(user_id),
  CONSTRAINT fk_planner_settings_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sobhan_notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  actor_user_id INT UNSIGNED NULL,
  sender_user_id INT UNSIGNED NULL,
  event_type VARCHAR(80) NOT NULL,
  module VARCHAR(50) NOT NULL DEFAULT 'system',
  type VARCHAR(80) NOT NULL DEFAULT 'general',
  title VARCHAR(190) NOT NULL,
  body TEXT NULL,
  safe_body VARCHAR(255) NULL,
  safe_push_body VARCHAR(255) NULL,
  related_type VARCHAR(60) NULL,
  related_module VARCHAR(60) NULL,
  related_id BIGINT UNSIGNED NULL,
  conversation_id BIGINT UNSIGNED NULL,
  action_url VARCHAR(255) NULL,
  actions_json LONGTEXT NULL,
  priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  status ENUM('unread','read','archived') NOT NULL DEFAULT 'unread',
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  channel_in_app TINYINT(1) NOT NULL DEFAULT 1,
  channel_push_requested TINYINT(1) NOT NULL DEFAULT 0,
  channel_push_sent TINYINT(1) NOT NULL DEFAULT 0,
  channel_email_requested TINYINT(1) NOT NULL DEFAULT 0,
  channel_sms_requested TINYINT(1) NOT NULL DEFAULT 0,
  push_attempts INT UNSIGNED NOT NULL DEFAULT 0,
  push_sent_at DATETIME NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_sobhan_notifications_user_status(user_id,status,created_at),
  INDEX idx_sobhan_notifications_event(event_type),
  INDEX idx_sobhan_notifications_related(related_type,related_id),
  CONSTRAINT fk_sobhan_notifications_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_sobhan_notifications_actor FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sobhan_push_subscriptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  endpoint TEXT NOT NULL,
  endpoint_hash CHAR(64) NOT NULL,
  p256dh VARCHAR(255) NULL,
  auth_key VARCHAR(255) NULL,
  content_encoding VARCHAR(40) NOT NULL DEFAULT 'aes128gcm',
  user_agent VARCHAR(255) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_success_at DATETIME NULL,
  last_error VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sobhan_push_endpoint(endpoint_hash),
  INDEX idx_sobhan_push_user(user_id,active),
  CONSTRAINT fk_sobhan_push_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sobhan_user_notification_settings (
  user_id INT UNSIGNED NOT NULL PRIMARY KEY,
  in_app_enabled TINYINT(1) NOT NULL DEFAULT 1,
  push_enabled TINYINT(1) NOT NULL DEFAULT 1,
  email_enabled TINYINT(1) NOT NULL DEFAULT 0,
  sms_enabled TINYINT(1) NOT NULL DEFAULT 0,
  quiet_hours_start TIME NULL,
  quiet_hours_end TIME NULL,
  event_settings_json LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_sobhan_notification_settings_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS letter_letterheads (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(190) NOT NULL, company_name VARCHAR(190) NULL,
  contact_info TEXT NULL, logo_path VARCHAR(255) NULL, background_path VARCHAR(255) NULL, watermark_text VARCHAR(190) NULL,
  header_html MEDIUMTEXT NULL, footer_html MEDIUMTEXT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_letterheads_active(is_active), CONSTRAINT fk_letterheads_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS letter_signatures (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, signer_name VARCHAR(190) NOT NULL, signer_title VARCHAR(190) NULL,
  signature_path VARCHAR(255) NULL, stamp_path VARCHAR(255) NULL, user_id INT UNSIGNED NULL, is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_signatures_active(is_active), CONSTRAINT fk_signatures_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_signatures_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS letter_templates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(190) NOT NULL, default_subject VARCHAR(255) NULL, default_body MEDIUMTEXT NULL,
  letterhead_id INT UNSIGNED NULL, signature_id INT UNSIGNED NULL, paper_size ENUM('A4','A5') NOT NULL DEFAULT 'A4',
  orientation ENUM('portrait','landscape') NOT NULL DEFAULT 'portrait', is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_templates_active(is_active), CONSTRAINT fk_templates_letterhead FOREIGN KEY(letterhead_id) REFERENCES letter_letterheads(id) ON DELETE SET NULL,
  CONSTRAINT fk_templates_signature FOREIGN KEY(signature_id) REFERENCES letter_signatures(id) ON DELETE SET NULL,
  CONSTRAINT fk_templates_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organizational_letters (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, letter_number VARCHAR(100) NULL, letter_date DATE NOT NULL, subject VARCHAR(255) NOT NULL,
  recipient_name VARCHAR(190) NOT NULL, recipient_title VARCHAR(190) NULL, recipient_organization VARCHAR(190) NULL, sender_unit VARCHAR(190) NULL,
  template_id INT UNSIGNED NULL, letterhead_id INT UNSIGNED NULL, signature_id INT UNSIGNED NULL, body_html MEDIUMTEXT NOT NULL, final_html LONGTEXT NULL,
  paper_size ENUM('A4','A5') NOT NULL DEFAULT 'A4', orientation ENUM('portrait','landscape') NOT NULL DEFAULT 'portrait',
  importance ENUM('normal','important','urgent') NOT NULL DEFAULT 'normal', confidentiality ENUM('normal','confidential','secret') NOT NULL DEFAULT 'normal',
  status ENUM('draft','pending_signature','signed','issued','archived','cancelled') NOT NULL DEFAULT 'draft', created_by INT UNSIGNED NOT NULL,
  approved_by INT UNSIGNED NULL, issued_at DATETIME NULL, archived_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_letter_number(letter_number),
  INDEX idx_letters_date(letter_date), INDEX idx_letters_status(status), INDEX idx_letters_confidentiality(confidentiality), INDEX idx_letters_creator(created_by),
  CONSTRAINT fk_letters_template FOREIGN KEY(template_id) REFERENCES letter_templates(id) ON DELETE SET NULL,
  CONSTRAINT fk_letters_letterhead FOREIGN KEY(letterhead_id) REFERENCES letter_letterheads(id) ON DELETE SET NULL,
  CONSTRAINT fk_letters_signature FOREIGN KEY(signature_id) REFERENCES letter_signatures(id) ON DELETE SET NULL,
  CONSTRAINT fk_letters_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_letters_approver FOREIGN KEY(approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organizational_letter_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, letter_id BIGINT UNSIGNED NOT NULL, user_id INT UNSIGNED NULL, action VARCHAR(60) NOT NULL,
  from_status VARCHAR(40) NULL, to_status VARCHAR(40) NULL, description VARCHAR(500) NULL, ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_letter_logs_letter(letter_id,created_at),
  CONSTRAINT fk_letter_logs_letter FOREIGN KEY(letter_id) REFERENCES organizational_letters(id) ON DELETE CASCADE,
  CONSTRAINT fk_letter_logs_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organizational_letter_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, letter_id BIGINT UNSIGNED NOT NULL, original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL, file_path VARCHAR(255) NOT NULL, mime_type VARCHAR(120) NOT NULL, file_size INT UNSIGNED NOT NULL,
  uploaded_by INT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_letter_attachments_letter(letter_id),
  CONSTRAINT fk_letter_attachments_letter FOREIGN KEY(letter_id) REFERENCES organizational_letters(id) ON DELETE CASCADE,
  CONSTRAINT fk_letter_attachments_user FOREIGN KEY(uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_providers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(190) NOT NULL, code VARCHAR(100) NOT NULL UNIQUE,
  provider_type VARCHAR(40) NOT NULL DEFAULT 'custom', imap_host VARCHAR(255) NULL, imap_port INT UNSIGNED NOT NULL DEFAULT 993,
  imap_encryption ENUM('ssl','tls','none') NOT NULL DEFAULT 'ssl', smtp_host VARCHAR(255) NULL, smtp_port INT UNSIGNED NOT NULL DEFAULT 587,
  smtp_encryption ENUM('ssl','tls','none') NOT NULL DEFAULT 'tls', auth_type ENUM('password','app_password','oauth2') NOT NULL DEFAULT 'password',
  oauth_config_json LONGTEXT NULL, active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_email_providers_active(active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_accounts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, provider_id INT UNSIGNED NOT NULL, account_title VARCHAR(190) NOT NULL,
  email_address VARCHAR(255) NOT NULL, display_name VARCHAR(190) NULL, username VARCHAR(255) NOT NULL,
  encrypted_password LONGTEXT NULL, encrypted_access_token LONGTEXT NULL, encrypted_refresh_token LONGTEXT NULL,
  auth_type ENUM('password','app_password','oauth2') NOT NULL DEFAULT 'password',
  account_scope ENUM('personal','department','role','shared','system') NOT NULL DEFAULT 'personal', owner_user_id INT UNSIGNED NULL,
  department_id INT UNSIGNED NULL, role_id INT UNSIGNED NULL, is_shared TINYINT(1) NOT NULL DEFAULT 0,
  sync_enabled TINYINT(1) NOT NULL DEFAULT 1, send_enabled TINYINT(1) NOT NULL DEFAULT 1, last_sync_at DATETIME NULL,
  sync_status ENUM('never','syncing','ok','error') NOT NULL DEFAULT 'never', last_error TEXT NULL, active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email_accounts_scope(account_scope), INDEX idx_email_accounts_sync(active,sync_enabled),
  CONSTRAINT fk_email_accounts_provider FOREIGN KEY(provider_id) REFERENCES email_providers(id) ON DELETE RESTRICT,
  CONSTRAINT fk_email_accounts_owner FOREIGN KEY(owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_email_accounts_department FOREIGN KEY(department_id) REFERENCES org_units(id) ON DELETE SET NULL,
  CONSTRAINT fk_email_accounts_role FOREIGN KEY(role_id) REFERENCES org_roles(id) ON DELETE SET NULL,
  CONSTRAINT fk_email_accounts_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_account_permissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, account_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NULL,
  role_id INT UNSIGNED NULL, department_id INT UNSIGNED NULL, can_read TINYINT(1) NOT NULL DEFAULT 0,
  can_send TINYINT(1) NOT NULL DEFAULT 0, can_reply TINYINT(1) NOT NULL DEFAULT 0, can_forward TINYINT(1) NOT NULL DEFAULT 0,
  can_delete TINYINT(1) NOT NULL DEFAULT 0, can_manage TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email_permissions_account(account_id), INDEX idx_email_permissions_user(user_id),
  CONSTRAINT fk_email_permissions_account FOREIGN KEY(account_id) REFERENCES email_accounts(id) ON DELETE CASCADE,
  CONSTRAINT fk_email_permissions_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_email_permissions_role FOREIGN KEY(role_id) REFERENCES org_roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_email_permissions_department FOREIGN KEY(department_id) REFERENCES org_units(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_folders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, account_id INT UNSIGNED NOT NULL, remote_folder_id VARCHAR(500) NULL,
  folder_name VARCHAR(255) NOT NULL, folder_path VARCHAR(500) NOT NULL,
  folder_type ENUM('inbox','sent','drafts','spam','trash','archive','custom') NOT NULL DEFAULT 'custom',
  total_messages INT UNSIGNED NOT NULL DEFAULT 0, unread_count INT UNSIGNED NOT NULL DEFAULT 0, uid_validity BIGINT UNSIGNED NULL,
  last_uid BIGINT UNSIGNED NOT NULL DEFAULT 0, sync_enabled TINYINT(1) NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_email_folder_path(account_id,folder_path(190)), INDEX idx_email_folders_account(account_id),
  CONSTRAINT fk_email_folders_account FOREIGN KEY(account_id) REFERENCES email_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, account_id INT UNSIGNED NOT NULL, folder_id BIGINT UNSIGNED NOT NULL,
  uid_validity BIGINT UNSIGNED NOT NULL DEFAULT 0, remote_uid BIGINT UNSIGNED NOT NULL, outbox_id BIGINT UNSIGNED NULL, message_id VARCHAR(500) NULL,
  thread_id VARCHAR(500) NULL, subject VARCHAR(500) NULL, from_email VARCHAR(255) NULL, from_name VARCHAR(255) NULL,
  to_json LONGTEXT NULL, cc_json LONGTEXT NULL, bcc_json LONGTEXT NULL, reply_to_json LONGTEXT NULL,
  date_received DATETIME NULL, date_sent DATETIME NULL, body_text LONGTEXT NULL, body_html LONGTEXT NULL, snippet VARCHAR(500) NULL,
  has_attachments TINYINT(1) NOT NULL DEFAULT 0, is_read TINYINT(1) NOT NULL DEFAULT 0, is_starred TINYINT(1) NOT NULL DEFAULT 0,
  is_flagged TINYINT(1) NOT NULL DEFAULT 0, is_answered TINYINT(1) NOT NULL DEFAULT 0, is_forwarded TINYINT(1) NOT NULL DEFAULT 0,
  importance ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
  status ENUM('new','read','pending_reply','replied','forwarded','archived','spam','deleted') NOT NULL DEFAULT 'new',
  tags_json LONGTEXT NULL, raw_headers_json LONGTEXT NULL, assigned_user_id INT UNSIGNED NULL, assigned_group VARCHAR(190) NULL,
  internal_note TEXT NULL, related_ticket_id BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_email_remote_uid(account_id,folder_id,uid_validity,remote_uid), UNIQUE KEY uq_email_message_outbox(outbox_id), INDEX idx_email_messages_folder(folder_id,date_received),
  INDEX idx_email_messages_status(account_id,status), INDEX idx_email_messages_message_id(message_id(190)),
  CONSTRAINT fk_email_messages_account FOREIGN KEY(account_id) REFERENCES email_accounts(id) ON DELETE CASCADE,
  CONSTRAINT fk_email_messages_folder FOREIGN KEY(folder_id) REFERENCES email_folders(id) ON DELETE CASCADE,
  CONSTRAINT fk_email_messages_assignee FOREIGN KEY(assigned_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_outbox (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, account_id INT UNSIGNED NOT NULL, sender_user_id INT UNSIGNED NOT NULL,
  to_json LONGTEXT NOT NULL, cc_json LONGTEXT NULL, bcc_json LONGTEXT NULL, subject VARCHAR(500) NULL,
  body_html LONGTEXT NULL, body_text LONGTEXT NULL, attachments_json LONGTEXT NULL, related_message_id BIGINT UNSIGNED NULL,
  send_type ENUM('compose','reply','reply_all','forward') NOT NULL DEFAULT 'compose',
  status ENUM('draft','queued','sending','sent','failed','cancelled') NOT NULL DEFAULT 'draft', attempts INT UNSIGNED NOT NULL DEFAULT 0,
  last_error TEXT NULL, scheduled_at DATETIME NULL, sent_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_email_outbox_queue(status,scheduled_at),
  CONSTRAINT fk_email_outbox_account FOREIGN KEY(account_id) REFERENCES email_accounts(id) ON DELETE CASCADE,
  CONSTRAINT fk_email_outbox_sender FOREIGN KEY(sender_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_email_outbox_related FOREIGN KEY(related_message_id) REFERENCES email_messages(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, message_id BIGINT UNSIGNED NULL, account_id INT UNSIGNED NOT NULL,
  outbox_id BIGINT UNSIGNED NULL, file_name VARCHAR(255) NOT NULL, mime_type VARCHAR(190) NOT NULL,
  file_size INT UNSIGNED NOT NULL DEFAULT 0, storage_path VARCHAR(500) NOT NULL, content_id VARCHAR(500) NULL,
  is_inline TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email_attachments_message(message_id), INDEX idx_email_attachments_outbox(outbox_id),
  CONSTRAINT fk_email_attachments_message FOREIGN KEY(message_id) REFERENCES email_messages(id) ON DELETE CASCADE,
  CONSTRAINT fk_email_attachments_account FOREIGN KEY(account_id) REFERENCES email_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, account_id INT UNSIGNED NULL, message_id BIGINT UNSIGNED NULL,
  user_id INT UNSIGNED NULL, action VARCHAR(60) NOT NULL, description VARCHAR(500) NULL, technical_details LONGTEXT NULL,
  ip_address VARCHAR(45) NULL, user_agent VARCHAR(255) NULL, meta_json LONGTEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email_logs_account(account_id,created_at), INDEX idx_email_logs_action(action),
  CONSTRAINT fk_email_logs_account FOREIGN KEY(account_id) REFERENCES email_accounts(id) ON DELETE SET NULL,
  CONSTRAINT fk_email_logs_message FOREIGN KEY(message_id) REFERENCES email_messages(id) ON DELETE SET NULL,
  CONSTRAINT fk_email_logs_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_templates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(190) NOT NULL, category VARCHAR(100) NULL,
  subject_template VARCHAR(500) NULL, body_template LONGTEXT NOT NULL, active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_email_templates_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_rules (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(190) NOT NULL, account_id INT UNSIGNED NULL,
  condition_field ENUM('from','subject','body','has_attachment','age_hours') NOT NULL,
  condition_operator ENUM('contains','equals','domain','greater_than') NOT NULL DEFAULT 'contains', condition_value VARCHAR(500) NULL,
  action_type ENUM('add_tag','assign_user','assign_group','create_ticket','set_pending_reply','mark_important') NOT NULL,
  action_value VARCHAR(500) NULL, priority INT NOT NULL DEFAULT 100, active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_email_rules_active(active,priority),
  CONSTRAINT fk_email_rules_account FOREIGN KEY(account_id) REFERENCES email_accounts(id) ON DELETE CASCADE,
  CONSTRAINT fk_email_rules_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_integrations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, message_id BIGINT UNSIGNED NOT NULL,
  integration_type ENUM('ticket','cartable') NOT NULL, target_id BIGINT UNSIGNED NULL,
  status ENUM('pending','completed','failed') NOT NULL DEFAULT 'pending', requested_by INT UNSIGNED NULL,
  payload_json LONGTEXT NULL, last_error TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_email_integrations_message(message_id),
  CONSTRAINT fk_email_integrations_message FOREIGN KEY(message_id) REFERENCES email_messages(id) ON DELETE CASCADE,
  CONSTRAINT fk_email_integrations_user FOREIGN KEY(requested_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_import_batches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, file_name VARCHAR(255) NOT NULL, imported_by INT UNSIGNED NULL,
  mode ENUM('create','update','upsert') NOT NULL DEFAULT 'create', allow_empty_employee_no TINYINT(1) NOT NULL DEFAULT 0,
  total_rows INT UNSIGNED NOT NULL DEFAULT 0, success_count INT UNSIGNED NOT NULL DEFAULT 0, error_count INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('preview','committed','failed') NOT NULL DEFAULT 'preview', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, committed_at DATETIME NULL,
  INDEX idx_user_import_batches_user(imported_by), CONSTRAINT fk_user_import_batches_user FOREIGN KEY(imported_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_import_rows (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, batch_id BIGINT UNSIGNED NOT NULL, source_row INT UNSIGNED NOT NULL,
  employee_no VARCHAR(50) NULL, raw_data_json LONGTEXT NOT NULL, status ENUM('valid','error','created','updated','skipped') NOT NULL DEFAULT 'valid',
  error_message TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_user_import_rows_batch(batch_id,status),
  CONSTRAINT fk_user_import_rows_batch FOREIGN KEY(batch_id) REFERENCES user_import_batches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS docs_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(190) NOT NULL, slug VARCHAR(190) NOT NULL UNIQUE, parent_id INT UNSIGNED NULL,
  sort_order INT NOT NULL DEFAULT 0, active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_docs_categories_parent(parent_id),
  CONSTRAINT fk_docs_categories_parent FOREIGN KEY(parent_id) REFERENCES docs_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS docs_articles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, category_id INT UNSIGNED NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL UNIQUE,
  summary TEXT NULL, content LONGTEXT NOT NULL, cover_image VARCHAR(500) NULL, attachment_path VARCHAR(500) NULL, attachment_name VARCHAR(255) NULL,
  attachment_mime VARCHAR(190) NULL, visibility_scope ENUM('all','units','roles','users') NOT NULL DEFAULT 'all', allowed_roles_json LONGTEXT NULL,
  allowed_units_json LONGTEXT NULL, allowed_users_json LONGTEXT NULL, require_read_confirmation TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1, created_by INT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_docs_articles_category(category_id), INDEX idx_docs_articles_active(active),
  CONSTRAINT fk_docs_articles_category FOREIGN KEY(category_id) REFERENCES docs_categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_docs_articles_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS docs_read_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, doc_id BIGINT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL,
  read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, confirmed_at DATETIME NULL, ip_address VARCHAR(45) NULL, user_agent VARCHAR(255) NULL,
  UNIQUE KEY uq_docs_read_user(doc_id,user_id), INDEX idx_docs_read_logs_doc(doc_id),
  CONSTRAINT fk_docs_read_logs_doc FOREIGN KEY(doc_id) REFERENCES docs_articles(id) ON DELETE CASCADE,
  CONSTRAINT fk_docs_read_logs_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payroll_periods (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(190) NOT NULL, period_key VARCHAR(80) NOT NULL UNIQUE, year INT NOT NULL,
  month TINYINT UNSIGNED NOT NULL, start_date DATE NULL, end_date DATE NULL, status ENUM('draft','imported','published','locked','cancelled') NOT NULL DEFAULT 'draft',
  created_by INT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_payroll_periods_status(status), CONSTRAINT fk_payroll_periods_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payroll_fields (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, field_key VARCHAR(100) NOT NULL UNIQUE, label VARCHAR(190) NOT NULL,
  field_type ENUM('earning','deduction','info','calculated','employer_cost') NOT NULL, data_type ENUM('text','number','money','date','percent') NOT NULL DEFAULT 'money',
  calculation_type ENUM('manual','formula','system') NOT NULL DEFAULT 'manual', formula VARCHAR(1000) NULL, default_value VARCHAR(500) NULL,
  visible_to_employee TINYINT(1) NOT NULL DEFAULT 1, visible_in_pdf TINYINT(1) NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_payroll_fields_active(active,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payroll_import_batches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, period_id INT UNSIGNED NOT NULL, file_name VARCHAR(255) NOT NULL, imported_by INT UNSIGNED NULL,
  total_rows INT UNSIGNED NOT NULL DEFAULT 0, success_count INT UNSIGNED NOT NULL DEFAULT 0, error_count INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('preview','committed','failed') NOT NULL DEFAULT 'preview', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, committed_at DATETIME NULL,
  INDEX idx_payroll_batches_period(period_id), CONSTRAINT fk_payroll_batches_period FOREIGN KEY(period_id) REFERENCES payroll_periods(id) ON DELETE CASCADE,
  CONSTRAINT fk_payroll_batches_user FOREIGN KEY(imported_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payroll_import_rows (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, batch_id BIGINT UNSIGNED NOT NULL, source_row INT UNSIGNED NOT NULL, employee_no VARCHAR(50) NULL,
  raw_data_json LONGTEXT NOT NULL, status ENUM('valid','error','committed','skipped') NOT NULL DEFAULT 'valid', error_message TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_payroll_rows_batch(batch_id,status),
  CONSTRAINT fk_payroll_rows_batch FOREIGN KEY(batch_id) REFERENCES payroll_import_batches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payroll_slips (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, period_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL, employee_no VARCHAR(50) NOT NULL,
  gross_amount DECIMAL(18,2) NOT NULL DEFAULT 0, total_earnings DECIMAL(18,2) NOT NULL DEFAULT 0, total_deductions DECIMAL(18,2) NOT NULL DEFAULT 0,
  net_pay DECIMAL(18,2) NOT NULL DEFAULT 0, status ENUM('draft','ready','published','cancelled') NOT NULL DEFAULT 'draft', published_at DATETIME NULL,
  tracking_code VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_payroll_period_user(period_id,user_id), UNIQUE KEY uq_payroll_tracking(tracking_code), INDEX idx_payroll_slips_status(status),
  CONSTRAINT fk_payroll_slips_period FOREIGN KEY(period_id) REFERENCES payroll_periods(id) ON DELETE CASCADE,
  CONSTRAINT fk_payroll_slips_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payroll_slip_values (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, slip_id BIGINT UNSIGNED NOT NULL, field_id INT UNSIGNED NULL, field_key VARCHAR(100) NOT NULL,
  label VARCHAR(190) NOT NULL, value_text LONGTEXT NULL, value_number DECIMAL(18,4) NULL, sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_payroll_slip_field(slip_id,field_key), INDEX idx_payroll_values_slip(slip_id),
  CONSTRAINT fk_payroll_values_slip FOREIGN KEY(slip_id) REFERENCES payroll_slips(id) ON DELETE CASCADE,
  CONSTRAINT fk_payroll_values_field FOREIGN KEY(field_id) REFERENCES payroll_fields(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payroll_exports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, slip_id BIGINT UNSIGNED NULL, period_id INT UNSIGNED NULL,
  export_type ENUM('pdf','image','zip','group_pdf') NOT NULL, file_path VARCHAR(500) NULL, generated_by INT UNSIGNED NULL,
  generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_payroll_exports_slip(slip_id),
  CONSTRAINT fk_payroll_exports_slip FOREIGN KEY(slip_id) REFERENCES payroll_slips(id) ON DELETE CASCADE,
  CONSTRAINT fk_payroll_exports_period FOREIGN KEY(period_id) REFERENCES payroll_periods(id) ON DELETE CASCADE,
  CONSTRAINT fk_payroll_exports_user FOREIGN KEY(generated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS management_report_templates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, report_type VARCHAR(40) NOT NULL, title VARCHAR(190) NOT NULL, description TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1, created_by INT UNSIGNED NULL, updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_management_report_template_type(report_type), INDEX idx_management_report_templates_active(active),
  CONSTRAINT fk_management_report_templates_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_management_report_templates_updater FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS management_report_sections (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, template_id INT UNSIGNED NOT NULL, section_key VARCHAR(100) NOT NULL, title VARCHAR(190) NOT NULL,
  description TEXT NULL, sort_order INT NOT NULL DEFAULT 0, active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_management_report_section_key(template_id,section_key), INDEX idx_management_report_sections_template(template_id,active,sort_order),
  CONSTRAINT fk_management_report_sections_template FOREIGN KEY(template_id) REFERENCES management_report_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS management_report_fields (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, section_id INT UNSIGNED NOT NULL, field_key VARCHAR(100) NOT NULL, label VARCHAR(190) NOT NULL,
  field_type ENUM('text','textarea','number','currency','percent','date','select','checkbox','table','repeater','file','readonly_metric') NOT NULL DEFAULT 'text',
  placeholder VARCHAR(255) NULL, help_text TEXT NULL, options_json LONGTEXT NULL, validation_json LONGTEXT NULL, default_value LONGTEXT NULL,
  linked_source_key VARCHAR(190) NULL, is_required TINYINT(1) NOT NULL DEFAULT 0, sort_order INT NOT NULL DEFAULT 0, active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_management_report_field_key(section_id,field_key), INDEX idx_management_report_fields_section(section_id,active,sort_order),
  INDEX idx_management_report_fields_linked(linked_source_key), CONSTRAINT fk_management_report_fields_section FOREIGN KEY(section_id) REFERENCES management_report_sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS management_report_periods (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  code VARCHAR(80) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_management_report_period_code(code),
  INDEX idx_management_report_period_active(active,sort_order,start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sales data foundation (Stage 01: schema only; no parser, sync or dashboard migration)
CREATE TABLE IF NOT EXISTS sales_import_batches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, source_type VARCHAR(30) NOT NULL, source_module VARCHAR(50) NOT NULL,
  file_name VARCHAR(255) NULL, file_hash VARCHAR(128) NULL, detected_sheet VARCHAR(255) NULL, detected_table VARCHAR(255) NULL,
  import_mode VARCHAR(30) NOT NULL DEFAULT 'skip_duplicates', status VARCHAR(30) NOT NULL DEFAULT 'uploaded',
  total_rows INT NOT NULL DEFAULT 0, valid_rows INT NOT NULL DEFAULT 0, invalid_rows INT NOT NULL DEFAULT 0,
  duplicate_rows INT NOT NULL DEFAULT 0, imported_rows INT NOT NULL DEFAULT 0, updated_rows INT NOT NULL DEFAULT 0, skipped_rows INT NOT NULL DEFAULT 0,
  started_by BIGINT UNSIGNED NULL, started_at DATETIME NULL, finished_at DATETIME NULL, error_message TEXT NULL, metadata_json LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
  INDEX idx_sales_import_batches_status(status), INDEX idx_sales_import_batches_source_type(source_type),
  INDEX idx_sales_import_batches_source_module(source_module), INDEX idx_sales_import_batches_file_hash(file_hash),
  INDEX idx_sales_import_batches_started_by(started_by), INDEX idx_sales_import_batches_created_at(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_import_errors (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, import_batch_id BIGINT UNSIGNED NOT NULL, source_module VARCHAR(50) NOT NULL,
  `row_number` INT NULL, error_code VARCHAR(100) NULL, error_message TEXT NOT NULL, raw_json LONGTEXT NULL, normalized_json LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sales_import_errors_batch(import_batch_id), INDEX idx_sales_import_errors_source(source_module), INDEX idx_sales_import_errors_code(error_code),
  CONSTRAINT fk_sales_import_errors_batch FOREIGN KEY(import_batch_id) REFERENCES sales_import_batches(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_import_column_mappings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, source_module VARCHAR(50) NOT NULL, source_header VARCHAR(255) NOT NULL,
  normalized_key VARCHAR(191) NOT NULL, required TINYINT(1) NOT NULL DEFAULT 0, data_type VARCHAR(50) NOT NULL DEFAULT 'string',
  active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
  UNIQUE KEY uq_sales_import_mapping_source_header(source_module,source_header), INDEX idx_sales_import_mappings_source(source_module),
  INDEX idx_sales_import_mappings_key(normalized_key), INDEX idx_sales_import_mappings_active(active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staging_sales_data (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, import_batch_id BIGINT UNSIGNED NOT NULL, source_module VARCHAR(50) NOT NULL,
  `row_number` INT NOT NULL, raw_json LONGTEXT NOT NULL, normalized_json LONGTEXT NULL,
  validation_status VARCHAR(30) NOT NULL DEFAULT 'pending', validation_errors_json LONGTEXT NULL, source_unique_key VARCHAR(191) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_staging_sales_batch(import_batch_id), INDEX idx_staging_sales_source(source_module),
  INDEX idx_staging_sales_validation(validation_status), INDEX idx_staging_sales_unique_key(source_unique_key),
  CONSTRAINT fk_staging_sales_batch FOREIGN KEY(import_batch_id) REFERENCES sales_import_batches(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_aggregate_rows (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, import_batch_id BIGINT UNSIGNED NULL, source_unique_key VARCHAR(191) NULL,
  unique_code VARCHAR(191) NULL, invoice_type VARCHAR(100) NULL, invoice_number VARCHAR(100) NULL, sub_invoice_number VARCHAR(100) NULL,
  invoice_date_raw VARCHAR(100) NULL, invoice_date DATE NULL, customer_code VARCHAR(100) NULL, customer_name VARCHAR(255) NULL,
  product_code VARCHAR(100) NULL, product_name VARCHAR(255) NULL, visitor_code VARCHAR(100) NULL, line_code VARCHAR(100) NULL,
  quantity DECIMAL(18,4) NULL, gross_amount DECIMAL(20,2) NULL, discount_amount DECIMAL(20,2) NULL, net_amount DECIMAL(20,2) NULL,
  return_quantity DECIMAL(18,4) NULL, return_amount DECIMAL(20,2) NULL, raw_json LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
  INDEX idx_sales_aggregate_batch(import_batch_id), UNIQUE KEY uq_sales_aggregate_source_key(source_unique_key), INDEX idx_sales_aggregate_date(invoice_date),
  INDEX idx_sales_aggregate_customer(customer_code), INDEX idx_sales_aggregate_product(product_code), INDEX idx_sales_aggregate_visitor(visitor_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_aggregate_rows (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, import_batch_id BIGINT UNSIGNED NULL, source_type VARCHAR(30) NULL, source_unique_key VARCHAR(191) NULL,
  product_code VARCHAR(100) NULL,product_name VARCHAR(255) NULL,index_code VARCHAR(100) NULL,index_name VARCHAR(255) NULL,consumer_price DECIMAL(20,4) NULL,expire_date DATE NULL,expire_date_raw VARCHAR(100) NULL,manufacturer_code VARCHAR(100) NULL,manufacturer_name VARCHAR(255) NULL,
  sales_carton_qty DECIMAL(20,4) NULL,sales_part_qty DECIMAL(20,4) NULL,sales_total_qty DECIMAL(20,4) NULL,sales_total_amount DECIMAL(20,4) NULL,sales_discount_amount DECIMAL(20,4) NULL,sales_tax_amount DECIMAL(20,4) NULL,sales_duty_amount DECIMAL(20,4) NULL,sales_payable_amount DECIMAL(20,4) NULL,sales_return_carton_qty DECIMAL(20,4) NULL,sales_return_part_qty DECIMAL(20,4) NULL,sales_return_total_qty DECIMAL(20,4) NULL,
  purchase_carton_qty DECIMAL(20,4) NULL,purchase_part_qty DECIMAL(20,4) NULL,purchase_total_qty DECIMAL(20,4) NULL,opening_carton_qty DECIMAL(20,4) NULL,opening_part_qty DECIMAL(20,4) NULL,opening_total_qty DECIMAL(20,4) NULL,inbound_carton_qty DECIMAL(20,4) NULL,inbound_part_qty DECIMAL(20,4) NULL,inbound_total_qty DECIMAL(20,4) NULL,outbound_carton_qty DECIMAL(20,4) NULL,outbound_part_qty DECIMAL(20,4) NULL,outbound_total_qty DECIMAL(20,4) NULL,current_period_carton_qty DECIMAL(20,4) NULL,current_period_part_qty DECIMAL(20,4) NULL,current_period_total_qty DECIMAL(20,4) NULL,carton_size DECIMAL(20,4) NULL,
  last_cost_price DECIMAL(20,4) NULL,last_purchase_price DECIMAL(20,4) NULL,stock_value_by_last_cost DECIMAL(20,4) NULL,stock_value_by_sale_price_1 DECIMAL(20,4) NULL,retail_price DECIMAL(20,4) NULL,wholesale_price DECIMAL(20,4) NULL,sale_price_3 DECIMAL(20,4) NULL,sale_price_4 DECIMAL(20,4) NULL,sale_price_5 DECIMAL(20,4) NULL,sale_price_6 DECIMAL(20,4) NULL,sale_price_7 DECIMAL(20,4) NULL,sale_price_8 DECIMAL(20,4) NULL,sale_price_9 DECIMAL(20,4) NULL,sale_price_10 DECIMAL(20,4) NULL,sale_price_11 DECIMAL(20,4) NULL,sale_price_12 DECIMAL(20,4) NULL,
  retail_commission DECIMAL(20,4) NULL,wholesale_commission DECIMAL(20,4) NULL,commission_3 DECIMAL(20,4) NULL,commission_4 DECIMAL(20,4) NULL,commission_5 DECIMAL(20,4) NULL,commission_6 DECIMAL(20,4) NULL,commission_7 DECIMAL(20,4) NULL,commission_8 DECIMAL(20,4) NULL,commission_9 DECIMAL(20,4) NULL,commission_10 DECIMAL(20,4) NULL,commission_11 DECIMAL(20,4) NULL,commission_12 DECIMAL(20,4) NULL,
  current_weight DECIMAL(20,4) NULL,current_volume DECIMAL(20,4) NULL,product_tree_group_code VARCHAR(100) NULL,product_tree_group_name VARCHAR(255) NULL,barcode VARCHAR(191) NULL,control_code VARCHAR(191) NULL,group_code VARCHAR(100) NULL,group_name VARCHAR(255) NULL,retail_collection_days DECIMAL(20,4) NULL,current_base_stock DECIMAL(20,4) NULL,current_part_stock DECIMAL(20,4) NULL,current_total_stock DECIMAL(20,4) NULL,brand_name VARCHAR(255) NULL,last_purchase_date DATE NULL,last_purchase_date_raw VARCHAR(100) NULL,raw_json LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
  INDEX idx_inventory_aggregate_batch(import_batch_id), UNIQUE KEY uq_inventory_aggregate_source_key(source_unique_key), INDEX idx_inventory_aggregate_product(product_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_team_members (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, import_batch_id BIGINT UNSIGNED NULL, source_unique_key VARCHAR(191) NULL,
  user_id INT UNSIGNED NULL, personnel_code VARCHAR(100) NULL, full_name VARCHAR(255) NULL, role_type VARCHAR(50) NULL,
  line_code VARCHAR(100) NULL, supervisor_code VARCHAR(100) NULL, sales_manager_code VARCHAR(100) NULL, region_code VARCHAR(100) NULL,
  share_percent DECIMAL(8,4) NULL, active TINYINT(1) NOT NULL DEFAULT 1, raw_json LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
  INDEX idx_sales_team_batch(import_batch_id), INDEX idx_sales_team_unique_key(source_unique_key), INDEX idx_sales_team_user(user_id),
  INDEX idx_sales_team_line(line_code), INDEX idx_sales_team_role(role_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_customer_class_coefficients (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, import_batch_id BIGINT UNSIGNED NULL, source_unique_key VARCHAR(191) NULL,
  customer_class_code VARCHAR(100) NULL, customer_class_title VARCHAR(255) NULL, coefficient DECIMAL(12,6) NULL,
  effective_from DATE NULL, effective_to DATE NULL, active TINYINT(1) NOT NULL DEFAULT 1, raw_json LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
  INDEX idx_sales_coeff_batch(import_batch_id), INDEX idx_sales_coeff_unique_key(source_unique_key), INDEX idx_sales_coeff_class(customer_class_code),
  INDEX idx_sales_coeff_effective(effective_from,effective_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_priorities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, import_batch_id BIGINT UNSIGNED NULL, source_unique_key VARCHAR(191) NULL,
  product_code VARCHAR(100) NULL, product_name VARCHAR(255) NULL, brand_code VARCHAR(100) NULL, brand_name VARCHAR(255) NULL,
  priority_code VARCHAR(100) NULL, priority_rank INT NULL, inventory_quantity DECIMAL(18,4) NULL, inventory_value DECIMAL(20,2) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1, raw_json LONGTEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
  INDEX idx_product_priorities_batch(import_batch_id), INDEX idx_product_priorities_unique_key(source_unique_key),
  INDEX idx_product_priorities_product(product_code), INDEX idx_product_priorities_brand(brand_code), INDEX idx_product_priorities_priority(priority_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_targets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, import_batch_id BIGINT UNSIGNED NULL, source_unique_key VARCHAR(191) NULL,
  target_year SMALLINT NULL, target_month TINYINT NULL, line_code VARCHAR(100) NULL, product_code VARCHAR(100) NULL,
  priority_code VARCHAR(100) NULL, visitor_code VARCHAR(100) NULL, supervisor_code VARCHAR(100) NULL,
  target_quantity DECIMAL(18,4) NULL, target_amount DECIMAL(20,2) NULL, raw_json LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
  INDEX idx_sales_targets_batch(import_batch_id), INDEX idx_sales_targets_unique_key(source_unique_key), INDEX idx_sales_targets_period(target_year,target_month),
  INDEX idx_sales_targets_line(line_code), INDEX idx_sales_targets_product(product_code), INDEX idx_sales_targets_visitor(visitor_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commission_formula_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, formula_key VARCHAR(100) NOT NULL, title VARCHAR(255) NULL, formula_expression TEXT NULL,
  settings_json LONGTEXT NULL, version_no INT NOT NULL DEFAULT 1, status VARCHAR(30) NOT NULL DEFAULT 'draft',
  effective_from DATE NULL, effective_to DATE NULL, created_by BIGINT UNSIGNED NULL, published_by BIGINT UNSIGNED NULL,
  published_at DATETIME NULL, raw_json LONGTEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
  UNIQUE KEY uq_commission_formula_version(formula_key,version_no), INDEX idx_commission_formula_status(status),
  INDEX idx_commission_formula_effective(effective_from,effective_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commission_calculation_runs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, run_key VARCHAR(100) NOT NULL, period_year SMALLINT NULL, period_month TINYINT NULL,
  formula_version VARCHAR(100) NULL, status VARCHAR(30) NOT NULL DEFAULT 'pending', started_by BIGINT UNSIGNED NULL,
  started_at DATETIME NULL, finished_at DATETIME NULL, input_summary_json LONGTEXT NULL, result_summary_json LONGTEXT NULL,
  error_message TEXT NULL, raw_json LONGTEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
  UNIQUE KEY uq_commission_run_key(run_key), INDEX idx_commission_runs_period(period_year,period_month),
  INDEX idx_commission_runs_status(status), INDEX idx_commission_runs_started_by(started_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commission_calculation_results (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, calculation_run_id BIGINT UNSIGNED NOT NULL, result_type VARCHAR(30) NOT NULL,
  subject_key VARCHAR(191) NULL, user_id INT UNSIGNED NULL, gross_commission DECIMAL(20,2) NULL, reduction_amount DECIMAL(20,2) NULL,
  reward_amount DECIMAL(20,2) NULL, penalty_amount DECIMAL(20,2) NULL, final_commission DECIMAL(20,2) NULL,
  breakdown_json LONGTEXT NULL, raw_json LONGTEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
  INDEX idx_commission_results_run(calculation_run_id), INDEX idx_commission_results_type(result_type),
  INDEX idx_commission_results_subject(subject_key), INDEX idx_commission_results_user(user_id),
  CONSTRAINT fk_commission_results_run FOREIGN KEY(calculation_run_id) REFERENCES commission_calculation_runs(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS management_report_submissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, template_id INT UNSIGNED NOT NULL, report_type VARCHAR(40) NOT NULL,
  period_key VARCHAR(80) NOT NULL, period_title VARCHAR(190) NOT NULL, period_start DATE NULL, period_end DATE NULL,
  submitter_id INT UNSIGNED NOT NULL, unit_id INT UNSIGNED NULL, status ENUM('draft','submitted','returned','approved','archived') NOT NULL DEFAULT 'draft',
  submitted_at DATETIME NULL, returned_at DATETIME NULL, approved_at DATETIME NULL, approved_by INT UNSIGNED NULL, archived_at DATETIME NULL,
  return_note TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_management_report_period_user(template_id,period_key,submitter_id), INDEX idx_management_report_submissions_type(report_type),
  INDEX idx_management_report_submissions_period(period_key), INDEX idx_management_report_submissions_status(status),
  INDEX idx_management_report_submissions_submitter(submitter_id), INDEX idx_management_report_submissions_unit(unit_id),
  CONSTRAINT fk_management_report_submissions_template FOREIGN KEY(template_id) REFERENCES management_report_templates(id) ON DELETE RESTRICT,
  CONSTRAINT fk_management_report_submissions_submitter FOREIGN KEY(submitter_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_management_report_submissions_unit FOREIGN KEY(unit_id) REFERENCES org_units(id) ON DELETE SET NULL,
  CONSTRAINT fk_management_report_submissions_approver FOREIGN KEY(approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS management_report_values (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, submission_id BIGINT UNSIGNED NOT NULL, field_id INT UNSIGNED NOT NULL,
  value_text LONGTEXT NULL, value_number DECIMAL(20,4) NULL, value_json LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_management_report_value(submission_id,field_id), INDEX idx_management_report_values_submission(submission_id),
  CONSTRAINT fk_management_report_values_submission FOREIGN KEY(submission_id) REFERENCES management_report_submissions(id) ON DELETE CASCADE,
  CONSTRAINT fk_management_report_values_field FOREIGN KEY(field_id) REFERENCES management_report_fields(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS management_report_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, submission_id BIGINT UNSIGNED NOT NULL, field_id INT UNSIGNED NULL,
  original_name VARCHAR(255) NOT NULL, storage_path VARCHAR(500) NOT NULL, mime_type VARCHAR(190) NOT NULL, file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_management_report_attachments_submission(submission_id),
  CONSTRAINT fk_management_report_attachments_submission FOREIGN KEY(submission_id) REFERENCES management_report_submissions(id) ON DELETE CASCADE,
  CONSTRAINT fk_management_report_attachments_field FOREIGN KEY(field_id) REFERENCES management_report_fields(id) ON DELETE SET NULL,
  CONSTRAINT fk_management_report_attachments_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS management_report_reviews (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, submission_id BIGINT UNSIGNED NOT NULL,
  action ENUM('created','draft_saved','submitted','returned','approved','archived','reopened') NOT NULL,
  old_status VARCHAR(30) NULL, new_status VARCHAR(30) NULL, note TEXT NULL, created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_management_report_reviews_submission(submission_id,created_at),
  CONSTRAINT fk_management_report_reviews_submission FOREIGN KEY(submission_id) REFERENCES management_report_submissions(id) ON DELETE CASCADE,
  CONSTRAINT fk_management_report_reviews_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS uploaded_files_backup (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, file_key CHAR(64) NOT NULL, original_name VARCHAR(255) NOT NULL,
  relative_path VARCHAR(500) NOT NULL, file_size BIGINT UNSIGNED NOT NULL DEFAULT 0, file_hash CHAR(64) NULL,
  mime_type VARCHAR(190) NOT NULL DEFAULT 'application/octet-stream', backup_status ENUM('pending','synced','error') NOT NULL DEFAULT 'pending',
  backup_confirmed_at DATETIME NULL, deleted_from_host TINYINT(1) NOT NULL DEFAULT 0, deleted_from_host_at DATETIME NULL,
  last_error TEXT NULL, download_attempts INT UNSIGNED NOT NULL DEFAULT 0, last_attempt_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_uploaded_files_backup_key(file_key), UNIQUE KEY uq_uploaded_files_backup_path(relative_path),
  INDEX idx_uploaded_files_backup_queue(backup_status,deleted_from_host,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS uploaded_files_backup_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, file_id BIGINT UNSIGNED NULL, action VARCHAR(50) NOT NULL,
  status VARCHAR(30) NOT NULL, message TEXT NULL, actor_type VARCHAR(30) NOT NULL DEFAULT 'system', actor_user_id INT UNSIGNED NULL,
  ip_address VARCHAR(45) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_uploaded_files_backup_logs_file(file_id,created_at), INDEX idx_uploaded_files_backup_logs_action(action,created_at),
  CONSTRAINT fk_uploaded_files_backup_logs_file FOREIGN KEY(file_id) REFERENCES uploaded_files_backup(id) ON DELETE SET NULL,
  CONSTRAINT fk_uploaded_files_backup_logs_user FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sobhan_notification_devices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,device_uid CHAR(36) NOT NULL,
  device_name VARCHAR(190) NOT NULL,device_type VARCHAR(30) NOT NULL DEFAULT 'windows',app_version VARCHAR(30) NOT NULL,
  machine_fingerprint_hash CHAR(64) NOT NULL,token_hash CHAR(64) NOT NULL,last_seen_at DATETIME NULL,active TINYINT(1) NOT NULL DEFAULT 1,
  revoked_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_notification_device_uid(device_uid),INDEX idx_notification_devices_user(user_id,active),INDEX idx_notification_devices_fingerprint(user_id,machine_fingerprint_hash),
  CONSTRAINT fk_notification_devices_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sobhan_notification_delivery_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,notification_id BIGINT UNSIGNED NOT NULL,device_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(30) NOT NULL,action VARCHAR(40) NULL,reply_text TEXT NULL,delivered_at DATETIME NULL,clicked_at DATETIME NULL,error_message VARCHAR(1000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_notification_delivery(notification_id,device_id),INDEX idx_notification_delivery_device(device_id,status,created_at),
  CONSTRAINT fk_notification_delivery_notification FOREIGN KEY(notification_id) REFERENCES sobhan_notifications(id) ON DELETE CASCADE,
  CONSTRAINT fk_notification_delivery_device FOREIGN KEY(device_id) REFERENCES sobhan_notification_devices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sobhan_user_notification_module_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,module VARCHAR(50) NOT NULL,enabled TINYINT(1) NOT NULL DEFAULT 1,
  show_body TINYINT(1) NOT NULL DEFAULT 1,sound VARCHAR(50) NOT NULL DEFAULT 'default',priority VARCHAR(30) NOT NULL DEFAULT 'normal',
  allow_quick_reply TINYINT(1) NOT NULL DEFAULT 0,direct_action_enabled TINYINT(1) NOT NULL DEFAULT 0,desktop_enabled TINYINT(1) NOT NULL DEFAULT 1,
  mobile_enabled TINYINT(1) NOT NULL DEFAULT 1,email_enabled TINYINT(1) NOT NULL DEFAULT 0,sms_enabled TINYINT(1) NOT NULL DEFAULT 0,
  silent_hours_enabled TINYINT(1) NOT NULL DEFAULT 0,silent_from TIME NULL,silent_to TIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_user_notification_module(user_id,module),
  INDEX idx_user_notification_module_enabled(user_id,desktop_enabled,enabled),CONSTRAINT fk_user_notification_module_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sobhan_notification_pairing_codes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,code_hash CHAR(64) NOT NULL,expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,created_ip VARCHAR(45) NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_notification_pair_code_hash(code_hash),INDEX idx_notification_pair_user(user_id,expires_at),
  CONSTRAINT fk_notification_pair_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sobhan_notification_pairing_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,ip_hash CHAR(64) NOT NULL,success TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_notification_pair_attempt(ip_hash,attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS management_meetings (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,title VARCHAR(255) NOT NULL,meeting_type VARCHAR(50) NOT NULL DEFAULT 'general',meeting_date DATE NOT NULL,
 start_time TIME NULL,end_time TIME NULL,location VARCHAR(255) NULL,organizer_user_id INT UNSIGNED NULL,secretary_user_id INT UNSIGNED NULL,
 attendees_json LONGTEXT NULL,absent_users_json LONGTEXT NULL,agenda TEXT NULL,meeting_summary LONGTEXT NULL,status VARCHAR(30) NOT NULL DEFAULT 'draft',
 attachments_json LONGTEXT NULL,created_by INT UNSIGNED NOT NULL,updated_by INT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL,
 INDEX idx_management_meeting_date(meeting_date),INDEX idx_management_meeting_status(status),INDEX idx_management_meeting_organizer(organizer_user_id),
 CONSTRAINT fk_management_meeting_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS management_decisions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,meeting_id BIGINT UNSIGNED NOT NULL,title VARCHAR(255) NOT NULL,description LONGTEXT NULL,
 decision_type VARCHAR(50) NOT NULL DEFAULT 'decision',category VARCHAR(100) NULL,responsible_user_id INT UNSIGNED NULL,responsible_unit_id INT UNSIGNED NULL,
 supervisor_user_id INT UNSIGNED NULL,priority VARCHAR(20) NOT NULL DEFAULT 'normal',due_date DATE NULL,followup_status VARCHAR(30) NOT NULL DEFAULT 'not_started',
 progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,latest_followup_note TEXT NULL,is_rule TINYINT(1) NOT NULL DEFAULT 0,rule_effective_date DATE NULL,
 rule_expire_date DATE NULL,closed_at DATETIME NULL,closed_by INT UNSIGNED NULL,verified_by INT UNSIGNED NULL,verification_status VARCHAR(30) NULL,
 created_by INT UNSIGNED NOT NULL,updated_by INT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL,
 INDEX idx_decision_meeting(meeting_id),INDEX idx_decision_responsible(responsible_user_id),INDEX idx_decision_unit(responsible_unit_id),
 INDEX idx_decision_status(followup_status),INDEX idx_decision_due(due_date),INDEX idx_decision_rule(is_rule),
 CONSTRAINT fk_decision_meeting FOREIGN KEY(meeting_id) REFERENCES management_meetings(id) ON DELETE RESTRICT,
 CONSTRAINT fk_decision_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS management_decision_followups (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,decision_id BIGINT UNSIGNED NOT NULL,old_status VARCHAR(30) NULL,new_status VARCHAR(30) NOT NULL,
 progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,followup_note TEXT NULL,next_followup_date DATE NULL,attachment_json LONGTEXT NULL,
 created_by INT UNSIGNED NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_followup_decision(decision_id),INDEX idx_followup_next(next_followup_date),
 CONSTRAINT fk_followup_decision FOREIGN KEY(decision_id) REFERENCES management_decisions(id) ON DELETE RESTRICT,
 CONSTRAINT fk_followup_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS management_rule_versions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,decision_id BIGINT UNSIGNED NOT NULL,rule_code VARCHAR(100) NOT NULL,title VARCHAR(255) NOT NULL,
 content LONGTEXT NOT NULL,version_number INT UNSIGNED NOT NULL DEFAULT 1,effective_date DATE NOT NULL,expire_date DATE NULL,
 scope_type VARCHAR(30) NOT NULL DEFAULT 'company',scope_value VARCHAR(190) NULL,active TINYINT(1) NOT NULL DEFAULT 0,approved_by INT UNSIGNED NULL,
 approved_at DATETIME NULL,created_by INT UNSIGNED NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_rule_version(rule_code,version_number),INDEX idx_rule_decision(decision_id),INDEX idx_rule_active(active),INDEX idx_rule_scope(scope_type,scope_value),
 CONSTRAINT fk_rule_decision FOREIGN KEY(decision_id) REFERENCES management_decisions(id) ON DELETE RESTRICT,
 CONSTRAINT fk_rule_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS hr_work_groups (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  code VARCHAR(60) NOT NULL UNIQUE,
  description TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_hr_work_groups_active(active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_attendance_settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  work_group_id INT UNSIGNED NOT NULL,
  effective_from DATE NOT NULL,
  effective_to DATE NULL,
  default_start_time TIME NOT NULL,
  default_end_time TIME NOT NULL,
  late_tolerance_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  early_leave_tolerance_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  allowed_checkin_from TIME NULL,
  allowed_checkin_to TIME NULL,
  allowed_checkout_from TIME NULL,
  allowed_checkout_to TIME NULL,
  allow_before_shift_overtime TINYINT(1) NOT NULL DEFAULT 0,
  allow_after_shift_overtime TINYINT(1) NOT NULL DEFAULT 1,
  require_overtime_approval TINYINT(1) NOT NULL DEFAULT 1,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_hr_attendance_setting_version(work_group_id,effective_from),
  INDEX idx_hr_attendance_setting_active(work_group_id,active,effective_from),
  CONSTRAINT fk_hr_attendance_setting_group FOREIGN KEY(work_group_id) REFERENCES hr_work_groups(id),
  CONSTRAINT fk_hr_attendance_setting_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_month_holidays (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  holiday_date DATE NOT NULL,
  jalali_year SMALLINT UNSIGNED NOT NULL,
  jalali_month TINYINT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  holiday_type ENUM('official','company','internal','half_day') NOT NULL DEFAULT 'official',
  applies_to_group ENUM('all','sales','admin_warehouse') NOT NULL DEFAULT 'all',
  is_half_day TINYINT(1) NOT NULL DEFAULT 0,
  description TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_hr_holiday_date_group(holiday_date,applies_to_group),
  INDEX idx_hr_holiday_jalali(jalali_year,jalali_month),
  INDEX idx_hr_holiday_active(holiday_date,active),
  CONSTRAINT fk_hr_holiday_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_attendance_entries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id INT UNSIGNED NOT NULL,
  work_group_id INT UNSIGNED NOT NULL,
  attendance_date DATE NOT NULL,
  is_holiday TINYINT(1) NOT NULL DEFAULT 0,
  holiday_id INT UNSIGNED NULL,
  approved_start_time TIME NULL,
  approved_end_time TIME NULL,
  actual_in_time TIME NULL,
  actual_out_time TIME NULL,
  break_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  late_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  early_leave_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  normal_overtime_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  holiday_overtime_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  work_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  day_status ENUM('present','absent','leave','mission','holiday','half_day') NOT NULL DEFAULT 'present',
  overtime_status ENUM('none','pending','approved','rejected') NOT NULL DEFAULT 'none',
  notes TEXT NULL,
  attachment_path VARCHAR(500) NULL,
  created_by INT UNSIGNED NULL,
  approved_by INT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_hr_attendance_employee_date(employee_id,attendance_date),
  INDEX idx_hr_attendance_date(attendance_date),
  INDEX idx_hr_attendance_group(work_group_id,attendance_date),
  INDEX idx_hr_attendance_status(day_status,overtime_status),
  CONSTRAINT fk_hr_attendance_employee FOREIGN KEY(employee_id) REFERENCES users(id),
  CONSTRAINT fk_hr_attendance_group FOREIGN KEY(work_group_id) REFERENCES hr_work_groups(id),
  CONSTRAINT fk_hr_attendance_holiday FOREIGN KEY(holiday_id) REFERENCES hr_month_holidays(id) ON DELETE SET NULL,
  CONSTRAINT fk_hr_attendance_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_hr_attendance_approver FOREIGN KEY(approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_attendance_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attendance_entry_id BIGINT UNSIGNED NOT NULL,
  action ENUM('create','update','approve_overtime','reject_overtime','delete_soft','manual_override') NOT NULL,
  old_value_json LONGTEXT NULL,
  new_value_json LONGTEXT NULL,
  performed_by INT UNSIGNED NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_hr_attendance_log_entry(attendance_entry_id,created_at),
  INDEX idx_hr_attendance_log_actor(performed_by,created_at),
  CONSTRAINT fk_hr_attendance_log_entry FOREIGN KEY(attendance_entry_id) REFERENCES hr_attendance_entries(id),
  CONSTRAINT fk_hr_attendance_log_actor FOREIGN KEY(performed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE OR REPLACE VIEW vw_hr_attendance_monthly_summary AS
SELECT employee_id,YEAR(attendance_date) AS `year`,MONTH(attendance_date) AS `month`,
  SUM(late_minutes) AS total_late_minutes,
  SUM(early_leave_minutes) AS total_early_leave_minutes,
  SUM(CASE WHEN overtime_status='approved' THEN normal_overtime_minutes ELSE 0 END) AS total_normal_overtime_minutes,
  SUM(CASE WHEN overtime_status='approved' THEN holiday_overtime_minutes ELSE 0 END) AS total_holiday_overtime_minutes,
  SUM(day_status='absent') AS absent_days,
  SUM(day_status='leave') AS leave_days,
  SUM(day_status='mission') AS mission_days,
  SUM(day_status IN ('present','half_day')) AS present_days,
  ROUND(GREATEST(0,10-(((SUM(late_minutes)+SUM(early_leave_minutes))/30)*0.5)-(SUM(day_status='absent')*2)),2) AS attendance_score_suggestion
FROM hr_attendance_entries
GROUP BY employee_id,YEAR(attendance_date),MONTH(attendance_date);

-- Independent ticketing module.
CREATE TABLE IF NOT EXISTS ticket_categories (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,title VARCHAR(190) NOT NULL,description TEXT NULL,assigned_unit_id INT UNSIGNED NULL,default_assignee_user_id INT UNSIGNED NULL,active TINYINT(1) NOT NULL DEFAULT 1,sort_order INT NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_ticket_category_active(active,sort_order)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS tickets (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,ticket_no VARCHAR(40) NULL,subject VARCHAR(255) NOT NULL,category_id INT UNSIGNED NOT NULL,priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',requester_user_id INT UNSIGNED NOT NULL,assigned_user_id INT UNSIGNED NULL,assigned_unit_id INT UNSIGNED NULL,due_at DATETIME NULL,status ENUM('open','assigned','in_progress','waiting_user','waiting_admin','resolved','closed','cancelled') NOT NULL DEFAULT 'open',last_message_at DATETIME NULL,resolved_at DATETIME NULL,closed_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_ticket_no(ticket_no),INDEX idx_ticket_requester(requester_user_id,status),INDEX idx_ticket_assignee(assigned_user_id,status),INDEX idx_ticket_unit(assigned_unit_id,status),INDEX idx_ticket_due(status,due_at),CONSTRAINT fk_ticket_category FOREIGN KEY(category_id) REFERENCES ticket_categories(id) ON DELETE RESTRICT,CONSTRAINT fk_ticket_requester FOREIGN KEY(requester_user_id) REFERENCES users(id) ON DELETE RESTRICT,CONSTRAINT fk_ticket_assignee FOREIGN KEY(assigned_user_id) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS ticket_messages (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,ticket_id BIGINT UNSIGNED NOT NULL,user_id INT UNSIGNED NOT NULL,message TEXT NOT NULL,is_internal TINYINT(1) NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL,INDEX idx_ticket_message(ticket_id,created_at),CONSTRAINT fk_ticket_message_ticket FOREIGN KEY(ticket_id) REFERENCES tickets(id) ON DELETE RESTRICT,CONSTRAINT fk_ticket_message_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS ticket_attachments (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,ticket_id BIGINT UNSIGNED NOT NULL,message_id BIGINT UNSIGNED NULL,uploaded_by INT UNSIGNED NOT NULL,original_name VARCHAR(255) NOT NULL,file_path VARCHAR(500) NOT NULL,mime_type VARCHAR(120) NULL,file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_ticket_attachment(ticket_id,message_id),CONSTRAINT fk_ticket_attachment_ticket FOREIGN KEY(ticket_id) REFERENCES tickets(id) ON DELETE RESTRICT,CONSTRAINT fk_ticket_attachment_message FOREIGN KEY(message_id) REFERENCES ticket_messages(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS ticket_status_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,ticket_id BIGINT UNSIGNED NOT NULL,actor_user_id INT UNSIGNED NULL,old_status VARCHAR(30) NULL,new_status VARCHAR(30) NOT NULL,note TEXT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_ticket_status_log(ticket_id,created_at),CONSTRAINT fk_ticket_status_ticket FOREIGN KEY(ticket_id) REFERENCES tickets(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS ticket_assignments (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,ticket_id BIGINT UNSIGNED NOT NULL,assigned_user_id INT UNSIGNED NULL,assigned_unit_id INT UNSIGNED NULL,assigned_by INT UNSIGNED NOT NULL,note TEXT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,ended_at DATETIME NULL,INDEX idx_ticket_assignment(ticket_id,created_at),CONSTRAINT fk_ticket_assignment_ticket FOREIGN KEY(ticket_id) REFERENCES tickets(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS ticket_sla_rules (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,category_id INT UNSIGNED NULL,priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',first_response_minutes INT UNSIGNED NOT NULL DEFAULT 480,resolution_minutes INT UNSIGNED NOT NULL DEFAULT 2880,active TINYINT(1) NOT NULL DEFAULT 1,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_ticket_sla(category_id,priority),CONSTRAINT fk_ticket_sla_category FOREIGN KEY(category_id) REFERENCES ticket_categories(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS sales_offer_budget_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, request_code VARCHAR(50) NOT NULL, requested_by BIGINT UNSIGNED NOT NULL,
  sales_manager_id BIGINT UNSIGNED NULL, sales_line VARCHAR(100) NULL, period_key VARCHAR(50) NULL, date_from DATE NULL, date_to DATE NULL,
  product_code VARCHAR(100) NULL, product_name VARCHAR(255) NULL, brand_name VARCHAR(255) NULL, supplier_name VARCHAR(255) NULL,
  purchase_price DECIMAL(18,2) NOT NULL DEFAULT 0, requested_offer_qty DECIMAL(18,3) NOT NULL DEFAULT 0, sold_qty DECIMAL(18,3) NOT NULL DEFAULT 0,
  sold_amount DECIMAL(18,2) NOT NULL DEFAULT 0, provisional_offer_rate DECIMAL(10,4) NOT NULL DEFAULT 0, provisional_budget DECIMAL(18,2) NOT NULL DEFAULT 0,
  formula_version VARCHAR(50) NOT NULL DEFAULT 'provisional_v1', formula_snapshot_json JSON NULL, status VARCHAR(30) NOT NULL DEFAULT 'draft',
  manager_note TEXT NULL, admin_note TEXT NULL, reviewed_by BIGINT UNSIGNED NULL, reviewed_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL, UNIQUE KEY uq_offer_budget_code(request_code), INDEX idx_offer_budget_status(status), INDEX idx_offer_budget_manager(sales_manager_id),
  INDEX idx_offer_budget_product(product_code), INDEX idx_offer_budget_period(period_key), INDEX idx_offer_budget_dates(date_from,date_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS sales_offer_budget_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,request_id BIGINT UNSIGNED NOT NULL,action VARCHAR(50) NOT NULL,performed_by BIGINT UNSIGNED NULL,old_value_json JSON NULL,new_value_json JSON NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_offer_budget_log_request(request_id,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS sales_offer_formula_settings (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,formula_key VARCHAR(100) NOT NULL UNIQUE,title VARCHAR(255) NOT NULL,formula_version VARCHAR(50) NOT NULL,settings_json JSON NULL,active TINYINT(1) NOT NULL DEFAULT 1,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
