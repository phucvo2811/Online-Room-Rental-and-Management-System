<?php
return [
    'host'    => 'localhost',
    'dbname'  => 'phongtro_db',
    'user'    => 'postgres',
    'pass'    => '2811',
    'port'    => '5432',
    'options' => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ],
];