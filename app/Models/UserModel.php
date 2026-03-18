<?php
namespace App\Models;

use App\Core\BaseModel;
use App\Core\Database;

class UserModel extends BaseModel
{
    protected string $table = 'users';

    public function findByEmail(string $email): ?array
    {
        return Database::fetch("SELECT * FROM {$this->table} WHERE email=?", [$email]);
    }

    public function register(array $data): int
    {
        $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        return $this->create($data);
    }

    public function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    public function getFavorites(int $userId): array
    {
        return Database::fetchAll(
            "SELECT r.*, d.name AS district_name,
                (SELECT image_path FROM room_images ri WHERE ri.room_id=r.id AND ri.is_primary=TRUE LIMIT 1) AS primary_image,
                ROUND((SELECT AVG(rating) FROM reviews rv WHERE rv.room_id=r.id)::numeric,1) AS avg_rating
             FROM favorites f
             JOIN rooms r ON f.room_id=r.id
             LEFT JOIN districts d ON r.district_id=d.id
             WHERE f.user_id=? AND r.status='approved'
             ORDER BY f.created_at DESC",
            [$userId]
        );
    }

    public function isFavorite(int $userId, int $roomId): bool
    {
        return Database::fetch(
            "SELECT id FROM favorites WHERE user_id=? AND room_id=?",
            [$userId, $roomId]
        ) !== null;
    }

    public function toggleFavorite(int $userId, int $roomId): string
    {
        if ($this->isFavorite($userId, $roomId)) {
            Database::execute("DELETE FROM favorites WHERE user_id=? AND room_id=?", [$userId, $roomId]);
            return 'removed';
        }
        Database::insert("INSERT INTO favorites (user_id,room_id) VALUES (?,?)", [$userId, $roomId]);
        return 'added';
    }

    public function getNotifications(int $userId, int $limit = 20): array
    {
        return Database::fetchAll(
            "SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT $limit",
            [$userId]
        );
    }

    public function unreadCount(int $userId): int
    {
        $row = Database::fetch(
            "SELECT COUNT(*) AS c FROM notifications WHERE user_id=? AND is_read=FALSE",
            [$userId]
        );
        return (int)($row['c'] ?? 0);
    }

    public function addNotification(int $userId, string $title, string $message, string $type = 'info'): void
    {
        Database::insert(
            "INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,?)",
            [$userId, $title, $message, $type]
        );
    }

    public function markAllRead(int $userId): void
    {
        Database::execute("UPDATE notifications SET is_read=TRUE WHERE user_id=?", [$userId]);
    }
}