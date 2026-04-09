<?php
namespace App\Models;

use App\Core\BaseModel;
use App\Core\Database;

class PostModel extends BaseModel
{
    protected string $table = 'posts';

    public function getAllByUser(int $userId): array
    {
        return Database::fetchAll(
            "SELECT p.*, rb.name AS property_name
             FROM {$this->table} p
             LEFT JOIN room_blocks rb ON p.block_id=rb.id
             WHERE p.user_id=?
             ORDER BY p.created_at DESC",
            [$userId]
        );
    }

    public function getById(int $id): ?array
    {
        return Database::fetch(
            "SELECT p.*, rb.name AS property_name
             FROM {$this->table} p
             LEFT JOIN room_blocks rb ON p.block_id=rb.id
             WHERE p.id=?",
            [$id]
        );
    }

    public function getActiveListingByProperty(int $propertyId): ?array
    {
        return Database::fetch(
            "SELECT p.* FROM {$this->table} p WHERE p.block_id=? AND p.status='active' ORDER BY p.created_at DESC LIMIT 1",
            [$propertyId]
        );
    }

    public function getBlockPosts(int $blockId): array
    {
        return Database::fetchAll(
            "SELECT p.* FROM {$this->table} p WHERE p.block_id=? AND p.type='block' ORDER BY p.created_at DESC",
            [$blockId]
        );
    }

    public function getRoomPosts(int $roomId): array
    {
        return Database::fetchAll(
            "SELECT p.* FROM {$this->table} p WHERE p.room_id=? AND p.type='room' ORDER BY p.created_at DESC",
            [$roomId]
        );
    }

    public function getAllByStatus(string $status = 'inactive'): array
    {
        return Database::fetchAll(
            "SELECT p.*, rb.name AS block_name, r.title AS room_title, u.name AS landlord_name
             FROM {$this->table} p
             LEFT JOIN room_blocks rb ON p.block_id=rb.id
             LEFT JOIN rooms r ON p.room_id=r.id
             LEFT JOIN users u ON p.user_id=u.id
             WHERE p.status=?
             ORDER BY p.created_at DESC",
            [$status]
        );
    }

    public function updateByRoom(int $roomId, array $data): int
    {
        if (empty($data)) return 0;
        $sets = implode(', ', array_map(fn($k) => "$k=?", array_keys($data)));
        return Database::execute(
            "UPDATE {$this->table} SET $sets WHERE room_id=? AND type='room'",
            [...array_values($data), $roomId]
        );
    }

    public function updateByBlock(int $blockId, array $data): int
    {
        if (empty($data)) return 0;
        $sets = implode(', ', array_map(fn($k) => "$k=?", array_keys($data)));
        return Database::execute(
            "UPDATE {$this->table} SET $sets WHERE block_id=? AND type='block'",
            [...array_values($data), $blockId]
        );
    }

    public function incrementView(int $postId): void
    {
        Database::execute(
            "UPDATE {$this->table} SET view_count=view_count+1 WHERE id=?",
            [$postId]
        );
    }

    public function toggleStatus(int $postId, int $userId): string
    {
        $post = Database::fetch(
            "SELECT id, status FROM {$this->table} WHERE id=? AND user_id=?",
            [$postId, $userId]
        );
        if (!$post) return '';
        $newStatus = ($post['status'] === 'active') ? 'inactive' : 'active';
        Database::execute(
            "UPDATE {$this->table} SET status=?, updated_at=NOW() WHERE id=? AND user_id=?",
            [$newStatus, $postId, $userId]
        );
        return $newStatus;
    }

    public function getLatestByBlock(int $blockId): ?array
    {
        return Database::fetch(
            "SELECT * FROM {$this->table} WHERE block_id=? ORDER BY created_at DESC LIMIT 1",
            [$blockId]
        );
    }
}
