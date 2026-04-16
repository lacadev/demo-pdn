const fs = require('fs');
const path = require('path');

const baseDir = '/Users/anhd/Documents/wordpress/demo-pdn/app/public/wp-content/themes/lacadev-client-child/block-gutenberg';

const blocks = fs.readdirSync(baseDir).filter(f => fs.statSync(path.join(baseDir, f)).isDirectory());

const panelBodyJSX = `
                { /* Panel 3: Giao diện */ }
                <PanelBody title={ __( 'Giao diện', 'laca' ) } initialOpen={ false }>
                    <p style={ { fontSize: '0.8rem', fontWeight: 600, marginBottom: '0.5rem' } }>
                        { __( 'Màu nền section', 'laca' ) }
                    </p>
                    <ColorPicker
                        color={ bgColor }
                        onChange={ ( v ) => setAttributes( { bgColor: v } ) }
                        enableAlpha={ false }
                        defaultValue="#0f0f0f"
                    />
                    <RangeControl
                        label={ __( 'Độ mờ nền (%) — 0 = trong suốt', 'laca' ) }
                        value={ bgOpacity }
                        min={ 0 }
                        max={ 100 }
                        step={ 5 }
                        onChange={ ( v ) => setAttributes( { bgOpacity: v } ) }
                    />
                </PanelBody>
`;

blocks.forEach(block => {
    if (block === 'projects-slider-block') return;

    const blockJsonPath = path.join(baseDir, block, 'block.json');
    const editJsPath = path.join(baseDir, block, 'edit.js');
    const renderPhpPath = path.join(baseDir, block, 'render.php');

    if (!fs.existsSync(blockJsonPath) || !fs.existsSync(editJsPath) || !fs.existsSync(renderPhpPath)) return;

    // 1. Update block.json
    let blockJson = JSON.parse(fs.readFileSync(blockJsonPath, 'utf8'));
    let modifiedJson = false;
    
    // Remove old background color attributes if any
    if (blockJson.attributes.backgroundColor) {
        delete blockJson.attributes.backgroundColor;
        modifiedJson = true;
    }
    
    if (!blockJson.attributes.bgColor) {
        blockJson.attributes.bgColor = { type: 'string', default: '#0f0f0f' };
        modifiedJson = true;
    }
    if (!blockJson.attributes.bgOpacity) {
        blockJson.attributes.bgOpacity = { type: 'number', default: 100 };
        modifiedJson = true;
    }
    
    if (modifiedJson) {
        fs.writeFileSync(blockJsonPath, JSON.stringify(blockJson, null, 4));
        console.log(`Updated block.json for ${block}`);
    }

    // 2. Update edit.js
    let editJs = fs.readFileSync(editJsPath, 'utf8');
    
    // Import ColorPicker and RangeControl
    if (!editJs.includes('ColorPicker')) {
        editJs = editJs.replace(/import \{([^}]+)\} from '@wordpress\/components';/s, (match, imports) => {
            return `import {${imports}, ColorPicker, RangeControl } from '@wordpress/components';`;
        });
    }

    // Destructure attributes
    if (!editJs.includes('bgOpacity')) {
        editJs = editJs.replace(/const \{\s*([^}]+)\s*\} = attributes;/s, (match, attrs) => {
            // Remove old backgroundColor if present
            let newAttrs = attrs.replace(/backgroundColor\s*,?/, '');
            return `const { ${newAttrs}, bgColor, bgOpacity } = attributes;`;
        });
    }

    // Add PanelBody
    if (!editJs.includes('Panel 3: Giao diện') && editJs.includes('</InspectorControls>')) {
        // inject right before </InspectorControls>
        editJs = editJs.replace('</InspectorControls>', `${panelBodyJSX}\n            </InspectorControls>`);
        fs.writeFileSync(editJsPath, editJs);
        console.log(`Updated edit.js for ${block}`);
    }

    // 3. Update render.php
    let renderPhp = fs.readFileSync(renderPhpPath, 'utf8');
    if (!renderPhp.includes('$bg_opacity')) {
        // Replace old $bg_color logic
        renderPhp = renderPhp.replace(/\$bg_color\s*=\s*(?:esc_attr\()?\$attributes\['(?:backgroundColor|bgColor)'\][^;]+;/g, '');
        
        const phpVars = `
// ── Appearance attributes ──────────────────────────────────────────────────
$bg_color     = preg_match( '/^#[0-9a-fA-F]{6}$/', $attributes['bgColor'] ?? '' ) ? $attributes['bgColor'] : '#0f0f0f';
$bg_opacity   = max( 0, min( 100, intval( $attributes['bgOpacity'] ?? 100 ) ) );
$r = hexdec( substr( $bg_color, 1, 2 ) );
$g = hexdec( substr( $bg_color, 3, 2 ) );
$b = hexdec( substr( $bg_color, 5, 2 ) );
$bg_rgba = 'rgba(' . $r . ',' . $g . ',' . $b . ',' . ( $bg_opacity / 100 ) . ')';
`;
        
        // Find the end of attribute extraction usually before ?> or HTML section
        // We'll just put it right after defined('ABSPATH')
        renderPhp = renderPhp.replace(/(if \( ! defined\( 'ABSPATH' \) \) \{?\s*exit;\s*\}?)/, `$1\n${phpVars}`);

        // Update the wrapper to output style
        // Look for get_block_wrapper_attributes or class=""
        if (renderPhp.includes('get_block_wrapper_attributes')) {
            // If it already has style="background-color:<?php echo esc_attr( $bg_color ); ?>;"
            renderPhp = renderPhp.replace(/style="background-color:<\?php echo esc_attr\( \$bg_color \); \?>;"/, '');
            
            // Add custom style to wrapper if possible. Actually, simpler: wrapper might not easily accept style injected from variable unless we parse it.
            // Let's just wrap it or inject if we find exactly '<section <?php echo get_block_wrapper_attributes('
            renderPhp = renderPhp.replace(/<section <\?php echo get_block_wrapper_attributes\(([^)]*)\); \?>/, '<section <?php echo get_block_wrapper_attributes($1); ?> style="background:<?php echo esc_attr($bg_rgba); ?>;"');
            
            // For video-block and other similar blocks:
            renderPhp = renderPhp.replace(/<div class="laca-([a-z-]+)__inner"/, '<div class="laca-$1__inner" style="background:<?php echo esc_attr($bg_rgba); ?>;"');
            renderPhp = renderPhp.replace(/<section class="block-([^"]+)"/, '<section class="block-$1" style="background:<?php echo esc_attr($bg_rgba); ?>;"');
        }
        
        fs.writeFileSync(renderPhpPath, renderPhp);
        console.log(`Updated render.php for ${block}`);
    }
});
