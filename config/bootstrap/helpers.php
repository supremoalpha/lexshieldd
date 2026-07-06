<?php
declare(strict_types=1);

function lex_audit(string $action, string $table, ?string $targetId = null, ?int $userId = null): void
{
    try {
        $userId = $userId ?? (lex_current_user()['id'] ?? null);
        $stmt = lex_pdo()->prepare('INSERT INTO audit_logs (user_id, action, target_table, target_id, ip_address, user_agent, performed_at) VALUES (:user_id, :action, :target_table, :target_id, :ip_address, :user_agent, NOW())');
        $stmt->execute([
            'user_id' => $userId,
            'action' => $action,
            'target_table' => $table,
            'target_id' => $targetId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 250),
        ]);
    } catch (Throwable $e) {
        error_log(sprintf('[AUDIT] %s on %s failed: %s', $action, $table, $e->getMessage()));
    }
}

function lex_notify(int $userId, string $type, string $message): void
{
    try {
        $stmt = lex_pdo()->prepare('INSERT INTO notifications (user_id, type, message, is_read, created_at) VALUES (:user_id, :type, :message, 0, NOW())');
        $stmt->execute([
            'user_id' => $userId,
            'type' => $type,
            'message' => $message,
        ]);
    } catch (Throwable $e) {
        error_log(sprintf('[NOTIFY] %s for user %d failed: %s', $type, $userId, $e->getMessage()));
    }
}

function lex_message_role_label(string $role): string
{
    return match ($role) {
        'admin' => 'Admin',
        'lawyer' => 'Lawyer',
        'client' => 'Client',
        default => ucfirst($role),
    };
}

function lex_message_excerpt(string $value, int $limit = 72): string
{
    $text = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    if ($text === '') {
        return 'No message yet';
    }
    if (strlen($text) <= $limit) {
        return $text;
    }

    return rtrim(substr($text, 0, max(0, $limit - 3))) . '...';
}

function lex_message_display_text(array $message): string
{
    $isEncrypted = !empty($message['is_encrypted']);
    $plain = $isEncrypted
        ? lex_decrypt_string((string) ($message['message_text'] ?? ''))
        : (string) ($message['message_text'] ?? '');
    $plain = trim($plain);
    if ($plain === '') {
        if (!empty($message['attachment_original_name'])) {
            $plain = 'Attachment: ' . (string) $message['attachment_original_name'];
        } elseif (!empty($message['attachment_stored_name'])) {
            $plain = 'Attachment: ' . (string) $message['attachment_stored_name'];
        }
    }
    return $plain !== '' ? $plain : (string) ($message['message_text'] ?? '');
}

function lex_message_timestamp(string $value): string
{
    $time = strtotime($value);
    if ($time === false) {
        return $value;
    }
    return date('M j, g:i A', $time);
}

function lex_message_bubble_class(int $senderId, int $currentUserId): string
{
    return $senderId === $currentUserId ? 'sent' : 'received';
}

function lex_case_count_for_role(array $user): int
{
    return lex_db_retry(static function () use ($user): int {
        if ($user['role'] === 'admin') {
            return lex_stats('SELECT COUNT(*) FROM cases');
        }
        if ($user['role'] === 'lawyer') {
            $stmt = lex_pdo()->prepare('SELECT id FROM lawyers WHERE user_id = :uid');
            $stmt->execute(['uid' => $user['id']]);
            $lawyerId = (int) ($stmt->fetchColumn() ?: 0);
            return $lawyerId ? lex_stats('SELECT COUNT(*) FROM cases WHERE lawyer_id = :lid', ['lid' => $lawyerId]) : 0;
        }
        $stmt = lex_pdo()->prepare('SELECT id FROM clients WHERE user_id = :uid');
        $stmt->execute(['uid' => $user['id']]);
        $clientId = (int) ($stmt->fetchColumn() ?: 0);
        return $clientId ? lex_stats('SELECT COUNT(*) FROM cases WHERE client_id = :cid', ['cid' => $clientId]) : 0;
    }, 0);
}

function lex_crypto_key(): string
{
    global $lexEncryptionKey;
    return substr(hash('sha256', $lexEncryptionKey, true), 0, 32);
}

function lex_encrypt_string(string $plaintext): string
{
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plaintext, 'AES-256-CBC', lex_crypto_key(), OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $cipher);
}

function lex_decrypt_string(string $payload): string
{
    $raw = base64_decode($payload, true);
    if ($raw === false || strlen($raw) < 17) {
        return '';
    }
    $iv = substr($raw, 0, 16);
    $cipher = substr($raw, 16);
    $plain = openssl_decrypt($cipher, 'AES-256-CBC', lex_crypto_key(), OPENSSL_RAW_DATA, $iv);
    return $plain === false ? '' : $plain;
}

function lex_user_lawyer_id(int $userId): int
{
    return (int) lex_db_retry(static function () use ($userId): int {
        $stmt = lex_pdo()->prepare('SELECT id FROM lawyers WHERE user_id = :uid');
        $stmt->execute(['uid' => $userId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }, 0);
}

function lex_user_client_id(int $userId): int
{
    return (int) lex_db_retry(static function () use ($userId): int {
        $stmt = lex_pdo()->prepare('SELECT id FROM clients WHERE user_id = :uid');
        $stmt->execute(['uid' => $userId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }, 0);
}
