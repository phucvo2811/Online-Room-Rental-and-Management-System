<?php
namespace App\Middleware;
class AdminMiddleware {
    public function handle(): bool {
        if (!isset($_SESSION['user_id'])) { header('Location: ' . APP_URL . '/login'); exit; }
        if (($_SESSION['user_role'] ?? '') !== 'admin') { header('Location: ' . APP_URL . '/dashboard'); exit; }
        return true;
    }
}