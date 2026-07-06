<?php
declare(strict_types=1);

function lex_case_files_base_dir(): string
{
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'case_files';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function lex_case_files_slug(string $value): string
{
    $value = preg_replace('/[^A-Za-z0-9]+/', '_', trim($value)) ?? '';
    $value = trim($value, '_');
    return strtoupper($value !== '' ? $value : 'CASE');
}

function lex_case_files_folder_name(string $fullName, string $caseFileTitle, string $identifier = ''): string
{
    $parts = ['CF', date('YmdHis'), lex_case_files_slug($fullName), lex_case_files_slug($caseFileTitle)];
    if ($identifier !== '') {
        $parts[] = lex_case_files_slug($identifier);
    }
    return implode('_', $parts);
}

function lex_case_files_table_ensure(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    lex_db_retry(static function () use (&$done): void {
        lex_pdo()->exec(
            "CREATE TABLE IF NOT EXISTS `case_files` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `full_name` VARCHAR(150) NOT NULL,
              `case_identifier` VARCHAR(80) NOT NULL,
              `case_file_title` VARCHAR(180) NOT NULL,
              `description` TEXT NULL,
              `date_created` DATE NOT NULL,
              `client_user_id` INT UNSIGNED NOT NULL,
              `assigned_lawyer_user_id` INT UNSIGNED DEFAULT NULL,
              `status` ENUM('open','ongoing','closed') NOT NULL DEFAULT 'open',
              `folder_name` VARCHAR(180) NOT NULL,
              `attachments_json` LONGTEXT NULL,
              `created_by_user_id` INT UNSIGNED NOT NULL,
              `updated_by_user_id` INT UNSIGNED DEFAULT NULL,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_case_files_identifier` (`case_identifier`),
              UNIQUE KEY `uq_case_files_folder` (`folder_name`),
              KEY `idx_case_files_fullname` (`full_name`),
              KEY `idx_case_files_title` (`case_file_title`),
              KEY `idx_case_files_client` (`client_user_id`),
              KEY `idx_case_files_lawyer` (`assigned_lawyer_user_id`),
              CONSTRAINT `fk_case_files_client_user` FOREIGN KEY (`client_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
              CONSTRAINT `fk_case_files_assigned_lawyer_user` FOREIGN KEY (`assigned_lawyer_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
              CONSTRAINT `fk_case_files_created_by_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
              CONSTRAINT `fk_case_files_updated_by_user` FOREIGN KEY (`updated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $done = true;
    });
}

function lex_case_file_vault_table_ensure(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    lex_case_files_table_ensure();
    lex_db_retry(static function () use (&$done): void {
        $pdo = lex_pdo();
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `case_file_folders` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `case_file_id` INT UNSIGNED NOT NULL,
              `parent_folder_id` INT UNSIGNED DEFAULT NULL,
              `name` VARCHAR(150) NOT NULL,
              `slug` VARCHAR(170) NOT NULL,
              `created_by_user_id` INT UNSIGNED NOT NULL,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_case_file_folder_slug` (`case_file_id`, `slug`),
              KEY `idx_case_file_folders_case` (`case_file_id`),
              KEY `idx_case_file_folders_parent` (`parent_folder_id`),
              CONSTRAINT `fk_case_file_folders_case` FOREIGN KEY (`case_file_id`) REFERENCES `case_files` (`id`) ON DELETE CASCADE,
              CONSTRAINT `fk_case_file_folders_parent` FOREIGN KEY (`parent_folder_id`) REFERENCES `case_file_folders` (`id`) ON DELETE CASCADE,
              CONSTRAINT `fk_case_file_folders_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `case_file_documents` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `case_file_id` INT UNSIGNED NOT NULL,
              `folder_id` INT UNSIGNED NOT NULL,
              `original_name` VARCHAR(255) NOT NULL,
              `stored_name` VARCHAR(255) NOT NULL,
              `mime_type` VARCHAR(120) DEFAULT NULL,
              `file_size` INT UNSIGNED DEFAULT NULL,
              `file_hash` CHAR(64) DEFAULT NULL,
              `upload_status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
              `uploaded_by_user_id` INT UNSIGNED NOT NULL,
              `approved_by_user_id` INT UNSIGNED DEFAULT NULL,
              `approved_at` DATETIME DEFAULT NULL,
              `rejection_reason` TEXT DEFAULT NULL,
              `is_confidential` TINYINT(1) NOT NULL DEFAULT 0,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_case_file_documents_stored` (`case_file_id`, `stored_name`),
              KEY `idx_case_file_documents_case` (`case_file_id`, `upload_status`),
              KEY `idx_case_file_documents_folder` (`folder_id`),
              KEY `idx_case_file_documents_uploaded_by` (`uploaded_by_user_id`),
              CONSTRAINT `fk_case_file_documents_case` FOREIGN KEY (`case_file_id`) REFERENCES `case_files` (`id`) ON DELETE CASCADE,
              CONSTRAINT `fk_case_file_documents_folder` FOREIGN KEY (`folder_id`) REFERENCES `case_file_folders` (`id`) ON DELETE CASCADE,
              CONSTRAINT `fk_case_file_documents_uploaded_by` FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
              CONSTRAINT `fk_case_file_documents_approved_by` FOREIGN KEY (`approved_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $done = true;
    });
}

function lex_case_files_folder_path(string $folderName): string
{
    return lex_case_files_base_dir() . DIRECTORY_SEPARATOR . $folderName;
}

function lex_case_files_metadata_path(string $folderName): string
{
    return lex_case_files_folder_path($folderName) . DIRECTORY_SEPARATOR . 'metadata.json';
}

function lex_case_files_ensure_folders(string $folderName): void
{
    $root = lex_case_files_folder_path($folderName);
    $subfolders = ['DOCUMENTS', 'PHOTOS', 'EVIDENCE', 'COURT_FILINGS', 'CORRESPONDENCE', 'CLIENT_UPLOADS'];
    if (!is_dir($root)) {
        @mkdir($root, 0775, true);
    }
    foreach ($subfolders as $subfolder) {
        $path = $root . DIRECTORY_SEPARATOR . $subfolder;
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }
    }
}

function lex_case_file_vault_default_folders(): array
{
    return [
        'Documents',
        'Evidence',
        'Court Filings',
        'Photos',
        'Correspondence',
        'Client Uploads',
    ];
}

function lex_case_file_vault_slug(string $value): string
{
    $slug = preg_replace('/[^A-Za-z0-9]+/', '_', trim($value)) ?? '';
    $slug = trim($slug, '_');
    return strtoupper($slug !== '' ? $slug : 'FOLDER');
}

function lex_case_file_vault_folder_dir(array $caseFile, array $folder): string
{
    $slug = lex_case_file_vault_slug((string) ($folder['slug'] ?? $folder['name'] ?? 'DOCUMENTS'));
    return lex_case_files_folder_path((string) $caseFile['folder_name']) . DIRECTORY_SEPARATOR . $slug;
}

function lex_case_file_vault_ensure_defaults(int $caseFileId, int $createdByUserId = 1): void
{
    lex_case_file_vault_table_ensure();
    $pdo = lex_pdo();
    $stmt = $pdo->prepare('SELECT folder_name FROM case_files WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $caseFileId]);
    $folderName = (string) ($stmt->fetchColumn() ?: '');
    if ($folderName === '') {
        return;
    }
    lex_case_files_ensure_folders($folderName);
    $insert = $pdo->prepare(
        'INSERT IGNORE INTO case_file_folders (case_file_id, parent_folder_id, name, slug, created_by_user_id)
         VALUES (:case_file_id, NULL, :name, :slug, :created_by_user_id)'
    );
    $exists = $pdo->prepare('SELECT id FROM case_file_folders WHERE case_file_id = :case_file_id AND slug = :slug LIMIT 1');
    foreach (lex_case_file_vault_default_folders() as $name) {
        $slug = lex_case_file_vault_slug($name);
        $path = lex_case_files_folder_path($folderName) . DIRECTORY_SEPARATOR . $slug;
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }
        $exists->execute(['case_file_id' => $caseFileId, 'slug' => $slug]);
        if ($exists->fetchColumn()) {
            continue;
        }
        $insert->execute([
            'case_file_id' => $caseFileId,
            'name' => $name,
            'slug' => $slug,
            'created_by_user_id' => $createdByUserId,
        ]);
    }
}

function lex_case_file_vault_fetch(int $caseFileId, array $viewer): array
{
    lex_case_file_vault_ensure_defaults($caseFileId, (int) ($viewer['id'] ?? 1));
    $pdo = lex_pdo();
    $folders = lex_recent(
        'SELECT f.*,
                (SELECT COUNT(*) FROM case_file_documents ad WHERE ad.folder_id = f.id AND ad.upload_status = "approved") AS approved_count,
                (SELECT COUNT(*) FROM case_file_documents pd WHERE pd.folder_id = f.id AND pd.upload_status = "pending") AS pending_count
         FROM case_file_folders f
         WHERE f.case_file_id = :case_file_id
         ORDER BY f.parent_folder_id IS NOT NULL, f.name ASC',
        ['case_file_id' => $caseFileId]
    );
    $statusClause = ((string) ($viewer['role'] ?? '') === 'lawyer') ? '1 = 1' : 'd.upload_status = "approved"';
    $documents = lex_recent(
        'SELECT d.*, f.name AS folder_name, f.slug AS folder_slug, u.full_name AS uploaded_by_name, au.full_name AS approved_by_name
         FROM case_file_documents d
         JOIN case_file_folders f ON f.id = d.folder_id
         JOIN users u ON u.id = d.uploaded_by_user_id
         LEFT JOIN users au ON au.id = d.approved_by_user_id
         WHERE d.case_file_id = :case_file_id AND ' . $statusClause . '
         ORDER BY d.upload_status = "pending" DESC, d.created_at DESC',
        ['case_file_id' => $caseFileId]
    );
    return ['folders' => $folders, 'documents' => $documents];
}

function lex_case_file_vault_access(array $caseFile, array $user): string
{
    $role = (string) ($user['role'] ?? '');
    $userId = (int) ($user['id'] ?? 0);
    if ($role === 'lawyer' && ($userId === (int) ($caseFile['created_by_user_id'] ?? 0) || $userId === (int) ($caseFile['assigned_lawyer_user_id'] ?? 0))) {
        return 'manage';
    }
    if ($role === 'client' && $userId === (int) ($caseFile['client_user_id'] ?? 0)) {
        return 'client';
    }
    return 'none';
}

function lex_case_file_vault_store_document(array $caseFile, int $folderId, array $file, array $user, string $status): array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Select a document to upload.');
    }
    if ($error !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
        throw new RuntimeException('Unable to upload document.');
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        throw new RuntimeException('Document is empty.');
    }
    if ($size > 25 * 1024 * 1024) {
        throw new RuntimeException('Document is too large. The limit is 25 MB.');
    }
    $folderStmt = lex_pdo()->prepare('SELECT * FROM case_file_folders WHERE id = :id AND case_file_id = :case_file_id LIMIT 1');
    $folderStmt->execute(['id' => $folderId, 'case_file_id' => (int) $caseFile['id']]);
    $folder = $folderStmt->fetch();
    if (!$folder) {
        throw new RuntimeException('Select a valid vault folder.');
    }
    $originalName = trim((string) ($file['name'] ?? 'document'));
    $originalName = $originalName !== '' ? $originalName : 'document';
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $blockedExtensions = ['php', 'phtml', 'phar', 'cgi', 'pl', 'asp', 'aspx', 'jsp', 'sh', 'bat', 'cmd', 'exe', 'dll', 'com', 'scr'];
    if ($extension !== '' && in_array($extension, $blockedExtensions, true)) {
        throw new RuntimeException('Unsupported document type.');
    }
    $storedName = bin2hex(random_bytes(16)) . ($extension !== '' ? '.' . $extension : '');
    $targetDir = lex_case_file_vault_folder_dir($caseFile, $folder);
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0775, true);
    }
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $storedName;
    if (!move_uploaded_file((string) $file['tmp_name'], $targetPath)) {
        throw new RuntimeException('Unable to save document.');
    }
    $mime = 'application/octet-stream';
    if (function_exists('mime_content_type')) {
        $detected = @mime_content_type($targetPath);
        if (is_string($detected) && $detected !== '') {
            $mime = $detected;
        }
    } elseif (!empty($file['type'])) {
        $mime = (string) $file['type'];
    }
    $hash = hash_file('sha256', $targetPath) ?: null;
    $approvedBy = $status === 'approved' ? (int) $user['id'] : null;
    $approvedAt = $status === 'approved' ? date('Y-m-d H:i:s') : null;
    lex_pdo()->prepare(
        'INSERT INTO case_file_documents
            (case_file_id, folder_id, original_name, stored_name, mime_type, file_size, file_hash, upload_status, uploaded_by_user_id, approved_by_user_id, approved_at)
         VALUES
            (:case_file_id, :folder_id, :original_name, :stored_name, :mime_type, :file_size, :file_hash, :upload_status, :uploaded_by_user_id, :approved_by_user_id, :approved_at)'
    )->execute([
        'case_file_id' => (int) $caseFile['id'],
        'folder_id' => $folderId,
        'original_name' => $originalName,
        'stored_name' => $storedName,
        'mime_type' => $mime,
        'file_size' => $size,
        'file_hash' => $hash,
        'upload_status' => $status,
        'uploaded_by_user_id' => (int) $user['id'],
        'approved_by_user_id' => $approvedBy,
        'approved_at' => $approvedAt,
    ]);
    return [
        'id' => (int) lex_pdo()->lastInsertId(),
        'original_name' => $originalName,
        'stored_name' => $storedName,
        'path' => $targetPath,
        'mime_type' => $mime,
        'size' => $size,
    ];
}

function lex_case_files_write_metadata(array $record): void
{
    if (empty($record['folder_name'])) {
        return;
    }
    lex_case_files_ensure_folders((string) $record['folder_name']);
    $payload = [
        'FULLNAME' => $record['full_name'] ?? '',
        'CASE_IDENTIFIER' => $record['case_identifier'] ?? '',
        'CASE_FILE' => $record['case_file_title'] ?? '',
        'DESCRIPTION' => $record['description'] ?? '',
        'DATE_CREATED' => $record['date_created'] ?? '',
        'ASSIGNED_LAWYER' => [
            'id' => (int) ($record['assigned_lawyer_user_id'] ?? 0),
            'name' => $record['assigned_lawyer_name'] ?? '',
        ],
        'STATUS' => strtoupper((string) ($record['status'] ?? 'open')),
        'CLIENT_USER_ID' => (int) ($record['client_user_id'] ?? 0),
        'ATTACHMENTS' => json_decode((string) ($record['attachments_json'] ?? '[]'), true) ?: [],
        'UPDATED_AT' => $record['updated_at'] ?? date('c'),
    ];
    file_put_contents(lex_case_files_metadata_path((string) $record['folder_name']), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function lex_case_files_recursive_delete(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }
    @rmdir($path);
}
