import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
    PanelBody,
    TextControl,
    TextareaControl,
    Button,
} from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
    const { heading, bgColor, steps } = attributes;
    const blockProps = useBlockProps( {
        style: { background: bgColor || '#1e1e1e' },
    } );

    /* ── Mở WordPress media modal trực tiếp ── */
    const openMedia = ( index ) => {
        if ( ! window.wp || ! window.wp.media ) return;
        const frame = window.wp.media( {
            title: __( 'Chọn ảnh', 'laca' ),
            button: { text: __( 'Dùng ảnh này', 'laca' ) },
            multiple: false,
        } );
        frame.on( 'select', () => {
            const attachment = frame.state().get( 'selection' ).first().toJSON();
            const next = steps.map( ( item, i ) =>
                i === index
                    ? { ...item, imageId: attachment.id, imageUrl: attachment.url, imageAlt: attachment.alt || '' }
                    : item
            );
            setAttributes( { steps: next } );
        } );
        frame.open();
    };

    /* ── Step repeater helpers ── */
    const updateStep = ( index, key, value ) => {
        const next = steps.map( ( item, i ) =>
            i === index ? { ...item, [ key ]: value } : item
        );
        setAttributes( { steps: next } );
    };
    const addStep    = () => setAttributes( { steps: [ ...steps, { title: '', description: '', imageId: 0, imageUrl: '', imageAlt: '' } ] } );
    const removeStep = ( index ) => setAttributes( { steps: steps.filter( ( _, i ) => i !== index ) } );

    return (
        <>
            <InspectorControls>
                {/* ── Cài đặt chung ── */}
                <PanelBody title={ __( 'Cài đặt chung', 'laca' ) } initialOpen={ true }>
                    <TextControl
                        label={ __( 'Tiêu đề section', 'laca' ) }
                        value={ heading }
                        onChange={ ( v ) => setAttributes( { heading: v } ) }
                        placeholder="HÀNH TRÌNH XÂY NHÀ"
                    />
                    <TextControl
                        type="color"
                        label={ __( 'Màu nền', 'laca' ) }
                        value={ bgColor || '#1e1e1e' }
                        onChange={ ( v ) => setAttributes( { bgColor: v } ) }
                        help={ __( 'Mặc định: #1e1e1e (nền tối)', 'laca' ) }
                    />
                    { bgColor && (
                        <Button
                            variant="secondary"
                            isSmall
                            onClick={ () => setAttributes( { bgColor: '' } ) }
                        >
                            { __( 'Đặt lại màu mặc định', 'laca' ) }
                        </Button>
                    ) }
                </PanelBody>

                {/* ── Danh sách bước ── */}
                <PanelBody title={ __( 'Các bước hành trình', 'laca' ) } initialOpen={ true }>
                    { steps.map( ( step, index ) => (
                        <div
                            key={ index }
                            style={ {
                                borderBottom: '1px solid #e0e0e0',
                                marginBottom: '1.6rem',
                                paddingBottom: '1.6rem',
                            } }
                        >
                            <strong style={ { display: 'block', marginBottom: '0.8rem' } }>
                                { __( 'Bước', 'laca' ) } { index + 1 }
                            </strong>
                            <TextControl
                                label={ __( 'Tiêu đề', 'laca' ) }
                                value={ step.title }
                                onChange={ ( v ) => updateStep( index, 'title', v ) }
                                placeholder="NHẬN TƯ VẤN TRỰC TIẾP TỪ KIẾN TRÚC SƯ"
                            />
                            <TextareaControl
                                label={ __( 'Mô tả', 'laca' ) }
                                value={ step.description }
                                onChange={ ( v ) => updateStep( index, 'description', v ) }
                                rows={ 3 }
                            />
                            { step.imageUrl && (
                                <img
                                    src={ step.imageUrl }
                                    alt={ step.imageAlt }
                                    style={ {
                                        width: '100%',
                                        borderRadius: '6px',
                                        marginBottom: '0.6rem',
                                        aspectRatio: '4/3',
                                        objectFit: 'cover',
                                        display: 'block',
                                    } }
                                />
                            ) }
                            <Button
                                variant={ step.imageUrl ? 'secondary' : 'primary' }
                                onClick={ () => openMedia( index ) }
                                style={ { marginBottom: '0.6rem', display: 'block' } }
                            >
                                { step.imageUrl ? __( 'Đổi ảnh', 'laca' ) : __( 'Chọn ảnh', 'laca' ) }
                            </Button>
                            { step.imageUrl && (
                                <Button
                                    isDestructive
                                    variant="secondary"
                                    size="small"
                                    onClick={ () => {
                                        updateStep( index, 'imageId',  0 );
                                        updateStep( index, 'imageUrl', '' );
                                        updateStep( index, 'imageAlt', '' );
                                    } }
                                    style={ { marginBottom: '0.4rem', display: 'block' } }
                                >
                                    { __( 'Xoá ảnh', 'laca' ) }
                                </Button>
                            ) }
                            <Button
                                isDestructive
                                variant="secondary"
                                size="small"
                                onClick={ () => removeStep( index ) }
                                style={ { marginTop: '0.4rem' } }
                            >
                                { __( 'Xoá bước này', 'laca' ) }
                            </Button>
                        </div>
                    ) ) }
                    <Button variant="primary" onClick={ addStep }>
                        { __( '+ Thêm bước', 'laca' ) }
                    </Button>
                </PanelBody>
            </InspectorControls>

            {/* ── Canvas preview ── */}
            <div { ...blockProps }>
                { heading && (
                    <div className="block-journey-gallery__header">
                        <h2 className="block-journey-gallery__heading">{ heading }</h2>
                    </div>
                ) }

                { steps.length > 0 ? (
                    <div className="block-journey-gallery__preview-grid">
                        { steps.map( ( step, index ) => (
                            <div key={ index } className="block-journey-gallery__preview-step">
                                <span className="block-journey-gallery__preview-number">{ index + 1 }</span>
                                { step.imageUrl && (
                                    <img
                                        src={ step.imageUrl }
                                        alt={ step.imageAlt }
                                        className="block-journey-gallery__preview-img"
                                    />
                                ) }
                                { step.title && (
                                    <strong className="block-journey-gallery__preview-title">{ step.title }</strong>
                                ) }
                                { step.description && (
                                    <p className="block-journey-gallery__preview-desc">{ step.description }</p>
                                ) }
                            </div>
                        ) ) }
                    </div>
                ) : (
                    <p style={ { textAlign: 'center', color: '#888', padding: '3rem 2rem' } }>
                        { __( 'Thêm các bước hành trình trong sidebar →', 'laca' ) }
                    </p>
                ) }
            </div>
        </>
    );
}
