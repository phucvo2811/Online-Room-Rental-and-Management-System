<?php
define('ROOT', dirname(__DIR__));

require_once ROOT . '/vendor/autoload.php';

$appCfg = require ROOT . '/config/app.php';
$dbCfg  = require ROOT . '/config/database.php';

foreach ($appCfg as $k => $v) {
    if (!is_array($v)) define('APP_' . strtoupper($k), $v);
}
define('APP_CONFIG',     $appCfg);
define('UPLOAD_PATH',    $appCfg['upload_path']);
define('UPLOAD_URL',     APP_URL . $appCfg['upload_url']);
define('ITEMS_PER_PAGE', $appCfg['items_per_page']);
define('ROOM_TYPES',     $appCfg['room_types']);
define('ROOM_STATUS',    $appCfg['room_status']);

date_default_timezone_set($appCfg['timezone']);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    session_set_cookie_params($appCfg['session_lifetime']);
    session_start();
}

\App\Core\Database::init($dbCfg);

$loader = new \Twig\Loader\FilesystemLoader(ROOT . '/resources/views');
$twig   = new \Twig\Environment($loader, [
    'cache'       => false,
    'debug'       => $appCfg['debug'],
    'auto_reload' => true,
]);

if ($appCfg['debug']) {
    $twig->addExtension(new \Twig\Extension\DebugExtension());
}

$twig->addGlobal('app_name',           $appCfg['name']);
$twig->addGlobal('app_url',            APP_URL);
$twig->addGlobal('upload_url',         UPLOAD_URL);
$twig->addGlobal('room_types',         ROOM_TYPES);
$twig->addGlobal('room_status',        ROOM_STATUS);
$twig->addGlobal('session',            $_SESSION);
$twig->addGlobal('current_year',       date('Y'));
$twig->addGlobal('google_maps_api_key', $appCfg['google_maps_api_key'] ?? '');
define('GEMINI_API_KEY', $appCfg['gemini_api_key'] ?? '');

// VNPay constants
define('VNP_TMN_CODE',    $appCfg['vnp_tmn_code']    ?? '');
define('VNP_HASH_SECRET', $appCfg['vnp_hash_secret'] ?? '');
define('VNP_URL',         $appCfg['vnp_url']         ?? 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');
define('VNP_RETURN_URL',  !empty($appCfg['vnp_return_url']) ? $appCfg['vnp_return_url'] : (APP_URL . '/payment/return'));

$twig->addFilter(new \Twig\TwigFilter('money', function (float $n): string {
    return number_format($n, 0, ',', '.') . 'đ';
}));

$twig->addFilter(new \Twig\TwigFilter('truncate', function (string $s, int $len = 80): string {
    return mb_strlen($s) > $len ? mb_substr($s, 0, $len) . '...' : $s;
}));

$twig->addFilter(new \Twig\TwigFilter('time_ago', function (string $dt): string {
    $diff = (new DateTime())->diff(new DateTime($dt));
    if ($diff->days === 0) return $diff->h === 0 ? $diff->i . ' phút trước' : $diff->h . ' giờ trước';
    if ($diff->days < 7)   return $diff->days . ' ngày trước';
    if ($diff->days < 30)  return floor($diff->days / 7) . ' tuần trước';
    if ($diff->days < 365) return floor($diff->days / 30) . ' tháng trước';
    return floor($diff->days / 365) . ' năm trước';
}));

$twig->addFunction(new \Twig\TwigFunction('stars', function (float $rating): string {
    $html = '<span class="stars">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $rating >= $i ? '<i class="bi bi-star-fill"></i>'
               : ($rating >= $i - 0.5 ? '<i class="bi bi-star-half"></i>' : '<i class="bi bi-star"></i>');
    }
    return $html . '</span>';
}, ['is_safe' => ['html']]));

$twig->addFunction(new \Twig\TwigFunction('asset', function (string $path): string {
    return APP_URL . '/public/' . ltrim($path, '/');
}, ['is_safe' => ['html']]));

$twig->addFunction(new \Twig\TwigFunction('route', function (string $path): string {
    return APP_URL . '/' . ltrim($path, '/');
}, ['is_safe' => ['html']]));

// Ensure CSRF token exists for global use (CPW widget etc.)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$twig->addGlobal('csrf_token', $_SESSION['csrf_token']);

$twig->addGlobal('flash', $_SESSION['flash'] ?? []);
unset($_SESSION['flash']);

\App\Core\Container::set('twig', $twig);

return $twig;