<?php
namespace App\Controllers;
use App\Core\BaseController;
use App\Models\UserModel;
use App\Models\RoomModel;

class UserController extends BaseController
{
    private UserModel $users;

    public function __construct()
    {
        parent::__construct();
        $this->users = new UserModel();
    }

    public function dashboard(): void
    {
        $user   = $this->currentUser();
        $rooms  = $user['role'] === 'landlord' ? (new RoomModel())->getByUser($user['id']) : [];
        $favs   = $this->users->getFavorites($user['id']);
        $notifs = $this->users->getNotifications($user['id']);
        $this->users->markAllRead($user['id']);
        $this->view('user/dashboard', [
            'myRooms'   => $rooms,
            'favorites' => $favs,
            'notifs'    => $notifs,
            'pageTitle' => 'Dashboard',
        ]);
    }

    public function profile(): void
    {
        $user = $this->users->findById($_SESSION['user_id']);
        $this->view('user/profile', ['user' => $user, 'pageTitle' => 'Hồ sơ của tôi']);
    }

    public function updateProfile(): void
    {
        $data   = ['name' => trim($this->post('name', '')), 'phone' => trim($this->post('phone', ''))];
        $errors = [];
        if (mb_strlen($data['name']) < 2) $errors['name'] = 'Tên quá ngắn';

        $avatar = $this->uploadSingle('avatar');
        if ($avatar) {
            $data['avatar']          = $avatar;
            $_SESSION['user_avatar'] = $avatar;
        }

        if (!empty($this->post('new_password'))) {
            $user = $this->users->findById($_SESSION['user_id']);
            if (!$this->users->verifyPassword($this->post('current_password', ''), $user['password'])) {
                $errors['current_password'] = 'Mật khẩu hiện tại không đúng';
            } elseif (strlen($this->post('new_password')) < 6) {
                $errors['new_password'] = 'Mật khẩu mới phải có ít nhất 6 ký tự';
            } else {
                $data['password'] = password_hash($this->post('new_password'), PASSWORD_BCRYPT);
            }
        }

        if (empty($errors)) {
            $this->users->update($_SESSION['user_id'], $data);
            $_SESSION['user_name'] = $data['name'];
            $this->setFlash('success', 'Cập nhật hồ sơ thành công!');
            $this->redirect('/profile');
        }
        $user = $this->users->findById($_SESSION['user_id']);
        $this->view('user/profile', ['user' => $user, 'errors' => $errors, 'pageTitle' => 'Hồ sơ']);
    }

    public function favorites(): void
    {
        $this->view('user/favorites', [
            'favorites' => $this->users->getFavorites($_SESSION['user_id']),
            'pageTitle' => 'Yêu thích',
        ]);
    }

    public function toggleFavorite(): void
    {
        $roomId = (int)$this->post('room_id', 0);
        $result = $this->users->toggleFavorite($_SESSION['user_id'], $roomId);
        $this->json(['status' => $result]);
    }
}