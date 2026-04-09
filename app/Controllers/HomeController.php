<?php
namespace App\Controllers;
use App\Core\BaseController;
use App\Core\Database;
use App\Models\BannerModel;
use App\Models\LocationModel;
use App\Models\RoomBlockModel;
use App\Models\RoomModel;

class HomeController extends BaseController
{
    public function index(): void
    {
        $roomModel     = new RoomModel();
        $bannerModel   = new BannerModel();
        $blockModel    = new RoomBlockModel();
        $locationModel = new LocationModel();

        // Count new approved listings today
        $todayNew = (int)(Database::fetch(
            "SELECT COUNT(*) AS c FROM room_blocks WHERE status='approved' AND created_at::date = CURRENT_DATE"
        )['c'] ?? 0);

        // Top landlords by listing count
        $topLandlords = Database::fetchAll(
            "SELECT u.id, u.name, u.avatar,
                    COUNT(DISTINCT rb.id) AS listing_count,
                    COALESCE(ROUND(AVG(rev.rating)::numeric,1), 5.0) AS avg_rating
             FROM users u
             JOIN room_blocks rb ON rb.user_id = u.id AND rb.status = 'approved'
             LEFT JOIN rooms r ON r.property_id = rb.id
             LEFT JOIN reviews rev ON rev.room_id = r.id
             WHERE u.role IN ('landlord','admin') AND u.status = 'active'
             GROUP BY u.id, u.name, u.avatar
             ORDER BY listing_count DESC, avg_rating DESC
             LIMIT 6"
        );

        // Wards with listing counts (sorted by engagement for carousel)
        $wardStats = Database::fetchAll(
            "SELECT w.id, w.name, d.name AS district_name, d.id AS district_id,
                    COUNT(rb.id) AS listing_count
             FROM wards w
             JOIN districts d ON w.district_id = d.id
             LEFT JOIN room_blocks rb ON rb.ward_id = w.id AND rb.status = 'approved'
             GROUP BY w.id, w.name, d.name, d.id
             ORDER BY listing_count DESC, w.name ASC"
        );

        $featured = array_slice($blockModel->getFiltered([]), 0, 8);

        // Add computed badge flags to each featured block
        $now = time();
        foreach ($featured as &$block) {
            $createdAt = isset($block['created_at']) ? strtotime($block['created_at']) : $now;
            $block['is_new'] = ($now - $createdAt) < (7 * 86400);
            $block['is_hot'] = !empty($block['min_display_price']) && $block['min_display_price'] < 2000000;
        }
        unset($block);

        $this->view('home/index', [
            'banners'        => $bannerModel->getActive(),
            'featured'       => $featured,
            'stats'          => $roomModel->getStats(),
            'districts'      => $locationModel->getAllDistricts(),
            'ward_stats'     => $wardStats,
            'top_landlords'  => $topLandlords,
            'today_new'      => $todayNew,
            'blocks'         => $blockModel->getAll(),
            'pageTitle'      => 'Trang chủ',
        ]);
    }
}
