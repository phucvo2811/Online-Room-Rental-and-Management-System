<?php
namespace App\Core;

use Twig\Environment;

abstract class BaseController
{
    protected Environment $twig;

    public function __construct()
    {
        $this->twig = Container::get('twig');
    }

    protected function view(string $template, array $data = []): void
    {
        $data['auth'] = $this->currentUser();
        echo $this->twig->render($template . '.twig', $data);
    }

    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . APP_URL . $path);
        exit;
    }

    protected function redirectBack(): void
    {
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? APP_URL));
        exit;
    }

    protected function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    protected function requireAuth(): void
    {
        if (!$this->isLoggedIn()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            $this->redirect('/login');
        }
    }

    protected function requireRole(string ...$roles): void
    {
        $this->requireAuth();
        if (!in_array($_SESSION['user_role'] ?? '', $roles, true)) {
            $this->setFlash('danger', 'Bạn không có quyền truy cập.');
            $this->redirect('/dashboard');
        }
    }

    protected function currentUser(): ?array
    {
        if (!$this->isLoggedIn()) return null;
        return [
            'id'     => $_SESSION['user_id'],
            'name'   => $_SESSION['user_name'],
            'email'  => $_SESSION['user_email'],
            'role'   => $_SESSION['user_role'],
            'avatar' => $_SESSION['user_avatar'] ?? null,
        ];
    }

    protected function setFlash(string $type, string $message): void
    {
        $_SESSION['flash'][$type] = $message;
    }

    protected function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    protected function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    protected function only(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) $result[$key] = $this->post($key);
        return $result;
    }

    protected function generateCsrf(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    protected function uploadImages(string $field = 'images'): array
    {
        $uploaded = [];
        if (empty($_FILES[$field]['name'][0])) return $uploaded;
        $files = $_FILES[$field];
        foreach ($files['name'] as $i => $name) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, APP_CONFIG['allowed_ext'], true)) continue;
            if ($files['size'][$i] > APP_CONFIG['max_file_size'])  continue;
            $filename = uniqid('room_', true) . '.' . $ext;
            if (move_uploaded_file($files['tmp_name'][$i], UPLOAD_PATH . $filename)) {
                $uploaded[] = $filename;
            }
        }
        return $uploaded;
    }

    protected function uploadSingle(string $field = 'avatar'): ?string
    {
        if (empty($_FILES[$field]['name'])) return null;
        $file = $_FILES[$field];
        if ($file['error'] !== UPLOAD_ERR_OK) return null;
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, APP_CONFIG['allowed_ext'], true)) return null;
        if ($file['size'] > APP_CONFIG['max_file_size']) return null;
        $filename = uniqid('avatar_', true) . '.' . $ext;
        move_uploaded_file($file['tmp_name'], UPLOAD_PATH . $filename);
        return $filename;
    }
}