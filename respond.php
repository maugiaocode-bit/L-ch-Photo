<?php
declare(strict_types=1);
require __DIR__ . '/api/lib.php';

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');

function page(string $title, string $inner): void {
    $app = esc((string)cfg('app_name', 'Lịch Chụp'));
    echo '<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . esc($title) . ' · ' . $app . '</title><style>'
        . '*{box-sizing:border-box}body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;'
        . 'font-family:-apple-system,BlinkMacSystemFont,"SF Pro Display","Segoe UI",Roboto,sans-serif;color:#1c1c1e;'
        . 'background:radial-gradient(900px 500px at 10% 0%,#cfe3ff 0,transparent 60%),radial-gradient(800px 500px at 100% 10%,#ffd9ec 0,transparent 55%),radial-gradient(900px 600px at 50% 100%,#d6f7e6 0,transparent 55%),#f2f5fc}'
        . '.card{width:100%;max-width:460px;background:rgba(255,255,255,.7);backdrop-filter:blur(30px) saturate(180%);-webkit-backdrop-filter:blur(30px) saturate(180%);'
        . 'border:1px solid rgba(255,255,255,.9);border-radius:30px;padding:28px;box-shadow:0 20px 60px rgba(60,80,140,.18),inset 0 1px 0 #fff}'
        . 'h1{font-size:22px;margin:0 0 6px}.sub{color:#8e8e93;font-size:14px;margin:0 0 18px}'
        . 'table{width:100%;border-collapse:collapse;margin-bottom:20px}td{padding:10px 0;border-bottom:1px solid rgba(0,0,0,.06);font-size:15px;vertical-align:top}td:first-child{color:#8e8e93;font-size:13px;width:34%}'
        . '.row{display:flex;gap:10px}button{flex:1;border:0;border-radius:16px;padding:14px;font-size:16px;font-weight:600;color:#fff;cursor:pointer;font-family:inherit}'
        . '.ok{background:#34c759}.no{background:#ff453a}.pill{display:inline-block;padding:4px 12px;border-radius:99px;font-size:13px;font-weight:600}'
        . '.p-accepted{background:#e3f8e9;color:#1f9d45}.p-declined{background:#ffe5e3;color:#d63227}.p-pending{background:#fff1dc;color:#c76f00}'
        . '</style></head><body><div class="card">' . $inner . '</div></body></html>';
    exit;
}

try {
    $token = preg_replace('/[^a-f0-9]/', '', strtolower((string)($_GET['t'] ?? $_POST['t'] ?? '')));
    if (strlen($token) !== 32) page('Liên kết không hợp lệ', '<h1>Liên kết không hợp lệ</h1><p class="sub">Vui lòng mở lại liên kết trong email.</p>');

    $a = q('SELECT ss.*, s.name, s.email FROM shoot_staff ss JOIN staff s ON s.id=ss.staff_id WHERE ss.token=?', [$token])->fetch();
    if (!$a) page('Không tìm thấy', '<h1>Không tìm thấy ca chụp</h1><p class="sub">Ca này có thể đã bị hủy hoặc thay đổi.</p>');
    $shoot = get_shoot((int)$a['shoot_id']);
    $owner = get_owner((int)$shoot['user_id']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $act = $_POST['a'] ?? '';
        if (in_array($act, ['accept', 'decline'], true)) {
            $new = $act === 'accept' ? 'accepted' : 'declined';
            if ($new !== $a['status']) {
                q('UPDATE shoot_staff SET status=?, responded_at=? WHERE id=?', [$new, now_str(), $a['id']]);
                if ($owner['email']) {
                    $body = '<p style="font-size:15px;line-height:1.5"><b>' . esc($a['name']) . '</b> (' . esc(role_label($a['role'])) . ') đã <b>'
                        . ($new === 'accepted' ? 'đồng ý' : 'từ chối') . '</b> ca chụp:</p>' . mail_details($shoot) . shoot_team_html((int)$shoot['id']);
                    send_mail($owner['email'], ($new === 'accepted' ? '✓ ' : '✕ ') . $a['name'] . ' ' . ($new === 'accepted' ? 'đồng ý' : 'từ chối') . ' ca ' . (new DateTime($shoot['start_at']))->format('d/m H:i'),
                        mail_layout('Phản hồi từ ' . esc($a['name']), $body));
                }
            }
            $a['status'] = $new;
        }
        header('Location: respond.php?t=' . $token . '&done=1');
        exit;
    }

    $rows = [['Khách hàng', $shoot['customer_name'] . ($shoot['customer_phone'] ? ' • ' . $shoot['customer_phone'] : '')],
        ['Thời gian', vn_range($shoot['start_at'], $shoot['end_at'])], ['Địa điểm', $shoot['location']],
        ['Vai trò của bạn', role_label($a['role'])]];
    if (!empty($shoot['notes'])) $rows[] = ['Ghi chú', $shoot['notes']];
    $tbl = '<table>';
    foreach ($rows as [$k, $v]) if ($v !== '') $tbl .= '<tr><td>' . esc($k) . '</td><td>' . nl2br(esc($v)) . '</td></tr>';
    $tbl .= '</table>';

    $inner = '<h1>Xin chào ' . esc($a['name']) . ' 👋</h1><p class="sub">' . esc(owner_label($owner)) . ' mời bạn nhận ca chụp này.</p>' . $tbl
        . '<p class="sub" style="margin-bottom:14px">Trạng thái hiện tại: <span class="pill p-' . esc($a['status']) . '">' . esc(status_label($a['status'])) . '</span>'
        . (isset($_GET['done']) ? ' &nbsp;✓ Đã ghi nhận' : '') . '</p>'
        . '<form method="post" class="row"><input type="hidden" name="t" value="' . esc($token) . '">'
        . '<button class="ok" name="a" value="accept">✓ Đồng ý</button><button class="no" name="a" value="decline">✕ Không nhận</button></form>';
    page('Phản hồi ca chụp', $inner);
} catch (Throwable $e) {
    error_log('[respond] ' . $e->getMessage());
    page('Lỗi', '<h1>Có lỗi xảy ra</h1><p class="sub">Vui lòng thử lại sau.</p>');
}
