/* eslint-disable no-console, no-unused-vars, no-alert */
// eslint-disable-next-line no-unused-vars
import '@styles/login';

document.addEventListener( 'DOMContentLoaded', () => {
	const loginHeaderLink = document.querySelector( '#login h1 a' );
	if ( loginHeaderLink ) {
		loginHeaderLink.setAttribute( 'href', 'https://lacadev.com/' );
		loginHeaderLink.setAttribute( 'target', '_blank' );
	}

	// 1. DUAL TRANSLATIONS
	const locales = {
		vi: {
			userLabel: 'Ai đang ghé trạm?',
			userPlaceholder: 'Điền tên hoặc email vào đây nhé',
			passLabel: 'Chìa khóa',
			passPlaceholder: 'Nhập chìa khóa mở cửa',
			welcomeText:
				'Chào mừng về Trạm Laca!<br/>Cắm sạc, pha trà và bắt đầu nào!',
			forgetPwd: 'Rớt chìa khoá?',
			backToBlog: '← Rời khỏi Trạm',
		},
		en: {
			userLabel: "Who's visiting the station?",
			userPlaceholder: 'Enter name or email here',
			passLabel: 'The Key',
			passPlaceholder: 'Enter your key to open',
			welcomeText:
				"Welcome to Laca Station!<br/>Charge up, brew some tea and let's go!",
			forgetPwd: 'Lost your key?',
			backToBlog: '← Leave the Station',
		},
	};

	const currentLang = document.documentElement.lang.includes( 'en' )
		? 'en'
		: 'vi';
	const i18n = locales[ currentLang ];

	const userLabel = document.querySelector( 'label[for="user_login"]' );
	if ( userLabel ) {
		userLabel.childNodes[ 0 ].textContent = i18n.userLabel;
	}
	const userLogin = document.getElementById( 'user_login' );
	if ( userLogin ) {
		userLogin.setAttribute( 'placeholder', i18n.userPlaceholder );
	}

	const passLabel = document.querySelector( 'label[for="user_pass"]' );
	if ( passLabel ) {
		passLabel.childNodes[ 0 ].textContent = i18n.passLabel;
	}
	const userPass = document.getElementById( 'user_pass' );
	if ( userPass ) {
		userPass.setAttribute( 'placeholder', i18n.passPlaceholder );
	}

	// Rename footer links
	const navLink = document.querySelector( '#nav a' );
	if ( navLink ) {
		navLink.textContent = i18n.forgetPwd;
	}

	const backLink = document.querySelector( '#backtoblog a' );
	if ( backLink ) {
		backLink.textContent = i18n.backToBlog;
	}

	// Move links into a horizontal container
	const nav = document.getElementById( 'nav' );
	const back = document.getElementById( 'backtoblog' );
	const loginDiv = document.getElementById( 'login' );

	if ( nav && back && loginDiv ) {
		const footerLinks = document.createElement( 'div' );
		footerLinks.className = 'login-footer-links';
		footerLinks.appendChild( nav );
		footerLinks.appendChild( back );
		loginDiv.appendChild( footerLinks );
	}

	// 2. CUSTOM LANGUAGE TOGGLE
	const switcher = document.querySelector( '.language-switcher' );
	const switcherForm = document.querySelector( '.language-switcher form' );
	const switcherSelect = document.querySelector(
		'.language-switcher select'
	);

	if ( switcher && loginDiv ) {
		// Place toggle ABOVE the form/logo
		loginDiv.prepend( switcher );

		if ( switcherForm && switcherSelect ) {
			const langWrapper = document.createElement( 'div' );
			langWrapper.className = 'alp-lang-toggle';

			langWrapper.innerHTML = `
				<button type="button" data-lang="vi" class="${
					currentLang === 'vi' ? 'active' : ''
				}">Vie</button>
				<button type="button" data-lang="en_US" class="${
					currentLang === 'en' ? 'active' : ''
				}">Eng</button>
			`;

			switcherForm.appendChild( langWrapper );

			langWrapper.querySelectorAll( 'button' ).forEach( ( btn ) => {
				btn.addEventListener( 'click', ( e ) => {
					const targetLang = e.target.getAttribute( 'data-lang' );
					if ( switcherSelect.value !== targetLang ) {
						switcherSelect.value = targetLang;
						switcherForm.submit();
					}
				} );
			} );
		}
	}

	// 3. TYPEWRITER EFFECT
	const welcomeDiv = document.createElement( 'div' );
	welcomeDiv.className = 'welcome';

	const logo = document.querySelector( '#login h1' );
	if ( logo ) {
		logo.insertAdjacentElement( 'afterend', welcomeDiv );
	}

	const welcomeText = i18n.welcomeText;
	let charIndex = 0;

	function typeWriter() {
		if ( charIndex < welcomeText.length ) {
			if ( welcomeText.charAt( charIndex ) === '<' ) {
				const tagEnd = welcomeText.indexOf( '>', charIndex );
				if ( tagEnd !== -1 ) {
					welcomeDiv.innerHTML += welcomeText.substring(
						charIndex,
						tagEnd + 1
					);
					charIndex = tagEnd + 1;
				} else {
					welcomeDiv.innerHTML += welcomeText.charAt( charIndex );
					charIndex++;
				}
			} else {
				welcomeDiv.innerHTML += welcomeText.charAt( charIndex );
				charIndex++;
			}

			const speed = Math.random() * ( 150 - 50 ) + 50;
			setTimeout( typeWriter, speed );
		} else {
			welcomeDiv.classList.add( 'typed-done' );
		}
	}

	setTimeout( typeWriter, 600 );
} );
