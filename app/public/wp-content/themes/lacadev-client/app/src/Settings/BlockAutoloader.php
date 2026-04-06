<?php

namespace App\Settings;

/**
 * BlockAutoloader
 *
 * Tự động scan thư mục block-gutenberg/ trong child theme và register tất cả các blocks.
 * Handle names dùng quy ước giống parent theme: lacadev-block-{name}-editor, lacadev-block-{name}
 */
class BlockAutoloader
{
    public function __construct()
    {
        add_action('init', [$this, 'registerBlocks'], 20);
    }

    public function registerBlocks(): void
    {
        $childRoot = dirname(get_stylesheet_directory());
        $blockDir  = $childRoot . '/block-gutenberg';
        $childUri  = dirname(get_stylesheet_directory_uri());

        if (!is_dir($blockDir)) {
            return;
        }

        foreach (glob($blockDir . '/*/block.json') as $blockJsonPath) {
            $blockFolder = dirname($blockJsonPath);
            $blockName   = basename($blockFolder);

            $blockData = json_decode(file_get_contents($blockJsonPath), true);
            if (!$blockData || empty($blockData['name'])) {
                continue;
            }

            $blockArgs = [];
            $hasBuild  = is_dir($blockFolder . '/build');

            if ($hasBuild) {
                $assetFile = $blockFolder . '/build/index.asset.php';
                $asset     = file_exists($assetFile)
                    ? require $assetFile
                    : ['dependencies' => [], 'version' => null];

                // Dùng cùng convention với parent: lacadev-block-{name}-editor / lacadev-block-{name}
                $editorHandle = 'lacadev-block-' . $blockName . '-editor';
                $styleHandle  = 'lacadev-block-' . $blockName;

                wp_register_script(
                    $editorHandle,
                    $childUri . '/block-gutenberg/' . $blockName . '/build/index.js',
                    $asset['dependencies'] ?? [],
                    $asset['version'] ?? null,
                    true
                );
                $blockArgs['editor_script'] = $editorHandle;

                if (file_exists($blockFolder . '/build/index.css')) {
                    wp_register_style(
                        $editorHandle,
                        $childUri . '/block-gutenberg/' . $blockName . '/build/index.css',
                        [],
                        $asset['version'] ?? null
                    );
                    $blockArgs['editor_style'] = $editorHandle;
                }

                if (file_exists($blockFolder . '/build/style-index.css')) {
                    wp_register_style(
                        $styleHandle,
                        $childUri . '/block-gutenberg/' . $blockName . '/build/style-index.css',
                        [],
                        $asset['version'] ?? null
                    );
                    $blockArgs['style'] = $styleHandle;
                }
            }

            $renderPhp = $blockFolder . '/render.php';
            if (file_exists($renderPhp)) {
                $blockArgs['render_callback'] = static function ($attributes, $content) use ($renderPhp) {
                    ob_start();
                    require $renderPhp;
                    return ob_get_clean();
                };
            }

            register_block_type($blockFolder, $blockArgs);
        }
    }
}
