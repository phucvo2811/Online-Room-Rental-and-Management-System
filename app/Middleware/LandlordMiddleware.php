<?php
namespace App\Middleware;
class LandlordMiddleware {
    public function handle(): bool {
        if (!isset($_SESSION['user_id'])) { header('Location: ' . APP_URL . '/login'); exit; }
        if (!in_array($_SESSION['user_role'] ?? '', ['landlord','admin'], true)) {
            $_SESSION['flash']['danger'] = 'Chỉ chủ trọ mới có thể đăng tin.';
            header('Location: ' . APP_URL . '/dashboard'); exit;
        }
        return true;
    }
}