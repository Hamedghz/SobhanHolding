<?php

class LetterModule
{
    public const STATUSES = [
        'draft' => 'پیش‌نویس',
        'pending_signature' => 'در انتظار امضا',
        'signed' => 'امضا شده',
        'issued' => 'صادر شده',
        'archived' => 'بایگانی شده',
        'cancelled' => 'لغو شده',
    ];

    public const IMPORTANCE = ['normal' => 'عادی', 'important' => 'مهم', 'urgent' => 'فوری'];
    public const CONFIDENTIALITY = ['normal' => 'عادی', 'confidential' => 'محرمانه', 'secret' => 'خیلی محرمانه'];
    public const LOG_ACTIONS = [
        'created' => 'ایجاد نامه', 'updated' => 'ویرایش پیش‌نویس', 'copied' => 'کپی نامه',
        'request_signature' => 'ارسال برای امضا', 'sign' => 'امضای نامه', 'issue' => 'صدور نامه',
        'archive' => 'بایگانی نامه', 'cancel' => 'لغو نامه', 'attachment_added' => 'افزودن پیوست',
    ];

    public static function repair(PDO $pdo): void
    {
        $statements = [
            "CREATE TABLE IF NOT EXISTS letter_letterheads (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(190) NOT NULL,
                company_name VARCHAR(190) NULL,
                contact_info TEXT NULL,
                logo_path VARCHAR(255) NULL,
                background_path VARCHAR(255) NULL,
                watermark_text VARCHAR(190) NULL,
                header_html MEDIUMTEXT NULL,
                footer_html MEDIUMTEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_letterheads_active(is_active),
                CONSTRAINT fk_letterheads_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS letter_signatures (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                signer_name VARCHAR(190) NOT NULL,
                signer_title VARCHAR(190) NULL,
                signature_path VARCHAR(255) NULL,
                stamp_path VARCHAR(255) NULL,
                user_id INT UNSIGNED NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_signatures_active(is_active),
                CONSTRAINT fk_signatures_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
                CONSTRAINT fk_signatures_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS letter_templates (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(190) NOT NULL,
                default_subject VARCHAR(255) NULL,
                default_body MEDIUMTEXT NULL,
                letterhead_id INT UNSIGNED NULL,
                signature_id INT UNSIGNED NULL,
                paper_size ENUM('A4','A5') NOT NULL DEFAULT 'A4',
                orientation ENUM('portrait','landscape') NOT NULL DEFAULT 'portrait',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_templates_active(is_active),
                CONSTRAINT fk_templates_letterhead FOREIGN KEY(letterhead_id) REFERENCES letter_letterheads(id) ON DELETE SET NULL,
                CONSTRAINT fk_templates_signature FOREIGN KEY(signature_id) REFERENCES letter_signatures(id) ON DELETE SET NULL,
                CONSTRAINT fk_templates_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS organizational_letters (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                letter_number VARCHAR(100) NULL,
                letter_date DATE NOT NULL,
                subject VARCHAR(255) NOT NULL,
                recipient_name VARCHAR(190) NOT NULL,
                recipient_title VARCHAR(190) NULL,
                recipient_organization VARCHAR(190) NULL,
                sender_unit VARCHAR(190) NULL,
                template_id INT UNSIGNED NULL,
                letterhead_id INT UNSIGNED NULL,
                signature_id INT UNSIGNED NULL,
                body_html MEDIUMTEXT NOT NULL,
                final_html LONGTEXT NULL,
                paper_size ENUM('A4','A5') NOT NULL DEFAULT 'A4',
                orientation ENUM('portrait','landscape') NOT NULL DEFAULT 'portrait',
                importance ENUM('normal','important','urgent') NOT NULL DEFAULT 'normal',
                confidentiality ENUM('normal','confidential','secret') NOT NULL DEFAULT 'normal',
                status ENUM('draft','pending_signature','signed','issued','archived','cancelled') NOT NULL DEFAULT 'draft',
                created_by INT UNSIGNED NOT NULL,
                approved_by INT UNSIGNED NULL,
                issued_at DATETIME NULL,
                archived_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_letter_number(letter_number),
                INDEX idx_letters_date(letter_date),
                INDEX idx_letters_status(status),
                INDEX idx_letters_confidentiality(confidentiality),
                INDEX idx_letters_creator(created_by),
                CONSTRAINT fk_letters_template FOREIGN KEY(template_id) REFERENCES letter_templates(id) ON DELETE SET NULL,
                CONSTRAINT fk_letters_letterhead FOREIGN KEY(letterhead_id) REFERENCES letter_letterheads(id) ON DELETE SET NULL,
                CONSTRAINT fk_letters_signature FOREIGN KEY(signature_id) REFERENCES letter_signatures(id) ON DELETE SET NULL,
                CONSTRAINT fk_letters_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT,
                CONSTRAINT fk_letters_approver FOREIGN KEY(approved_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS organizational_letter_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                letter_id BIGINT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NULL,
                action VARCHAR(60) NOT NULL,
                from_status VARCHAR(40) NULL,
                to_status VARCHAR(40) NULL,
                description VARCHAR(500) NULL,
                ip_address VARCHAR(45) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_letter_logs_letter(letter_id,created_at),
                CONSTRAINT fk_letter_logs_letter FOREIGN KEY(letter_id) REFERENCES organizational_letters(id) ON DELETE CASCADE,
                CONSTRAINT fk_letter_logs_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS organizational_letter_attachments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                letter_id BIGINT UNSIGNED NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                stored_name VARCHAR(255) NOT NULL,
                file_path VARCHAR(255) NOT NULL,
                mime_type VARCHAR(120) NOT NULL,
                file_size INT UNSIGNED NOT NULL,
                uploaded_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_letter_attachments_letter(letter_id),
                CONSTRAINT fk_letter_attachments_letter FOREIGN KEY(letter_id) REFERENCES organizational_letters(id) ON DELETE CASCADE,
                CONSTRAINT fk_letter_attachments_user FOREIGN KEY(uploaded_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
        foreach ($statements as $statement) $pdo->exec($statement);

        $modules = [
            ['organizational_letters', 'نامه‌های سازمانی'],
            ['letters.sign', 'امضای نامه سازمانی'],
            ['letters.issue', 'صدور و شماره‌گذاری نامه'],
            ['letters.archive', 'بایگانی نامه'],
            ['letters.confidential', 'مشاهده نامه محرمانه'],
            ['letters.settings', 'تنظیمات و قالب‌های نامه'],
        ];
        $stmt = $pdo->prepare("INSERT INTO modules(module_key,module_title,sort_order,status,created_at) VALUES(?,?,70,'active',NOW()) ON DUPLICATE KEY UPDATE module_title=VALUES(module_title)");
        foreach ($modules as $module) $stmt->execute($module);

        $defaults = [
            'letter_auto_numbering' => '1', 'letter_number_prefix' => 'SH-', 'letter_number_next' => '1',
            'letter_default_font' => 'Vazirmatn, Tahoma, sans-serif', 'letter_default_font_size' => '14',
            'letter_default_paper_size' => 'A4', 'letter_default_orientation' => 'portrait',
            'letter_margin_top' => '18', 'letter_margin_right' => '18', 'letter_margin_bottom' => '18', 'letter_margin_left' => '18',
        ];
        $settingStmt = $pdo->prepare("INSERT INTO site_settings(setting_key,setting_value,setting_type,updated_at) VALUES(?,?,'text',NOW()) ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key)");
        foreach ($defaults as $key => $value) $settingStmt->execute([$key, $value]);
    }

    public static function isSecretariat(?array $user = null): bool
    {
        $user ??= Auth::user() ?: [];
        $haystack = implode(' ', [(string)($user['role_key'] ?? ''), (string)($user['department'] ?? '')]);
        return (bool)preg_match('/secretariat|دبیرخانه/ui', $haystack);
    }

    public static function can(string $action): bool
    {
        $user = Auth::user();
        if (!$user) return false;
        if (Auth::isAdmin()) return true;
        return match ($action) {
            'view' => self::isSecretariat($user) || Auth::can('organizational_letters', 'view'),
            'create' => Auth::can('organizational_letters', 'create'),
            'edit' => Auth::can('organizational_letters', 'edit'),
            'sign' => Auth::can('letters.sign', 'edit'),
            'issue' => self::isSecretariat($user) || Auth::can('letters.issue', 'edit'),
            'archive' => self::isSecretariat($user) || Auth::can('letters.archive', 'edit'),
            'confidential' => Auth::can('letters.confidential', 'view'),
            'settings' => Auth::can('letters.settings', 'edit'),
            default => false,
        };
    }

    public static function requireCapability(string $action): void
    {
        Auth::requireLogin();
        if (!self::can($action)) {
            http_response_code(403);
            $pageTitle = 'دسترسی غیرمجاز';
            require __DIR__ . '/../views/partials/admin-header.php';
            echo '<section class="card"><h2>دسترسی کافی ندارید</h2><p class="muted">برای انجام این عملیات با مدیر سامانه تماس بگیرید.</p></section>';
            require __DIR__ . '/../views/partials/admin-footer.php';
            exit;
        }
    }

    public static function canViewLetter(array $letter): bool
    {
        if (!self::can('view')) return false;
        if (($letter['confidentiality'] ?? 'normal') === 'normal') return true;
        $userId = (int)(Auth::user()['id'] ?? 0);
        return Auth::isAdmin() || self::can('confidential') || $userId === (int)($letter['created_by'] ?? 0) || $userId === (int)($letter['approved_by'] ?? 0) || $userId === (int)($letter['signer_user_id'] ?? 0);
    }

    public static function sanitizeHtml(?string $html): string
    {
        $html = trim((string)$html);
        if ($html === '') return '';
        if (strlen($html) > 4000000) throw new InvalidArgumentException('حجم متن نامه بیش از حد مجاز است.');
        $html = preg_replace('#<(script|style|iframe|object|embed|form|input|button|textarea|select|meta|link)[^>]*>.*?</\1>#is', '', $html) ?? '';
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $html) ?? '';
        $html = preg_replace('/\s(href|src)\s*=\s*(["\'])\s*(javascript:|vbscript:)[^"\']*\2/iu', '', $html) ?? '';
        $allowed = '<p><br><div><span><strong><b><em><i><u><s><h1><h2><h3><h4><blockquote><ul><ol><li><table><thead><tbody><tfoot><tr><th><td><img><hr><sub><sup>';
        $html = strip_tags($html, $allowed);
        if (class_exists('DOMDocument')) {
            $doc = new DOMDocument('1.0', 'UTF-8');
            libxml_use_internal_errors(true);
            $doc->loadHTML('<?xml encoding="utf-8" ?><div id="letter-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();
            $allowedAttributes = ['style', 'class', 'dir', 'align', 'colspan', 'rowspan', 'width', 'height', 'src', 'alt'];
            foreach ($doc->getElementsByTagName('*') as $node) {
                if (!$node->hasAttributes()) continue;
                foreach (iterator_to_array($node->attributes) as $attribute) {
                    $name = strtolower($attribute->name);
                    if (!in_array($name, $allowedAttributes, true)) $node->removeAttribute($attribute->name);
                }
                if ($node->hasAttribute('src')) {
                    $src = trim($node->getAttribute('src'));
                    if (!preg_match('#^(https?://|/|data:image/(png|jpeg|webp);base64,)#i', $src)) $node->removeAttribute('src');
                }
                if ($node->hasAttribute('style')) {
                    $style = preg_replace('/(?:expression|url\s*\(|position\s*:|behavior\s*:|@import)/iu', '', $node->getAttribute('style')) ?? '';
                    $node->setAttribute('style', $style);
                }
            }
            $root = $doc->getElementById('letter-root');
            if ($root) {
                $html = '';
                foreach ($root->childNodes as $child) $html .= $doc->saveHTML($child);
            }
        }
        return trim($html);
    }

    public static function variables(array $letter): array
    {
        $user = Auth::user() ?: [];
        return [
            '{letter_number}' => (string)($letter['letter_number'] ?: '—'),
            '{letter_date}' => function_exists('format_jalali_date') ? format_jalali_date((string)$letter['letter_date']) : (string)$letter['letter_date'],
            '{subject}' => (string)$letter['subject'], '{recipient_name}' => (string)$letter['recipient_name'],
            '{recipient_title}' => (string)($letter['recipient_title'] ?? ''), '{recipient_organization}' => (string)($letter['recipient_organization'] ?? ''),
            '{sender_unit}' => (string)($letter['sender_unit'] ?? ''), '{signer_name}' => (string)($letter['signer_name'] ?? ''),
            '{signer_title}' => (string)($letter['signer_title'] ?? ''), '{company_name}' => (string)($letter['company_name'] ?? setting('company_name', 'شرکت پخش سبحان')),
            '{current_user_name}' => (string)($letter['creator_name'] ?? $user['name'] ?? ''),
        ];
    }

    public static function renderBody(array $letter): string
    {
        return strtr((string)$letter['body_html'], array_map('e', self::variables($letter)));
    }

    public static function nextNumber(PDO $pdo): string
    {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT setting_key,setting_value FROM site_settings WHERE setting_key IN ('letter_number_prefix','letter_number_next') FOR UPDATE");
            $stmt->execute();
            $settings = array_column($stmt->fetchAll(), 'setting_value', 'setting_key');
            $next = max(1, (int)($settings['letter_number_next'] ?? 1));
            $prefix = (string)($settings['letter_number_prefix'] ?? 'SH-');
            do {
                $number = $prefix . str_pad((string)$next, 5, '0', STR_PAD_LEFT);
                $check = $pdo->prepare('SELECT id FROM organizational_letters WHERE letter_number=? LIMIT 1');
                $check->execute([$number]);
                if (!$check->fetch()) break;
                $next++;
            } while ($next < 1000000000);
            $update = $pdo->prepare("UPDATE site_settings SET setting_value=?,updated_at=NOW() WHERE setting_key='letter_number_next'");
            $update->execute([(string)($next + 1)]);
            $pdo->commit();
            return $number;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function log(int $letterId, string $action, ?string $from = null, ?string $to = null, ?string $description = null): void
    {
        Database::execute('INSERT INTO organizational_letter_logs(letter_id,user_id,action,from_status,to_status,description,ip_address,created_at) VALUES(?,?,?,?,?,?,?,NOW())', [
            $letterId, (int)(Auth::user()['id'] ?? 0) ?: null, $action, $from, $to, $description, substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        ]);
        Auth::log((int)(Auth::user()['id'] ?? 0) ?: null, $action, 'organizational_letters', $letterId);
    }

    public static function load(int $id): ?array
    {
        return Database::fetch('SELECT l.*,t.title template_title,h.title letterhead_title,h.company_name,h.contact_info,h.logo_path,h.background_path,h.watermark_text,h.header_html,h.footer_html,s.signer_name,s.signer_title,s.signature_path,s.stamp_path,s.user_id signer_user_id,u.name creator_name,a.name approver_name FROM organizational_letters l LEFT JOIN letter_templates t ON t.id=l.template_id LEFT JOIN letter_letterheads h ON h.id=l.letterhead_id LEFT JOIN letter_signatures s ON s.id=l.signature_id LEFT JOIN users u ON u.id=l.created_by LEFT JOIN users a ON a.id=l.approved_by WHERE l.id=? LIMIT 1', [$id]);
    }
}
