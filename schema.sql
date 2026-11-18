-- Import file này vào MySQL (hPanel -> Databases -> phpMyAdmin -> Import)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(120) NOT NULL,
  studio_name VARCHAR(160) NOT NULL DEFAULT '',
  phone VARCHAR(40) NOT NULL DEFAULT '',
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  plan_expires_at DATE NULL,
  notify_24h TINYINT(1) NOT NULL DEFAULT 1,
  notify_2h TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS staff (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(40) NOT NULL DEFAULT '',
  roles VARCHAR(100) NOT NULL DEFAULT 'photographer',
  note VARCHAR(255) NOT NULL DEFAULT '',
  color VARCHAR(9) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  KEY idx_staff_user (user_id),
  CONSTRAINT fk_staff_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS shoots (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL DEFAULT '',
  customer_name VARCHAR(160) NOT NULL DEFAULT '',
  customer_phone VARCHAR(40) NOT NULL DEFAULT '',
  location VARCHAR(255) NOT NULL DEFAULT '',
  notes TEXT NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  owner_r24 TINYINT(1) NOT NULL DEFAULT 0,
  owner_r2 TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  KEY idx_shoot_user_start (user_id, start_at),
  KEY idx_shoot_start (start_at),
  CONSTRAINT fk_shoot_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS shoot_media (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  shoot_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  filename VARCHAR(60) NOT NULL,
  orig_name VARCHAR(255) NOT NULL DEFAULT '',
  mime VARCHAR(100) NOT NULL,
  size INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  KEY idx_media_shoot (shoot_id),
  CONSTRAINT fk_media_shoot FOREIGN KEY (shoot_id) REFERENCES shoots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS shoot_staff (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  shoot_id INT UNSIGNED NOT NULL,
  staff_id INT UNSIGNED NOT NULL,
  role ENUM('photographer','assistant','makeup') NOT NULL,
  status ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
  token CHAR(32) NOT NULL UNIQUE,
  r24 TINYINT(1) NOT NULL DEFAULT 0,
  r2 TINYINT(1) NOT NULL DEFAULT 0,
  invited_at DATETIME NOT NULL,
  responded_at DATETIME NULL,
  UNIQUE KEY uq_assign (shoot_id, staff_id, role),
  KEY idx_ss_status (status),
  CONSTRAINT fk_ss_shoot FOREIGN KEY (shoot_id) REFERENCES shoots(id) ON DELETE CASCADE,
  CONSTRAINT fk_ss_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- NÂNG CẤP database cũ (đã import bản trước): chạy riêng dòng dưới 1 lần
-- ALTER TABLE staff ADD COLUMN color VARCHAR(9) NOT NULL DEFAULT '';
