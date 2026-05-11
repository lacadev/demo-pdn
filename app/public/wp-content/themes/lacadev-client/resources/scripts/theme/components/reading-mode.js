/**
 * Reading Mode
 *
 * Adds a floating button on single posts/pages that toggles `.reading-mode`
 * on <body>.  The visual transformation is handled entirely in CSS
 * (_reading-mode.scss).  Preference is persisted via localStorage.
 *
 * Activation criteria: body must have class `single-post` or `page` (WP
 * body_class) — so it never activates on archives or admin.
 */

const LS_KEY = 'lacadev_reading_mode';

function isEligiblePage() {
	return (
		document.body.classList.contains( 'single-post' ) ||
		document.body.classList.contains( 'page' )
	);
}

function enable() {
	document.body.classList.add( 'reading-mode' );
	localStorage.setItem( LS_KEY, '1' );
}

function disable() {
	document.body.classList.remove( 'reading-mode' );
	localStorage.removeItem( LS_KEY );
}

function toggle() {
	if ( document.body.classList.contains( 'reading-mode' ) ) {
		disable();
	} else {
		enable();
	}
	syncButtonState();
}

function syncButtonState() {
	const btn = document.getElementById( 'reading-mode-btn' );
	if ( ! btn ) return;

	const active = document.body.classList.contains( 'reading-mode' );
	btn.setAttribute( 'aria-pressed', String( active ) );
	btn.title = active
		? ( btn.dataset.labelOff || 'Exit reading mode' )
		: ( btn.dataset.labelOn  || 'Enter reading mode' );
}

function createButton() {
	if ( document.getElementById( 'reading-mode-btn' ) ) return;

	const btn = document.createElement( 'button' );
	btn.id             = 'reading-mode-btn';
	btn.type           = 'button';
	btn.className      = 'reading-mode-toggle';
	btn.dataset.labelOn  = ( window.themeData && window.themeData.i18n && window.themeData.i18n.readingModeOn ) || 'Reading mode';
	btn.dataset.labelOff = ( window.themeData && window.themeData.i18n && window.themeData.i18n.readingModeOff ) || 'Exit reading mode';

	// SVG: open book icon
	btn.innerHTML = `
		<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
		     fill="none" stroke="currentColor" stroke-width="2"
		     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
			<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
			<path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
		</svg>
	`;

	btn.addEventListener( 'click', toggle );
	document.body.appendChild( btn );
	syncButtonState();
}

export function initReadingMode() {
	if ( window.themeData && window.themeData.readingModeEnabled === false ) return;
	if ( ! isEligiblePage() ) return;

	// Restore previous preference before first paint (avoids flash)
	if ( localStorage.getItem( LS_KEY ) === '1' ) {
		document.body.classList.add( 'reading-mode' );
	}

	createButton();
}
