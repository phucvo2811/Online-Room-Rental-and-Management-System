<?php
namespace App\Models;

use App\Core\BaseModel;
use App\Core\Database;

class MessageModel extends BaseModel
{
    protected string $table = 'messages';

    /**
     * Load last N messages for a conversation (oldest first).
     */
    public function getByConversation(int $convId, int $limit = 80): array
    {
        return Database::fetchAll(
            "SELECT m.id, m.conversation_id, m.sender_id, m.content, m.is_read,
                    m.created_at, u.name AS sender_name, u.avatar AS sender_avatar
             FROM messages m
             JOIN users u ON u.id = m.sender_id
             WHERE m.conversation_id = ?
             ORDER BY m.created_at ASC, m.id ASC
             LIMIT ?",
            [$convId, $limit]
        );
    }

    /**
     * Poll: messages newer than $afterId.
     */
    public function getAfter(int $convId, int $afterId): array
    {
        return Database::fetchAll(
            "SELECT m.id, m.conversation_id, m.sender_id, m.content, m.is_read,
                    m.created_at, u.name AS sender_name, u.avatar AS sender_avatar
             FROM messages m
             JOIN users u ON u.id = m.sender_id
             WHERE m.conversation_id = ? AND m.id > ?
             ORDER BY m.created_at ASC, m.id ASC",
            [$convId, $afterId]
        );
    }

    /**
     * Mark all messages from the other party as read.
     */
    public function markRead(int $convId, int $myUserId): void
    {
        Database::execute(
            "UPDATE messages SET is_read = TRUE
             WHERE conversation_id = ? AND sender_id != ? AND is_read = FALSE",
            [$convId, $myUserId]
        );
    }

    /**
     * Insert and return the full message row.
     */
    public function send(int $convId, int $senderId, string $content): array
    {
        $id = Database::insert(
            "INSERT INTO messages (conversation_id, sender_id, content) VALUES (?, ?, ?)",
            [$convId, $senderId, $content]
        );
        return Database::fetch(
            "SELECT m.id, m.conversation_id, m.sender_id, m.content, m.is_read,
                    m.created_at, u.name AS sender_name, u.avatar AS sender_avatar
             FROM messages m JOIN users u ON u.id = m.sender_id
             WHERE m.id = ?",
            [$id]
        );
    }
}
