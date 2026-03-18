<?php
namespace App\Middleware;
class AuthMiddleware {
    public function handle(): bool {
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header('Location: ' . APP_URL . '/login'); exit;
        }
        return true;
    }
}