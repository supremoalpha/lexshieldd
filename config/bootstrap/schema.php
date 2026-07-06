<?php
declare(strict_types=1);

function lex_users_table_ensure(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    lex_db_retry(static function () use (&$done): void {
        $pdo = lex_pdo();
        $columns = [];
        $stmt = $pdo->query('SHOW COLUMNS FROM users');
        if ($stmt) {
            $columns = array_map(static fn ($row) => (string) $row['Field'], $stmt->fetchAll());
        }
        if (!in_array('avatar_stored_name', $columns, true)) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `avatar_stored_name` VARCHAR(255) DEFAULT NULL AFTER `last_login`");
        }
        $done = true;
    });
}

function lex_lawyers_table_ensure(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    lex_db_retry(static function () use (&$done): void {
        $pdo = lex_pdo();
        $columns = [];
        $stmt = $pdo->query('SHOW COLUMNS FROM lawyers');
        $columnRows = [];
        if ($stmt) {
            $columnRows = $stmt->fetchAll();
            $columns = array_map(static fn ($row) => (string) $row['Field'], $columnRows);
        }
        if (!in_array('background', $columns, true)) {
            $pdo->exec("ALTER TABLE `lawyers` ADD COLUMN `background` TEXT DEFAULT NULL AFTER `bio`");
        }
        foreach ($columnRows as $row) {
            if (($row['Field'] ?? '') === 'status' && !str_contains((string) ($row['Type'] ?? ''), "'busy'")) {
                $pdo->exec("ALTER TABLE `lawyers` MODIFY COLUMN `status` ENUM('active','busy','suspended') NOT NULL DEFAULT 'active'");
                break;
            }
        }
        $done = true;
    });
}

function lex_lawyer_reviews_table_ensure(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    lex_db_retry(static function () use (&$done): void {
        $pdo = lex_pdo();
        $stmt = $pdo->query("SHOW TABLES LIKE 'lawyer_reviews'");
        $exists = $stmt ? (bool) $stmt->fetchColumn() : false;
        if ($exists) {
            $done = true;
            return;
        }

        $pdo->exec(
            "CREATE TABLE `lawyer_reviews` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `lawyer_id` INT UNSIGNED NOT NULL,
                `client_id` INT UNSIGNED NOT NULL,
                `rating` TINYINT UNSIGNED NOT NULL,
                `comment` TEXT DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_lawyer_reviews_pair` (`lawyer_id`, `client_id`),
                KEY `idx_lawyer_reviews_lawyer` (`lawyer_id`),
                KEY `idx_lawyer_reviews_client` (`client_id`),
                CONSTRAINT `fk_lawyer_reviews_lawyer` FOREIGN KEY (`lawyer_id`) REFERENCES `lawyers` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_lawyer_reviews_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $done = true;
    });
}


function lex_manual_payments_table_ensure(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    lex_db_retry(static function () use (&$done): void {
        $pdo = lex_pdo();
        $stmt = $pdo->query("SHOW TABLES LIKE 'manual_payments'");
        $exists = $stmt ? (bool) $stmt->fetchColumn() : false;
        if (!$exists) {
            $pdo->exec(
                "CREATE TABLE `manual_payments` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `client_id` INT UNSIGNED NOT NULL,
                    `payment_channel` ENUM('gcash') NOT NULL DEFAULT 'gcash',
                    `payment_for` VARCHAR(180) NOT NULL,
                    `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    `currency` VARCHAR(10) NOT NULL DEFAULT 'PHP',
                    `payer_name` VARCHAR(150) NOT NULL,
                    `payer_contact` VARCHAR(40) DEFAULT NULL,
                    `reference_number` VARCHAR(120) DEFAULT NULL,
                    `notes` TEXT DEFAULT NULL,
                    `proof_original_name` VARCHAR(255) NOT NULL,
                    `proof_stored_name` VARCHAR(255) NOT NULL,
                    `proof_mime_type` VARCHAR(120) DEFAULT NULL,
                    `proof_size` INT UNSIGNED DEFAULT NULL,
                    `status` ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
                    `admin_notes` TEXT DEFAULT NULL,
                    `reviewed_by_user_id` INT UNSIGNED DEFAULT NULL,
                    `reviewed_at` DATETIME DEFAULT NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_manual_payments_client` (`client_id`, `status`, `created_at`),
                    KEY `idx_manual_payments_status` (`status`, `created_at`),
                    KEY `idx_manual_payments_reviewer` (`reviewed_by_user_id`),
                    CONSTRAINT `fk_manual_payments_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `fk_manual_payments_reviewer` FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
        $done = true;
    });
}

function lex_appointments_table_ensure(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    lex_db_retry(static function () use (&$done): void {
        $pdo = lex_pdo();
        $columns = [];
        $stmt = $pdo->query('SHOW COLUMNS FROM appointments');
        if ($stmt) {
            $columns = array_map(static fn ($row) => (string) $row['Field'], $stmt->fetchAll());
        }

        if (!in_array('appointment_type', $columns, true)) {
            $pdo->exec("ALTER TABLE `appointments` ADD COLUMN `appointment_type` VARCHAR(120) NOT NULL DEFAULT 'Client Intake Consultation' AFTER `scheduled_at`");
        }

        $done = true;
    });
}
