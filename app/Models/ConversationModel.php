<?php
namespace App\Models;

use App\Core\BaseModel;
use App\Core\Database;

class ConversationModel extends BaseModel
{
    protected string $table = 'conversations';

    /**
     * Find or create a conversation between two users.
     * Always stores user1_id = MIN, user2_id = MAX.
     */
    public function findOrCreate(int $uid1, int $uid2): array
    {
        $u1 = min($uid1, $uid2);
        $u2 = max($uid1, $uid2);

        $conv = Database::fetch(
            "SELECT * FROM conversations WHERE user1_id = ? AND user2_id = ?",
            [$u1, $u2]
        );
        if ($conv) return $conv;

        $id = Database::insert(
            "INSERT INTO conversations (user1_id, user2_id) VALUES (?, ?)",
            [$u1, $u2]
        );
        return Database::fetch("SELECT * FROM conversations WHERE id = ?", [$id]);
    }

    /**
     * All conversations for a user, with partner info and unread count.
     */
    public function getByUser(int $userId): array
    {
        return Database::fetchAll(
            "SELECT c.*,
                    CASE WHEN c.user1_id = :uid  THEN u2.id     ELSE u1.id     END AS partner_id,
                    CASE WHEN c.user1_id = :uid2 THEN u2.name   ELSE u1.name   END AS partner_name,
                    CASE WHEN c.user1_id = :uid3 THEN u2.avatar ELSE u1.avatar END AS partner_avatar,
                    (SELECT COUNT(*) FROM messages m
                       WHERE m.conversation_id = c.id
                         AND m.sender_id != :uid4
                         AND m.is_read = FALSE) AS unread_count
             FROM conversations c
             JOIN users u1 ON u1.id = c.user1_id
             JOIN users u2 ON u2.id = c.user2_id
             WHERE c.user1_id = :uid5 OR c.user2_id = :uid6
             ORDER BY c.last_message_at DESC NULLS LAST, c.created_at DESC",
            [
                ':uid'  => $userId, ':uid2' => $userId, ':uid3' => $userId,
                ':uid4' => $userId, ':uid5' => $userId, ':uid6' => $userId,
            ]
        );
    }

    /**
     * Get single conversation with partner info, verify user belongs.
     */
    public function findWithPartner(int $convId, int $userId): ?array
    {
        return Database::fetch(
            "SELECT c.*,
                    CASE WHEN c.user1_id = :uid  THEN u2.id     ELSE u1.id     END AS partner_id,
                    CASE WHEN c.user1_id = :uid2 THEN u2.name   ELSE u1.name   END AS partner_name,
                    CASE WHEN c.user1_id = :uid3 THEN u2.avatar ELSE u1.avatar END AS partner_avatar
             FROM conversations c
             JOIN users u1 ON u1.id = c.user1_id
             JOIN users u2 ON u2.id = c.user2_id
             WHERE c.id = :cid AND (c.user1_id = :uid4 OR c.user2_id = :uid5)",
            [
                ':uid'  => $userId, ':uid2' => $userId, ':uid3' => $userId,
                ':cid'  => $convId, ':uid4' => $userId, ':uid5' => $userId,
            ]
        );
    }

    public function updateLastMessage(int $convId, string $text): void
    {
        Database::execute(
            "UPDATE conversations SET last_message = ?, last_message_at = NOW() WHERE id = ?",
            [mb_substr($text, 0, 100), $convId]
        );
    }

    public function updateTyping(int $convId, bool $isUser1): void
    {
        $col = $isUser1 ? 'user1_typing_at' : 'user2_typing_at';
        Database::execute(
            "UPDATE conversations SET {$col} = NOW() WHERE id = ?",
            [$convId]
        );
    }

    /**
     * Total unread messages across all conversations for a user.
     */
    public function getTotalUnread(int $userId): int
    {
        $row = Database::fetch(
            "SELECT COUNT(*) AS cnt
             FROM messages m
             JOIN conversations c ON c.id = m.conversation_id
             WHERE (c.user1_id = ? OR c.user2_id = ?)
               AND m.sender_id != ?
               AND m.is_read = FALSE",
            [$userId, $userId, $userId]
        );
        return (int)($row['cnt'] ?? 0);
    }
}
