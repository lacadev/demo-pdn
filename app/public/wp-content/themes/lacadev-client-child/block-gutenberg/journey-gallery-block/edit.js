import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	RichText,
	MediaUpload,
	MediaUploadCheck,
} from '@wordpress/block-editor';
import {
	PanelBody,
	Button,
	RangeControl,
	SelectControl,
	TextControl,
	__experimentalText as Text,
} from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
	const {
		heading,
		description,
		items,
		columns,
		headingTag,
		containerLayout,
	} = attributes;

	const blockProps = useBlockProps( {
		className: 'block-journey-gallery',
	} );

	// ─── Helpers ─────────────────────────────────────────────────────────────
	const addItem = () => {
		setAttributes( {
			items: [
				...items,
				{ id: Date.now(), imageId: 0, imageUrl: '', imageAlt: '' },
			],
		} );
	};

	const removeItem = ( index ) => {
		const next = items.filter( ( _, i ) => i !== index );
		setAttributes( { items: next } );
	};

	const updateItem = ( index, patch ) => {
		const next = items.map( ( item, i ) =>
			i === index ? { ...item, ...patch } : item
		);
		setAttributes( { items: next } );
	};

	const moveItem = ( from, to ) => {
		const next = [ ...items ];
		const [ moved ] = next.splice( from, 1 );
		next.splice( to, 0, moved );
		setAttributes( { items: next } );
	};

	// ─── Render ──────────────────────────────────────────────────────────────
	return (
		<>
			{ /* ── Sidebar ── */ }
			<InspectorControls>
				<PanelBody title={ __( 'Cài đặt chung', 'laca' ) } initialOpen={ true }>
					<SelectControl
						label={ __( 'Thẻ tiêu đề', 'laca' ) }
						value={ headingTag }
						options={ [
							{ label: 'H2', value: 'h2' },
							{ label: 'H3', value: 'h3' },
							{ label: 'H4', value: 'h4' },
						] }
						onChange={ ( val ) => setAttributes( { headingTag: val } ) }
					/>
					<SelectControl
						label={ __( 'Bố cục', 'laca' ) }
						value={ containerLayout }
						options={ [
							{ label: 'Container', value: 'container' },
							{ label: 'Full width', value: 'container-fluid' },
						] }
						onChange={ ( val ) => setAttributes( { containerLayout: val } ) }
					/>
					<RangeControl
						label={ __( 'Số cột ảnh', 'laca' ) }
						value={ columns }
						min={ 1 }
						max={ 4 }
						step={ 1 }
						onChange={ ( val ) => setAttributes( { columns: val } ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Danh sách ảnh', 'laca' ) } initialOpen={ true }>
					{ items.length === 0 && (
						<Text isDestructive style={ { display: 'block', marginBottom: '8px' } }>
							{ __( 'Chưa có ảnh nào. Nhấn "+ Thêm ảnh" để bắt đầu.', 'laca' ) }
						</Text>
					) }

					{ items.map( ( item, index ) => (
						<div
							key={ item.id ?? index }
							style={ {
								border: '1px solid #e0e0e0',
								borderRadius: '4px',
								padding: '8px',
								marginBottom: '10px',
								background: '#fafafa',
							} }
						>
							<p style={ { fontWeight: 600, marginBottom: '6px', fontSize: '12px' } }>
								{ __( 'Ảnh', 'laca' ) } #{ index + 1 }
							</p>

							{ /* Thumbnail preview */ }
							{ item.imageUrl && (
								<img
									src={ item.imageUrl }
									alt={ item.imageAlt || '' }
									style={ {
										width: '100%',
										aspectRatio: '16/9',
										objectFit: 'cover',
										borderRadius: '4px',
										marginBottom: '6px',
									} }
								/>
							) }

							{ /* Upload button */ }
							<MediaUploadCheck>
								<MediaUpload
									onSelect={ ( media ) =>
										updateItem( index, {
											imageId: media.id,
											imageUrl: media.url,
											imageAlt: media.alt || media.title || '',
										} )
									}
									allowedTypes={ [ 'image' ] }
									value={ item.imageId }
									render={ ( { open } ) => (
										<Button
											onClick={ open }
											variant="secondary"
											style={ { width: '100%', marginBottom: '6px', justifyContent: 'center' } }
										>
											{ item.imageId
												? __( 'Thay ảnh', 'laca' )
												: __( 'Chọn ảnh', 'laca' ) }
										</Button>
									) }
								/>
							</MediaUploadCheck>

							{ /* Alt text */ }
							<TextControl
								label={ __( 'Alt text', 'laca' ) }
								value={ item.imageAlt || '' }
								onChange={ ( val ) => updateItem( index, { imageAlt: val } ) }
								placeholder={ __( 'Mô tả ảnh (SEO)…', 'laca' ) }
							/>

							{ /* Move / Remove */ }
							<div style={ { display: 'flex', gap: '6px', marginTop: '4px' } }>
								{ index > 0 && (
									<Button
										isSmall
										variant="tertiary"
										onClick={ () => moveItem( index, index - 1 ) }
									>
										↑
									</Button>
								) }
								{ index < items.length - 1 && (
									<Button
										isSmall
										variant="tertiary"
										onClick={ () => moveItem( index, index + 1 ) }
									>
										↓
									</Button>
								) }
								<Button
									isSmall
									isDestructive
									onClick={ () => removeItem( index ) }
									style={ { marginLeft: 'auto' } }
								>
									{ __( 'Xoá', 'laca' ) }
								</Button>
							</div>
						</div>
					) ) }

					<Button
						variant="primary"
						onClick={ addItem }
						style={ { width: '100%', justifyContent: 'center', marginTop: '4px' } }
					>
						{ __( '+ Thêm ảnh', 'laca' ) }
					</Button>
				</PanelBody>
			</InspectorControls>

			{ /* ── Canvas ── */ }
			<section { ...blockProps }>
				<div className={ containerLayout }>
					{ /* Header */ }
					<div className="block-journey-gallery__header">
						<RichText
							tagName={ headingTag }
							className="block-journey-gallery__heading"
							value={ heading }
							onChange={ ( val ) => setAttributes( { heading: val } ) }
							placeholder={ __( 'Nhập tiêu đề…', 'laca' ) }
							allowedFormats={ [ 'core/bold', 'core/italic' ] }
						/>
						<RichText
							tagName="p"
							className="block-journey-gallery__description"
							value={ description }
							onChange={ ( val ) => setAttributes( { description: val } ) }
							placeholder={ __( 'Nhập mô tả (tuỳ chọn)…', 'laca' ) }
							allowedFormats={ [
								'core/bold',
								'core/italic',
								'core/link',
								'core/text-color',
							] }
						/>
					</div>

					{ /* Grid preview */ }
					{ items.length > 0 ? (
						<div
							className="block-journey-gallery__grid"
							style={ { gridTemplateColumns: `repeat(${ columns }, 1fr)` } }
						>
							{ items.map( ( item, index ) => (
								<div
									key={ item.id ?? index }
									className="block-journey-gallery__item"
								>
									{ item.imageUrl ? (
										<img
											src={ item.imageUrl }
											alt={ item.imageAlt || '' }
											className="block-journey-gallery__img"
										/>
									) : (
										<div className="block-journey-gallery__placeholder">
											<span className="dashicons dashicons-format-image" />
											<p>{ __( 'Chưa có ảnh', 'laca' ) }</p>
										</div>
									) }
								</div>
							) ) }
						</div>
					) : (
						<div
							style={ {
								padding: '4rem 2rem',
								textAlign: 'center',
								border: '2px dashed #ccc',
								borderRadius: '8px',
								color: '#888',
							} }
						>
							<span className="dashicons dashicons-format-gallery" style={ { fontSize: '3rem', height: 'auto', width: 'auto' } } />
							<p>{ __( 'Thêm ảnh từ sidebar bên phải.', 'laca' ) }</p>
						</div>
					) }
				</div>
			</section>
		</>
	);
}
