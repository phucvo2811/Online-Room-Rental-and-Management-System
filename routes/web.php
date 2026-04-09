<?php
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\AdminMiddleware;
use App\Middleware\LandlordMiddleware;

$router = new Router();

// ── Public ────────────────────────────────────────────────────────────────
$router->get('/',            'HomeController', 'index');
$router->get('/terms',       'PageController', 'terms');
$router->get('/privacy',     'PageController', 'privacy');
$router->get('/rooms',       'RoomController', 'index');
$router->post('/rooms',      'RoomController', 'index');

// ── API ───────────────────────────────────────────────────────────────────
$router->get('/api/wards',   'ApiController',  'wards');
$router->get('/api/streets', 'ApiController',  'streets');
$router->get('/api/rooms/{id}', 'RoomController', 'apiRoom');
$router->post('/api/chatbot','ApiController',  'chatbot');
// Landlord request API
$router->post('/api/landlord/request',          'ApiController', 'landlordRequest');
$router->get('/api/admin/landlord-requests',    'ApiController', 'adminGetLandlordRequests');
$router->post('/api/admin/approve-landlord',    'ApiController', 'adminApproveLandlord');
$router->post('/api/admin/reject-landlord',     'ApiController', 'adminRejectLandlord');
$router->get('/api/finance/summary',            'ApiController', 'financeSummary');

// ── Public property routes (Nhà trọ/KTX, mini/full house, variant selection)
$router->get('/properties',                      'RoomController', 'listProperties');
$router->get('/properties/{id}',                 'RoomController', 'showProperty');
$router->get('/property/{id}',                   'RoomController', 'showProperty');
$router->get('/property/{id}/room/{room_number}','RoomController', 'getRoomByNumber');

// ── Auth ──────────────────────────────────────────────────────────────────
$router->get('/login',       'AuthController', 'loginForm');
$router->post('/login',      'AuthController', 'login');
$router->get('/register',    'AuthController', 'registerForm');
$router->post('/register',   'AuthController', 'register');
$router->get('/logout',      'AuthController', 'logout');


$router->group('', [LandlordMiddleware::class], function (Router $r) {
    $r->get('/my-rooms',           'RoomController', 'myRooms');
    $r->get('/rooms/{id}/edit',    'RoomController', 'editForm');     // động
    $r->post('/rooms/{id}/edit',   'RoomController', 'update');       // động
    $r->post('/rooms/{id}/delete', 'RoomController', 'delete');       // động
    $r->post('/rooms/{id}/toggle', 'RoomController', 'toggleAvailability');
    $r->post('/rooms/{id}/assign-block', 'RoomController', 'assignRoomBlock');

    // Room blocks (landlord)  
    $r->get('/my-blocks',                           'RoomController', 'myBlocks');
    $r->get('/blocks/create',                      'RoomController', 'createBlockForm');
    $r->post('/blocks/create',                     'RoomController', 'createBlock');
    $r->get('/blocks/{id}/edit',                   'RoomController', 'editBlockForm');
    $r->post('/blocks/{id}/edit',                  'RoomController', 'updateBlock');
    $r->post('/blocks/{id}/delete',                'RoomController', 'deleteBlock');

    $r->get('/blocks/{id}/rooms',                  'RoomController', 'blockRooms');
    $r->get('/blocks/{id}/rooms/create',           'RoomController', 'createBlockRoomForm');
    $r->post('/blocks/{id}/rooms/create',          'RoomController', 'createBlockRoom');
    $r->post('/blocks/{id}/rooms/bulk-create',     'RoomController', 'bulkCreateRooms');
    $r->post('/blocks/{id}/rooms/{room_id}/occupancy', 'RoomController', 'setRoomOccupancy');

    // Post listing flow (Property-based)
    $r->get('/post',                       'PostController', 'index');
    $r->get('/post/create',                'PostController', 'create');
    $r->post('/post/store',                'PostController', 'store');

    // Legacy posts/room posts (if needed)
    $r->get('/my-posts',                   'RoomController', 'myPosts');
    $r->get('/posts/{id}',                 'RoomController', 'showPost');
    $r->get('/posts/{id}/edit',            'RoomController', 'editPostForm');
    $r->post('/posts/{id}/edit',           'RoomController', 'updatePost');
    $r->post('/posts/{id}/delete',         'RoomController', 'deletePost');
    $r->post('/posts/{id}/toggle',         'RoomController', 'togglePost');

    $r->post('/landlord/pro',        'UserController', 'purchasePro');
    $r->post('/landlord/financials','UserController', 'saveFinancials');
});

$router->get('/rooms/{id}',  'RoomController', 'show');

$router->group('', [AuthMiddleware::class], function (Router $r) {
    $r->get('/dashboard',          'UserController',    'dashboard');
    $r->get('/profile',            'UserController',    'profile');
    $r->post('/profile',           'UserController',    'updateProfile');
    $r->get('/favorites',          'UserController',    'favorites');
    $r->post('/favorites/toggle',  'UserController',    'toggleFavorite');
    $r->post('/contact',           'ContactController', 'send');
    $r->post('/reviews',           'ReviewController',  'store');
    // Landlord upgrade flow
    $r->get('/landlord-request',   'UserController',    'landlordRequestForm');
    $r->post('/landlord-request',  'UserController',    'submitLandlordRequest');

    $r->get('/chat',                   'ChatController', 'index');
    $r->get('/chat/start/{id}',        'ChatController', 'startWith');   // static before dynamic
    $r->get('/chat/{id}',              'ChatController', 'conversation');
    $r->get('/chat/{id}/poll',         'ChatController', 'poll');
    $r->post('/chat/send',             'ChatController', 'send');
    $r->post('/chat/{id}/typing',      'ChatController', 'typing');
});

$router->group('', [AuthMiddleware::class], function (Router $r) {
    $r->get('/api/chat/unread',         'ChatController', 'unreadCount');
    $r->get('/api/chat/conversations',  'ChatController', 'conversations');
    $r->get('/api/chat/open/{userId}',  'ChatController', 'openWithUser');
});

$router->group('/admin', [AdminMiddleware::class], function (Router $r) {
    $r->get('',                        'AdminController', 'dashboard');
    $r->get('/rooms',                  'AdminController', 'rooms');
    $r->post('/rooms/{id}/approve',    'AdminController', 'approveRoom');
    $r->post('/rooms/{id}/reject',     'AdminController', 'rejectRoom');
    $r->get('/users',                  'AdminController', 'users');
    $r->post('/users/{id}/ban',        'AdminController', 'banUser');

    // Post duyệt
    $r->get('/posts',                  'AdminController', 'posts');
    $r->post('/posts/{id}/approve',    'AdminController', 'approvePost');
    $r->post('/posts/{id}/reject',     'AdminController', 'rejectPost');

    $r->get('/contacts',               'AdminController', 'contacts');
    $r->get('/banners',                    'AdminController', 'banners');
    $r->post('/banners/create',            'AdminController', 'createBanner');
    $r->post('/banners/{id}/delete',       'AdminController', 'deleteBanner');
    $r->post('/banners/{id}/toggle',       'AdminController', 'toggleBanner');

    // Banner management
    $r->get('/banners',                 'AdminController', 'banners');
    $r->post('/banners/create',         'AdminController', 'createBanner');
    $r->get('/banners/{id}/edit',       'AdminController', 'editBanner');
    $r->post('/banners/{id}/edit',      'AdminController', 'updateBanner');
    $r->post('/banners/{id}/delete',    'AdminController', 'deleteBanner');
    $r->post('/banners/order',          'AdminController', 'updateBannerOrder');
    $r->post('/banners/{id}/toggle',    'AdminController', 'toggleBanner');

    // user management advanced
    $r->get('/users/{id}',              'AdminController', 'userDetail');
    $r->post('/users/{id}/toggle-status','AdminController', 'toggleUserStatus');
    $r->post('/users/{id}/role',        'AdminController', 'setUserRole');
    $r->post('/users/{id}/trusted',     'AdminController', 'toggleTrustedLandlord');

    // PRO admin
    $r->get('/pro',                     'AdminController', 'pro');
    $r->post('/pro/settings',           'AdminController', 'updateProSettings');

    // Site settings
    $r->get('/settings',                'AdminController', 'settings');
    $r->post('/settings',               'AdminController', 'updateSettings');

    // room spam/hide
    $r->post('/rooms/{id}/hide',        'AdminController', 'hideRoom');

    // Properties approval
    $r->get('/properties',                         'AdminController', 'properties');
    $r->get('/properties/{id}',                    'AdminController', 'previewProperty');
    $r->post('/properties/{id}/approve',           'AdminController', 'approveProperty');
    $r->post('/properties/{id}/reject',            'AdminController', 'rejectProperty');

    // Payment admin
    $r->get('/payments',                           'AdminController', 'payments');

    // Landlord request approvals
    $r->get('/landlord-requests',                  'AdminController', 'landlordRequests');
    $r->post('/landlord-requests/{id}/approve',    'AdminController', 'approveLandlord');
    $r->post('/landlord-requests/{id}/reject',     'AdminController', 'rejectLandlord');
});

$router->get('/payment/return',   'PaymentController', 'returnUrl');
$router->get('/payment/ipn',      'PaymentController', 'ipn');

$router->group('', [AuthMiddleware::class], function (Router $r) {
    $r->get('/payment/packages',  'PaymentController', 'packages');
    $r->post('/payment/create',   'PaymentController', 'create');
    $r->get('/payment/history',   'PaymentController', 'history');
});

return $router;