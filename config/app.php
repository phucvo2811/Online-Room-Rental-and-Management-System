<?php
return [
    'name'        => 'PhòngTrọ Cần Thơ',
    'version'     => '2.0.0',
    'url'         => 'http://quanlyphongtro.localhost',
    'env'         => 'development',
    'debug'       => true,
    'timezone'    => 'Asia/Ho_Chi_Minh',

    'upload_path'    => __DIR__ . '/../public/images/uploads/',
    'upload_url'     => '/images/uploads/',
    'max_file_size'  => 5 * 1024 * 1024,
    'allowed_ext'    => ['jpg', 'jpeg', 'png', 'webp'],

    'items_per_page'   => 12,
    'session_lifetime' => 86400,

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
];