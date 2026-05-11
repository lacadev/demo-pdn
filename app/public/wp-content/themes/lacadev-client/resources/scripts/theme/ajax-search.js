/**
 * Smart Search
 *
 * Strategy:
 *   1. On first keystroke, fetch the lightweight JSON index from
 *      /wp-json/lacadev/v1/search-index and cache it in sessionStorage.
 *   2. Use Fuse.js for instant client-side fuzzy search (no network round-trip).
 *   3. Index is refreshed automatically when the CDN/WP transient expires (30 min).
 *
 * Falls back to the legacy AJAX handler if the index endpoint is unavailable.
 */

import Fuse from 'fuse.js';

// ─── Config ───────────────────────────────────────────────────────────────────

const FUSE_OPTIONS = {
	includeScore:      true,
	includeMatches:    true,
	threshold:         0.35,   // 0 = exact, 1 = match anything
	minMatchCharLength: 2,
	ignoreLocation:    true,
	keys: [
		{ name: 'title',   weight: 0.7 },
		{ name: 'excerpt', weight: 0.2 },
		{ name: 'type',    weight: 0.1 },
	],
};

const CACHE_KEY    = 'lacadev_search_index_v1';
const CACHE_TTL    = 30 * 60 * 1000; // 30 min in ms
const DEBOUNCE_MS  = 220;
const MIN_QUERY    = 2;
const MAX_RESULTS  = 12;

// ─── State ────────────────────────────────────────────────────────────────────
let fuse          = null;
let indexLoaded   = false;
let indexLoading  = false;
let searchTimeout = null;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function getIndexUrl() {
	return window.themeData?.searchIndex || null;
}

function readCache() {
	try {
		const raw = sessionStorage.getItem( CACHE_KEY );
		if ( ! raw ) return null;
		const { ts, data } = JSON.parse( raw );
		if ( Date.now() - ts > CACHE_TTL ) {
			sessionStorage.removeItem( CACHE_KEY );
			return null;
		}
		return data;
	} catch {
		return null;
	}
}

function writeCache( data ) {
	try {
		sessionStorage.setItem( CACHE_KEY, JSON.stringify( { ts: Date.now(), data } ) );
	} catch {
		/* storage full — skip */
	}
}

async function loadIndex() {
	if ( indexLoaded || indexLoading ) return;
	indexLoading = true;

	// Try sessionStorage cache first
	const cached = readCache();
	if ( cached ) {
		fuse        = new Fuse( cached, FUSE_OPTIONS );
		indexLoaded = true;
		indexLoading = false;
		return;
	}

	const url = getIndexUrl();
	if ( ! url ) {
		indexLoading = false;
		return;
	}

	try {
		const res  = await fetch( url, { credentials: 'omit' } );
		if ( ! res.ok ) throw new Error( `HTTP ${ res.status }` );
		const data = await res.json();
		writeCache( data );
		fuse        = new Fuse( data, FUSE_OPTIONS );
		indexLoaded = true;
	} catch ( err ) {
		console.warn( '[SmartSearch] Could not load index:', err );
	} finally {
		indexLoading = false;
	}
}

// ─── Rendering ────────────────────────────────────────────────────────────────

function highlightMatch( text, indices ) {
	if ( ! indices || indices.length === 0 ) return escapeHtml( text );

	let result = '';
	let cursor = 0;
	for ( const [ start, end ] of indices ) {
		result += escapeHtml( text.slice( cursor, start ) );
		result += `<mark>${ escapeHtml( text.slice( start, end + 1 ) ) }</mark>`;
		cursor = end + 1;
	}
	result += escapeHtml( text.slice( cursor ) );
	return result;
}

function escapeHtml( str ) {
	const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
	return String( str ).replace( /[&<>"']/g, c => map[ c ] );
}

function typeLabel( type ) {
	const labels = {
		post:    'Bài viết',
		page:    'Trang',
		product: 'Sản phẩm',
	};
	return labels[ type ] || type.charAt( 0 ).toUpperCase() + type.slice( 1 );
}

function renderResults( fuseResults, query ) {
	if ( fuseResults.length === 0 ) {
		return `<div class="search-results__empty"><p>Không tìm thấy kết quả cho "<strong>${ escapeHtml( query ) }</strong>"</p></div>`;
	}

	// Group by post type
	const grouped = {};
	for ( const r of fuseResults.slice( 0, MAX_RESULTS ) ) {
		const { item, matches } = r;
		const titleMatch   = matches?.find( m => m.key === 'title' );
		const excerptMatch = matches?.find( m => m.key === 'excerpt' );

		const group = item.type;
		if ( ! grouped[ group ] ) grouped[ group ] = [];
		grouped[ group ].push( { item, titleMatch, excerptMatch } );
	}

	// Preferred ordering: product > post > page > everything else
	const ORDER = [ 'product', 'post', 'page' ];
	const types = Object.keys( grouped ).sort( ( a, b ) => {
		const ia = ORDER.indexOf( a ), ib = ORDER.indexOf( b );
		if ( ia !== -1 && ib !== -1 ) return ia - ib;
		if ( ia !== -1 ) return -1;
		if ( ib !== -1 ) return 1;
		return a.localeCompare( b );
	} );

	let html = '';
	for ( const type of types ) {
		const items = grouped[ type ];
		html += `
		<div class="search-results__section">
			<h3 class="search-results__title">
				<strong>${ escapeHtml( typeLabel( type ) ) } liên quan</strong>
				<span class="search-results__count">(${ items.length } kết quả)</span>:
			</h3>
			<div class="search-results__list">
		`;
		for ( const { item, titleMatch, excerptMatch } of items ) {
			const title   = titleMatch
				? highlightMatch( item.title, titleMatch.indices )
				: escapeHtml( item.title );
			const excerpt = excerptMatch
				? highlightMatch( item.excerpt, excerptMatch.indices )
				: escapeHtml( item.excerpt );
			html += `
				<a href="${ escapeHtml( item.url ) }" class="search-results__item">
					${ item.thumb
						? `<div class="search-results__image"><img src="${ escapeHtml( item.thumb ) }" alt="" loading="lazy" decoding="async"></div>`
						: '' }
					<div class="search-results__content">
						<h4 class="search-results__item-title">${ title }</h4>
						${ item.excerpt ? `<p class="search-results__item-excerpt">${ excerpt }</p>` : '' }
					</div>
				</a>
			`;
		}
		html += '</div></div>';
	}

	return html;
}

// ─── Core search ──────────────────────────────────────────────────────────────

async function performSearch( query, resultsContainer ) {
	// Ensure index is available
	if ( ! indexLoaded ) {
		resultsContainer.innerHTML = '<div class="search-results__loading">Đang tải...</div>';
		resultsContainer.classList.add( 'active' );
		await loadIndex();
	}

	// Client-side Fuse search
	if ( fuse ) {
		const results = fuse.search( query );
		resultsContainer.innerHTML = renderResults( results, query );
		resultsContainer.classList.add( 'active' );
		return;
	}

	// Fallback: legacy AJAX
	legacyAjaxSearch( query, resultsContainer );
}

function legacyAjaxSearch( query, resultsContainer ) {
	if ( ! window.themeData ) return;

	resultsContainer.innerHTML = '<div class="search-results__loading">Đang tìm kiếm...</div>';
	resultsContainer.classList.add( 'active' );

	const params = new URLSearchParams( {
		action: 'ajax_search',
		s:      query,
		nonce:  window.themeData.searchNonce || window.themeData.nonce,
	} );

	fetch( `${ window.themeData.ajaxurl }?${ params }`, {
		headers: { 'X-Requested-With': 'XMLHttpRequest' },
	} )
		.then( r => r.text() )
		.then( html => {
			resultsContainer.innerHTML = html || '<div class="search-results__empty"><p>Không tìm thấy kết quả</p></div>';
			resultsContainer.classList.add( 'active' );
		} )
		.catch( () => {
			resultsContainer.innerHTML = '<div class="search-results__error">Có lỗi xảy ra. Vui lòng thử lại.</div>';
			resultsContainer.classList.add( 'active' );
		} );
}

// ─── Init ─────────────────────────────────────────────────────────────────────

export function initSmartSearch() {
	const searchInput = document.querySelector( '.header__bottom-search input[type="text"]' );
	const searchForm  = document.querySelector( '.header__bottom-search .search-box' );

	if ( ! searchInput ) return;

	// Ensure results container exists
	let resultsContainer = searchForm?.querySelector( '.search-results' );
	if ( ! resultsContainer ) {
		resultsContainer = document.createElement( 'div' );
		resultsContainer.className = 'search-results';
		( searchForm || searchInput.parentElement ).appendChild( resultsContainer );
	}

	// Pre-warm the index on page load (best-effort)
	if ( document.readyState === 'complete' ) {
		loadIndex();
	} else {
		window.addEventListener( 'load', loadIndex, { once: true } );
	}

	// Typing handler
	searchInput.addEventListener( 'input', ( e ) => {
		const query = e.target.value.trim();
		clearTimeout( searchTimeout );

		if ( query.length < MIN_QUERY ) {
			resultsContainer.innerHTML = '';
			resultsContainer.classList.remove( 'active' );
			return;
		}

		searchTimeout = setTimeout( () => performSearch( query, resultsContainer ), DEBOUNCE_MS );
	} );

	// Close on outside click
	document.addEventListener( 'click', ( e ) => {
		if ( ! e.target.closest( '.header__bottom-search' ) ) {
			resultsContainer.classList.remove( 'active' );
		}
	} );

	// Re-show on focus if results present
	searchInput.addEventListener( 'focus', () => {
		if ( resultsContainer.innerHTML.trim() ) {
			resultsContainer.classList.add( 'active' );
		}
	} );

	// Reset
	searchForm?.addEventListener( 'reset', () => {
		setTimeout( () => {
			resultsContainer.innerHTML = '';
			resultsContainer.classList.remove( 'active' );
		}, 10 );
	} );
}
