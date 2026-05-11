/**
 * Multi-step Lead Form
 *
 * Handles `.laca-multistep-form` elements rendered by [laca_lead_form] shortcode.
 * No external dependencies — pure DOM + fetch.
 *
 * Features:
 *  - Step navigation (next / prev)
 *  - Per-step HTML5 constraint validation + inline error messages
 *  - Animated progress bar
 *  - AJAX submit (delegates to `laca_contact_submit`)
 *  - Accessible: aria-live notices, focus management
 */

const AJAX_URL = () => window.themeData?.ajaxurl || '/wp-admin/admin-ajax.php';

// ─── Validation ───────────────────────────────────────────────────────────────

function validatePanel( panel ) {
	let valid = true;
	let firstInvalid = null;

	// Clear previous errors
	panel.querySelectorAll( '.laca-cf-field-error' ).forEach( el => {
		el.textContent = '';
		el.hidden = true;
	} );
	panel.querySelectorAll( '.is-invalid' ).forEach( el => el.classList.remove( 'is-invalid' ) );

	// Gather all "required" inputs in this panel
	const inputs = panel.querySelectorAll( '[data-required="true"], [required]' );
	inputs.forEach( input => {
		const fieldRow  = input.closest( '.laca-cf-form-row' );
		const errorEl   = fieldRow?.querySelector( '.laca-cf-field-error' );
		let   fieldValid = true;

		if ( input.type === 'checkbox' ) {
			// For checkbox groups: at least one must be checked
			const group = input.closest( '.laca-cf-checkbox-group' );
			if ( group ) {
				const checked = group.querySelectorAll( 'input:checked' );
				if ( checked.length === 0 ) fieldValid = false;
			} else if ( ! input.checked ) {
				fieldValid = false;
			}
		} else if ( input.tagName === 'SELECT' && input.multiple ) {
			if ( input.selectedOptions.length === 0 ) fieldValid = false;
		} else if ( ! input.validity.valid || ! input.value.trim() ) {
			fieldValid = false;
		}

		if ( ! fieldValid ) {
			valid = false;
			input.classList.add( 'is-invalid', 'laca-cf-field-invalid' );
			input.setAttribute( 'aria-invalid', 'true' );
			if ( ! firstInvalid ) {
				firstInvalid = input;
			}
			if ( errorEl ) {
				const label = fieldRow?.querySelector( '.laca-cf-label' )?.textContent?.replace( '*', '' ).trim() || 'Trường này';
				errorEl.textContent = `${ label } là bắt buộc.`;
				errorEl.hidden = false;
			}
		} else {
			input.classList.remove( 'is-invalid', 'laca-cf-field-invalid' );
			input.setAttribute( 'aria-invalid', 'false' );
		}
	} );

	if ( firstInvalid ) {
		firstInvalid.focus( { preventScroll: true } );
		firstInvalid.scrollIntoView( { behavior: 'smooth', block: 'center' } );
	}

	return valid;
}

// ─── Progress ────────────────────────────────────────────────────────────────

function updateProgress( root, currentStep, totalSteps ) {
	const pct  = Math.round( ( ( currentStep + 1 ) / totalSteps ) * 100 );
	const fill = root.querySelector( '.lmsf__progress-fill' );
	const bar  = root.querySelector( '.lmsf__progress' );
	if ( fill ) fill.style.width = pct + '%';
	if ( bar  ) bar.setAttribute( 'aria-valuenow', currentStep + 1 );

	root.querySelectorAll( '.lmsf__step-dot' ).forEach( ( dot, i ) => {
		dot.classList.toggle( 'is-done',   i < currentStep );
		dot.classList.toggle( 'is-active', i === currentStep );
	} );
}

// ─── Notice ──────────────────────────────────────────────────────────────────

function showNotice( root, message, type = 'error' ) {
	const el = root.querySelector( '.lmsf__notice' );
	if ( ! el ) return;
	el.textContent = message;
	el.className   = `lmsf__notice lmsf__notice--${ type }`;
	el.hidden      = false;
	el.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
}

function clearNotice( root ) {
	const el = root.querySelector( '.lmsf__notice' );
	if ( el ) { el.hidden = true; el.textContent = ''; }
}

// ─── Submit ──────────────────────────────────────────────────────────────────

async function submitForm( root, form ) {
	const submitBtn = root.querySelector( '.lmsf__btn--submit' );
	const formData  = new FormData( form );
	formData.append( 'action', 'laca_contact_submit' );

	if ( submitBtn ) {
		submitBtn.disabled    = true;
		submitBtn.textContent = 'Đang gửi...';
	}

	try {
		const res  = await fetch( AJAX_URL(), { method: 'POST', body: formData } );
		const data = await res.json();

		if ( data.success ) {
			root.innerHTML = `
				<div class="lmsf__success" role="status">
					<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24"
					     fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
						<circle cx="12" cy="12" r="10"/>
						<path d="m9 12 2 2 4-4"/>
					</svg>
					<p>${ data.data?.message || 'Cảm ơn bạn! Chúng tôi sẽ liên hệ sớm.' }</p>
				</div>
			`;
		} else {
			const msg = data.data?.message || 'Có lỗi xảy ra. Vui lòng thử lại.';
			showNotice( root, msg, 'error' );
			if ( submitBtn ) {
				submitBtn.disabled    = false;
				submitBtn.textContent = submitBtn.dataset.labelOriginal || 'Gửi thông tin';
			}
		}
	} catch {
		showNotice( root, 'Mất kết nối. Vui lòng kiểm tra mạng và thử lại.', 'error' );
		if ( submitBtn ) {
			submitBtn.disabled    = false;
			submitBtn.textContent = submitBtn.dataset.labelOriginal || 'Gửi thông tin';
		}
	}
}

// ─── Init ─────────────────────────────────────────────────────────────────────

function initMultiStepForm( root ) {
	if ( root.dataset.lacaLeadReady === '1' ) return;
	root.dataset.lacaLeadReady = '1';

	const totalSteps = parseInt( root.dataset.totalSteps, 10 ) || 1;
	const panels     = [ ...root.querySelectorAll( '.lmsf__panel' ) ];
	const form       = root.querySelector( '.lmsf__form' );
	const btnNext    = root.querySelector( '.lmsf__btn--next' );
	const btnPrev    = root.querySelector( '.lmsf__btn--prev' );
	const btnSubmit  = root.querySelector( '.lmsf__btn--submit' );

	if ( ! form || panels.length === 0 ) return;

	if ( btnSubmit ) btnSubmit.dataset.labelOriginal = btnSubmit.textContent;

	let currentStep = 0;

	function showStep( step ) {
		panels.forEach( ( p, i ) => {
			const active = i === step;
			p.hidden = ! active;
			p.classList.toggle( 'is-active', active );
		} );
		btnPrev?.toggleAttribute( 'hidden', step === 0 );
		btnNext?.toggleAttribute( 'hidden', step >= totalSteps - 1 );
		btnSubmit?.toggleAttribute( 'hidden', step < totalSteps - 1 );

		updateProgress( root, step, totalSteps );
		clearNotice( root );

		// Move focus to first input in the new panel
		const firstInput = panels[ step ]?.querySelector( 'input, select, textarea' );
		if ( firstInput ) firstInput.focus();
	}

	// Next
	form.addEventListener( 'click', ( e ) => {
		const next = e.target.closest( '.lmsf__btn--next' );
		const prev = e.target.closest( '.lmsf__btn--prev' );

		if ( next && form.contains( next ) ) {
			e.preventDefault();
			if ( ! validatePanel( panels[ currentStep ] ) ) {
				showNotice( root, 'Vui lòng điền đầy đủ thông tin bắt buộc.', 'error' );
				return;
			}
			currentStep++;
			showStep( currentStep );
		}

		if ( prev && form.contains( prev ) ) {
			e.preventDefault();
			currentStep = Math.max( 0, currentStep - 1 );
			showStep( currentStep );
		}
	} );

	form.querySelectorAll( 'input, select, textarea' ).forEach( input => {
		input.addEventListener( 'input', () => {
			input.classList.remove( 'is-invalid', 'laca-cf-field-invalid' );
			input.setAttribute( 'aria-invalid', 'false' );
			const errorEl = input.closest( '.laca-cf-form-row' )?.querySelector( '.laca-cf-field-error' );
			if ( errorEl ) {
				errorEl.textContent = '';
				errorEl.hidden = true;
			}
		} );
	} );

	// Submit
	form.addEventListener( 'submit', async ( e ) => {
		e.preventDefault();
		if ( ! validatePanel( panels[ currentStep ] ) ) {
			showNotice( root, 'Vui lòng điền đầy đủ thông tin bắt buộc.', 'error' );
			return;
		}
		await submitForm( root, form );
	} );

	// Initial render
	showStep( 0 );
}

export function initMultiStepForms() {
	document.querySelectorAll( '.laca-multistep-form' ).forEach( initMultiStepForm );
}
