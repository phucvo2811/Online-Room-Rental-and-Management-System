<?php
namespace App\Core;

use Twig\Environment;
use App\Core\Database;

abstract class BaseController
{
    protected Environment $twig;

    public function __construct()
    {
        $this->twig = Container::get('twig');

        $settingsRows = Database::fetchAll('SELECT key, value FROM settings');
        $settings     = array_reduce($settingsRows, fn($carry, $row) => array_merge($carry, [$row['key'] => $row['value']]), []);

        if (!empty($settings['site_name'])) {
            $this->twig->addGlobal('app_name', $settings['site_name']);
        }
        $this->twig->addGlobal('site_logo', $settings['site_logo'] ?? null);
    }

    protected function view(string $template, array $data = []): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8', true);
        }
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
        if (empty($_FILES[$field])) return $uploaded;

        $files = $_FILES[$field];
        // Ensure uploads are handled as arrays even if only one file is selected
        if (!is_array($files['name'])) {
            $files = [
                'name'     => [$files['name']],
                'type'     => [$files['type']],
                'tmp_name' => [$files['tmp_name']],
                'error'    => [$files['error']],
                'size'     => [$files['size']],
            ];
        }

        if (empty($files['name'][0])) return $uploaded;

        // Normalize indexes to integers (avoid string keys from $_FILES) so checks like $i === 0 work reliably.
        $names    = array_values((array)$files['name']);
        $types    = array_values((array)$files['type']);
        $tmpNames = array_values((array)$files['tmp_name']);
        $errors   = array_values((array)$files['error']);
        $sizes    = array_values((array)$files['size']);

        foreach ($names as $i => $name) {
            if (empty($name) || ($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, APP_CONFIG['allowed_ext'], true)) continue;
            if (($sizes[$i] ?? 0) > APP_CONFIG['max_file_size']) continue;
            $filename = uniqid('room_', true) . '.' . $ext;
            if (!empty($tmpNames[$i]) && move_uploaded_file($tmpNames[$i], UPLOAD_PATH . $filename)) {
                $uploaded[] = basename($filename);
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
        return basename($filename);
    }

    protected function logActivity(?int $userId, string $action, ?string $targetType = null, ?int $targetId = null, array $metadata = []): void
    {
        Database::insert(
            'INSERT INTO activity_logs (user_id, action, target_type, target_id, metadata) VALUES (?, ?, ?, ?, ?)',
            [$userId, $action, $targetType, $targetId, json_encode($metadata, JSON_UNESCAPED_UNICODE)]
        );
    }
}
