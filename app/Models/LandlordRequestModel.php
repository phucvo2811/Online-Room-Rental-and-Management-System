<?php
namespace App\Models;

use App\Core\BaseModel;
use App\Core\Database;

class LandlordRequestModel extends BaseModel
{
    protected string $table = 'landlord_requests';

    /**
     * Get the most recent request for a user (any status).
     */
    public function getLatestByUser(int $userId): ?array
    {
        return Database::fetch(
            "SELECT lr.*, u.name AS user_name, u.email AS user_email
             FROM {$this->table} lr
             JOIN users u ON u.id = lr.user_id
             WHERE lr.user_id = ?
             ORDER BY lr.created_at DESC
             LIMIT 1",
            [$userId]
        );
    }

    /**
     * Get the active pending request for a user, if any.
     */
    public function getPendingByUser(int $userId): ?array
    {
        return Database::fetch(
            "SELECT * FROM {$this->table} WHERE user_id = ? AND status = 'pending' LIMIT 1",
            [$userId]
        );
    }

    /**
     * Get all requests for the admin panel, optionally filtered by status.
     */
    public function getAllForAdmin(string $status = 'pending'): array
    {
        return Database::fetchAll(
            "SELECT lr.*,
                    u.name    AS user_name,
                    u.email   AS user_email,
                    u.avatar  AS user_avatar,
                    u.created_at AS user_created_at,
                    r.name    AS reviewer_name
             FROM {$this->table} lr
             JOIN users u ON u.id = lr.user_id
             LEFT JOIN users r ON r.id = lr.reviewed_by
             WHERE lr.status = ?
             ORDER BY lr.created_at ASC",
            [$status]
        );
    }

    /**
     * Count pending requests (used in admin sidebar badge).
     */
    public function countPending(): int
    {
        $row = Database::fetch(
            "SELECT COUNT(*) AS c FROM {$this->table} WHERE status = 'pending'"
        );
        return (int)($row['c'] ?? 0);
    }

    /**
     * Approve a request: update status, record reviewer and timestamp.
     */
    public function approve(int $id, int $adminId): void
    {
        Database::execute(
            "UPDATE {$this->table}
             SET status = 'approved', reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW()
             WHERE id = ?",
            [$adminId, $id]
        );
    }

    /**
     * Reject a request with an optional admin note.
     */
    public function reject(int $id, int $adminId, string $note = ''): void
    {
        Database::execute(
            "UPDATE {$this->table}
             SET status = 'rejected', admin_note = ?, reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW()
             WHERE id = ?",
            [$note ?: null, $adminId, $id]
        );
    }
}
