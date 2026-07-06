<?php
declare(strict_types=1);

function lex_messages_delete_attachment_file(array $message): void
{
    if (!empty($message['attachment_stored_name'])) {
        $path = lex_messages_attachment_path((string) $message['attachment_stored_name']);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

function lex_messages_delete_conversation_for_user(int $caseId, int $currentUserId, int $partnerUserId): int
{
    $deletedCount = lex_mark_conversation_deleted_for_user($caseId, $currentUserId, $partnerUserId);
    if ($deletedCount > 0) {
        lex_audit('delete_conversation', 'messages', (string) $caseId);
    }
    return $deletedCount;
}

function lex_messages_find_owned_message(PDO $pdo, int $messageId, int $caseId, int $senderId, int $viewerId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, sender_id, attachment_stored_name
         FROM messages
         WHERE id = :id
           AND case_id = :case_id
           AND sender_id = :sender_id
           AND NOT EXISTS (SELECT 1 FROM message_deletions md WHERE md.message_id = messages.id AND md.user_id = :viewer_id)
         LIMIT 1'
    );
    $stmt->execute([
        'id' => $messageId,
        'case_id' => $caseId,
        'sender_id' => $senderId,
        'viewer_id' => $viewerId,
    ]);
    $message = $stmt->fetch();
    return $message ?: null;
}

function lex_messages_delete_message_for_user(int $messageId, int $viewerId): void
{
    lex_mark_message_deleted_for_user($messageId, $viewerId);
    lex_audit('delete_message', 'messages', (string) $messageId);
}

function lex_messages_send_secure(PDO $pdo, int $senderId, int $receiverId, int $caseId, string $payload, ?array $attachment = null): int
{
    $encrypted = lex_encrypt_string($payload);
    $stmt = $pdo->prepare(
        'INSERT INTO messages (sender_id, receiver_id, case_id, message_text, is_encrypted, sent_at, is_read, attachment_original_name, attachment_stored_name, attachment_mime_type, attachment_size)
         VALUES (:sender_id, :receiver_id, :case_id, :message_text, 1, NOW(), 0, :attachment_original_name, :attachment_stored_name, :attachment_mime_type, :attachment_size)'
    );
    $stmt->execute([
        'sender_id' => $senderId,
        'receiver_id' => $receiverId,
        'case_id' => $caseId,
        'message_text' => $encrypted,
        'attachment_original_name' => $attachment['original_name'] ?? null,
        'attachment_stored_name' => $attachment['stored_name'] ?? null,
        'attachment_mime_type' => $attachment['mime_type'] ?? null,
        'attachment_size' => $attachment['size'] ?? null,
    ]);
    $messageId = (int) $pdo->lastInsertId();
    lex_audit('send_message', 'messages', (string) $messageId);
    return $messageId;
}
