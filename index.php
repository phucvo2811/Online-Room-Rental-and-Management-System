<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap/app.php';

$router = require_once __DIR__ . '/routes/web.php';
$router->dispatch();