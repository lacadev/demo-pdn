import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls, MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import {
    PanelBody,
    TextControl,
    Button,
} from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
    const {
        heading, imageId, imageUrl, budgetOptions, buttonText,
    } = attributes;

    const blockProps = useBlockProps( { className: 'block-contact-form' } );

    /* ── Budget options helpers ── */
    const updateBudget = ( index, value ) => {
        const next = budgetOptions.map( ( item, i ) => i === index ? value : item );
        setAttributes( { budgetOptions: next } );
    };
    const addBudget = () => setAttributes( { budgetOptions: [ ...budgetOptions, '' ] } );
    const removeBudget = ( index ) => setAttributes( { budgetOptions: budgetOptions.filter( ( _, i ) => i !== index ) } );

    return (
        <>
            <InspectorControls>
                <PanelBody title={ __( 'Nội dung', 'laca' ) } initialOpen={ true }>
                    <TextControl
                        label={ __( 'Tiêu đề', 'laca' ) }
                        value={ heading }
                        onChange={ ( v ) => setAttributes( { heading: v } ) }
                    />
                    <TextControl
                        label={ __( 'Text nút gửi', 'laca' ) }
                        value={ buttonText }
                        onChange={ ( v ) => setAttributes( { buttonText: v } ) }
                    />
                </PanelBody>

                <PanelBody title={ __( 'Ảnh đại diện', 'laca' ) } initialOpen={ true }>
                    <MediaUploadCheck>
                        <MediaUpload
                            onSelect={ ( media ) => setAttributes( { imageId: media.id, imageUrl: media.url } ) }
                            allowedTypes={ [ 'image' ] }
                            value={ imageId }
                            render={ ( { open } ) => (
                                <div>
                                    { imageUrl ? (
                                        <>
                                            <img src={ imageUrl } style={{ width: '100%', borderRadius: '4px', marginBottom: '8px' }} />
                                            <Button variant="secondary" onClick={ open } style={{ marginRight: '8px' }}>
                                                { __( 'Đổi ảnh', 'laca' ) }
                                            </Button>
                                            <Button isDestructive variant="link" onClick={ () => setAttributes( { imageId: 0, imageUrl: '' } ) }>
                                                { __( 'Xóa', 'laca' ) }
                                            </Button>
                                        </>
                                    ) : (
                                        <Button variant="primary" onClick={ open }>
                                            { __( 'Chọn ảnh', 'laca' ) }
                                        </Button>
                                    ) }
                                </div>
                            ) }
                        />
                    </MediaUploadCheck>
                </PanelBody>

                <PanelBody title={ __( 'Ngân sách (dropdown)', 'laca' ) } initialOpen={ false }>
                    { budgetOptions.map( ( item, index ) => (
                        <div key={ index } style={{ display: 'flex', gap: '6px', marginBottom: '6px', alignItems: 'center' }}>
                            <TextControl
                                value={ item }
                                onChange={ ( v ) => updateBudget( index, v ) }
                                placeholder={ __( 'VD: 1 - 3 tỷ', 'laca' ) }
                                style={{ flex: 1, marginBottom: 0 }}
                            />
                            <Button isDestructive size="small" onClick={ () => removeBudget( index ) }>✕</Button>
                        </div>
                    ) ) }
                    <Button variant="secondary" onClick={ addBudget }>
                        { __( '+ Thêm mục', 'laca' ) }
                    </Button>
                </PanelBody>
            </InspectorControls>

            {/* ── Canvas preview ── */}
            <section { ...blockProps }>
                <div className="container">
                    <div className="bcf__inner">
                        <div className="bcf__left">
                            { heading && <h2 className="bcf__heading">{ heading }</h2> }

                            <div className="bcf__form-preview">
                                <div className="bcf__field">
                                    <input type="text" className="bcf__input" placeholder={ __( 'Địa chỉ xây dựng', 'laca' ) } disabled />
                                </div>
                                <div className="bcf__field">
                                    <input type="text" className="bcf__input" placeholder={ __( 'Quy mô xây dựng', 'laca' ) } disabled />
                                </div>
                                <div className="bcf__field">
                                    <select className="bcf__select" disabled>
                                        <option>{ __( 'Ngân sách', 'laca' ) }</option>
                                    </select>
                                </div>
                                <div className="bcf__field">
                                    <input type="text" className="bcf__input" placeholder={ __( 'Họ và tên', 'laca' ) } disabled />
                                </div>
                                <div className="bcf__field">
                                    <input type="tel" className="bcf__input" placeholder={ __( 'Số điện thoại liên hệ', 'laca' ) } disabled />
                                </div>
                                <div className="bcf__submit-wrap">
                                    <span className="bcf__btn">{ buttonText }</span>
                                </div>
                            </div>
                        </div>

                        <div className="bcf__right">
                            { imageUrl ? (
                                <div className="bcf__img-wrap">
                                    <img src={ imageUrl } alt="" className="bcf__img" />
                                </div>
                            ) : (
                                <div className="bcf__img-placeholder">
                                    <span>{ __( 'Chọn ảnh →', 'laca' ) }</span>
                                </div>
                            ) }
                        </div>
                    </div>
                </div>
            </section>
        </>
    );
}
