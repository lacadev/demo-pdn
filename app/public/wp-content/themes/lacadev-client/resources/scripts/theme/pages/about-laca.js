import gsap from 'gsap';

export const initAboutLacaHero = () => {
	const heroSection = document.querySelector( '.block-about-laca' );

	if ( ! heroSection ) {
		return;
	}

	const imgContainer = heroSection.querySelector( '.img-container' );
	const content = heroSection.querySelector( '.content-wrapper' );

	if ( ! imgContainer || ! content ) {
		return;
	}

	const parallaxBg = heroSection.querySelector( '.parallax-bg' );

	gsap.killTweensOf( [ imgContainer, content, parallaxBg ] );

	const rootFontSize = parseFloat(
		window.getComputedStyle( document.documentElement ).fontSize
	);
	const initialMaxWidth = 50 * rootFontSize;
	const fullWidth = window.innerWidth;

	gsap.set( imgContainer, {
		maxWidth: initialMaxWidth,
		borderRadius: '2rem',
	} );
	gsap.set( content, { opacity: 0, y: 30 } );

	if ( parallaxBg ) {
		gsap.set( parallaxBg, { scale: 1.1 } );
	}

	const timeline = gsap.timeline( {
		scrollTrigger: {
			trigger: heroSection,
			start: '50% bottom',
			end: '+=70%',
			scrub: 1,
			invalidateOnRefresh: true,
		},
	} );

	timeline.fromTo(
		imgContainer,
		{ maxWidth: initialMaxWidth, borderRadius: '2rem' },
		{
			maxWidth: fullWidth,
			borderRadius: '0rem',
			ease: 'none',
			force3D: true,
		},
		0
	);

	if ( parallaxBg ) {
		timeline.fromTo(
			parallaxBg,
			{ scale: 1.1 },
			{ scale: 1, ease: 'none', force3D: true },
			0
		);
	}

	timeline.fromTo(
		content,
		{ opacity: 0, y: 30 },
		{ opacity: 1, y: 0, duration: 0.4, ease: 'power2.out', force3D: true },
		0.3
	);
};
