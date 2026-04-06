# Hướng dẫn: Update Theme & Đồng bộ Blocks cho Site Khách hàng

---

## Kiến trúc 3 theme

```text
lacadev/                  ← Theme gốc của lacadev.com (dev ở đây)
lacadev-client/           ← Parent theme cài trên site khách hàng
lacadev-client-child/     ← Child theme cài trên site khách hàng (active theme)
```

**Quy tắc cốt lõi:**

- Toàn bộ code custom phát triển ở **`lacadev/`**
- Khi xong → sync sang **`lacadev-client/`** (loại bỏ tính năng nội bộ)
- Site khách hàng cài **cả 2**: `lacadev-client` + `lacadev-client-child`, kích hoạt **child theme**
- `lacadev-client-child/` không bao giờ bị ghi đè khi update `lacadev-client`

---

## PHẦN 1: Cài đặt lần đầu cho site khách hàng

### Bước 1.1 — Tạo thư mục trên lacadev.com

Truy cập hosting lacadev.com và tạo thư mục:

```text
/public_html/theme-updates/
```

Upload 2 file từ `lacadev-client/theme-server/` lên hosting:

| File local | Đích trên server |
| --- | --- |
| `theme-server/.htaccess` | `/public_html/theme-updates/.htaccess` |
| `theme-server/lacadev-client.json` | `/public_html/theme-updates/lacadev-client.json` |

Kiểm tra JSON trả về đúng:

```text
https://lacadev.com/theme-updates/lacadev-client.json
```

### Bước 1.2 — Cài theme lên site khách hàng

Cài **cả 2 theme** qua WP Admin → Appearance → Themes → Add New → Upload Theme:

1. Upload `lacadev-client.zip` → **không kích hoạt**
1. Upload `lacadev-client-child.zip` → **kích hoạt**

Sau khi kích hoạt child theme, hệ thống auto-update và block sync hoạt động ngay.

### Bước 1.3 — Lấy API Key và Endpoint của site khách hàng

Trên site khách hàng, vào **WP Admin → LacaDev → Block Sync**:

- Copy **API Key** (tự sinh, unique mỗi site)
- Copy **Endpoint URL** (dạng `https://site-khach.com/wp-json/lacadev/v1/sync-block`)

### Bước 1.4 — Tạo Project trên lacadev.com

Trên lacadev.com → WP Admin → Projects → Add New:

- Điền thông tin project
- Dán **Sync API Key** và **Sync Endpoint URL** vào meta box tương ứng
- Lưu

---

## PHẦN 2: Đồng bộ Block từ lacadev → site khách hàng

Blocks được phát triển ở `lacadev/block-gutenberg/` và đẩy sang `lacadev-client-child/block-gutenberg/` của site khách hàng. Khi update `lacadev-client`, blocks này **không bị xoá**.

### Cách đồng bộ (từ lacadev.com)

1. Vào WP Admin lacadev.com → **Projects** → mở project của site khách hàng
1. Kéo xuống box **Block Sync Manager**
1. Thao tác:

| Nút | Chức năng |
| --- | --- |
| **↻ Refresh** | Tải trạng thái blocks hiện có trên site khách hàng |
| **Push Selected** | Đẩy các block đã tick sang site khách hàng |
| **🔄 Sync Outdated** | Tự động push tất cả block có version cũ hơn |

Cột **Trạng thái** cho biết:

- `🆕 Chưa có` — block chưa được push lần nào
- `⚠️ Cũ hơn` — lacadev.com có version mới hơn, cần push lại
- `✅ Đồng bộ` — client đang dùng version mới nhất

### Flow kỹ thuật

```text
lacadev.com (Block Sync Manager)
  → POST https://site-khach.com/wp-json/lacadev/v1/sync-block
      Header: X-Laca-Key: {api_key}
      Body: { block_name, version, files: {base64} }
        → BlockSyncReceiver giải mã + ghi file
          → Ghi vào: lacadev-client-child/block-gutenberg/{block_name}/
            → Tự đăng ký qua lacadev_child_register_synced_blocks() (init priority 15)
```

### Cập nhật version block

Trước khi push, sửa `version` trong `block.json` của block đó:

```json
{
  "name": "lacadev/my-block",
  "version": "1.2.0"
}
```

Block Sync Manager so sánh version này với version đang có trên client để hiện badge trạng thái.

---

## PHẦN 3: Update lacadev-client (Parent Theme)

Update theme `lacadev-client` **không ảnh hưởng** đến:

- `lacadev-client-child/` (child theme của khách hàng)
- Blocks đã sync (nằm trong child theme)
- Cài đặt WordPress và dữ liệu

### Bước 3.1 — Sync code từ lacadev → lacadev-client

Chạy rsync để copy các file phù hợp, bỏ qua tính năng nội bộ:

```bash
rsync -av --checksum \
  --exclude='app/src/PostTypes/project.php' \
  --exclude='app/src/Databases/' \
  --exclude='app/src/Models/ProjectLog.php' \
  --exclude='app/src/Models/ProjectAlert.php' \
  --exclude='app/src/Settings/LacaTools/' \
  --exclude='app/src/PostTypes/Concerns/BlockSyncSender.php' \
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
  --exclude='block-gutenberg/' \
  --exclude='style.css' \
  /path/to/lacadev/ \
  /path/to/lacadev-client/
```

> **Lưu ý:** `block-gutenberg/` không rsync — blocks đi qua Block Sync Manager riêng.

### Bước 3.2 — Build release

```bash
cd /path/to/lacadev-client
./build-release.sh 3.2 "Mô tả thay đổi ngắn gọn"
```

Script tự động:

- Cập nhật `Version: 3.2` trong `theme/style.css`
- Cập nhật `theme-server/lacadev-client.json` với version + download URL + changelog
- Tạo file `releases/lacadev-client-3.2.zip`

### Bước 3.3 — Upload lên lacadev.com

```bash
scp releases/lacadev-client-3.2.zip user@lacadev.com:/public_html/theme-updates/
scp theme-server/lacadev-client.json user@lacadev.com:/public_html/theme-updates/
```

Hoặc upload thủ công qua cPanel → File Manager.

### Bước 3.4 — Xác nhận

```bash
curl https://lacadev.com/theme-updates/lacadev-client.json
```

Phải thấy `"version": "3.2"`.

### Bước 3.5 — Site khách hàng nhận update

- **Tự động:** WordPress check mỗi 12 giờ
- **Force ngay:** WP Admin site khách → Dashboard → Updates → "Check Again"

Admin sẽ thấy:

```text
Appearance → Themes:
┌──────────────────────────────────────────┐
│ La Cà Dev - Client           v3.1         │
│ ⚠️ Update available: v3.2                 │
│    [Update now]                           │
└──────────────────────────────────────────┘
```

Click **"Update now"** → WP tự download `.zip` từ lacadev.com và cài đè `lacadev-client/`.

---

## PHẦN 4: Cập nhật lacadev-client-child (Child Theme)

Child theme ít khi update hơn vì chỉ chứa overrides giao diện riêng của từng site. Khi cần update:

1. Sửa file trong `lacadev-client-child/`
1. Tăng `Version` trong `lacadev-client-child/theme/style.css`
1. Zip thủ công và upload lên site (child theme không có auto-update)

> Child theme update **không xoá** blocks đã sync vì blocks nằm trong `lacadev-client-child/block-gutenberg/`.

---

## PHẦN 5: Tóm tắt nhanh

### Push block mới/cập nhật lên site khách

```text
lacadev.com → WP Admin → Projects → {project} → Block Sync Manager → Push / Sync Outdated
```

### Update parent theme

```text
1. rsync lacadev/ → lacadev-client/  (bỏ tính năng nội bộ)
2. ./build-release.sh 3.X "Mô tả"
3. scp releases/lacadev-client-3.X.zip + lacadev-client.json → lacadev.com/theme-updates/
4. Site khách → Dashboard → Updates → Check Again → Update now
```

---

## PHẦN 6: Cấu trúc file liên quan

```text
lacadev-client/
├── build-release.sh                        ← Script build & release
├── HUONG-DAN-UPDATE.md                     ← File này
├── theme-server/
│   ├── lacadev-client.json                 ← Upload lên lacadev.com/theme-updates/
│   └── .htaccess                           ← Upload lên lacadev.com/theme-updates/
├── releases/                               ← File zip (tự tạo khi build)
│   └── lacadev-client-X.X.zip
└── app/src/Settings/
    ├── ThemeUpdater.php                    ← Auto-update: check lacadev-client.json
    ├── BlockSyncReceiver.php               ← REST API nhận blocks, ghi vào child theme
    └── BlockAutoloader.php                 ← Tự đăng ký blocks trong child theme

lacadev-client-child/
└── block-gutenberg/                        ← Blocks được ghi vào đây khi sync
    └── {block-name}/
        ├── block.json
        ├── render.php
        └── build/

lacadev/ (lacadev.com)
├── block-gutenberg/                        ← Nguồn blocks để push đi
└── app/src/PostTypes/Concerns/
    └── BlockSyncSender.php                 ← Đọc block files, POST sang site khách
```
