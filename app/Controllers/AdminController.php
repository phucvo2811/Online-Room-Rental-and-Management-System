<?php
namespace App\Controllers;
use App\Core\BaseController;
use App\Core\Database;
use App\Models\RoomModel;
use App\Models\UserModel;

class AdminController extends BaseController
{
    private RoomModel $rooms;
    private UserModel $users;

    public function __construct()
    {
        parent::__construct();
        $this->rooms = new RoomModel();
        $this->users = new UserModel();
    }

    public function dashboard(): void
    {
        $this->view('admin/dashboard', [
            'stats'     => $this->rooms->getStats(),
            'pending'   => $this->rooms->getPending(),
            'pageTitle' => 'Admin Dashboard',
        ]);
    }

    public function rooms(): void
    {
        $status = $this->get('status', 'pending');
        $rooms  = Database::fetchAll(
            "SELECT r.*, u.name AS landlord_name, u.phone AS landlord_phone,
                d.name AS district_name,
                (SELECT image_path FROM room_images ri WHERE ri.room_id=r.id AND ri.is_primary=TRUE LIMIT 1) AS primary_image
             FROM rooms r
             LEFT JOIN users u ON r.user_id=u.id
             LEFT JOIN districts d ON r.district_id=d.id
             WHERE r.status=? ORDER BY r.created_at DESC",
            [$status]
        );
        $this->view('admin/rooms', [
            'rooms'         => $rooms,
            'currentStatus' => $status,
            'stats'         => $this->rooms->getStats(),
            'pageTitle'     => 'Quản lý phòng trọ',
        ]);
    }

    public function approveRoom(string $id): void
    {
        $room = $this->rooms->findById((int)$id);
        if ($room) {
            $this->rooms->update((int)$id, ['status' => 'approved']);
            $this->users->addNotification($room['user_id'], 'Tin đăng được duyệt ✅',
                "Tin \"{$room['title']}\" đã được duyệt.", 'success');
        }
        $this->setFlash('success', 'Đã duyệt tin thành công!');
        $this->redirect('/admin/rooms?status=pending');
    }

    public function rejectRoom(string $id): void
    {
        $room   = $this->rooms->findById((int)$id);
        $reason = trim($this->post('reason', 'Không đáp ứng yêu cầu'));
        if ($room) {
            $this->rooms->update((int)$id, ['status' => 'rejected']);
            $this->users->addNotification($room['user_id'], 'Tin đăng bị từ chối ❌',
                "Tin \"{$room['title']}\" bị từ chối. Lý do: $reason", 'danger');
        }
        $this->setFlash('warning', 'Đã từ chối tin đăng.');
        $this->redirect('/admin/rooms?status=pending');
    }

    public function users(): void
    {
        $users = Database::fetchAll(
            "SELECT u.*, (SELECT COUNT(*) FROM rooms WHERE user_id=u.id) AS room_count
             FROM users u ORDER BY u.created_at DESC"
        );
        $this->view('admin/users', [
            'users'     => $users,
            'stats'     => $this->rooms->getStats(),
            'pageTitle' => 'Quản lý người dùng',
        ]);
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
}