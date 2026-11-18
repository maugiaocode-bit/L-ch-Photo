<?php
// Phục vụ giao diện qua PHP để host miễn phí (có lớp kiểm tra bot bằng cookie) xử lý ngay ở lần mở trang đầu tiên,
// nhờ vậy các lệnh gọi api/index.php sau đó đã có cookie và trả JSON bình thường.
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');
readfile(__DIR__ . '/index.html');
