<?php
return [
    'name'        => 'PhòngTrọ Cần Thơ',
    'version'     => '2.0.0',
    'url'         => 'http://phongtrocantho.localhost',
    'env'         => 'development',
    'debug'       => true,
    'timezone'    => 'Asia/Ho_Chi_Minh',

    'upload_path'    => __DIR__ . '/../public/uploads/',
    // NOTE: The app serves static assets from /public/, so upload URLs must also include /public/.
    'upload_url'     => '/public/uploads/',
    'max_file_size'  => 5 * 1024 * 1024,
    'allowed_ext'    => ['jpg', 'jpeg', 'png', 'webp'],

    'items_per_page'   => 12,
    'session_lifetime' => 86400,

    // Google Maps JavaScript API key
    // Get yours at: https://console.cloud.google.com/
    // Enable: Maps JavaScript API + Places API
    'google_maps_api_key' => '',  // <-- paste your key here

    // Google Gemini API key (for AI chatbot)
    // Get yours at: https://aistudio.google.com/app/apikey
    'gemini_api_key' => 'AIzaSyCDBH13fSS7YG_DeebUZdv4m5VCnEXR2-s',

    // ── VNPay Payment Gateway ──────────────────────────────────────────────
    // Đăng ký tài khoản merchant tại: https://sandbox.vnpayment.vn/
    // Sandbox (test): https://sandbox.vnpayment.vn/paymentv2/vpcpay.html
    // Production:      https://pay.vnpay.vn/vpcpay.html
    'vnp_tmn_code'    => '',                 // Terminal code từ VNPay portal
    'vnp_hash_secret' => '',                 // Hash secret từ VNPay portal
    'vnp_url'         => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
    'vnp_return_url'  => '',                 // VD: http://yourdomain.com/payment/return
    'vnp_ipn_url'     => '',                 // VD: http://yourdomain.com/payment/ipn (cấu hình trong VNPay portal)

    'room_types' => [
        'phong_tro'      => 'Phòng trọ',
        'chung_cu_mini'  => 'Chung cư mini',
        'nha_nguyen_can' => 'Nhà nguyên căn',
        'homestay'       => 'Homestay',
        'ky_tuc_xa'      => 'Ký túc xá',
    ],

    'room_status' => [
        'pending'  => ['label' => 'Chờ duyệt',   'class' => 'warning'],
        'approved' => ['label' => 'Đã duyệt',    'class' => 'success'],
        'rejected' => ['label' => 'Từ chối',      'class' => 'danger'],
        'rented'   => ['label' => 'Đã cho thuê', 'class' => 'secondary'],
        'inactive' => ['label' => 'Tạm dừng',    'class' => 'dark'],
    ],

    // PRO subscription plans for landlords (pricing is illustrative)
    'pro_plans' => [
        ['id' => 'pro_7',  'title' => 'PRO 7 ngày',  'days' => 7,  'price' => 49000],
        ['id' => 'pro_30', 'title' => 'PRO 30 ngày', 'days' => 30, 'price' => 149000],
    ],
];