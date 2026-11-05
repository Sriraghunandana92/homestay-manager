<?php
// 1. Copy this file and name the copy config.php (same folder).
// 2. Fill in the details from Hostinger hPanel → Databases → Management.
//    Hostinger adds a prefix such as u123456789_ to the database name and user.
return [
    'db_host'  => 'localhost',
    'db_name'  => 'u123456789_homestay',
    'db_user'  => 'u123456789_homestay',
    'db_pass'  => 'PUT-YOUR-DATABASE-PASSWORD-HERE',
    'timezone' => 'Asia/Kolkata',
    // Keep false on the live site. true shows technical error details while you set things up.
    'debug'    => false,
];
