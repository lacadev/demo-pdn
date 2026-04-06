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
        $oldVersion = $installed[$blockName] ?? null;
        $isUpdate   = $oldVersion !== null;

        try {
            $this->writeBlockFiles($blockDir, $files);
        } catch (\Exception $e) {
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
        $this->appendLog($logMsg);

        return new \WP_REST_Response([
            'success' => true,
            'message' => $isUpdate
                ? "Đã cập nhật {$blockName} từ {$oldVersion} lên {$version}"
                : "Đã nhận {$blockName} v{$version} thành công",
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
    private function appendLog(string $message): void
    {
        $log = get_option(self::LOG_OPTION, []);

        array_unshift($log, [
            'time'    => current_time('mysql'),
            'message' => $message,
        ]);

        // Giữ tối đa 50 entries
        $log = array_slice($log, 0, 50);

        update_option(self::LOG_OPTION, $log, false);
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
}
