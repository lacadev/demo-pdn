# Hướng dẫn cập nhật & phân phối LacaDev Child Theme

> **Mục đích:** Khi bạn cập nhật theme `lacadev` (parent), các site khách hàng dùng `lacadev-child` sẽ tự động nhận thông báo update và cập nhật qua WP Admin — không cần FTP hay đăng nhập từng site.

---

## Kiến trúc tổng quan

```
[Máy dev — VS Code]
    ↓ 1. Sửa code
    ↓ 2. Chạy build-release.sh
[lacadev.com/theme-updates/]
    ├── lacadev-child.json      ← thông tin version mới
    └── lacadev-child-X.X.zip  ← file theme đóng gói
         ↑ WordPress các site check mỗi 12h
[phucdainam.com / site khác]
    └── WP Admin → "Có bản cập nhật" → Click "Cập nhật ngay"
```

---

## PHẦN 1: Lần đầu setup (chỉ làm 1 lần)

### Bước 1.1 — Tạo thư mục trên hosting lacadev.com

Truy cập hosting lacadev.com (cPanel / FileManager / FTP) và tạo thư mục:

```
/public_html/theme-updates/
```

### Bước 1.2 — Upload file cấu hình

Upload 2 file từ thư mục `theme-server/` trong theme lên hosting:

| File local | Đích trên server |
|---|---|
| `theme-server/.htaccess` | `/public_html/theme-updates/.htaccess` |
| `theme-server/lacadev-child.json` | `/public_html/theme-updates/lacadev-child.json` |

### Bước 1.3 — Kiểm tra JSON accessible

Mở trình duyệt kiểm tra URL này phải trả về JSON:

```
https://lacadev.com/theme-updates/lacadev-child.json
```

### Bước 1.4 — Cài lacadev-child lên site khách hàng

- Upload `lacadev-child.zip` qua **WP Admin → Appearance → Themes → Add New → Upload**
- Hoặc copy thư mục qua FTP vào `/wp-content/themes/lacadev-child/`
- Kích hoạt theme.

Sau bước này, theme sẽ tự động check update — bạn không cần làm gì thêm cho lần sau.

---

## PHẦN 2: Quy trình update (mỗi lần có thay đổi mới)

### Bước 2.1 — Sửa code trên máy dev

Sửa code trong thư mục `lacadev` (parent theme).

> ⚠️ **Lưu ý:** Những file dưới đây **chỉ có ở parent**, KHÔNG có ở child  
> (đây là tính năng Project Manager đã xóa khỏi child):
> - `app/src/PostTypes/project.php`
> - `app/src/Databases/`
> - `app/src/Models/ProjectLog.php`, `ProjectAlert.php`
> - `app/src/Settings/LacaTools/` (các file AI, Tracker, Portal...)
> - `theme/single-project.php`
> - `theme/page_templates/template-client-portal.php`

Sau khi sửa xong ở parent, nếu cần sync sang child: copy file bằng tay hoặc chạy rsync (xem Phần 4 bên dưới).

### Bước 2.2 — Chạy build script

Mở Terminal, cd vào thư mục child:

```bash
cd /path/to/lacadev-child
./build-release.sh 3.2 "Mô tả thay đổi ngắn gọn"
```

**Ví dụ thực tế:**
```bash
./build-release.sh 3.2 "Thêm widget hiển thị dịch vụ, sửa lỗi mobile menu"
```

Script tự động làm:
- ✅ Cập nhật `Version: 3.2` trong `theme/style.css`
- ✅ Cập nhật `theme-server/lacadev-child.json` với version + URL mới + changelog
- ✅ Tạo file `releases/lacadev-child-3.2.zip`
- ✅ Hiện hướng dẫn upload lên server

### Bước 2.3 — Upload lên lacadev.com

Sau khi script chạy xong, upload 2 file lên hosting:

```bash
# Thay your-user và /path/ bằng thông tin hosting thực tế
scp releases/lacadev-child-3.2.zip your-user@lacadev.com:/public_html/theme-updates/
scp theme-server/lacadev-child.json your-user@lacadev.com:/public_html/theme-updates/
```

Hoặc upload thủ công qua **cPanel → File Manager**.

### Bước 2.4 — Xác nhận đã upload đúng

Mở trình duyệt kiểm tra:

```
https://lacadev.com/theme-updates/lacadev-child.json
```

Phải thấy `"version": "3.2"` trong JSON.

### Bước 2.5 — Chờ hoặc force-check

- **Tự động:** WordPress các site client sẽ check trong vòng **12 giờ**
- **Force ngay:** Vào WP Admin của site client → **Dashboard → Updates** → Nhấn "Check Again"

---

## PHẦN 3: Thông báo update ở site khách hàng

Sau khi upload, WP Admin của `phucdainam.com` (và các site khác) sẽ hiện:

```
Appearance → Themes:
┌──────────────────────────────────────────────┐
│ LacaDev Child                    v3.1         │
│ ⚠️ Update available: v3.2                     │
│    [Update now]                               │
└──────────────────────────────────────────────┘
```

Khách hàng (hoặc bạn) click **"Update now"** → WordPress tự download từ `lacadev.com/theme-updates/` và cài đặt.

> 💡 **Muốn update tự động hoàn toàn không cần click?**  
> Thêm vào `hooks.php` của child theme:
> ```php
> add_filter('auto_update_theme', function($update, $item) {
>     return $item->theme === 'lacadev-child' ? true : $update;
> }, 10, 2);
> ```

---

## PHẦN 4: Sync code từ parent sang child (tùy chọn)

Khi cập nhật `lacadev` (parent), dùng script sau để copy các file chung sang child mà **không đụng file đã xóa**:

```bash
rsync -av --checksum \
  --exclude='app/src/PostTypes/project.php' \
  --exclude='app/src/Databases/' \
  --exclude='app/src/Models/ProjectLog.php' \
  --exclude='app/src/Models/ProjectAlert.php' \
  --exclude='app/src/Settings/LacaTools/AIChatHandler.php' \
  --exclude='app/src/Settings/LacaTools/AITranslation*' \
  --exclude='app/src/Settings/LacaTools/ProjectNotificationHandler.php' \
  --exclude='app/src/Settings/LacaTools/ProjectPdfExporter.php' \
  --exclude='app/src/Settings/LacaTools/ProjectReportsManager.php' \
  --exclude='app/src/Settings/LacaTools/ProjectTrackerGenerator.php' \
  --exclude='app/src/Settings/LacaTools/ClientPortalEndpoint.php' \
  --exclude='app/src/Settings/LacaTools/TrackerEndpointHandler.php' \
  --exclude='app/src/Settings/LacaTools/ManagementExperience.php' \
  --exclude='app/src/Settings/LacaTools/Management/' \
  --exclude='resources/scripts/admin/project.js' \
  --exclude='resources/scripts/admin/ai-chat.js' \
  --exclude='resources/scripts/theme/micro-interactions.js' \
  --exclude='resources/scripts/theme/project-block.js' \
  --exclude='resources/scripts/theme/pages/about-laca.js' \
  --exclude='resources/styles/admin/_project.scss' \
  --exclude='resources/styles/admin/_admin-custom.scss' \
  --exclude='resources/styles/theme/pages/_client-portal.scss' \
  --exclude='resources/styles/theme/pages/_cpt.scss' \
  --exclude='resources/styles/theme/components/_micro-interactions.scss' \
  --exclude='theme/single-project.php' \
  --exclude='theme/page_templates/template-client-portal.php' \
  --exclude='app/src/Settings/AdminSettings.php' \
  --exclude='app/hooks.php' \
  --exclude='style.css' \
  /path/to/lacadev/ \
  /path/to/lacadev-child/
```

> ⚠️ Exclude `AdminSettings.php` và `hooks.php` vì child có version riêng đã bỏ Project Manager.

---

## PHẦN 5: Cấu trúc file liên quan

```
lacadev-child/
├── build-release.sh              ← Script build & release
├── HUONG-DAN-UPDATE.md           ← File này
├── theme-server/
│   ├── lacadev-child.json        ← Upload lên lacadev.com/theme-updates/
│   └── .htaccess                 ← Upload lên lacadev.com/theme-updates/
├── releases/                     ← Thư mục chứa file zip (tự tạo khi build)
│   └── lacadev-child-X.X.zip
└── app/src/Settings/
    └── ThemeUpdater.php          ← Class xử lý auto-update trong WP
```

---

## Tóm tắt nhanh

> Mỗi lần update chỉ cần 3 bước:
> ```
> 1. Sửa code
> 2. ./build-release.sh 3.X "Mô tả"
> 3. Upload 2 file lên lacadev.com/theme-updates/
> ```
