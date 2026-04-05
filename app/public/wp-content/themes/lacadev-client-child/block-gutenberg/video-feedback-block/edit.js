import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
    PanelBody,
    TextControl,
    Button,
    ToggleControl,
    RangeControl,
} from '@wordpress/components';

/**
 * Trích YouTube ID từ nhiều dạng URL.
 */
function extractYoutubeId( url ) {
    if ( ! url ) return '';
    const m = url.match(
        /(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|shorts\/|v\/))([A-Za-z0-9_-]{11})/
    );
    return m ? m[ 1 ] : '';
}

export default function Edit( { attributes, setAttributes } ) {
    const {
        heading,
        videos,
        slidesPerView,
        spaceBetween,
        loop,
        autoplay,
        autoplayDelay,
        showPagination,
        showNavigation,
    } = attributes;

    const blockProps = useBlockProps();

    /* ── Video repeater helpers ── */
    const updateVideo = ( index, key, value ) => {
        const next = videos.map( ( item, i ) =>
            i === index ? { ...item, [ key ]: value } : item
        );
        setAttributes( { videos: next } );
    };
    const addVideo    = () => setAttributes( { videos: [ ...videos, { url: '', name: '' } ] } );
    const removeVideo = ( index ) => setAttributes( { videos: videos.filter( ( _, i ) => i !== index ) } );

    return (
        <>
            <InspectorControls>
                {/* ── Nội dung ── */}
                <PanelBody title={ __( 'Nội dung', 'laca' ) } initialOpen={ true }>
                    <TextControl
                        label={ __( 'Tiêu đề block', 'laca' ) }
                        value={ heading }
                        onChange={ ( v ) => setAttributes( { heading: v } ) }
                        placeholder={ __( 'VD: Khách hàng nói gì về Phúc Đại Nam', 'laca' ) }
                    />
                </PanelBody>

                {/* ── Slider Settings ── */}
                <PanelBody title={ __( 'Slider Settings', 'laca' ) } initialOpen={ true }>
                    <RangeControl
                        label={ __( 'Slides Per View (Desktop)', 'laca' ) }
                        value={ slidesPerView }
                        onChange={ ( v ) => setAttributes( { slidesPerView: v } ) }
                        min={ 1 }
                        max={ 6 }
                        help={ __( 'Mobile: 1.2 / Tablet: 2.2 / Desktop: giá trị này', 'laca' ) }
                    />
                    <RangeControl
                        label={ __( 'Space Between (px)', 'laca' ) }
                        value={ spaceBetween }
                        onChange={ ( v ) => setAttributes( { spaceBetween: v } ) }
                        min={ 0 }
                        max={ 60 }
                    />
                    <ToggleControl
                        label={ __( 'Loop', 'laca' ) }
                        checked={ loop }
                        onChange={ ( v ) => setAttributes( { loop: v } ) }
                    />
                    <ToggleControl
                        label={ __( 'Autoplay', 'laca' ) }
                        checked={ autoplay }
                        onChange={ ( v ) => setAttributes( { autoplay: v } ) }
                    />
                    { autoplay && (
                        <RangeControl
                            label={ __( 'Autoplay Delay (ms)', 'laca' ) }
                            value={ autoplayDelay }
                            onChange={ ( v ) => setAttributes( { autoplayDelay: v } ) }
                            min={ 1000 }
                            max={ 10000 }
                            step={ 500 }
                        />
                    ) }
                    <ToggleControl
                        label={ __( 'Show Pagination (dots)', 'laca' ) }
                        checked={ showPagination }
                        onChange={ ( v ) => setAttributes( { showPagination: v } ) }
                    />
                    <ToggleControl
                        label={ __( 'Show Navigation (prev/next)', 'laca' ) }
                        checked={ showNavigation }
                        onChange={ ( v ) => setAttributes( { showNavigation: v } ) }
                    />
                </PanelBody>

                {/* ── Danh sách video ── */}
                <PanelBody title={ __( 'Danh sách video', 'laca' ) } initialOpen={ false }>
                    { videos.map( ( item, index ) => {
                        const ytId = extractYoutubeId( item.url );
                        return (
                            <div
                                key={ index }
                                style={ {
                                    borderBottom: '1px solid #e0e0e0',
                                    marginBottom: '1.6rem',
                                    paddingBottom: '1.6rem',
                                } }
                            >
                                <strong style={ { display: 'block', marginBottom: '0.8rem' } }>
                                    { __( 'Video', 'laca' ) } { index + 1 }
                                </strong>
                                <TextControl
                                    label={ __( 'URL YouTube', 'laca' ) }
                                    value={ item.url }
                                    onChange={ ( v ) => updateVideo( index, 'url', v ) }
                                    placeholder="https://youtu.be/..."
                                />
                                { ytId && (
                                    <img
                                        src={ `https://img.youtube.com/vi/${ ytId }/mqdefault.jpg` }
                                        alt="YouTube thumbnail"
                                        style={ {
                                            width: '100%',
                                            borderRadius: '4px',
                                            marginBottom: '0.8rem',
                                        } }
                                    />
                                ) }
                                <TextControl
                                    label={ __( 'Tên người feedback', 'laca' ) }
                                    value={ item.name }
                                    onChange={ ( v ) => updateVideo( index, 'name', v ) }
                                    placeholder={ __( 'VD: Mr Thành – Đồng Nai', 'laca' ) }
                                />
                                <Button
                                    isDestructive
                                    variant="secondary"
                                    size="small"
                                    onClick={ () => removeVideo( index ) }
                                >
                                    { __( 'Xoá video này', 'laca' ) }
                                </Button>
                            </div>
                        );
                    } ) }
                    <Button variant="primary" onClick={ addVideo }>
                        { __( '+ Thêm video', 'laca' ) }
                    </Button>
                </PanelBody>
            </InspectorControls>

            {/* ── Canvas preview ── */}
            <div { ...blockProps }>
                { heading && (
                    <div className="block-video-feedback__header">
                        <h2 className="block-video-feedback__heading">{ heading }</h2>
                    </div>
                ) }

                { videos.length > 0 ? (
                    <div className="block-video-feedback__preview-grid">
                        { videos.map( ( item, index ) => {
                            const ytId = extractYoutubeId( item.url );
                            return (
                                <figure key={ index } className="block-video-feedback__preview-item">
                                    <div
                                        className={ `block-video-feedback__thumb-wrap${ ! ytId ? ' block-video-feedback__thumb-wrap--empty' : '' }` }
                                    >
                                        { ytId ? (
                                            <>
                                                <img
                                                    src={ `https://img.youtube.com/vi/${ ytId }/mqdefault.jpg` }
                                                    alt={ item.name || `Video ${ index + 1 }` }
                                                />
                                                <span className="block-video-feedback__play" aria-hidden="true">▶</span>
                                            </>
                                        ) : (
                                            <span>YouTube URL</span>
                                        ) }
                                    </div>
                                    { item.name && (
                                        <figcaption className="block-video-feedback__name">{ item.name }</figcaption>
                                    ) }
                                </figure>
                            );
                        } ) }
                    </div>
                ) : (
                    <p style={ { textAlign: 'center', color: '#aaa', padding: '2rem' } }>
                        { __( 'Thêm video trong "Danh sách video" →', 'laca' ) }
                    </p>
                ) }
            </div>
        </>
    );
}
