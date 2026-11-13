<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

class ApiError extends Exception {
    public int $status;
    public function __construct(string $m, int $status = 400) { parent::__construct($m); $this->status = $status; }
}

function out($data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function start_session(): void {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    ini_set('session.gc_maxlifetime', '2592000');
    session_name('lichchup');
    session_set_cookie_params(['lifetime' => 2592000, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

function body(): array {
    $raw = file_get_contents('php://input');
    $d = json_decode($raw ?: '[]', true);
    return is_array($d) ? $d : [];
}

function str($v, int $max = 255): string { return mb_substr(trim((string)($v ?? '')), 0, $max); }

function me(): ?array {
    if (empty($_SESSION['uid'])) return null;
    $u = q('SELECT * FROM users WHERE id=?', [(int)$_SESSION['uid']])->fetch();
    if (!$u || !(int)$u['is_active']) { unset($_SESSION['uid']); return null; }
    return $u;
}
function need_user(): array {
    $u = me();
    if (!$u) throw new ApiError('Bạn cần đăng nhập', 401);
    return $u;
}
function expired(array $u): bool {
    return !(int)$u['is_admin'] && $u['plan_expires_at'] && $u['plan_expires_at'] < date('Y-m-d');
}
function need_active(array $u): void {
    if (expired($u)) throw new ApiError('Tài khoản đã hết hạn sử dụng. Vui lòng gia hạn để tiếp tục thêm/sửa lịch.', 402);
}
function user_public(array $u): array {
    return [
        'id' => (int)$u['id'], 'email' => $u['email'], 'name' => $u['name'], 'studio_name' => $u['studio_name'],
        'phone' => $u['phone'], 'is_admin' => (bool)$u['is_admin'], 'plan_expires_at' => $u['plan_expires_at'],
        'expired' => expired($u), 'notify_24h' => (bool)$u['notify_24h'], 'notify_2h' => (bool)$u['notify_2h'],
    ];
}
function valid_dt(string $s): string {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/', $s)) throw new ApiError('Thời gian không hợp lệ');
    $s = str_replace('T', ' ', $s);
    return strlen($s) === 16 ? $s . ':00' : $s;
}
function valid_email(string $e): string {
    $e = strtolower(trim($e));
    if (!filter_var($e, FILTER_VALIDATE_EMAIL)) throw new ApiError('Email không hợp lệ');
    return $e;
}
function new_token(): string { return bin2hex(random_bytes(16)); }

const ROLES = ['photographer', 'assistant', 'makeup'];

function load_shoots(int $uid, string $from, string $to): array {
    $shoots = q('SELECT * FROM shoots WHERE user_id=? AND start_at < ? AND end_at > ? ORDER BY start_at', [$uid, $to, $from])->fetchAll();
    if (!$shoots) return [];
    $ids = array_column($shoots, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $rows = q("SELECT ss.id ss_id, ss.shoot_id, ss.staff_id, ss.role, ss.status, ss.responded_at, s.name, s.email, s.phone
               FROM shoot_staff ss JOIN staff s ON s.id=ss.staff_id WHERE ss.shoot_id IN ($in)", $ids)->fetchAll();
    $by = [];
    foreach ($rows as $r) { $by[$r['shoot_id']][] = $r; }
    foreach ($shoots as &$s) { $s['staff'] = $by[$s['id']] ?? []; }
    return $shoots;
}

start_session();
$r = (string)($_GET['r'] ?? '');
$method = $_SERVER['REQUEST_METHOD'];

try {
    if (!is_file(__DIR__ . '/config.php')) throw new ApiError('Chưa có api/config.php – hãy sao chép từ config.sample.php', 500);

    if ($method === 'POST') {
        $tok = $_SERVER['HTTP_X_CSRF'] ?? '';
        if (!hash_equals($_SESSION['csrf'], (string)$tok)) throw new ApiError('Phiên làm việc không hợp lệ, hãy tải lại trang', 403);
    }

    switch ($r) {
        /* ---------- Tài khoản ---------- */
        case 'me': {
            $u = me();
            out(['user' => $u ? user_public($u) : null, 'csrf' => $_SESSION['csrf'], 'app_name' => cfg('app_name', 'Lịch Chụp'),
                 'can_register' => (bool)cfg('allow_register', true) || (int)q('SELECT COUNT(*) c FROM users')->fetch()['c'] === 0]);
        }
        case 'register': {
            $b = body();
            $count = (int)q('SELECT COUNT(*) c FROM users')->fetch()['c'];
            if ($count > 0 && !cfg('allow_register', true)) throw new ApiError('Hiện chưa mở đăng ký', 403);
            $email = valid_email((string)($b['email'] ?? ''));
            $name = str($b['name'] ?? '', 120);
            $pass = (string)($b['password'] ?? '');
            if ($name === '') throw new ApiError('Vui lòng nhập tên');
            if (strlen($pass) < 8) throw new ApiError('Mật khẩu tối thiểu 8 ký tự');
            if (q('SELECT id FROM users WHERE email=?', [$email])->fetch()) throw new ApiError('Email này đã được đăng ký');
            $trial = (int)cfg('trial_days', 0);
            $exp = ($count > 0 && $trial > 0) ? date('Y-m-d', strtotime("+$trial days")) : null;
            q('INSERT INTO users (email,password_hash,name,studio_name,is_admin,plan_expires_at,created_at) VALUES (?,?,?,?,?,?,?)',
              [$email, password_hash($pass, PASSWORD_DEFAULT), $name, str($b['studio_name'] ?? '', 160), $count === 0 ? 1 : 0, $exp, now_str()]);
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)db()->lastInsertId();
            out(['user' => user_public(me())]);
        }
        case 'login': {
            $b = body();
            $u = q('SELECT * FROM users WHERE email=?', [strtolower(trim((string)($b['email'] ?? '')))])->fetch();
            if (!$u || !password_verify((string)($b['password'] ?? ''), $u['password_hash'])) { usleep(700000); throw new ApiError('Sai email hoặc mật khẩu', 401); }
            if (!(int)$u['is_active']) throw new ApiError('Tài khoản đã bị khóa', 403);
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$u['id'];
            out(['user' => user_public($u)]);
        }
        case 'logout': {
            unset($_SESSION['uid']);
            session_regenerate_id(true);
            out(['ok' => true]);
        }
        case 'profile_save': {
            $u = need_user(); $b = body();
            $name = str($b['name'] ?? '', 120);
            if ($name === '') throw new ApiError('Vui lòng nhập tên');
            q('UPDATE users SET name=?, studio_name=?, phone=?, notify_24h=?, notify_2h=? WHERE id=?',
              [$name, str($b['studio_name'] ?? '', 160), str($b['phone'] ?? '', 40), !empty($b['notify_24h']) ? 1 : 0, !empty($b['notify_2h']) ? 1 : 0, $u['id']]);
            out(['user' => user_public(me())]);
        }
        case 'password_change': {
            $u = need_user(); $b = body();
            if (!password_verify((string)($b['old'] ?? ''), $u['password_hash'])) throw new ApiError('Mật khẩu hiện tại không đúng');
            if (strlen((string)($b['new'] ?? '')) < 8) throw new ApiError('Mật khẩu mới tối thiểu 8 ký tự');
            q('UPDATE users SET password_hash=? WHERE id=?', [password_hash((string)$b['new'], PASSWORD_DEFAULT), $u['id']]);
            out(['ok' => true]);
        }

        /* ---------- Nhân sự ---------- */
        case 'staff_list': {
            $u = need_user();
            out(['staff' => q('SELECT id,name,email,phone,roles,note FROM staff WHERE user_id=? ORDER BY name', [$u['id']])->fetchAll()]);
        }
        case 'staff_save': {
            $u = need_user(); need_active($u); $b = body();
            $name = str($b['name'] ?? '', 120);
            if ($name === '') throw new ApiError('Vui lòng nhập tên');
            $email = valid_email((string)($b['email'] ?? ''));
            $roles = array_values(array_intersect(ROLES, (array)($b['roles'] ?? [])));
            if (!$roles) throw new ApiError('Chọn ít nhất 1 vai trò');
            $vals = [$name, $email, str($b['phone'] ?? '', 40), implode(',', $roles), str($b['note'] ?? '', 255)];
            if (!empty($b['id'])) {
                $st = q('UPDATE staff SET name=?,email=?,phone=?,roles=?,note=? WHERE id=? AND user_id=?', array_merge($vals, [(int)$b['id'], $u['id']]));
                $id = (int)$b['id'];
            } else {
                q('INSERT INTO staff (name,email,phone,roles,note,user_id,created_at) VALUES (?,?,?,?,?,?,?)', array_merge($vals, [$u['id'], now_str()]));
                $id = (int)db()->lastInsertId();
            }
            out(['id' => $id]);
        }
        case 'staff_delete': {
            $u = need_user(); $b = body();
            q('DELETE FROM staff WHERE id=? AND user_id=?', [(int)($b['id'] ?? 0), $u['id']]);
            out(['ok' => true]);
        }

        /* ---------- Lịch chụp ---------- */
        case 'shoots_list': {
            $u = need_user();
            $from = valid_dt((string)($_GET['from'] ?? date('Y-m-01') . ' 00:00'));
            $to = valid_dt((string)($_GET['to'] ?? date('Y-m-t') . ' 23:59'));
            out(['shoots' => load_shoots((int)$u['id'], $from, $to)]);
        }
        case 'shoot_save': {
            $u = need_user(); need_active($u); $b = body();
            $start = valid_dt((string)($b['start_at'] ?? ''));
            $end = valid_dt((string)($b['end_at'] ?? ''));
            if ($end <= $start) throw new ApiError('Giờ kết thúc phải sau giờ bắt đầu');
            $cust = str($b['customer_name'] ?? '', 160);
            if ($cust === '') throw new ApiError('Vui lòng nhập tên khách hàng');
            $title = str($b['title'] ?? '', 190) ?: ('Chụp - ' . $cust);
            $data = [$title, $cust, str($b['customer_phone'] ?? '', 40), str($b['location'] ?? '', 255), (string)($b['notes'] ?? ''), $start, $end];

            // Chuẩn hóa danh sách nhân sự
            $want = [];
            foreach ((array)($b['staff'] ?? []) as $it) {
                $sid = (int)($it['staff_id'] ?? 0); $role = (string)($it['role'] ?? '');
                if (!in_array($role, ROLES, true)) continue;
                $want[$sid . '|' . $role] = ['staff_id' => $sid, 'role' => $role];
            }
            if ($want) {
                $ids = array_unique(array_column($want, 'staff_id'));
                $in = implode(',', array_fill(0, count($ids), '?'));
                $okIds = array_column(q("SELECT id FROM staff WHERE user_id=? AND id IN ($in)", array_merge([$u['id']], $ids))->fetchAll(), 'id');
                foreach ($want as $k => $w) if (!in_array((string)$w['staff_id'], array_map('strval', $okIds), true)) unset($want[$k]);
            }

            $mails = []; // [kind, assignment id]
            $pdo = db();
            $pdo->beginTransaction();
            try {
                if (!empty($b['id'])) {
                    $id = (int)$b['id'];
                    $old = q('SELECT * FROM shoots WHERE id=? AND user_id=?', [$id, $u['id']])->fetch();
                    if (!$old) throw new ApiError('Không tìm thấy ca chụp', 404);
                    $changed = $old['start_at'] !== $start || $old['end_at'] !== $end || $old['location'] !== $data[3];
                    q('UPDATE shoots SET title=?,customer_name=?,customer_phone=?,location=?,notes=?,start_at=?,end_at=?' . ($changed ? ',owner_r24=0,owner_r2=0' : '') . ' WHERE id=?', array_merge($data, [$id]));
                    $cur = q('SELECT * FROM shoot_staff WHERE shoot_id=?', [$id])->fetchAll();
                    $curKeys = [];
                    foreach ($cur as $c) {
                        $k = $c['staff_id'] . '|' . $c['role'];
                        $curKeys[$k] = true;
                        if (!isset($want[$k])) {
                            $mails[] = ['cancel', $c];
                            q('DELETE FROM shoot_staff WHERE id=?', [$c['id']]);
                        } elseif ($changed) {
                            q("UPDATE shoot_staff SET status='pending', r24=0, r2=0, invited_at=?, responded_at=NULL WHERE id=?", [now_str(), $c['id']]);
                            $mails[] = ['update', $c['id']];
                        }
                    }
                    foreach ($want as $k => $w) {
                        if (isset($curKeys[$k])) continue;
                        q('INSERT INTO shoot_staff (shoot_id,staff_id,role,token,invited_at) VALUES (?,?,?,?,?)', [$id, $w['staff_id'], $w['role'], new_token(), now_str()]);
                        $mails[] = ['invite', (int)$pdo->lastInsertId()];
                    }
                } else {
                    q('INSERT INTO shoots (title,customer_name,customer_phone,location,notes,start_at,end_at,user_id,created_at) VALUES (?,?,?,?,?,?,?,?,?)', array_merge($data, [$u['id'], now_str()]));
                    $id = (int)$pdo->lastInsertId();
                    foreach ($want as $w) {
                        q('INSERT INTO shoot_staff (shoot_id,staff_id,role,token,invited_at) VALUES (?,?,?,?,?)', [$id, $w['staff_id'], $w['role'], new_token(), now_str()]);
                        $mails[] = ['invite', (int)$pdo->lastInsertId()];
                    }
                }
                $pdo->commit();
            } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }

            // Gửi email sau khi lưu thành công
            $shoot = get_shoot($id);
            $sent = 0; $failed = 0;
            foreach ($mails as [$kind, $ref]) {
                if ($kind === 'cancel') {
                    $st = q('SELECT name,email FROM staff WHERE id=?', [$ref['staff_id']])->fetch();
                    $a = $ref + ($st ?: ['name' => '', 'email' => '']);
                } else {
                    $a = q('SELECT ss.*, s.name, s.email FROM shoot_staff ss JOIN staff s ON s.id=ss.staff_id WHERE ss.id=?', [$ref])->fetch();
                }
                if (!$a || !$a['email']) continue;
                send_assignment_mail($shoot, $a, $kind) ? $sent++ : $failed++;
            }
            out(['id' => $id, 'sent' => $sent, 'failed' => $failed]);
        }
        case 'shoot_delete': {
            $u = need_user(); $b = body();
            $id = (int)($b['id'] ?? 0);
            $shoot = q('SELECT * FROM shoots WHERE id=? AND user_id=?', [$id, $u['id']])->fetch();
            if (!$shoot) throw new ApiError('Không tìm thấy ca chụp', 404);
            $as = q('SELECT ss.*, s.name, s.email FROM shoot_staff ss JOIN staff s ON s.id=ss.staff_id WHERE ss.shoot_id=? AND ss.status<>"declined"', [$id])->fetchAll();
            q('DELETE FROM shoots WHERE id=?', [$id]);
            foreach ($as as $a) send_assignment_mail($shoot, $a, 'cancel');
            out(['ok' => true]);
        }
        case 'assignment_resend': {
            $u = need_user(); need_active($u); $b = body();
            $a = q('SELECT ss.*, s.name, s.email, sh.user_id FROM shoot_staff ss JOIN staff s ON s.id=ss.staff_id JOIN shoots sh ON sh.id=ss.shoot_id WHERE ss.id=? AND sh.user_id=?', [(int)($b['id'] ?? 0), $u['id']])->fetch();
            if (!$a) throw new ApiError('Không tìm thấy', 404);
            $ok = send_assignment_mail(get_shoot((int)$a['shoot_id']), $a, 'invite');
            out(['sent' => $ok ? 1 : 0, 'failed' => $ok ? 0 : 1]);
        }
        case 'assignment_status': {
            $u = need_user(); $b = body();
            $st = (string)($b['status'] ?? '');
            if (!in_array($st, ['pending', 'accepted', 'declined'], true)) throw new ApiError('Trạng thái không hợp lệ');
            q('UPDATE shoot_staff ss JOIN shoots sh ON sh.id=ss.shoot_id SET ss.status=?, ss.responded_at=? WHERE ss.id=? AND sh.user_id=?',
              [$st, $st === 'pending' ? null : now_str(), (int)($b['id'] ?? 0), $u['id']]);
            out(['ok' => true]);
        }

        /* ---------- Quản trị (bán dịch vụ) ---------- */
        case 'admin_users': {
            $u = need_user();
            if (!(int)$u['is_admin']) throw new ApiError('Không có quyền', 403);
            $rows = q('SELECT u.id,u.email,u.name,u.studio_name,u.is_admin,u.is_active,u.plan_expires_at,u.created_at,
                       (SELECT COUNT(*) FROM shoots s WHERE s.user_id=u.id) shoots FROM users u ORDER BY u.id DESC')->fetchAll();
            out(['users' => $rows]);
        }
        case 'admin_user_update': {
            $u = need_user();
            if (!(int)$u['is_admin']) throw new ApiError('Không có quyền', 403);
            $b = body();
            $exp = trim((string)($b['plan_expires_at'] ?? ''));
            if ($exp !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $exp)) throw new ApiError('Ngày hết hạn không hợp lệ');
            q('UPDATE users SET is_active=?, plan_expires_at=? WHERE id=? AND is_admin=0', [!empty($b['is_active']) ? 1 : 0, $exp === '' ? null : $exp, (int)($b['id'] ?? 0)]);
            out(['ok' => true]);
        }

        default:
            throw new ApiError('Không tìm thấy', 404);
    }
} catch (ApiError $e) {
    out(['error' => $e->getMessage()], $e->status);
} catch (Throwable $e) {
    error_log('[api] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    out(['error' => 'Lỗi máy chủ. Kiểm tra cấu hình database trong api/config.php'], 500);
}
