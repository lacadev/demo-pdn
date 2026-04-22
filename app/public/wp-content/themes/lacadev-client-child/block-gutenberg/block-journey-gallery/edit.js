import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
    TextareaControl,
    Button,
    TextControl,
    PanelBody
} from '@wordpress/components';
import {
    BlockBasePanels
} from '../utils/inspector-panels';
import { useInserterPreview, BlockPreviewMock } from '../utils/preview';
import { hexToRgba, getSpacingVars } from '../utils/style';

function hasResponsiveSpacingValue( spacing = {} ) {
    const devices = [ 'desktop', 'tablet', 'mobile' ];
    const types = [ 'margin', 'padding' ];
    const sides = [ 'top', 'left', 'bottom', 'right' ];

    return devices.some( ( device ) =>
        types.some( ( type ) =>
            sides.some( ( side ) => `${ spacing?.[ device ]?.[ type ]?.[ side ] ?? '' }`.trim() !== '' )
        )
    );
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
        spacing,
        marginTop,
        marginBottom,
        paddingTop,
        paddingBottom
    } = attributes;
    const backgroundColor = hexToRgba( bgColor || '#0f0f0f', bgOpacity );
    const useResponsiveSpacing = hasResponsiveSpacingValue( spacing );
    const blockProps = useBlockProps( {
        className: 'block-journey-gallery',
        style: {
            background: backgroundColor,
            ...( useResponsiveSpacing
                ? getSpacingVars( spacing, '--laca-journey' )
                : {
                    marginTop: `${ marginTop }px`,
                    marginBottom: `${ marginBottom }px`,
                    paddingTop: `${ paddingTop }px`,
                    paddingBottom: `${ paddingBottom }px`
                } ),
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
    const displaySteps = steps.filter( ( step ) => ( step?.title || '' ).trim() || ( step?.imageUrl || '' ).trim() );
    const cols = Math.max( 1, Math.ceil( displaySteps.length / 2 ) );

    return (
        <>
            <InspectorControls>
                <BlockBasePanels
                    attributes={ attributes }
                    setAttributes={ setAttributes }
                    textdomain="laca"
                    titleLabel="Tiêu đề section"
                    subtitleLabel="Phụ đề section"
                    titlePlaceholder="HÀNH TRÌNH XÂY NHÀ"
                    subtitlePlaceholder="Mô tả ngắn cho section"
                    spacingAttributeKey="spacing"
                />

                <PanelBody title={ __( 'Nội dung block', 'laca' ) } initialOpen={ true }>
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
                </PanelBody>

            </InspectorControls>

            {/* ── Canvas preview ── */}
            <div { ...blockProps }>
                <div className="container-fluid">
                    { heading && (
                        <div className="block-journey-gallery__header">
                            <h2 className="block-journey-gallery__heading">{ heading }</h2>
                            { subheading && (
                                <p className="block-journey-gallery__subheading">{ subheading }</p>
                            ) }
                        </div>
                    ) }

                    { displaySteps.length > 0 ? (
                        <div className="block-journey-gallery__track" style={ { '--jg-cols': cols } }>
                            { displaySteps.map( ( step, index ) => {
                                const stepNumber = index + 1;
                                const col = Math.floor( index / 2 ) + 1;
                                const row = index % 2 === 0 ? 1 : 3;
                                const isEven = index % 2 !== 0;

                                return (
                                    <article
                                        key={ index }
                                        className={ `block-journey-gallery__step${ isEven ? ' block-journey-gallery__step--even' : '' }` }
                                        style={ { '--jg-col': col, '--jg-row': row } }
                                    >
                                        <div className="block-journey-gallery__content">
                                            <span className="block-journey-gallery__number">{ stepNumber }</span>

                                            <div className="block-journey-gallery__text">
                                                { step.title && (
                                                    <h3 className="block-journey-gallery__title">{ step.title }</h3>
                                                ) }
                                                { step.description && (
                                                    <p className="block-journey-gallery__desc">{ step.description }</p>
                                                ) }
                                            </div>
                                        </div>

                                        { step.imageUrl && (
                                            <div className="block-journey-gallery__image">
                                                <img src={ step.imageUrl } alt={ step.imageAlt || step.title || '' } />
                                            </div>
                                        ) }
                                    </article>
                                );
                            } ) }

                            <div className="block-journey-gallery__timeline">
                                <div className="block-journey-gallery__timeline-line"></div>
                                { displaySteps.map( ( _, index ) => (
                                    <span key={ `timeline-${ index }` } className="block-journey-gallery__timeline-dot"></span>
                                ) ) }
                            </div>
                        </div>
                    ) : (
                        <p style={ { textAlign: 'center', color: '#888', padding: '3rem 2rem' } }>
                            { __( 'Thêm các bước hành trình trong sidebar →', 'laca' ) }
                        </p>
                    ) }
                </div>
            </div>
        </>
    );
}
