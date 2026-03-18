<?php
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\AdminMiddleware;
use App\Middleware\LandlordMiddleware;

$router = new Router();

// ── Public ────────────────────────────────────────────────────────────────
$router->get('/',            'HomeController', 'index');
$router->get('/rooms',       'RoomController', 'index');
$router->post('/rooms',      'RoomController', 'index');

// ── API ───────────────────────────────────────────────────────────────────
$router->get('/api/wards',   'ApiController',  'wards');
$router->get('/api/streets', 'ApiController',  'streets');

// ── Auth ──────────────────────────────────────────────────────────────────
$router->get('/login',       'AuthController', 'loginForm');
$router->post('/login',      'AuthController', 'login');
$router->get('/register',    'AuthController', 'registerForm');
$router->post('/register',   'AuthController', 'register');
$router->get('/logout',      'AuthController', 'logout');

// ── QUAN TRỌNG: Route tĩnh phải trước route động ──────────────────────────
$router->group('', [LandlordMiddleware::class], function (Router $r) {
    $r->get('/my-rooms',           'RoomController', 'myRooms');
    $r->get('/rooms/create',       'RoomController', 'createForm');   // tĩnh
    $r->post('/rooms/create',      'RoomController', 'create');       // tĩnh
    $r->get('/rooms/{id}/edit',    'RoomController', 'editForm');     // động
    $r->post('/rooms/{id}/edit',   'RoomController', 'update');       // động
    $r->post('/rooms/{id}/delete', 'RoomController', 'delete');       // động
});

// ── Route động /rooms/{id} phải ở SAU /rooms/create ──────────────────────
$router->get('/rooms/{id}',  'RoomController', 'show');

// ── Authenticated ─────────────────────────────────────────────────────────
$router->group('', [AuthMiddleware::class], function (Router $r) {
    $r->get('/dashboard',          'UserController',    'dashboard');
    $r->get('/profile',            'UserController',    'profile');
    $r->post('/profile',           'UserController',    'updateProfile');
    $r->get('/favorites',          'UserController',    'favorites');
    $r->post('/favorites/toggle',  'UserController',    'toggleFavorite');
    $r->post('/contact',           'ContactController', 'send');
    $r->post('/reviews',           'ReviewController',  'store');
});

// ── Admin ─────────────────────────────────────────────────────────────────
$router->group('/admin', [AdminMiddleware::class], function (Router $r) {
    $r->get('',                        'AdminController', 'dashboard');
    $r->get('/rooms',                  'AdminController', 'rooms');
    $r->post('/rooms/{id}/approve',    'AdminController', 'approveRoom');
    $r->post('/rooms/{id}/reject',     'AdminController', 'rejectRoom');
    $r->get('/users',                  'AdminController', 'users');
    $r->post('/users/{id}/ban',        'AdminController', 'banUser');
    $r->get('/contacts',               'AdminController', 'contacts');
});

return $router;