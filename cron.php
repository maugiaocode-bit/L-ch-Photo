<?php
declare(strict_types=1);
/**
 * Gửi nhắc lịch: trước 1 ngày (24h) và trước 2 tiếng.
 * Cron job trên Hostinger (chạy mỗi 5 phút):
 *   php /home/uXXXX/domains/tenmien.com/public_html/cron.php
 * hoặc (nếu dùng URL):  https://tenmien.com/cron.php?key=CRON_KEY
 */
require __DIR__ . '/api/lib.php';

$cli = PHP_SAPI === 'cli';
if (!$cli) {
    header('Content-Type: text/plain; charset=utf-8');
    $key = (string)cfg('cron_key', '');
    if ($key === '' || !hash_equals($key, (string)($_GET['key'] ?? ''))) { http_response_code(403); exit("Forbidden\n"); }
}
set_time_limit(120);

$now = new DateTime();
$n = $now->format('Y-m-d H:i:s');
$in24 = (clone $now)->modify('+24 hours')->format('Y-m-d H:i:s');
$in2 = (clone $now)->modify('+2 hours')->format('Y-m-d H:i:s');
$sent = 0; $failed = 0;

foreach (['24h' => [$in24, 'r24', 'notify_24h', 'owner_r24'], '2h' => [$in2, 'r2', 'notify_2h', 'owner_r2']] as $when => [$limit, $flag, $userFlag, $ownerFlag]) {
    // --- Nhắc từng người trong ca (chưa từ chối) ---
    $rows = q("SELECT ss.id ss_id, ss.role, ss.status, ss.token, ss.invited_at, s.name, s.email, sh.*
               FROM shoot_staff ss
               JOIN staff s ON s.id=ss.staff_id
               JOIN shoots sh ON sh.id=ss.shoot_id
               JOIN users u ON u.id=sh.user_id
               WHERE ss.$flag=0 AND ss.status<>'declined' AND u.is_active=1 AND u.$userFlag=1
                 AND sh.start_at > ? AND sh.start_at <= ?", [$n, $limit])->fetchAll();
    foreach ($rows as $row) {
        // Nếu vừa được mời sau mốc nhắc 24h thì bỏ qua (tránh gửi trùng với email mời)
        $skip = $when === '24h' && $row['invited_at'] > (new DateTime($row['start_at']))->modify('-24 hours')->format('Y-m-d H:i:s');
        $ok = $skip ? true : send_reminder_mail($row, ['name' => $row['name'], 'email' => $row['email'], 'role' => $row['role'], 'status' => $row['status'], 'token' => $row['token']] , $when);
        if ($ok) { q("UPDATE shoot_staff SET $flag=1 WHERE id=?", [$row['ss_id']]); if (!$skip) $sent++; } else { $failed++; }
    }

    // --- Nhắc chủ studio ---
    $own = q("SELECT sh.* FROM shoots sh JOIN users u ON u.id=sh.user_id
              WHERE sh.$ownerFlag=0 AND u.is_active=1 AND u.$userFlag=1 AND sh.start_at > ? AND sh.start_at <= ?", [$n, $limit])->fetchAll();
    foreach ($own as $sh) {
        $skip = $when === '24h' && $sh['created_at'] > (new DateTime($sh['start_at']))->modify('-24 hours')->format('Y-m-d H:i:s');
        $ok = $skip ? true : send_owner_reminder($sh, $when);
        if ($ok) { q("UPDATE shoots SET $ownerFlag=1 WHERE id=?", [$sh['id']]); if (!$skip) $sent++; } else { $failed++; }
    }
}

echo date('c') . " sent=$sent failed=$failed\n";
