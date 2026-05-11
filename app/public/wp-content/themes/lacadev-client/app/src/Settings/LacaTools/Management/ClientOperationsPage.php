<?php

namespace App\Settings\LacaTools\Management;

use App\Databases\TrackerEventTable;
use App\Settings\BlockSyncReceiver;
use App\Settings\LacaDevTrackerClient;

class ClientOperationsPage
{
    private const MENU_SLUG = 'laca-client-ops';
    private const PARENT_SLUG = 'laca-admin';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu'], 30);
        add_action('admin_post_laca_client_ops_action', [$this, 'handleAction']);
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            self::PARENT_SLUG,
            __('Client Operations', 'laca'),
            __('Client Operations', 'laca'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render']
        );
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Bạn không có quyền truy cập trang này.', 'laca'));
        }

        $health = LacaDevTrackerClient::getHealthSummary();
        $hasOutbox = LacaDevTrackerClient::hasTrackerEventTable();
        $recentSupport = $hasOutbox
            ? TrackerEventTable::getRecent(12, 'support')
            : [];
        $recentEvents = $hasOutbox
            ? TrackerEventTable::getRecent(20)
            : [];
        $weeklySummary = $hasOutbox ? TrackerEventTable::getSummarySince(7) : [];
        $installedBlocks = get_option('laca_blocks_installed', []);
        $blockLog = get_option('laca_block_activity_log', []);
        $blockDiagnostics = class_exists(BlockSyncReceiver::class) ? BlockSyncReceiver::getDiagnostics() : [];
        $remoteHistory = LacaDevTrackerClient::getRemoteUpdateHistory();
        $readiness = $this->getReadiness($hasOutbox, is_array($blockDiagnostics) ? $blockDiagnostics : []);
        ?>
        <div class="wrap laca-client-ops">
            <h1><?php echo esc_html__('Client Operations', 'laca'); ?></h1>
            <p class="description"><?php echo esc_html__('Theo dõi kết nối LacaDev, support requests, queue gửi log và tình trạng vận hành tại website khách hàng.', 'laca'); ?></p>
            <?php $this->renderNotice(); ?>
            <?php $this->renderActions(); ?>

            <?php $this->renderStyles(); ?>

            <div class="laca-client-ops__grid">
                <?php $this->renderHealthCard($health); ?>
                <?php $this->renderQueueCard($health); ?>
                <?php $this->renderBlockSyncCard(is_array($installedBlocks) ? $installedBlocks : [], is_array($blockLog) ? $blockLog : [], is_array($blockDiagnostics) ? $blockDiagnostics : []); ?>
                <?php $this->renderWeeklySummaryCard($weeklySummary); ?>
                <?php $this->renderReadinessCard($readiness); ?>
            </div>

            <div class="laca-client-ops__panel">
                <div class="laca-client-ops__panel-head">
                    <h2><?php echo esc_html__('Yêu cầu hỗ trợ gần đây', 'laca'); ?></h2>
                    <code>[laca_support_center]</code>
                </div>
                <?php $this->renderSupportTable($recentSupport); ?>
            </div>

            <div class="laca-client-ops__panel">
                <div class="laca-client-ops__panel-head">
                    <h2><?php echo esc_html__('Timeline cho khách hàng', 'laca'); ?></h2>
                    <code>[laca_maintenance_timeline]</code>
                </div>
                <p class="laca-client-ops__empty"><?php echo esc_html__('Đặt shortcode này trên trang Client Portal để khách xem lịch sử bảo trì, cập nhật plugin/theme/core và yêu cầu hỗ trợ đã ghi nhận.', 'laca'); ?></p>
            </div>

            <div class="laca-client-ops__panel">
                <h2><?php echo esc_html__('Tracker event audit', 'laca'); ?></h2>
                <?php $this->renderEventTable($recentEvents); ?>
            </div>

            <div class="laca-client-ops__panel">
                <h2><?php echo esc_html__('Remote maintenance history', 'laca'); ?></h2>
                <?php $this->renderRemoteHistory($remoteHistory); ?>
            </div>

            <div class="laca-client-ops__panel">
                <h2><?php echo esc_html__('Block sync diagnostics', 'laca'); ?></h2>
                <?php $this->renderBlockDiagnostics(is_array($blockDiagnostics) ? $blockDiagnostics : []); ?>
            </div>
        </div>
        <?php
    }

    public function handleAction(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Bạn không có quyền thực hiện thao tác này.', 'laca'));
        }

        check_admin_referer('laca_client_ops_action');

        $task = sanitize_key((string) ($_POST['task'] ?? ''));
        $done = 'none';

        if ($task === 'heartbeat') {
            do_action(LacaDevTrackerClient::CRON_HEARTBEAT);
            $done = 'heartbeat';
        } elseif ($task === 'queue') {
            do_action(LacaDevTrackerClient::CRON_RETRY);
            $done = 'queue';
        } elseif ($task === 'summary') {
            do_action(LacaDevTrackerClient::CRON_WEEKLY_SUMMARY);
            $done = 'summary';
        }

        wp_safe_redirect(add_query_arg([
            'page' => self::MENU_SLUG,
            'laca_client_ops_done' => $done,
        ], admin_url('admin.php')));
        exit;
    }

    private function renderNotice(): void
    {
        $done = sanitize_key((string) ($_GET['laca_client_ops_done'] ?? ''));
        if ($done === '') {
            return;
        }

        $messages = [
            'heartbeat' => __('Đã chạy heartbeat thủ công.', 'laca'),
            'queue' => __('Đã chạy retry queue thủ công.', 'laca'),
            'summary' => __('Đã tạo báo cáo tuần thủ công.', 'laca'),
            'none' => __('Không có thao tác nào được chạy.', 'laca'),
        ];

        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($messages[$done] ?? __('Đã xử lý thao tác.', 'laca')) . '</p></div>';
    }

    private function renderActions(): void
    {
        ?>
        <div class="laca-client-ops__actions">
            <?php $this->renderActionButton('heartbeat', __('Gửi heartbeat', 'laca')); ?>
            <?php $this->renderActionButton('queue', __('Chạy queue retry', 'laca')); ?>
            <?php $this->renderActionButton('summary', __('Gửi báo cáo tuần', 'laca')); ?>
        </div>
        <?php
    }

    private function renderActionButton(string $task, string $label): void
    {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('laca_client_ops_action'); ?>
            <input type="hidden" name="action" value="laca_client_ops_action">
            <input type="hidden" name="task" value="<?php echo esc_attr($task); ?>">
            <button type="submit" class="button"><?php echo esc_html($label); ?></button>
        </form>
        <?php
    }

    private function renderHealthCard(array $health): void
    {
        $status = !empty($health['configured']) ? __('Đã cấu hình', 'laca') : __('Chưa cấu hình', 'laca');
        $statusClass = !empty($health['configured']) ? 'is-ok' : 'is-warn';
        if (!empty($health['last_error'])) {
            $status = __('Có lỗi gửi log', 'laca');
            $statusClass = 'is-bad';
        }
        ?>
        <section class="laca-client-ops__card">
            <span class="laca-client-ops__label"><?php echo esc_html__('Tracker status', 'laca'); ?></span>
            <strong class="<?php echo esc_attr($statusClass); ?>"><?php echo esc_html($status); ?></strong>
            <dl>
                <div><dt><?php echo esc_html__('Gửi thành công cuối', 'laca'); ?></dt><dd><?php echo esc_html($health['last_success_at'] ?: '-'); ?></dd></div>
                <div><dt><?php echo esc_html__('Lần thử cuối', 'laca'); ?></dt><dd><?php echo esc_html($health['last_attempt_at'] ?: '-'); ?></dd></div>
                <div><dt><?php echo esc_html__('HTTP', 'laca'); ?></dt><dd><?php echo esc_html((string) ($health['last_http_code'] ?? '-')); ?></dd></div>
            </dl>
            <?php if (!empty($health['last_error'])) : ?>
                <p class="laca-client-ops__error"><?php echo esc_html($health['last_error']); ?></p>
            <?php endif; ?>
        </section>
        <?php
    }

    private function renderQueueCard(array $health): void
    {
        ?>
        <section class="laca-client-ops__card">
            <span class="laca-client-ops__label"><?php echo esc_html__('Delivery queue', 'laca'); ?></span>
            <div class="laca-client-ops__stats">
                <?php $this->renderStat(__('Đang chờ', 'laca'), (int) $health['queued']); ?>
                <?php $this->renderStat(__('Retry', 'laca'), (int) $health['retry']); ?>
                <?php $this->renderStat(__('Lỗi', 'laca'), (int) $health['failed']); ?>
                <?php $this->renderStat(__('Đã gửi', 'laca'), (int) $health['delivered']); ?>
            </div>
        </section>
        <?php
    }

    private function renderBlockSyncCard(array $installedBlocks, array $blockLog, array $diagnostics): void
    {
        $lastLog = $blockLog[0] ?? null;
        $warningCount = 0;
        foreach ($diagnostics as $item) {
            if (is_array($item)) {
                $warningCount += count((array) ($item['warnings'] ?? []));
                $warningCount += count((array) ($item['errors'] ?? []));
            }
        }
        ?>
        <section class="laca-client-ops__card">
            <span class="laca-client-ops__label"><?php echo esc_html__('Block sync', 'laca'); ?></span>
            <strong><?php echo esc_html((string) count($installedBlocks)); ?> <?php echo esc_html__('blocks', 'laca'); ?></strong>
            <dl>
                <div>
                    <dt><?php echo esc_html__('Log gần nhất', 'laca'); ?></dt>
                    <dd><?php echo esc_html(is_array($lastLog) ? (string) ($lastLog['time'] ?? '-') : '-'); ?></dd>
                </div>
                <div>
                    <dt><?php echo esc_html__('Activity logs', 'laca'); ?></dt>
                    <dd><?php echo esc_html((string) count($blockLog)); ?></dd>
                </div>
                <div>
                    <dt><?php echo esc_html__('Diagnostics', 'laca'); ?></dt>
                    <dd><?php echo esc_html((string) $warningCount); ?> <?php echo esc_html__('warnings/errors', 'laca'); ?></dd>
                </div>
            </dl>
        </section>
        <?php
    }

    private function renderWeeklySummaryCard(array $summary): void
    {
        $byChannel = is_array($summary['by_channel'] ?? null) ? $summary['by_channel'] : [];
        ?>
        <section class="laca-client-ops__card">
            <span class="laca-client-ops__label"><?php echo esc_html__('7-day activity', 'laca'); ?></span>
            <strong><?php echo esc_html((string) (int) ($summary['total'] ?? 0)); ?> <?php echo esc_html__('events', 'laca'); ?></strong>
            <dl>
                <div><dt><?php echo esc_html__('Tracker', 'laca'); ?></dt><dd><?php echo esc_html((string) (int) ($byChannel['tracker'] ?? 0)); ?></dd></div>
                <div><dt><?php echo esc_html__('Support', 'laca'); ?></dt><dd><?php echo esc_html((string) (int) ($byChannel['support'] ?? 0)); ?></dd></div>
                <div><dt><?php echo esc_html__('Summary', 'laca'); ?></dt><dd><?php echo esc_html((string) (int) ($byChannel['summary'] ?? 0)); ?></dd></div>
            </dl>
        </section>
        <?php
    }

    private function renderReadinessCard(array $readiness): void
    {
        $issues = array_values(array_filter($readiness, static fn(array $item): bool => empty($item['ok'])));
        ?>
        <section class="laca-client-ops__card">
            <span class="laca-client-ops__label"><?php echo esc_html__('Operations readiness', 'laca'); ?></span>
            <strong class="<?php echo empty($issues) ? 'is-ok' : 'is-warn'; ?>">
                <?php echo empty($issues) ? esc_html__('Sẵn sàng', 'laca') : esc_html(count($issues) . ' mục cần kiểm tra'); ?>
            </strong>
            <dl>
                <?php foreach ($readiness as $item) : ?>
                    <div>
                        <dt><?php echo esc_html($item['label']); ?></dt>
                        <dd><?php echo esc_html(!empty($item['ok']) ? __('OK', 'laca') : (string) $item['note']); ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </section>
        <?php
    }

    private function renderStat(string $label, int $value): void
    {
        ?>
        <div class="laca-client-ops__stat">
            <strong><?php echo esc_html((string) $value); ?></strong>
            <span><?php echo esc_html($label); ?></span>
        </div>
        <?php
    }

    private function getReadiness(bool $hasOutbox, array $blockDiagnostics): array
    {
        $blockIssues = 0;
        foreach ($blockDiagnostics as $item) {
            if (!is_array($item)) {
                continue;
            }

            $blockIssues += count((array) ($item['errors'] ?? []));
            $blockIssues += count((array) ($item['warnings'] ?? []));
        }

        return [
            [
                'label' => __('Tracker config', 'laca'),
                'ok' => LacaDevTrackerClient::isConfigured(),
                'note' => __('Chưa cấu hình endpoint/secret.', 'laca'),
            ],
            [
                'label' => __('Outbox table', 'laca'),
                'ok' => $hasOutbox,
                'note' => __('Chưa tạo được bảng queue.', 'laca'),
            ],
            [
                'label' => __('Retry cron', 'laca'),
                'ok' => (bool) wp_next_scheduled(LacaDevTrackerClient::CRON_RETRY),
                'note' => __('Chưa schedule retry cron.', 'laca'),
            ],
            [
                'label' => __('Heartbeat cron', 'laca'),
                'ok' => (bool) wp_next_scheduled(LacaDevTrackerClient::CRON_HEARTBEAT),
                'note' => __('Chưa schedule heartbeat.', 'laca'),
            ],
            [
                'label' => __('Block diagnostics', 'laca'),
                'ok' => $blockIssues === 0,
                'note' => sprintf(__('%d cảnh báo/lỗi', 'laca'), $blockIssues),
            ],
        ];
    }

    private function renderSupportTable(array $rows): void
    {
        if (empty($rows)) {
            echo '<p class="laca-client-ops__empty">' . esc_html__('Chưa có yêu cầu hỗ trợ nào.', 'laca') . '</p>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('Mã', 'laca') . '</th>';
        echo '<th>' . esc_html__('Trạng thái', 'laca') . '</th>';
        echo '<th>' . esc_html__('Người gửi', 'laca') . '</th>';
        echo '<th>' . esc_html__('Loại', 'laca') . '</th>';
        echo '<th>' . esc_html__('Thời gian', 'laca') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $context = TrackerEventTable::decodeJsonColumn($row['context'] ?? '');
            echo '<tr>';
            echo '<td><code>' . esc_html((string) ($context['request_id'] ?? $row['event_uuid'])) . '</code></td>';
            echo '<td>' . esc_html((string) ($row['status'] ?? '')) . '</td>';
            echo '<td>' . esc_html(trim(($context['contact_name'] ?? '') . ' ' . ($context['contact_email'] ?? ''))) . '</td>';
            echo '<td>' . esc_html((string) ($context['request_type'] ?? $row['event_type'])) . '</td>';
            echo '<td>' . esc_html((string) ($row['created_at'] ?? '')) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function renderEventTable(array $rows): void
    {
        if (empty($rows)) {
            echo '<p class="laca-client-ops__empty">' . esc_html__('Chưa có tracker event.', 'laca') . '</p>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('Channel', 'laca') . '</th>';
        echo '<th>' . esc_html__('Type', 'laca') . '</th>';
        echo '<th>' . esc_html__('Status', 'laca') . '</th>';
        echo '<th>' . esc_html__('Attempts', 'laca') . '</th>';
        echo '<th>' . esc_html__('Error', 'laca') . '</th>';
        echo '<th>' . esc_html__('Updated', 'laca') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td>' . esc_html((string) ($row['channel'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['event_type'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['status'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['attempts'] ?? '0')) . '</td>';
            echo '<td>' . esc_html((string) ($row['last_error'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['updated_at'] ?? '')) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function renderRemoteHistory(array $rows): void
    {
        if (empty($rows)) {
            echo '<p class="laca-client-ops__empty">' . esc_html__('Chưa có thao tác remote maintenance.', 'laca') . '</p>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('Time', 'laca') . '</th>';
        echo '<th>' . esc_html__('Action', 'laca') . '</th>';
        echo '<th>' . esc_html__('Target', 'laca') . '</th>';
        echo '<th>' . esc_html__('Status', 'laca') . '</th>';
        echo '<th>' . esc_html__('Message', 'laca') . '</th>';
        echo '<th>' . esc_html__('Preflight', 'laca') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $meta = is_array($row['meta'] ?? null) ? $row['meta'] : [];
            $preflight = is_array($meta['preflight'] ?? null) ? $meta['preflight'] : [];
            $warnings = count((array) ($preflight['warnings'] ?? []));
            $errors = count((array) ($preflight['errors'] ?? []));
            echo '<tr>';
            echo '<td>' . esc_html((string) ($row['time'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['action'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['slug'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['status'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string) ($row['message'] ?? '')) . '</td>';
            echo '<td>' . esc_html($errors . ' errors / ' . $warnings . ' warnings') . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function renderBlockDiagnostics(array $items): void
    {
        if (empty($items)) {
            echo '<p class="laca-client-ops__empty">' . esc_html__('Chưa có diagnostics block sync.', 'laca') . '</p>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('Block', 'laca') . '</th>';
        echo '<th>' . esc_html__('Version', 'laca') . '</th>';
        echo '<th>' . esc_html__('Synced', 'laca') . '</th>';
        echo '<th>' . esc_html__('Files', 'laca') . '</th>';
        echo '<th>' . esc_html__('Warnings', 'laca') . '</th>';
        echo '<th>' . esc_html__('Compatibility', 'laca') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($items as $blockName => $item) {
            if (!is_array($item)) {
                continue;
            }

            $compat = is_array($item['compatibility'] ?? null) ? $item['compatibility'] : [];
            $messages = array_merge((array) ($item['errors'] ?? []), (array) ($item['warnings'] ?? []));
            echo '<tr>';
            echo '<td><strong>' . esc_html((string) $blockName) . '</strong></td>';
            echo '<td>' . esc_html((string) ($item['old_version'] ?? '-')) . ' → ' . esc_html((string) ($item['new_version'] ?? '-')) . '</td>';
            echo '<td>' . esc_html((string) ($item['synced_at'] ?? '-')) . '<br><span class="laca-client-ops__muted">' . esc_html((string) ($item['synced_by'] ?? '-')) . '</span></td>';
            echo '<td>' . esc_html((string) ($item['file_count'] ?? 0)) . '</td>';
            echo '<td>' . esc_html(empty($messages) ? 'OK' : implode(' ', $messages)) . '</td>';
            echo '<td>' . esc_html('WP ' . ($compat['wp_version'] ?? '-') . ' / PHP ' . ($compat['php_version'] ?? '-')) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function renderStyles(): void
    {
        ?>
        <style>
            .laca-client-ops { max-width: 1180px; }
            .laca-client-ops__actions { display: flex; flex-wrap: wrap; gap: 8px; margin: 16px 0; }
            .laca-client-ops__actions form { margin: 0; }
            .laca-client-ops__grid { display: grid; gap: 16px; grid-template-columns: repeat(2, minmax(0, 1fr)); margin: 18px 0; }
            .laca-client-ops__card, .laca-client-ops__panel { background: #fff; border: 1px solid #dbe1ea; border-radius: 8px; padding: 18px; }
            .laca-client-ops__label { color: #64748b; display: block; font-size: 12px; font-weight: 700; margin-bottom: 8px; text-transform: uppercase; }
            .laca-client-ops__card > strong { display: block; font-size: 24px; line-height: 1.2; margin-bottom: 14px; }
            .laca-client-ops__card dl { display: grid; gap: 8px; margin: 0; }
            .laca-client-ops__card dl div { align-items: center; display: flex; justify-content: space-between; }
            .laca-client-ops__card dt { color: #64748b; font-weight: 500; }
            .laca-client-ops__card dd { margin: 0; }
            .laca-client-ops__stats { display: grid; gap: 12px; grid-template-columns: repeat(4, minmax(0, 1fr)); }
            .laca-client-ops__stat { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; }
            .laca-client-ops__stat strong { display: block; font-size: 24px; line-height: 1; }
            .laca-client-ops__stat span { color: #64748b; display: block; margin-top: 6px; }
            .laca-client-ops__panel { margin-top: 16px; }
            .laca-client-ops__panel-head { align-items: center; display: flex; justify-content: space-between; gap: 12px; }
            .laca-client-ops__panel h2 { margin-top: 0; }
            .laca-client-ops__error { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; color: #991b1b; padding: 10px 12px; }
            .laca-client-ops__empty { color: #64748b; margin: 0; }
            .laca-client-ops__muted { color: #64748b; font-size: 12px; }
            .is-ok { color: #047857; }
            .is-warn { color: #b45309; }
            .is-bad { color: #b91c1c; }
            @media (max-width: 900px) {
                .laca-client-ops__grid, .laca-client-ops__stats { grid-template-columns: 1fr; }
            }
        </style>
        <?php
    }
}
