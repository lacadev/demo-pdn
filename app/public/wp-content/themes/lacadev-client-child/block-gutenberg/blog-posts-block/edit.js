import { __ } from '@wordpress/i18n';
import {
    InspectorControls,
    useBlockProps,
    useBlockEditContext,
} from '@wordpress/block-editor';
import {
    PanelBody,
    PanelRow,
    SelectControl,
    RangeControl,
    ToggleControl,
    CheckboxControl,
    ColorPalette,
    TextControl,
    Spinner,
    __experimentalNumberControl as NumberControl,
    ColorPicker
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useState, useEffect } from '@wordpress/element';
import previewImage from './preview.png';

export default function Edit( { attributes, setAttributes } ) {
    // ── Preview Image (Block Inserter) ──────────────────────────────────────
    const { __unstableIsPreviewMode } = useBlockEditContext();
    const isPreview = ( __unstableIsPreviewMode ?? false ) || ( attributes.__isPreview ?? false );
    if ( isPreview ) {
        return (
            <div style={ { width: '100%', lineHeight: 0 } }>
                <img
                    src={ previewImage }
                    alt="Block Preview"
                    style={ { width: '100%', height: 'auto', display: 'block' } }
                />
            </div>
        );
    }

    const { containerLayout,
        
        sectionBadge,
        sectionTitle,
        postType,
        taxonomy,
        selectedTerms,
        columns,
        postsCount,
        orderBy,
        order,
        mode,
        selectedPosts,
        ctaText,
    bgColor, bgOpacity } = attributes;

    const blockProps = useBlockProps( {
        className: 'wp-block-lacadev-blog-posts-block',
    } );

    // ── Lấy danh sách Post Types ────────────────────────────────────────────
    const postTypes = useSelect( ( select ) => {
        const types = select( 'core' ).getPostTypes( { per_page: -1 } );
        if ( ! types ) return [];
        return types
            .filter( ( t ) => t.viewable && t.slug !== 'attachment' )
            .map( ( t ) => ( { label: t.name, value: t.slug } ) );
    }, [] );

    // ── Lấy Taxonomies theo post type ────────────────────────────────────────
    const taxonomies = useSelect( ( select ) => {
        const types = select( 'core' ).getPostTypes( { per_page: -1 } );
        if ( ! types ) return [];
        const currentType = types.find( ( t ) => t.slug === postType );
        if ( ! currentType ) return [];
        return ( currentType.taxonomies || [] ).map( ( slug ) => {
            const tax = select( 'core' ).getTaxonomy( slug );
            return { label: tax ? tax.name : slug, value: slug };
        } );
    }, [ postType ] );

    // ── Lấy danh sách Terms theo taxonomy ───────────────────────────────────
    const terms = useSelect( ( select ) => {
        if ( ! taxonomy ) return [];
        const result = select( 'core' ).getEntityRecords( 'taxonomy', taxonomy, { per_page: 50 } );
        return result || [];
    }, [ taxonomy ] );

    // ── Chế độ thủ công: lấy danh sách posts để chọn ───────────────────────
    const manualPosts = useSelect( ( select ) => {
        if ( mode !== 'manual' ) return [];
        return select( 'core' ).getEntityRecords( 'postType', postType, {
            per_page: 50,
            status: 'publish',
        } ) || [];
    }, [ mode, postType ] );

    // ── Preview posts (auto mode, để hiển thị trong editor) ─────────────────
    const previewPosts = useSelect( ( select ) => {
        if ( mode !== 'auto' ) return [];
        const query = {
            per_page: postsCount,
            status: 'publish',
            orderby: orderBy,
            order,
        };
        if ( selectedTerms.length > 0 && taxonomy ) {
            query[ taxonomy ] = selectedTerms.join( ',' );
        }
        return select( 'core' ).getEntityRecords( 'postType', postType, query ) || [];
    }, [ mode, postType, taxonomy, selectedTerms, postsCount, orderBy, order ] );

    // ── Khi đổi postType → reset taxonomy / terms ───────────────────────────
    useEffect( () => {
        setAttributes( { selectedTerms: [], taxonomy: '' } );
    }, [ postType ] );

    const colsClass = columns === 4
        ? 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12'
        : 'grid grid-cols-1 md:grid-cols-3 gap-12';

    const postsToShow = mode === 'manual'
        ? manualPosts.filter( ( p ) => selectedPosts.includes( p.id ) )
        : previewPosts;

    // ── Render card ──────────────────────────────────────────────────────────
    const renderCard = ( post, i ) => {
        const thumb = post._embedded?.['wp:featuredmedia']?.[ 0 ]?.source_url;
        const catName = post._embedded?.['wp:term']?.[ 0 ]?.[ 0 ]?.name || '';
        const title = post.title?.rendered || '';
        const excerpt = post.excerpt?.rendered?.replace( /(<([^>]+)>)/gi, '' ) || '';
        const link = post.link || '#';
        return (
            <article key={ post.id || i } className="group">
                <div className="overflow-hidden rounded-lg mb-8 aspect-video bg-surface-container-high">
                    { thumb ? (
                        <img
                            src={ thumb }
                            alt={ title }
                            style={ { width: '100%', height: '100%', objectFit: 'cover', transition: 'transform 0.7s', display: 'block' } }
                        />
                    ) : (
                        <div style={ { display: 'flex', alignItems: 'center', justifyContent: 'center', height: '100%', color: '#aaa', fontSize: '0.85rem' } }>
                            { __( 'Chưa có ảnh đại diện', 'laca' ) }
                        </div>
                    ) }
                </div>
                { catName && (
                    <span className="text-xs font-label uppercase text-on-surface-variant tracking-widest block mb-3">
                        { catName }
                    </span>
                ) }
                <h3
                    className="text-2xl font-headline mb-4"
                    dangerouslySetInnerHTML={ { __html: title } }
                />
                <p
                    className="text-on-surface-variant text-sm mb-6 leading-relaxed"
                    style={ { display: '-webkit-box', WebkitLineClamp: 3, WebkitBoxOrient: 'vertical', overflow: 'hidden' } }
                >
                    { excerpt.slice( 0, 150 ) }
                </p>
                <span className="inline-flex items-center text-on-surface font-label gap-1">
                    { ctaText } →
                </span>
            </article>
        );
    };

    // ── Placeholder cards khi chưa có dữ liệu ───────────────────────────────
    const renderPlaceholder = ( count ) =>
        [ ...Array( count ) ].map( ( _, i ) => (
            <article key={ i } className="group">
                <div className="overflow-hidden rounded-lg mb-8 aspect-video bg-surface-container-high" style={ { minHeight: '180px' } } />
                <span style={ { display: 'block', width: '50%', height: '0.75rem', background: '#ddd', borderRadius: 4, marginBottom: '0.75rem' } } />
                <span style={ { display: 'block', width: '80%', height: '1.5rem', background: '#e5e5e5', borderRadius: 4, marginBottom: '1rem' } } />
                <span style={ { display: 'block', width: '100%', height: '3rem', background: '#efefef', borderRadius: 4, marginBottom: '1.5rem' } } />
                <span style={ { display: 'block', width: '30%', height: '0.75rem', background: '#ddd', borderRadius: 4 } } />
            </article>
        ) );

    return (
        <>
            { /* ── Sidebar Controls ──────────────────────────────────────────── */ }
            <InspectorControls>

                { /* Phần: Nội dung Heading */ }
                <PanelBody title={ __( 'Tiêu đề Section', 'laca' ) } initialOpen={ true }>
                    <PanelRow>
                        <TextControl
                            label={ __( 'Badge nhỏ (phụ đề)', 'laca' ) }
                            value={ sectionBadge }
                            onChange={ ( v ) => setAttributes( { sectionBadge: v } ) }
                        />
                    </PanelRow>
                    <PanelRow>
                        <TextControl
                            label={ __( 'Tiêu đề chính', 'laca' ) }
                            value={ sectionTitle }
                            onChange={ ( v ) => setAttributes( { sectionTitle: v } ) }
                        />
                    </PanelRow>
                    <PanelRow>
                        <TextControl
                            label={ __( 'Text nút "Đọc thêm"', 'laca' ) }
                            value={ ctaText }
                            onChange={ ( v ) => setAttributes( { ctaText: v } ) }
                        />
                    </PanelRow>
                </PanelBody>

                { /* Phần: Nguồn bài viết */ }
                <PanelBody title={ __( 'Nguồn bài viết', 'laca' ) } initialOpen={ true }>

                    { /* Post Type */ }
                    { postTypes.length > 0 ? (
                        <SelectControl
                            label={ __( 'Loại bài viết (Post Type)', 'laca' ) }
                            value={ postType }
                            options={ postTypes }
                            onChange={ ( v ) => setAttributes( { postType: v, selectedTerms: [], selectedPosts: [] } ) }
                        />
                    ) : (
                        <Spinner />
                    ) }

                    { /* Taxonomy */ }
                    { taxonomies.length > 0 && (
                        <SelectControl
                            label={ __( 'Taxonomy (Phân loại)', 'laca' ) }
                            value={ taxonomy }
                            options={ [ { label: __( '— Tất cả —', 'laca' ), value: '' }, ...taxonomies ] }
                            onChange={ ( v ) => setAttributes( { taxonomy: v, selectedTerms: [] } ) }
                        />
                    ) }

                    { /* Terms Checkboxes */ }
                    { taxonomy && terms.length > 0 && (
                        <div style={ { marginTop: '0.75rem' } }>
                            <p style={ { fontWeight: 600, marginBottom: '0.5rem', fontSize: '0.8rem' } }>
                                { __( 'Chọn danh mục:', 'laca' ) }
                            </p>
                            { terms.map( ( term ) => (
                                <CheckboxControl
                                    key={ term.id }
                                    label={ `${ term.name } (${ term.count })` }
                                    checked={ selectedTerms.includes( term.id ) }
                                    onChange={ ( checked ) => {
                                        const next = checked
                                            ? [ ...selectedTerms, term.id ]
                                            : selectedTerms.filter( ( id ) => id !== term.id );
                                        setAttributes( { selectedTerms: next } );
                                    } }
                                />
                            ) ) }
                        </div>
                    ) }

                    { /* Chế độ thủ công / tự động */ }
                    <SelectControl
                        label={ __( 'Chế độ chọn bài viết', 'laca' ) }
                        value={ mode }
                        options={ [
                            { label: __( 'Tự động (theo query)', 'laca' ), value: 'auto' },
                            { label: __( 'Thủ công (chọn tay)', 'laca' ), value: 'manual' },
                        ] }
                        onChange={ ( v ) => setAttributes( { mode: v, selectedPosts: [] } ) }
                        style={ { marginTop: '1rem' } }
                    />

                    { /* Chọn bài viết thủ công */ }
                    { mode === 'manual' && (
                        <div style={ { marginTop: '0.75rem' } }>
                            <p style={ { fontWeight: 600, marginBottom: '0.5rem', fontSize: '0.8rem' } }>
                                { __( 'Chọn bài viết hiển thị:', 'laca' ) }
                            </p>
                            { manualPosts.length === 0 ? <Spinner /> : manualPosts.map( ( p ) => (
                                <CheckboxControl
                                    key={ p.id }
                                    label={ p.title?.rendered || `(Post #${ p.id })` }
                                    checked={ selectedPosts.includes( p.id ) }
                                    onChange={ ( checked ) => {
                                        const next = checked
                                            ? [ ...selectedPosts, p.id ]
                                            : selectedPosts.filter( ( id ) => id !== p.id );
                                        setAttributes( { selectedPosts: next } );
                                    } }
                                />
                            ) ) }
                        </div>
                    ) }

                    { /* Số lượng bài (chỉ hiện khi auto) */ }
                    { mode === 'auto' && (
                        <RangeControl
                            label={ __( 'Số bài viết hiển thị', 'laca' ) }
                            value={ postsCount }
                            min={ 1 }
                            max={ 12 }
                            onChange={ ( v ) => setAttributes( { postsCount: v } ) }
                            style={ { marginTop: '1rem' } }
                        />
                    ) }

                    { /* Sắp xếp */ }
                    { mode === 'auto' && (
                        <>
                            <SelectControl
                                label={ __( 'Sắp xếp theo', 'laca' ) }
                                value={ orderBy }
                                options={ [
                                    { label: __( 'Ngày đăng', 'laca' ),   value: 'date' },
                                    { label: __( 'Tiêu đề (A-Z)', 'laca' ), value: 'title' },
                                    { label: __( 'Ngẫu nhiên', 'laca' ),   value: 'rand' },
                                    { label: __( 'Menu Order', 'laca' ),  value: 'menu_order' },
                                ] }
                                onChange={ ( v ) => setAttributes( { orderBy: v } ) }
                            />
                            <SelectControl
                                label={ __( 'Thứ tự', 'laca' ) }
                                value={ order }
                                options={ [
                                    { label: __( 'Mới nhất trước (DESC)', 'laca' ), value: 'DESC' },
                                    { label: __( 'Cũ nhất trước (ASC)', 'laca' ),   value: 'ASC' },
                                ] }
                                onChange={ ( v ) => setAttributes( { order: v } ) }
                            />
                        </>
                    ) }
                </PanelBody>

                { /* Phần: Layout */ }
                <PanelBody title={ __( 'Layout & Styles', 'laca' ) } initialOpen={ false }>
                    <SelectControl
                        label={ __( 'Số cột', 'laca' ) }
                        value={ String( columns ) }
                        options={ [
                            { label: __( '3 cột', 'laca' ), value: '3' },
                            { label: __( '4 cột', 'laca' ), value: '4' },
                        ] }
                        onChange={ ( v ) => setAttributes( { columns: Number( v ) } ) }
                    />
                    <SelectControl
                        label={ __( 'Container Layout', 'laca' ) }
                        value={ containerLayout }
                        options={ [
                            { label: __( 'Boxed (container)', 'laca' ),        value: 'container' },
                            { label: __( 'Full Width (container-fluid)', 'laca' ), value: 'container-fluid' },
                        ] }
                        onChange={ ( v ) => setAttributes( { containerLayout: v } ) }
                    />
                    <p style={ { fontWeight: 600, fontSize: '0.8rem', marginBottom: '0.5rem' } }>
                        { __( 'Màu nền (để trống = mặc định)', 'laca' ) }
                    </p>
                    <ColorPalette
                        value={ backgroundColor }
                        onChange={ ( v ) => setAttributes( { backgroundColor: v || '' } ) }
                    />
                </PanelBody>

            
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

            </InspectorControls>

            { /* ── Editor Preview ─────────────────────────────────────────── */ }
            <section { ...blockProps } style={ backgroundColor ? { backgroundColor } : {} }>
                <div className={ containerLayout }>
                    <div style={ { padding: '5rem 0 8rem' } }>

                        { /* Section Heading */ }
                        <div style={ { textAlign: 'center', marginBottom: '5rem' } }>
                            { sectionBadge && (
                                <span style={ {
                                    display: 'block',
                                    textTransform: 'uppercase',
                                    letterSpacing: '0.3em',
                                    fontSize: '0.75rem',
                                    marginBottom: '1rem',
                                    opacity: 0.6,
                                } }>
                                    { sectionBadge }
                                </span>
                            ) }
                            <h2 style={ { fontSize: 'clamp(1.75rem, 3vw, 2.75rem)', fontFamily: 'Georgia, serif', fontWeight: 400, margin: 0 } }>
                                { sectionTitle }
                            </h2>
                        </div>

                        { /* Posts Grid */ }
                        <div style={ {
                            display: 'grid',
                            gridTemplateColumns: `repeat(${ columns }, 1fr)`,
                            gap: '3rem',
                        } }>
                            { postsToShow.length > 0
                                ? postsToShow.map( ( p, i ) => renderCard( p, i ) )
                                : renderPlaceholder( postsCount )
                            }
                        </div>

                        { /* Mode badge */ }
                        <p style={ { textAlign: 'center', marginTop: '1.5rem', fontSize: '0.75rem', color: '#aaa' } }>
                            { mode === 'manual'
                                ? __( `✎ Chế độ thủ công — ${ selectedPosts.length } bài đã chọn`, 'laca' )
                                : __( `⚡ Tự động — ${ postsCount } bài / ${ columns } cột`, 'laca' )
                            }
                        </p>
                    </div>
                </div>
            </section>
        </>
    );
}
