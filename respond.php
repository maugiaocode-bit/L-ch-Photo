<?php
declare(strict_types=1);
require __DIR__ . '/api/lib.php';

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');

function page(string $title, string $inner): void {
    $app = esc((string)cfg('app_name', 'Lịch Chụp'));
    echo '<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png"><link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">'
        . '<title>' . esc($title) . ' · ' . $app . '</title><style>'
        . '*{box-sizing:border-box}body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;'
        . 'font-family:-apple-system,BlinkMacSystemFont,"SF Pro Display","Segoe UI",Roboto,sans-serif;color:#1c1c1e;'
        . 'background:radial-gradient(900px 500px at 10% 0%,#cfe3ff 0,transparent 60%),radial-gradient(800px 500px at 100% 10%,#ffd9ec 0,transparent 55%),radial-gradient(900px 600px at 50% 100%,#d6f7e6 0,transparent 55%),#f2f5fc}'
        . '.card{width:100%;max-width:460px;background:rgba(255,255,255,.7);backdrop-filter:blur(30px) saturate(180%);-webkit-backdrop-filter:blur(30px) saturate(180%);'
        . 'border:1px solid rgba(255,255,255,.9);border-radius:30px;padding:28px;box-shadow:0 20px 60px rgba(60,80,140,.18),inset 0 1px 0 #fff}'
        . 'h1{font-size:22px;margin:0 0 6px}.sub{color:#8e8e93;font-size:14px;margin:0 0 18px}'
        . 'table{width:100%;border-collapse:collapse;margin-bottom:20px}td{padding:10px 0;border-bottom:1px solid rgba(0,0,0,.06);font-size:15px;vertical-align:top}td:first-child{color:#8e8e93;font-size:13px;width:34%}'
        . '.row{display:flex;gap:10px}button{flex:1;border:0;border-radius:16px;padding:14px;font-size:16px;font-weight:600;color:#fff;cursor:pointer;font-family:inherit}'
        . '.bt{flex:1;display:block;text-align:center;text-decoration:none;border-radius:16px;padding:14px;font-size:16px;font-weight:600;color:#fff;-webkit-tap-highlight-color:transparent}.bt:active{opacity:.7}'
        . '.ok{background:#34c759}.no{background:#ff453a}.pill{display:inline-block;padding:4px 12px;border-radius:99px;font-size:13px;font-weight:600}'
        . '.gal{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:20px}.gal img,.gal video{width:100%;aspect-ratio:1;object-fit:cover;border-radius:14px;background:#000}'
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

    // Xem ảnh/video khách gửi (chỉ người có token của ca này)
    if (isset($_GET['m'])) {
        $m = q('SELECT * FROM shoot_media WHERE id=? AND shoot_id=?', [(int)$_GET['m'], $shoot['id']])->fetch();
        if (!$m) { http_response_code(404); exit; }
        serve_media($m);
    }
    $media = q('SELECT id, mime, orig_name FROM shoot_media WHERE shoot_id=? ORDER BY id', [$shoot['id']])->fetchAll();

    // Nút trên trang dùng liên kết GET (?do=...) vì host miễn phí có thể làm rơi dữ liệu POST trên điện thoại.
    // Liên kết trong email dùng &a=... (chỉ mở trang, không ghi nhận) để bộ quét link của Gmail không tự bấm thay.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['do'])) {
        $act = $_POST['a'] ?? ($_GET['do'] ?? '');
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
    if ($media) {
        $tbl .= '<p class="sub" style="margin:0 0 8px">Ảnh / video khách gửi (' . count($media) . ')</p><div class="gal">';
        foreach ($media as $m) {
            $u = 'respond.php?t=' . $token . '&m=' . (int)$m['id'];
            $tbl .= strpos($m['mime'], 'video/') === 0
                ? '<video src="' . esc($u) . '" controls preload="metadata" playsinline></video>'
                : '<a href="' . esc($u) . '" target="_blank" rel="noopener"><img src="' . esc($u) . '" alt="' . esc($m['orig_name']) . '" loading="lazy"></a>';
        }
        $tbl .= '</div>';
    }

    $st = (string)$a['status'];
    $banner = '';
    if ($st === 'accepted') {
        $banner = '<div style="background:#e3f8e9;color:#1f9d45;border-radius:20px;padding:18px;text-align:center;margin-bottom:18px">'
            . '<div style="font-size:42px;line-height:1">✓</div><div style="font-size:19px;font-weight:700;margin-top:6px">Đã xác nhận: bạn ĐỒNG Ý ca này</div>'
            . '<div style="font-size:13px;margin-top:4px;opacity:.85">' . esc(owner_label($owner)) . ' đã được thông báo.</div></div>';
    } elseif ($st === 'declined') {
        $banner = '<div style="background:#ffe5e3;color:#d63227;border-radius:20px;padding:18px;text-align:center;margin-bottom:18px">'
            . '<div style="font-size:42px;line-height:1">✕</div><div style="font-size:19px;font-weight:700;margin-top:6px">Đã ghi nhận: bạn TỪ CHỐI ca này</div>'
            . '<div style="font-size:13px;margin-top:4px;opacity:.85">' . esc(owner_label($owner)) . ' đã được thông báo.</div></div>';
    }
    $lk = 'respond.php?t=' . $token . '&do=';
    if ($st === 'pending') {
        $buttons = '<div class="row"><a class="bt ok" href="' . esc($lk) . 'accept">✓ Đồng ý</a><a class="bt no" href="' . esc($lk) . 'decline">✕ Không nhận</a></div>';
        $intro = '<h1>Xin chào ' . esc($a['name']) . ' 👋</h1><p class="sub">' . esc(owner_label($owner)) . ' mời bạn nhận ca chụp này.</p>';
    } else {
        $buttons = '<p class="sub" style="margin:0 0 10px;text-align:center">Đổi ý?</p><div class="row">'
            . ($st === 'accepted' ? '<a class="bt no" href="' . esc($lk) . 'decline">✕ Đổi thành: Không nhận</a>' : '<a class="bt ok" href="' . esc($lk) . 'accept">✓ Đổi thành: Đồng ý</a>')
            . '</div>';
        $intro = '<h1>Ca chụp của bạn</h1>';
    }
    $inner = $banner . $intro . $tbl . $buttons;
    page('Phản hồi ca chụp', $inner);
} catch (Throwable $e) {
    error_log('[respond] ' . $e->getMessage());
    page('Lỗi', '<h1>Có lỗi xảy ra</h1><p class="sub">Vui lòng thử lại sau.</p>');
}
