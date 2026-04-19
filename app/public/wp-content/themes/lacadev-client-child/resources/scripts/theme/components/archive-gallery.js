/**
 * Archive Gallery – JS
 *
 * Chức năng:
 * 1. Toggle dropdown filter
 * 2. AJAX filter theo gallery-cat
 * 3. AJAX pagination
 * 4. Fancybox popup khi click card/button
 */

import '@fancyapps/ui/dist/fancybox/fancybox.css';
import { Fancybox } from '@fancyapps/ui';

const ROOT_SELECTOR   = '.laca-gallery-archive';
const GRID_ID         = 'gallery-grid';
const PAGINATION_ID   = 'gallery-pagination';
const FILTER_SELECTOR = '.laca-gallery-filter';
const CARD_SELECTOR   = '.laca-gallery-card';

// ─────────────────────────────────────────────────────────────────────────────
//  Fancybox options
// ─────────────────────────────────────────────────────────────────────────────
const FANCYBOX_OPTS = {
	Toolbar: {
		display: {
			left  : [ 'infobar' ],
			middle: [],
			right : [ 'slideshow', 'thumbs', 'close' ],
		},
	},
	Thumbs: { type: 'classic' },
};

/**
 * Mở Fancybox cho một card cụ thể.
 * @param {HTMLElement} card
 */
function openGallery( card ) {
	let items = [];
	try {
		items = JSON.parse( card.dataset.galleryItems || '[]' );
	} catch ( err ) {
		console.error( '[Gallery] JSON parse error:', err );
		return;
	}

	if ( ! items.length ) {
		console.warn( '[Gallery] No images for card:', card );
		return;
	}

	// Fancybox v6 item format: { src, thumb, caption }
	// PHP template dùng key 'subHtml' → map thành 'caption'
	const fancyItems = items.map( ( item ) => ( {
		src    : item.src,
		thumb  : item.thumb  || item.src,
		caption: item.caption || item.subHtml || '',
		type   : 'image',
	} ) );

	Fancybox.show( fancyItems, FANCYBOX_OPTS );
}

/**
 * Bind click delegation — chỉ bind 1 lần trên root.
 * @param {HTMLElement} root
 */
function bindClickDelegation( root ) {
	root.addEventListener( 'click', ( e ) => {
		const trigger = e.target.closest( '.js-open-gallery, .laca-gallery-card__img' );
		if ( ! trigger ) return;

		const card = trigger.closest( CARD_SELECTOR );
		if ( ! card ) return;

		e.preventDefault();
		openGallery( card );
	} );
}

/**
 * AJAX request lấy grid mới.
 */
async function fetchGallery( { config, catSlug, paged } ) {
	const body = new URLSearchParams( {
		action        : 'lacadev_gallery_archive_load',
		nonce         : config.nonce,
		cat_slug      : catSlug,
		paged         : paged,
		posts_per_page: config.posts_per_page,
	} );
	const res = await fetch( config.ajaxurl, { method: 'POST', body } );
	return res.json();
}

/**
 * Update grid + pagination + URL sau AJAX.
 */
function updatePage( { gridEl, paginationEl, filterEl, html, pagination, activeLabel, catSlug, paged, archiveUrl } ) {
	gridEl.innerHTML       = html;
	paginationEl.innerHTML = pagination;

	// Update toolbar title (tên danh mục)
	const titleEl = document.querySelector( '.laca-gallery-toolbar__title' );
	if ( titleEl ) titleEl.textContent = activeLabel;

	const labelEl = filterEl.querySelector( '.laca-gallery-filter__label' );
	if ( labelEl ) labelEl.textContent = activeLabel;

	filterEl.querySelectorAll( '[data-cat-slug]' ).forEach( item => {
		item.classList.toggle( 'is-active', item.dataset.catSlug === catSlug );
	} );

	const url = new URL( archiveUrl );
	if ( catSlug ) url.searchParams.set( 'gallery-cat', catSlug );
	else           url.searchParams.delete( 'gallery-cat' );
	if ( paged > 1 ) url.searchParams.set( 'paged', paged );
	else             url.searchParams.delete( 'paged' );
	history.pushState( { catSlug, paged }, '', url.toString() );
}

/**
 * Main init — chạy khi DOM ready và sau mỗi Barba navigation.
 */
function init() {
	const root = document.querySelector( ROOT_SELECTOR );
	if ( ! root ) return;

	const gridEl = document.getElementById( GRID_ID );
	if ( ! gridEl ) return;

	// Click delegation — chỉ bind 1 lần mỗi root
	if ( ! root.dataset.galleryBound ) {
		root.dataset.galleryBound = '1';
		bindClickDelegation( root );
	}

	// ── AJAX features ──
	const config = JSON.parse( root.dataset.archiveConfig || '{}' );
	if ( ! config.ajaxurl ) return;

	const paginationEl = document.getElementById( PAGINATION_ID );
	const filterEl     = root.querySelector( FILTER_SELECTOR );
	let currentCat     = config.cat_slug || '';

	// ── Dropdown filter ──
	if ( filterEl && ! filterEl.dataset.bound ) {
		filterEl.dataset.bound = '1';
		const trigger = filterEl.querySelector( '.laca-gallery-filter__trigger' );
		const list    = filterEl.querySelector( '.laca-gallery-filter__list' );

		if ( trigger && list ) {
			trigger.addEventListener( 'click', () => {
				const isOpen = filterEl.classList.toggle( 'is-open' );
				trigger.setAttribute( 'aria-expanded', isOpen );
			} );

			document.addEventListener( 'click', ( e ) => {
				if ( ! filterEl.contains( e.target ) ) {
					filterEl.classList.remove( 'is-open' );
					trigger.setAttribute( 'aria-expanded', 'false' );
				}
			} );

			list.addEventListener( 'click', async ( e ) => {
				const item = e.target.closest( '[data-cat-slug]' );
				if ( ! item ) return;
				e.preventDefault();

				const catSlug = item.dataset.catSlug;
				if ( catSlug === currentCat ) {
					filterEl.classList.remove( 'is-open' );
					return;
				}

				filterEl.classList.remove( 'is-open' );
				gridEl.classList.add( 'is-loading' );
				try {
					const res = await fetchGallery( { config, catSlug, paged: 1 } );
					if ( res.success ) {
						currentCat = catSlug;
						updatePage( {
							gridEl, paginationEl, filterEl,
							html       : res.data.html,
							pagination : res.data.pagination,
							activeLabel: res.data.active_label,
							catSlug, paged: 1,
							archiveUrl : config.archive_url,
						} );
					}
				} finally {
					gridEl.classList.remove( 'is-loading' );
				}
			} );
		}
	}

	// ── Pagination ──
	if ( paginationEl && ! paginationEl.dataset.bound ) {
		paginationEl.dataset.bound = '1';
		paginationEl.addEventListener( 'click', async ( e ) => {
			const link = e.target.closest( 'a' );
			if ( ! link ) return;
			e.preventDefault();

			const paged = parseInt( new URL( link.href ).searchParams.get( 'paged' ) || '1', 10 );
			gridEl.classList.add( 'is-loading' );
			try {
				const res = await fetchGallery( { config, catSlug: currentCat, paged } );
				if ( res.success ) {
					updatePage( {
						gridEl, paginationEl, filterEl,
						html       : res.data.html,
						pagination : res.data.pagination,
						activeLabel: res.data.active_label,
						catSlug    : currentCat, paged,
						archiveUrl : config.archive_url,
					} );
					window.scrollTo( { top: root.offsetTop - 80, behavior: 'smooth' } );
				}
			} finally {
				gridEl.classList.remove( 'is-loading' );
			}
		} );
	}
}

// ── Bootstrap ─────────────────────────────────────────────────────────────────
let _barbaHooked = false;

function bootstrap() {
	init();
	setTimeout( () => {
		if ( window.barba && ! _barbaHooked ) {
			_barbaHooked = true;
			window.barba.hooks.after( () => init() );
		}
	}, 0 );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', bootstrap );
} else {
	bootstrap();
}
