<?php
declare(strict_types=1);

function lex_storage_dir(): string
{
    $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'documents';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function lex_profile_avatars_dir(): string
{
    $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'avatars';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function lex_profile_avatar_url(?string $storedName): string
{
    $storedName = basename(trim((string) $storedName));
    if ($storedName === '') {
        return '';
    }
    $avatarPath = lex_profile_avatars_dir() . DIRECTORY_SEPARATOR . $storedName;
    if (!is_file($avatarPath)) {
        $legacyPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'avatars' . DIRECTORY_SEPARATOR . $storedName;
        if (is_file($legacyPath)) {
            @copy($legacyPath, $avatarPath);
        }
    }
    if (!is_file($avatarPath)) {
        return '';
    }
    return lex_app_url('storage/avatars/' . rawurlencode($storedName));
}

function lex_profile_avatar_remove(?string $storedName): void
{
    $storedName = trim((string) $storedName);
    if ($storedName === '') {
        return;
    }
    $path = lex_profile_avatars_dir() . DIRECTORY_SEPARATOR . $storedName;
    if (is_file($path)) {
        @unlink($path);
    }
}


function lex_messages_base_dir(): string
{
    $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'messages';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function lex_messages_attachment_path(string $storedName): string
{
    return lex_messages_base_dir() . DIRECTORY_SEPARATOR . $storedName;
}

function lex_human_file_size(int $bytes): string
{
    if ($bytes <= 0) {
        return '0 B';
    }
    $units = ['B', 'KB', 'MB', 'GB'];
    $size = (float) $bytes;
    $unit = 0;
    while ($size >= 1024 && $unit < count($units) - 1) {
        $size /= 1024;
        $unit++;
    }
    return $unit === 0 ? sprintf('%d %s', (int) $size, $units[$unit]) : sprintf('%.1f %s', $size, $units[$unit]);
}

function lex_store_profile_avatar(array $file): ?array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
        throw new RuntimeException('Unable to upload the avatar.');
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        throw new RuntimeException('Avatar image is empty.');
    }
    if ($size > 5 * 1024 * 1024) {
        throw new RuntimeException('Avatar image is too large. The limit is 5 MB.');
    }
    $tmpPath = (string) $file['tmp_name'];
    $imageType = function_exists('exif_imagetype') ? @exif_imagetype($tmpPath) : false;
    $allowed = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];
    if (!isset($allowed[$imageType])) {
        throw new RuntimeException('Avatar must be a JPG, PNG, GIF, or WEBP image.');
    }
    $extension = $allowed[$imageType];
    $storedName = 'avatar_' . bin2hex(random_bytes(16)) . '.' . $extension;
    $targetPath = lex_profile_avatars_dir() . DIRECTORY_SEPARATOR . $storedName;
    if (!move_uploaded_file($tmpPath, $targetPath)) {
        throw new RuntimeException('Unable to save the avatar image.');
    }
    return [
        'stored_name' => $storedName,
        'path' => $targetPath,
        'mime_type' => image_type_to_mime_type($imageType),
        'size' => $size,
        'original_name' => (string) ($file['name'] ?? 'avatar'),
    ];
}

function lex_store_message_attachment(array $file): ?array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
        throw new RuntimeException('Unable to upload attachment.');
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        throw new RuntimeException('Attachment is empty.');
    }
    if ($size > 25 * 1024 * 1024) {
        throw new RuntimeException('Attachment is too large. The limit is 25 MB.');
    }
    $originalName = trim((string) ($file['name'] ?? 'attachment'));
    $originalName = $originalName !== '' ? $originalName : 'attachment';
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $blockedExtensions = ['php', 'phtml', 'phar', 'cgi', 'pl', 'asp', 'aspx', 'jsp', 'sh', 'bat', 'cmd', 'exe', 'dll'];
    if ($extension !== '' && in_array($extension, $blockedExtensions, true)) {
        throw new RuntimeException('Unsupported attachment type.');
    }
    $storedName = bin2hex(random_bytes(16)) . ($extension !== '' ? '.' . $extension : '');
    $targetPath = lex_messages_attachment_path($storedName);
    if (!move_uploaded_file((string) $file['tmp_name'], $targetPath)) {
        throw new RuntimeException('Unable to save attachment.');
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
    return [
        'original_name' => $originalName,
        'stored_name' => $storedName,
        'mime_type' => $mime,
        'size' => $size,
        'path' => $targetPath,
    ];
}

function lex_payment_proofs_dir(): string
{
    $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'payment_proofs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function lex_payment_qr_dir(): string
{
    $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'payment_qr';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function lex_payment_proof_path(string $storedName): string
{
    return lex_payment_proofs_dir() . DIRECTORY_SEPARATOR . basename($storedName);
}

function lex_payment_qr_path(string $storedName): string
{
    return lex_payment_qr_dir() . DIRECTORY_SEPARATOR . basename($storedName);
}

function lex_store_payment_proof(array $file): array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Upload your payment screenshot or PDF proof.');
    }
    if ($error !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
        throw new RuntimeException('Unable to upload payment proof.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        throw new RuntimeException('Payment proof is empty.');
    }
    if ($size > 8 * 1024 * 1024) {
        throw new RuntimeException('Payment proof is too large. The limit is 8 MB.');
    }

    $tmpPath = (string) $file['tmp_name'];
    $originalName = trim((string) ($file['name'] ?? 'payment-proof'));
    $originalName = $originalName !== '' ? $originalName : 'payment-proof';

    $mime = '';
    $extension = '';
    $imageType = function_exists('exif_imagetype') ? @exif_imagetype($tmpPath) : false;
    $allowedImages = [
        IMAGETYPE_JPEG => ['jpg', 'image/jpeg'],
        IMAGETYPE_PNG => ['png', 'image/png'],
        IMAGETYPE_GIF => ['gif', 'image/gif'],
        IMAGETYPE_WEBP => ['webp', 'image/webp'],
    ];

    if (isset($allowedImages[$imageType])) {
        [$extension, $mime] = $allowedImages[$imageType];
    } else {
        $detectedMime = function_exists('mime_content_type') ? (string) @mime_content_type($tmpPath) : '';
        if ($detectedMime === 'application/pdf') {
            $extension = 'pdf';
            $mime = 'application/pdf';
        }
    }

    if ($extension === '' || $mime === '') {
        throw new RuntimeException('Payment proof must be a JPG, PNG, GIF, WEBP, or PDF file.');
    }

    $storedName = 'proof_' . bin2hex(random_bytes(16)) . '.' . $extension;
    $targetPath = lex_payment_proof_path($storedName);
    if (!move_uploaded_file($tmpPath, $targetPath)) {
        throw new RuntimeException('Unable to save payment proof.');
    }

    return [
        'original_name' => $originalName,
        'stored_name' => $storedName,
        'mime_type' => $mime,
        'size' => $size,
        'path' => $targetPath,
    ];
}

function lex_store_payment_qr(array $file): ?array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
        throw new RuntimeException('Unable to upload the GCash QR image.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        throw new RuntimeException('GCash QR image is empty.');
    }
    if ($size > 5 * 1024 * 1024) {
        throw new RuntimeException('GCash QR image is too large. The limit is 5 MB.');
    }

    $tmpPath = (string) $file['tmp_name'];
    $imageType = function_exists('exif_imagetype') ? @exif_imagetype($tmpPath) : false;
    $allowed = [
        IMAGETYPE_JPEG => ['jpg', 'image/jpeg'],
        IMAGETYPE_PNG => ['png', 'image/png'],
        IMAGETYPE_GIF => ['gif', 'image/gif'],
        IMAGETYPE_WEBP => ['webp', 'image/webp'],
    ];
    if (!isset($allowed[$imageType])) {
        throw new RuntimeException('GCash QR must be a JPG, PNG, GIF, or WEBP image.');
    }

    [$extension, $mime] = $allowed[$imageType];
    $storedName = 'gcash_qr_' . bin2hex(random_bytes(16)) . '.' . $extension;
    $targetPath = lex_payment_qr_path($storedName);
    if (!move_uploaded_file($tmpPath, $targetPath)) {
        throw new RuntimeException('Unable to save the GCash QR image.');
    }

    return [
        'original_name' => trim((string) ($file['name'] ?? 'gcash-qr')),
        'stored_name' => $storedName,
        'mime_type' => $mime,
        'size' => $size,
        'path' => $targetPath,
    ];
}

function lex_case_files_sync_record(array $record): void
{
    if (empty($record['folder_name'])) {
        return;
    }
    lex_case_files_write_metadata($record);
}

function lex_encrypt_file_contents(string $contents): string
{
    return lex_encrypt_string($contents);
}

function lex_decrypt_file_contents(string $payload): string
{
    return lex_decrypt_string($payload);
}
