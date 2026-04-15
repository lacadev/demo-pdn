<?php

namespace App\Features\ContactForm;

use App\Databases\ContactFormTable;

/**
 * ContactFormManager
 *
 * Admin UI để tạo và quản lý form liên hệ tùy chỉnh.
 * Menu: Appearance > Form Liên Hệ
 *
 * Views:
 *   (default)             → danh sách tất cả forms
 *   ?action=new           → tạo form mới
 *   ?action=edit&id=X     → sửa form
 *   ?action=submissions&id=X → xem submissions
 *
 * Data format (fields column in DB) — row-based:
 *   [ { id, cols: [ { id, span, fields: [ {id, type, name, label, ...} ] } ] } ]
 * Old flat format still supported for display/submissions.
 */
class ContactFormManager
{
    const NONCE_ACTION = 'laca_contact_form_action';
    const NONCE_FIELD  = '_laca_cf_nonce';
    const CAP          = 'manage_options';
    const MENU_SLUG    = 'laca-contact-forms';
    const PARENT_SLUG  = 'laca-admin';

    /** Field types được hỗ trợ */
    const FIELD_TYPES = [
        'text'        => 'Văn bản (Text)',
        'textarea'    => 'Đoạn văn (Textarea)',
        'email'       => 'Email',
        'phone'       => 'Số điện thoại',
        'number'      => 'Số (Number)',
        'select'      => 'Dropdown (Select)',
        'multiselect' => 'Chọn nhiều (Multi-select)',
        'radio'       => 'Radio button',
        'checkbox'    => 'Checkbox',
        'date'        => 'Ngày (Date)',
        'datetime'    => 'Ngày & Giờ (Datetime)',
        'url'         => 'Đường dẫn (URL)',
        'hidden'      => 'Ẩn (Hidden)',
    ];

    /** Allowed column spans in 12-col grid */
    const ALLOWED_SPANS = [3, 4, 6, 8, 12];

    public function __construct()
    {
        add_action('admin_menu',  [$this, 'registerMenu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('admin_post_laca_cf_save',              [$this, 'handleSave']);
        add_action('admin_post_laca_cf_delete',            [$this, 'handleDelete']);
        add_action('admin_post_laca_cf_delete_submission', [$this, 'handleDeleteSubmission']);
        add_action('admin_post_laca_cf_mark_read',         [$this, 'handleMarkRead']);
        add_action('admin_post_laca_cf_export_csv',        [$this, 'handleExportCsv']);
    }

    public function enqueueAssets(string $hook): void
    {
        if (!str_contains($hook, self::MENU_SLUG)) {
            return;
        }

        $action = sanitize_key($_GET['action'] ?? '');
        if (!in_array($action, ['new', 'edit', ''], true)) {
            return;
        }

        $themeRoot    = dirname(get_template_directory());
        $themeRootUri = dirname(get_template_directory_uri());
        $sortableFile = $themeRoot . '/node_modules/sortablejs/Sortable.min.js';
        $sortableUrl  = $themeRootUri . '/node_modules/sortablejs/Sortable.min.js';

        if (file_exists($sortableFile)) {
            wp_enqueue_script('sortablejs', $sortableUrl, [], '1.15.7', false);
        }
    }

    // =========================================================================
    // MENU
    // =========================================================================

    public function registerMenu(): void
    {
        add_submenu_page(
            self::PARENT_SLUG,
            __('Form Liên Hệ', 'laca'),
            __('Form Liên Hệ', 'laca'),
            self::CAP,
            self::MENU_SLUG,
            [$this, 'renderPage']
        );
    }

    // =========================================================================
    // PAGE ROUTER
    // =========================================================================

    public function renderPage(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Bạn không có quyền truy cập trang này.', 'laca'));
        }

        $action = sanitize_key($_GET['action'] ?? '');
        $id     = absint($_GET['id'] ?? 0);

        $this->renderPageStyles();

        switch ($action) {
            case 'new':
                $this->renderEditPage(null);
                break;
            case 'edit':
                $form = $id ? ContactFormTable::getForm($id) : null;
                if (!$form) {
                    wp_die(esc_html__('Form không tồn tại.', 'laca'));
                }
                $this->renderEditPage($form);
                break;
            case 'submissions':
                $form = $id ? ContactFormTable::getForm($id) : null;
                if (!$form) {
                    wp_die(esc_html__('Form không tồn tại.', 'laca'));
                }
                $this->renderSubmissionsPage($form);
                break;
            default:
                $this->renderListPage();
        }
    }

    // =========================================================================
    // HELPERS — extract flat field list from either DB format
    // =========================================================================

    /**
     * Extract a flat array of field objects from a form row.
     * Handles both old flat format and new row-based format.
     */
    private static function extractFlatFields(array $form): array
    {
        $raw = json_decode($form['fields'] ?? '[]', true) ?: [];
        if (empty($raw)) {
            return [];
        }
        // Old flat format: first item has 'type' and no 'cols'
        if (isset($raw[0]['type']) && !isset($raw[0]['cols'])) {
            return $raw;
        }
        // New row-based format
        $fields = [];
        foreach ($raw as $row) {
            foreach ($row['cols'] ?? [] as $col) {
                foreach ($col['fields'] ?? [] as $field) {
                    $fields[] = $field;
                }
            }
        }
        return $fields;
    }

    /**
     * Convert raw DB data to row-based format for the builder JS.
     * Old flat format is auto-converted: each field → single-col row.
     */
    private static function toRowsFormat(array $form): array
    {
        $raw = json_decode($form['fields'] ?? '[]', true) ?: [];
        if (empty($raw)) {
            return [];
        }
        // Already row-based
        if (isset($raw[0]['cols'])) {
            return $raw;
        }
        // Convert old flat format
        return array_map(function ($field) {
            $span = in_array((int) ($field['col_width'] ?? 12), self::ALLOWED_SPANS, true)
                ? (int) $field['col_width']
                : 12;
            unset($field['col_width']);
            return [
                'id'   => 'row_' . ($field['id'] ?? uniqid()),
                'cols' => [[
                    'id'     => 'col_' . ($field['id'] ?? uniqid()),
                    'span'   => $span,
                    'fields' => [$field],
                ]],
            ];
        }, $raw);
    }

    // =========================================================================
    // LIST PAGE
    // =========================================================================

    private function renderListPage(): void
    {
        $forms   = ContactFormTable::getAllForms();
        $pageUrl = admin_url('themes.php?page=' . self::MENU_SLUG);
        $message = $this->getFlashMessage();
        ?>
        <div class="wrap laca-cf-wrap">
            <div class="laca-cf-header">
                <div>
                    <h1>📋 Quản lý Form Liên Hệ</h1>
                    <p class="laca-cf-subtitle">Tạo và quản lý các form liên hệ. Nhúng bằng shortcode <code>[laca_contact_form id="X"]</code></p>
                </div>
                <a href="<?php echo esc_url($pageUrl . '&action=new'); ?>" class="button button-primary laca-cf-btn-new">
                    + Tạo Form Mới
                </a>
            </div>

            <?php if ($message): ?>
                <div class="laca-cf-notice laca-cf-notice--<?php echo esc_attr($message['type']); ?>">
                    <?php echo esc_html($message['text']); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($forms)): ?>
                <div class="laca-cf-empty">
                    <p>Chưa có form nào. <a href="<?php echo esc_url($pageUrl . '&action=new'); ?>">Tạo form đầu tiên</a></p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped laca-cf-table">
                    <thead>
                        <tr>
                            <th style="width:40px">ID</th>
                            <th>Tên Form</th>
                            <th style="width:100px">Số Fields</th>
                            <th style="width:120px">Submissions</th>
                            <th style="width:120px">Chưa đọc</th>
                            <th style="width:140px">Shortcode</th>
                            <th style="width:200px">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($forms as $form): ?>
                            <?php
                            $flatFields  = self::extractFlatFields($form);
                            $formId      = (int) $form['id'];
                            $shortcode   = '[laca_contact_form id="' . $formId . '"]';
                            $editUrl     = $pageUrl . '&action=edit&id=' . $formId;
                            $subsUrl     = $pageUrl . '&action=submissions&id=' . $formId;
                            $unreadCount = (int) $form['unread_count'];
                            $totalCount  = (int) $form['submission_count'];
                            ?>
                            <tr>
                                <td><?php echo esc_html($formId); ?></td>
                                <td>
                                    <strong>
                                        <a href="<?php echo esc_url($editUrl); ?>">
                                            <?php echo esc_html($form['name']); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td><?php echo count($flatFields); ?></td>
                                <td>
                                    <a href="<?php echo esc_url($subsUrl); ?>">
                                        <?php echo esc_html($totalCount); ?> lượt
                                    </a>
                                </td>
                                <td>
                                    <?php if ($unreadCount > 0): ?>
                                        <span class="laca-cf-badge laca-cf-badge--unread"><?php echo esc_html($unreadCount); ?> mới</span>
                                    <?php else: ?>
                                        <span style="color:#999">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <code class="laca-cf-shortcode" title="Click để copy"
                                          onclick="navigator.clipboard.writeText('<?php echo esc_js($shortcode); ?>').then(()=>alert('Đã copy shortcode!'))"
                                          style="cursor:pointer">
                                        <?php echo esc_html($shortcode); ?>
                                    </code>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url($editUrl); ?>" class="button button-small">Sửa</a>
                                    <a href="<?php echo esc_url($subsUrl); ?>" class="button button-small">Xem Submissions</a>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                          style="display:inline"
                                          onsubmit="return confirm('Xoá form này và toàn bộ submissions? Không thể khôi phục.')">
                                        <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>
                                        <input type="hidden" name="action" value="laca_cf_delete">
                                        <input type="hidden" name="form_id" value="<?php echo esc_attr($formId); ?>">
                                        <button type="submit" class="button button-small button-link-delete">Xoá</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    // =========================================================================
    // EDIT / NEW FORM PAGE
    // =========================================================================

    private function renderEditPage(?array $form): void
    {
        $isNew   = ($form === null);
        $pageUrl = admin_url('themes.php?page=' . self::MENU_SLUG);
        $formId  = $isNew ? 0 : (int) $form['id'];
        $rows    = $isNew ? [] : self::toRowsFormat($form);
        $message = $this->getFlashMessage();

        $defaultAdminSubject    = 'Đăng kí tư vấn [$name - $phone_number]';
        $defaultAdminBody       = "Họ tên: \$name\nEmail: \$email\nSố điện thoại: \$phone_number\nGhi chú: \$message\n\nĐược gửi từ IP: \$ip lúc \$time - \$date";
        $defaultCustomerSubject = 'Cảm ơn bạn đã liên hệ - ' . get_bloginfo('name');
        $defaultCustomerBody    = "Cảm ơn anh/chị \$name đã liên hệ, chúng tôi sẽ xác nhận lại và liên hệ lại quý khách trong 24h.\n\nTrân trọng,\n" . get_bloginfo('name');
        ?>
        <div class="wrap laca-cf-wrap">
            <div class="laca-cf-header">
                <div>
                    <h1><?php echo $isNew ? '+ Tạo Form Mới' : '✏️ Sửa Form: ' . esc_html($form['name']); ?></h1>
                    <p class="laca-cf-subtitle">
                        <a href="<?php echo esc_url($pageUrl); ?>">← Quay lại danh sách</a>
                        <?php if (!$isNew): ?>
                            &nbsp;|&nbsp;
                            Shortcode: <code onclick="navigator.clipboard.writeText('[laca_contact_form id=&quot;<?php echo esc_js($formId); ?>&quot;]').then(()=>alert('Đã copy!'))" style="cursor:pointer" title="Click để copy">[laca_contact_form id="<?php echo esc_html($formId); ?>"]</code>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="laca-cf-notice laca-cf-notice--<?php echo esc_attr($message['type']); ?>">
                    <?php echo esc_html($message['text']); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="laca-cf-form">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>
                <input type="hidden" name="action"      value="laca_cf_save">
                <input type="hidden" name="form_id"     value="<?php echo esc_attr($formId); ?>">
                <input type="hidden" name="fields_json" id="fields-json-input" value="<?php echo esc_attr(wp_json_encode($rows)); ?>">

                <div class="laca-cf-grid">

                    <!-- ====== CỘT TRÁI: Thông tin chung + Builder ====== -->
                    <div class="laca-cf-col-main">

                        <!-- Card: Thông tin form -->
                        <div class="laca-cf-card">
                            <h2>Thông tin Form</h2>
                            <table class="form-table">
                                <tr>
                                    <th><label for="cf-name">Tên form <span class="required">*</span></label></th>
                                    <td>
                                        <input type="text" id="cf-name" name="form_name" class="regular-text"
                                               value="<?php echo esc_attr($form['name'] ?? ''); ?>"
                                               placeholder="VD: Form Tư Vấn Miễn Phí" required>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="cf-notify-email">Email nhận thông báo</label></th>
                                    <td>
                                        <input type="email" id="cf-notify-email" name="notify_email" class="regular-text"
                                               value="<?php echo esc_attr($form['notify_email'] ?? ''); ?>"
                                               placeholder="Để trống = dùng <?php echo esc_attr(get_option('admin_email')); ?>">
                                        <p class="description">Email admin sẽ nhận thông báo mỗi khi có submission mới.</p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Card: Layout Builder -->
                        <div class="laca-cf-card">
                            <h2 style="margin-bottom:6px">Các trường nhập liệu</h2>
                            <p class="description" style="margin-bottom:16px;color:#888">
                                Thêm hàng layout, rồi thêm field vào từng cột. Kéo field sang cột khác để di chuyển.
                            </p>

                            <!-- Rows container -->
                            <div id="rows-builder" class="laca-cf-rows-builder"></div>
                            <p id="rows-empty-msg" class="laca-cf-fields-empty" style="<?php echo empty($rows) ? '' : 'display:none'; ?>">
                                Chưa có hàng nào. Thêm hàng bên dưới.
                            </p>

                            <!-- Add-row palette -->
                            <div class="laca-cf-add-row-palette">
                                <span class="lcf-palette-label">+ Thêm hàng:</span>
                                <button type="button" class="lcf-add-row-btn" onclick="lcfAddRow('1')">
                                    <span class="lcf-row-preview lcf-rp-1"></span>1 cột
                                </button>
                                <button type="button" class="lcf-add-row-btn" onclick="lcfAddRow('2')">
                                    <span class="lcf-row-preview lcf-rp-2"></span>2 cột
                                </button>
                                <button type="button" class="lcf-add-row-btn" onclick="lcfAddRow('3')">
                                    <span class="lcf-row-preview lcf-rp-3"></span>3 cột
                                </button>
                                <button type="button" class="lcf-add-row-btn" onclick="lcfAddRow('4')">
                                    <span class="lcf-row-preview lcf-rp-4"></span>4 cột
                                </button>
                                <button type="button" class="lcf-add-row-btn" onclick="lcfAddRow('1-2')">
                                    <span class="lcf-row-preview lcf-rp-1-2"></span>1/3 + 2/3
                                </button>
                                <button type="button" class="lcf-add-row-btn" onclick="lcfAddRow('2-1')">
                                    <span class="lcf-row-preview lcf-rp-2-1"></span>2/3 + 1/3
                                </button>
                            </div>
                        </div>

                    </div>

                    <!-- ====== CỘT PHẢI: Email Templates ====== -->
                    <div class="laca-cf-col-sidebar">

                        <!-- Card: Email tới Admin -->
                        <div class="laca-cf-card">
                            <h2>📧 Email thông báo Admin</h2>
                            <p class="description" style="margin-bottom:10px">Gửi đến email admin khi có submission mới. Dùng <code>$tên_field</code> để chèn giá trị.</p>

                            <div class="laca-cf-field-group">
                                <label>Tiêu đề (Subject)</label>
                                <input type="text" name="email_admin_subject" class="widefat"
                                       value="<?php echo esc_attr($form['email_admin_subject'] ?? $defaultAdminSubject); ?>">
                            </div>

                            <div class="laca-cf-field-group">
                                <label>Nội dung (Body)</label>
                                <textarea name="email_admin_body" class="widefat laca-cf-email-body" rows="8"><?php echo esc_textarea($form['email_admin_body'] ?? $defaultAdminBody); ?></textarea>
                            </div>

                            <div class="laca-cf-var-hint">
                                <strong>Biến mặc định:</strong>
                                <code>$name</code> <code>$email</code> <code>$phone_number</code>
                                <code>$message</code> <code>$ip</code> <code>$date</code> <code>$time</code>
                                + tên field tùy chỉnh với tiền tố <code>$</code>
                            </div>
                        </div>

                        <!-- Card: Email tới Khách -->
                        <div class="laca-cf-card">
                            <h2>✉️ Email xác nhận Khách hàng</h2>
                            <p class="description" style="margin-bottom:10px">Gửi đến email khách sau khi submit. Để trống tiêu đề = không gửi.</p>

                            <div class="laca-cf-field-group">
                                <label>Tiêu đề (Subject)</label>
                                <input type="text" name="email_customer_subject" class="widefat"
                                       value="<?php echo esc_attr($form['email_customer_subject'] ?? $defaultCustomerSubject); ?>">
                            </div>

                            <div class="laca-cf-field-group">
                                <label>Nội dung (Body)</label>
                                <textarea name="email_customer_body" class="widefat laca-cf-email-body" rows="6"><?php echo esc_textarea($form['email_customer_body'] ?? $defaultCustomerBody); ?></textarea>
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="laca-cf-card" style="text-align:right">
                            <a href="<?php echo esc_url($pageUrl); ?>" class="button" style="margin-right:8px">Huỷ</a>
                            <button type="submit" class="button button-primary button-large">
                                <?php echo $isNew ? 'Tạo Form' : 'Lưu Thay Đổi'; ?>
                            </button>
                        </div>

                    </div>
                </div><!-- .laca-cf-grid -->
            </form>
        </div>

        <script>
        (function() {
            'use strict';

            const FIELD_TYPES = <?php echo wp_json_encode(self::FIELD_TYPES); ?>;
            const HAS_OPTIONS = ['select', 'multiselect', 'radio', 'checkbox'];

            // Row layout templates: array of spans (12-col grid)
            const ROW_TEMPLATES = {
                '1':   [12],
                '2':   [6, 6],
                '3':   [4, 4, 4],
                '4':   [3, 3, 3, 3],
                '1-2': [4, 8],
                '2-1': [8, 4],
            };

            // Mutable state
            let rows = <?php echo wp_json_encode($rows); ?> || [];
            let sortableInstances = [];

            // ── Escape helpers ────────────────────────────────────────────────
            function escHtml(str) {
                const d = document.createElement('div');
                d.textContent = str || '';
                return d.innerHTML;
            }
            function escAttr(str) {
                return (str || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            }

            // ── Unique ID ─────────────────────────────────────────────────────
            function uid() {
                return 'id_' + Date.now() + '_' + Math.floor(Math.random() * 10000);
            }

            // ── Sync hidden JSON input ────────────────────────────────────────
            function updateJsonInput() {
                // Strip internal _autoName before saving
                const clean = rows.map(function(row) {
                    return {
                        id:   row.id,
                        cols: row.cols.map(function(col) {
                            return {
                                id:     col.id,
                                span:   col.span,
                                fields: col.fields.map(function(f) {
                                    const c = Object.assign({}, f);
                                    delete c._autoName;
                                    return c;
                                }),
                            };
                        }),
                    };
                });
                document.getElementById('fields-json-input').value = JSON.stringify(clean);
            }

            // ── Find field by id → { row, col, field } or null ───────────────
            function findField(fieldId) {
                for (const row of rows) {
                    for (const col of row.cols) {
                        for (const field of col.fields) {
                            if (field.id === fieldId) {
                                return { row, col, field };
                            }
                        }
                    }
                }
                return null;
            }

            // ── Build field card HTML ─────────────────────────────────────────
            function buildFieldCard(field) {
                const typeLabel  = FIELD_TYPES[field.type] || field.type;
                const hasOptions = HAS_OPTIONS.includes(field.type);
                const reqMark    = field.required ? ' <span style="color:#d9534f">*</span>' : '';
                const labelPrev  = field.label
                    ? escHtml(field.label)
                    : '<em style="color:#aaa;font-weight:400">Chưa đặt nhãn</em>';

                const optHtml = hasOptions ? `
                    <div class="lcf-input-row" style="margin-top:10px">
                        <label class="lcf-label">Các lựa chọn <small style="font-weight:400">(mỗi dòng 1 option)</small></label>
                        <textarea class="widefat" rows="3"
                            placeholder="Lựa chọn 1&#10;Lựa chọn 2"
                            oninput="lcfFieldUpdate('${escAttr(field.id)}','options',this.value.split('\\n').map(function(s){return s.trim();}).filter(Boolean))"
                        >${escHtml((field.options || []).join('\n'))}</textarea>
                    </div>` : '';

                return `<div class="laca-cf-field-card" data-field-id="${escAttr(field.id)}">
                    <div class="laca-cf-field-card-header" onclick="lcfToggleCard(this.closest('.laca-cf-field-card'))">
                        <span class="lcf-field-drag-handle" title="Kéo để di chuyển field">
                            <svg width="10" height="16" viewBox="0 0 10 16" fill="currentColor">
                                <circle cx="3" cy="2"  r="1.4"/><circle cx="7" cy="2"  r="1.4"/>
                                <circle cx="3" cy="8"  r="1.4"/><circle cx="7" cy="8"  r="1.4"/>
                                <circle cx="3" cy="14" r="1.4"/><circle cx="7" cy="14" r="1.4"/>
                            </svg>
                        </span>
                        <span class="lcf-type-badge">${escHtml(typeLabel)}</span>
                        <span class="lcf-label-preview">${labelPrev}${reqMark}</span>
                        <span class="lcf-toggle-icon">
                            <svg width="10" height="6" viewBox="0 0 10 6" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M1 1l4 4 4-4"/></svg>
                        </span>
                        <button type="button" class="lcf-remove-field-btn"
                            onclick="lcfRemoveField(event,'${escAttr(field.id)}')"
                            title="Xoá field">✕</button>
                    </div>
                    <div class="laca-cf-field-card-body">
                        <div class="lcf-field-inputs">
                            <div class="lcf-input-row">
                                <label class="lcf-label">Nhãn (Label) <span class="required">*</span></label>
                                <input type="text" class="widefat" placeholder="VD: Họ và tên"
                                    value="${escAttr(field.label)}"
                                    oninput="lcfFieldUpdate('${escAttr(field.id)}','label',this.value)">
                            </div>
                            <div class="lcf-input-row">
                                <label class="lcf-label">Tên biến (name) <span class="required">*</span></label>
                                <input type="text" class="widefat lcf-name-input" placeholder="VD: ho_ten"
                                    value="${escAttr(field.name)}"
                                    oninput="lcfFieldUpdate('${escAttr(field.id)}','name',this.value)"
                                    pattern="[a-z0-9_]+" title="Chỉ dùng chữ thường, số, dấu gạch dưới">
                                <p class="lcf-name-hint">Dùng trong email: $<strong class="lcf-name-strong">${escHtml(field.name || 'ten_bien')}</strong></p>
                            </div>
                            <div class="lcf-input-row">
                                <label class="lcf-label">Placeholder</label>
                                <input type="text" class="widefat"
                                    value="${escAttr(field.placeholder || '')}"
                                    oninput="lcfFieldUpdate('${escAttr(field.id)}','placeholder',this.value)">
                            </div>
                            <div class="lcf-input-row" style="margin-top:4px">
                                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px">
                                    <input type="checkbox" ${field.required ? 'checked' : ''}
                                        onchange="lcfFieldUpdate('${escAttr(field.id)}','required',this.checked)">
                                    Bắt buộc nhập
                                </label>
                            </div>
                            ${optHtml}
                        </div>
                    </div>
                </div>`;
            }

            // ── Build row HTML ────────────────────────────────────────────────
            function buildRowHtml(row) {
                const colInfo = row.cols.map(function(c) {
                    return Math.round((c.span / 12) * 100) + '%';
                }).join(' / ');

                const colsHtml = row.cols.map(function(col, idx) {
                    const fieldsHtml = col.fields.map(buildFieldCard).join('');
                    const pct        = Math.round((col.span / 12) * 100);
                    return `<div class="laca-cf-col-slot" data-col-id="${escAttr(col.id)}" data-span="${col.span}" style="flex:${col.span}">
                        <div class="laca-cf-col-header">Cột ${idx + 1} <span style="opacity:0.6;font-weight:400">${pct}%</span></div>
                        <div class="laca-cf-col-drop" data-row-id="${escAttr(row.id)}" data-col-id="${escAttr(col.id)}">
                            ${fieldsHtml}
                            <div class="lcf-col-empty-hint" style="${col.fields.length ? 'display:none' : ''}">Kéo field vào đây</div>
                        </div>
                        <div class="laca-cf-col-add-field">
                            <select class="laca-cf-add-field-type">
                                ${Object.entries(FIELD_TYPES).map(([k, v]) => `<option value="${escAttr(k)}">${escHtml(v)}</option>`).join('')}
                            </select>
                            <button type="button"
                                onclick="lcfAddField('${escAttr(row.id)}','${escAttr(col.id)}',this.previousElementSibling.value)">
                                + Field
                            </button>
                        </div>
                    </div>`;
                }).join('');

                return `<div class="laca-cf-layout-row" data-row-id="${escAttr(row.id)}">
                    <div class="laca-cf-row-toolbar">
                        <span class="lcf-row-drag-handle" title="Kéo để di chuyển hàng">
                            <svg width="16" height="10" viewBox="0 0 16 10" fill="currentColor">
                                <rect x="0" y="0" width="16" height="1.8" rx="0.9"/>
                                <rect x="0" y="4" width="16" height="1.8" rx="0.9"/>
                                <rect x="0" y="8" width="16" height="1.8" rx="0.9"/>
                            </svg>
                        </span>
                        <span class="lcf-row-label">${escHtml(colInfo)}</span>
                        <button type="button" class="lcf-remove-row-btn"
                            onclick="lcfRemoveRow('${escAttr(row.id)}')">✕ Xoá hàng</button>
                    </div>
                    <div class="laca-cf-row-content">${colsHtml}</div>
                </div>`;
            }

            // ── Render all rows ───────────────────────────────────────────────
            function renderRows() {
                sortableInstances.forEach(function(s) { s.destroy(); });
                sortableInstances = [];

                const builder  = document.getElementById('rows-builder');
                const emptyMsg = document.getElementById('rows-empty-msg');
                builder.innerHTML = '';
                if (emptyMsg) emptyMsg.style.display = rows.length ? 'none' : '';

                rows.forEach(function(row) {
                    builder.insertAdjacentHTML('beforeend', buildRowHtml(row));
                });

                updateJsonInput();
                initSortables();
            }

            // ── Sync data from DOM (called after drag) ────────────────────────
            function syncFromDOM() {
                // Build flat field index
                const fieldIndex = {};
                rows.forEach(function(row) {
                    row.cols.forEach(function(col) {
                        col.fields.forEach(function(f) { fieldIndex[f.id] = f; });
                    });
                });

                const newRows = [];
                document.querySelectorAll('#rows-builder > .laca-cf-layout-row').forEach(function(rowEl) {
                    const rowId  = rowEl.dataset.rowId;
                    const oldRow = rows.find(function(r) { return r.id === rowId; });
                    if (!oldRow) return;

                    const newCols = [];
                    rowEl.querySelectorAll(':scope > .laca-cf-row-content > .laca-cf-col-slot').forEach(function(slotEl) {
                        const colId = slotEl.dataset.colId;
                        const span  = parseInt(slotEl.dataset.span) || 12;

                        const newFields = [];
                        slotEl.querySelectorAll(':scope > .laca-cf-col-drop > .laca-cf-field-card').forEach(function(cardEl) {
                            const fId = cardEl.dataset.fieldId;
                            if (fieldIndex[fId]) newFields.push(fieldIndex[fId]);
                        });

                        // Update empty hint
                        const hint = slotEl.querySelector('.lcf-col-empty-hint');
                        if (hint) hint.style.display = newFields.length ? 'none' : '';

                        newCols.push({ id: colId, span, fields: newFields });
                    });

                    newRows.push({ id: rowId, cols: newCols });
                });

                rows = newRows;
                updateJsonInput();
            }

            // ── Init SortableJS ───────────────────────────────────────────────
            function initSortables() {
                if (typeof Sortable === 'undefined') return;

                // Row-level
                sortableInstances.push(Sortable.create(document.getElementById('rows-builder'), {
                    handle:     '.lcf-row-drag-handle',
                    animation:  150,
                    group:      'layout-rows',
                    ghostClass: 'lcf-ghost',
                    onEnd: syncFromDOM,
                }));

                // Field-level per column drop zone
                document.querySelectorAll('.laca-cf-col-drop').forEach(function(colDrop) {
                    sortableInstances.push(Sortable.create(colDrop, {
                        handle:     '.lcf-field-drag-handle',
                        animation:  150,
                        group:      { name: 'form-fields', pull: true, put: true },
                        filter:     '.lcf-col-empty-hint',
                        ghostClass: 'lcf-ghost',
                        dragClass:  'lcf-dragging',
                        onEnd: syncFromDOM,
                    }));
                });
            }

            // ── Public: toggle accordion ──────────────────────────────────────
            window.lcfToggleCard = function(cardEl) {
                if (!cardEl) return;
                cardEl.classList.toggle('is-open');
            };

            // ── Public: update field property (no re-render) ──────────────────
            window.lcfFieldUpdate = function(fieldId, key, value) {
                const found = findField(fieldId);
                if (!found) return;
                const { field } = found;
                field[key] = value;

                const cardEl = document.querySelector('.laca-cf-field-card[data-field-id="' + fieldId + '"]');

                if (key === 'label') {
                    const prev      = cardEl ? cardEl.querySelector('.lcf-label-preview') : null;
                    const nameInput = cardEl ? cardEl.querySelector('.lcf-name-input')    : null;
                    if (prev) {
                        const reqMark = field.required ? ' <span style="color:#d9534f">*</span>' : '';
                        prev.innerHTML = (value
                            ? escHtml(value)
                            : '<em style="color:#aaa;font-weight:400">Chưa đặt nhãn</em>') + reqMark;
                    }
                    // Auto-slugify name
                    if (nameInput && (!field.name || field.name === field._autoName)) {
                        const slug = value.toLowerCase()
                            .replace(/[àáạảãâầấậẩẫăằắặẳẵ]/g,'a')
                            .replace(/[èéẹẻẽêềếệểễ]/g,'e')
                            .replace(/[ìíịỉĩ]/g,'i')
                            .replace(/[òóọỏõôồốộổỗơờớợởỡ]/g,'o')
                            .replace(/[ùúụủũưừứựửữ]/g,'u')
                            .replace(/[ỳýỵỷỹ]/g,'y')
                            .replace(/đ/g,'d')
                            .replace(/[^a-z0-9]+/g,'_')
                            .replace(/^_+|_+$/g,'');
                        field.name = slug;
                        field._autoName = slug;
                        nameInput.value = slug;
                        const strong = cardEl ? cardEl.querySelector('.lcf-name-strong') : null;
                        if (strong) strong.textContent = slug || 'ten_bien';
                    }
                }

                if (key === 'required') {
                    const prev  = cardEl ? cardEl.querySelector('.lcf-label-preview') : null;
                    if (prev) {
                        const reqMark = value ? ' <span style="color:#d9534f">*</span>' : '';
                        prev.innerHTML = (field.label
                            ? escHtml(field.label)
                            : '<em style="color:#aaa;font-weight:400">Chưa đặt nhãn</em>') + reqMark;
                    }
                }

                if (key === 'name') {
                    const strong = cardEl ? cardEl.querySelector('.lcf-name-strong') : null;
                    if (strong) strong.textContent = value || 'ten_bien';
                }

                updateJsonInput();
            };

            // ── Public: add layout row ────────────────────────────────────────
            window.lcfAddRow = function(template) {
                const spans = ROW_TEMPLATES[template] || [12];
                const cols  = spans.map(function(span) {
                    return { id: uid(), span: span, fields: [] };
                });
                rows.push({ id: uid(), cols: cols });
                renderRows();
                // Scroll to new row
                const builder = document.getElementById('rows-builder');
                const last    = builder.lastElementChild;
                if (last) last.scrollIntoView({ behavior: 'smooth', block: 'center' });
            };

            // ── Public: remove row ────────────────────────────────────────────
            window.lcfRemoveRow = function(rowId) {
                const row         = rows.find(function(r) { return r.id === rowId; });
                const totalFields = (row ? row.cols : []).reduce(function(n, c) { return n + c.fields.length; }, 0);
                if (totalFields > 0 && !confirm('Xoá hàng này và ' + totalFields + ' field bên trong?')) return;
                rows = rows.filter(function(r) { return r.id !== rowId; });
                renderRows();
            };

            // ── Public: add field to column ───────────────────────────────────
            window.lcfAddField = function(rowId, colId, type) {
                const row = rows.find(function(r) { return r.id === rowId; });
                if (!row) return;
                const col = row.cols.find(function(c) { return c.id === colId; });
                if (!col) return;

                const newField = {
                    id: uid(), type: type, name: '', label: '',
                    placeholder: '', required: false, options: [], _autoName: '',
                };
                col.fields.push(newField);
                renderRows();

                // Open & focus new card
                setTimeout(function() {
                    const card = document.querySelector('.laca-cf-field-card[data-field-id="' + newField.id + '"]');
                    if (!card) return;
                    card.classList.add('is-open');
                    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    const firstInput = card.querySelector('input[type=text]');
                    if (firstInput) firstInput.focus();
                }, 60);
            };

            // ── Public: remove field ──────────────────────────────────────────
            window.lcfRemoveField = function(event, fieldId) {
                event.stopPropagation(); // prevent accordion toggle
                const found = findField(fieldId);
                if (!found) return;
                const label = found.field.label || '(chưa đặt tên)';
                if (!confirm('Xoá field "' + label + '"?')) return;

                rows.forEach(function(row) {
                    row.cols.forEach(function(col) {
                        col.fields = col.fields.filter(function(f) { return f.id !== fieldId; });
                    });
                });

                const card = document.querySelector('.laca-cf-field-card[data-field-id="' + fieldId + '"]');
                if (card) {
                    const drop = card.closest('.laca-cf-col-drop');
                    card.remove();
                    if (drop) {
                        const hint = drop.querySelector('.lcf-col-empty-hint');
                        if (hint) hint.style.display = drop.querySelectorAll('.laca-cf-field-card').length ? 'none' : '';
                    }
                }

                updateJsonInput();
            };

            // ── Form submit validation ────────────────────────────────────────
            document.getElementById('laca-cf-form').addEventListener('submit', function(e) {
                if (!document.getElementById('cf-name').value.trim()) {
                    alert('Vui lòng nhập tên form.');
                    e.preventDefault();
                    return;
                }
                const allFields = [];
                rows.forEach(function(row) {
                    row.cols.forEach(function(col) {
                        col.fields.forEach(function(f) { allFields.push(f); });
                    });
                });
                for (let i = 0; i < allFields.length; i++) {
                    const f = allFields[i];
                    if (!f.label) {
                        alert('Có field chưa điền nhãn (Label).');
                        e.preventDefault();
                        return;
                    }
                    if (!f.name) {
                        alert('Field "' + f.label + '" cần có tên biến (name).');
                        e.preventDefault();
                        return;
                    }
                }
                updateJsonInput();
            });

            // ── Init ──────────────────────────────────────────────────────────
            renderRows();
        })();
        </script>
        <?php
    }

    // =========================================================================
    // SUBMISSIONS PAGE
    // =========================================================================

    private function renderSubmissionsPage(array $form): void
    {
        $formId  = (int) $form['id'];
        $pageUrl = admin_url('themes.php?page=' . self::MENU_SLUG);
        $page    = max(1, absint($_GET['paged'] ?? 1));
        $perPage = 20;
        $subs    = ContactFormTable::getSubmissions($formId, $page, $perPage);
        $total   = ContactFormTable::countSubmissions($formId);
        $pages   = (int) ceil($total / $perPage);
        $fields  = self::extractFlatFields($form);
        $message = $this->getFlashMessage();
        ?>
        <div class="wrap laca-cf-wrap">
            <div class="laca-cf-header">
                <div>
                    <h1>📥 Submissions — <?php echo esc_html($form['name']); ?></h1>
                    <p class="laca-cf-subtitle"><a href="<?php echo esc_url($pageUrl); ?>">← Quay lại danh sách</a></p>
                </div>
                <div style="display:flex;gap:8px;align-items:center">
                    <span class="laca-cf-badge laca-cf-badge--total"><?php echo esc_html($total); ?> submission</span>
                    <?php if ($total > 0):
                        $exportUrl = wp_nonce_url(
                            admin_url('admin-post.php?action=laca_cf_export_csv&form_id=' . $formId),
                            self::NONCE_ACTION,
                            self::NONCE_FIELD
                        );
                    ?>
                        <a href="<?php echo esc_url($exportUrl); ?>" class="button button-secondary">
                            ⬇ Xuất CSV
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="laca-cf-notice laca-cf-notice--<?php echo esc_attr($message['type']); ?>">
                    <?php echo esc_html($message['text']); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($subs)): ?>
                <div class="laca-cf-empty"><p>Chưa có submission nào.</p></div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped laca-cf-table">
                    <thead>
                        <tr>
                            <th style="width:40px">ID</th>
                            <th style="width:60px">Đọc</th>
                            <?php foreach ($fields as $field): ?>
                                <th><?php echo esc_html($field['label']); ?></th>
                            <?php endforeach; ?>
                            <th style="width:120px">IP</th>
                            <th style="width:150px">Thời gian</th>
                            <th style="width:80px">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subs as $sub): ?>
                            <?php
                            $subId   = (int) $sub['id'];
                            $data    = json_decode($sub['data'] ?? '{}', true) ?: [];
                            $isRead  = (bool) $sub['is_read'];
                            $markUrl = admin_url('admin-post.php?action=laca_cf_mark_read&submission_id=' . $subId . '&form_id=' . $formId . '&' . self::NONCE_FIELD . '=' . wp_create_nonce(self::NONCE_ACTION));
                            $delUrl  = admin_url('admin-post.php?action=laca_cf_delete_submission&submission_id=' . $subId . '&form_id=' . $formId . '&' . self::NONCE_FIELD . '=' . wp_create_nonce(self::NONCE_ACTION));
                            ?>
                            <tr class="<?php echo $isRead ? '' : 'laca-cf-row-unread'; ?>">
                                <td><?php echo esc_html($subId); ?></td>
                                <td>
                                    <?php if ($isRead): ?>
                                        <span title="Đã đọc" style="color:#5cb85c">✓</span>
                                    <?php else: ?>
                                        <a href="<?php echo esc_url($markUrl); ?>" title="Đánh dấu đã đọc" class="laca-cf-mark-read">👁</a>
                                    <?php endif; ?>
                                </td>
                                <?php foreach ($fields as $field): ?>
                                    <td>
                                        <?php
                                        $val = $data[$field['name']] ?? '';
                                        if (is_array($val)) {
                                            echo esc_html(implode(', ', $val));
                                        } else {
                                            echo esc_html($val);
                                        }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                                <td><?php echo esc_html($sub['ip_address']); ?></td>
                                <td><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($sub['created_at']))); ?></td>
                                <td>
                                    <a href="<?php echo esc_url($delUrl); ?>"
                                       class="button button-small button-link-delete"
                                       onclick="return confirm('Xoá submission này?')">Xoá</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($pages > 1): ?>
                    <div class="tablenav">
                        <div class="tablenav-pages">
                            <?php for ($i = 1; $i <= $pages; $i++): ?>
                                <a href="<?php echo esc_url($pageUrl . '&action=submissions&id=' . $formId . '&paged=' . $i); ?>"
                                   class="button button-small <?php echo $i === $page ? 'button-primary' : ''; ?>">
                                    <?php echo esc_html($i); ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    // =========================================================================
    // ACTION HANDLERS
    // =========================================================================

    public function handleSave(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Không có quyền.', 'laca'));
        }
        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $formId   = absint($_POST['form_id'] ?? 0);
        $formName = sanitize_text_field($_POST['form_name'] ?? '');

        if (!$formName) {
            wp_redirect($this->buildRedirectUrl($formId, 'error_name'));
            exit;
        }

        $fieldsJson = stripslashes($_POST['fields_json'] ?? '[]');
        $rawData    = json_decode($fieldsJson, true) ?: [];

        // Sanitize row-based structure
        $cleanRows = [];
        foreach ($rawData as $row) {
            if (!isset($row['cols'])) {
                continue; // skip malformed
            }
            $cleanCols = [];
            foreach ($row['cols'] as $col) {
                $cleanFields = [];
                foreach ($col['fields'] ?? [] as $field) {
                    if (empty($field['name']) || empty($field['label'])) {
                        continue;
                    }
                    $cleanFields[] = [
                        'id'          => sanitize_key($field['id'] ?? uniqid('field_', true)),
                        'type'        => in_array($field['type'], array_keys(self::FIELD_TYPES), true) ? $field['type'] : 'text',
                        'name'        => sanitize_key($field['name']),
                        'label'       => sanitize_text_field($field['label']),
                        'placeholder' => sanitize_text_field($field['placeholder'] ?? ''),
                        'required'    => !empty($field['required']),
                        'options'     => array_map('sanitize_text_field', (array) ($field['options'] ?? [])),
                    ];
                }
                $span = (int) ($col['span'] ?? 12);
                $cleanCols[] = [
                    'id'     => sanitize_key($col['id'] ?? uniqid('col_', true)),
                    'span'   => in_array($span, self::ALLOWED_SPANS, true) ? $span : 12,
                    'fields' => $cleanFields,
                ];
            }
            $cleanRows[] = [
                'id'   => sanitize_key($row['id'] ?? uniqid('row_', true)),
                'cols' => $cleanCols,
            ];
        }

        $data = [
            'name'                   => $formName,
            'fields'                 => $cleanRows,
            'notify_email'           => sanitize_email($_POST['notify_email'] ?? ''),
            'email_admin_subject'    => sanitize_text_field($_POST['email_admin_subject'] ?? ''),
            'email_admin_body'       => sanitize_textarea_field($_POST['email_admin_body'] ?? ''),
            'email_customer_subject' => sanitize_text_field($_POST['email_customer_subject'] ?? ''),
            'email_customer_body'    => sanitize_textarea_field($_POST['email_customer_body'] ?? ''),
        ];

        if ($formId > 0) {
            ContactFormTable::updateForm($formId, $data);
            $redirectId = $formId;
        } else {
            $redirectId = ContactFormTable::insertForm($data);
        }

        wp_redirect($this->buildRedirectUrl($redirectId, 'saved'));
        exit;
    }

    public function handleDelete(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Không có quyền.', 'laca'));
        }
        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $formId = absint($_POST['form_id'] ?? 0);
        if ($formId > 0) {
            ContactFormTable::deleteForm($formId);
        }

        wp_redirect(admin_url('themes.php?page=' . self::MENU_SLUG . '&laca_msg=deleted'));
        exit;
    }

    public function handleDeleteSubmission(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Không có quyền.', 'laca'));
        }
        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $subId  = absint($_GET['submission_id'] ?? 0);
        $formId = absint($_GET['form_id'] ?? 0);
        if ($subId > 0) {
            ContactFormTable::deleteSubmission($subId);
        }

        wp_redirect(admin_url('themes.php?page=' . self::MENU_SLUG . '&action=submissions&id=' . $formId . '&laca_msg=deleted'));
        exit;
    }

    public function handleMarkRead(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Không có quyền.', 'laca'));
        }
        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $subId  = absint($_GET['submission_id'] ?? 0);
        $formId = absint($_GET['form_id'] ?? 0);
        if ($subId > 0) {
            ContactFormTable::markRead($subId);
        }

        wp_redirect(admin_url('themes.php?page=' . self::MENU_SLUG . '&action=submissions&id=' . $formId . '&laca_msg=marked_read'));
        exit;
    }

    public function handleExportCsv(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Không có quyền.', 'laca'));
        }
        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $formId = absint($_GET['form_id'] ?? 0);
        $form   = $formId ? ContactFormTable::getForm($formId) : null;
        if (!$form) {
            wp_die(esc_html__('Form không tồn tại.', 'laca'));
        }

        $fields = self::extractFlatFields($form);
        $subs   = ContactFormTable::getSubmissions($formId, 1, 9999);

        $filename = 'submissions-form-' . $formId . '-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM for Excel compatibility
        fwrite($out, "\xEF\xBB\xBF");

        // Header row
        $headers = ['#', 'Đọc', 'IP', 'Thời gian'];
        foreach ($fields as $field) {
            $headers[] = $field['label'];
        }
        fputcsv($out, $headers);

        // Data rows
        foreach ($subs as $idx => $sub) {
            $data = json_decode($sub['data'] ?? '{}', true) ?: [];
            $row  = [
                $sub['id'],
                $sub['is_read'] ? 'Đã đọc' : 'Chưa đọc',
                $sub['ip_address'],
                date_i18n('d/m/Y H:i', strtotime($sub['created_at'])),
            ];
            foreach ($fields as $field) {
                $val = $data[$field['name']] ?? '';
                $row[] = is_array($val) ? implode(', ', $val) : $val;
            }
            fputcsv($out, $row);
        }

        fclose($out);
        exit;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function buildRedirectUrl(int $formId, string $msg): string
    {
        $base = admin_url('themes.php?page=' . self::MENU_SLUG);
        if ($formId > 0) {
            return $base . '&action=edit&id=' . $formId . '&laca_msg=' . $msg;
        }
        return $base . '&laca_msg=' . $msg;
    }

    private function getFlashMessage(): ?array
    {
        $msg = sanitize_key($_GET['laca_msg'] ?? '');
        $map = [
            'saved'       => ['type' => 'success', 'text' => 'Đã lưu form thành công.'],
            'deleted'     => ['type' => 'success', 'text' => 'Đã xoá thành công.'],
            'marked_read' => ['type' => 'success', 'text' => 'Đã đánh dấu đã đọc.'],
            'error_name'  => ['type' => 'error',   'text' => 'Vui lòng nhập tên form.'],
        ];
        return $map[$msg] ?? null;
    }

    // =========================================================================
    // ADMIN CSS
    // =========================================================================

    private function renderPageStyles(): void
    {
        ?>
        <style>
        /* ── Wrap & header ────────────────────────────────────────────────── */
        .laca-cf-wrap { max-width: 100%; }
        .laca-cf-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; gap: 15px; }
        .laca-cf-header h1 { margin: 0 0 5px; }
        .laca-cf-subtitle { margin: 0; color: #666; }
        .laca-cf-btn-new { white-space: nowrap; }
        .laca-cf-notice { padding: 10px 15px; border-radius: 4px; margin-bottom: 15px; border-left: 4px solid; }
        .laca-cf-notice--success { background: #d4edda; border-color: #28a745; color: #155724; }
        .laca-cf-notice--error   { background: #f8d7da; border-color: #dc3545; color: #721c24; }
        .laca-cf-empty { padding: 30px; text-align: center; background: #f9f9f9; border: 1px dashed #ddd; border-radius: 4px; }
        .laca-cf-table th, .laca-cf-table td { vertical-align: middle; }
        .laca-cf-row-unread { background: #fff8e1 !important; font-weight: 600; }
        .laca-cf-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .laca-cf-badge--unread { background: #d9534f; color: #fff; }
        .laca-cf-badge--total  { background: #2271b1; color: #fff; padding: 5px 12px; font-size: 14px; }
        .laca-cf-shortcode { background: #f0f0f1; padding: 2px 6px; border-radius: 3px; font-size: 11px; }

        /* ── Page grid ────────────────────────────────────────────────────── */
        .laca-cf-grid { display: grid; grid-template-columns: 1fr 380px; gap: 20px; align-items: start; }
        .laca-cf-col-main, .laca-cf-col-sidebar { display: flex; flex-direction: column; gap: 20px; }
        .laca-cf-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; }
        .laca-cf-card h2 { margin-top: 0; font-size: 16px; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px; }

        /* ── Rows builder ─────────────────────────────────────────────────── */
        .laca-cf-rows-builder { display: flex; flex-direction: column; gap: 12px; }
        .laca-cf-fields-empty { color: #999; text-align: center; padding: 24px; background: #f9f9f9; border: 1px dashed #ddd; border-radius: 4px; margin: 0 0 12px; }

        /* Layout row */
        .laca-cf-layout-row { border: 1px solid #c3c4c7; border-radius: 6px; background: #fafafa; overflow: hidden; }
        .laca-cf-row-toolbar { display: flex; align-items: center; gap: 10px; padding: 7px 12px; background: #f0f0f1; border-bottom: 1px solid #ddd; }
        .lcf-row-drag-handle { cursor: grab; color: #aaa; flex-shrink: 0; padding: 4px 4px; border-radius: 3px; display: flex; align-items: center; }
        .lcf-row-drag-handle:hover { color: #2271b1; background: #e8f0fc; cursor: grab; }
        .lcf-row-drag-handle:active { cursor: grabbing; }
        .lcf-row-label { flex: 1; font-size: 11px; font-weight: 600; color: #888; letter-spacing: 0.02em; }
        .lcf-remove-row-btn { padding: 2px 8px; border: 1px solid #d9534f; background: #fff; color: #d9534f; border-radius: 3px; cursor: pointer; font-size: 11px; white-space: nowrap; }
        .lcf-remove-row-btn:hover { background: #fdf0f0; }

        /* Column slots */
        .laca-cf-row-content { display: flex; gap: 0; min-height: 80px; }
        .laca-cf-col-slot { display: flex; flex-direction: column; border-right: 1px dashed #ddd; }
        .laca-cf-col-slot:last-child { border-right: none; }
        .laca-cf-col-header { padding: 5px 10px; font-size: 11px; font-weight: 700; color: #888; background: #f5f5f5; border-bottom: 1px dashed #e0e0e0; text-align: center; letter-spacing: 0.02em; }
        .laca-cf-col-drop { flex: 1; min-height: 60px; padding: 10px; display: flex; flex-direction: column; gap: 7px; transition: background 0.15s; }
        .laca-cf-col-drop.sortable-over { background: #f0f6fc; }
        .lcf-col-empty-hint { border: 1px dashed #ccc; border-radius: 4px; padding: 12px 10px; text-align: center; color: #bbb; font-size: 11px; flex: 1; display: flex; align-items: center; justify-content: center; pointer-events: none; }
        .laca-cf-col-add-field { padding: 8px 10px; border-top: 1px dashed #e0e0e0; display: flex; gap: 5px; background: #f5f5f5; }
        .laca-cf-col-add-field select { flex: 1; height: 26px; font-size: 11px; padding: 0 4px; border: 1px solid #ccc; border-radius: 3px; }
        .laca-cf-col-add-field button { padding: 0 9px; height: 26px; font-size: 11px; background: #2271b1; color: #fff; border: none; border-radius: 3px; cursor: pointer; white-space: nowrap; font-weight: 600; }
        .laca-cf-col-add-field button:hover { background: #1a5a9e; }

        /* ── Field card (accordion) ───────────────────────────────────────── */
        .laca-cf-field-card { border: 1px solid #ddd; border-radius: 4px; background: #fff; overflow: hidden; }
        .laca-cf-field-card-header { display: flex; align-items: center; gap: 7px; padding: 7px 9px; background: #fafafa; cursor: pointer; user-select: none; }
        .laca-cf-field-card-header:hover { background: #f0f0f1; }
        .lcf-field-drag-handle { cursor: grab; color: #ccc; flex-shrink: 0; padding: 2px; display: flex; align-items: center; }
        .lcf-field-drag-handle:hover { color: #2271b1; cursor: grab; }
        .lcf-field-drag-handle:active { cursor: grabbing; }
        .lcf-type-badge { background: #2271b1; color: #fff; padding: 1px 7px; border-radius: 10px; font-size: 10px; white-space: nowrap; flex-shrink: 0; letter-spacing: 0.02em; }
        .lcf-label-preview { flex: 1; font-size: 12px; font-weight: 600; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .lcf-toggle-icon { color: #aaa; flex-shrink: 0; transition: transform 0.2s; display: flex; align-items: center; }
        .laca-cf-field-card.is-open .lcf-toggle-icon { transform: rotate(180deg); }
        .lcf-remove-field-btn { padding: 1px 6px; border: 1px solid #d9534f; background: #fff; color: #d9534f; border-radius: 3px; cursor: pointer; font-size: 11px; flex-shrink: 0; line-height: 1.5; }
        .lcf-remove-field-btn:hover { background: #fdf0f0; }
        .laca-cf-field-card-body { display: none; padding: 11px 12px; border-top: 1px solid #eee; background: #fff; }
        .laca-cf-field-card.is-open .laca-cf-field-card-body { display: block; }

        /* Field inputs inside accordion */
        .lcf-field-inputs { display: flex; flex-direction: column; gap: 8px; }
        .lcf-input-row { display: flex; flex-direction: column; gap: 3px; }
        .lcf-label { font-size: 12px; font-weight: 600; display: block; }
        .lcf-name-hint { margin: 2px 0 0; font-size: 11px; color: #999; }

        /* ── Add-row palette ──────────────────────────────────────────────── */
        .laca-cf-add-row-palette { margin-top: 14px; padding-top: 14px; border-top: 1px dashed #ddd; display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }
        .lcf-palette-label { font-size: 12px; font-weight: 700; color: #666; white-space: nowrap; }
        .lcf-add-row-btn { display: flex; align-items: center; gap: 5px; padding: 4px 10px; border: 1px solid #2271b1; background: #fff; color: #2271b1; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600; }
        .lcf-add-row-btn:hover { background: #f0f6fc; }

        /* Mini column-layout preview icons inside buttons */
        .lcf-row-preview { display: inline-flex; gap: 1px; align-items: center; height: 14px; }
        .lcf-row-preview::before, .lcf-row-preview::after { content: ''; display: block; background: #2271b1; border-radius: 1px; height: 14px; }
        .lcf-rp-1::before  { width: 18px; }
        .lcf-rp-2::before  { width: 9px; } .lcf-rp-2::after  { width: 9px; }
        .lcf-rp-3 { gap: 1px; }
        .lcf-rp-3::before  { width: 6px; } .lcf-rp-3::after  { width: 6px; }
        .lcf-rp-3 span::before { content:''; display:block; width:6px; height:14px; background:#2271b1; border-radius:1px; }
        .lcf-rp-4::before  { width: 5px; } .lcf-rp-4::after  { width: 5px; }
        .lcf-rp-1-2::before { width: 6px; } .lcf-rp-1-2::after { width: 12px; }
        .lcf-rp-2-1::before { width: 12px; } .lcf-rp-2-1::after { width: 6px; }

        /* ── Drag states ──────────────────────────────────────────────────── */
        .lcf-ghost    { opacity: 0.3; background: #e8f0fc !important; }
        .lcf-dragging { opacity: 0.85; box-shadow: 0 4px 16px rgba(0,0,0,.15); }

        /* ── Email cards ──────────────────────────────────────────────────── */
        .laca-cf-field-group { margin-bottom: 12px; }
        .laca-cf-field-group label { display: block; font-weight: 600; font-size: 12px; margin-bottom: 4px; }
        .laca-cf-email-body { font-family: monospace; font-size: 12px; resize: vertical; }
        .laca-cf-var-hint { background: #f0f6fc; border: 1px solid #c8dff7; border-radius: 4px; padding: 8px 10px; font-size: 12px; margin-top: 10px; }
        .laca-cf-var-hint code { background: #e8f0fe; padding: 1px 4px; border-radius: 3px; margin-right: 3px; font-size: 11px; }
        .required { color: #d9534f; }

        @media (max-width: 1200px) {
            .laca-cf-grid { grid-template-columns: 1fr; }
        }
        </style>
        <?php
    }
}
