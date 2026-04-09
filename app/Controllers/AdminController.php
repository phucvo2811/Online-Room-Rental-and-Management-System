<?php
namespace App\Controllers;
use App\Core\BaseController;
use App\Core\Database;
use App\Models\BannerModel;
use App\Models\RoomModel;
use App\Models\RoomBlockModel;
use App\Models\UserModel;
use App\Models\PaymentModel;
use App\Models\LandlordRequestModel;

class AdminController extends BaseController
{
    private RoomModel $rooms;
    private UserModel $users;
    private BannerModel $banners;
    private RoomBlockModel $blocks;

    public function __construct()
    {
        parent::__construct();
        $this->rooms   = new RoomModel();
        $this->users   = new UserModel();
        $this->banners = new BannerModel();
        $this->blocks  = new RoomBlockModel();
    }

    public function dashboard(): void
    {
        $stats = $this->rooms->getStats();

        $userStats = Database::fetchAll(
            "SELECT
                COUNT(*) FILTER (WHERE role='tenant') AS total_users,
                COUNT(*) FILTER (WHERE role='landlord') AS total_landlords,
                COUNT(*) FILTER (WHERE role='admin') AS total_admins
             FROM users"
        );

        $proStats = Database::fetchAll(
            "SELECT
                COUNT(*) AS total_pro_orders,
                COALESCE(SUM(amount),0) AS total_pro_revenue
             FROM subscriptions
             WHERE status IN ('active','expired')"
        );

        $roomStats = Database::fetchAll(
            "SELECT
                COUNT(*) AS total_rooms,
                COUNT(*) FILTER (WHERE status='approved' AND is_available=TRUE) AS active_rooms,
                COUNT(*) FILTER (WHERE status!='approved' OR is_available=FALSE) AS hidden_rooms
             FROM rooms"
        );

        $roomGrowth = Database::fetchAll(
            "SELECT date_trunc('day', created_at)::date AS date, COUNT(*) AS count
             FROM rooms
             WHERE created_at >= NOW() - INTERVAL '30 days'
             GROUP BY date
             ORDER BY date"
        );

        $proRevenueGrowth = Database::fetchAll(
            "SELECT date_trunc('month', starts_at)::date AS month, COALESCE(SUM(amount),0) AS revenue
             FROM subscriptions
             WHERE starts_at >= NOW() - INTERVAL '12 months'
             GROUP BY month
             ORDER BY month"
        );

        $this->view('admin/dashboard', [
            'stats'               => $stats,
            'user_stats'          => $userStats[0] ?? [],
            'pro_stats'           => $proStats[0] ?? [],
            'room_stats'          => $roomStats[0] ?? [],
            'room_growth'         => $roomGrowth,
            'pro_revenue_growth'  => $proRevenueGrowth,
            'pending'             => $this->rooms->getPending(),
            'pageTitle'           => 'Admin Dashboard',
        ]);
    }

    public function rooms(): void
    {
        $status   = $this->get('status', 'pending');
        $filters  = ['moderation_status' => $status];
        if ($this->get('spam') === '1') {
            $filters['is_spam'] = true;
        }

        $rooms = $this->rooms->getRoomsForAdmin($filters);

        $this->view('admin/rooms', [
            'rooms'         => $rooms,
            'currentStatus' => $status,
            'stats'         => $this->rooms->getStats(),
            'pageTitle'     => 'Quản lý phòng trọ',
        ]);
    }

    public function posts(): void
    {
        $status = $this->get('status', 'inactive');
        $postModel = new \App\Models\PostModel();
        $posts = $postModel->getAllByStatus($status);

        $this->view('admin/posts', [
            'posts' => $posts,
            'currentStatus' => $status,
            'pageTitle' => 'Duyệt bài đăng',
        ]);
    }

    public function approvePost(string $id): void
    {
        $postModel = new \App\Models\PostModel();
        $postModel->update((int)$id, ['status' => 'active']);

        $post = $postModel->getById((int)$id);
        if ($post) {
            (new \App\Models\UserModel())->addNotification($post['user_id'], 'Bài đăng đã được duyệt', "Bài đăng '{$post['title']}' đã được duyệt.", 'success');
        }

        $this->setFlash('success', 'Đã duyệt bài đăng.');
        $this->redirect('/admin/posts?status=inactive');
    }

    public function rejectPost(string $id): void
    {
        $postModel = new \App\Models\PostModel();
        $postModel->update((int)$id, ['status' => 'inactive']);

        $post = $postModel->getById((int)$id);
        if ($post) {
            (new \App\Models\UserModel())->addNotification($post['user_id'], 'Bài đăng bị từ chối', "Bài đăng '{$post['title']}' đã bị từ chối.", 'danger');
        }

        $this->setFlash('warning', 'Đã từ chối bài đăng.');
        $this->redirect('/admin/posts?status=inactive');
    }

    public function approveRoom(string $id): void
    {
        $this->rooms->setModerationStatus((int)$id, 'approved');
        $room = $this->rooms->getByIdWithHistory((int)$id);
        if ($room) {
            $this->users->addNotification($room['user_id'], 'Tin đăng đã được duyệt ✅',
                "Tin \"{$room['title']}\" đã được duyệt.", 'success');
            $this->logActivity($room['user_id'], 'approve_room', 'room', (int)$id, ['room_title' => $room['title']]);
        }
        $this->setFlash('success', 'Đã duyệt tin thành công!');
        $this->redirect('/admin/rooms?status=pending');
    }

    public function rejectRoom(string $id): void
    {
        $reason = trim($this->post('reason', 'Không đáp ứng yêu cầu'));
        $this->rooms->setModerationStatus((int)$id, 'rejected');
        $room = $this->rooms->getByIdWithHistory((int)$id);
        if ($room) {
            $this->users->addNotification($room['user_id'], 'Tin đăng bị từ chối ❌',
                "Tin \"{$room['title']}\" bị từ chối. Lý do: $reason", 'danger');
            $this->logActivity($room['user_id'], 'reject_room', 'room', (int)$id, ['reason'=>$reason]);
        }
        $this->setFlash('warning', 'Đã từ chối tin đăng.');
        $this->redirect('/admin/rooms?status=pending');
    }

    public function hideRoom(string $id): void
    {
        $this->rooms->setModerationStatus((int)$id, 'hidden');
        $room = $this->rooms->getByIdWithHistory((int)$id);
        if ($room) {
            $this->logActivity($room['user_id'], 'hide_room', 'room', (int)$id, ['title'=>$room['title']]);
        }
        $this->setFlash('success', 'Đã ẩn tin thành công.');
        $this->redirect('/admin/rooms?status=hidden');
    }

    public function properties(): void
    {
        $status = $this->get('status', 'pending');
        $properties = Database::fetchAll(
            "SELECT rb.*, u.name AS owner_name, u.email AS owner_email
             FROM room_blocks rb
             JOIN users u ON u.id = rb.user_id
             WHERE rb.status = ?
             ORDER BY rb.created_at DESC",
            [$status]
        );
        $pendingCount = Database::fetch("SELECT COUNT(*) AS cnt FROM room_blocks WHERE status = 'pending'")['cnt'] ?? 0;

        $this->view('admin/properties', [
            'properties'   => $properties,
            'currentStatus'=> $status,
            'pendingCount' => $pendingCount,
            'pageTitle'    => 'Duyệt Bất động sản',
            'csrf'         => $this->generateCsrf(),
        ]);
    }

    public function approveProperty(string $id): void
    {
        Database::execute("UPDATE room_blocks SET status = 'approved', updated_at = NOW() WHERE id = ?", [(int)$id]);
        $block = $this->blocks->findById((int)$id);
        if ($block) {
            $this->users->addNotification($block['user_id'], 'Bất động sản đã được duyệt ✅',
                "Bất động sản \"{$block['name']}\" đã được phê duyệt. Bạn có thể đăng tin ngay.", 'success');
        }
        $this->setFlash('success', 'Đã duyệt bất động sản.');
        $this->redirect('/admin/properties?status=pending');
    }

    public function rejectProperty(string $id): void
    {
        $reason = trim($this->post('reason', 'Không đáp ứng yêu cầu'));
        Database::execute("UPDATE room_blocks SET status = 'rejected', updated_at = NOW() WHERE id = ?", [(int)$id]);
        $block = $this->blocks->findById((int)$id);
        if ($block) {
            $this->users->addNotification($block['user_id'], 'Bất động sản bị từ chối ❌',
                "Bất động sản \"{$block['name']}\" bị từ chối. Lý do: $reason", 'danger');
        }
        $this->setFlash('warning', 'Đã từ chối bất động sản.');
        $this->redirect('/admin/properties?status=pending');
    }

    public function previewProperty(string $id): void
    {
        $block = $this->blocks->getById((int)$id);
        if (!$block) {
            $this->setFlash('danger', 'Bất động sản không tồn tại.');
            $this->redirect('/admin/properties');
        }

        $rooms = $this->blocks->getRooms((int)$id);

        $selectedRoom = null;
        foreach ($rooms as $room) {
            if (($room['occupancy_status'] ?? 'available') === 'available') {
                $selectedRoom = $room;
                break;
            }
        }
        if (!$selectedRoom && !empty($rooms)) {
            $selectedRoom = $rooms[0];
        }

        $selectedRoomImages = [];
        if (!empty($selectedRoom['id'])) {
            $selectedRoomImages = (new RoomModel())->getImages((int)$selectedRoom['id']);
        }

        $this->view('room/property_detail', [
            'block'              => $block,
            'rooms'              => $rooms,
            'selectedRoom'       => $selectedRoom,
            'selectedRoomImages' => $selectedRoomImages,
            'blockImages'        => $this->blocks->getImages((int)$id),
            'similar'            => [],
            'pageTitle'          => '[Xem trước] ' . $block['name'],
            'admin_preview'      => true,
            'csrf'               => $this->generateCsrf(),
        ]);
    }


    public function users(): void
    {
        $filters = [
            'role'   => $this->get('role', ''),
            'status' => $this->get('status', ''),
            'keyword'=> $this->get('keyword', ''),
        ];

        $users = $this->users->getAdminUsers($filters);

        $this->view('admin/users', [
            'users'     => $users,
            'stats'     => $this->rooms->getStats(),
            'pageTitle' => 'Quản lý người dùng',
        ]);
    }

    public function userDetail(string $id): void
    {
        $user = $this->users->findById((int)$id);
        if (!$user) {
            $this->setFlash('danger', 'Người dùng không tồn tại.');
            $this->redirect('/admin/users');
        }

        $userActivity = $this->users->getActivityLog((int)$id, 50, 0);
        $userRooms    = (new RoomModel())->getByUser((int)$id);

        $this->view('admin/user_detail', [
            'user'         => $user,
            'activity'     => $userActivity,
            'rooms'        => $userRooms,
            'pageTitle'    => 'Chi tiết người dùng',
        ]);
    }

    public function toggleUserStatus(string $id): void
    {
        $user = $this->users->findById((int)$id);
        if ($user && $user['role'] !== 'admin') {
            $newStatus = $user['status'] === 'banned' ? 'active' : 'banned';
            $this->users->setStatus((int)$id, $newStatus);
            $this->logActivity($_SESSION['user_id'], 'toggle_user_status', 'user', (int)$id, ['status' => $newStatus]);
            $this->setFlash('success', 'Đã cập nhật trạng thái người dùng.');
        }
        $this->redirect('/admin/users');
    }

    public function setUserRole(string $id): void
    {
        $role = $this->post('role');
        if (!in_array($role, ['tenant','landlord','admin'], true)) {
            $this->setFlash('danger', 'Vai trò không hợp lệ.');
            $this->redirect('/admin/users');
        }
        $this->users->setRole((int)$id, $role);
        $this->logActivity($_SESSION['user_id'], 'set_user_role', 'user', (int)$id, ['role' => $role]);
        $this->setFlash('success', 'Đã cập nhật vai trò.');
        $this->redirect('/admin/users');
    }

    public function toggleTrustedLandlord(string $id): void
    {
        $user = $this->users->findById((int)$id);
        if ($user && $user['role'] === 'landlord') {
            $newTrusted = !$user['is_trusted_landlord'];
            $this->users->setTrustedLandlord((int)$id, $newTrusted);
            $this->logActivity($_SESSION['user_id'], 'toggle_trusted_landlord', 'user', (int)$id, ['trusted' => $newTrusted]);
            $this->setFlash('success', 'Đã cập nhật trạng thái trusted landlord.');
        }
        $this->redirect('/admin/users');
    }

    public function banUser(string $id): void
    {
        if ((int)$id === $_SESSION['user_id']) {
            $this->setFlash('danger', 'Không thể khóa tài khoản của chính mình!');
            $this->redirect('/admin/users');
        }
        $user = $this->users->findById((int)$id);
        if ($user) {
            $newStatus = $user['status'] === 'banned' ? 'active' : 'banned';
            $this->users->update((int)$id, ['status' => $newStatus]);
            $this->setFlash('success', $newStatus === 'banned' ? 'Đã khóa.' : 'Đã mở khóa.');
        }
        $this->redirect('/admin/users');
    }

    public function contacts(): void
    {
        $contacts = Database::fetchAll(
            "SELECT c.*, r.title AS room_title, r.id AS room_id
             FROM contacts c JOIN rooms r ON c.room_id=r.id
             ORDER BY c.created_at DESC"
        );
        $this->view('admin/contacts', [
            'contacts'  => $contacts,
            'stats'     => $this->rooms->getStats(),
            'pageTitle' => 'Tin nhắn liên hệ',
        ]);
    }

    public function payments(): void
    {
        $model    = new PaymentModel();
        $payments = $model->getAll(100);
        $stats    = $model->stats();

        $this->view('admin/payments', [
            'payments'   => $payments,
            'stats'      => $stats,
            'page_stats' => $this->rooms->getStats(),
        ]);
    }

    public function pro(): void
    {
        $subscriptions = Database::fetchAll(
            "SELECT s.*, u.name AS user_name, u.email AS user_email
             FROM subscriptions s
             LEFT JOIN users u ON s.user_id=u.id
             ORDER BY s.starts_at DESC"
        );

        $revenue = Database::fetchAll(
            "SELECT date_trunc('month', starts_at) AS month, COALESCE(SUM(amount),0) AS revenue
             FROM subscriptions
             GROUP BY month ORDER BY month DESC LIMIT 12"
        );

        $active = Database::fetch("SELECT COUNT(*) AS c FROM subscriptions WHERE status='active'")[ 'c'] ?? 0;
        $expired = Database::fetch("SELECT COUNT(*) AS c FROM subscriptions WHERE status='expired'")[ 'c'] ?? 0;

        $settings = Database::fetchAll('SELECT key, value FROM settings');
        $settings = array_reduce($settings, fn($carry, $row) => array_merge($carry, [$row['key'] => $row['value']]), []);

        $this->view('admin/pro', [
            'subscriptions' => $subscriptions,
            'revenue'       => $revenue,
            'active_count'  => $active,
            'expired_count' => $expired,
            'settings'      => $settings,
            'stats'         => $this->rooms->getStats(),
            'pageTitle'     => 'Quản lý PRO',
        ]);
    }

    public function updateProSettings(): void
    {
        $pro7_price  = (float)$this->post('pro_7_price', 0);
        $pro30_price = (float)$this->post('pro_30_price', 0);

        Database::execute("INSERT INTO settings (key,value) VALUES ('pro_7_price',?) ON CONFLICT (key) DO UPDATE SET value=EXCLUDED.value", [$pro7_price]);
        Database::execute("INSERT INTO settings (key,value) VALUES ('pro_30_price',?) ON CONFLICT (key) DO UPDATE SET value=EXCLUDED.value", [$pro30_price]);

        $this->setFlash('success', 'Cập nhật giá gói PRO thành công.');
        $this->redirect('/admin/pro');
    }

    public function settings(): void
    {
        $settingsRows = Database::fetchAll('SELECT key, value FROM settings');
        $settings     = array_reduce($settingsRows, fn($carry, $row) => array_merge($carry, [$row['key'] => $row['value']]), []);

        $this->view('admin/settings', [
            'settings'  => $settings,
            'stats'     => $this->rooms->getStats(),
            'pageTitle' => 'Cài đặt hệ thống',
            'active_page' => 'settings',
        ]);
    }

    public function updateSettings(): void
    {
        $site_name = trim($this->post('site_name', ''));

        if ($site_name !== '') {
            Database::execute("INSERT INTO settings (key,value) VALUES ('site_name',?) ON CONFLICT (key) DO UPDATE SET value=EXCLUDED.value", [$site_name]);
        }

        $uploaded = $this->uploadSingle('site_logo');
        if ($uploaded) {
            $logoName = basename($uploaded);
            $subpath  = 'branding/' . $logoName;
            $destDir  = UPLOAD_PATH . 'branding/';
            if (!is_dir($destDir)) mkdir($destDir, 0755, true);
            @rename(UPLOAD_PATH . $logoName, $destDir . $logoName);
            Database::execute("INSERT INTO settings (key,value) VALUES ('site_logo',?) ON CONFLICT (key) DO UPDATE SET value=EXCLUDED.value", [$subpath]);
        }

        $this->setFlash('success', 'Cập nhật cài đặt trang thành công.');
        $this->redirect('/admin/settings');
    }

    public function banners(): void
    {
        $this->view('admin/banners', [
            'banners'   => $this->banners->getAll(),
            'stats'     => $this->rooms->getStats(),
            'pageTitle' => 'Quản lý banner',
        ]);
    }
    public function createBanner(): void
    {
        $link   = trim($this->post('link', ''));
        $status = $this->post('status', 'active') === 'inactive' ? 'inactive' : 'active';
        $order  = (int)$this->post('order');

        $image = $this->uploadSingle('image');
        if (!$image) {
            $this->setFlash('danger', 'Vui lòng chọn ảnh banner hợp lệ.');
            $this->redirect('/admin/banners');
        }

        $image = basename($image);
        $subpath = 'banners/' . $image;
        $destDir = UPLOAD_PATH . 'banners/';
        if (!is_dir($destDir)) mkdir($destDir, 0755, true);
        @rename(UPLOAD_PATH . $image, $destDir . $image);

        if ($order <= 0) {
            $row   = Database::fetch('SELECT COALESCE(MAX("order"),0) AS max_order FROM banners');
            $order = (int)($row['max_order'] ?? 0) + 1;
        }

        $this->banners->create([
            'image_url' => $subpath,
            'link'      => $link ?: null,
            'status'    => $status,
            '"order"' => $order,
        ]);

        $this->setFlash('success', 'Đã thêm banner mới.');
        $this->redirect('/admin/banners');
    }

    public function editBanner(string $id): void
    {
        $banner = $this->banners->findById((int)$id);
        if (!$banner) {
            $this->setFlash('danger', 'Banner không tồn tại.');
            $this->redirect('/admin/banners');
        }
        $this->view('admin/banners', [
            'banners'   => $this->banners->getAll(),
            'editing'   => $banner,
            'stats'     => $this->rooms->getStats(),
            'pageTitle' => 'Chỉnh sửa banner',
        ]);
    }

    public function updateBanner(string $id): void
    {
        $banner = $this->banners->findById((int)$id);
        if (!$banner) {
            $this->setFlash('danger', 'Banner không tồn tại.');
            $this->redirect('/admin/banners');
        }

        $link   = trim($this->post('link_url', ''));
        $status = $this->post('status', 'active') === 'inactive' ? 'inactive' : 'active';
        $order  = (int)$this->post('order');

        $data = [
            'link_url'      => $link ?: null,
            'status'    => $status,
            '"order"' => $order > 0 ? $order : (int)$banner['order'],
        ];

        $newImage = $this->uploadSingle('image');
        if ($newImage) {
            $newImage = basename($newImage);
            $subpath  = 'banners/' . $newImage;
            $destDir  = UPLOAD_PATH . 'banners/';
            if (!is_dir($destDir)) mkdir($destDir, 0755, true);
            @rename(UPLOAD_PATH . $newImage, $destDir . $newImage);

            $data['image_url'] = $subpath;

            // Remove old file if it exists (normalize stored paths that may include /uploads/ prefix).
            $oldPathRelative = ltrim($banner['image_url'], '/\\');
            $oldPathRelative = preg_replace('#^(public/)?uploads/#i', '', $oldPathRelative);
            $oldPath = UPLOAD_PATH . $oldPathRelative;
            if (file_exists($oldPath)) unlink($oldPath);
        }

        $this->banners->update((int)$id, $data);
        $this->setFlash('success', 'Đã cập nhật banner.');
        $this->redirect('/admin/banners');
    }

    public function updateBannerOrder(): void
    {
        $orders = $this->post('order', []);
        if (is_array($orders)) {
            foreach ($orders as $id => $value) {
                $id    = (int)$id;
                $order = (int)$value;
                if ($id > 0 && $order >= 0) {
                    $this->banners->update($id, ['"order"' => $order]);
                }
            }
        }
        $this->setFlash('success', 'Đã cập nhật thứ tự banner.');
        $this->redirect('/admin/banners');
    }

    public function deleteBanner(string $id): void
    {
        $banner = $this->banners->findById((int)$id);
        if ($banner) {
            $path = UPLOAD_PATH . $banner['image_url'];
            if (file_exists($path)) unlink($path);
            $this->banners->delete((int)$id);
            $this->setFlash('success', 'Đã xóa banner.');
        }
        $this->redirect('/admin/banners');
    }

    public function toggleBanner(string $id): void
    {
        $banner = $this->banners->findById((int)$id);
        if ($banner) {
            $newStatus = $banner['status'] === 'active' ? 'inactive' : 'active';
            $this->banners->update((int)$id, ['status' => $newStatus]);
            $this->setFlash('success', 'Đã ' . ($newStatus === 'active' ? 'hiển thị' : 'ẩn') . ' banner.');
        }
        $this->redirect('/admin/banners');
    }

    // ── Landlord Requests ─────────────────────────────────────────────────

    /**
     * List landlord upgrade requests.
     * GET /admin/landlord-requests
     */
    public function landlordRequests(): void
    {
        $status = $this->get('status', 'pending');
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $status = 'pending';
        }

        $model    = new LandlordRequestModel();
        $requests = $model->getAllForAdmin($status);

        $this->view('admin/landlord-requests', [
            'requests'      => $requests,
            'currentStatus' => $status,
            'pendingCount'  => $model->countPending(),
            'stats'         => $this->rooms->getStats(),
            'pageTitle'     => 'Yêu cầu làm chủ trọ',
            'csrf'          => $this->generateCsrf(),
        ]);
    }

    public function approveLandlord(string $id): void
    {
        $model   = new LandlordRequestModel();
        $request = $model->findById((int)$id);

        if (!$request || $request['status'] !== 'pending') {
            $this->setFlash('danger', 'Yêu cầu không tồn tại hoặc đã được xử lý.');
            $this->redirect('/admin/landlord-requests');
        }

        $model->approve((int)$id, $_SESSION['user_id']);

        // Elevate role and mark approved
        $this->users->update($request['user_id'], [
            'role'            => 'landlord',
            'landlord_status' => 'approved',
        ]);

        $this->users->addNotification(
            $request['user_id'],
            'Yêu cầu làm chủ trọ đã được duyệt ✅',
            'Chúc mừng! Tài khoản của bạn đã được nâng cấp thành Chủ trọ. Bạn có thể bắt đầu đăng tin phòng ngay bây giờ.',
            'success'
        );

        $this->logActivity($_SESSION['user_id'], 'approve_landlord_request', 'user', $request['user_id']);
        $this->setFlash('success', 'Đã duyệt yêu cầu và nâng cấp tài khoản thành Chủ trọ.');
        $this->redirect('/admin/landlord-requests?status=pending');
    }

    /**
     * Reject a landlord request.
     * POST /admin/landlord-requests/{id}/reject
     */
    public function rejectLandlord(string $id): void
    {
        $model   = new LandlordRequestModel();
        $request = $model->findById((int)$id);

        if (!$request || $request['status'] !== 'pending') {
            $this->setFlash('danger', 'Yêu cầu không tồn tại hoặc đã được xử lý.');
            $this->redirect('/admin/landlord-requests');
        }

        $reason = trim($this->post('reason', 'Không đáp ứng yêu cầu'));

        $model->reject((int)$id, $_SESSION['user_id'], $reason);

        // Reset so the user can re-apply
        $this->users->update($request['user_id'], ['landlord_status' => 'rejected']);

        $this->users->addNotification(
            $request['user_id'],
            'Yêu cầu làm chủ trọ bị từ chối ❌',
            "Yêu cầu đăng ký làm chủ trọ của bạn đã bị từ chối. Lý do: {$reason}. Bạn có thể gửi lại yêu cầu mới.",
            'danger'
        );

        $this->logActivity($_SESSION['user_id'], 'reject_landlord_request', 'user', $request['user_id'], ['reason' => $reason]);
        $this->setFlash('warning', 'Đã từ chối yêu cầu.');
        $this->redirect('/admin/landlord-requests?status=pending');
    }
}