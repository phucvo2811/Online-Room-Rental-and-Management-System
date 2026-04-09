<?php
namespace App\Models;

use App\Core\BaseModel;
use App\Core\Database;

class RoomBlockModel extends BaseModel
{
    protected string $table = 'room_blocks';

    // Property type groups
    const GROUP_COMPOSITE = ['boarding_house', 'dormitory', 'homestay'];  // Contains multiple rooms
    const GROUP_SINGLE = ['mini_house', 'full_house'];                     // Single property

    /**
     * Get all properties for a user
     */
    public function getByUser(int $userId): array
    {
        return Database::fetchAll(
            "SELECT rb.*,
                COUNT(r.id) AS room_count,
                COALESCE(rb.price_min, (SELECT MIN(price) FROM rooms WHERE property_id=rb.id AND occupancy_status='available'), rb.price) AS min_display_price,
                COALESCE(rb.price_max, (SELECT MAX(price) FROM rooms WHERE property_id=rb.id AND occupancy_status='available')) AS max_display_price
             FROM {$this->table} rb
             LEFT JOIN rooms r ON r.property_id = rb.id
             WHERE rb.user_id = ?
             GROUP BY rb.id
             ORDER BY rb.status = 'pending' DESC, rb.created_at DESC",
            [$userId]
        );
    }

    /**
     * Get APPROVED properties for a user (usable for posting)
     */
    public function getApprovedByUser(int $userId): array
    {
        return Database::fetchAll(
            "SELECT rb.*,
                COUNT(r.id) AS room_count,
                COALESCE(rb.price_min, (SELECT MIN(price) FROM rooms WHERE property_id=rb.id AND occupancy_status='available'), rb.price) AS min_display_price,
                COALESCE(rb.price_max, (SELECT MAX(price) FROM rooms WHERE property_id=rb.id AND occupancy_status='available')) AS max_display_price
             FROM {$this->table} rb
             LEFT JOIN rooms r ON r.property_id = rb.id
             WHERE rb.user_id = ? AND rb.status = 'approved'
             GROUP BY rb.id
             ORDER BY rb.created_at DESC",
            [$userId]
        );
    }

    /**
     * Get properties by approval status
     */
    public function getByStatus(string $status): array
    {
        return Database::fetchAll(
            "SELECT rb.*, COUNT(r.id) AS room_count
             FROM {$this->table} rb
             LEFT JOIN rooms r ON r.property_id = rb.id
             WHERE rb.status = ?
             GROUP BY rb.id
             ORDER BY rb.created_at DESC",
            [$status]
        );
    }

    /**
     * Get property with room count
     */
    public function getBlockWithRooms(int $blockId, int $userId): ?array
    {
        return Database::fetch(
            "SELECT rb.*, COUNT(r.id) AS room_count
             FROM {$this->table} rb
             LEFT JOIN rooms r ON r.property_id = rb.id
             WHERE rb.id = ? AND rb.user_id = ?
             GROUP BY rb.id",
            [$blockId, $userId]
        );
    }

    /**
     * Get property details with pricing info
     */
    public function getById(int $blockId): ?array
    {
        return Database::fetch(
            "SELECT rb.*,
                d.name AS district_name,
                w.name AS ward_name,
                COUNT(r.id) AS room_count,
                COALESCE(rb.price_min, (SELECT MIN(price) FROM rooms WHERE property_id=rb.id AND occupancy_status='available'), rb.price) AS computed_price_min,
                COALESCE(rb.price_max, (SELECT MAX(price) FROM rooms WHERE property_id=rb.id AND occupancy_status='available')) AS computed_price_max,
                COALESCE((SELECT MIN(price) FROM rooms WHERE property_id=rb.id AND occupancy_status='available'), rb.price) AS min_display_price,
                COALESCE(
                    (SELECT image_path FROM block_images bi WHERE bi.block_id=rb.id AND bi.is_primary=TRUE LIMIT 1),
                    (SELECT image_path FROM block_images bi WHERE bi.block_id=rb.id ORDER BY bi.id ASC LIMIT 1)
                ) AS primary_image
             FROM {$this->table} rb
             LEFT JOIN rooms r ON r.property_id = rb.id
             LEFT JOIN districts d ON d.id = rb.district_id
             LEFT JOIN wards w ON w.id = rb.ward_id
             WHERE rb.id = ?
             GROUP BY rb.id, d.name, w.name",
            [$blockId]
        );
    }

    /**
     * Check if property is composite (contains rooms)
     */
    public function isComposite(string $type): bool
    {
        return in_array($type, self::GROUP_COMPOSITE);
    }

    /**
     * Check if property is single/standalone
     */
    public function isSingle(string $type): bool
    {
        return in_array($type, self::GROUP_SINGLE);
    }

    /**
     * Get property price display information
     * Returns: price, display_price, or price_range based on type
     */
    public function getPriceDisplayInfo(array $property): array
    {
        $type = $property['type'] ?? '';

        if (in_array($type, self::GROUP_COMPOSITE)) {
            // Composite: show price range from rooms
            $priceMin = $property['computed_price_min'] ?? $property['price_min'];
            $priceMax = $property['computed_price_max'] ?? $property['price_max'];

            return [
                'type' => 'range',
                'min' => $priceMin,
                'max' => $priceMax,
                'display' => number_format($priceMin ?? 0, 0, ',', '.') . 'đ - ' . number_format($priceMax ?? 0, 0, ',', '.').'đ',
            ];
        } else {
            // Single: show exact price
            $price = $property['price'] ?? 0;
            return [
                'type' => 'exact',
                'price' => $price,
                'display' => number_format($price, 0, ',', '.').'đ',
            ];
        }
    }

    /**
     * Get property images
     */
    public function getImages(int $blockId): array
    {
        return Database::fetchAll(
            "SELECT * FROM block_images WHERE block_id = ? ORDER BY is_primary DESC, sort_order ASC",
            [$blockId]
        );
    }

    /**
     * Get primary image for property
     */
    public function getPrimaryImage(int $blockId): ?array
    {
        return Database::fetch(
            "SELECT * FROM block_images WHERE block_id = ? AND is_primary = TRUE LIMIT 1",
            [$blockId]
        );
    }

    public function getBlockReviews(int $blockId): array
    {
        return Database::fetchAll(
            "SELECT rv.id, rv.rating, rv.comment, rv.created_at,
                    u.name AS user_name, u.avatar,
                    r.room_number
             FROM reviews rv
             JOIN users u ON u.id = rv.user_id
             JOIN rooms r  ON r.id = rv.room_id
             WHERE r.block_id = ?
             ORDER BY rv.created_at DESC",
            [$blockId]
        );
    }

    public function getAll(): array
    {
        return $this->getFiltered([]);
    }

    public function getFiltered(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        // Require properties to be approved for public browsing (unless admin)
        if (empty($filters['include_pending'])) {
            $where[] = 'rb.status = ?';
            $params[] = 'approved';
        }

        if (!empty($filters['type'])) {
            $where[] = 'rb.type = ?';
            $params[] = $filters['type'];
        }
        if (!empty($filters['district_id'])) {
            $where[] = 'rb.district_id = ?';
            $params[] = $filters['district_id'];
        }
        if (!empty($filters['ward_id'])) {
            $where[] = 'rb.ward_id = ?';
            $params[] = $filters['ward_id'];
        }
        if (!empty($filters['location'])) {
            $where[] = '(rb.name ILIKE ? OR rb.address ILIKE ?)';
            $params[] = '%'.$filters['location'].'%';
            $params[] = '%'.$filters['location'].'%';
        }
        if (!empty($filters['has_ac'])) {
            $where[] = 'EXISTS (SELECT 1 FROM rooms rr WHERE rr.property_id = rb.id AND rr.has_ac = TRUE)';
        }
        if (!empty($filters['has_wifi'])) {
            $where[] = 'EXISTS (SELECT 1 FROM rooms rr WHERE rr.property_id = rb.id AND rr.has_wifi = TRUE)';
        }
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'available') {
                $where[] = "EXISTS (SELECT 1 FROM rooms rr WHERE rr.property_id = rb.id AND rr.occupancy_status = 'available')";
            } elseif ($filters['status'] === 'full') {
                $where[] = "NOT EXISTS (SELECT 1 FROM rooms rr WHERE rr.property_id = rb.id AND rr.occupancy_status = 'available')";
            }
        }
        if (!empty($filters['price_min'])) {
            $where[] = '(COALESCE(rb.price_min, (SELECT MIN(price) FROM rooms WHERE property_id=rb.id AND occupancy_status=\'available\'), rb.price) >= ?)';
            $params[] = $filters['price_min'];
        }
        if (!empty($filters['price_max'])) {
            $where[] = '(COALESCE(rb.price_min, (SELECT MIN(price) FROM rooms WHERE property_id=rb.id AND occupancy_status=\'available\'), rb.price) <= ?)';
            $params[] = $filters['price_max'];
        }
        if (!empty($filters['room_count_min'])) {
            $where[] = '(SELECT COUNT(*) FROM rooms rr WHERE rr.property_id = rb.id) >= ?';
            $params[] = $filters['room_count_min'];
        }
        if (!empty($filters['room_count_max'])) {
            $where[] = '(SELECT COUNT(*) FROM rooms rr WHERE rr.property_id = rb.id) <= ?';
            $params[] = $filters['room_count_max'];
        }
        if (!empty($filters['landlord_id'])) {
            $where[] = 'rb.user_id = ?';
            $params[] = (int)$filters['landlord_id'];
        }

        $orderBy = 'rb.created_at DESC';
        if (!empty($filters['sort'])) {
            if ($filters['sort'] === 'price_asc') {
                $orderBy = 'COALESCE(rb.price_min, (SELECT MIN(price) FROM rooms WHERE property_id=rb.id AND occupancy_status=\'available\'), rb.price) ASC';
            } elseif ($filters['sort'] === 'price_desc') {
                $orderBy = 'COALESCE(rb.price_min, (SELECT MIN(price) FROM rooms WHERE property_id=rb.id AND occupancy_status=\'available\'), rb.price) DESC';
            } elseif ($filters['sort'] === 'newest') {
                $orderBy = 'rb.created_at DESC';
            }
        }

        return Database::fetchAll(
            "SELECT rb.*,
                COUNT(r.id) AS room_count,
                d.name AS district_name,
                w.name AS ward_name,
                COALESCE(
                    (SELECT image_path FROM block_images bi WHERE bi.block_id=rb.id AND bi.is_primary=TRUE LIMIT 1),
                    (SELECT image_path FROM block_images bi WHERE bi.block_id=rb.id ORDER BY bi.id ASC LIMIT 1)
                ) AS primary_image,
                COALESCE(rb.price_min, (SELECT MIN(price) FROM rooms WHERE property_id=rb.id AND occupancy_status='available'), rb.price) AS min_display_price,
                COALESCE(rb.price_max, (SELECT MAX(price) FROM rooms WHERE property_id=rb.id AND occupancy_status='available')) AS max_display_price
             FROM {$this->table} rb
             LEFT JOIN rooms r ON r.property_id = rb.id
             LEFT JOIN districts d ON d.id = rb.district_id
             LEFT JOIN wards w ON w.id = rb.ward_id
             WHERE " . implode(' AND ', $where) . "
             GROUP BY rb.id, d.name, w.name
             ORDER BY $orderBy",
            $params
        );
    }

    public function getRoomByNumber(int $propertyId, int $roomNumber): ?array
    {
        return Database::fetch(
            "SELECT r.*
             FROM rooms r
             WHERE r.property_id=? AND r.room_number=?",
            [$propertyId, $roomNumber]
        );
    }

    public function getRooms(int $blockId): array
    {
        return Database::fetchAll(
            "SELECT r.*, rt.name AS room_type_name
             FROM rooms r
             LEFT JOIN room_types rt ON rt.id = r.room_type_id
             WHERE r.property_id = ?
             ORDER BY r.room_number ASC",
            [$blockId]
        );
    }

    public function countRooms(int $blockId): int
    {
        $row = Database::fetch("SELECT COUNT(*) AS c FROM rooms WHERE property_id=?", [$blockId]);
        return (int)($row['c'] ?? 0);
    }

    /**
     * Add an image record for a block.
     */
    public function addImage(int $blockId, string $imagePath, bool $isPrimary = false, int $sortOrder = 0): void
    {
        Database::execute(
            "INSERT INTO block_images (block_id, image_path, is_primary, sort_order) VALUES (?, ?, ?, ?)",
            [$blockId, $imagePath, $isPrimary ? 1 : 0, $sortOrder]
        );
    }

    /**
     * Delete all images for a block (used before re-uploading on edit).
     */
    public function deleteImages(int $blockId): void
    {
        Database::execute("DELETE FROM block_images WHERE block_id = ?", [$blockId]);
    }
}