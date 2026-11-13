<?php
// Sao chép file này thành config.php rồi điền thông tin thật.
// KHÔNG commit config.php lên GitHub (đã nằm trong .gitignore).
return [
    // --- Database (tạo trong hPanel -> Databases -> MySQL Databases) ---
    'db_host' => 'localhost',
    'db_name' => 'u123456789_lich',
    'db_user' => 'u123456789_lich',
    'db_pass' => 'DOI_MAT_KHAU',

    // --- Website ---
    'base_url'   => 'https://tenmiencuaban.com',   // KHÔNG có dấu / ở cuối
    'app_name'   => 'Lịch Chụp',
    'timezone'   => 'Asia/Ho_Chi_Minh',
    'cron_key'   => 'DOI_CHUOI_BI_MAT_DAI',         // dùng cho cron.php qua URL

    // --- Bán nhiều người dùng ---
    'allow_register' => true,   // false = đóng đăng ký (tài khoản đầu tiên luôn tạo được = admin)
    'trial_days'     => 14,     // số ngày dùng thử cho tài khoản mới (0 = không giới hạn)

    // --- Gửi email (tạo hộp thư trong hPanel -> Emails) ---
    'mail_from'   => 'no-reply@tenmiencuaban.com',
    'mail_name'   => 'Lịch Chụp',
    'smtp_host'   => 'smtp.hostinger.com',   // để trống để dùng hàm mail() của PHP
    'smtp_port'   => 465,
    'smtp_secure' => 'ssl',                  // 'ssl' (465) | 'tls' (587) | ''
    'smtp_user'   => 'no-reply@tenmiencuaban.com',
    'smtp_pass'   => 'MAT_KHAU_HOP_THU',
];
