---
description: Đánh giá, tư vấn và hướng dẫn nâng cấp (refactor) theme WordPress cũ lên chuẩn kiến trúc LacaDev hiện tại.
---
# LacaDev Legacy Theme Review & Upgrade Workflow

Quy trình tự động hóa việc Audit (đánh giá), Review và đưa ra Recommendation (đề xuất nâng cấp) đối với các file/thành phần của một theme WordPress cũ. Mục tiêu là giúp người dùng nâng cấp mã nguồn cũ đạt đến độ hoàn thiện, bảo mật, hiệu năng và kiến trúc tương đương với chuẩn của theme `lacadev` hiện hành.

Kích hoạt khi người dùng sử dụng lệnh `/review-legacy-theme` kèm theo mã nguồn (PHP, JS, CSS/SCSS) hoặc cấu trúc thư mục của theme cũ.

## 1. Kích hoạt Kỹ năng (Skills Validation)
Trước khi đưa ra đánh giá, Agent BẮT BUỘC vận dụng hệ quy chiếu từ các nhóm kỹ năng sau:
- **WordPress Core & Development**: Dùng `@wordpress-theme-development` và `@php-pro` để soi chiếu logic template hierarchy, vòng lặp (The Loop), hooks (actions/filters).
- **Mã nguồn sạch & Chuẩn mực**: `@cc-skill-coding-standards`, `@frontend-dev-guidelines`, `@clean-code` để đánh giá độ "sạch", khử code rác, code lặp.
- **Thiết kế UI/UX & CSS Architecture**: Dùng `@frontend-design`, phương pháp **BEM**, kiến trúc **Sass 7-1**, và ITCSS/SMACSS để review cách viết style.
- **Hiệu năng & Bảo mật**: Dùng `@wp-performance`, `@database-optimizer`, `@security-auditor`, `@xss-html-injection` để dò tìm N+1 queries, thiếu escape dữ liệu, thẻ script block render.
- **SEO & Trải nghiệm**: Dùng `@seo-structure-architect`, `@accessibility-compliance-accessibility-audit` để review thẻ HTML semantic và accessibility.

## 2. Quy trình Thực thi Đánh giá (Review Execution Steps)

Khi nhận được mã nguồn cũ, Agent cần thực hiện tuần tự:

### Bước 2.1: Phân tích Tình trạng Hiện tại (Current State Analysis)
- Đọc lướt qua mã nguồn cũ, chỉ ra những điểm **chưa đạt chuẩn** (Bad Practices).
- Phân loại lỗi theo các nhóm: Security (Bảo mật), Performance (Hiệu năng), Kiến trúc (Architecture/Clean Code), SEO/A11y.

### Bước 2.2: Đề xuất Phương án Nâng cấp (Upgrade Recommendations)
- Chỉ ra cách refactor (viết lại) từng đoạn code cụ thể để đạt chuẩn `lacadev`.
- **Với PHP/HTML**: Hướng dẫn bóc tách logic, thêm các hàm bảo mật (`esc_html`, `esc_url`, `wp_kses_post`), tối ưu `WP_Query` (thêm `no_found_rows`, bỏ `rand`), chuyển đổi thẻ div vô nghĩa sang HTML5 Semantic.
- **Với CSS/SCSS**:
  - Đánh giá mức độ lồng nhau (nesting). Cảnh báo nếu nesting > 3 cấp.
  - Hướng dẫn cấu trúc lại theo **Sass 7-1 Pattern**.
  - Áp dụng nguyên tắc **BEM** (Block Element Modifier) triệt để. Xóa bỏ ID (`#`) hoặc các class inline không cần thiết.
- **Với JS**: Hướng dẫn loại bỏ thẻ `<script>` nội tuyến, thay thế bằng cách enqueue script thông qua `functions.php` hoặc module bundle bên trong `resources/scripts/`.

### Bước 2.3: Sinh Mã Nguồn Chuẩn (Refactored Code Generation)
- Trình bày một phiên bản mã nguồn đã được viết lại hoàn toàn (Refactored Version) dựa trên những đề xuất trên.
- Đảm bảo mã SCSS (nếu có) được đặt vào đúng cấu trúc phẳng, sử dụng biến (variables) hợp lý.
- Code phải gọn gàng, có comment giải thích lý do tại sao thay đổi (nếu cần).

### Bước 2.4: Checklist Hoàn thiện (Definition of Done)
- Cung cấp một checklist ngắn gọn để user có thể tự đối chiếu:
  - [ ] Đã xóa bỏ inline CSS/JS chưa?
  - [ ] SCSS có tuân thủ BEM và Nesting <= 3 không?
  - [ ] Dữ liệu in ra (echo) đã dùng escape functions chưa?
  - [ ] Đã tách block/component ra hợp lý chưa?

## 3. Trình bày Output cho User
- **Greeting**: "Chào bạn, tôi đã phân tích mã nguồn theme cũ của bạn. Dưới đây là đánh giá tổng quan và phương án nâng cấp lên chuẩn hệ thống LacaDev."
- Phân tách rõ ràng giữa "Code Hiện Tại (Pain Points)" và "Code Đề Xuất (Best Practices)".
- Hỏi User: "Bạn có muốn tôi áp dụng thẳng (write/replace) bản nâng cấp này vào dự án hiện tại không?"
