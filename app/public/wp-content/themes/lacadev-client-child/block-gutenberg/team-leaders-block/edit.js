import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps, MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import {
    PanelBody,
    PanelRow,
    TextControl,
    TextareaControl,
    Button,
    ColorPicker,
} from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
    const { sectionTitle, backgroundColor, leaders } = attributes;

    const blockProps = useBlockProps( {
        className: 'wp-block-lacadev-team-leaders-block',
    } );

    const updateLeader = ( index, key, value ) => {
        const updated = leaders.map( ( l, i ) =>
            i === index ? { ...l, [ key ]: value } : l
        );
        setAttributes( { leaders: updated } );
    };

    const addLeader = () => {
        if ( leaders.length >= 4 ) return;
        setAttributes( {
            leaders: [
                ...leaders,
                { imageId: 0, imageUrl: '', prefix: 'Ông', name: 'TÊN LÃNH ĐẠO', position: 'CHỨC VỤ', quote: '' },
            ],
        } );
    };

    const removeLeader = ( index ) => {
        setAttributes( { leaders: leaders.filter( ( _, i ) => i !== index ) } );
    };

    return (
        <>
            <InspectorControls>
                <PanelBody title={ __( 'Tiêu đề section', 'laca' ) } initialOpen={ true }>
                    <TextControl
                        label={ __( 'Tiêu đề', 'laca' ) }
                        value={ sectionTitle }
                        onChange={ ( v ) => setAttributes( { sectionTitle: v } ) }
                    />
                </PanelBody>

                <PanelBody title={ __( 'Style', 'laca' ) } initialOpen={ false }>
                    <p style={ { marginBottom: 4 } }>{ __( 'Màu nền', 'laca' ) }</p>
                    <ColorPicker
                        color={ backgroundColor }
                        onChange={ ( v ) => setAttributes( { backgroundColor: v } ) }
                        enableAlpha={ false }
                    />
                </PanelBody>

                <PanelBody title={ __( 'Danh sách lãnh đạo (tối đa 4)', 'laca' ) } initialOpen={ false }>
                    { leaders.map( ( leader, index ) => (
                        <div key={ index } style={ { borderBottom: '1px solid #ddd', marginBottom: 16, paddingBottom: 16 } }>
                            <p style={ { fontWeight: 600, marginBottom: 8 } }>{ __( 'Lãnh đạo', 'laca' ) } { index + 1 }</p>

                            <MediaUploadCheck>
                                <MediaUpload
                                    onSelect={ ( media ) => {
                                        updateLeader( index, 'imageId', media.id );
                                        updateLeader( index, 'imageUrl', media.url );
                                    } }
                                    allowedTypes={ [ 'image' ] }
                                    value={ leader.imageId }
                                    render={ ( { open } ) => (
                                        <div style={ { marginBottom: 8 } }>
                                            { leader.imageUrl
                                                ? <img src={ leader.imageUrl } alt="" style={ { width: '100%', maxHeight: 120, objectFit: 'cover', marginBottom: 4 } } />
                                                : null
                                            }
                                            <Button variant="secondary" onClick={ open }>
                                                { leader.imageUrl ? __( 'Đổi ảnh', 'laca' ) : __( 'Chọn ảnh', 'laca' ) }
                                            </Button>
                                        </div>
                                    ) }
                                />
                            </MediaUploadCheck>

                            <PanelRow>
                                <TextControl
                                    label={ __( 'Prefix (Ông/Bà)', 'laca' ) }
                                    value={ leader.prefix }
                                    onChange={ ( v ) => updateLeader( index, 'prefix', v ) }
                                />
                            </PanelRow>
                            <PanelRow>
                                <TextControl
                                    label={ __( 'Tên', 'laca' ) }
                                    value={ leader.name }
                                    onChange={ ( v ) => updateLeader( index, 'name', v ) }
                                />
                            </PanelRow>
                            <PanelRow>
                                <TextControl
                                    label={ __( 'Chức vụ', 'laca' ) }
                                    value={ leader.position }
                                    onChange={ ( v ) => updateLeader( index, 'position', v ) }
                                />
                            </PanelRow>
                            <TextareaControl
                                label={ __( 'Quote', 'laca' ) }
                                value={ leader.quote }
                                onChange={ ( v ) => updateLeader( index, 'quote', v ) }
                                rows={ 3 }
                            />
                            <Button isDestructive variant="secondary" onClick={ () => removeLeader( index ) }>
                                { __( 'Xóa', 'laca' ) }
                            </Button>
                        </div>
                    ) ) }

                    { leaders.length < 4 && (
                        <Button variant="primary" onClick={ addLeader }>
                            { __( '+ Thêm lãnh đạo', 'laca' ) }
                        </Button>
                    ) }
                </PanelBody>
            </InspectorControls>

            <div { ...blockProps } style={ { backgroundColor } }>
                <section className="block-team-leaders" style={ { backgroundColor } }>
                    <div className="block-team-leaders__inner">
                        { sectionTitle && (
                            <h2 className="block-team-leaders__title">{ sectionTitle }</h2>
                        ) }
                        <div className="block-team-leaders__grid">
                            { leaders.map( ( leader, index ) => (
                                <div key={ index } className="block-team-leaders__card">
                                    <figure className="block-team-leaders__figure">
                                        { leader.imageUrl
                                            ? <img src={ leader.imageUrl } alt={ leader.name } />
                                            : <div className="block-team-leaders__no-image"></div>
                                        }
                                    </figure>
                                    <div className="block-team-leaders__info">
                                        <div className="block-team-leaders__name-wrap">
                                            <span className="block-team-leaders__prefix">{ leader.prefix }</span>
                                            <strong className="block-team-leaders__name">{ leader.name }</strong>
                                        </div>
                                        <span className="block-team-leaders__badge">{ leader.position }</span>
                                    </div>
                                    { leader.quote && (
                                        <blockquote className="block-team-leaders__quote">
                                            "{ leader.quote }"
                                        </blockquote>
                                    ) }
                                </div>
                            ) ) }
                        </div>
                    </div>
                </section>
            </div>
        </>
    );
}
