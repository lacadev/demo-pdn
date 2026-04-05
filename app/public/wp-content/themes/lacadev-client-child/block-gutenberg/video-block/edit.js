import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	RichText,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
	Button,
	Placeholder,
	RangeControl,
} from '@wordpress/components';
import { ColorPalette } from '@wordpress/block-editor';
import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';

/**
 * Convert YouTube / Vimeo URL → embed URL for editor preview.
 * Falls back to original URL if not recognised.
 */
function getEmbedUrl( url ) {
	// YouTube
	const ytMatch = url.match(
		/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/
	);
	if ( ytMatch ) {
		return `https://www.youtube.com/embed/${ ytMatch[ 1 ] }`;
	}
	// Vimeo
	const vmMatch = url.match( /vimeo\.com\/(\d+)/ );
	if ( vmMatch ) {
		return `https://player.vimeo.com/video/${ vmMatch[ 1 ] }`;
	}
	return url;
}

export default function Edit( { attributes, setAttributes } ) {

	const {
		sourceType,
		videoUrl,
		videoId,
		videoFileUrl,
		autoplay,
		loop,
		muted,
		controls,
		posterUrl,
		posterId,
		overlayEnabled,
		overlayColor,
		overlayOpacity,
		overlayText,
		overlayFontSize,
	} = attributes;

	const blockProps = useBlockProps( {
		className: 'laca-video-block',
	} );

	const handleSelectVideo = ( media ) => {
		setAttributes( {
			videoId: media.id,
			videoFileUrl: media.url,
		} );
	};

	const handleRemoveVideo = () => {
		setAttributes( {
			videoId: 0,
			videoFileUrl: '',
		} );
	};

	const handleSelectPoster = ( media ) => {
		setAttributes( {
			posterId: media.id,
			posterUrl: media.url,
		} );
	};

	const handleRemovePoster = () => {
		setAttributes( {
			posterId: 0,
			posterUrl: '',
		} );
	};

	const hasVideo =
		( sourceType === 'url' && videoUrl ) ||
		( sourceType === 'file' && videoFileUrl );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Cài đặt Video', 'lacadev' ) }
					initialOpen={ true }
				>
					<SelectControl
						label={ __( 'Nguồn Video', 'lacadev' ) }
						value={ sourceType }
						options={ [
							{ label: __( 'URL (YouTube, Vimeo…)', 'lacadev' ), value: 'url' },
							{ label: __( 'File Upload', 'lacadev' ), value: 'file' },
						] }
						onChange={ ( val ) =>
							setAttributes( { sourceType: val } )
						}
					/>

					{ sourceType === 'url' && (
						<TextControl
							label={ __( 'URL Video', 'lacadev' ) }
							value={ videoUrl }
							placeholder="https://www.youtube.com/watch?v=..."
							onChange={ ( val ) =>
								setAttributes( { videoUrl: val } )
							}
						/>
					) }

					{ sourceType === 'file' && (
						<MediaUploadCheck>
							<MediaUpload
								onSelect={ handleSelectVideo }
								allowedTypes={ [ 'video' ] }
								value={ videoId }
								render={ ( { open } ) => (
									<div className="laca-video-block__media-control">
										{ videoFileUrl ? (
											<>
												<video
													src={ videoFileUrl }
													preload="metadata"
													style={ {
														width: '100%',
														maxHeight: '160px',
														borderRadius: '4px',
														marginBottom: '8px',
													} }
												/>
												<Button
													variant="secondary"
													onClick={ open }
													style={ { marginRight: '8px' } }
												>
													{ __( 'Thay video', 'lacadev' ) }
												</Button>
												<Button
													variant="link"
													isDestructive
													onClick={ handleRemoveVideo }
												>
													{ __( 'Xoá', 'lacadev' ) }
												</Button>
											</>
										) : (
											<Button
												variant="primary"
												onClick={ open }
											>
												{ __( 'Chọn File Video', 'lacadev' ) }
											</Button>
										) }
									</div>
								) }
							/>
						</MediaUploadCheck>
					) }
				</PanelBody>

				<PanelBody
					title={ __( 'Poster (Ảnh thumbnail)', 'lacadev' ) }
					initialOpen={ false }
				>
					<MediaUploadCheck>
						<MediaUpload
							onSelect={ handleSelectPoster }
							allowedTypes={ [ 'image' ] }
							value={ posterId }
							render={ ( { open } ) => (
								<div className="laca-video-block__media-control">
									{ posterUrl ? (
										<>
											<img
												src={ posterUrl }
												alt={ __( 'Poster', 'lacadev' ) }
												style={ {
													width: '100%',
													height: '80px',
													objectFit: 'cover',
													borderRadius: '4px',
													marginBottom: '8px',
												} }
											/>
											<Button
												variant="secondary"
												onClick={ open }
												style={ { marginRight: '8px' } }
											>
												{ __( 'Thay poster', 'lacadev' ) }
											</Button>
											<Button
												variant="link"
												isDestructive
												onClick={ handleRemovePoster }
											>
												{ __( 'Xoá', 'lacadev' ) }
											</Button>
										</>
									) : (
										<Button variant="secondary" onClick={ open }>
											{ __( 'Chọn Ảnh Poster', 'lacadev' ) }
										</Button>
									) }
								</div>
							) }
						/>
					</MediaUploadCheck>
				</PanelBody>

				<PanelBody
					title={ __( 'Tuỳ chọn phát', 'lacadev' ) }
					initialOpen={ false }
				>
					<ToggleControl
						label={ __( 'Hiển thị controls', 'lacadev' ) }
						checked={ controls }
						onChange={ ( val ) => setAttributes( { controls: val } ) }
					/>
					<ToggleControl
						label={ __( 'Tự động phát', 'lacadev' ) }
						checked={ autoplay }
						onChange={ ( val ) => setAttributes( { autoplay: val } ) }
					/>
					<ToggleControl
						label={ __( 'Tắt tiếng', 'lacadev' ) }
						checked={ muted }
						onChange={ ( val ) => setAttributes( { muted: val } ) }
					/>
					<ToggleControl
						label={ __( 'Lặp lại', 'lacadev' ) }
						checked={ loop }
						onChange={ ( val ) => setAttributes( { loop: val } ) }
					/>
				</PanelBody>

				{ /* ── Overlay panel ── */ }
				<PanelBody
					title={ __( 'Overlay', 'lacadev' ) }
					initialOpen={ false }
				>
					<ToggleControl
						label={ __( 'Bật overlay', 'lacadev' ) }
						checked={ overlayEnabled }
						onChange={ ( val ) => setAttributes( { overlayEnabled: val } ) }
					/>
					{ overlayEnabled && (
						<>
							<p style={ { marginBottom: '4px', fontWeight: 600, fontSize: '11px', textTransform: 'uppercase', color: '#1e1e1e' } }>
								{ __( 'Màu overlay', 'lacadev' ) }
							</p>
							<ColorPalette
								value={ overlayColor }
								onChange={ ( val ) => setAttributes( { overlayColor: val || '#000000' } ) }
							/>
							<RangeControl
								label={ __( 'Độ mờ overlay (%)', 'lacadev' ) }
								value={ overlayOpacity }
								min={ 0 }
								max={ 100 }
								step={ 5 }
								onChange={ ( val ) => setAttributes( { overlayOpacity: val } ) }
							/>
							<RangeControl
								label={ __( 'Cỡ chữ overlay (px)', 'lacadev' ) }
								value={ overlayFontSize }
								min={ 10 }
								max={ 120 }
								step={ 1 }
								onChange={ ( val ) => setAttributes( { overlayFontSize: val } ) }
							/>
						</>
					) }
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ ! hasVideo ? (
					<Placeholder
						icon="video-alt3"
						label={ __( 'Video Block', 'lacadev' ) }
						instructions={ __(
							'Chọn nguồn video ở thanh bên phải (URL hoặc file upload)',
							'lacadev'
						) }
						className="laca-video-block__placeholder"
					/>
				) : (
					<div className="laca-video-block__preview" style={ { position: 'relative' } }>
						{ sourceType === 'url' && videoUrl ? (
							<div className="laca-video-block__iframe-wrap">
								<iframe
									src={ getEmbedUrl( videoUrl ) }
									frameBorder="0"
									allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
									allowFullScreen
									style={ { width: '100%', height: '100%', border: 0 } }
									title="Video preview"
								/>
							</div>
						) : (
							<video
								src={ videoFileUrl }
								poster={ posterUrl || undefined }
								controls={ controls }
								preload="metadata"
								style={ { width: '100%', borderRadius: '8px' } }
							/>
						) }

						{ /* Overlay preview */ }
						{ overlayEnabled && (
							<div
								className="laca-video-block__overlay"
								style={ {
									position: 'absolute',
									inset: 0,
									backgroundColor: overlayColor,
									opacity: overlayOpacity / 100,
									display: 'flex',
									alignItems: 'center',
									justifyContent: 'center',
									pointerEvents: 'none',
								} }
							/>
						) }

						{ /* RichText overlay text — luôn hiển thị khi overlay bật */ }
						{ overlayEnabled && (
							<div
								className="laca-video-block__overlay-text"
								style={ {
									position: 'absolute',
									inset: 0,
									display: 'flex',
									alignItems: 'center',
									justifyContent: 'center',
									padding: '2rem',
									zIndex: 2,
									fontSize: `${ overlayFontSize }px`,
								} }
							>
								<RichText
									tagName="p"
									value={ overlayText }
									onChange={ ( val ) => setAttributes( { overlayText: val } ) }
									placeholder={ __( 'Nhập văn bản overlay…', 'lacadev' ) }
									allowedFormats={ [
										'core/bold',
										'core/italic',
										'core/underline',
										'core/strikethrough',
										'core/link',
										'core/text-color',
										'core/font-size',
										'core/subscript',
										'core/superscript',
									] }
									style={ { color: '#fff', textAlign: 'center', width: '100%', margin: 0 } }
								/>
							</div>
						) }
					</div>
				) }
			</div>
		</>
	);
}
