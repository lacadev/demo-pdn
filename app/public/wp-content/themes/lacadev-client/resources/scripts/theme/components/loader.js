import gsap from 'gsap';

const LOADER_KEY = 'laca_loader_shown';
const HOURS_24 = 24 * 60 * 60 * 1000;
const WORDS = [ 'LA CÀ DEV', 'WORDPRESS', 'BLOG', 'TRAVELLING', 'MINIMAL', 'CLEAN' ];

let flickerInterval;

function stopFlicker() {
	if ( flickerInterval ) {
		clearInterval( flickerInterval );
		flickerInterval = null;
	}
}

function startFlicker() {
	stopFlicker();

	const randoms = document.querySelectorAll( '.randoms' );
	flickerInterval = setInterval( () => {
		randoms.forEach( el => {
			el.textContent = WORDS[ Math.floor( Math.random() * WORDS.length ) ];
			el.style.opacity = Math.random() > 0.5 ? '1' : '0.1';
		} );
	}, 120 );
}

function hidePageLoader() {
	const loader = document.querySelector( '.page-loader' );
	const textLoader = document.querySelector( '.text-loader' );

	if ( ! loader || ! textLoader ) {
		document.documentElement.classList.remove( 'loading' );
		document.body.classList.remove( 'overflow-hidden' );
		stopFlicker();
		return;
	}

	document.querySelectorAll( '.randoms' ).forEach( el => {
		el.style.opacity = '1';
	} );

	gsap.to( textLoader, {
		opacity: 0,
		duration: 0.5,
		delay: 0.3,
		ease: 'power2.inOut',
		onComplete: () => {
			gsap.to( loader, {
				opacity: 0,
				duration: 0.6,
				delay: 0.1,
				ease: 'power2.inOut',
				onComplete: () => {
					loader.style.display = 'none';
					loader.classList.remove( 'active' );
					document.body.classList.remove( 'overflow-hidden' );
					document.documentElement.classList.remove( 'loading' );
					stopFlicker();
				},
			} );
		},
	} );
}

export function shouldShowLoader() {
	const lastShown = localStorage.getItem( LOADER_KEY );
	return ! lastShown || Date.now() - parseInt( lastShown, 10 ) >= HOURS_24;
}

export function initPageLoader( isMobile ) {
	const loader = document.querySelector( '.page-loader' );

	if ( ! loader ) {
		document.documentElement.classList.remove( 'loading' );
		document.body.classList.remove( 'overflow-hidden' );
		return;
	}

	if ( isMobile || ! shouldShowLoader() ) {
		loader.style.display = 'none';
		loader.classList.remove( 'active' );
		document.body.classList.remove( 'overflow-hidden' );
		document.documentElement.classList.remove( 'loading' );
		return;
	}

	loader.classList.add( 'active' );
	document.body.classList.add( 'overflow-hidden' );
	document.documentElement.classList.add( 'loading' );
	startFlicker();
	localStorage.setItem( LOADER_KEY, Date.now().toString() );

	const startTime = Date.now();
	const minDisplay = 1000;
	const finish = () => {
		setTimeout( hidePageLoader, Math.max( 0, minDisplay - ( Date.now() - startTime ) ) );
	};

	if ( document.readyState === 'complete' ) {
		finish();
	} else {
		window.addEventListener( 'load', finish, { once: true } );
	}

	setTimeout( () => {
		if ( loader.style.display !== 'none' ) {
			hidePageLoader();
		}
	}, 5000 );
}
