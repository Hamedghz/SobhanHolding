CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  username VARCHAR(100) NOT NULL UNIQUE,
  employee_no VARCHAR(50) NULL,
  kara_system_code VARCHAR(100) NULL,
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
  sales_line_id INT UNSIGNED NULL,
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
  UNIQUE KEY uq_users_employee_no(employee_no),
  UNIQUE KEY uq_users_kara_system_code(kara_system_code),
  INDEX idx_users_sales_line_id(sales_line_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_lines (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL,
  title VARCHAR(190) NOT NULL,
  manager_user_id INT UNSIGNED NULL,
  supervisor_user_id INT UNSIGNED NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  description TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sales_lines_code(code),
  INDEX idx_sales_lines_manager(manager_user_id),
  INDEX idx_sales_lines_supervisor(supervisor_user_id),
  INDEX idx_sales_lines_active(active),
  CONSTRAINT fk_sales_lines_manager FOREIGN KEY(manager_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_sales_lines_supervisor FOREIGN KEY(supervisor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_line_brands (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  line_id INT UNSIGNED NOT NULL,
  brand_code VARCHAR(80) NULL,
  brand_name VARCHAR(190) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sales_line_brand_name(line_id,brand_name),
  INDEX idx_sales_line_brands_line(line_id),
  INDEX idx_sales_line_brands_active(active),
  CONSTRAINT fk_sales_line_brands_line FOREIGN KEY(line_id) REFERENCES sales_lines(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_geographies (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id INT UNSIGNED NULL,
  type ENUM('city','region') NOT NULL DEFAULT 'city',
  code VARCHAR(80) NOT NULL,
  title VARCHAR(190) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sales_geographies_code(code),
  INDEX idx_sales_geographies_parent(parent_id),
  INDEX idx_sales_geographies_type(type),
  INDEX idx_sales_geographies_active(active),
  CONSTRAINT fk_sales_geographies_parent FOREIGN KEY(parent_id) REFERENCES sales_geographies(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_visitor_territories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  visitor_user_id INT UNSIGNED NOT NULL,
  line_id INT UNSIGNED NOT NULL,
  geography_id INT UNSIGNED NOT NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  notes TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sales_visitor_geography(visitor_user_id,geography_id),
  INDEX idx_sales_visitor_territories_visitor(visitor_user_id),
  INDEX idx_sales_visitor_territories_line(line_id),
  INDEX idx_sales_visitor_territories_geo(geography_id),
  INDEX idx_sales_visitor_territories_active(active),
  CONSTRAINT fk_sales_visitor_territories_visitor FOREIGN KEY(visitor_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_sales_visitor_territories_line FOREIGN KEY(line_id) REFERENCES sales_lines(id) ON DELETE CASCADE,
  CONSTRAINT fk_sales_visitor_territories_geo FOREIGN KEY(geography_id) REFERENCES sales_geographies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_structure_audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id INT UNSIGNED NULL,
  performed_by INT UNSIGNED NULL,
  payload_json LONGTEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sales_structure_logs_entity(entity_type,entity_id),
  INDEX idx_sales_structure_logs_actor(performed_by),
  INDEX idx_sales_structure_logs_created(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sms_settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, provider_name VARCHAR(100) NOT NULL DEFAULT 'bazyabpayam',
  wsdl_url VARCHAR(255) NOT NULL, url_api_base VARCHAR(255) NULL, username VARCHAR(100) NOT NULL,
  password_encrypted LONGTEXT NOT NULL, default_sender VARCHAR(50) NOT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_credit VARCHAR(100) NULL, last_credit_checked_at DATETIME NULL, last_test_status VARCHAR(50) NULL, last_test_message VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_sms_settings_active(is_active), CONSTRAINT fk_sms_settings_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sms_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(100) NOT NULL UNIQUE, title VARCHAR(200) NOT NULL, body TEXT NOT NULL,
  module_key VARCHAR(100) NULL, event_key VARCHAR(100) NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_sms_template_module(module_key,event_key), CONSTRAINT fk_sms_template_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sms_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, provider_name VARCHAR(100) NOT NULL DEFAULT 'bazyabpayam', sender VARCHAR(50) NOT NULL,
  message_body TEXT NOT NULL, message_hash VARCHAR(64) NULL, request_key CHAR(64) NULL, segment_count INT UNSIGNED NOT NULL DEFAULT 1, recipients_count INT UNSIGNED NOT NULL DEFAULT 0,
  valid_recipients_count INT UNSIGNED NOT NULL DEFAULT 0, invalid_recipients_count INT UNSIGNED NOT NULL DEFAULT 0, bulk_code VARCHAR(100) NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'queued', source_module VARCHAR(100) NULL, source_id BIGINT UNSIGNED NULL,
  created_by INT UNSIGNED NULL, sent_at DATETIME NULL, last_checked_at DATETIME NULL, error_code VARCHAR(50) NULL,
  error_message TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sms_request_key(request_key), INDEX idx_sms_messages_status(status), INDEX idx_sms_messages_bulk(bulk_code), INDEX idx_sms_messages_source(source_module,source_id),
  CONSTRAINT fk_sms_messages_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sms_message_recipients (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, message_id BIGINT UNSIGNED NOT NULL, mobile VARCHAR(20) NOT NULL,
  normalized_mobile VARCHAR(20) NOT NULL, delivery_status VARCHAR(50) NULL, provider_message_id VARCHAR(100) NULL,
  checked_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sms_recipient_message(message_id), INDEX idx_sms_recipient_mobile(normalized_mobile), INDEX idx_sms_delivery_status(delivery_status),
  CONSTRAINT fk_sms_recipient_message FOREIGN KEY(message_id) REFERENCES sms_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sms_gateway_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, level VARCHAR(30) NOT NULL DEFAULT 'error', error_code VARCHAR(100) NULL,
  safe_message VARCHAR(255) NOT NULL, provider_raw_masked TEXT NULL, context_json TEXT NULL, created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_sms_log_code(error_code), INDEX idx_sms_log_created(created_at),
  CONSTRAINT fk_sms_log_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
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
  `from_date` DATE NULL,
  `to_date` DATE NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ceo_periods_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_periods (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  period_key VARCHAR(100) NOT NULL,
  title VARCHAR(190) NOT NULL,
  period_type ENUM('daily','weekly','monthly','quarterly','half_yearly','yearly','custom') NOT NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  jalali_year SMALLINT UNSIGNED NULL,
  jalali_month TINYINT UNSIGNED NULL,
  scope_key VARCHAR(100) NOT NULL DEFAULT 'global',
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  is_system TINYINT(1) NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_system_period_key (period_key),
  INDEX idx_system_period_type (period_type,is_active,start_date,end_date),
  INDEX idx_system_period_scope (scope_key,is_active,sort_order),
  INDEX idx_system_period_current (is_current,is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dashboard_widget_preferences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scope_type VARCHAR(40) NOT NULL,
    scope_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    user_id INT UNSIGNED NOT NULL DEFAULT 0,
    widget_key VARCHAR(100) NOT NULL,
    title_override VARCHAR(190) NULL,
    visible TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    size_key VARCHAR(20) NOT NULL DEFAULT 'wide',
    default_period_key VARCHAR(40) NOT NULL DEFAULT 'monthly',
    default_filters_json LONGTEXT NULL,
    refresh_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    drilldown_enabled TINYINT(1) NOT NULL DEFAULT 1,
    data_source_key VARCHAR(150) NOT NULL,
    settings_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_dashboard_widget_preference (scope_type,scope_id,user_id,widget_key),
    INDEX idx_dashboard_widget_scope (scope_type,scope_id,user_id,visible,sort_order),
    INDEX idx_dashboard_widget_source (data_source_key)
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
  mobile_image_path VARCHAR(255) NULL,
  alt_text VARCHAR(255) NULL,
  button_text VARCHAR(100) NULL,
  button_link VARCHAR(255) NULL,
  link_target VARCHAR(10) NOT NULL DEFAULT '_self',
  placement VARCHAR(50) NOT NULL DEFAULT 'homepage',
  item_type VARCHAR(30) NOT NULL DEFAULT 'slider',
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_carousel_publication(status,placement,item_type,starts_at,ends_at,sort_order)
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

CREATE TABLE IF NOT EXISTS work_planner_tasks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, template_id INT UNSIGNED NULL, user_id INT UNSIGNED NOT NULL,
  employee_id INT UNSIGNED NULL, assigned_by INT UNSIGNED NULL, assigned_to_role_id INT UNSIGNED NULL, assigned_to_unit_id INT UNSIGNED NULL,
  title VARCHAR(190) NOT NULL, description TEXT NULL, task_type VARCHAR(40) NOT NULL DEFAULT 'custom',
  priority VARCHAR(20) NOT NULL DEFAULT 'normal', status VARCHAR(20) NOT NULL DEFAULT 'todo',
  start_date DATE NULL, due_date DATE NULL, started_at DATETIME NULL, paused_at DATETIME NULL, completed_at DATETIME NULL,
  progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0, related_module VARCHAR(100) NULL, related_record_id BIGINT UNSIGNED NULL,
  parent_task_id BIGINT UNSIGNED NULL, recurrence_key VARCHAR(100) NULL, client_request_key VARCHAR(64) NULL,
  is_locked TINYINT(1) NOT NULL DEFAULT 0, is_personal TINYINT(1) NOT NULL DEFAULT 0, is_visible_on_dashboard TINYINT(1) NOT NULL DEFAULT 1,
  manual_sort_order INT NOT NULL DEFAULT 0, recurrence_type VARCHAR(20) NOT NULL DEFAULT 'none', recurrence_interval INT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_work_planner_generated(template_id,user_id,start_date), UNIQUE KEY uq_work_planner_recurrence(recurrence_key),
  UNIQUE KEY uq_work_planner_client_request(user_id,client_request_key), INDEX idx_work_planner_user_status(user_id,status,due_date),
  INDEX idx_work_planner_scope(assigned_to_unit_id,assigned_to_role_id), INDEX idx_work_planner_related(related_module,related_record_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS work_planner_user_preferences (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL UNIQUE,
  default_view VARCHAR(20) NOT NULL DEFAULT 'list', dashboard_widget_enabled TINYINT(1) NOT NULL DEFAULT 1,
  show_in_progress_first TINYINT(1) NOT NULL DEFAULT 1, show_overdue_tasks TINYINT(1) NOT NULL DEFAULT 1,
  show_today_tasks TINYINT(1) NOT NULL DEFAULT 1, show_completed_tasks TINYINT(1) NOT NULL DEFAULT 0,
  preferred_grouping VARCHAR(20) NOT NULL DEFAULT 'status', preferred_sorting VARCHAR(20) NOT NULL DEFAULT 'priority',
  work_style VARCHAR(40) NULL, compact_mode TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS work_planner_task_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, task_id BIGINT UNSIGNED NOT NULL, user_id INT UNSIGNED NULL,
  action VARCHAR(40) NOT NULL, old_value_json LONGTEXT NULL, new_value_json LONGTEXT NULL, note TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_work_planner_logs_task(task_id,created_at), INDEX idx_work_planner_logs_user(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS work_planner_comments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, task_id BIGINT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL,
  comment_text TEXT NOT NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_work_planner_comments_task(task_id,created_at)
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
  contact_info TEXT NULL, logo_path VARCHAR(255) NULL, background_path VARCHAR(255) NULL, background_mime VARCHAR(120) NULL, watermark_text VARCHAR(190) NULL,
  header_html MEDIUMTEXT NULL, footer_html MEDIUMTEXT NULL,
  margin_top_mm TINYINT UNSIGNED NULL, margin_right_mm TINYINT UNSIGNED NULL, margin_bottom_mm TINYINT UNSIGNED NULL, margin_left_mm TINYINT UNSIGNED NULL,
  header_position_mm TINYINT UNSIGNED NULL, footer_position_mm TINYINT UNSIGNED NULL, is_default TINYINT(1) NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1,
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
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(190) NOT NULL, default_subject VARCHAR(255) NULL, default_body MEDIUMTEXT NULL, default_delta_json LONGTEXT NULL,
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
  template_id INT UNSIGNED NULL, letterhead_id INT UNSIGNED NULL, signature_id INT UNSIGNED NULL, body_html MEDIUMTEXT NOT NULL, body_delta_json LONGTEXT NULL, final_html LONGTEXT NULL,
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
  access_token_expires_at DATETIME NULL,
  auth_type ENUM('password','app_password','oauth2') NOT NULL DEFAULT 'password',
  account_scope ENUM('personal','department','role','shared','system') NOT NULL DEFAULT 'personal', owner_user_id INT UNSIGNED NULL,
  department_id INT UNSIGNED NULL, role_id INT UNSIGNED NULL, is_shared TINYINT(1) NOT NULL DEFAULT 0,
  sync_enabled TINYINT(1) NOT NULL DEFAULT 1, send_enabled TINYINT(1) NOT NULL DEFAULT 1, last_sync_at DATETIME NULL,
  sync_status ENUM('never','syncing','ok','partial','needs_reauth','error') NOT NULL DEFAULT 'never', sync_lock_token CHAR(64) NULL,
  sync_lock_expires_at DATETIME NULL, last_error TEXT NULL, active TINYINT(1) NOT NULL DEFAULT 1,
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
  calculation_type ENUM('manual','formula','system') NOT NULL DEFAULT 'manual', formula VARCHAR(1000) NULL, formula_definition_id BIGINT UNSIGNED NULL, default_value VARCHAR(500) NULL,
  visible_to_employee TINYINT(1) NOT NULL DEFAULT 1, visible_in_pdf TINYINT(1) NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_payroll_fields_active(active,sort_order), INDEX idx_payroll_fields_formula_definition(formula_definition_id)
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
  version_no INT UNSIGNED NOT NULL DEFAULT 1, active TINYINT(1) NOT NULL DEFAULT 1, created_by INT UNSIGNED NULL, updated_by INT UNSIGNED NULL,
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
  field_type VARCHAR(40) NOT NULL DEFAULT 'text',
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
  file_name VARCHAR(255) NULL, stored_file_path VARCHAR(500) NULL, file_hash VARCHAR(128) NULL, detected_sheet VARCHAR(255) NULL, detected_table VARCHAR(255) NULL, detected_range VARCHAR(100) NULL,
  period_key VARCHAR(50) NULL, snapshot_date DATE NULL, period_id BIGINT UNSIGNED NULL, retry_of_batch_id BIGINT UNSIGNED NULL, source_confidence DECIMAL(6,2) NULL,
  import_mode VARCHAR(30) NOT NULL DEFAULT 'skip_duplicates', status VARCHAR(30) NOT NULL DEFAULT 'uploaded', pipeline_status VARCHAR(40) NOT NULL DEFAULT 'uploaded',
  is_active_reference TINYINT NOT NULL DEFAULT 0, activated_at DATETIME NULL, activated_by BIGINT UNSIGNED NULL,
  total_rows INT NOT NULL DEFAULT 0, valid_rows INT NOT NULL DEFAULT 0, invalid_rows INT NOT NULL DEFAULT 0,
  duplicate_rows INT NOT NULL DEFAULT 0, imported_rows INT NOT NULL DEFAULT 0, updated_rows INT NOT NULL DEFAULT 0, skipped_rows INT NOT NULL DEFAULT 0,
  started_by BIGINT UNSIGNED NULL, started_at DATETIME NULL, finished_at DATETIME NULL, error_message TEXT NULL, metadata_json LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
  INDEX idx_sales_import_batches_status(status), INDEX idx_sales_import_batches_source_type(source_type),
  INDEX idx_sales_import_batches_source_module(source_module), INDEX idx_sales_import_batches_file_hash(file_hash),
  INDEX idx_sales_import_batches_started_by(started_by), INDEX idx_sales_import_batches_created_at(created_at), INDEX idx_sales_import_pipeline(pipeline_status)
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
  `row_number` INT NOT NULL, source_row_number INT NULL, source_sheet VARCHAR(255) NULL, source_table VARCHAR(255) NULL, source_row_hash CHAR(64) NULL, raw_json LONGTEXT NOT NULL, normalized_json LONGTEXT NULL,
  validation_status VARCHAR(30) NOT NULL DEFAULT 'pending', validation_errors_json LONGTEXT NULL, source_unique_key VARCHAR(191) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_staging_sales_batch(import_batch_id), INDEX idx_staging_sales_source(source_module),
  INDEX idx_staging_sales_validation(validation_status), INDEX idx_staging_sales_unique_key(source_unique_key), INDEX idx_staging_source_row_hash(source_row_hash),
  CONSTRAINT fk_staging_sales_batch FOREIGN KEY(import_batch_id) REFERENCES sales_import_batches(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_reference_import_batches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, source_module VARCHAR(50) NOT NULL, source_type VARCHAR(50) NOT NULL DEFAULT 'excel_upload',
  original_file_name VARCHAR(255) NULL, stored_file_path VARCHAR(500) NULL, file_hash VARCHAR(128) NULL,
  detected_sheet VARCHAR(255) NULL, detected_table VARCHAR(255) NULL, detected_range VARCHAR(100) NULL,
  period_key VARCHAR(50) NULL, snapshot_date DATE NULL, period_id BIGINT UNSIGNED NULL, retry_of_batch_id BIGINT UNSIGNED NULL, source_confidence DECIMAL(6,2) NULL,
  import_mode VARCHAR(50) NOT NULL DEFAULT 'replace_reference', status VARCHAR(50) NOT NULL DEFAULT 'uploaded', pipeline_status VARCHAR(40) NOT NULL DEFAULT 'uploaded',
  is_active_reference TINYINT NOT NULL DEFAULT 0, activated_at DATETIME NULL, activated_by BIGINT UNSIGNED NULL,
  total_rows INT NOT NULL DEFAULT 0, valid_rows INT NOT NULL DEFAULT 0, invalid_rows INT NOT NULL DEFAULT 0, duplicate_rows INT NOT NULL DEFAULT 0,
  inserted_rows INT NOT NULL DEFAULT 0, updated_rows INT NOT NULL DEFAULT 0, skipped_rows INT NOT NULL DEFAULT 0,
  started_by BIGINT UNSIGNED NULL, started_at DATETIME NULL, finished_at DATETIME NULL, error_message TEXT NULL, metadata_json LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
  INDEX idx_ref_batches_source_module(source_module), INDEX idx_ref_batches_source_type(source_type), INDEX idx_ref_batches_status(status),
  INDEX idx_ref_batches_pipeline(pipeline_status), INDEX idx_ref_batches_period(period_key), INDEX idx_ref_batches_active(is_active_reference),
  INDEX idx_ref_batches_hash(file_hash), INDEX idx_ref_batches_created(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staging_sales_reference_rows (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, import_batch_id BIGINT UNSIGNED NOT NULL, source_module VARCHAR(50) NOT NULL,
  `row_number` INT NOT NULL, source_row_number INT NULL, source_sheet VARCHAR(255) NULL, source_table VARCHAR(255) NULL, source_row_hash CHAR(64) NULL,
  source_unique_key VARCHAR(191) NULL, raw_json LONGTEXT NOT NULL, normalized_json LONGTEXT NULL,
  validation_status VARCHAR(50) NOT NULL DEFAULT 'pending', validation_errors_json LONGTEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ref_staging_batch(import_batch_id), INDEX idx_ref_staging_source(source_module), INDEX idx_ref_staging_key(source_unique_key),
  INDEX idx_ref_staging_status(validation_status), INDEX idx_ref_staging_row_hash(source_row_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_reference_import_errors (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, import_batch_id BIGINT UNSIGNED NOT NULL, source_module VARCHAR(50) NOT NULL,
  `row_number` INT NULL, error_code VARCHAR(100) NULL, error_message TEXT NOT NULL, raw_json LONGTEXT NULL, normalized_json LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ref_errors_batch(import_batch_id), INDEX idx_ref_errors_source(source_module), INDEX idx_ref_errors_code(error_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_aggregate_rows (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, import_batch_id BIGINT UNSIGNED NULL, source_unique_key VARCHAR(191) NULL,
  unique_code VARCHAR(191) NULL, invoice_type VARCHAR(100) NULL, invoice_number VARCHAR(100) NULL, sub_invoice_number VARCHAR(100) NULL,
  invoice_date_raw VARCHAR(100) NULL, invoice_date DATE NULL, turnover_month VARCHAR(100) NULL, turnover_year SMALLINT NULL, period_key VARCHAR(50) NULL,
  customer_code VARCHAR(100) NULL, customer_name VARCHAR(255) NULL, customer_guild_code VARCHAR(100) NULL, customer_guild_name VARCHAR(255) NULL,
  product_code VARCHAR(100) NULL, product_name VARCHAR(255) NULL, brand_code VARCHAR(100) NULL, brand_name VARCHAR(255) NULL,
  visitor_code VARCHAR(100) NULL, visitor_name VARCHAR(255) NULL,
  supervisor_code VARCHAR(100) NULL, supervisor_name VARCHAR(255) NULL,
  sales_manager_code VARCHAR(100) NULL, sales_manager_name VARCHAR(255) NULL,
  line_code VARCHAR(100) NULL, line_name VARCHAR(100) NULL,
  quantity DECIMAL(18,4) NULL, total_qty DECIMAL(18,4) NULL, gross_amount DECIMAL(20,2) NULL, discount_amount DECIMAL(20,2) NULL, net_amount DECIMAL(20,2) NULL,
  discount_total DECIMAL(18,4) NULL, return_quantity DECIMAL(18,4) NULL, return_amount DECIMAL(20,2) NULL, raw_json LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
  INDEX idx_sales_aggregate_batch(import_batch_id), UNIQUE KEY uq_sales_aggregate_source_key(source_unique_key), INDEX idx_sales_aggregate_date(invoice_date),
  INDEX idx_sales_aggregate_customer(customer_code), INDEX idx_sales_aggregate_product(product_code), INDEX idx_sales_aggregate_visitor(visitor_code),
  INDEX idx_sales_aggregate_turnover_period(turnover_year,turnover_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_aggregate_rows (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, import_batch_id BIGINT UNSIGNED NOT NULL, source_unique_key VARCHAR(191) NOT NULL,
  invoice_type VARCHAR(100) NULL, invoice_number VARCHAR(100) NULL, invoice_date_raw VARCHAR(100) NULL, invoice_date DATE NULL,
  supplier_code VARCHAR(100) NULL, supplier_name VARCHAR(255) NULL, manufacturer_code VARCHAR(100) NULL, manufacturer_name VARCHAR(255) NULL,
  line_code VARCHAR(100) NULL, line_name VARCHAR(255) NULL, product_code VARCHAR(100) NULL, product_name VARCHAR(255) NULL,
  quantity DECIMAL(18,4) NULL, gross_amount DECIMAL(20,2) NULL, discount_amount DECIMAL(20,2) NULL, net_amount DECIMAL(20,2) NULL,
  brand_code VARCHAR(100) NULL, brand_name VARCHAR(255) NULL, raw_json LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
  INDEX idx_purchase_batch(import_batch_id), INDEX idx_purchase_key(source_unique_key), INDEX idx_purchase_date(invoice_date),
  INDEX idx_purchase_supplier(supplier_code), INDEX idx_purchase_product(product_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_aggregate_rows (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, import_batch_id BIGINT UNSIGNED NULL, source_type VARCHAR(30) NULL, source_unique_key VARCHAR(191) NULL,
  period_key VARCHAR(50) NULL, snapshot_date DATE NULL, period_id BIGINT UNSIGNED NULL,
  product_code VARCHAR(100) NULL,product_name VARCHAR(255) NULL,index_code VARCHAR(100) NULL,index_name VARCHAR(255) NULL,consumer_price DECIMAL(20,4) NULL,expire_date DATE NULL,expire_date_raw VARCHAR(100) NULL,manufacturer_code VARCHAR(100) NULL,manufacturer_name VARCHAR(255) NULL,
  sales_carton_qty DECIMAL(20,4) NULL,sales_part_qty DECIMAL(20,4) NULL,sales_total_qty DECIMAL(20,4) NULL,sales_total_amount DECIMAL(20,4) NULL,sales_discount_amount DECIMAL(20,4) NULL,sales_tax_amount DECIMAL(20,4) NULL,sales_duty_amount DECIMAL(20,4) NULL,sales_payable_amount DECIMAL(20,4) NULL,sales_return_carton_qty DECIMAL(20,4) NULL,sales_return_part_qty DECIMAL(20,4) NULL,sales_return_total_qty DECIMAL(20,4) NULL,
  purchase_carton_qty DECIMAL(20,4) NULL,purchase_part_qty DECIMAL(20,4) NULL,purchase_total_qty DECIMAL(20,4) NULL,opening_carton_qty DECIMAL(20,4) NULL,opening_part_qty DECIMAL(20,4) NULL,opening_total_qty DECIMAL(20,4) NULL,inbound_carton_qty DECIMAL(20,4) NULL,inbound_part_qty DECIMAL(20,4) NULL,inbound_total_qty DECIMAL(20,4) NULL,outbound_carton_qty DECIMAL(20,4) NULL,outbound_part_qty DECIMAL(20,4) NULL,outbound_total_qty DECIMAL(20,4) NULL,current_period_carton_qty DECIMAL(20,4) NULL,current_period_part_qty DECIMAL(20,4) NULL,current_period_total_qty DECIMAL(20,4) NULL,carton_size DECIMAL(20,4) NULL,
  last_cost_price DECIMAL(20,4) NULL,last_purchase_price DECIMAL(20,4) NULL,stock_value_by_last_cost DECIMAL(20,4) NULL,stock_value_by_sale_price_1 DECIMAL(20,4) NULL,retail_price DECIMAL(20,4) NULL,wholesale_price DECIMAL(20,4) NULL,sale_price_3 DECIMAL(20,4) NULL,sale_price_4 DECIMAL(20,4) NULL,sale_price_5 DECIMAL(20,4) NULL,sale_price_6 DECIMAL(20,4) NULL,sale_price_7 DECIMAL(20,4) NULL,sale_price_8 DECIMAL(20,4) NULL,sale_price_9 DECIMAL(20,4) NULL,sale_price_10 DECIMAL(20,4) NULL,sale_price_11 DECIMAL(20,4) NULL,sale_price_12 DECIMAL(20,4) NULL,
  retail_commission DECIMAL(20,4) NULL,wholesale_commission DECIMAL(20,4) NULL,commission_3 DECIMAL(20,4) NULL,commission_4 DECIMAL(20,4) NULL,commission_5 DECIMAL(20,4) NULL,commission_6 DECIMAL(20,4) NULL,commission_7 DECIMAL(20,4) NULL,commission_8 DECIMAL(20,4) NULL,commission_9 DECIMAL(20,4) NULL,commission_10 DECIMAL(20,4) NULL,commission_11 DECIMAL(20,4) NULL,commission_12 DECIMAL(20,4) NULL,
  current_weight DECIMAL(20,4) NULL,current_volume DECIMAL(20,4) NULL,product_tree_group_code VARCHAR(100) NULL,product_tree_group_name VARCHAR(255) NULL,barcode VARCHAR(191) NULL,control_code VARCHAR(191) NULL,group_code VARCHAR(100) NULL,group_name VARCHAR(255) NULL,retail_collection_days DECIMAL(20,4) NULL,current_base_stock DECIMAL(20,4) NULL,current_part_stock DECIMAL(20,4) NULL,current_total_stock DECIMAL(20,4) NULL,period_total_stock DECIMAL(20,4) NULL,brand_name VARCHAR(255) NULL,last_purchase_date DATE NULL,last_purchase_date_raw VARCHAR(100) NULL,raw_json LONGTEXT NULL,
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
  period_id BIGINT UNSIGNED NULL, guild_identity_key VARCHAR(191) NULL, customer_class_code VARCHAR(100) NULL,
  customer_class_title VARCHAR(255) NULL, normalized_guild_name VARCHAR(191) NULL, coefficient DECIMAL(12,6) NULL,
  effective_from DATE NULL, effective_to DATE NULL, version_no INT UNSIGNED NOT NULL DEFAULT 1,
  source_type VARCHAR(30) NOT NULL DEFAULT 'import', active TINYINT(1) NOT NULL DEFAULT 1, created_by INT UNSIGNED NULL, raw_json LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
  INDEX idx_sales_coeff_batch(import_batch_id), INDEX idx_sales_coeff_unique_key(source_unique_key), INDEX idx_sales_coeff_class(customer_class_code),
  INDEX idx_sales_coeff_effective(effective_from,effective_to), INDEX idx_sales_coeff_period(period_id),
  INDEX idx_sales_coeff_identity(guild_identity_key), INDEX idx_sales_coeff_active_version(active,version_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_priorities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, import_batch_id BIGINT UNSIGNED NULL, source_unique_key VARCHAR(191) NULL,
  period_id BIGINT UNSIGNED NULL,
  product_code VARCHAR(100) NULL, product_name VARCHAR(255) NULL, brand_code VARCHAR(100) NULL, brand_name VARCHAR(255) NULL,
  priority_code VARCHAR(100) NULL, priority_rank INT NULL, inventory_quantity DECIMAL(18,4) NULL, inventory_value DECIMAL(20,2) NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'active', active TINYINT(1) NOT NULL DEFAULT 1, created_by INT UNSIGNED NULL,
  raw_json LONGTEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
  INDEX idx_product_priorities_batch(import_batch_id), INDEX idx_product_priorities_unique_key(source_unique_key),
  INDEX idx_product_priorities_product(product_code), INDEX idx_product_priorities_brand(brand_code), INDEX idx_product_priorities_priority(priority_code),
  INDEX idx_product_priorities_period(period_id), INDEX idx_product_priorities_status(status,active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_targets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, import_batch_id BIGINT UNSIGNED NULL, source_unique_key VARCHAR(191) NULL,
  period_id BIGINT UNSIGNED NULL, visitor_user_id INT UNSIGNED NULL, line_id INT UNSIGNED NULL,
  target_year SMALLINT NULL, target_month TINYINT NULL, line_code VARCHAR(100) NULL, product_code VARCHAR(100) NULL,
  product_name VARCHAR(255) NULL, brand_code VARCHAR(100) NULL, brand_name VARCHAR(255) NULL,
  priority_code VARCHAR(100) NULL, visitor_code VARCHAR(100) NULL, supervisor_code VARCHAR(100) NULL,
  target_quantity DECIMAL(18,4) NULL, target_amount DECIMAL(20,2) NULL, allocation_percent DECIMAL(8,4) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1, source_type VARCHAR(30) NOT NULL DEFAULT 'import', created_by INT UNSIGNED NULL, raw_json LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
  INDEX idx_sales_targets_batch(import_batch_id), INDEX idx_sales_targets_unique_key(source_unique_key), INDEX idx_sales_targets_period(target_year,target_month),
  INDEX idx_sales_targets_line(line_code), INDEX idx_sales_targets_product(product_code), INDEX idx_sales_targets_visitor(visitor_code),
  INDEX idx_sales_targets_period_id(period_id), INDEX idx_sales_targets_visitor_user(visitor_user_id), INDEX idx_sales_targets_line_id(line_id),
  INDEX idx_sales_targets_grain(period_id,visitor_user_id,line_id,product_code), INDEX idx_sales_targets_active(active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE OR REPLACE VIEW vw_active_sales_aggregate_rows AS
SELECT r.* FROM sales_aggregate_rows r
JOIN sales_import_batches b ON b.id=r.import_batch_id AND b.source_module='sales_aggregate' AND b.is_active_reference=1 AND b.status='committed';

CREATE OR REPLACE VIEW vw_active_inventory_aggregate_rows AS
SELECT r.* FROM inventory_aggregate_rows r
JOIN sales_import_batches b ON b.id=r.import_batch_id AND b.source_module='inventory_aggregate' AND b.is_active_reference=1 AND b.status='committed';

CREATE OR REPLACE VIEW vw_active_purchase_aggregate_rows AS
SELECT r.* FROM purchase_aggregate_rows r
JOIN sales_import_batches b ON b.id=r.import_batch_id AND b.source_module='purchase_aggregate' AND b.is_active_reference=1 AND b.status='committed';

CREATE OR REPLACE VIEW vw_active_sales_targets AS
SELECT t.* FROM sales_targets t
LEFT JOIN sales_import_batches b ON b.id=t.import_batch_id
WHERE t.active=1
  AND (t.import_batch_id IS NULL OR (b.source_module='sales_targets' AND b.is_active_reference=1 AND b.status='committed'))
  AND (t.import_batch_id IS NULL OR NOT EXISTS (
      SELECT 1 FROM sales_targets manual
      WHERE manual.import_batch_id IS NULL AND manual.active=1
        AND manual.period_id=t.period_id AND manual.visitor_user_id=t.visitor_user_id
        AND manual.line_id=t.line_id AND manual.product_code=t.product_code
  ));

CREATE OR REPLACE VIEW vw_active_product_priorities AS
SELECT p.* FROM product_priorities p
LEFT JOIN sales_import_batches b ON b.id=p.import_batch_id
WHERE p.active=1 AND p.status='active'
  AND (p.import_batch_id IS NULL OR (b.source_module='product_priorities' AND b.is_active_reference=1 AND b.status='committed'));

CREATE OR REPLACE VIEW vw_active_customer_class_coefficients AS
SELECT c.* FROM sales_customer_class_coefficients c
LEFT JOIN sales_import_batches b ON b.id=c.import_batch_id
WHERE c.active=1
  AND (c.import_batch_id IS NULL OR (b.source_module='customer_coefficients' AND b.is_active_reference=1 AND b.status='committed'))
  AND (c.import_batch_id IS NULL OR NOT EXISTS (
      SELECT 1 FROM sales_customer_class_coefficients manual
      WHERE manual.import_batch_id IS NULL AND manual.active=1
        AND manual.period_id <=> c.period_id AND manual.guild_identity_key=c.guild_identity_key
  ));

CREATE OR REPLACE VIEW vw_sales_target_achievement AS
SELECT
  t.id target_id,t.period_id,p.period_key,p.title period_title,p.start_date,p.end_date,
  t.visitor_user_id,u.name visitor_name,u.employee_no visitor_code,
  t.line_id,l.code line_code,l.title line_title,
  t.product_code,t.product_name,t.brand_code,t.brand_name,
  t.target_quantity,t.target_amount,t.allocation_percent,
  COALESCE((
    SELECT SUM(COALESCE(s.total_qty,s.quantity,0)-COALESCE(s.return_quantity,0))
    FROM vw_active_sales_aggregate_rows s
    WHERE s.invoice_date BETWEEN p.start_date AND p.end_date
      AND s.product_code=t.product_code AND s.line_code=l.code
      AND (s.visitor_code=u.employee_no OR s.visitor_code=u.kara_system_code)
  ),0) achievement_quantity,
  COALESCE((
    SELECT SUM(COALESCE(s.net_amount,0)-COALESCE(s.return_amount,0))
    FROM vw_active_sales_aggregate_rows s
    WHERE s.invoice_date BETWEEN p.start_date AND p.end_date
      AND s.product_code=t.product_code AND s.line_code=l.code
      AND (s.visitor_code=u.employee_no OR s.visitor_code=u.kara_system_code)
  ),0) achievement_amount
FROM vw_active_sales_targets t
JOIN system_periods p ON p.id=t.period_id
JOIN users u ON u.id=t.visitor_user_id
JOIN sales_lines l ON l.id=t.line_id;

CREATE OR REPLACE VIEW vw_sales_target_visitor_totals AS
SELECT period_id,period_key,period_title,visitor_user_id,visitor_name,line_id,line_code,line_title,
  SUM(target_quantity) target_quantity,SUM(target_amount) target_amount,
  SUM(achievement_quantity) achievement_quantity,SUM(achievement_amount) achievement_amount
FROM vw_sales_target_achievement
GROUP BY period_id,period_key,period_title,visitor_user_id,visitor_name,line_id,line_code,line_title;

CREATE OR REPLACE VIEW vw_sales_target_line_products AS
SELECT period_id,period_key,period_title,line_id,line_code,line_title,product_code,
  MAX(product_name) product_name,MAX(brand_code) brand_code,MAX(brand_name) brand_name,
  SUM(target_quantity) target_quantity,SUM(target_amount) target_amount,
  SUM(achievement_quantity) achievement_quantity,SUM(achievement_amount) achievement_amount
FROM vw_sales_target_achievement
GROUP BY period_id,period_key,period_title,line_id,line_code,line_title,product_code;

CREATE OR REPLACE VIEW vw_sales_target_line_totals AS
SELECT period_id,period_key,period_title,line_id,line_code,line_title,
  SUM(target_quantity) target_quantity,SUM(target_amount) target_amount,
  SUM(achievement_quantity) achievement_quantity,SUM(achievement_amount) achievement_amount
FROM vw_sales_target_achievement
GROUP BY period_id,period_key,period_title,line_id,line_code,line_title;

CREATE OR REPLACE VIEW vw_sales_target_brand_totals AS
SELECT period_id,period_key,period_title,
  COALESCE(NULLIF(brand_code,''),CONCAT('name:',COALESCE(NULLIF(brand_name,''),'بدون برند'))) brand_key,
  COALESCE(NULLIF(brand_name,''),'بدون برند') brand_name,
  SUM(target_quantity) target_quantity,SUM(target_amount) target_amount,
  SUM(achievement_quantity) achievement_quantity,SUM(achievement_amount) achievement_amount
FROM vw_sales_target_achievement
GROUP BY period_id,period_key,period_title,brand_key,brand_name;

CREATE TABLE IF NOT EXISTS commission_formula_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, formula_key VARCHAR(100) NOT NULL, title VARCHAR(255) NULL, formula_expression TEXT NULL,
  settings_json LONGTEXT NULL, version_no INT NOT NULL DEFAULT 1, status VARCHAR(30) NOT NULL DEFAULT 'draft',
  effective_from DATE NULL, effective_to DATE NULL, created_by BIGINT UNSIGNED NULL, published_by BIGINT UNSIGNED NULL,
  published_at DATETIME NULL, raw_json LONGTEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
  UNIQUE KEY uq_commission_formula_version(formula_key,version_no), INDEX idx_commission_formula_status(status),
  INDEX idx_commission_formula_effective(effective_from,effective_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS formula_definitions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  formula_key VARCHAR(100) NOT NULL,
  title VARCHAR(190) NOT NULL,
  category_key VARCHAR(60) NOT NULL,
  description TEXT NULL,
  owner_scope VARCHAR(60) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_formula_definition_key(formula_key),
  INDEX idx_formula_definition_category(category_key,active),
  CONSTRAINT fk_formula_definition_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS formula_versions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  definition_id BIGINT UNSIGNED NOT NULL,
  version_no INT UNSIGNED NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'draft',
  effective_from DATE NULL,
  effective_to DATE NULL,
  data_source_key VARCHAR(100) NOT NULL,
  metric_key VARCHAR(100) NOT NULL,
  comparison_metric_key VARCHAR(100) NULL,
  aggregation_key VARCHAR(30) NOT NULL,
  operator_key VARCHAR(30) NOT NULL,
  condition_value_json LONGTEXT NULL,
  result_type VARCHAR(40) NOT NULL,
  result_value DECIMAL(20,6) NOT NULL DEFAULT 0,
  priority INT NOT NULL DEFAULT 100,
  user_note TEXT NULL,
  rule_json LONGTEXT NOT NULL,
  created_by INT UNSIGNED NULL,
  published_by INT UNSIGNED NULL,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_formula_definition_version(definition_id,version_no),
  INDEX idx_formula_version_status(status,effective_from,effective_to),
  INDEX idx_formula_version_source(data_source_key,metric_key,priority),
  CONSTRAINT fk_formula_version_definition FOREIGN KEY(definition_id) REFERENCES formula_definitions(id) ON DELETE RESTRICT,
  CONSTRAINT fk_formula_version_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_formula_version_publisher FOREIGN KEY(published_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS formula_filters (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  formula_version_id BIGINT UNSIGNED NOT NULL,
  field_key VARCHAR(100) NOT NULL,
  operator_key VARCHAR(30) NOT NULL,
  value_json LONGTEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_formula_filter_version(formula_version_id,sort_order),
  CONSTRAINT fk_formula_filter_version FOREIGN KEY(formula_version_id) REFERENCES formula_versions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS formula_dependencies (
  formula_version_id BIGINT UNSIGNED NOT NULL,
  depends_on_definition_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(formula_version_id,depends_on_definition_id),
  INDEX idx_formula_dependency_target(depends_on_definition_id),
  CONSTRAINT fk_formula_dependency_version FOREIGN KEY(formula_version_id) REFERENCES formula_versions(id) ON DELETE CASCADE,
  CONSTRAINT fk_formula_dependency_definition FOREIGN KEY(depends_on_definition_id) REFERENCES formula_definitions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS formula_audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  definition_id BIGINT UNSIGNED NULL,
  formula_version_id BIGINT UNSIGNED NULL,
  actor_user_id INT UNSIGNED NULL,
  action VARCHAR(60) NOT NULL,
  old_value_json LONGTEXT NULL,
  new_value_json LONGTEXT NULL,
  note VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_formula_audit_definition(definition_id,created_at),
  INDEX idx_formula_audit_version(formula_version_id,created_at),
  INDEX idx_formula_audit_actor(actor_user_id,created_at),
  CONSTRAINT fk_formula_audit_definition FOREIGN KEY(definition_id) REFERENCES formula_definitions(id) ON DELETE SET NULL,
  CONSTRAINT fk_formula_audit_version FOREIGN KEY(formula_version_id) REFERENCES formula_versions(id) ON DELETE SET NULL,
  CONSTRAINT fk_formula_audit_actor FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS formula_test_runs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  formula_version_id BIGINT UNSIGNED NOT NULL,
  actor_user_id INT UNSIGNED NULL,
  context_json LONGTEXT NULL,
  input_values_json LONGTEXT NULL,
  trace_json LONGTEXT NOT NULL,
  matched TINYINT(1) NOT NULL DEFAULT 0,
  final_result DECIMAL(20,6) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_formula_test_version(formula_version_id,created_at),
  INDEX idx_formula_test_actor(actor_user_id,created_at),
  CONSTRAINT fk_formula_test_version FOREIGN KEY(formula_version_id) REFERENCES formula_versions(id) ON DELETE RESTRICT,
  CONSTRAINT fk_formula_test_actor FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE SET NULL
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
  submitter_id INT UNSIGNED NOT NULL, unit_id INT UNSIGNED NULL, template_version_no INT UNSIGNED NOT NULL DEFAULT 1,
  schema_snapshot_json LONGTEXT NULL, status ENUM('draft','submitted','returned','approved','archived') NOT NULL DEFAULT 'draft',
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

CREATE TABLE IF NOT EXISTS management_report_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, submission_id BIGINT UNSIGNED NOT NULL, field_id INT UNSIGNED NULL,
  link_type VARCHAR(40) NOT NULL, linked_type VARCHAR(80) NOT NULL, linked_id BIGINT UNSIGNED NOT NULL,
  link_url VARCHAR(500) NULL, label VARCHAR(255) NULL, created_by INT UNSIGNED NULL, active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
  UNIQUE KEY uq_management_report_field_link(submission_id,field_id,link_type),
  INDEX idx_management_report_links_target(linked_type,linked_id),
  CONSTRAINT fk_management_report_links_submission FOREIGN KEY(submission_id) REFERENCES management_report_submissions(id) ON DELETE CASCADE,
  CONSTRAINT fk_management_report_links_field FOREIGN KEY(field_id) REFERENCES management_report_fields(id) ON DELETE SET NULL,
  CONSTRAINT fk_management_report_links_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
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
  import_batch_id BIGINT UNSIGNED NULL,
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
  day_status VARCHAR(30) NOT NULL DEFAULT 'present',
  leave_type VARCHAR(100) NULL,
  mission_details TEXT NULL,
  overtime_status ENUM('none','pending','approved','rejected') NOT NULL DEFAULT 'none',
  notes TEXT NULL,
  import_time_notes TEXT NULL,
  attachment_path VARCHAR(500) NULL,
  created_by INT UNSIGNED NULL,
  approved_by INT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_hr_attendance_employee_date(employee_id,attendance_date),
  INDEX idx_hr_attendance_date(attendance_date),
  INDEX idx_hr_attendance_import_batch(import_batch_id),
  INDEX idx_hr_attendance_group(work_group_id,attendance_date),
  INDEX idx_hr_attendance_status(day_status,overtime_status),
  CONSTRAINT fk_hr_attendance_employee FOREIGN KEY(employee_id) REFERENCES users(id),
  CONSTRAINT fk_hr_attendance_group FOREIGN KEY(work_group_id) REFERENCES hr_work_groups(id),
  CONSTRAINT fk_hr_attendance_holiday FOREIGN KEY(holiday_id) REFERENCES hr_month_holidays(id) ON DELETE SET NULL,
  CONSTRAINT fk_hr_attendance_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_hr_attendance_approver FOREIGN KEY(approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hr_attendance_identity_mappings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_field VARCHAR(40) NOT NULL,
  external_code VARCHAR(100) NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_hr_attendance_identity(source_field,external_code),
  INDEX idx_hr_attendance_identity_user(user_id,active),
  CONSTRAINT fk_hr_attendance_identity_user FOREIGN KEY(user_id) REFERENCES users(id),
  CONSTRAINT fk_hr_attendance_identity_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
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
  SUM(day_status IN ('present','half_day','holiday_work')) AS present_days,
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

-- OKR MVP: additive organization objectives, measurable results, check-ins and planner links.
CREATE TABLE IF NOT EXISTS okr_cycles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,title VARCHAR(190) NOT NULL,cycle_type VARCHAR(30) NOT NULL DEFAULT 'quarterly',start_date DATE NOT NULL,end_date DATE NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'draft',registration_deadline DATE NULL,approval_deadline DATE NULL,checkin_frequency VARCHAR(20) NOT NULL DEFAULT 'weekly',checkin_count INT UNSIGNED NOT NULL DEFAULT 0,created_by INT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_okr_cycle_title_period(title,start_date,end_date),INDEX idx_okr_cycles_status_dates(status,start_date,end_date),CONSTRAINT fk_okr_cycles_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS okr_objectives (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,cycle_id INT UNSIGNED NOT NULL,parent_objective_id BIGINT UNSIGNED NULL,owner_user_id INT UNSIGNED NOT NULL,org_unit_id INT UNSIGNED NULL,sales_line VARCHAR(50) NULL,objective_level VARCHAR(30) NOT NULL DEFAULT 'employee',title VARCHAR(255) NOT NULL,description TEXT NULL,okr_type VARCHAR(20) NOT NULL DEFAULT 'committed',priority VARCHAR(20) NOT NULL DEFAULT 'normal',weight DECIMAL(7,2) NOT NULL DEFAULT 100.00,status VARCHAR(30) NOT NULL DEFAULT 'draft',progress_score DECIMAL(7,2) NOT NULL DEFAULT 0.00,health_status VARCHAR(20) NOT NULL DEFAULT 'on_track',start_date DATE NOT NULL,due_date DATE NOT NULL,created_by INT UNSIGNED NOT NULL,approved_by INT UNSIGNED NULL,approved_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_okr_objective_cycle_status(cycle_id,status,due_date),INDEX idx_okr_objective_owner(owner_user_id,status,due_date),INDEX idx_okr_objective_scope(org_unit_id,sales_line,status),INDEX idx_okr_objective_parent(parent_objective_id),CONSTRAINT fk_okr_objective_cycle FOREIGN KEY(cycle_id) REFERENCES okr_cycles(id) ON DELETE RESTRICT,CONSTRAINT fk_okr_objective_parent FOREIGN KEY(parent_objective_id) REFERENCES okr_objectives(id) ON DELETE RESTRICT,CONSTRAINT fk_okr_objective_owner FOREIGN KEY(owner_user_id) REFERENCES users(id) ON DELETE RESTRICT,CONSTRAINT fk_okr_objective_unit FOREIGN KEY(org_unit_id) REFERENCES org_units(id) ON DELETE SET NULL,CONSTRAINT fk_okr_objective_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT,CONSTRAINT fk_okr_objective_approver FOREIGN KEY(approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS okr_key_results (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,objective_id BIGINT UNSIGNED NOT NULL,title VARCHAR(255) NOT NULL,metric_type VARCHAR(30) NOT NULL DEFAULT 'number',baseline_value DECIMAL(20,4) NOT NULL DEFAULT 0,target_value DECIMAL(20,4) NOT NULL DEFAULT 0,current_value DECIMAL(20,4) NOT NULL DEFAULT 0,unit VARCHAR(40) NOT NULL DEFAULT 'count',direction VARCHAR(20) NOT NULL DEFAULT 'increase',weight DECIMAL(7,2) NOT NULL DEFAULT 0,data_source_type VARCHAR(30) NOT NULL DEFAULT 'manual',data_source_config_json LONGTEXT NULL,calculation_formula TEXT NULL,owner_user_id INT UNSIGNED NOT NULL,status VARCHAR(20) NOT NULL DEFAULT 'active',health_status VARCHAR(20) NOT NULL DEFAULT 'on_track',progress_percent DECIMAL(7,2) NOT NULL DEFAULT 0.00,due_date DATE NOT NULL,last_checkin_at DATETIME NULL,last_calculated_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_okr_kr_objective(objective_id,status,due_date),INDEX idx_okr_kr_owner(owner_user_id,status,due_date),CONSTRAINT fk_okr_kr_objective FOREIGN KEY(objective_id) REFERENCES okr_objectives(id) ON DELETE RESTRICT,CONSTRAINT fk_okr_kr_owner FOREIGN KEY(owner_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS okr_alignments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,child_objective_id BIGINT UNSIGNED NOT NULL,parent_objective_id BIGINT UNSIGNED NOT NULL,alignment_type VARCHAR(30) NOT NULL DEFAULT 'contributes',contribution_weight DECIMAL(7,2) NOT NULL DEFAULT 100.00,note VARCHAR(500) NULL,active TINYINT(1) NOT NULL DEFAULT 1,created_by INT UNSIGNED NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_okr_alignment_pair(child_objective_id,parent_objective_id),INDEX idx_okr_alignment_parent(parent_objective_id,active),INDEX idx_okr_alignment_child(child_objective_id,active),CONSTRAINT fk_okr_alignment_child FOREIGN KEY(child_objective_id) REFERENCES okr_objectives(id) ON DELETE RESTRICT,CONSTRAINT fk_okr_alignment_parent FOREIGN KEY(parent_objective_id) REFERENCES okr_objectives(id) ON DELETE RESTRICT,CONSTRAINT fk_okr_alignment_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS okr_approvals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,objective_id BIGINT UNSIGNED NOT NULL,requested_by INT UNSIGNED NOT NULL,approver_user_id INT UNSIGNED NULL,decision VARCHAR(20) NOT NULL DEFAULT 'pending',note TEXT NULL,decided_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_okr_approval_objective(objective_id,decision,created_at),INDEX idx_okr_approval_approver(approver_user_id,decision),CONSTRAINT fk_okr_approval_objective FOREIGN KEY(objective_id) REFERENCES okr_objectives(id) ON DELETE RESTRICT,CONSTRAINT fk_okr_approval_requester FOREIGN KEY(requested_by) REFERENCES users(id) ON DELETE RESTRICT,CONSTRAINT fk_okr_approval_approver FOREIGN KEY(approver_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS okr_checkins (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,objective_id BIGINT UNSIGNED NOT NULL,key_result_id BIGINT UNSIGNED NOT NULL,current_value DECIMAL(20,4) NOT NULL,progress_percent DECIMAL(7,2) NOT NULL,confidence_level VARCHAR(20) NOT NULL DEFAULT 'medium',health_status VARCHAR(20) NOT NULL DEFAULT 'on_track',blocker_text TEXT NULL,next_action TEXT NULL,note TEXT NULL,created_by INT UNSIGNED NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_okr_checkin_kr(key_result_id,created_at),INDEX idx_okr_checkin_objective(objective_id,created_at),INDEX idx_okr_checkin_creator(created_by,created_at),CONSTRAINT fk_okr_checkin_objective FOREIGN KEY(objective_id) REFERENCES okr_objectives(id) ON DELETE RESTRICT,CONSTRAINT fk_okr_checkin_kr FOREIGN KEY(key_result_id) REFERENCES okr_key_results(id) ON DELETE RESTRICT,CONSTRAINT fk_okr_checkin_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS okr_initiatives (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,objective_id BIGINT UNSIGNED NOT NULL,key_result_id BIGINT UNSIGNED NULL,owner_user_id INT UNSIGNED NOT NULL,title VARCHAR(255) NOT NULL,description TEXT NULL,priority VARCHAR(20) NOT NULL DEFAULT 'normal',status VARCHAR(20) NOT NULL DEFAULT 'open',start_date DATE NOT NULL,due_date DATE NOT NULL,planner_task_id BIGINT UNSIGNED NULL,created_by INT UNSIGNED NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_okr_initiative_objective(objective_id,status,due_date),INDEX idx_okr_initiative_owner(owner_user_id,status,due_date),INDEX idx_okr_initiative_task(planner_task_id),CONSTRAINT fk_okr_initiative_objective FOREIGN KEY(objective_id) REFERENCES okr_objectives(id) ON DELETE RESTRICT,CONSTRAINT fk_okr_initiative_kr FOREIGN KEY(key_result_id) REFERENCES okr_key_results(id) ON DELETE RESTRICT,CONSTRAINT fk_okr_initiative_owner FOREIGN KEY(owner_user_id) REFERENCES users(id) ON DELETE RESTRICT,CONSTRAINT fk_okr_initiative_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS okr_task_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,objective_id BIGINT UNSIGNED NOT NULL,key_result_id BIGINT UNSIGNED NULL,initiative_id BIGINT UNSIGNED NULL,planner_task_id BIGINT UNSIGNED NOT NULL,created_by INT UNSIGNED NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_okr_task_link(planner_task_id),INDEX idx_okr_task_objective(objective_id,created_at),CONSTRAINT fk_okr_task_objective FOREIGN KEY(objective_id) REFERENCES okr_objectives(id) ON DELETE RESTRICT,CONSTRAINT fk_okr_task_kr FOREIGN KEY(key_result_id) REFERENCES okr_key_results(id) ON DELETE RESTRICT,CONSTRAINT fk_okr_task_initiative FOREIGN KEY(initiative_id) REFERENCES okr_initiatives(id) ON DELETE RESTRICT,CONSTRAINT fk_okr_task_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS okr_evidence (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,objective_id BIGINT UNSIGNED NOT NULL,key_result_id BIGINT UNSIGNED NULL,checkin_id BIGINT UNSIGNED NULL,original_name VARCHAR(255) NOT NULL,stored_name VARCHAR(255) NOT NULL,mime_type VARCHAR(120) NOT NULL,file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,uploaded_by INT UNSIGNED NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_okr_evidence_objective(objective_id,created_at),INDEX idx_okr_evidence_checkin(checkin_id),CONSTRAINT fk_okr_evidence_objective FOREIGN KEY(objective_id) REFERENCES okr_objectives(id) ON DELETE RESTRICT,CONSTRAINT fk_okr_evidence_kr FOREIGN KEY(key_result_id) REFERENCES okr_key_results(id) ON DELETE RESTRICT,CONSTRAINT fk_okr_evidence_checkin FOREIGN KEY(checkin_id) REFERENCES okr_checkins(id) ON DELETE RESTRICT,CONSTRAINT fk_okr_evidence_uploader FOREIGN KEY(uploaded_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS okr_score_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,objective_id BIGINT UNSIGNED NOT NULL,score_percent DECIMAL(7,2) NOT NULL,health_status VARCHAR(20) NOT NULL,source VARCHAR(30) NOT NULL DEFAULT 'checkin',recorded_by INT UNSIGNED NULL,recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_okr_score_objective(objective_id,recorded_at),CONSTRAINT fk_okr_score_objective FOREIGN KEY(objective_id) REFERENCES okr_objectives(id) ON DELETE RESTRICT,CONSTRAINT fk_okr_score_recorder FOREIGN KEY(recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS okr_audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,objective_id BIGINT UNSIGNED NULL,key_result_id BIGINT UNSIGNED NULL,actor_user_id INT UNSIGNED NULL,action VARCHAR(60) NOT NULL,old_value_json LONGTEXT NULL,new_value_json LONGTEXT NULL,note VARCHAR(500) NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_okr_audit_objective(objective_id,created_at),INDEX idx_okr_audit_kr(key_result_id,created_at),INDEX idx_okr_audit_actor(actor_user_id,created_at),CONSTRAINT fk_okr_audit_objective FOREIGN KEY(objective_id) REFERENCES okr_objectives(id) ON DELETE RESTRICT,CONSTRAINT fk_okr_audit_kr FOREIGN KEY(key_result_id) REFERENCES okr_key_results(id) ON DELETE RESTRICT,CONSTRAINT fk_okr_audit_actor FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS okr_reminder_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,objective_id BIGINT UNSIGNED NOT NULL,key_result_id BIGINT UNSIGNED NULL,recipient_user_id INT UNSIGNED NOT NULL,reminder_type VARCHAR(40) NOT NULL,reminder_key VARCHAR(80) NOT NULL,notification_id BIGINT UNSIGNED NULL,sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_okr_reminder_once(objective_id,recipient_user_id,reminder_type,reminder_key),INDEX idx_okr_reminder_recipient(recipient_user_id,sent_at),INDEX idx_okr_reminder_objective(objective_id,sent_at),CONSTRAINT fk_okr_reminder_objective FOREIGN KEY(objective_id) REFERENCES okr_objectives(id) ON DELETE RESTRICT,CONSTRAINT fk_okr_reminder_kr FOREIGN KEY(key_result_id) REFERENCES okr_key_results(id) ON DELETE RESTRICT,CONSTRAINT fk_okr_reminder_recipient FOREIGN KEY(recipient_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS okr_decision_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,decision_id BIGINT UNSIGNED NOT NULL,objective_id BIGINT UNSIGNED NOT NULL,key_result_id BIGINT UNSIGNED NULL,initiative_id BIGINT UNSIGNED NULL,planner_task_id BIGINT UNSIGNED NULL,link_note VARCHAR(500) NULL,created_by INT UNSIGNED NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_okr_decision_link(decision_id,objective_id,key_result_id),INDEX idx_okr_decision_objective(objective_id,created_at),INDEX idx_okr_decision_kr(key_result_id),CONSTRAINT fk_okr_decision_link_decision FOREIGN KEY(decision_id) REFERENCES management_decisions(id) ON DELETE RESTRICT,CONSTRAINT fk_okr_decision_link_objective FOREIGN KEY(objective_id) REFERENCES okr_objectives(id) ON DELETE RESTRICT,CONSTRAINT fk_okr_decision_link_kr FOREIGN KEY(key_result_id) REFERENCES okr_key_results(id) ON DELETE RESTRICT,CONSTRAINT fk_okr_decision_link_initiative FOREIGN KEY(initiative_id) REFERENCES okr_initiatives(id) ON DELETE RESTRICT,CONSTRAINT fk_okr_decision_link_task FOREIGN KEY(planner_task_id) REFERENCES work_planner_tasks(id) ON DELETE RESTRICT,CONSTRAINT fk_okr_decision_link_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS okr_ai_analyses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,objective_id BIGINT UNSIGNED NOT NULL,requested_by INT UNSIGNED NOT NULL,analysis_type VARCHAR(50) NOT NULL,context_summary_json LONGTEXT NULL,result_json LONGTEXT NULL,response_text LONGTEXT NULL,source VARCHAR(30) NOT NULL DEFAULT 'deterministic',status VARCHAR(20) NOT NULL DEFAULT 'success',error_message VARCHAR(500) NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_okr_ai_objective(objective_id,created_at),INDEX idx_okr_ai_requester(requested_by,created_at),CONSTRAINT fk_okr_ai_objective FOREIGN KEY(objective_id) REFERENCES okr_objectives(id) ON DELETE RESTRICT,CONSTRAINT fk_okr_ai_requester FOREIGN KEY(requested_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Universal Action Hub: one organization-wide action path with dynamic Persian fields and legacy adapters.
CREATE TABLE IF NOT EXISTS action_types (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,code VARCHAR(100) NOT NULL,title VARCHAR(190) NOT NULL,description TEXT NULL,color VARCHAR(20) NOT NULL DEFAULT '#2563eb',icon VARCHAR(80) NULL,active TINYINT(1) NOT NULL DEFAULT 1,requires_approval TINYINT(1) NOT NULL DEFAULT 0,required_fields_csv VARCHAR(500) NULL,sort_order INT NOT NULL DEFAULT 0,created_by INT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL,
  UNIQUE KEY uq_action_types_code(code),INDEX idx_action_types_active(active,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS action_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,action_type_id INT UNSIGNED NOT NULL,template_code VARCHAR(100) NOT NULL,title VARCHAR(190) NOT NULL,description TEXT NULL,instructions TEXT NULL,active TINYINT(1) NOT NULL DEFAULT 1,legacy_source_type VARCHAR(60) NULL,legacy_source_id BIGINT UNSIGNED NULL,created_by INT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL,
  UNIQUE KEY uq_action_templates_code(template_code),UNIQUE KEY uq_action_templates_legacy(legacy_source_type,legacy_source_id),INDEX idx_action_templates_type(action_type_id,active),CONSTRAINT fk_action_templates_type FOREIGN KEY(action_type_id) REFERENCES action_types(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS action_template_fields (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,template_id BIGINT UNSIGNED NOT NULL,field_key VARCHAR(100) NOT NULL,field_label VARCHAR(190) NOT NULL,field_type VARCHAR(50) NOT NULL,help_text TEXT NULL,placeholder VARCHAR(255) NULL,options_json LONGTEXT NULL,data_source VARCHAR(100) NULL,formula_expression TEXT NULL,default_value TEXT NULL,required TINYINT(1) NOT NULL DEFAULT 0,readonly TINYINT(1) NOT NULL DEFAULT 0,active TINYINT(1) NOT NULL DEFAULT 1,sort_order INT NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL,
  UNIQUE KEY uq_action_template_field(template_id,field_key),INDEX idx_action_template_fields(template_id,active,sort_order),CONSTRAINT fk_action_template_fields_template FOREIGN KEY(template_id) REFERENCES action_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS actions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,title VARCHAR(190) NOT NULL,description TEXT NULL,action_type_id INT UNSIGNED NOT NULL,template_id BIGINT UNSIGNED NULL,assigned_to INT UNSIGNED NOT NULL,assigned_by INT UNSIGNED NOT NULL,priority VARCHAR(20) NOT NULL DEFAULT 'normal',status VARCHAR(40) NOT NULL DEFAULT 'new',start_date DATE NULL,due_date DATE NULL,source_type VARCHAR(80) NOT NULL DEFAULT 'manual',source_id BIGINT UNSIGNED NULL,planner_task_id BIGINT UNSIGNED NULL,approval_required TINYINT(1) NOT NULL DEFAULT 0,approved_by INT UNSIGNED NULL,approved_at DATETIME NULL,legacy_source_type VARCHAR(60) NULL,legacy_source_id BIGINT UNSIGNED NULL,completed_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL,
  UNIQUE KEY uq_actions_legacy(legacy_source_type,legacy_source_id),INDEX idx_actions_assigned(assigned_to,status,due_date),INDEX idx_actions_assigner(assigned_by,status,created_at),INDEX idx_actions_type(action_type_id,status),INDEX idx_actions_source(source_type,source_id),INDEX idx_actions_due(status,due_date),CONSTRAINT fk_actions_type FOREIGN KEY(action_type_id) REFERENCES action_types(id) ON DELETE RESTRICT,CONSTRAINT fk_actions_template FOREIGN KEY(template_id) REFERENCES action_templates(id) ON DELETE SET NULL,CONSTRAINT fk_actions_assigned_to FOREIGN KEY(assigned_to) REFERENCES users(id) ON DELETE RESTRICT,CONSTRAINT fk_actions_assigned_by FOREIGN KEY(assigned_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS action_field_values (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,action_id BIGINT UNSIGNED NOT NULL,field_id BIGINT UNSIGNED NULL,field_key VARCHAR(100) NOT NULL,field_label VARCHAR(190) NULL,field_type VARCHAR(50) NOT NULL,value_text LONGTEXT NULL,value_number DECIMAL(20,4) NULL,value_date DATE NULL,value_datetime DATETIME NULL,value_json LONGTEXT NULL,file_path VARCHAR(500) NULL,file_name VARCHAR(255) NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL,
  UNIQUE KEY uq_action_field_value(action_id,field_key),INDEX idx_action_field_values_action(action_id),CONSTRAINT fk_action_field_values_action FOREIGN KEY(action_id) REFERENCES actions(id) ON DELETE CASCADE,CONSTRAINT fk_action_field_values_field FOREIGN KEY(field_id) REFERENCES action_template_fields(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS action_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,action_id BIGINT UNSIGNED NOT NULL,link_type VARCHAR(60) NOT NULL,linked_type VARCHAR(80) NULL,linked_id BIGINT UNSIGNED NULL,link_url VARCHAR(500) NULL,label VARCHAR(190) NULL,created_by INT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_action_link(action_id,link_type,linked_type,linked_id),INDEX idx_action_links_target(linked_type,linked_id),CONSTRAINT fk_action_links_action FOREIGN KEY(action_id) REFERENCES actions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS action_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,action_id BIGINT UNSIGNED NOT NULL,action_key VARCHAR(60) NOT NULL,old_status VARCHAR(40) NULL,new_status VARCHAR(40) NULL,note TEXT NULL,old_value_json LONGTEXT NULL,new_value_json LONGTEXT NULL,performed_by INT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_action_logs_action(action_id,created_at),INDEX idx_action_logs_actor(performed_by,created_at),CONSTRAINT fk_action_logs_action FOREIGN KEY(action_id) REFERENCES actions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Generic Daily Work Report: scoped templates, controlled data sources and report-to-action links.
CREATE TABLE IF NOT EXISTS daily_report_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,template_code VARCHAR(100) NOT NULL,title VARCHAR(190) NOT NULL,description TEXT NULL,version_no INT UNSIGNED NOT NULL DEFAULT 1,active TINYINT(1) NOT NULL DEFAULT 1,created_by INT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL,
  UNIQUE KEY uq_daily_report_template_code(template_code),INDEX idx_daily_report_templates_active(active,title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS daily_report_template_fields (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,template_id BIGINT UNSIGNED NOT NULL,field_key VARCHAR(100) NOT NULL,field_label VARCHAR(190) NOT NULL,input_type VARCHAR(40) NOT NULL DEFAULT 'long_text',source_type VARCHAR(40) NOT NULL DEFAULT 'manual',source_key VARCHAR(100) NULL,aggregation_key VARCHAR(30) NULL,formula_expression TEXT NULL,help_text TEXT NULL,placeholder VARCHAR(255) NULL,options_json LONGTEXT NULL,required TINYINT(1) NOT NULL DEFAULT 0,readonly TINYINT(1) NOT NULL DEFAULT 0,active TINYINT(1) NOT NULL DEFAULT 1,sort_order INT NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL,
  UNIQUE KEY uq_daily_report_template_field(template_id,field_key),INDEX idx_daily_report_fields(template_id,active,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS daily_report_template_assignments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,template_id BIGINT UNSIGNED NOT NULL,scope_type VARCHAR(40) NOT NULL,scope_id BIGINT UNSIGNED NOT NULL DEFAULT 0,scope_key VARCHAR(150) NOT NULL DEFAULT '',active TINYINT(1) NOT NULL DEFAULT 1,created_by INT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL,
  UNIQUE KEY uq_daily_report_assignment(template_id,scope_type,scope_id,scope_key),INDEX idx_daily_report_assignment_scope(scope_type,scope_id,scope_key,active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS daily_reports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,template_id BIGINT UNSIGNED NOT NULL,template_version_no INT UNSIGNED NOT NULL DEFAULT 1,user_id INT UNSIGNED NOT NULL,report_date DATE NOT NULL,status VARCHAR(30) NOT NULL DEFAULT 'draft',submitted_at DATETIME NULL,created_by INT UNSIGNED NOT NULL,legacy_source_type VARCHAR(60) NULL,legacy_source_id BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL,
  UNIQUE KEY uq_daily_report_user_date_template(user_id,report_date,template_id),UNIQUE KEY uq_daily_report_legacy(legacy_source_type,legacy_source_id),INDEX idx_daily_reports_user(user_id,report_date,status),INDEX idx_daily_reports_date(report_date,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS daily_report_values (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,report_id BIGINT UNSIGNED NOT NULL,field_id BIGINT UNSIGNED NULL,field_key VARCHAR(100) NOT NULL,field_label VARCHAR(190) NOT NULL,source_type VARCHAR(40) NOT NULL DEFAULT 'manual',value_text LONGTEXT NULL,value_number DECIMAL(20,4) NULL,value_date DATE NULL,display_text LONGTEXT NULL,readonly TINYINT(1) NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL,
  UNIQUE KEY uq_daily_report_value(report_id,field_key),INDEX idx_daily_report_values(report_id,source_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS daily_report_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,report_id BIGINT UNSIGNED NOT NULL,field_id BIGINT UNSIGNED NULL,link_type VARCHAR(40) NOT NULL,linked_type VARCHAR(80) NOT NULL,linked_id BIGINT UNSIGNED NOT NULL,link_url VARCHAR(500) NULL,label VARCHAR(190) NULL,snapshot_text TEXT NULL,created_by INT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_daily_report_link(report_id,link_type,linked_type,linked_id),INDEX idx_daily_report_links_target(linked_type,linked_id),INDEX idx_daily_report_links_report(report_id,field_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS daily_report_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,report_id BIGINT UNSIGNED NOT NULL,action_key VARCHAR(60) NOT NULL,note TEXT NULL,performed_by INT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_daily_report_logs(report_id,created_at),INDEX idx_daily_report_logs_actor(performed_by,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Canonical reporting contract. Imported sources are limited to active committed batches by the underlying active views.
CREATE OR REPLACE VIEW vw_sales_active AS
SELECT s.*,visitor.id visitor_user_id,supervisor.id supervisor_user_id,manager.id manager_user_id
FROM vw_active_sales_aggregate_rows s
LEFT JOIN users visitor ON visitor.status='active' AND (visitor.employee_no=s.visitor_code OR visitor.kara_system_code=s.visitor_code)
LEFT JOIN users supervisor ON supervisor.status='active' AND (supervisor.employee_no=s.supervisor_code OR supervisor.kara_system_code=s.supervisor_code)
LEFT JOIN users manager ON manager.status='active' AND (manager.employee_no=s.sales_manager_code OR manager.kara_system_code=s.sales_manager_code);

CREATE OR REPLACE VIEW vw_sales_by_period AS
SELECT COALESCE(NULLIF(period_key,''),DATE_FORMAT(invoice_date,'%Y-%m')) period_key,MIN(invoice_date) period_start,MAX(invoice_date) period_end,
visitor_user_id,supervisor_user_id,manager_user_id,visitor_code,visitor_name,supervisor_code,supervisor_name,sales_manager_code,sales_manager_name,line_code,line_name,
COUNT(DISTINCT CONCAT_WS(':',import_batch_id,invoice_number)) invoice_count,SUM(COALESCE(total_qty,quantity,0)) gross_quantity,SUM(COALESCE(return_quantity,0)) return_quantity,
SUM(COALESCE(total_qty,quantity,0)-COALESCE(return_quantity,0)) net_quantity,SUM(COALESCE(gross_amount,0)) gross_amount,
SUM(COALESCE(discount_total,discount_amount,0)) discount_amount,SUM(COALESCE(return_amount,0)) return_amount,SUM(COALESCE(net_amount,0)-COALESCE(return_amount,0)) net_sales_amount
FROM vw_sales_active GROUP BY period_key,visitor_user_id,supervisor_user_id,manager_user_id,visitor_code,visitor_name,supervisor_code,supervisor_name,sales_manager_code,sales_manager_name,line_code,line_name;

CREATE OR REPLACE VIEW vw_sales_by_visitor AS
SELECT visitor_user_id,supervisor_user_id,manager_user_id,visitor_code,visitor_name,supervisor_code,supervisor_name,sales_manager_code,sales_manager_name,line_code,line_name,
COUNT(DISTINCT CONCAT_WS(':',import_batch_id,invoice_number)) invoice_count,SUM(COALESCE(total_qty,quantity,0)) gross_quantity,SUM(COALESCE(return_quantity,0)) return_quantity,
SUM(COALESCE(total_qty,quantity,0)-COALESCE(return_quantity,0)) net_quantity,SUM(COALESCE(gross_amount,0)) gross_amount,
SUM(COALESCE(discount_total,discount_amount,0)) discount_amount,SUM(COALESCE(return_amount,0)) return_amount,SUM(COALESCE(net_amount,0)-COALESCE(return_amount,0)) net_sales_amount
FROM vw_sales_active GROUP BY visitor_user_id,supervisor_user_id,manager_user_id,visitor_code,visitor_name,supervisor_code,supervisor_name,sales_manager_code,sales_manager_name,line_code,line_name;

CREATE OR REPLACE VIEW vw_sales_by_supervisor AS
SELECT supervisor_user_id,manager_user_id,supervisor_code,supervisor_name,sales_manager_code,sales_manager_name,line_code,line_name,
COUNT(DISTINCT CONCAT_WS(':',import_batch_id,invoice_number)) invoice_count,SUM(COALESCE(total_qty,quantity,0)-COALESCE(return_quantity,0)) net_quantity,
SUM(COALESCE(gross_amount,0)) gross_amount,SUM(COALESCE(discount_total,discount_amount,0)) discount_amount,
SUM(COALESCE(return_amount,0)) return_amount,SUM(COALESCE(net_amount,0)-COALESCE(return_amount,0)) net_sales_amount
FROM vw_sales_active GROUP BY supervisor_user_id,manager_user_id,supervisor_code,supervisor_name,sales_manager_code,sales_manager_name,line_code,line_name;

CREATE OR REPLACE VIEW vw_sales_by_manager AS
SELECT manager_user_id,sales_manager_code,sales_manager_name,line_code,line_name,
COUNT(DISTINCT CONCAT_WS(':',import_batch_id,invoice_number)) invoice_count,SUM(COALESCE(total_qty,quantity,0)-COALESCE(return_quantity,0)) net_quantity,
SUM(COALESCE(gross_amount,0)) gross_amount,SUM(COALESCE(discount_total,discount_amount,0)) discount_amount,
SUM(COALESCE(return_amount,0)) return_amount,SUM(COALESCE(net_amount,0)-COALESCE(return_amount,0)) net_sales_amount
FROM vw_sales_active GROUP BY manager_user_id,sales_manager_code,sales_manager_name,line_code,line_name;

CREATE OR REPLACE VIEW vw_sales_by_line AS
SELECT line_code,line_name,COUNT(DISTINCT CONCAT_WS(':',import_batch_id,invoice_number)) invoice_count,
SUM(COALESCE(total_qty,quantity,0)-COALESCE(return_quantity,0)) net_quantity,SUM(COALESCE(gross_amount,0)) gross_amount,
SUM(COALESCE(discount_total,discount_amount,0)) discount_amount,SUM(COALESCE(return_amount,0)) return_amount,
SUM(COALESCE(net_amount,0)-COALESCE(return_amount,0)) net_sales_amount FROM vw_sales_active GROUP BY line_code,line_name;

CREATE OR REPLACE VIEW vw_sales_by_customer AS
SELECT visitor_user_id,supervisor_user_id,manager_user_id,visitor_code,visitor_name,supervisor_code,supervisor_name,sales_manager_code,sales_manager_name,line_code,line_name,
customer_code,customer_name,MAX(customer_guild_code) customer_guild_code,MAX(customer_guild_name) customer_guild_name,
COUNT(DISTINCT CONCAT_WS(':',import_batch_id,invoice_number)) invoice_count,SUM(COALESCE(total_qty,quantity,0)-COALESCE(return_quantity,0)) net_quantity,
SUM(COALESCE(net_amount,0)-COALESCE(return_amount,0)) net_sales_amount FROM vw_sales_active
GROUP BY visitor_user_id,supervisor_user_id,manager_user_id,visitor_code,visitor_name,supervisor_code,supervisor_name,sales_manager_code,sales_manager_name,line_code,line_name,customer_code,customer_name;

CREATE OR REPLACE VIEW vw_sales_by_product AS
SELECT visitor_user_id,supervisor_user_id,manager_user_id,visitor_code,visitor_name,supervisor_code,supervisor_name,sales_manager_code,sales_manager_name,line_code,line_name,
product_code,product_name,brand_code,brand_name,COUNT(DISTINCT CONCAT_WS(':',import_batch_id,invoice_number)) invoice_count,
SUM(COALESCE(total_qty,quantity,0)-COALESCE(return_quantity,0)) net_quantity,SUM(COALESCE(net_amount,0)-COALESCE(return_amount,0)) net_sales_amount
FROM vw_sales_active GROUP BY visitor_user_id,supervisor_user_id,manager_user_id,visitor_code,visitor_name,supervisor_code,supervisor_name,sales_manager_code,sales_manager_name,line_code,line_name,product_code,product_name,brand_code,brand_name;

CREATE OR REPLACE VIEW vw_sales_by_brand AS
SELECT visitor_user_id,supervisor_user_id,manager_user_id,visitor_code,visitor_name,supervisor_code,supervisor_name,sales_manager_code,sales_manager_name,line_code,line_name,
brand_code,brand_name,COUNT(DISTINCT product_code) product_count,COUNT(DISTINCT CONCAT_WS(':',import_batch_id,invoice_number)) invoice_count,
SUM(COALESCE(total_qty,quantity,0)-COALESCE(return_quantity,0)) net_quantity,SUM(COALESCE(net_amount,0)-COALESCE(return_amount,0)) net_sales_amount
FROM vw_sales_active GROUP BY visitor_user_id,supervisor_user_id,manager_user_id,visitor_code,visitor_name,supervisor_code,supervisor_name,sales_manager_code,sales_manager_name,line_code,line_name,brand_code,brand_name;

CREATE OR REPLACE VIEW vw_purchase_active AS SELECT * FROM vw_active_purchase_aggregate_rows;
CREATE OR REPLACE VIEW vw_purchase_by_supplier AS
SELECT supplier_code,supplier_name,line_code,line_name,COUNT(DISTINCT CONCAT_WS(':',import_batch_id,invoice_number)) invoice_count,
COUNT(DISTINCT product_code) product_count,SUM(quantity) quantity,SUM(gross_amount) gross_amount,SUM(discount_amount) discount_amount,SUM(net_amount) net_amount
FROM vw_active_purchase_aggregate_rows GROUP BY supplier_code,supplier_name,line_code,line_name;
CREATE OR REPLACE VIEW vw_inventory_current AS SELECT * FROM vw_active_inventory_aggregate_rows;
CREATE OR REPLACE VIEW vw_inventory_by_product AS
SELECT product_code,product_name,brand_name,group_code,group_name,MAX(snapshot_date) snapshot_date,
SUM(COALESCE(current_total_stock,period_total_stock)) stock_quantity,SUM(stock_value_by_last_cost) stock_value_by_last_cost,
SUM(stock_value_by_sale_price_1) stock_value_by_sale_price FROM vw_active_inventory_aggregate_rows
GROUP BY product_code,product_name,brand_name,group_code,group_name;
CREATE OR REPLACE VIEW vw_target_achievement AS SELECT * FROM vw_sales_target_achievement;
CREATE OR REPLACE VIEW vw_target_by_visitor AS SELECT * FROM vw_sales_target_visitor_totals;
CREATE OR REPLACE VIEW vw_target_by_line AS SELECT * FROM vw_sales_target_line_totals;
CREATE OR REPLACE VIEW vw_commission_inputs AS
SELECT a.*,p.priority_code,p.priority_rank,CASE WHEN COALESCE(a.target_amount,0)>0 THEN ROUND((a.achievement_amount/a.target_amount)*100,4) ELSE 0 END achievement_percent
FROM vw_sales_target_achievement a LEFT JOIN vw_active_product_priorities p ON p.period_id=a.period_id AND p.product_code=a.product_code;
CREATE OR REPLACE VIEW vw_attendance_period_summary AS
SELECT e.employee_id,YEAR(e.attendance_date) year,MONTH(e.attendance_date) month,MIN(e.attendance_date) period_start,MAX(e.attendance_date) period_end,
SUM(e.work_minutes) work_minutes,SUM(e.late_minutes) late_minutes,SUM(e.early_leave_minutes) early_leave_minutes,
SUM(CASE WHEN e.overtime_status='approved' THEN e.normal_overtime_minutes ELSE 0 END) normal_overtime_minutes,
SUM(CASE WHEN e.overtime_status='approved' THEN e.holiday_overtime_minutes ELSE 0 END) holiday_overtime_minutes,
SUM(e.day_status='absent') absent_days,SUM(e.day_status='leave') leave_days,SUM(e.day_status='mission') mission_days,
SUM(e.day_status IN ('present','half_day','holiday_work')) present_days FROM hr_attendance_entries e
LEFT JOIN sales_import_batches b ON b.id=e.import_batch_id
WHERE e.import_batch_id IS NULL OR (b.source_module='attendance' AND b.is_active_reference=1 AND b.status='committed')
GROUP BY e.employee_id,YEAR(e.attendance_date),MONTH(e.attendance_date);
CREATE OR REPLACE VIEW vw_action_workload AS
SELECT assigned_to user_id,status,priority,COUNT(*) action_count,SUM(due_date<CURDATE() AND status NOT IN ('done','cancelled')) overdue_count,
MIN(due_date) nearest_due_date,MAX(updated_at) last_activity_at FROM actions GROUP BY assigned_to,status,priority;
CREATE OR REPLACE VIEW vw_daily_report_completion AS
SELECT user_id,report_date,status,COUNT(*) report_count,SUM(status IN ('submitted','approved')) completed_count,MAX(submitted_at) last_submitted_at
FROM daily_reports GROUP BY user_id,report_date,status;
