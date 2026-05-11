<?php
namespace App\Features;

/**
 * EditorialWorkflow
 *
 * Adds a 4-stage editorial status machine on top of WordPress' built-in
 * post statuses:
 *
 *   draft → pending_review → approved → scheduled / publish
 *
 * Registered statuses:
 *   pending_review  — "Chờ duyệt"   (reviewer has submitted, awaiting editor)
 *   approved        — "Đã duyệt"    (editor approved, ready to schedule/publish)
 *
 * (draft and publish/scheduled are native WP statuses.)
 *
 * Features:
 *  - Custom statuses with labels in post list / edit screen
 *  - Status transition dropdown in the Publish meta box
 *  - Email notifications on transitions (to editor on submit, to author on approve/reject)
 *  - Admin column "Status" on post list showing badge
 *  - Capability gate: only editors/admins can approve; subscribers/contributors submit
 *  - Filter `lacadev/editorial/post_types` to enable on more post types
 *  - Action `lacadev/editorial/status_changed` fired on every transition
 *
 * @package App\Features
 */
class EditorialWorkflow
{
    /** Custom status slugs */
    private const STATUS_REVIEW   = 'pending_review';
    private const STATUS_APPROVED = 'approved';

    /** Option key to enable/disable per post type */
    private const OPTION_TYPES    = 'laca_editorial_post_types';

    public function init(): void
    {
        add_action('init',                          [$this, 'registerStatuses']);
        add_action('admin_footer-post.php',         [$this, 'injectStatusScript']);
        add_action('admin_footer-post-new.php',     [$this, 'injectStatusScript']);
        add_action('post_submitbox_misc_actions',   [$this, 'renderWorkflowPanel']);
        add_action('transition_post_status',        [$this, 'onStatusTransition'], 10, 3);
        add_action('admin_enqueue_scripts',         [$this, 'enqueueAssets']);

        // Post list column
        add_filter('manage_posts_columns',          [$this, 'addStatusColumn']);
        add_action('manage_posts_custom_column',    [$this, 'renderStatusColumn'], 10, 2);

        // Show custom statuses in the WP admin post list dropdown
        add_action('restrict_manage_posts',         [$this, 'addStatusFilter']);
        add_filter('parse_query',                   [$this, 'filterByStatus']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Register custom post statuses
    // ─────────────────────────────────────────────────────────────────────

    public function registerStatuses(): void
    {
        register_post_status(self::STATUS_REVIEW, [
            'label'                     => 'Chờ duyệt',
            'label_count'               => _n_noop('Chờ duyệt (%s)', 'Chờ duyệt (%s)'),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
        ]);

        register_post_status(self::STATUS_APPROVED, [
            'label'                     => 'Đã duyệt',
            'label_count'               => _n_noop('Đã duyệt (%s)', 'Đã duyệt (%s)'),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Edit-screen publish meta box additions
    // ─────────────────────────────────────────────────────────────────────

    public function renderWorkflowPanel(\WP_Post $post): void
    {
        if (!$this->isEnabledForType($post->post_type)) {
            return;
        }

        $currentStatus = $post->post_status;
        $allowedNext   = $this->allowedTransitions($currentStatus);

        if (empty($allowedNext)) {
            return;
        }

        wp_nonce_field('laca_editorial_status', '_laca_ew_nonce');
        ?>
        <div class="misc-pub-section laca-ew-section">
            <label for="laca_ew_status"><strong>Quy trình biên tập</strong></label>
            <div class="laca-ew-current">
                Trạng thái hiện tại:
                <span class="laca-ew-badge laca-ew-badge--<?php echo esc_attr($currentStatus); ?>">
                    <?php echo esc_html($this->statusLabel($currentStatus)); ?>
                </span>
            </div>
            <select name="laca_ew_next_status" id="laca_ew_status" style="width:100%;margin-top:6px">
                <option value="">— giữ nguyên —</option>
                <?php foreach ($allowedNext as $status): ?>
                    <?php if (!$this->canSetStatus($status)) continue; ?>
                    <option value="<?php echo esc_attr($status); ?>">
                        → <?php echo esc_html($this->statusLabel($status)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($currentStatus === self::STATUS_REVIEW && current_user_can('edit_others_posts')): ?>
                <div style="margin-top:6px">
                    <label for="laca_ew_feedback">Ghi chú (gửi cho tác giả)</label><br>
                    <textarea id="laca_ew_feedback" name="laca_ew_feedback"
                              rows="3" style="width:100%"></textarea>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /** Fix: make custom statuses appear in the status dropdown via JS */
    public function injectStatusScript(): void
    {
        global $post;
        if (!$post || !$this->isEnabledForType($post->post_type)) {
            return;
        }

        $statuses = [
            self::STATUS_REVIEW   => 'Chờ duyệt',
            self::STATUS_APPROVED => 'Đã duyệt',
        ];

        $current = $post->post_status;
        $label   = $statuses[$current] ?? '';
        ?>
        <script>
        (function(){
            <?php foreach ($statuses as $value => $label): ?>
            jQuery('#post_status').append('<option value="<?php echo esc_js($value); ?>"><?php echo esc_js($label); ?></option>');
            <?php endforeach; ?>

            <?php if (isset($statuses[$current])): ?>
            jQuery('#post_status').val('<?php echo esc_js($current); ?>');
            jQuery('#post-status-display').text('<?php echo esc_js($label); ?>');
            <?php endif; ?>
        }());
        </script>
        <?php
    }

    // ─────────────────────────────────────────────────────────────────────
    // Status transitions
    // ─────────────────────────────────────────────────────────────────────

    public function onStatusTransition(string $new, string $old, \WP_Post $post): void
    {
        // Handle the workflow panel's next-status selection
        if (isset($_POST['_laca_ew_nonce']) &&
            wp_verify_nonce($_POST['_laca_ew_nonce'], 'laca_editorial_status')) {
            $requested = sanitize_key($_POST['laca_ew_next_status'] ?? '');
            if ($requested && $this->canSetStatus($requested)) {
                // Unhook to avoid recursion, update, re-hook
                remove_action('transition_post_status', [$this, 'onStatusTransition'], 10);
                wp_update_post(['ID' => $post->ID, 'post_status' => $requested]);
                add_action('transition_post_status', [$this, 'onStatusTransition'], 10, 3);
                $new = $requested;
            }
        }

        if ($old === $new || !$this->isEnabledForType($post->post_type)) {
            return;
        }

        do_action('lacadev/editorial/status_changed', $post, $new, $old);
        $this->sendTransitionEmail($post, $new, $old);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Email notifications
    // ─────────────────────────────────────────────────────────────────────

    private function sendTransitionEmail(\WP_Post $post, string $new, string $old): void
    {
        $author      = get_userdata($post->post_author);
        $editUrl     = get_edit_post_link($post->ID, 'raw');
        $siteName    = get_bloginfo('name');
        $postTitle   = get_the_title($post->ID);
        $feedback    = sanitize_textarea_field($_POST['laca_ew_feedback'] ?? '');

        // Author submits for review → notify editors
        if ($new === self::STATUS_REVIEW) {
            $editors = get_users(['role__in' => ['editor', 'administrator'], 'fields' => 'user_email']);
            $to      = array_slice($editors, 0, 5); // limit
            if (!empty($to)) {
                wp_mail(
                    $to,
                    "[{$siteName}] Bài viết mới chờ duyệt: {$postTitle}",
                    "Tác giả {$author->display_name} đã gửi bài viết «{$postTitle}» để duyệt.\n\nXem & duyệt: {$editUrl}"
                );
            }
        }

        // Editor approves → notify author
        if ($new === self::STATUS_APPROVED && $author) {
            $body = "Bài viết của bạn «{$postTitle}» đã được duyệt.";
            if ($feedback) {
                $body .= "\n\nGhi chú từ biên tập viên:\n{$feedback}";
            }
            $body .= "\n\nXem bài: {$editUrl}";
            wp_mail($author->user_email, "[{$siteName}] Bài viết của bạn đã được duyệt", $body);
        }

        // Editor rejects (back to draft) → notify author
        if ($new === 'draft' && $old === self::STATUS_REVIEW && $author) {
            $body = "Bài viết của bạn «{$postTitle}» cần chỉnh sửa thêm trước khi được duyệt.";
            if ($feedback) {
                $body .= "\n\nGhi chú từ biên tập viên:\n{$feedback}";
            }
            $body .= "\n\nChỉnh sửa tại: {$editUrl}";
            wp_mail($author->user_email, "[{$siteName}] Bài viết cần chỉnh sửa thêm", $body);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Post list column
    // ─────────────────────────────────────────────────────────────────────

    public function addStatusColumn(array $columns): array
    {
        $post_type = get_current_screen()->post_type ?? '';
        if (!$this->isEnabledForType($post_type)) {
            return $columns;
        }

        // Insert after 'title'
        $newColumns = [];
        foreach ($columns as $key => $label) {
            $newColumns[$key] = $label;
            if ($key === 'title') {
                $newColumns['laca_ew_status'] = 'Trạng thái';
            }
        }
        return $newColumns;
    }

    public function renderStatusColumn(string $column, int $postId): void
    {
        if ($column !== 'laca_ew_status') {
            return;
        }

        $status = get_post_status($postId);
        printf(
            '<span class="laca-ew-badge laca-ew-badge--%s">%s</span>',
            esc_attr($status),
            esc_html($this->statusLabel($status))
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // List filter
    // ─────────────────────────────────────────────────────────────────────

    public function addStatusFilter(): void
    {
        $screen = get_current_screen();
        if (!$screen || !$this->isEnabledForType($screen->post_type)) {
            return;
        }

        $selected = sanitize_key($_GET['laca_ew_status_filter'] ?? '');
        ?>
        <select name="laca_ew_status_filter">
            <option value="">Tất cả trạng thái workflow</option>
            <?php foreach ($this->allStatuses() as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($selected, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public function filterByStatus(\WP_Query $query): void
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $filter = sanitize_key($_GET['laca_ew_status_filter'] ?? '');
        if ($filter && array_key_exists($filter, $this->allStatuses())) {
            $query->set('post_status', $filter);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Assets
    // ─────────────────────────────────────────────────────────────────────

    public function enqueueAssets(string $hook): void
    {
        if (!in_array($hook, ['post.php', 'post-new.php', 'edit.php'], true)) {
            return;
        }

        wp_add_inline_style('wp-admin', '
.laca-ew-section { padding: 8px 0; }
.laca-ew-current { font-size: 12px; margin-top: 4px; }
.laca-ew-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .5px;
}
.laca-ew-badge--draft           { background: #f3f4f6; color: #374151; }
.laca-ew-badge--pending_review  { background: #fef3c7; color: #92400e; }
.laca-ew-badge--approved        { background: #d1fae5; color: #065f46; }
.laca-ew-badge--publish         { background: #dbeafe; color: #1e40af; }
.laca-ew-badge--future          { background: #ede9fe; color: #5b21b6; }
.laca-ew-badge--private         { background: #fce7f3; color: #831843; }
        ');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    private function isEnabledForType(string $postType): bool
    {
        $types = (array) apply_filters('lacadev/editorial/post_types',
            get_option(self::OPTION_TYPES, ['post'])
        );
        return in_array($postType, $types, true);
    }

    /** Returns allowed next-status values from a given current status */
    private function allowedTransitions(string $current): array
    {
        return match ($current) {
            'draft'                  => [self::STATUS_REVIEW],
            'auto-draft'             => [self::STATUS_REVIEW],
            self::STATUS_REVIEW      => ['draft', self::STATUS_APPROVED, 'publish'],
            self::STATUS_APPROVED    => ['publish', 'future'],
            'publish'                => ['draft'],
            default                  => [],
        };
    }

    private function canSetStatus(string $status): bool
    {
        return match ($status) {
            self::STATUS_APPROVED, 'publish' => current_user_can('edit_others_posts'),
            'future'                          => current_user_can('edit_others_posts'),
            default                           => current_user_can('edit_posts'),
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'draft'                  => 'Bản nháp',
            'auto-draft'             => 'Bản nháp',
            self::STATUS_REVIEW      => 'Chờ duyệt',
            self::STATUS_APPROVED    => 'Đã duyệt',
            'publish'                => 'Đã xuất bản',
            'future'                 => 'Lên lịch',
            'private'                => 'Riêng tư',
            'trash'                  => 'Thùng rác',
            default                  => ucfirst($status),
        };
    }

    private function allStatuses(): array
    {
        return [
            'draft'               => 'Bản nháp',
            self::STATUS_REVIEW   => 'Chờ duyệt',
            self::STATUS_APPROVED => 'Đã duyệt',
            'publish'             => 'Đã xuất bản',
            'future'              => 'Lên lịch',
        ];
    }
}
