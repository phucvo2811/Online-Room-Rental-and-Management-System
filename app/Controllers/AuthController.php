<?php
namespace App\Controllers;
use App\Core\BaseController;
use App\Models\UserModel;

class AuthController extends BaseController
{
    private UserModel $users;

    public function __construct()
    {
        parent::__construct();
        $this->users = new UserModel();
    }

    public function loginForm(): void
    {
        if ($this->isLoggedIn()) $this->redirect('/dashboard');
        $this->view('auth/login', ['pageTitle' => 'Đăng nhập']);
    }

    public function login(): void
    {
        $email    = trim($this->post('email', ''));
        $password = $this->post('password', '');
        $errors   = [];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email']    = 'Email không hợp lệ';
        if (empty($password))                           $errors['password'] = 'Vui lòng nhập mật khẩu';

        if (empty($errors)) {
            $user = $this->users->findByEmail($email);
            if ($user && $this->users->verifyPassword($password, $user['password'])) {
                if ($user['status'] === 'banned') {
                    $errors['general'] = 'Tài khoản đã bị khóa.';
                } else {
                    $_SESSION['user_id']     = $user['id'];
                    $_SESSION['user_name']   = $user['name'];
                    $_SESSION['user_email']  = $user['email'];
                    $_SESSION['user_role']   = $user['role'];
                    $_SESSION['user_avatar'] = $user['avatar'];
                    $redirect = $_SESSION['redirect_after_login'] ?? null;
                    unset($_SESSION['redirect_after_login']);
                    $this->redirect($user['role'] === 'admin' ? '/admin' : ($redirect ? '' : '/dashboard'));
                }
            } else {
                $errors['general'] = 'Email hoặc mật khẩu không đúng.';
            }
        }
        $this->view('auth/login', ['errors' => $errors, 'old' => ['email' => $email], 'pageTitle' => 'Đăng nhập']);
    }

    public function registerForm(): void
    {
        if ($this->isLoggedIn()) $this->redirect('/dashboard');
        $this->view('auth/register', ['pageTitle' => 'Đăng ký']);
    }

    public function register(): void
    {
        $data   = $this->only(['name','email','phone','password','confirm_password','role']);
        $errors = [];

        if (mb_strlen($data['name'] ?? '') < 2)                       $errors['name']     = 'Tên phải có ít nhất 2 ký tự';
        if (!filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) $errors['email']    = 'Email không hợp lệ';
        if ($this->users->findByEmail($data['email'] ?? ''))          $errors['email']    = 'Email đã được sử dụng';
        if (strlen($data['password'] ?? '') < 6)                      $errors['password'] = 'Mật khẩu tối thiểu 6 ký tự';
        if ($data['password'] !== $data['confirm_password'])          $errors['confirm']  = 'Mật khẩu không khớp';

        if (empty($errors)) {
            $this->users->register([
                'name'     => trim($data['name']),
                'email'    => trim($data['email']),
                'phone'    => trim($data['phone'] ?? ''),
                'password' => $data['password'],
                'role'     => in_array($data['role'], ['landlord','tenant']) ? $data['role'] : 'tenant',
            ]);
            $this->setFlash('success', 'Đăng ký thành công! Vui lòng đăng nhập.');
            $this->redirect('/login');
        }
        $this->view('auth/register', ['errors' => $errors, 'old' => $data, 'pageTitle' => 'Đăng ký']);
    }

    public function logout(): void
    {
        session_destroy();
        $this->redirect('/');
    }
}