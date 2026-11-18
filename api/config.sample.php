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

    // --- Ảnh/video khách đính kèm ---
    // Dung lượng tối đa mỗi tệp (MB). Thực tế bị giới hạn bởi PHP: upload_max_filesize & post_max_size
    // (hPanel -> Advanced -> PHP Configuration để tăng, vd 64M).
    'max_upload_mb' => 50,

    // --- Gửi email: chọn 1 trong 3 cách ---
    'mail_name'   => 'Lịch Chụp',

    // CÁCH 1: Gmail của bạn (Hostinger trả phí, host cho phép SMTP)
    //   Bật xác minh 2 bước cho Gmail -> https://myaccount.google.com/apppasswords
    //   -> tạo "Mật khẩu ứng dụng" 16 ký tự, dán vào smtp_pass (KHÔNG dùng mật khẩu Gmail thường).
    //   mail_from phải trùng chính địa chỉ Gmail. Giới hạn ~500 email/ngày.
    'mail_from'   => 'tengmail@gmail.com',
    'smtp_host'   => 'smtp.gmail.com',
    'smtp_port'   => 587,
    'smtp_secure' => 'tls',                  // 'ssl' (465) | 'tls' (587) | ''
    'smtp_user'   => 'tengmail@gmail.com',
    'smtp_pass'   => 'MAT_KHAU_UNG_DUNG_16_KY_TU',

    // CÁCH 2: Hộp thư tên miền Hostinger -> mail_from/smtp_user = no-reply@tenmien.com,
    //   smtp_host = 'smtp.hostinger.com', smtp_port = 465, smtp_secure = 'ssl'.

    // CÁCH 3: API Brevo (miễn phí ~300 email/ngày, chạy được trên host miễn phí chặn SMTP).
    //   Tạo tài khoản brevo.com -> SMTP & API -> tạo API key; vào Senders -> thêm & xác minh Gmail của bạn.
    //   Bỏ comment 2 dòng dưới, mail_from = Gmail đã xác minh ở Brevo.
    // 'mail_driver'   => 'brevo',
    // 'brevo_api_key' => 'xkeysib-...',
];
