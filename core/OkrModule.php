<?php
require_once __DIR__ . '/Database.php';

class OkrModule
{
    public static function repair(PDO $pdo): void
    {
        foreach (self::schema() as $sql) {
            $pdo->exec($sql);
        }
        self::seedPermissions($pdo);
    }

    public static function repairIntegrations(PDO $pdo): void
    {
        $engine = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $pdo->exec("CREATE TABLE IF NOT EXISTS okr_decision_links (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            decision_id BIGINT UNSIGNED NOT NULL,
            objective_id BIGINT UNSIGNED NOT NULL,
            key_result_id BIGINT UNSIGNED NULL,
            initiative_id BIGINT UNSIGNED NULL,
            planner_task_id BIGINT UNSIGNED NULL,
            link_note VARCHAR(500) NULL,
            created_by INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_okr_decision_link(decision_id,objective_id,key_result_id),
            INDEX idx_okr_decision_objective(objective_id,created_at),
            INDEX idx_okr_decision_kr(key_result_id),
            CONSTRAINT fk_okr_decision_link_decision FOREIGN KEY(decision_id) REFERENCES management_decisions(id) ON DELETE RESTRICT,
            CONSTRAINT fk_okr_decision_link_objective FOREIGN KEY(objective_id) REFERENCES okr_objectives(id) ON DELETE RESTRICT,
            CONSTRAINT fk_okr_decision_link_kr FOREIGN KEY(key_result_id) REFERENCES okr_key_results(id) ON DELETE RESTRICT,
            CONSTRAINT fk_okr_decision_link_initiative FOREIGN KEY(initiative_id) REFERENCES okr_initiatives(id) ON DELETE RESTRICT,
            CONSTRAINT fk_okr_decision_link_task FOREIGN KEY(planner_task_id) REFERENCES work_planner_tasks(id) ON DELETE RESTRICT,
            CONSTRAINT fk_okr_decision_link_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT
        ){$engine}");
    }

    public static function schema(): array
    {
        $engine = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        return [
            "CREATE TABLE IF NOT EXISTS okr_cycles (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(190) NOT NULL,
                cycle_type VARCHAR(30) NOT NULL DEFAULT 'quarterly',
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                registration_deadline DATE NULL,
                approval_deadline DATE NULL,
                checkin_frequency VARCHAR(20) NOT NULL DEFAULT 'weekly',
                checkin_count INT UNSIGNED NOT NULL DEFAULT 0,
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_okr_cycle_title_period(title,start_date,end_date),
                INDEX idx_okr_cycles_status_dates(status,start_date,end_date),
                CONSTRAINT fk_okr_cycles_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS okr_objectives (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                cycle_id INT UNSIGNED NOT NULL,
                parent_objective_id BIGINT UNSIGNED NULL,
                owner_user_id INT UNSIGNED NOT NULL,
                org_unit_id INT UNSIGNED NULL,
                sales_line VARCHAR(50) NULL,
                objective_level VARCHAR(30) NOT NULL DEFAULT 'employee',
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                okr_type VARCHAR(20) NOT NULL DEFAULT 'committed',
                priority VARCHAR(20) NOT NULL DEFAULT 'normal',
                weight DECIMAL(7,2) NOT NULL DEFAULT 100.00,
                status VARCHAR(30) NOT NULL DEFAULT 'draft',
                progress_score DECIMAL(7,2) NOT NULL DEFAULT 0.00,
                health_status VARCHAR(20) NOT NULL DEFAULT 'on_track',
                start_date DATE NOT NULL,
                due_date DATE NOT NULL,
                created_by INT UNSIGNED NOT NULL,
                approved_by INT UNSIGNED NULL,
                approved_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_okr_objective_cycle_status(cycle_id,status,due_date),
                INDEX idx_okr_objective_owner(owner_user_id,status,due_date),
                INDEX idx_okr_objective_scope(org_unit_id,sales_line,status),
                INDEX idx_okr_objective_parent(parent_objective_id),
                CONSTRAINT fk_okr_objective_cycle FOREIGN KEY(cycle_id) REFERENCES okr_cycles(id) ON DELETE RESTRICT,
                CONSTRAINT fk_okr_objective_parent FOREIGN KEY(parent_objective_id) REFERENCES okr_objectives(id) ON DELETE RESTRICT,
                CONSTRAINT fk_okr_objective_owner FOREIGN KEY(owner_user_id) REFERENCES users(id) ON DELETE RESTRICT,
                CONSTRAINT fk_okr_objective_unit FOREIGN KEY(org_unit_id) REFERENCES org_units(id) ON DELETE SET NULL,
                CONSTRAINT fk_okr_objective_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT,
                CONSTRAINT fk_okr_objective_approver FOREIGN KEY(approved_by) REFERENCES users(id) ON DELETE SET NULL
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS okr_key_results (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                objective_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                metric_type VARCHAR(30) NOT NULL DEFAULT 'number',
                baseline_value DECIMAL(20,4) NOT NULL DEFAULT 0,
                target_value DECIMAL(20,4) NOT NULL DEFAULT 0,
                current_value DECIMAL(20,4) NOT NULL DEFAULT 0,
                unit VARCHAR(40) NOT NULL DEFAULT 'count',
                direction VARCHAR(20) NOT NULL DEFAULT 'increase',
                weight DECIMAL(7,2) NOT NULL DEFAULT 0,
                data_source_type VARCHAR(30) NOT NULL DEFAULT 'manual',
                data_source_config_json LONGTEXT NULL,
                calculation_formula TEXT NULL,
                owner_user_id INT UNSIGNED NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                health_status VARCHAR(20) NOT NULL DEFAULT 'on_track',
                progress_percent DECIMAL(7,2) NOT NULL DEFAULT 0.00,
                due_date DATE NOT NULL,
                last_checkin_at DATETIME NULL,
                last_calculated_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_okr_kr_objective(objective_id,status,due_date),
                INDEX idx_okr_kr_owner(owner_user_id,status,due_date),
                CONSTRAINT fk_okr_kr_objective FOREIGN KEY(objective_id) REFERENCES okr_objectives(id) ON DELETE RESTRICT,
                CONSTRAINT fk_okr_kr_owner FOREIGN KEY(owner_user_id) REFERENCES users(id) ON DELETE RESTRICT
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS okr_alignments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                child_objective_id BIGINT UNSIGNED NOT NULL,
                parent_objective_id BIGINT UNSIGNED NOT NULL,
                alignment_type VARCHAR(30) NOT NULL DEFAULT 'contributes',
                contribution_weight DECIMAL(7,2) NOT NULL DEFAULT 100.00,
                note VARCHAR(500) NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_by INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_okr_alignment_pair(child_objective_id,parent_objective_id),
                INDEX idx_okr_alignment_parent(parent_objective_id,active),
                INDEX idx_okr_alignment_child(child_objective_id,active),
                CONSTRAINT fk_okr_alignment_child FOREIGN KEY(child_objective_id) REFERENCES okr_objectives(id) ON DELETE RESTRICT,
                CONSTRAINT fk_okr_alignment_parent FOREIGN KEY(parent_objective_id) REFERENCES okr_objectives(id) ON DELETE RESTRICT,
                CONSTRAINT fk_okr_alignment_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS okr_approvals (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                objective_id BIGINT UNSIGNED NOT NULL,
                requested_by INT UNSIGNED NOT NULL,
                approver_user_id INT UNSIGNED NULL,
                decision VARCHAR(20) NOT NULL DEFAULT 'pending',
                note TEXT NULL,
                decided_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_okr_approval_objective(objective_id,decision,created_at),
                INDEX idx_okr_approval_approver(approver_user_id,decision),
                CONSTRAINT fk_okr_approval_objective FOREIGN KEY(objective_id) REFERENCES okr_objectives(id) ON DELETE RESTRICT,
                CONSTRAINT fk_okr_approval_requester FOREIGN KEY(requested_by) REFERENCES users(id) ON DELETE RESTRICT,
                CONSTRAINT fk_okr_approval_approver FOREIGN KEY(approver_user_id) REFERENCES users(id) ON DELETE SET NULL
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS okr_checkins (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                objective_id BIGINT UNSIGNED NOT NULL,
                key_result_id BIGINT UNSIGNED NOT NULL,
                current_value DECIMAL(20,4) NOT NULL,
                progress_percent DECIMAL(7,2) NOT NULL,
                confidence_level VARCHAR(20) NOT NULL DEFAULT 'medium',
                health_status VARCHAR(20) NOT NULL DEFAULT 'on_track',
                blocker_text TEXT NULL,
                next_action TEXT NULL,
                note TEXT NULL,
                created_by INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_okr_checkin_kr(key_result_id,created_at),
                INDEX idx_okr_checkin_objective(objective_id,created_at),
                INDEX idx_okr_checkin_creator(created_by,created_at),
                CONSTRAINT fk_okr_checkin_objective FOREIGN KEY(objective_id) REFERENCES okr_objectives(id) ON DELETE RESTRICT,
                CONSTRAINT fk_okr_checkin_kr FOREIGN KEY(key_result_id) REFERENCES okr_key_results(id) ON DELETE RESTRICT,
                CONSTRAINT fk_okr_checkin_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS okr_initiatives (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                objective_id BIGINT UNSIGNED NOT NULL,
                key_result_id BIGINT UNSIGNED NULL,
                owner_user_id INT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                priority VARCHAR(20) NOT NULL DEFAULT 'normal',
                status VARCHAR(20) NOT NULL DEFAULT 'open',
                start_date DATE NOT NULL,
                due_date DATE NOT NULL,
                planner_task_id BIGINT UNSIGNED NULL,
                created_by INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_okr_initiative_objective(objective_id,status,due_date),
                INDEX idx_okr_initiative_owner(owner_user_id,status,due_date),
                INDEX idx_okr_initiative_task(planner_task_id),
                CONSTRAINT fk_okr_initiative_objective FOREIGN KEY(objective_id) REFERENCES okr_objectives(id) ON DELETE RESTRICT,
                CONSTRAINT fk_okr_initiative_kr FOREIGN KEY(key_result_id) REFERENCES okr_key_results(id) ON DELETE RESTRICT,
                CONSTRAINT fk_okr_initiative_owner FOREIGN KEY(owner_user_id) REFERENCES users(id) ON DELETE RESTRICT,
                CONSTRAINT fk_okr_initiative_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS okr_task_links (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                objective_id BIGINT UNSIGNED NOT NULL,
                key_result_id BIGINT UNSIGNED NULL,
                initiative_id BIGINT UNSIGNED NULL,
                planner_task_id BIGINT UNSIGNED NOT NULL,
                created_by INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_okr_task_link(planner_task_id),
                INDEX idx_okr_task_objective(objective_id,created_at),
                CONSTRAINT fk_okr_task_objective FOREIGN KEY(objective_id) REFERENCES okr_objectives(id) ON DELETE RESTRICT,
                CONSTRAINT fk_okr_task_kr FOREIGN KEY(key_result_id) REFERENCES okr_key_results(id) ON DELETE RESTRICT,
                CONSTRAINT fk_okr_task_initiative FOREIGN KEY(initiative_id) REFERENCES okr_initiatives(id) ON DELETE RESTRICT,
                CONSTRAINT fk_okr_task_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS okr_evidence (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                objective_id BIGINT UNSIGNED NOT NULL,
                key_result_id BIGINT UNSIGNED NULL,
                checkin_id BIGINT UNSIGNED NULL,
                original_name VARCHAR(255) NOT NULL,
                stored_name VARCHAR(255) NOT NULL,
                mime_type VARCHAR(120) NOT NULL,
                file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
                uploaded_by INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_okr_evidence_objective(objective_id,created_at),
                INDEX idx_okr_evidence_checkin(checkin_id),
                CONSTRAINT fk_okr_evidence_objective FOREIGN KEY(objective_id) REFERENCES okr_objectives(id) ON DELETE RESTRICT,
                CONSTRAINT fk_okr_evidence_kr FOREIGN KEY(key_result_id) REFERENCES okr_key_results(id) ON DELETE RESTRICT,
                CONSTRAINT fk_okr_evidence_checkin FOREIGN KEY(checkin_id) REFERENCES okr_checkins(id) ON DELETE RESTRICT,
                CONSTRAINT fk_okr_evidence_uploader FOREIGN KEY(uploaded_by) REFERENCES users(id) ON DELETE RESTRICT
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS okr_score_history (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                objective_id BIGINT UNSIGNED NOT NULL,
                score_percent DECIMAL(7,2) NOT NULL,
                health_status VARCHAR(20) NOT NULL,
                source VARCHAR(30) NOT NULL DEFAULT 'checkin',
                recorded_by INT UNSIGNED NULL,
                recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_okr_score_objective(objective_id,recorded_at),
                CONSTRAINT fk_okr_score_objective FOREIGN KEY(objective_id) REFERENCES okr_objectives(id) ON DELETE RESTRICT,
                CONSTRAINT fk_okr_score_recorder FOREIGN KEY(recorded_by) REFERENCES users(id) ON DELETE SET NULL
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS okr_audit_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                objective_id BIGINT UNSIGNED NULL,
                key_result_id BIGINT UNSIGNED NULL,
                actor_user_id INT UNSIGNED NULL,
                action VARCHAR(60) NOT NULL,
                old_value_json LONGTEXT NULL,
                new_value_json LONGTEXT NULL,
                note VARCHAR(500) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_okr_audit_objective(objective_id,created_at),
                INDEX idx_okr_audit_kr(key_result_id,created_at),
                INDEX idx_okr_audit_actor(actor_user_id,created_at),
                CONSTRAINT fk_okr_audit_objective FOREIGN KEY(objective_id) REFERENCES okr_objectives(id) ON DELETE RESTRICT,
                CONSTRAINT fk_okr_audit_kr FOREIGN KEY(key_result_id) REFERENCES okr_key_results(id) ON DELETE RESTRICT,
                CONSTRAINT fk_okr_audit_actor FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE SET NULL
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS okr_reminder_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                objective_id BIGINT UNSIGNED NOT NULL,
                key_result_id BIGINT UNSIGNED NULL,
                recipient_user_id INT UNSIGNED NOT NULL,
                reminder_type VARCHAR(40) NOT NULL,
                reminder_key VARCHAR(80) NOT NULL,
                notification_id BIGINT UNSIGNED NULL,
                sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_okr_reminder_once(objective_id,recipient_user_id,reminder_type,reminder_key),
                INDEX idx_okr_reminder_recipient(recipient_user_id,sent_at),
                INDEX idx_okr_reminder_objective(objective_id,sent_at),
                CONSTRAINT fk_okr_reminder_objective FOREIGN KEY(objective_id) REFERENCES okr_objectives(id) ON DELETE RESTRICT,
                CONSTRAINT fk_okr_reminder_kr FOREIGN KEY(key_result_id) REFERENCES okr_key_results(id) ON DELETE RESTRICT,
                CONSTRAINT fk_okr_reminder_recipient FOREIGN KEY(recipient_user_id) REFERENCES users(id) ON DELETE RESTRICT
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS okr_ai_analyses (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                objective_id BIGINT UNSIGNED NOT NULL,
                requested_by INT UNSIGNED NOT NULL,
                analysis_type VARCHAR(50) NOT NULL,
                context_summary_json LONGTEXT NULL,
                result_json LONGTEXT NULL,
                response_text LONGTEXT NULL,
                source VARCHAR(30) NOT NULL DEFAULT 'deterministic',
                status VARCHAR(20) NOT NULL DEFAULT 'success',
                error_message VARCHAR(500) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_okr_ai_objective(objective_id,created_at),
                INDEX idx_okr_ai_requester(requested_by,created_at),
                CONSTRAINT fk_okr_ai_objective FOREIGN KEY(objective_id) REFERENCES okr_objectives(id) ON DELETE RESTRICT,
                CONSTRAINT fk_okr_ai_requester FOREIGN KEY(requested_by) REFERENCES users(id) ON DELETE RESTRICT
            ){$engine}",
        ];
    }

    private static function seedPermissions(PDO $pdo): void
    {
        $stmt = $pdo->prepare('INSERT IGNORE INTO modules(module_key,module_title,sort_order,status,created_at) VALUES (?,?,?,"active",NOW())');
        foreach ([
            ['okr.view', 'مشاهده OKR', 719],
            ['okr.manage', 'ایجاد و مدیریت OKR', 720],
            ['okr.approve', 'تأیید یا رد OKR', 721],
            ['okr.checkin', 'ثبت Check-in نتایج کلیدی', 722],
            ['okr.cycles', 'مدیریت دوره‌های OKR', 723],
            ['okr.ai', 'تحلیل هوشمند OKR', 724],
        ] as $permission) {
            $stmt->execute($permission);
        }
        $token = bin2hex(random_bytes(24));
        $setting = $pdo->prepare('INSERT IGNORE INTO site_settings(setting_key,setting_value,setting_type,updated_at) VALUES ("okr_cron_token",?,"password",NOW())');
        $setting->execute([$token]);
    }
}
