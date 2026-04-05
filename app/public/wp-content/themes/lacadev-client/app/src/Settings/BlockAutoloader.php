<?php

namespace App\Settings;

/**
 * BlockAutoloader
 *
 * Tự động scan thư mục block-gutenberg/ trong child theme và register tất cả các blocks.
 * Chỉ register blocks có block.json hợp lệ.
 */
class BlockAutoloader
{
    public function __construct()
    {
        add_action('init', [$this, 'registerBlocks'], 20);
    }

    public function registerBlocks(): void
    {
        $blockDir = get_stylesheet_directory() . '/block-gutenberg';

        if (!is_dir($blockDir)) {
            return;
        }

        foreach (glob($blockDir . '/*/block.json') as $blockJsonPath) {
            $blockFolder = dirname($blockJsonPath);

            // Kiểm tra block.json hợp lệ trước khi register
            $blockData = json_decode(file_get_contents($blockJsonPath), true);
            if (!$blockData || empty($blockData['name'])) {
                continue;
            }

            register_block_type($blockFolder);
        }
    }
}
