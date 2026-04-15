<?php

namespace App\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * LacaDev Tracker Client
 *
 * Chạy ở web CLIENT để tự động gửi log & cảnh báo về hệ thống
 * quản lý dự án (lacadev.com). Gửi khi:
 *   - Plugin/Theme/Core được cập nhật hoặc cài mới
 *   - Plugin bị xóa hoặc kích hoạt
 *   - File lạ xuất hiện ở thư mục gốc, uploads, mu-plugins (cron hàng giờ)
 *   - Hàng ngày: digest danh sách plugin/theme đang chờ update
 *
 * Cấu hình qua Carbon Fields (Laca Admin → 📡 Tracker):
 *   laca_tracker_endpoint   — REST URL của lacadev CMS
 *   laca_tracker_secret_key — Secret key của project
 */
class LacaDevTrackerClient
{
    // Carbon Fields field names (dùng với carbon_get_theme_option)
    const CF_ENDPOINT = 'laca_tracker_endpoint';
    const CF_SECRET   = 'laca_tracker_secret_key';

    // WP Cron hook names
    const CRON_HOURLY  = 'laca_tracker_hourly_scan';
    const CRON_DAILY   = 'laca_tracker_daily_digest';

    /**
     * Thư mục cần quét file lạ (relative to ABSPATH)
     * Chỉ chứa các thư mục nhỏ/nguy hiểm cần scan liên tục.
     * Theme/plugin active được xử lý riêng với baseline filemtime.
     */
    const SUSPICIOUS_DIRS = [
        '',                          // Root (wp-config.php, .htaccess, index.php)
        'wp-content/uploads',        // Nơi hacker hay nhét shell
        'wp-content/mu-plugins',     // MU-plugin chạy tự động không cần kích hoạt
    ];

    /**
     * Extension file lạ trong uploads & root cần cảnh báo
     */
    const SUSPICIOUS_EXTS = ['php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar'];

    /**
     * Option key lưu baseline filemtime cho theme/plugin đang active
     */
    const OPT_BASELINE = '_laca_tracker_file_baseline';

    /**
     * Option key để track danh sách plugin update đã biết
     * → tránh gửi alert trùng lặp mỗi lần page load
     */
    const OPT_KNOWN_UPDATES = '_laca_tracker_known_plugin_updates';

    // =========================================================================
    // KHỞI TẠO
    // =========================================================================

    public function __construct()
    {
        // --- Event hooks ---
        add_action('upgrader_process_complete', [$this, 'onUpgraderComplete'], 20, 2);
        add_action('delete_plugin',             [$this, 'onDeletePlugin']);
        add_action('deleted_plugin',            [$this, 'afterDeletePlugin'], 10, 2);
        add_action('activated_plugin',          [$this, 'onActivatePlugin']);
        add_action('deactivated_plugin',        [$this, 'onDeactivatePlugin']);

        // --- Phát hiện plugin cần update NGAY KHI WP check (không đợi cron) ---
        // Filter set_site_transient_update_plugins chạy mỗi khi WP lưu kết quả
        // check update mới từ wordpress.org → so sánh với lần trước, gửi alert ngay.
        add_filter('set_site_transient_update_plugins', [$this, 'onUpdateTransientSet']);

        // --- REST endpoint: nhận lệnh cập nhật từ xa từ lacadev.com ---
        add_action('rest_api_init', [$this, 'registerRemoteUpdateEndpoint']);

        // --- Cron hàng giờ: quét file lạ ở thư mục nhạy cảm ---
        add_action(self::CRON_HOURLY, [$this, 'runHourlyScan']);
        if (!wp_next_scheduled(self::CRON_HOURLY)) {
            wp_schedule_event(time(), 'hourly', self::CRON_HOURLY);
        }

        // --- Cron hàng ngày: digest update pending + scan baseline theme/plugin ---
        add_action(self::CRON_DAILY, [$this, 'runDailyDigest']);
        if (!wp_next_scheduled(self::CRON_DAILY)) {
            // Chạy lúc 8:00 sáng (UTC+7 = 1:00 UTC)
            $nextRun = strtotime('tomorrow 01:00:00 UTC');
            wp_schedule_event($nextRun, 'daily', self::CRON_DAILY);
        }
    }


    // =========================================================================
    // EVENT HOOKS — gửi log tức thì
    // =========================================================================

    /**
     * Chạy ngay khi WP lưu kết quả check update plugin mới từ wordpress.org
     *
     * Hook: set_site_transient_update_plugins (filter, không phải action)
     * Phải return $value để không phá vỡ transient.
     *
     * Logic: so sánh tập hợp plugin-file trong response với lần lưu trước.
     * Nếu có plugin MỚI xuất hiện trong danh sách cần update (chưa có lần trước)
     * → gửi alert ngay lập tức, không đợi cron 8h sáng.
     */
    public function onUpdateTransientSet(mixed $value): mixed
    {
        // Không có response = không có plugin cần update
        if (empty($value->response) || !is_array($value->response)) {
            // KHÔNG delete known list — giữ để tránh re-alert khi WP check lại
            return $value;
        }

        $currentKeys = array_keys($value->response); // vd: ['litespeed-cache/litespeed-cache.php',...]
        sort($currentKeys);

        $knownKeys = (array) get_option(self::OPT_KNOWN_UPDATES, []);
        sort($knownKeys);

        // Tìm plugin MỚI (chưa có trong lần check trước)
        $newlyFound = array_diff($currentKeys, $knownKeys);

        if (!empty($newlyFound)) {
            $logs = [];
            foreach ($newlyFound as $pluginFile) {
                $data       = get_plugin_data(WP_PLUGIN_DIR . '/' . $pluginFile, false, false);
                $name       = $data['Name']    ?? $pluginFile;
                $current    = $data['Version'] ?? '?';
                $newVersion = $value->response[$pluginFile]->new_version ?? '?';

                $logs[] = [
                    'type'    => 'update_pending',
                    'content' => "⚠️ Plugin cần update: {$name}\n  Phiên bản hiện tại: {$current} → Bản mới: {$newVersion}",
                    'level'   => 'warning',
                ];
            }

            if (!empty($logs)) {
                $this->sendLogs($logs);
            }
        }

        // Cập nhật danh sách đã biết (dù có mới hay không) để tránh re-alert
        update_option(self::OPT_KNOWN_UPDATES, $currentKeys, false);

        return $value;
    }

    /**
     * Plugin/Theme/Core vừa được update hoặc cài mới
     */
    public function onUpgraderComplete(mixed $upgrader, array $options): void

    {
        $action = $options['action'] ?? '';
        $type   = $options['type']   ?? '';

        if ($action !== 'update' && $action !== 'install') {
            return;
        }

        $logs = [];

        if ($type === 'plugin') {
            $plugins = (array) ($options['plugins'] ?? []);
            if ($action === 'install' && !empty($upgrader->new_plugin_data)) {
                $name    = $upgrader->new_plugin_data['Name']    ?? 'Không rõ';
                $version = $upgrader->new_plugin_data['Version'] ?? '';
                $logs[]  = [
                    'type'    => 'plugin_install',
                    'content' => "Cài mới plugin: {$name}" . ($version ? " v{$version}" : ''),
                    'level'   => 'info',
                ];
            } else {
                foreach ($plugins as $plugin) {
                    $data    = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin, false, false);
                    $name    = $data['Name']    ?? $plugin;
                    $version = $data['Version'] ?? '';
                    $logs[]  = [
                        'type'    => 'plugin_update',
                        'content' => "Cập nhật plugin: {$name}" . ($version ? " → v{$version}" : ''),
                        'level'   => 'info',
                    ];
                }
            }
        } elseif ($type === 'theme') {
            $themes = (array) ($options['themes'] ?? []);
            foreach ($themes as $theme) {
                $data    = wp_get_theme($theme);
                $name    = $data->get('Name')    ?: $theme;
                $version = $data->get('Version') ?: '';
                $logs[]  = [
                    'type'    => 'theme_update',
                    'content' => "Cập nhật theme: {$name}" . ($version ? " → v{$version}" : ''),
                    'level'   => 'info',
                ];
            }
        } elseif ($type === 'core') {
            $wpVersion = get_bloginfo('version');
            $logs[]    = [
                'type'    => 'core_update',
                'content' => "Cập nhật WordPress Core → v{$wpVersion}",
                'level'   => 'info',
            ];
        }

        // Reset baseline sau khi update để tránh false positive
        if (!empty($logs)) {
            delete_option(self::OPT_BASELINE);
            $this->sendLogs($logs);
        }
    }

    /**
     * Plugin sắp bị xóa — lưu tên trước khi mất
     */
    public function onDeletePlugin(string $pluginFile): void
    {
        $data = get_plugin_data(WP_PLUGIN_DIR . '/' . $pluginFile, false, false);
        set_transient('_laca_deleting_plugin', $data['Name'] ?? $pluginFile, 60);
    }

    public function afterDeletePlugin(string $pluginFile, bool $deleted): void
    {
        if (!$deleted) {
            return;
        }
        $name = get_transient('_laca_deleting_plugin') ?: $pluginFile;
        delete_transient('_laca_deleting_plugin');

        $this->sendLogs([[
            'type'    => 'plugin_delete',
            'content' => "⚠️ Đã xóa plugin: {$name}",
            'level'   => 'warning',
        ]]);
    }

    /**
     * Plugin vừa được kích hoạt
     */
    public function onActivatePlugin(string $pluginFile): void
    {
        $data    = get_plugin_data(WP_PLUGIN_DIR . '/' . $pluginFile, false, false);
        $name    = $data['Name']    ?? $pluginFile;
        $version = $data['Version'] ?? '';

        $this->sendLogs([[
            'type'    => 'plugin_activate',
            'content' => "✅ Kích hoạt plugin: {$name}" . ($version ? " v{$version}" : ''),
            'level'   => 'info',
        ]]);
    }

    /**
     * Plugin bị tắt (deactivate)
     */
    public function onDeactivatePlugin(string $pluginFile): void
    {
        $data = get_plugin_data(WP_PLUGIN_DIR . '/' . $pluginFile, false, false);
        $name = $data['Name'] ?? $pluginFile;

        $this->sendLogs([[
            'type'    => 'plugin_deactivate',
            'content' => "🔴 Tắt plugin: {$name}",
            'level'   => 'warning',
        ]]);
    }

    // =========================================================================
    // CRON HOURLY — quét file lạ ở thư mục nhạy cảm
    // =========================================================================

    /**
     * Quét hàng giờ: tìm file PHP/shell trong thư mục root, uploads, mu-plugins
     */
    public function runHourlyScan(): void
    {
        $found = [];

        foreach (self::SUSPICIOUS_DIRS as $relDir) {
            $absDir = rtrim(ABSPATH, '/') . ($relDir ? '/' . ltrim($relDir, '/') : '');
            if (!is_dir($absDir)) {
                continue;
            }

            if ($relDir === '') {
                // Chỉ quét 1 cấp ở root (không đệ quy — tránh trùng với các thư mục khác)
                $this->scanRootLevel($absDir, $found);
            } else {
                // Đệ quy trong uploads và mu-plugins
                $this->scanSuspiciousRecursive($absDir, $relDir . '/', $found);
            }
        }

        if (!empty($found)) {
            $list = implode("\n", array_map(fn($f) => '  - ' . $f, $found));
            $this->sendLogs([[
                'type'    => 'file_suspicious',
                'content' => "⚠️ Phát hiện file đáng ngờ:\n{$list}",
                'level'   => 'critical',
            ]]);
        }
    }

    /**
     * Quét 1 cấp thư mục root — chỉ bắt file lạ, không đệ quy
     * (Tránh quét lại wp-content, wp-includes, v.v.)
     */
    private function scanRootLevel(string $absDir, array &$found): void
    {
        // File quan trọng cần giám sát thay đổi ở root
        $watchFiles = ['wp-config.php', '.htaccess', 'index.php', '.user.ini', 'php.ini'];

        foreach ($watchFiles as $file) {
            $full = $absDir . '/' . $file;
            if (!file_exists($full)) {
                continue;
            }

            // Phát hiện nội dung đáng ngờ trong wp-config.php / .htaccess
            if (in_array($file, ['wp-config.php', '.htaccess'], true)) {
                $this->checkFileForShellPatterns($full, $file, $found);
            }
        }

        // Quét file lạ (không phải file WordPress chuẩn) ở thư mục root
        $allowedRootFiles = [
            'wp-config.php', 'wp-config-sample.php', '.htaccess', 'index.php',
            'wp-activate.php', 'wp-blog-header.php', 'wp-comments-post.php',
            'wp-cron.php', 'wp-links-opml.php', 'wp-load.php', 'wp-login.php',
            'wp-mail.php', 'wp-settings.php', 'wp-signup.php', 'wp-trackback.php',
            'xmlrpc.php', 'readme.html', 'license.txt', '.user.ini', 'php.ini',
            'robots.txt', 'sitemap.xml', 'sitemap_index.xml',
        ];

        $files = glob($absDir . '/*.php') ?: [];
        foreach ($files as $filePath) {
            $filename = basename($filePath);
            if (!in_array($filename, $allowedRootFiles, true)) {
                $relPath  = str_replace(ABSPATH, '/', $filePath);
                $found[]  = $relPath . ' [PHP lạ ở root]';
            }
        }

        // File HTML/JS/tệp bất thường ở root
        $htmlFiles = array_merge(
            glob($absDir . '/*.html') ?: [],
            glob($absDir . '/*.htm') ?: [],
            glob($absDir . '/*.js') ?: []
        );
        foreach ($htmlFiles as $filePath) {
            $filename = basename($filePath);
            if (!in_array($filename, ['readme.html', 'license.txt'], true)) {
                $relPath = str_replace(ABSPATH, '/', $filePath);
                $found[] = $relPath . ' [file lạ ở root]';
            }
        }
    }

    /**
     * Quét đệ quy thư mục tìm file có extension đáng ngờ
     */
    private function scanSuspiciousRecursive(string $absDir, string $relPrefix, array &$found): void
    {
        try {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($absDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($it as $file) {
                /** @var \SplFileInfo $file */
                if (!$file->isFile()) {
                    continue;
                }

                $ext = strtolower($file->getExtension());
                if (in_array($ext, self::SUSPICIOUS_EXTS, true)) {
                    $relPath = $relPrefix . $it->getSubPathname();
                    $found[] = $relPath;
                }
            }
        } catch (\UnexpectedValueException) {
            // Permission denied — bỏ qua
        }
    }

    /**
     * Kiểm tra nội dung file có chứa pattern shell/webshell không
     */
    private function checkFileForShellPatterns(string $filePath, string $displayName, array &$found): void
    {
        // Giới hạn đọc 50KB để tránh tốn bộ nhớ
        $content = @file_get_contents($filePath, false, null, 0, 51200);
        if ($content === false) {
            return;
        }

        $shellPatterns = [
            'eval(base64_decode',
            'eval(gzinflate',
            'eval(str_rot13',
            'eval($_POST',
            'eval($_GET',
            'assert($_',
            'system($_',
            'passthru($_',
            'exec($_',
            'shell_exec($_',
            'base64_decode(str_rot13',
            'preg_replace(\'/.*/e\'',
            'FilesMan',
            'c99shell',
            'r57shell',
        ];

        foreach ($shellPatterns as $pattern) {
            if (stripos($content, $pattern) !== false) {
                $found[] = $displayName . " [⚠️ pattern đáng ngờ: '{$pattern}']";
                break;
            }
        }
    }

    // =========================================================================
    // CRON DAILY — digest update pending + baseline check
    // =========================================================================

    /**
     * Chạy hàng ngày: gửi danh sách plugin/theme chờ update + kiểm tra file integrity
     */
    public function runDailyDigest(): void
    {
        $logs = [];

        // 1. Kiểm tra plugin chờ update — chỉ gửi plugin MỚI so với known list
        $pluginUpdates = $this->getPendingPluginUpdates();
        $knownKeys = (array) get_option(self::OPT_KNOWN_UPDATES, []);
        $newPlugins = array_filter($pluginUpdates, function ($p) use ($knownKeys) {
            return !in_array($p['slug'] ?? '', $knownKeys, true);
        });
        if (!empty($newPlugins)) {
            $list   = implode("\n", array_map(fn($p) => "  - {$p['name']}: {$p['current']} → {$p['new']}", $newPlugins));
            $logs[] = [
                'type'    => 'update_pending',
                'content' => "Plugin mới chờ update (" . count($newPlugins) . "):\n{$list}",
                'level'   => 'warning',
            ];
        }

        // 2. Kiểm tra theme chờ update
        $themeUpdates = $this->getPendingThemeUpdates();
        if (!empty($themeUpdates)) {
            $list   = implode("\n", array_map(fn($t) => "  - {$t['name']}: {$t['current']} → {$t['new']}", $themeUpdates));
            $logs[] = [
                'type'    => 'update_pending',
                'content' => "🎨 Có " . count($themeUpdates) . " theme chờ update:\n{$list}",
                'level'   => 'warning',
            ];
        }

        // 3. Kiểm tra WordPress Core có update không
        $coreUpdate = $this->getPendingCoreUpdate();
        if ($coreUpdate) {
            $logs[] = [
                'type'    => 'update_pending',
                'content' => "🔄 WordPress Core: {$coreUpdate['current']} → {$coreUpdate['new']} (có bản mới)",
                'level'   => 'warning',
            ];
        }

        // 4. File integrity: check theme đang active và plugins đang bật
        $modifiedFiles = $this->checkFileIntegrity();
        if (!empty($modifiedFiles)) {
            $list   = implode("\n", array_map(fn($f) => "  - {$f}", $modifiedFiles));
            $logs[] = [
                'type'    => 'file_changed',
                'content' => "📝 Phát hiện file theme/plugin bị thay đổi:\n{$list}",
                'level'   => 'critical',
            ];
        }

        if (!empty($logs)) {
            $this->sendLogs($logs);
        }
    }

    /**
     * Danh sách plugin có bản update mới (chưa update)
     */
    private function getPendingPluginUpdates(): array
    {
        // Buộc WordPress fetch thông tin update mới nhất
        wp_update_plugins();

        $updates = get_site_transient('update_plugins');
        if (empty($updates->response)) {
            return [];
        }

        $result = [];
        foreach ($updates->response as $pluginFile => $data) {
            $installed = get_plugin_data(WP_PLUGIN_DIR . '/' . $pluginFile, false, false);
            $result[]  = [
                'slug'    => $pluginFile,
                'name'    => $installed['Name'] ?? $pluginFile,
                'current' => $installed['Version'] ?? '?',
                'new'     => $data->new_version ?? '?',
            ];
        }
        return $result;
    }

    /**
     * Danh sách theme có bản update mới
     */
    private function getPendingThemeUpdates(): array
    {
        wp_update_themes();

        $updates = get_site_transient('update_themes');
        if (empty($updates->response)) {
            return [];
        }

        $result = [];
        foreach ($updates->response as $themeSlug => $data) {
            $theme    = wp_get_theme($themeSlug);
            $result[] = [
                'name'    => $theme->get('Name') ?: $themeSlug,
                'current' => $theme->get('Version') ?: '?',
                'new'     => $data['new_version'] ?? '?',
            ];
        }
        return $result;
    }

    /**
     * Kiểm tra WordPress Core có update không
     */
    private function getPendingCoreUpdate(): ?array
    {
        wp_version_check();

        $updates = get_site_transient('update_core');
        if (empty($updates->updates)) {
            return null;
        }

        foreach ($updates->updates as $update) {
            if (($update->response ?? '') === 'upgrade') {
                return [
                    'current' => get_bloginfo('version'),
                    'new'     => $update->version ?? '?',
                ];
            }
        }
        return null;
    }

    /**
     * Kiểm tra file integrity của theme đang active + plugins đang bật
     * Dùng baseline filemtime: lần đầu lưu baseline, lần sau so sánh.
     */
    private function checkFileIntegrity(): array
    {
        $baseline = get_option(self::OPT_BASELINE, []);
        $current  = [];
        $changed  = [];

        // Thu thập file cần theo dõi
        $watchPaths = $this->getIntegrityWatchPaths();

        foreach ($watchPaths as $absPath => $relLabel) {
            if (!file_exists($absPath)) {
                continue;
            }
            $mtime           = filemtime($absPath);
            $current[$relLabel] = $mtime;

            if (!empty($baseline[$relLabel]) && $baseline[$relLabel] !== $mtime) {
                $changed[] = $relLabel . ' (sửa lúc ' . date('d/m/Y H:i', $mtime) . ')';
            }
        }

        // Tìm file mới xuất hiện (chưa có trong baseline)
        foreach ($current as $label => $mtime) {
            if (!isset($baseline[$label])) {
                $changed[] = $label . ' [mới] (tạo lúc ' . date('d/m/Y H:i', $mtime) . ')';
            }
        }

        // Lưu baseline mới
        update_option(self::OPT_BASELINE, $current, false);

        // Bỏ qua lần đầu (baseline chưa có = không có gì để so)
        if (empty($baseline)) {
            return [];
        }

        return $changed;
    }

    /**
     * Danh sách file cần theo dõi integrity (key=abs path, value=relative label)
     */
    private function getIntegrityWatchPaths(): array
    {
        $paths = [];

        // Theme đang active — theo dõi file PHP + JS + CSS cấp 1
        $activeTheme    = get_stylesheet_directory();
        $themeSlug      = get_stylesheet();
        $themeFiles     = array_merge(
            glob($activeTheme . '/*.php')  ?: [],
            glob($activeTheme . '/*.js')   ?: [],
            glob($activeTheme . '/*.css')  ?: [],
            glob($activeTheme . '/functions.php') ?: []
        );
        foreach (array_unique($themeFiles) as $f) {
            $label = "themes/{$themeSlug}/" . basename($f);
            $paths[$f] = $label;
        }

        // functions.php trong thư mục con (child theme nếu có)
        $parentTheme = get_template_directory();
        if ($parentTheme !== $activeTheme) {
            $parentFunctions = $parentTheme . '/functions.php';
            if (file_exists($parentFunctions)) {
                $parentSlug          = get_template();
                $paths[$parentFunctions] = "themes/{$parentSlug}/functions.php";
            }
        }

        // Plugins đang kích hoạt — chỉ file chính (.php cùng tên thư mục)
        $activePlugins = (array) get_option('active_plugins', []);
        foreach ($activePlugins as $pluginRel) {
            $absPlugin = WP_PLUGIN_DIR . '/' . $pluginRel;
            if (file_exists($absPlugin)) {
                $paths[$absPlugin] = 'plugins/' . $pluginRel;
            }
        }

        return $paths;
    }

    // =========================================================================
    // INTERNAL — HTTP sender
    // =========================================================================

    /**
     * Gửi mảng logs về REST API của lacadev CMS
     *
     * @param array<array{type: string, content: string, level?: string}> $logs
     */
    private function sendLogs(array $logs): void
    {
        $endpoint  = self::getEndpoint();
        $secretKey = self::getSecretKey();

        if (empty($endpoint) || empty($secretKey) || empty($logs)) {
            return;
        }

        $siteUrl = get_bloginfo('url');

        wp_remote_post($endpoint, [
            'body'    => wp_json_encode([
                'secret_key' => $secretKey,
                'site_url'   => $siteUrl,
                'logs'       => $logs,
            ], JSON_UNESCAPED_UNICODE),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 8,
            'blocking' => false, // Fire-and-forget — không làm chậm admin
        ]);
    }

    // =========================================================================
    // STATIC CONFIG HELPERS
    // =========================================================================

    public static function getEndpoint(): string
    {
        if (function_exists('carbon_get_theme_option')) {
            return (string) (carbon_get_theme_option(self::CF_ENDPOINT) ?: '');
        }
        return (string) get_option('_' . self::CF_ENDPOINT, '');
    }

    public static function getSecretKey(): string
    {
        if (function_exists('carbon_get_theme_option')) {
            return (string) (carbon_get_theme_option(self::CF_SECRET) ?: '');
        }
        return (string) get_option('_' . self::CF_SECRET, '');
    }

    public static function isConfigured(): bool
    {
        return !empty(self::getEndpoint()) && !empty(self::getSecretKey());
    }

    /**
     * Đăng ký hooks — gọi từ hooks.php
     */
    public static function register(): void
    {
        if (!self::isConfigured()) {
            return;
        }
        new self();
    }

    // =========================================================================
    // REMOTE UPDATE — Nhận lệnh cập nhật từ xa từ lacadev.com
    // =========================================================================

    /**
     * Đăng ký REST endpoint /wp-json/laca/v1/remote-update
     * Nhận lệnh update plugin / theme / core từ lacadev.com
     */
    public function registerRemoteUpdateEndpoint(): void
    {
        register_rest_route('laca/v1', '/remote-update', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handleRemoteUpdate'],
            'permission_callback' => '__return_true', // Auth qua secret key bên trong
        ]);
    }

    /**
     * Xử lý lệnh update đến từ lacadev.com
     *
     * Body JSON: { secret_key, action, slug? }
     *   action: update_plugin | update_theme | update_core
     *   slug:   file/folder của plugin hoặc theme (bỏ qua khi update_core)
     */
    public function handleRemoteUpdate(\WP_REST_Request $request): \WP_REST_Response
    {
        $params    = $request->get_json_params() ?: [];
        $secretKey = sanitize_text_field($params['secret_key'] ?? '');
        $action    = sanitize_key($params['action'] ?? '');
        $slug      = sanitize_text_field($params['slug'] ?? '');

        // 1) Xác thực secret key
        if (empty($secretKey) || $secretKey !== self::getSecretKey()) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        // 2) Validate action
        $allowed = ['update_plugin', 'update_theme', 'update_core'];
        if (!in_array($action, $allowed, true)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Action không hợp lệ.'], 400);
        }

        // 3) Load các class WordPress cần thiết
        if (!function_exists('request_filesystem_credentials')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';

        // Dùng Automatic_Upgrader_Skin để không output HTML
        $skin = new \Automatic_Upgrader_Skin();

        // 4) Thực thi theo action
        switch ($action) {
            case 'update_plugin':
                if (empty($slug)) {
                    return new \WP_REST_Response(['success' => false, 'message' => 'Thiếu slug plugin.'], 400);
                }
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
                wp_update_plugins(); // Refresh transient từ API
                $upgrader = new \Plugin_Upgrader($skin);
                $result   = $upgrader->upgrade($slug);
                $label    = "plugin '{$slug}'";
                break;

            case 'update_theme':
                if (empty($slug)) {
                    return new \WP_REST_Response(['success' => false, 'message' => 'Thiếu slug theme.'], 400);
                }
                wp_update_themes();
                $upgrader = new \Theme_Upgrader($skin);
                $result   = $upgrader->upgrade($slug);
                $label    = "theme '{$slug}'";
                break;

            case 'update_core':
                require_once ABSPATH . 'wp-admin/includes/update.php';
                $updates = get_core_updates();
                if (empty($updates) || !isset($updates[0]->response) || $updates[0]->response === 'latest') {
                    return new \WP_REST_Response([
                        'success' => true,
                        'message' => 'WordPress đã ở phiên bản mới nhất, không cần cập nhật.',
                    ]);
                }
                $upgrader = new \Core_Upgrader($skin);
                $result   = $upgrader->upgrade($updates[0]);
                $label    = 'WordPress core';
                break;

            default:
                return new \WP_REST_Response(['success' => false, 'message' => 'Action không hợp lệ.'], 400);
        }

        // 5) Xử lý kết quả
        if (is_wp_error($result)) {
            $msg = "Cập nhật {$label} thất bại: " . $result->get_error_message();
            $this->sendLogs([['type' => 'other', 'content' => $msg, 'level' => 'critical']]);
            return new \WP_REST_Response(['success' => false, 'message' => $msg], 500);
        }

        if ($result === false || $result === null) {
            $msg = "Cập nhật {$label} không thành công (có thể đã ở phiên bản mới nhất).";
            return new \WP_REST_Response(['success' => false, 'message' => $msg]);
        }

        // Thành công — ghi log về lacadev
        $successMsg = "✅ Đã cập nhật {$label} thành công từ lệnh remote.";
        $this->sendLogs([['type' => 'deployment', 'content' => $successMsg, 'level' => 'info']]);

        return new \WP_REST_Response([
            'success' => true,
            'message' => $successMsg,
        ]);
    }
}
