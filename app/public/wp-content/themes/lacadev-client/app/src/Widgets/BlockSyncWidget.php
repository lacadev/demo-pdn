<?php

namespace App\Widgets;

/**
 * BlockSyncWidget
 *
 * WordPress Dashboard Widget hiển thị activity log của Block Sync.
 * Improvements v2:
 *  - Log tăng từ 50 → 200 entries
 *  - Search/filter log theo tên block (client-side JS)
 *  - Phân trang: hiển thị 20 entries/page, Previous/Next
 *  - Hiển thị đầy đủ installed blocks với link edit
 *
 * Ghi chú: BlockSyncReceiver cũng cần cập nhật max log = 200.
 */
class BlockSyncWidget
{
    private const LOG_OPTION   = 'laca_block_activity_log';
    private const INST_OPTION  = 'laca_blocks_installed';
    private const PAGE_SIZE    = 20;

    public function __construct()
    {
        add_action('wp_dashboard_setup',     [$this, 'register']);
        add_action('admin_enqueue_scripts',  [$this, 'enqueueAssets']);
        add_action('wp_ajax_laca_clear_sync_log', [$this, 'ajaxClearLog']);
    }

    public function register(): void
    {
        if (function_exists('lacadev_dashboard_widget_enabled') && !lacadev_dashboard_widget_enabled('block_sync')) {
            return;
        }

        wp_add_dashboard_widget(
            'laca_block_sync_widget',
            '📦 LacaDev Block Updates',
            [$this, 'render']
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Render
    // ─────────────────────────────────────────────────────────────────────

    public function render(): void
    {
        $log       = get_option(self::LOG_OPTION, []);
        $installed = get_option(self::INST_OPTION, []);
        $count     = count($installed);
        $logCount  = count($log);
        $nonce     = wp_create_nonce('laca_clear_sync_log');
        ?>
        <div class="laca-bsw" id="laca-bsw">

            <!-- Stats bar -->
            <div class="laca-bsw__stats">
                <div class="laca-bsw__stat">
                    <span class="laca-bsw__stat-num"><?php echo esc_html($count); ?></span>
                    <span class="laca-bsw__stat-label">Blocks đã cài</span>
                </div>
                <div class="laca-bsw__stat">
                    <span class="laca-bsw__stat-num" id="laca-bsw-log-count"><?php echo esc_html($logCount); ?></span>
                    <span class="laca-bsw__stat-label">Logs</span>
                </div>
                <div class="laca-bsw__stat-actions">
                    <button type="button" class="button button-small laca-bsw-clear-log"
                            data-nonce="<?php echo esc_attr($nonce); ?>"
                            title="Xóa toàn bộ log">
                        🗑 Xóa log
                    </button>
                </div>
            </div>

            <!-- Search -->
            <div class="laca-bsw__search">
                <input type="search" id="laca-bsw-search" placeholder="🔍 Lọc log theo tên block…"
                       class="laca-bsw__search-input">
                <span id="laca-bsw-search-count" class="laca-bsw__search-count"></span>
            </div>

            <!-- Log list -->
            <?php if (empty($log)): ?>
                <p class="laca-bsw__empty">
                    📭 Chưa có block nào được sync.<br>
                    <small>Push blocks từ lacadev.com để bắt đầu.</small>
                </p>
            <?php else: ?>
                <ul class="laca-bsw__log" id="laca-bsw-log">
                    <?php foreach ($log as $i => $entry): ?>
                    <li class="laca-bsw__log-item"
                        data-msg="<?php echo esc_attr(wp_strip_all_tags($entry['message'])); ?>"
                        data-page="<?php echo (int) floor($i / self::PAGE_SIZE); ?>">
                        <span class="laca-bsw__log-time">
                            <?php echo esc_html($this->formatTime($entry['time'])); ?>
                        </span>
                        <span class="laca-bsw__log-msg">
                            <?php echo wp_kses($entry['message'], ['strong' => []]); ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <!-- Pagination -->
                <?php $pages = (int) ceil($logCount / self::PAGE_SIZE); ?>
                <?php if ($pages > 1): ?>
                <div class="laca-bsw__pager" id="laca-bsw-pager"
                     data-pages="<?php echo esc_attr($pages); ?>">
                    <button type="button" class="button button-small" id="laca-bsw-prev" disabled>← Trước</button>
                    <span id="laca-bsw-page-indicator">Trang 1 / <?php echo esc_html($pages); ?></span>
                    <button type="button" class="button button-small" id="laca-bsw-next">Sau →</button>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Installed blocks -->
            <?php if (!empty($installed)): ?>
                <details class="laca-bsw__installed">
                    <summary>Blocks đã cài (<?php echo esc_html($count); ?>)</summary>
                    <ul class="laca-bsw__block-chips">
                        <?php foreach ($installed as $name => $version): ?>
                        <li class="laca-bsw__chip">
                            <?php echo esc_html($name); ?>
                            <span class="laca-bsw__chip-ver"><?php echo esc_html($version); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>

        </div>
        <?php
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX: clear log
    // ─────────────────────────────────────────────────────────────────────

    public function ajaxClearLog(): void
    {
        check_ajax_referer('laca_clear_sync_log', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }
        delete_option('laca_block_activity_log');
        wp_send_json_success(['cleared' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Assets
    // ─────────────────────────────────────────────────────────────────────

    public function enqueueAssets(string $hook): void
    {
        if ('index.php' !== $hook) {
            return;
        }

        wp_add_inline_style('wp-admin', $this->inlineCss());
        wp_add_inline_script('jquery', $this->inlineJs());
    }

    private function inlineCss(): string
    {
        return '
.laca-bsw { font-size: 13px; }
.laca-bsw__stats {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 12px 16px;
    margin: -12px -12px 12px;
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
    border-radius: 4px 4px 0 0;
}
.laca-bsw__stat { color: #fff; text-align: center; }
.laca-bsw__stat-num { display: block; font-size: 22px; font-weight: 700; line-height: 1; }
.laca-bsw__stat-label { display: block; font-size: 11px; opacity: .8; margin-top: 2px; }
.laca-bsw__stat-actions { margin-left: auto; }
.laca-bsw__search { margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
.laca-bsw__search-input { width: 100%; padding: 4px 8px; font-size: 12px; border: 1px solid #ddd; border-radius: 4px; }
.laca-bsw__search-count { font-size: 11px; color: #888; white-space: nowrap; }
.laca-bsw__empty { color: #999; font-size: 13px; text-align: center; padding: 16px 0; margin: 0; }
.laca-bsw__log { margin: 0; padding: 0; list-style: none; max-height: 280px; overflow-y: auto; }
.laca-bsw__log-item { padding: 7px 0; border-bottom: 1px solid #f0f0f0; font-size: 12px; display: flex; gap: 10px; align-items: flex-start; }
.laca-bsw__log-item--hidden { display: none !important; }
.laca-bsw__log-time { color: #888; white-space: nowrap; min-width: 80px; }
.laca-bsw__log-msg { line-height: 1.5; }
.laca-bsw__pager { display: flex; align-items: center; gap: 8px; justify-content: center; margin-top: 8px; font-size: 12px; color: #666; }
.laca-bsw__installed { margin-top: 12px; }
.laca-bsw__installed summary { cursor: pointer; font-size: 12px; color: #666; padding: 4px 0; }
.laca-bsw__block-chips { margin: 8px 0 0; padding: 0; list-style: none; display: flex; flex-wrap: wrap; gap: 4px; }
.laca-bsw__chip { background: #f0fdf4; color: #166534; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-family: monospace; border: 1px solid #bbf7d0; }
.laca-bsw__chip-ver { opacity: .7; }
';
    }

    private function inlineJs(): string
    {
        return '
(function($){
    var pageSize = ' . self::PAGE_SIZE . ';
    var currentPage = 0;

    function renderPage(page, items) {
        items.each(function(i) {
            $(this).toggleClass("laca-bsw__log-item--hidden", Math.floor(i / pageSize) !== page);
        });
        var pages = parseInt($("#laca-bsw-pager").data("pages") || 0);
        if (pages) {
            $("#laca-bsw-page-indicator").text("Trang " + (page + 1) + " / " + pages);
            $("#laca-bsw-prev").prop("disabled", page === 0);
            $("#laca-bsw-next").prop("disabled", page >= pages - 1);
        }
    }

    // Initial render
    var $items = $(".laca-bsw__log-item");
    renderPage(0, $items);

    // Pagination
    $("#laca-bsw-prev").on("click", function(){
        if (currentPage > 0) { currentPage--; renderPage(currentPage, $(".laca-bsw__log-item:visible, .laca-bsw__log-item")); }
    });
    $("#laca-bsw-next").on("click", function(){
        var pages = parseInt($("#laca-bsw-pager").data("pages") || 0);
        if (currentPage < pages - 1) { currentPage++; renderPage(currentPage, $(".laca-bsw__log-item")); }
    });

    // Search
    var searchTimer;
    $("#laca-bsw-search").on("input", function(){
        clearTimeout(searchTimer);
        var q = this.value.trim().toLowerCase();
        searchTimer = setTimeout(function(){
            currentPage = 0;
            var matched = 0;
            $(".laca-bsw__log-item").each(function(i){
                var msg = $(this).data("msg").toLowerCase();
                var show = !q || msg.includes(q);
                $(this).toggleClass("laca-bsw__log-item--hidden", !show);
                if (show) {
                    $(this).data("filteredIdx", matched);
                    matched++;
                }
            });
            $("#laca-bsw-search-count").text(q ? matched + " kết quả" : "");
            // Hide pager during search
            $("#laca-bsw-pager").toggle(!q);
            if (!q) renderPage(0, $(".laca-bsw__log-item"));
        }, 200);
    });

    // Clear log
    $(".laca-bsw-clear-log").on("click", function(){
        if (!confirm("Xóa toàn bộ activity log?")) return;
        var nonce = $(this).data("nonce");
        $.post(ajaxurl, { action: "laca_clear_sync_log", nonce: nonce }, function(res){
            if (res.success) {
                $("#laca-bsw-log").empty().closest(".laca-bsw").find(".laca-bsw__pager").hide();
                $("#laca-bsw-log-count").text("0");
            }
        });
    });
}(jQuery));
';
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    private function formatTime(string $mysqlTime): string
    {
        $timestamp = strtotime($mysqlTime);
        if (!$timestamp) {
            return $mysqlTime;
        }

        $diff = time() - $timestamp;
        if ($diff < 60) return 'Vừa xong';
        if ($diff < 3600) return round($diff / 60) . ' phút trước';
        if ($diff < 86400) return round($diff / 3600) . ' giờ trước';
        return date_i18n('d/m H:i', $timestamp);
    }
}
