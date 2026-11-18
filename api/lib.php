<?php
declare(strict_types=1);

/* ============ Cấu hình & DB ============ */
function cfg(string $k, $d = null) {
    static $c = null;
    if ($c === null) {
        $f = __DIR__ . '/config.php';
        $c = is_file($f) ? (require $f) : [];
    }
    return $c[$k] ?? $d;
}
date_default_timezone_set((string)cfg('timezone', 'Asia/Ho_Chi_Minh'));

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . cfg('db_host', 'localhost') . ';dbname=' . cfg('db_name', '') . ';charset=utf8mb4';
        $pdo = new PDO($dsn, (string)cfg('db_user', ''), (string)cfg('db_pass', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function q(string $sql, array $p = []): PDOStatement {
    $st = db()->prepare($sql);
    $st->execute($p);
    return $st;
}

function now_str(): string { return date('Y-m-d H:i:s'); }
function esc(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function base_url(): string { return rtrim((string)cfg('base_url', ''), '/'); }

/* ============ Ảnh / video đính kèm ============ */
const MEDIA_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
    'video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/webm' => 'webm'];
const MAX_MEDIA_PER_SHOOT = 20;

/** Thư mục uploads/ nằm ngoài tầm truy cập trực tiếp (Require all denied); chỉ PHP đọc được. */
function uploads_dir(): string {
    $d = dirname(__DIR__) . '/uploads';
    if (!is_dir($d)) @mkdir($d, 0755, true);
    $ht = $d . '/.htaccess';
    if (!is_file($ht)) @file_put_contents($ht, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    return $d;
}
function ini_bytes(string $v): int {
    $v = trim($v);
    if ($v === '') return 0;
    $n = (float)$v;
    switch (strtolower(substr($v, -1))) { case 'g': $n *= 1024; case 'm': $n *= 1024; case 'k': $n *= 1024; }
    return (int)$n;
}
function max_upload_bytes(): int {
    $cfg = (int)cfg('max_upload_mb', 50) * 1048576;
    $lim = array_filter([ini_bytes((string)ini_get('upload_max_filesize')), ini_bytes((string)ini_get('post_max_size'))]);
    return $lim ? min($cfg, ...$lim) : $cfg;
}
function serve_media(array $m): void {
    $path = uploads_dir() . '/' . $m['filename'];
    if (!is_file($path)) { http_response_code(404); exit; }
    $size = filesize($path); $start = 0; $end = $size - 1;
    header('Content-Type: ' . $m['mime']);
    header('Accept-Ranges: bytes');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=86400');
    header('Content-Disposition: inline; filename="' . rawurlencode((string)$m['orig_name']) . '"');
    if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $r)) {
        if ($r[1] === '' && $r[2] !== '') { $start = max(0, $size - (int)$r[2]); }
        else { if ($r[1] !== '') $start = (int)$r[1]; if ($r[2] !== '') $end = min((int)$r[2], $size - 1); }
        if ($start > $end || $start >= $size) { http_response_code(416); header("Content-Range: bytes */$size"); exit; }
        http_response_code(206);
        header("Content-Range: bytes $start-$end/$size");
    }
    header('Content-Length: ' . ($end - $start + 1));
    $fp = fopen($path, 'rb'); fseek($fp, $start); $left = $end - $start + 1;
    while ($left > 0 && !feof($fp)) { $chunk = fread($fp, min(8192, $left)); echo $chunk; $left -= strlen($chunk); }
    fclose($fp);
    exit;
}
function media_count(int $shootId): int {
    return (int)q('SELECT COUNT(*) c FROM shoot_media WHERE shoot_id=?', [$shootId])->fetch()['c'];
}
function delete_media_files(array $rows): void {
    foreach ($rows as $m) { $p = uploads_dir() . '/' . $m['filename']; if (is_file($p)) @unlink($p); }
}

/* ============ Định dạng tiếng Việt ============ */
function vn_weekday(DateTime $d): string {
    return ['Chủ nhật', 'Thứ hai', 'Thứ ba', 'Thứ tư', 'Thứ năm', 'Thứ sáu', 'Thứ bảy'][(int)$d->format('w')];
}
function vn_range(string $start, string $end): string {
    $s = new DateTime($start); $e = new DateTime($end);
    return vn_weekday($s) . ', ' . $s->format('d/m/Y') . ' • ' . $s->format('H:i') . ' – ' . $e->format('H:i');
}
function role_label(string $r): string {
    return ['photographer' => 'Thợ chụp', 'assistant' => 'Thợ phụ', 'makeup' => 'Makeup'][$r] ?? $r;
}
function status_label(string $s): string {
    return ['pending' => 'Chờ phản hồi', 'accepted' => 'Đã đồng ý', 'declined' => 'Từ chối'][$s] ?? $s;
}

/* ============ Gửi email (SMTP thuần, fallback mail()) ============ */
function send_mail(string $to, string $subject, string $html): bool {
    $from = (string)cfg('mail_from', 'no-reply@localhost');
    $fromName = (string)cfg('mail_name', 'Lịch Chụp');
    try {
        if (cfg('mail_driver') === 'brevo') {
            brevo_send($to, $subject, $html, $from, $fromName);
        } elseif (cfg('smtp_host')) {
            smtp_send($to, $subject, $html, $from, $fromName);
        } else {
            $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n"
                . 'From: =?UTF-8?B?' . base64_encode($fromName) . "?= <$from>\r\n";
            if (!mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $headers)) {
                throw new Exception('mail() failed');
            }
        }
        return true;
    } catch (Throwable $e) {
        error_log('[mail] ' . $to . ' : ' . $e->getMessage());
        return false;
    }
}

/** Gửi qua API Brevo (HTTPS cổng 443) – dùng được trên host miễn phí chặn SMTP. */
function brevo_send(string $to, string $subject, string $html, string $from, string $fromName): void {
    $key = (string)cfg('brevo_api_key', '');
    if ($key === '') throw new Exception('Thiếu brevo_api_key');
    $payload = json_encode([
        'sender' => ['name' => $fromName, 'email' => $from],
        'to' => [['email' => $to]],
        'subject' => $subject,
        'htmlContent' => $html,
    ], JSON_UNESCAPED_UNICODE);
    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['accept: application/json', 'content-type: application/json', 'api-key: ' . $key],
    ]);
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) throw new Exception('Brevo: ' . $err);
    if ($code < 200 || $code >= 300) throw new Exception("Brevo HTTP $code: " . substr((string)$res, 0, 300));
}

function smtp_send(string $to, string $subject, string $html, string $from, string $fromName): void {
    $secure = (string)cfg('smtp_secure', 'ssl');
    $host = (string)cfg('smtp_host');
    $port = (int)cfg('smtp_port', 465);
    $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port, $errno, $errstr, 15);
    if (!$fp) throw new Exception("SMTP connect: $errstr ($errno)");
    stream_set_timeout($fp, 20);

    $read = function () use ($fp): string {
        $r = '';
        while (($l = fgets($fp, 515)) !== false) {
            $r .= $l;
            if (strlen($l) < 4 || $l[3] === ' ') break;
        }
        return $r;
    };
    $cmd = function (?string $c, array $ok) use ($fp, $read): string {
        if ($c !== null) fwrite($fp, $c . "\r\n");
        $r = $read();
        if (!in_array((int)substr($r, 0, 3), $ok, true)) throw new Exception('SMTP: ' . trim($r));
        return $r;
    };

    $cmd(null, [220]);
    $domain = parse_url(base_url(), PHP_URL_HOST) ?: 'localhost';
    $cmd("EHLO $domain", [250]);
    if ($secure === 'tls') {
        $cmd('STARTTLS', [220]);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new Exception('SMTP: TLS failed');
        $cmd("EHLO $domain", [250]);
    }
    if (cfg('smtp_user')) {
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode((string)cfg('smtp_user')), [334]);
        $cmd(base64_encode((string)cfg('smtp_pass')), [235]);
    }
    $cmd("MAIL FROM:<$from>", [250]);
    $cmd("RCPT TO:<$to>", [250, 251]);
    $cmd('DATA', [354]);

    $msg = 'Date: ' . date('r') . "\r\n"
        . 'From: =?UTF-8?B?' . base64_encode($fromName) . "?= <$from>\r\n"
        . "To: <$to>\r\n"
        . 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n"
        . 'Message-ID: <' . bin2hex(random_bytes(8)) . "@$domain>\r\n"
        . "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html), 76, "\r\n");
    fwrite($fp, $msg . "\r\n.\r\n");
    $cmd(null, [250]);
    fwrite($fp, "QUIT\r\n");
    fclose($fp);
}

/* ============ Mẫu email (giao diện sáng, bo tròn) ============ */
function mail_layout(string $heading, string $body): string {
    $app = esc((string)cfg('app_name', 'Lịch Chụp'));
    return '<!doctype html><html><body style="margin:0;background:#eef2fb;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;color:#1c1c1e">'
        . '<div style="max-width:560px;margin:0 auto;padding:28px 16px">'
        . '<div style="text-align:center;font-weight:700;font-size:15px;color:#0a84ff;margin-bottom:14px">' . $app . '</div>'
        . '<div style="background:#fff;border-radius:24px;padding:28px;box-shadow:0 8px 30px rgba(60,80,140,.12)">'
        . '<h1 style="margin:0 0 16px;font-size:22px;line-height:1.25">' . $heading . '</h1>' . $body
        . '</div><div style="text-align:center;color:#8e8e93;font-size:12px;margin-top:16px">Email tự động từ ' . $app . '</div>'
        . '</div></body></html>';
}

function mail_details(array $shoot, ?string $role = null): string {
    $rows = [
        ['Khách hàng', $shoot['customer_name'] . ($shoot['customer_phone'] ? ' • ' . $shoot['customer_phone'] : '')],
        ['Thời gian', vn_range($shoot['start_at'], $shoot['end_at'])],
        ['Địa điểm', $shoot['location']],
    ];
    if ($role) $rows[] = ['Vai trò của bạn', role_label($role)];
    if (!empty($shoot['notes'])) $rows[] = ['Ghi chú', $shoot['notes']];
    if (!empty($shoot['id']) && ($n = media_count((int)$shoot['id'])) > 0) $rows[] = ['Ảnh/video khách gửi', $n . ' tệp – mở liên kết bên dưới để xem'];
    $h = '<table style="width:100%;border-collapse:collapse;margin:6px 0 18px">';
    foreach ($rows as [$k, $v]) {
        if ($v === '' || $v === null) continue;
        $h .= '<tr><td style="padding:9px 0;color:#8e8e93;font-size:13px;width:34%;vertical-align:top;border-bottom:1px solid #f0f0f5">' . esc($k)
            . '</td><td style="padding:9px 0;font-size:15px;border-bottom:1px solid #f0f0f5">' . nl2br(esc($v)) . '</td></tr>';
    }
    return $h . '</table>';
}

function btn(string $url, string $label, string $color = '#0a84ff'): string {
    return '<a href="' . esc($url) . '" style="display:inline-block;background:' . $color . ';color:#fff;text-decoration:none;font-weight:600;padding:12px 22px;border-radius:14px;font-size:15px">' . esc($label) . '</a>';
}

function respond_url(string $token): string { return base_url() . '/respond.php?t=' . $token; }

function get_shoot(int $id): ?array {
    $r = q('SELECT * FROM shoots WHERE id=?', [$id])->fetch();
    return $r ?: null;
}
function get_owner(int $uid): array {
    return q('SELECT id,email,name,studio_name FROM users WHERE id=?', [$uid])->fetch() ?: ['email' => '', 'name' => '', 'studio_name' => '', 'id' => $uid];
}
function owner_label(array $o): string { return $o['studio_name'] !== '' ? $o['studio_name'] : $o['name']; }

/** Gửi lời mời/cập nhật/hủy tới 1 người trong ca. $a: assignment + name,email */
function send_assignment_mail(array $shoot, array $a, string $kind): bool {
    $owner = get_owner((int)$shoot['user_id']);
    $who = esc($a['name']);
    $by = esc(owner_label($owner));
    $date = (new DateTime($shoot['start_at']))->format('d/m H:i');
    if ($kind === 'cancel') {
        $subject = "[Hủy lịch] $date - " . $shoot['customer_name'];
        $body = "<p style=\"font-size:15px;line-height:1.5\">Chào $who, $by đã <b>hủy</b> ca chụp dưới đây:</p>" . mail_details($shoot, $a['role']);
        return send_mail($a['email'], $subject, mail_layout('Ca chụp đã bị hủy', $body));
    }
    $upd = $kind === 'update';
    $subject = ($upd ? '[Cập nhật] ' : '[Lời mời] ') . "Lịch chụp $date - " . $shoot['customer_name'];
    $intro = $upd
        ? "Chào $who, ca chụp của $by vừa được <b>thay đổi</b>. Vui lòng xác nhận lại:"
        : "Chào $who, $by mời bạn tham gia ca chụp dưới đây. Bạn có nhận ca này không?";
    $url = respond_url($a['token']);
    $body = "<p style=\"font-size:15px;line-height:1.5;margin:0 0 8px\">$intro</p>" . mail_details($shoot, $a['role'])
        . '<div>' . btn($url . '&a=accept', '✓ Đồng ý', '#34c759') . '&nbsp;&nbsp;' . btn($url . '&a=decline', '✕ Không nhận', '#ff453a') . '</div>'
        . '<p style="color:#8e8e93;font-size:12px;margin-top:18px">Hoặc mở liên kết: <a href="' . esc($url) . '" style="color:#0a84ff">' . esc($url) . '</a></p>';
    return send_mail($a['email'], $subject, mail_layout($upd ? 'Ca chụp đã thay đổi' : 'Lời mời nhận ca chụp', $body));
}

function send_reminder_mail(array $shoot, array $a, string $when): bool {
    $label = $when === '24h' ? 'ngày mai' : 'sau khoảng 2 giờ nữa';
    $subject = ($when === '24h' ? '[Nhắc lịch ngày mai] ' : '[Sắp đến giờ chụp] ') . (new DateTime($shoot['start_at']))->format('d/m H:i') . ' - ' . $shoot['customer_name'];
    $body = '<p style="font-size:15px;line-height:1.5;margin:0 0 8px">Chào ' . esc($a['name']) . ', bạn có ca chụp <b>' . $label . '</b>:</p>' . mail_details($shoot, $a['role']);
    if (($a['status'] ?? '') === 'pending') {
        $url = respond_url($a['token']);
        $body .= '<p style="font-size:14px">Bạn chưa phản hồi ca này:</p><div>' . btn($url . '&a=accept', '✓ Đồng ý', '#34c759') . '&nbsp;&nbsp;' . btn($url . '&a=decline', '✕ Không nhận', '#ff453a') . '</div>';
    }
    return send_mail($a['email'], $subject, mail_layout($when === '24h' ? 'Nhắc lịch chụp ngày mai' : 'Sắp đến giờ chụp', $body));
}

function shoot_team_html(int $shootId): string {
    $rows = q('SELECT ss.role, ss.status, s.name FROM shoot_staff ss JOIN staff s ON s.id=ss.staff_id WHERE ss.shoot_id=? ORDER BY FIELD(ss.role,"photographer","assistant","makeup"), s.name', [$shootId])->fetchAll();
    if (!$rows) return '';
    $h = '<p style="font-size:13px;color:#8e8e93;margin:6px 0 4px">Đội ngũ</p><ul style="margin:0 0 12px;padding-left:18px;font-size:14px;line-height:1.7">';
    foreach ($rows as $r) $h .= '<li>' . esc(role_label($r['role'])) . ': <b>' . esc($r['name']) . '</b> – ' . esc(status_label($r['status'])) . '</li>';
    return $h . '</ul>';
}

function send_owner_reminder(array $shoot, string $when): bool {
    $o = get_owner((int)$shoot['user_id']);
    if (!$o['email']) return false;
    $label = $when === '24h' ? 'ngày mai' : 'sau khoảng 2 giờ nữa';
    $subject = ($when === '24h' ? '[Nhắc lịch ngày mai] ' : '[Sắp đến giờ chụp] ') . (new DateTime($shoot['start_at']))->format('d/m H:i') . ' - ' . $shoot['customer_name'];
    $body = '<p style="font-size:15px;line-height:1.5;margin:0 0 8px">Chào ' . esc($o['name']) . ', ca chụp sau diễn ra <b>' . $label . '</b>:</p>' . mail_details($shoot) . shoot_team_html((int)$shoot['id']);
    return send_mail($o['email'], $subject, mail_layout('Nhắc lịch chụp', $body));
}
