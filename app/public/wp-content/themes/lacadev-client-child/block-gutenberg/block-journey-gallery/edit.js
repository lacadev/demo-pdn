import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
    TextareaControl,
    Button,
    TextControl
} from '@wordpress/components';
import {
    BlockConfigPanel,
    TitlePanel,
    SubtitlePanel,
    AppearancePanel,
    SpacingPanel
} from '../utils/inspector-panels';
import { useInserterPreview, BlockPreviewMock } from '../utils/preview';

function hexToRgba( hex, opacity = 100 ) {
    if ( ! hex || ! /^#[0-9A-Fa-f]{6}$/.test( hex ) ) {
        return `rgba(15,15,15,${ Math.max( 0, Math.min( 100, opacity ) ) / 100 })`;
    }

    const normalizedOpacity = Math.max( 0, Math.min( 100, opacity ) ) / 100;
    const r = Number.parseInt( hex.slice( 1, 3 ), 16 );
    const g = Number.parseInt( hex.slice( 3, 5 ), 16 );
    const b = Number.parseInt( hex.slice( 5, 7 ), 16 );

    return `rgba(${ r },${ g },${ b },${ normalizedOpacity })`;
}

export default function Edit( { attributes, setAttributes } ) {
    const isPreview = useInserterPreview( attributes );
    if ( isPreview ) {
        return (
            <BlockPreviewMock
                kicker={ __( 'Journey Gallery', 'laca' ) }
                title={ attributes.heading || __( 'HÀNH TRÌNH XÂY NHÀ', 'laca' ) }
                columns={ 3 }
            />
        );
    }

    const {
        heading,
        subheading,
        steps,
        bgColor,
        bgOpacity,
        marginTop,
        marginBottom,
        paddingTop,
        paddingBottom
    } = attributes;
    const backgroundColor = hexToRgba( bgColor || '#0f0f0f', bgOpacity );
    const blockProps = useBlockProps( {
        style: {
            background: backgroundColor,
            marginTop: `${ marginTop }px`,
            marginBottom: `${ marginBottom }px`,
            paddingTop: `${ paddingTop }px`,
            paddingBottom: `${ paddingBottom }px`
        },
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
                <BlockConfigPanel textdomain="laca">
                    <p style={ { marginTop: 0, marginBottom: '0.8rem' } }>
                        { __( 'Thiết lập nội dung chính của block ở đây.', 'laca' ) }
                    </p>

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
                </BlockConfigPanel>

                <TitlePanel
                    value={ heading }
                    onChange={ ( value ) => setAttributes( { heading: value } ) }
                    textdomain="laca"
                    label="Tiêu đề section"
                    placeholder="HÀNH TRÌNH XÂY NHÀ"
                />

                <SubtitlePanel
                    value={ subheading }
                    onChange={ ( value ) => setAttributes( { subheading: value } ) }
                    textdomain="laca"
                    label="Phụ đề section"
                    placeholder="Mô tả ngắn cho section"
                />

                <AppearancePanel
                    textdomain="laca"
                    bgColor={ bgColor || '#0f0f0f' }
                    bgOpacity={ bgOpacity }
                    setAttributes={ setAttributes }
                />

                <SpacingPanel
                    textdomain="laca"
                    marginTop={ marginTop }
                    marginBottom={ marginBottom }
                    paddingTop={ paddingTop }
                    paddingBottom={ paddingBottom }
                    setAttributes={ setAttributes }
                />

            </InspectorControls>

            {/* ── Canvas preview ── */}
            <div { ...blockProps }>
                { heading && (
                    <div className="block-journey-gallery__header">
                        <h2 className="block-journey-gallery__heading">{ heading }</h2>
                        { subheading && (
                            <p className="block-journey-gallery__subheading">{ subheading }</p>
                        ) }
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
