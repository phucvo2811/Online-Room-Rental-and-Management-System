<?php
namespace App\Models;

use App\Core\BaseModel;
use App\Core\Database;

class RoomModel extends BaseModel
{
    protected string $table = 'rooms';

    public function getFiltered(array $f = [], int $page = 1): array
    {
        [$where, $params] = $this->buildFilter($f);
        $limit  = ITEMS_PER_PAGE;
        $offset = ($page - 1) * $limit;
        $order  = match ($f['sort'] ?? 'newest') {
            'price_asc'  => 'r.price ASC',
            'price_desc' => 'r.price DESC',
            'area'       => 'r.area DESC',
            'popular'    => 'r.view_count DESC',
            default      => 'r.created_at DESC',
        };

        // Prioritize PRO listings (active subscriptions) in search results.
        $order = "is_pro DESC, $order";

        return Database::fetchAll(
            "SELECT r.*,
                u.name  AS landlord_name, u.phone AS landlord_phone,
                d.name  AS district_name, w.name  AS ward_name, s.name AS street_name,
                (EXISTS (SELECT 1 FROM subscriptions s WHERE s.user_id=r.user_id AND s.status='active' AND s.starts_at <= NOW() AND s.ends_at >= NOW())) AS is_pro,
                COALESCE(
                    (SELECT image_path FROM room_images ri WHERE ri.room_id=r.id AND ri.is_primary=TRUE LIMIT 1),
                    (SELECT image_path FROM room_images ri WHERE ri.room_id=r.id ORDER BY id ASC LIMIT 1),
                    (SELECT image_path FROM block_images bi WHERE bi.block_id=r.block_id ORDER BY bi.id ASC LIMIT 1)
                ) AS primary_image,
                (SELECT COUNT(*) FROM reviews rv WHERE rv.room_id=r.id) AS review_count,
                ROUND((SELECT AVG(rating) FROM reviews rv WHERE rv.room_id=r.id)::numeric,1) AS avg_rating
             FROM {$this->table} r
             LEFT JOIN users     u ON r.user_id     = u.id
             LEFT JOIN districts d ON r.district_id = d.id
             LEFT JOIN wards     w ON r.ward_id      = w.id
             LEFT JOIN streets   s ON r.street_id    = s.id
             WHERE $where ORDER BY $order LIMIT $limit OFFSET $offset",
            $params
        );
    }

    public function countFiltered(array $f = []): int
    {
        [$where, $params] = $this->buildFilter($f);
        $row = Database::fetch("SELECT COUNT(*) AS cnt FROM {$this->table} r WHERE $where", $params);
        return (int)($row['cnt'] ?? 0);
    }

    private function buildFilter(array $f): array
    {
        $where  = ['r.status = ?'];
        $params = ['approved'];

        if (!empty($f['district_id'])) { $where[] = 'r.district_id = ?'; $params[] = $f['district_id']; }
        if (!empty($f['ward_id']))     { $where[] = 'r.ward_id = ?';     $params[] = $f['ward_id']; }
        if (!empty($f['street_id']))   { $where[] = 'r.street_id = ?';   $params[] = $f['street_id']; }
        if (!empty($f['block_id']))    { $where[] = 'r.block_id = ?';    $params[] = $f['block_id']; }
        if (!empty($f['room_type']))   { $where[] = 'r.room_type = ?';   $params[] = $f['room_type']; }
        if (!empty($f['price_min']))   { $where[] = 'r.price >= ?';      $params[] = $f['price_min']; }
        if (!empty($f['price_max']))   { $where[] = 'r.price <= ?';      $params[] = $f['price_max']; }
        if (isset($f['is_available'])) {
            $where[] = $f['is_available'] ? 'r.is_available = TRUE' : 'r.is_available = FALSE';
        }
        if (!empty($f['has_wifi']))    { $where[] = 'r.has_wifi = TRUE'; }
        if (!empty($f['has_ac']))      { $where[] = 'r.has_ac = TRUE'; }
        if (!empty($f['has_parking'])) { $where[] = 'r.has_parking = TRUE'; }
        if (!empty($f['allow_pet']))   { $where[] = 'r.allow_pet = TRUE'; }
        if (!empty($f['keyword'])) {
            $where[]  = '(r.title ILIKE ? OR r.address ILIKE ? OR r.description ILIKE ?)';
            $kw        = '%' . $f['keyword'] . '%';
            $params[] = $kw; $params[] = $kw; $params[] = $kw;
        }
        return [implode(' AND ', $where), $params];
    }

    public function getDetail(int $id): ?array
    {
        return Database::fetch(
            "SELECT r.*,
                u.name AS landlord_name, u.phone AS landlord_phone,
                u.email AS landlord_email,
                d.name AS district_name, w.name AS ward_name, s.name AS street_name,
                (EXISTS (SELECT 1 FROM subscriptions s WHERE s.user_id=r.user_id AND s.status='active' AND s.starts_at <= NOW() AND s.ends_at >= NOW())) AS is_pro,
                ROUND((SELECT AVG(rating) FROM reviews rv WHERE rv.room_id=r.id)::numeric,1) AS avg_rating,
                (SELECT COUNT(*) FROM reviews rv WHERE rv.room_id=r.id) AS review_count
             FROM {$this->table} r
             LEFT JOIN users     u ON r.user_id     = u.id
             LEFT JOIN districts d ON r.district_id = d.id
             LEFT JOIN wards     w ON r.ward_id      = w.id
             LEFT JOIN streets   s ON r.street_id    = s.id
             WHERE r.id = ?",
            [$id]
        );
    }

    public function getImages(int $roomId): array
    {
        return Database::fetchAll(
            "SELECT * FROM room_images WHERE room_id=? ORDER BY is_primary DESC, sort_order ASC",
            [$roomId]
        );
    }

    public function getReviews(int $roomId): array
    {
        return Database::fetchAll(
            "SELECT rv.*, u.name AS user_name, u.avatar
             FROM reviews rv JOIN users u ON rv.user_id=u.id
             WHERE rv.room_id=? ORDER BY rv.created_at DESC",
            [$roomId]
        );
    }

    public function getByUser(int $userId): array
    {
        return Database::fetchAll(
            "SELECT r.*, d.name AS district_name,
                rb.name AS property_name,
                rt.name AS room_type_name,
                (EXISTS (SELECT 1 FROM subscriptions s WHERE s.user_id=r.user_id AND s.status='active' AND s.starts_at <= NOW() AND s.ends_at >= NOW())) AS is_pro,
                COALESCE(
                    (SELECT image_path FROM room_images ri WHERE ri.room_id=r.id AND ri.is_primary=TRUE LIMIT 1),
                    (SELECT image_path FROM room_images ri WHERE ri.room_id=r.id ORDER BY id ASC LIMIT 1),
                    (SELECT image_path FROM block_images bi WHERE bi.block_id=r.block_id ORDER BY bi.id ASC LIMIT 1)
                ) AS primary_image
             FROM {$this->table} r
             LEFT JOIN districts d ON r.district_id=d.id
             LEFT JOIN room_types rt ON r.room_type_id=rt.id
             LEFT JOIN room_blocks rb ON r.property_id=rb.id
             WHERE r.user_id=? ORDER BY r.created_at DESC",
            [$userId]
        );
    }

    public function getByProperty(int $propertyId, int $userId): array
    {
        return Database::fetchAll(
            "SELECT r.*, rt.name AS room_type_name, rb.name AS property_name
             FROM {$this->table} r
             LEFT JOIN room_types rt ON r.room_type_id=rt.id
             LEFT JOIN room_blocks rb ON r.property_id=rb.id
             WHERE r.property_id=? AND rb.user_id=?
             ORDER BY r.room_number ASC",
            [$propertyId, $userId]
        );
    }

    public function findByIdInProperty(int $roomId, int $propertyId): ?array
    {
        return Database::fetch(
            "SELECT r.* FROM {$this->table} r WHERE r.id=? AND r.property_id=?",
            [$roomId, $propertyId]
        );
    }

    public function setOccupancyStatus(int $roomId, string $status): int
    {
        return Database::execute("UPDATE {$this->table} SET occupancy_status=? WHERE id=?", [$status, $roomId]);
    }

    public function assignBlock(int $roomId, int $blockId): int
    {
        return Database::execute("UPDATE {$this->table} SET block_id=?, property_id=? WHERE id=?", [$blockId, $blockId, $roomId]);
    }

    public function getFeatured(int $limit = 8): array
    {
        return Database::fetchAll(
            "SELECT r.*, d.name AS district_name,
                COALESCE(
                    (SELECT image_path FROM room_images ri WHERE ri.room_id=r.id AND ri.is_primary=TRUE LIMIT 1),
                    (SELECT image_path FROM room_images ri WHERE ri.room_id=r.id ORDER BY id ASC LIMIT 1),
                    (SELECT image_path FROM block_images bi WHERE bi.block_id=r.block_id ORDER BY bi.id ASC LIMIT 1)
                ) AS primary_image,
                ROUND((SELECT AVG(rating) FROM reviews rv WHERE rv.room_id=r.id)::numeric,1) AS avg_rating
             FROM {$this->table} r
             LEFT JOIN districts d ON r.district_id=d.id
             WHERE r.status='approved' AND r.is_available=TRUE
             ORDER BY r.view_count DESC LIMIT $limit"
        );
    }

    public function getPending(): array
    {
        return Database::fetchAll(
            "SELECT r.*, u.name AS landlord_name, u.phone AS landlord_phone,
                d.name AS district_name,
                COALESCE(
                    (SELECT image_path FROM room_images ri WHERE ri.room_id=r.id AND ri.is_primary=TRUE LIMIT 1),
                    (SELECT image_path FROM room_images ri WHERE ri.room_id=r.id ORDER BY id ASC LIMIT 1),
                    (SELECT image_path FROM block_images bi WHERE bi.block_id=r.block_id ORDER BY bi.id ASC LIMIT 1)
                ) AS primary_image
             FROM {$this->table} r
             LEFT JOIN users u ON r.user_id=u.id
             LEFT JOIN districts d ON r.district_id=d.id
             WHERE r.status='pending' ORDER BY r.created_at ASC"
        );
    }

    public function getStats(): array
    {
        // pending_landlord requires the migration to have been run; fall back to 0 gracefully
        try {
            $pendingLandlord = (int)(Database::fetch("SELECT COUNT(*) AS c FROM users WHERE landlord_status='pending'")['c'] ?? 0);
        } catch (\Throwable $e) {
            $pendingLandlord = 0;
        }

        return [
            'total'              => (int)(Database::fetch("SELECT COUNT(*) AS c FROM rooms")['c'] ?? 0),
            'approved'           => (int)(Database::fetch("SELECT COUNT(*) AS c FROM rooms WHERE status='approved'")['c'] ?? 0),
            'pending'            => (int)(Database::fetch("SELECT COUNT(*) AS c FROM rooms WHERE status='pending'")['c'] ?? 0),
            'users'              => (int)(Database::fetch("SELECT COUNT(*) AS c FROM users")['c'] ?? 0),
            'contacts'           => (int)(Database::fetch("SELECT COUNT(*) AS c FROM contacts WHERE is_read=FALSE")['c'] ?? 0),
            'pending_posts'      => (int)(Database::fetch("SELECT COUNT(*) AS c FROM posts WHERE status='inactive'")['c'] ?? 0),
            'pending_bds'        => (int)(Database::fetch("SELECT COUNT(*) AS c FROM room_blocks WHERE status='pending'")['c'] ?? 0),
            'pending_landlord'   => $pendingLandlord,
        ];
    }

    public function getTotalViewsByUser(int $userId): int
    {
        $row = Database::fetch(
            "SELECT COALESCE(SUM(view_count),0) AS c FROM {$this->table} WHERE user_id=?",
            [$userId]
        );
        return (int)($row['c'] ?? 0);
    }

    public function setModerationStatus(int $roomId, string $status): int
    {
        // Cập nhật cả moderation_status và status để phòng được hiển thị đúng với trạng thái duyệt.
        return Database::execute(
            "UPDATE {$this->table} SET moderation_status=?, status=? WHERE id=?",
            [$status, $status, $roomId]
        );
    }

    public function markSpam(int $roomId, bool $isSpam = true): int
    {
        return Database::execute(
            "UPDATE {$this->table} SET is_spam=? WHERE id=?",
            [$isSpam ? 'TRUE' : 'FALSE', $roomId]
        );
    }

    public function getRoomsForAdmin(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['moderation_status'])) {
            $where[] = 'moderation_status = ?';
            $params[] = $filters['moderation_status'];
        }
        if (!empty($filters['is_spam'])) {
            $where[] = 'is_spam = TRUE';
        }
        if (!empty($filters['keyword'])) {
            $where[] = '(r.title ILIKE ? OR r.description ILIKE ?)';
            $params[] = '%' . $filters['keyword'] . '%';
            $params[] = '%' . $filters['keyword'] . '%';
        }

        $whereSql = implode(' AND ', $where);

        return Database::fetchAll(
            "SELECT r.*, u.name AS landlord_name, u.phone AS landlord_phone, d.name AS district_name,
                COALESCE((SELECT image_path FROM room_images ri WHERE ri.room_id=r.id AND ri.is_primary=TRUE LIMIT 1),
                         (SELECT image_path FROM room_images ri WHERE ri.room_id=r.id ORDER BY id ASC LIMIT 1),
                         (SELECT image_path FROM block_images bi WHERE bi.block_id=r.block_id ORDER BY bi.id ASC LIMIT 1)) AS primary_image
             FROM {$this->table} r
             LEFT JOIN users u ON r.user_id=u.id
             LEFT JOIN districts d ON r.district_id=d.id
             WHERE $whereSql
             ORDER BY r.created_at DESC",
            $params
        );
    }

    public function getByIdWithHistory(int $roomId): ?array
    {
        return Database::fetch(
            "SELECT r.*, u.name AS landlord_name, u.phone AS landlord_phone, d.name AS district_name,
                COALESCE((SELECT image_path FROM room_images ri WHERE ri.room_id=r.id AND ri.is_primary=TRUE LIMIT 1),
                         (SELECT image_path FROM room_images ri WHERE ri.room_id=r.id ORDER BY id ASC LIMIT 1),
                         (SELECT image_path FROM block_images bi WHERE bi.block_id=r.block_id ORDER BY bi.id ASC LIMIT 1)) AS primary_image
             FROM {$this->table} r
             LEFT JOIN users u ON r.user_id=u.id
             LEFT JOIN districts d ON r.district_id=d.id
             WHERE r.id = ?",
            [$roomId]
        );
    }

    public function getEditHistory(int $roomId): array
    {
        return Database::fetchAll(
            "SELECT reh.*, u.name AS edited_by FROM room_edit_history reh
             LEFT JOIN users u ON reh.user_id=u.id
             WHERE reh.room_id=? ORDER BY reh.created_at DESC",
            [$roomId]
        );
    }

    public function saveEditHistory(int $roomId, int $userId, array $oldData, array $newData, ?string $reason): int
    {
        return Database::insert(
            "INSERT INTO room_edit_history (room_id,user_id,old_data,new_data,reason) VALUES (?,?,?,?,?)",
            [$roomId, $userId, json_encode($oldData, JSON_UNESCAPED_UNICODE), json_encode($newData, JSON_UNESCAPED_UNICODE), $reason]
        );
    }

    public function addImage(int $roomId, string $path, bool $primary = false, int $sort = 0): int
    {
        return Database::insert(
            "INSERT INTO room_images (room_id,image_path,is_primary,sort_order) VALUES (?,?,?,?)",
            [$roomId, $path, $primary ? 'TRUE' : 'FALSE', $sort]
        );
    }

    public function getImage(int $imageId): ?array
    {
        return Database::fetch("SELECT * FROM room_images WHERE id=?", [$imageId]);
    }

    public function deleteImage(int $imageId): int
    {
        return Database::execute("DELETE FROM room_images WHERE id=?", [$imageId]);
    }

    public function clearPrimaryImages(int $roomId): int
    {
        return Database::execute("UPDATE room_images SET is_primary=FALSE WHERE room_id=?", [$roomId]);
    }

    public function setPrimaryImage(int $roomId, int $imageId): int
    {
        $this->clearPrimaryImages($roomId);
        return Database::execute("UPDATE room_images SET is_primary=TRUE WHERE id=? AND room_id=?", [$imageId, $roomId]);
    }

    public function incrementView(int $id): void
    {
        Database::execute("UPDATE {$this->table} SET view_count=view_count+1 WHERE id=?", [$id]);
    }

    public function getRoomNumberInProperty(int $propertyId, int $roomNumber): ?array
    {
        return Database::fetch(
            "SELECT r.id FROM {$this->table} r WHERE r.property_id=? AND r.room_number=? LIMIT 1",
            [$propertyId, $roomNumber]
        );
    }

    public function getNextAvailableRoomNumber(int $propertyId): int
    {
        $result = Database::fetch(
            "SELECT COALESCE(MAX(r.room_number), 0) + 1 AS next_number FROM {$this->table} r WHERE r.property_id=?",
            [$propertyId]
        );
        return (int)($result['next_number'] ?? 1);
    }

    public function getMaxRoomNumberInProperty(int $propertyId): int
    {
        $result = Database::fetch(
            "SELECT COALESCE(MAX(r.room_number), 0) AS max_number FROM {$this->table} r WHERE r.property_id=?",
            [$propertyId]
        );
        return (int)($result['max_number'] ?? 0);
    }
}