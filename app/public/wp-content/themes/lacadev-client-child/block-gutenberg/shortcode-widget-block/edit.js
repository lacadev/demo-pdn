import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
    const { heading, shortcode1, shortcode2 } = attributes;
    const blockProps = useBlockProps();

    const colStyle = {
        border: '2px dashed #b9c3cc',
        borderRadius: '4px',
        padding: '1.6rem',
        background: '#f8fafc',
        flex: '1 1 0',
        minWidth: 0,
    };
    const codeStyle = { fontSize: '1.2rem', color: '#555' };

    return (
        <>
            <InspectorControls>
                <PanelBody title={ __( 'Cài đặt', 'laca' ) } initialOpen={ true }>
                    <TextControl
                        label={ __( 'Tiêu đề block', 'laca' ) }
                        value={ heading }
                        onChange={ ( v ) => setAttributes( { heading: v } ) }
                        placeholder={ __( 'VD: Nhận tư vấn trực tiếp từ KTS', 'laca' ) }
                    />
                    <TextControl
                        label={ __( 'Shortcode 1', 'laca' ) }
                        value={ shortcode1 }
                        onChange={ ( v ) => setAttributes( { shortcode1: v } ) }
                        placeholder="[wp_tuoixaydung]"
                    />
                    <TextControl
                        label={ __( 'Shortcode 2', 'laca' ) }
                        value={ shortcode2 }
                        onChange={ ( v ) => setAttributes( { shortcode2: v } ) }
                        placeholder="[wp_xemhuongnha]"
                    />
                </PanelBody>
            </InspectorControls>

            <div { ...blockProps }>
                { heading && (
                    <div className="block-shortcode-widget__header">
                        <h2 className="block-shortcode-widget__heading">{ heading }</h2>
                    </div>
                ) }
                <div style={ { display: 'flex', gap: '2rem' } }>
                    <div style={ colStyle }>
                        <code style={ codeStyle }>{ shortcode1 || '[shortcode 1]' }</code>
                    </div>
                    <div style={ colStyle }>
                        <code style={ codeStyle }>{ shortcode2 || '[shortcode 2]' }</code>
                    </div>
                </div>
            </div>
        </>
    );
}
