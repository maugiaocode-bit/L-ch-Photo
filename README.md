# Lịch Chụp – Quản lý lịch trình cho thợ chụp ảnh

Web app kiểu Google Calendar với giao diện sáng, phong cách iOS (kính mờ "Liquid Glass").

**Tính năng**
- Lịch Tháng / Tuần / Ngày / Lịch trình, hiển thị **lịch âm** (mùng 1, rằm, ngày lễ âm lịch, can chi)
- Mỗi ca có thông tin khách, địa điểm, ghi chú + **thợ chụp, thợ phụ, makeup** chọn từ danh sách đã lưu
- Lưu ca → hệ thống **gửi email mời** cho từng người, họ bấm **Đồng ý / Không nhận**; chủ studio nhận email phản hồi
- **Nhắc lịch tự động** trước 1 ngày và trước 2 tiếng cho mọi người trong ca (và chủ studio)
- Cảnh báo trùng lịch của nhân sự; đổi giờ/địa điểm → mọi người nhận email cập nhật và xác nhận lại; xóa ca → email báo hủy
- **Nhiều tài khoản** (mỗi studio một dữ liệu riêng), có trang Quản trị: đặt ngày hết hạn / khóa tài khoản để bán dịch vụ

**Công nghệ:** HTML/CSS/JS thuần (không cần build) + PHP 8 + MySQL – chạy được trên Hostinger shared hosting.

---

## 1. Đưa lên GitHub

(Cần cài [Git](https://git-scm.com/download/win).) Tạo repo trống trên github.com (để **Private** nếu bán), rồi:

```bash
cd "C:\app Lịch"
git init
git add .
git commit -m "Lịch Chụp v1"
git branch -M main
git remote add origin https://github.com/TEN_BAN/lich-chup.git
git push -u origin main
```

`api/config.php` (chứa mật khẩu) đã được `.gitignore` chặn, sẽ **không** lên GitHub.

## 2. Cài trên Hostinger

1. **Database:** hPanel → Databases → MySQL Databases → tạo database + user. Mở phpMyAdmin → Import file `schema.sql`.
2. **Gửi email** (xem 3 cách trong `api/config.sample.php`):
   - *Gmail của bạn:* bật xác minh 2 bước → tạo **Mật khẩu ứng dụng** tại myaccount.google.com/apppasswords → điền `smtp.gmail.com`, cổng 587, `tls`, `mail_from` = địa chỉ Gmail.
   - *Hộp thư tên miền:* hPanel → Emails → tạo `no-reply@tenmiencuaban.com`, SMTP `smtp.hostinger.com`, cổng 465, SSL.
   - *Host miễn phí chặn SMTP:* dùng API Brevo (`mail_driver` = `brevo`).
3. **Đưa code lên** (chọn 1 cách):
   - *Cách A – Git của Hostinger:* hPanel → Advanced → Git → nhập URL repo, nhánh `main`, thư mục `public_html` → Create → bấm Deploy (bật Auto deployment nếu muốn). Repo Private thì thêm Deploy key mà hPanel cung cấp vào GitHub (Settings → Deploy keys).
   - *Cách B – GitHub Actions + FTP:* tạo secrets `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`, `FTP_DIR` (vd `/public_html/`) trong GitHub → mỗi lần push tự upload (`.github/workflows/deploy.yml`).
4. **Tạo cấu hình:** trong File Manager, vào `public_html/api/`, sao chép `config.sample.php` → `config.php` rồi điền DB, `base_url`, SMTP, `cron_key`.
5. **Bật SSL** cho tên miền (hPanel → Security → SSL).
6. **Cron nhắc lịch:** hPanel → Advanced → Cron Jobs → mỗi **5 phút**:
   ```
   php /home/uXXXXXXXX/domains/tenmiencuaban.com/public_html/cron.php
   ```
   (đường dẫn đúng xem ở File Manager). Hoặc gọi URL: `https://tenmiencuaban.com/cron.php?key=CRON_KEY`.
7. Mở website → **Đăng ký** – tài khoản đầu tiên tự động là **Admin**.

## 3. Bán cho nhiều người
- `allow_register` (mở/đóng đăng ký) và `trial_days` (số ngày dùng thử) trong `api/config.php`.
- Vào **Quản trị** để đặt *ngày hết hạn* hoặc khóa từng tài khoản. Hết hạn → chỉ xem, không thêm/sửa được.
- Thanh toán/gia hạn hiện làm thủ công (chuyển khoản → admin đặt ngày hết hạn mới).

## 4. Luồng email
| Sự kiện | Ai nhận |
|---|---|
| Lưu ca mới | Từng thợ chụp/phụ/makeup được chọn (nút Đồng ý / Không nhận) |
| Thợ bấm phản hồi | Chủ studio |
| Sửa giờ/địa điểm | Mọi người trong ca (xác nhận lại) |
| Xóa ca | Mọi người chưa từ chối |
| Trước 24 giờ & trước 2 giờ | Mọi người chưa từ chối + chủ studio |

> Nếu email vào Spam: dùng hộp thư đúng tên miền của bạn (SPF/DKIM do Hostinger tự cấu hình) thay vì Gmail.
