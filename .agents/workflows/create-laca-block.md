---
description: Hướng dẫn tạo mới hoặc chuyển đổi Gutenberg Block chuẩn kiến trúc theme LacaDev
---
# LacaDev Gutenberg Block Creation & Conversion Workflow

Quy trình tự động hóa việc khởi tạo, phát triển hoặc chuyển đổi một Gutenberg Block cho theme LacaDev. Agent sử dụng quy trình này khi người dùng yêu cầu tạo block mới (từ thiết kế mẫu) HOẶC yêu cầu chuyển đổi một block cũ (thuần PHP/HTML/CSS) sang kiến trúc Gutenberg hiện tại.

Vị trí gốc của theme: `app/public/wp-content/themes/lacadev`

## 1. Kích hoạt Kỹ năng (Skills Validation)
Trước khi bắt đầu code, Agent BẮT BUỘC nhận thức và áp dụng tổ hợp các kỹ năng sau:
- **WordPress Core & Development**: `@wordpress`, `@wordpress-theme-development`, `@wp-block-development`.
- **Hiệu năng & Bảo mật**: `@wp-performance`, `@database-optimizer`, `@security-auditor`, `@xss-html-injection`.
- **SEO & Trải nghiệm**: `@seo-structure-architect`, `@accessibility-compliance-accessibility-audit`.
- **Mã nguồn sạch & Chuẩn mực**: `@cc-skill-coding-standards`, `@frontend-dev-guidelines`, `@clean-code`.
- **Thiết kế UI/UX**: `@ui-ux-pro-max`, `@frontend-design`, BEM, Sass 7-1, CSS Architecture.

## 2. Phân Tích Yêu Cầu (Tạo Mới hoặc Chuyển Đổi)
Agent xác định mục tiêu của người dùng:
- **Tạo mới:** Phân tích hình ảnh mẫu hoặc yêu cầu text để bóc tách UI Component, Block Attributes và cấu trúc HTML.
- **Chuyển đổi (Refactor):** Phân tích mã nguồn cũ (file PHP thuần, file SCSS cũ, js cũ). Bóc tách các phần nội dung tĩnh thành biến động (Block Attributes) để admin có thể sửa được trong Editor.

## 3. Khởi tạo Cấu trúc Block Gutenberg
Tạo thư mục block mới trong `block-gutenberg/[tên-block]/` với đủ 5 files cơ bản:
1. `block.json`: Metadata của block. Prefix tên phải là `lacadev/[tên-block]`. Xác định rõ thuộc tính `attributes` dựa trên phân tích ở bước 2.
2. `index.js`: Nơi register block.
3. `edit.js`: Chứa React code để render UI trong Editor (Backend). Giao diện trong editor phải sử dụng các components chuẩn của `@wordpress/components` như `TextControl`, `RichText`, `InspectorControls`, `PanelBody`...
4. `save.js`: Thường return `null` đối với dynamic block.
5. `render.php`: Chứa cấu trúc HTML và gọi dữ liệu PHP.
   - **Bảo mật**: Sử dụng `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`.
   - **Semantic SEO**: Bao bọc bằng thẻ HTML phù hợp (`<section>`, `<article>`, `<header>`). Phân cấp Heading (`<h2>`, `<h3>`) hợp lý.
   - **Xử lý tài nguyên cũ (Khi chuyển đổi)**: Lọc bỏ các thẻ script/style nội tuyến cũ nếu có, đưa chúng vào đúng kiến trúc hệ thống hiện đại.

## 4. Kiến trúc SCSS/Sass và Tiêu chuẩn BEM
Quy định khắt khe về cách viết style cho block:
- **KHÔNG tạo file SCSS rời** cho từng block.
- **Vị trí viết code**: Toàn bộ SCSS của block phải được đặt bên trong `@mixin block-styles { ... }` (hoặc cấu trúc module tương đương) tại tệp tin:
  `resources/styles/theme/layout/_blocks.scss`
  (Mục đích: đảm bảo hiển thị đồng nhất giữa Admin Editor và Frontend ngoài website).
- **Phương pháp BEM**: 
  - Khai báo class bao ngoài tuân thủ chuẩn BEM, ví dụ: `.laca-[tên-block]`.
  - Các thành phần con theo chuẩn Element: `__element`, Modifier: `--modifier`.
- **Refactor CSS/SCSS Cũ (Khi chuyển đổi)**: Nếu người dùng đưa class cũ (ví dụ: `.my-hero-section .title`), phải viết lại (refactor) hoàn toàn sang chuẩn BEM (ví dụ: `.laca-hero__title`). KHÔNG ĐƯỢC lồng (nest) code SCSS quá 3 cấp.
- Tận dụng biến và mixin sẵn có của hệ thống trong `abstracts/_variables.scss` và `abstracts/_mixin.scss`.

## 5. Kiến trúc JS & Tối ưu Hiệu năng (Performance)
- **Xử lý JS (Nếu có)**: Nếu block có JS (chẳng hạn hiệu ứng trượt, slider), thêm logic Script vào `resources/scripts/theme/` thay vì viết file thẻ `<script>` ở `render.php`.
- **Output Ảnh**: BẮT BUỘC dùng helper function của theme như `theResponsivePostThumbnail()` hiển thị WebP và sizes. Khuyến khích Image ID attribute thay vì URL trực tiếp.
- **WP_Query**: 
  - Thêm `'no_found_rows' => true` nếu không phân trang.
  - KHÔNG dùng `'orderby' => 'rand'`, tốn CPU.

## 6. Quy trình Review & Hỗ trợ (Final Step)
1. Xác nhận mã nguồn đã bóc tách rõ ràng các thành phần động (cho Editor) và tĩnh chưa?
2. Code có tuân thủ 100% chuẩn Clean Code và BEM không? SCSS đã chính xác nằm trong `_blocks.scss` chưa?
3. Các dữ liệu động từ Block Attributes đã được escape hợp lý trong `render.php` chưa?
4. Nếu là tác vụ **Chuyển đổi**, xác nhận xem code mới có tương đương hoặc tối ưu hơn giao diện cũ không?
5. Thông báo hoàn tất, hỏi user có cần review lại code không, nhắc người dùng chạy `yarn build` / `npm run build` để Webpack build output.
