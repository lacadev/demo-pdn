/**
 * Mobile Menu
 * Full-screen overlay menu với accordion submenu cho mobile.
 */

// State: lưu refs để closeMobileMenu() có thể gọi mà không cần DOM query lại.
let _burgerBtn = null;
let _overlay = null;

export function closeMobileMenu() {
	if ( ! _burgerBtn || ! _overlay ) {
		// Fallback: query DOM trực tiếp
		const btn = document.getElementById( 'btn-hamburger' );
		const ov = document.querySelector( '.header__overlay' );
		if ( btn ) btn.classList.remove( 'active' );
		if ( ov ) ov.classList.remove( 'active' );
		document.body.classList.remove( 'menu-open' );
		return;
	}
	_burgerBtn.classList.remove( 'active' );
	_overlay.classList.remove( 'active' );
	document.body.classList.remove( 'menu-open' );
}

export function initMobileMenu() {
	const burgerBtn = document.getElementById( 'btn-hamburger' );
	const overlay = document.querySelector( '.header__overlay' );
	if ( ! burgerBtn || ! overlay ) return;

	// Lưu refs để closeMobileMenu() có thể dùng
	_burgerBtn = burgerBtn;
	_overlay = overlay;

	const controller = new AbortController();
	const { signal } = controller;

	burgerBtn.addEventListener( 'click', () => {
		const isActive = burgerBtn.classList.contains( 'active' );
		if ( isActive ) {
			closeMobileMenu();
		} else {
			burgerBtn.classList.add( 'active' );
			overlay.classList.add( 'active' );
			document.body.classList.add( 'menu-open' );
		}
	}, { signal } );

	overlay.querySelectorAll( 'a' ).forEach( ( link ) => {
		link.addEventListener( 'click', ( e ) => {
			const parentLi = link.parentElement;
			const isParent = parentLi.classList.contains( 'has-children' );

			if ( window.innerWidth < 992 && isParent && ! parentLi.classList.contains( 'open' ) ) {
				e.preventDefault();
				e.stopPropagation();
				overlay.querySelectorAll( '.has-children' ).forEach( ( p ) => {
					if ( p !== parentLi ) p.classList.remove( 'open' );
				} );
				parentLi.classList.add( 'open' );
				return;
			}

			closeMobileMenu();
		}, { signal } );
	} );

	return () => controller.abort();
}
