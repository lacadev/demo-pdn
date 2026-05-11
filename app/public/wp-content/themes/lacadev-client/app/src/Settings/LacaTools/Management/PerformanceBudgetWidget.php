<?php
namespace App\Settings\LacaTools\Management;

/**
 * PerformanceBudgetWidget
 *
 * Dashboard widget that surfaces Core Web Vitals (via Google CrUX API)
 * and local bundle size summary for the current site.
 *
 * Configuration:
 *   laca_crux_api_key   — Google CrUX API key (optional, increases quota)
 *   laca_crux_url       — URL to measure (defaults to home_url())
 *
 * Data is cached for 24 hours in a transient.
 * Admins can force-refresh via AJAX button.
 *
 * @package App\Settings\LacaTools\Management
 */
class PerformanceBudgetWidget
{
    private const TRANSIENT_KEY = 'laca_cwv_data';
    private const CACHE_TTL     = 86400; // 24 hours
    private const CRUX_ENDPOINT = 'https://chromeuxreport.googleapis.com/v1/records:queryRecord';

    // Core Web Vitals thresholds (Good / Needs Improvement / Poor)
    private const THRESHOLDS = [
        'lcp'  => ['good' => 2500,  'poor' => 4000],   // ms
        'fid'  => ['good' => 100,   'poor' => 300],    // ms
        'cls'  => ['good' => 0.1,   'poor' => 0.25],   // score
        'fcp'  => ['good' => 1800,  'poor' => 3000],   // ms
        'ttfb' => ['good' => 800,   'poor' => 1800],   // ms
        'inp'  => ['good' => 200,   'poor' => 500],    // ms
    ];

    private function getCruxApiKey(): string
    {
        $value = get_option('laca_crux_api_key', '');
        if ($value === '' && function_exists('carbon_get_theme_option')) {
            $value = carbon_get_theme_option('laca_crux_api_key') ?: '';
        }

        return (string) $value;
    }

    private function getCruxUrl(): string
    {
        $value = get_option('laca_crux_url', '');
        if ($value === '' && function_exists('carbon_get_theme_option')) {
            $value = carbon_get_theme_option('laca_crux_url') ?: '';
        }

        return $value !== '' ? (string) $value : home_url('/');
    }

    public function register(): void
    {
        add_action('wp_dashboard_setup',       [$this, 'addWidget']);
        add_action('admin_enqueue_scripts',    [$this, 'enqueueAssets']);
        add_action('wp_ajax_laca_refresh_cwv', [$this, 'ajaxRefresh']);
    }

    public function addWidget(): void
    {
        if (function_exists('lacadev_dashboard_widget_enabled') && !lacadev_dashboard_widget_enabled('performance_budget')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        wp_add_dashboard_widget(
            'laca_performance_budget',
            '⚡ Performance Budget',
            [$this, 'renderWidget']
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Widget config panel (gear icon)
    // ─────────────────────────────────────────────────────────────────────

    public function renderWidgetConfig(): void
    {
        if (isset($_POST['laca_cwv_config_nonce']) &&
            wp_verify_nonce($_POST['laca_cwv_config_nonce'], 'laca_cwv_config')) {
            update_option('laca_crux_api_key', sanitize_text_field($_POST['laca_crux_api_key'] ?? ''));
            update_option('laca_crux_url',     esc_url_raw($_POST['laca_crux_url'] ?? ''));
            delete_transient(self::TRANSIENT_KEY); // bust cache
        }

        $apiKey = $this->getCruxApiKey();
        $url    = $this->getCruxUrl();
        wp_nonce_field('laca_cwv_config', 'laca_cwv_config_nonce');
        ?>
        <p>
            <label><strong>CrUX API Key</strong><br>
            <input type="text" name="laca_crux_api_key" value="<?php echo esc_attr($apiKey); ?>"
                   class="widefat" placeholder="AIza…">
            </label>
        </p>
        <p>
            <label><strong>URL cần đo</strong><br>
            <input type="url" name="laca_crux_url" value="<?php echo esc_attr($url); ?>"
                   class="widefat" placeholder="<?php echo esc_attr(home_url('/')); ?>">
            </label>
        </p>
        <?php
    }

    // ─────────────────────────────────────────────────────────────────────
    // Widget render
    // ─────────────────────────────────────────────────────────────────────

    public function renderWidget(): void
    {
        $data     = get_transient(self::TRANSIENT_KEY);
        $fetched  = false;

        if ($data === false) {
            $data    = $this->fetchCruxData();
            $fetched = true;
            if ($data) {
                set_transient(self::TRANSIENT_KEY, $data, self::CACHE_TTL);
            }
        }

        $bundles   = $this->getBundleSizes();
        $nonce     = wp_create_nonce('laca_refresh_cwv');
        $cacheTime = $fetched ? __('agora mesmo', 'lacadev') : human_time_diff((int) get_option('_transient_timeout_' . self::TRANSIENT_KEY));
        ?>
        <div class="laca-perf-widget" id="laca-perf-widget">
            <?php $this->renderCwvSection($data); ?>
            <?php $this->renderBundleSection($bundles); ?>

            <div class="laca-perf-footer">
                <span class="laca-perf-cache-time">
                    <?php printf('Cập nhật: %s trước', esc_html($cacheTime)); ?>
                </span>
                <button type="button" class="button button-small laca-perf-refresh"
                        data-nonce="<?php echo esc_attr($nonce); ?>">
                    ↻ Làm mới
                </button>
            </div>
        </div>
        <?php
    }

    private function renderCwvSection(?array $data): void
    {
        echo '<h4 style="margin:0 0 8px;font-size:12px;text-transform:uppercase;color:#666;">Core Web Vitals (CrUX)</h4>';

        if (empty($data['metrics'])) {
            $url = $this->getCruxUrl();
            echo '<p style="color:#999;font-size:12px;">Không có dữ liệu CrUX. ';
            if (!$this->getCruxApiKey()) {
                echo 'Hãy thêm CrUX API Key trong cài đặt widget.';
            } else {
                echo 'URL có thể chưa có đủ traffic để CrUX thu thập.';
            }
            echo '</p>';
            return;
        }

        echo '<div class="laca-cwv-grid">';
        foreach ($data['metrics'] as $key => $metric) {
            $label  = strtoupper($key);
            $p75    = $metric['p75'] ?? null;
            $rating = $p75 !== null ? $this->getRating($key, $p75) : 'na';
            $unit   = in_array($key, ['cls']) ? '' : 'ms';
            $display = $p75 !== null
                ? (in_array($key, ['cls']) ? number_format($p75, 3) : number_format($p75) . $unit)
                : 'N/A';
            ?>
            <div class="laca-cwv-metric laca-cwv-metric--<?php echo esc_attr($rating); ?>">
                <span class="laca-cwv-label"><?php echo esc_html($label); ?></span>
                <span class="laca-cwv-value"><?php echo esc_html($display); ?></span>
                <span class="laca-cwv-badge"><?php echo esc_html($this->ratingLabel($rating)); ?></span>
            </div>
            <?php
        }
        echo '</div>';
    }

    private function renderBundleSection(array $bundles): void
    {
        if (empty($bundles)) {
            return;
        }

        echo '<h4 style="margin:12px 0 8px;font-size:12px;text-transform:uppercase;color:#666;">Bundle Sizes</h4>';
        echo '<div class="laca-bundle-list">';
        foreach ($bundles as $label => $bytes) {
            $rating = $this->getBundleRating($label, $bytes);
            printf(
                '<div class="laca-bundle-item laca-bundle-item--%s"><span>%s</span><strong>%s</strong></div>',
                esc_attr($rating),
                esc_html($label),
                esc_html($this->formatBytes($bytes))
            );
        }
        echo '</div>';
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJAX refresh
    // ─────────────────────────────────────────────────────────────────────

    public function ajaxRefresh(): void
    {
        check_ajax_referer('laca_refresh_cwv', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }

        delete_transient(self::TRANSIENT_KEY);
        $data = $this->fetchCruxData();
        if ($data) {
            set_transient(self::TRANSIENT_KEY, $data, self::CACHE_TTL);
        }

        ob_start();
        $this->renderCwvSection($data);
        $this->renderBundleSection($this->getBundleSizes());
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // CrUX API fetch
    // ─────────────────────────────────────────────────────────────────────

    private function fetchCruxData(): ?array
    {
        $apiKey = $this->getCruxApiKey();
        $url    = $this->getCruxUrl();

        $endpoint = self::CRUX_ENDPOINT . ($apiKey ? '?key=' . urlencode($apiKey) : '');

        $response = wp_remote_post($endpoint, [
            'headers'     => ['Content-Type' => 'application/json'],
            'body'        => wp_json_encode([
                'url'           => $url,
                'formFactor'    => 'PHONE',
                'metrics'       => ['largest_contentful_paint', 'first_contentful_paint',
                                    'cumulative_layout_shift',  'first_input_delay',
                                    'experimental_time_to_first_byte', 'interaction_to_next_paint'],
            ]),
            'timeout'     => 15,
            'sslverify'   => !WP_DEBUG,
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['record']['metrics'])) {
            return null;
        }

        // Normalise: extract p75 values keyed by short name
        $metricMap = [
            'largest_contentful_paint'         => 'lcp',
            'first_contentful_paint'            => 'fcp',
            'cumulative_layout_shift'           => 'cls',
            'first_input_delay'                 => 'fid',
            'experimental_time_to_first_byte'   => 'ttfb',
            'interaction_to_next_paint'         => 'inp',
        ];

        $metrics = [];
        foreach ($body['record']['metrics'] as $rawKey => $rawData) {
            $key = $metricMap[$rawKey] ?? $rawKey;
            $p75 = $rawData['percentiles']['p75'] ?? null;
            if ($p75 !== null) {
                $metrics[$key] = ['p75' => (float) $p75];
            }
        }

        return ['metrics' => $metrics, 'url' => $url];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Bundle sizes
    // ─────────────────────────────────────────────────────────────────────

    private function getBundleSizes(): array
    {
        $distDir = get_template_directory() . '/dist/';
        $files   = [
            'theme.js'      => $distDir . 'theme.js',
            'theme.css'     => $distDir . 'styles/theme.css',
            'vendors.js'    => $distDir . 'vendors.js',
            'blocks.css'    => $distDir . 'styles/blocks.css',
            'critical.js'   => $distDir . 'critical.js',
        ];

        $sizes = [];
        foreach ($files as $label => $path) {
            if (file_exists($path)) {
                $sizes[$label] = (int) filesize($path);
            }
        }

        return $sizes;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    private function getRating(string $metric, float $value): string
    {
        $t = self::THRESHOLDS[$metric] ?? ['good' => PHP_INT_MAX, 'poor' => PHP_INT_MAX];

        if ($value <= $t['good']) {
            return 'good';
        }
        if ($value <= $t['poor']) {
            return 'needs-improvement';
        }
        return 'poor';
    }

    private function ratingLabel(string $rating): string
    {
        return match ($rating) {
            'good'             => '✓ Tốt',
            'needs-improvement'=> '⚠ Cần cải thiện',
            'poor'             => '✗ Kém',
            default            => '— N/A',
        };
    }

    private function getBundleRating(string $label, int $bytes): string
    {
        $budgets = [
            'theme.js'   => [150_000, 300_000],
            'vendors.js' => [200_000, 400_000],
            'theme.css'  => [50_000,  100_000],
            'blocks.css' => [30_000,  60_000],
            'critical.js'=> [20_000,  40_000],
        ];

        [$good, $poor] = $budgets[$label] ?? [PHP_INT_MAX, PHP_INT_MAX];

        if ($bytes <= $good) return 'good';
        if ($bytes <= $poor) return 'warning';
        return 'over';
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1_000_000) {
            return number_format($bytes / 1_000_000, 1) . ' MB';
        }
        if ($bytes >= 1_000) {
            return number_format($bytes / 1_000, 1) . ' kB';
        }
        return $bytes . ' B';
    }

    // ─────────────────────────────────────────────────────────────────────
    // Assets
    // ─────────────────────────────────────────────────────────────────────

    public function enqueueAssets(string $hook): void
    {
        if ('index.php' !== $hook) {
            return;
        }

        $css = '
.laca-perf-widget { font-size: 13px; }
.laca-cwv-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin-bottom: 12px;
}
.laca-cwv-metric {
    border-radius: 6px;
    padding: 8px;
    text-align: center;
    border: 1px solid #e0e0e0;
}
.laca-cwv-metric--good              { background: #ecfdf5; border-color: #6ee7b7; }
.laca-cwv-metric--needs-improvement { background: #fffbeb; border-color: #fcd34d; }
.laca-cwv-metric--poor              { background: #fef2f2; border-color: #fca5a5; }
.laca-cwv-label { display: block; font-size: 10px; font-weight: 700; color: #555; letter-spacing:.5px; }
.laca-cwv-value { display: block; font-size: 18px; font-weight: 700; margin: 2px 0; }
.laca-cwv-badge { display: block; font-size: 10px; }
.laca-cwv-metric--good              .laca-cwv-value { color: #065f46; }
.laca-cwv-metric--needs-improvement .laca-cwv-value { color: #92400e; }
.laca-cwv-metric--poor              .laca-cwv-value { color: #991b1b; }

.laca-bundle-list { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
.laca-bundle-item { border-radius: 4px; padding: 4px 8px; font-size: 11px; display: flex; gap: 6px; align-items: center; border: 1px solid #e0e0e0; }
.laca-bundle-item-good    { background: #ecfdf5; border-color: #6ee7b7; }
.laca-bundle-item-warning { background: #fffbeb; border-color: #fcd34d; }
.laca-bundle-item-over    { background: #fef2f2; border-color: #fca5a5; color: #991b1b; }

.laca-perf-footer { display: flex; justify-content: space-between; align-items: center; padding-top: 8px; border-top: 1px solid #e0e0e0; }
.laca-perf-cache-time { font-size: 11px; color: #999; }
';
        wp_add_inline_style('wp-admin', $css);

        $js = '
(function($){
    $(document).on("click", ".laca-perf-refresh", function(){
        var btn   = $(this);
        var nonce = btn.data("nonce");
        btn.prop("disabled", true).text("⟳ Đang tải…");
        $.post(ajaxurl, {
            action: "laca_refresh_cwv",
            nonce:  nonce
        }, function(res){
            if (res.success && res.data && res.data.html) {
                var $widget = $("#laca-perf-widget");
                $widget.find(".laca-cwv-grid, .laca-bundle-list, h4").remove();
                $widget.prepend(res.data.html);
            }
            btn.prop("disabled", false).text("↻ Làm mới");
        });
    });
}(jQuery));
';
        wp_add_inline_script('jquery', $js);
    }
}
