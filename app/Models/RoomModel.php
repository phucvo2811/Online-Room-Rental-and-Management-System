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

        return Database::fetchAll(
            "SELECT r.*,
                u.name  AS landlord_name, u.phone AS landlord_phone,
                d.name  AS district_name, w.name  AS ward_name, s.name AS street_name,
                (SELECT image_path FROM room_images ri WHERE ri.room_id=r.id AND ri.is_primary=TRUE LIMIT 1) AS primary_image,
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
        if (!empty($f['room_type']))   { $where[] = 'r.room_type = ?';   $params[] = $f['room_type']; }
        if (!empty($f['price_min']))   { $where[] = 'r.price >= ?';      $params[] = $f['price_min']; }
        if (!empty($f['price_max']))   { $where[] = 'r.price <= ?';      $params[] = $f['price_max']; }
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
                (SELECT image_path FROM room_images ri WHERE ri.room_id=r.id AND ri.is_primary=TRUE LIMIT 1) AS primary_image
             FROM {$this->table} r
             LEFT JOIN districts d ON r.district_id=d.id
             WHERE r.user_id=? ORDER BY r.created_at DESC",
            [$userId]
        );
    }

    public function getFeatured(int $limit = 8): array
    {
        return Database::fetchAll(
            "SELECT r.*, d.name AS district_name,
                (SELECT image_path FROM room_images ri WHERE ri.room_id=r.id AND ri.is_primary=TRUE LIMIT 1) AS primary_image,
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
                (SELECT image_path FROM room_images ri WHERE ri.room_id=r.id AND ri.is_primary=TRUE LIMIT 1) AS primary_image
             FROM {$this->table} r
             LEFT JOIN users u ON r.user_id=u.id
             LEFT JOIN districts d ON r.district_id=d.id
             WHERE r.status='pending' ORDER BY r.created_at ASC"
        );
    }

    public function getStats(): array
    {
        return [
            'total'    => (int)(Database::fetch("SELECT COUNT(*) AS c FROM rooms")['c'] ?? 0),
            'approved' => (int)(Database::fetch("SELECT COUNT(*) AS c FROM rooms WHERE status='approved'")['c'] ?? 0),
            'pending'  => (int)(Database::fetch("SELECT COUNT(*) AS c FROM rooms WHERE status='pending'")['c'] ?? 0),
            'users'    => (int)(Database::fetch("SELECT COUNT(*) AS c FROM users")['c'] ?? 0),
            'contacts' => (int)(Database::fetch("SELECT COUNT(*) AS c FROM contacts WHERE is_read=FALSE")['c'] ?? 0),
        ];
    }

    public function addImage(int $roomId, string $path, bool $primary = false, int $sort = 0): int
    {
        return Database::insert(
            "INSERT INTO room_images (room_id,image_path,is_primary,sort_order) VALUES (?,?,?,?)",
            [$roomId, $path, $primary ? 'TRUE' : 'FALSE', $sort]
        );
    }

    public function incrementView(int $id): void
    {
        Database::execute("UPDATE {$this->table} SET view_count=view_count+1 WHERE id=?", [$id]);
    }
}