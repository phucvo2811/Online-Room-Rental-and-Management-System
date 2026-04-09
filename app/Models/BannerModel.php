<?php
namespace App\Models;

use App\Core\BaseModel;
use App\Core\Database;

class BannerModel extends BaseModel
{
    protected string $table = 'banners';

    /**
     * Get active banners ordered by display order ascending.
     *
     * @return array<array>
     */
    public function getActive(): array
    {
        return array_map([$this, 'normalize'], Database::fetchAll(
            "SELECT * FROM {$this->table} WHERE status='active' ORDER BY \"order\" ASC"
        ));
    }

    /**
     * Get all banners ordered by display order.
     *
     * @return array<array>
     */
    public function getAll(): array
    {
        return array_map([$this, 'normalize'], Database::fetchAll(
            "SELECT * FROM {$this->table} ORDER BY \"order\" ASC"
        ));
    }

    private function normalize(array $row): array
    {
        if (empty($row['image_url'])) {
            return $row;
        }

        // Normalize stored image paths to be relative to the uploads directory.
        // The view layer will prepend UPLOAD_URL.
        $imageUrl = trim((string) $row['image_url']);
        $imageUrl = str_replace('\\', '/', $imageUrl);
        $imageUrl = ltrim($imageUrl, '/');

        // Strip any leading upload directory prefixes (e.g. "uploads/" or "public/uploads/").
        $imageUrl = preg_replace('#^(public/)?uploads/#i', '', $imageUrl);

        // If only a filename is stored, treat it as being in the banners/ folder.
        if (!str_contains($imageUrl, '/')) {
            $imageUrl = 'banners/' . basename($imageUrl);
        }

        $row['image_url'] = $imageUrl;
        return $row;
    }

    public function updateOrder(int $id, int $order): int
    {
        return $this->update($id, ['"order"' => $order]);
    }
}
