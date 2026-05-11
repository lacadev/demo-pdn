<?php

namespace App\Settings;

/**
 * BlockSyncReceiver
 *
 * Nhận block files từ lacadev.com qua REST API, ghi vào block-gutenberg/ của child theme.
 * Blocks được ghi vào: lacadev-client-child/block-gutenberg/{block_name}/
 * Nhờ vậy khi update lacadev-client (parent theme) sẽ không ảnh hưởng đến blocks đã sync.
 *
 * Endpoint: POST /wp-json/lacadev/v1/sync-block
 * Status:   GET  /wp-json/lacadev/v1/sync-block/status
 */
class BlockSyncReceiver
{
    private const NAMESPACE   = 'lacadev/v1';
    private const ROUTE       = 'sync-block';
    private const KEY_OPTION  = 'laca_sync_key';
    private const LOG_OPTION  = 'laca_block_activity_log';
    private const INST_OPTION = 'laca_blocks_installed';
    private const DIAG_OPTION = 'laca_block_sync_diagnostics';

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::ROUTE, [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'receiveBlock'],
            'permission_callback' => '__return_true', // Auth được xử lý trong callback
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::ROUTE . '/status', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getStatus'],
            'permission_callback' => '__return_true',
        ]);
    }

    // =========================================================================
    // REST CALLBACKS
    // =========================================================================

    public function receiveBlock(\WP_REST_Request $request): \WP_REST_Response
    {
        // --- Authenticate ---
        if (!$this->authenticate($request)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'API Key không hợp lệ.',
            ], 401);
        }

        // --- Validate payload ---
        $blockName = sanitize_key($request->get_param('block_name') ?? '');
        $version   = sanitize_text_field($request->get_param('version') ?? '1.0.0');
        $files     = $request->get_param('files') ?? [];

        if (empty($blockName)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Thiếu block_name'], 400);
        }

        if (empty($files) || !is_array($files)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Không có files'], 400);
        }

        // --- Ghi files ---
        // Ghi vào child theme để tách biệt với parent theme (lacadev-client).
        // Khi update lacadev-client sẽ không xoá blocks đã sync.
        // get_stylesheet_directory() trả về .../lacadev-client-child/theme khi child theme active,
        // nên dirname() lên 1 cấp → .../lacadev-client-child/
        $blockDir = dirname(get_stylesheet_directory()) . '/block-gutenberg/' . $blockName;

        // Xác định đây là install mới hay update
        $installed = get_option(self::INST_OPTION, []);
        if (!is_array($installed)) {
            $installed = [];
        }
        $oldVersion = isset($installed[$blockName]) && !is_array($installed[$blockName]) ? (string) $installed[$blockName] : null;
        $isUpdate   = $oldVersion !== null;
        $syncedBy = sanitize_text_field((string) ($request->get_param('synced_by') ?: $request->get_header('X-Laca-User') ?: 'lacadev'));
        $diagnostics = $this->diagnoseIncomingBlock($blockName, $version, $files, $oldVersion, $syncedBy);

        if (!empty($diagnostics['errors'])) {
            $this->appendLog(
                'Không nhận <strong>' . esc_html($blockName) . '</strong>: ' . esc_html(implode(' ', $diagnostics['errors'])),
                $diagnostics
            );
            $this->saveDiagnostics($blockName, $diagnostics);

            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Block payload không đạt preflight.',
                'diagnostics' => $diagnostics,
            ], 400);
        }

        try {
            $this->writeBlockFiles($blockDir, $files);
        } catch (\Exception $e) {
            $diagnostics['errors'][] = $e->getMessage();
            $this->saveDiagnostics($blockName, $diagnostics);
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Lỗi ghi file: ' . $e->getMessage(),
            ], 500);
        }

        // --- Cập nhật option installed ---
        $installed[$blockName] = $version;
        update_option(self::INST_OPTION, $installed);

        // --- Ghi Activity Log ---
        if ($isUpdate) {
            $logMsg = "🔄 Cập nhật <strong>{$blockName}</strong> {$oldVersion} → {$version}";
        } else {
            $logMsg = "✅ Nhận <strong>{$blockName}</strong> ({$version})";
        }
        $postWriteDiagnostics = $this->diagnoseInstalledBlock($blockDir, $diagnostics);
        $this->saveDiagnostics($blockName, $postWriteDiagnostics);
        $this->appendLog($logMsg, $postWriteDiagnostics);

        // --- Fire webhook action so child themes / plugins can react ---
        do_action('lacadev/block-sync/received', $blockName, $version, $isUpdate);

        return new \WP_REST_Response([
            'success' => true,
            'message' => $isUpdate
                ? "Đã cập nhật {$blockName} từ {$oldVersion} lên {$version}"
                : "Đã nhận {$blockName} v{$version} thành công",
            'diagnostics' => $postWriteDiagnostics,
        ], 200);
    }

    public function getStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!$this->authenticate($request)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'API Key không hợp lệ.'], 401);
        }

        return new \WP_REST_Response([
            'success'   => true,
            'installed' => get_option(self::INST_OPTION, []),
            'diagnostics' => get_option(self::DIAG_OPTION, []),
        ], 200);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function authenticate(\WP_REST_Request $request): bool
    {
        $storedKey  = get_option(self::KEY_OPTION, '');
        $requestKey = $request->get_header('X-Laca-Key') ?? '';

        if (empty($storedKey) || empty($requestKey)) {
            return false;
        }

        return hash_equals($storedKey, $requestKey);
    }

    /**
     * Giải mã base64 và ghi files vào block directory.
     * $files = ['relative/path' => 'base64_encoded_content']
     *
     * @throws \RuntimeException nếu không thể tạo thư mục hoặc ghi file
     */
    private function writeBlockFiles(string $blockDir, array $files): void
    {
        // Validate block name để tránh path traversal
        // Validate trong phạm vi block-gutenberg/ của child theme
        $childBlockGutenberg = dirname(get_stylesheet_directory()) . '/block-gutenberg';
        $realStyleDir = realpath($childBlockGutenberg);
        if ($realStyleDir === false) {
            // Thư mục chưa tồn tại - sẽ được tạo khi ghi file đầu tiên
            $realStyleDir = $childBlockGutenberg;
        }

        foreach ($files as $relativePath => $base64Content) {
            // Sanitize path: loại bỏ ký tự nguy hiểm
            $cleanPath = preg_replace('/[^a-zA-Z0-9\/_\-.]/u', '', $relativePath);
            if (str_contains($cleanPath, '..')) {
                continue; // Skip path traversal attempts
            }

            $targetPath = $blockDir . '/' . $cleanPath;

            // Tạo thư mục nếu chưa có
            $targetDir = dirname($targetPath);
            if (!is_dir($targetDir)) {
                if (!wp_mkdir_p($targetDir)) {
                    throw new \RuntimeException("Không thể tạo thư mục: {$targetDir}");
                }
            }

            // Xác minh path nằm trong child theme directory
            $realTargetDir = realpath($targetDir);
            if ($realTargetDir === false || !str_starts_with($realTargetDir, (string) $realStyleDir)) {
                continue; // Skip nếu path ngoài child theme
            }

            // Decode và ghi file
            $content = base64_decode($base64Content, strict: true);
            if ($content === false) {
                continue; // Skip file bị hỏng
            }

            file_put_contents($targetPath, $content);
        }
    }

    /**
     * Ghi entry vào activity log (giữ tối đa 50 entries gần nhất).
     */
    private function appendLog(string $message, array $context = []): void
    {
        $log = get_option(self::LOG_OPTION, []);

        array_unshift($log, [
            'time'    => current_time('mysql'),
            'message' => $message,
            'context' => $context,
        ]);

        // Giữ tối đa 200 entries
        $log = array_slice($log, 0, 200);

        update_option(self::LOG_OPTION, $log, false);
    }

    private function diagnoseIncomingBlock(string $blockName, string $version, array $files, ?string $oldVersion, string $syncedBy): array
    {
        $fileNames = array_map('strval', array_keys($files));
        $warnings = [];
        $errors = [];

        if (!in_array('block.json', $fileNames, true)) {
            $errors[] = 'Thiếu block.json nên block sẽ không thể đăng ký.';
        } else {
            $rawBlockJson = base64_decode((string) $files['block.json'], true);
            $metadata = $rawBlockJson ? json_decode($rawBlockJson, true) : null;
            if (!is_array($metadata)) {
                $errors[] = 'block.json không phải JSON hợp lệ.';
            } elseif (empty($metadata['name'])) {
                $warnings[] = 'block.json thiếu trường name.';
            }
        }

        if (!in_array('index.js', $fileNames, true) && !in_array('build/index.js', $fileNames, true)) {
            $warnings[] = 'Không thấy index.js hoặc build/index.js.';
        }

        if (!in_array('render.php', $fileNames, true) && !in_array('save.js', $fileNames, true)) {
            $warnings[] = 'Không thấy render.php hoặc save.js.';
        }

        foreach ($fileNames as $fileName) {
            if (str_contains($fileName, '..')) {
                $errors[] = 'Payload có path traversal: ' . $fileName;
            }
        }

        return [
            'block_name' => $blockName,
            'old_version' => $oldVersion,
            'new_version' => $version,
            'version_changed' => $oldVersion === null || version_compare($version, $oldVersion, '!='),
            'file_count' => count($files),
            'files' => $fileNames,
            'warnings' => array_values(array_unique($warnings)),
            'errors' => array_values(array_unique($errors)),
            'synced_by' => $syncedBy,
            'synced_at' => current_time('mysql'),
            'target_dir' => dirname(get_stylesheet_directory()) . '/block-gutenberg/' . $blockName,
            'compatibility' => $this->getCompatibilitySnapshot(),
        ];
    }

    private function diagnoseInstalledBlock(string $blockDir, array $diagnostics): array
    {
        $diagnostics['installed_files'] = [];
        $diagnostics['missing_after_write'] = [];

        foreach ((array) ($diagnostics['files'] ?? []) as $relativePath) {
            $fullPath = $blockDir . '/' . ltrim((string) $relativePath, '/');
            if (file_exists($fullPath)) {
                $diagnostics['installed_files'][] = (string) $relativePath;
            } else {
                $diagnostics['missing_after_write'][] = (string) $relativePath;
            }
        }

        if (!empty($diagnostics['missing_after_write'])) {
            $diagnostics['warnings'][] = 'Một số file không được ghi sau sync.';
            $diagnostics['warnings'] = array_values(array_unique((array) $diagnostics['warnings']));
        }

        return $diagnostics;
    }

    private function saveDiagnostics(string $blockName, array $diagnostics): void
    {
        $all = get_option(self::DIAG_OPTION, []);
        if (!is_array($all)) {
            $all = [];
        }

        $all[$blockName] = $diagnostics;
        update_option(self::DIAG_OPTION, $all, false);
    }

    private function getCompatibilitySnapshot(): array
    {
        return [
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'parent_theme' => get_template(),
            'child_theme' => get_stylesheet(),
            'uses_child_block_dir' => get_stylesheet_directory() !== get_template_directory(),
        ];
    }

    // =========================================================================
    // STATIC: AUTO-GENERATE API KEY
    // =========================================================================

    public static function ensureApiKey(): string
    {
        $key = get_option(self::KEY_OPTION, '');
        if (empty($key)) {
            $key = wp_generate_uuid4();
            update_option(self::KEY_OPTION, $key);
        }
        return $key;
    }

    public static function getDiagnostics(): array
    {
        $diagnostics = get_option(self::DIAG_OPTION, []);

        return is_array($diagnostics) ? $diagnostics : [];
    }
}
