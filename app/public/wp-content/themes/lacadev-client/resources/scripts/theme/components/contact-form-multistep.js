const FORM_SELECTOR = '.laca-contact-form--multistep';
const BUNDLE_READY_FLAG = 'lacaCfMultiStepBundleReady';

const ajaxUrl = () => window.themeData?.ajaxurl || '/wp-admin/admin-ajax.php';

function fieldRows( form ) {
	return Array.from( form.querySelectorAll( '.laca-cf-form-row' ) );
}

function fieldsByName( form, name ) {
	return Array.from( form.querySelectorAll( 'input, select, textarea' ) ).filter(
		field => field.name === name
	);
}

function getFieldValue( form, name ) {
	const fields = fieldsByName( form, name );

	if ( ! fields.length ) {
		return '';
	}

	const first = fields[ 0 ];

	if ( first.type === 'radio' ) {
		return fields.find( field => field.checked )?.value || '';
	}

	if ( first.type === 'checkbox' ) {
		return fields.filter( field => field.checked ).map( field => field.value );
	}

	if ( first.tagName === 'SELECT' && first.multiple ) {
		return Array.from( first.selectedOptions ).map( option => option.value );
	}

	return first.value || '';
}

function conditionMatches( form, row ) {
	const fieldName = row.dataset.conditionField;

	if ( ! fieldName ) {
		return true;
	}

	const operator = row.dataset.conditionOperator || 'equals';
	const expected = row.dataset.conditionValue || '';
	const value = getFieldValue( form, fieldName );
	const values = Array.isArray( value ) ? value : [ value ];
	const valueString = values.join( ', ' );

	switch ( operator ) {
		case 'not_equals':
			return ! values.includes( expected );
		case 'contains':
			return expected !== '' && valueString.includes( expected );
		case 'not_empty':
			return valueString.trim() !== '';
		case 'empty':
			return valueString.trim() === '';
		default:
			return values.includes( expected );
	}
}

function clearFieldError( field ) {
	if ( ! field ) {
		return;
	}

	field.classList.remove( 'is-invalid', 'laca-cf-field-invalid' );
	field.setAttribute( 'aria-invalid', 'false' );

	const row = field.closest( '.laca-cf-form-row' );
	const error = row?.querySelector( '.laca-cf-field-error' );

	if ( error ) {
		error.textContent = '';
		error.hidden = true;
	}
}

function showFieldError( target, message ) {
	const field = target.matches?.( 'input, select, textarea' )
		? target
		: target.querySelector?.( 'input, select, textarea' );

	if ( field ) {
		field.classList.add( 'is-invalid', 'laca-cf-field-invalid' );
		field.setAttribute( 'aria-invalid', 'true' );
	}

	const row = target.closest?.( '.laca-cf-form-row' ) || field?.closest( '.laca-cf-form-row' );
	const error = row?.querySelector( '.laca-cf-field-error' );

	if ( error ) {
		error.textContent = message;
		error.hidden = false;
	}

	return field || target;
}

function syncConditionalFields( form ) {
	fieldRows( form ).forEach( row => {
		if ( ! row.dataset.conditionField ) {
			return;
		}

		const visible = conditionMatches( form, row );
		row.hidden = ! visible;
		row.classList.toggle( 'laca-cf-conditional-hidden', ! visible );

		row.querySelectorAll( 'input, select, textarea' ).forEach( field => {
			field.disabled = ! visible;

			if ( ! visible ) {
				clearFieldError( field );
			}
		} );
	} );
}

function requiredTargets( panel ) {
	const targets = Array.from( panel.querySelectorAll( '[data-required="true"], [required]' ) );

	return targets.filter( ( target, index ) => {
		if ( target.matches?.( '.laca-cf-radio-group, .laca-cf-checkbox-group' ) ) {
			return true;
		}

		if ( ! target.matches?.( 'input, select, textarea' ) ) {
			return false;
		}

		if ( target.type !== 'radio' && target.type !== 'checkbox' ) {
			return true;
		}

		return targets.findIndex( item => item.name === target.name ) === index;
	} );
}

function targetLabel( target ) {
	const row = target.closest?.( '.laca-cf-form-row' ) || target.querySelector?.( '.laca-cf-form-row' );
	const label = row?.querySelector( '.laca-cf-label' )?.textContent?.replace( '*', '' ).trim();

	return label || 'Trường này';
}

function isTargetEmpty( form, target ) {
	if ( target.matches?.( '.laca-cf-radio-group, .laca-cf-checkbox-group' ) ) {
		return ! target.querySelector( 'input:checked' );
	}

	if ( target.type === 'radio' || target.type === 'checkbox' ) {
		return ! fieldsByName( form, target.name ).some( field => field.checked );
	}

	if ( target.tagName === 'SELECT' && target.multiple ) {
		return target.selectedOptions.length === 0;
	}

	return ! String( target.value || '' ).trim();
}

function isPhoneValid( value ) {
	const normalized = String( value || '' ).trim();
	const digits = normalized.replace( /\D/g, '' );

	return /^\+?[0-9\s().-]+$/.test( normalized ) && digits.length >= 8 && digits.length <= 15;
}

function visibleControls( panel ) {
	return Array.from( panel.querySelectorAll( 'input, select, textarea' ) ).filter( field => {
		const row = field.closest( '.laca-cf-form-row' );

		return field.type !== 'hidden' &&
			! field.disabled &&
			! row?.hidden &&
			! row?.classList.contains( 'laca-cf-conditional-hidden' );
	} );
}

function formatErrorFor( field ) {
	const value = String( field.value || '' ).trim();

	if ( ! value ) {
		return '';
	}

	const label = targetLabel( field );

	if ( field.type === 'email' && ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( value ) ) {
		return `${ label } không hợp lệ.`;
	}

	if ( field.type === 'url' && field.validity && ! field.validity.valid ) {
		return `${ label } không hợp lệ.`;
	}

	if ( field.type === 'tel' && ! isPhoneValid( value ) ) {
		return `${ label } không hợp lệ. Vui lòng nhập tối thiểu 8 chữ số.`;
	}

	if ( field.type === 'number' && ( field.validity?.badInput || Number.isNaN( Number( value ) ) ) ) {
		return `${ label } phải là số hợp lệ.`;
	}

	return '';
}

function validatePanel( form, panel ) {
	syncConditionalFields( form );

	let valid = true;
	let firstInvalid = null;

	panel.querySelectorAll( '.laca-cf-field-error' ).forEach( error => {
		error.textContent = '';
		error.hidden = true;
	} );

	panel.querySelectorAll( '.is-invalid, .laca-cf-field-invalid' ).forEach( field => {
		clearFieldError( field );
	} );

	requiredTargets( panel ).forEach( target => {
		const row = target.closest?.( '.laca-cf-form-row' );

		if ( target.disabled || row?.hidden || row?.classList.contains( 'laca-cf-conditional-hidden' ) ) {
			return;
		}

		if ( isTargetEmpty( form, target ) ) {
			valid = false;
			const invalidField = showFieldError( target, `${ targetLabel( target ) } là bắt buộc.` );

			if ( ! firstInvalid ) {
				firstInvalid = invalidField;
			}
		}
	} );

	visibleControls( panel ).forEach( field => {
		const message = formatErrorFor( field );

		if ( ! message ) {
			return;
		}

		valid = false;
		const invalidField = showFieldError( field, message );

		if ( ! firstInvalid ) {
			firstInvalid = invalidField;
		}
	} );

	if ( firstInvalid?.focus ) {
		firstInvalid.focus( { preventScroll: true } );
		firstInvalid.scrollIntoView( { behavior: 'smooth', block: 'center' } );
	}

	return valid;
}

function showNotice( form, message ) {
	const notice = form.querySelector( '.laca-cf-step-notice' );

	if ( ! notice ) {
		return;
	}

	notice.textContent = message;
	notice.hidden = false;
	notice.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
}

function clearNotice( form ) {
	const notice = form.querySelector( '.laca-cf-step-notice' );

	if ( notice ) {
		notice.textContent = '';
		notice.hidden = true;
	}
}

function setStep( form, panels, step ) {
	const total = panels.length;
	const current = Math.max( 0, Math.min( step, total - 1 ) );

	panels.forEach( ( panel, index ) => {
		const active = index === current;
		panel.hidden = ! active;
		panel.classList.toggle( 'is-active', active );
	} );

	const previousButton = form.querySelector( '.laca-cf-step-btn--prev' );
	const nextButton = form.querySelector( '.laca-cf-step-btn--next' );
	const submitButton = form.querySelector( '.laca-cf-step-btn--submit' );

	if ( previousButton ) {
		previousButton.hidden = current === 0;
	}

	if ( nextButton ) {
		nextButton.hidden = current >= total - 1;
	}

	if ( submitButton ) {
		submitButton.hidden = current < total - 1;
	}

	const fill = form.querySelector( '.laca-cf-step-progress__fill' );
	const progress = form.querySelector( '.laca-cf-step-progress' );
	const pct = Math.round( ( ( current + 1 ) / total ) * 100 );

	if ( fill ) {
		fill.style.width = `${ pct }%`;
	}

	if ( progress ) {
		progress.setAttribute( 'aria-valuenow', String( current + 1 ) );
	}

	form.querySelectorAll( '.laca-cf-step-dot' ).forEach( ( dot, index ) => {
		dot.classList.toggle( 'is-active', index === current );
		dot.classList.toggle( 'is-done', index < current );
	} );

	form.dataset.currentStep = String( current );
	clearNotice( form );
	syncConditionalFields( form );
}

async function submitForm( form ) {
	const submitButton = form.querySelector( '.laca-cf-step-btn--submit' );
	const buttonText = submitButton?.querySelector( '.laca-cf-btn-text' );
	const buttonLoading = submitButton?.querySelector( '.laca-cf-btn-loading' );

	if ( submitButton ) {
		submitButton.disabled = true;
		submitButton.setAttribute( 'aria-busy', 'true' );
	}

	if ( buttonText ) {
		buttonText.hidden = true;
	}

	if ( buttonLoading ) {
		buttonLoading.hidden = false;
	}

	try {
		const response = await fetch( ajaxUrl(), {
			method: 'POST',
			credentials: 'same-origin',
			body: new FormData( form ),
		} );
		const json = await response.json();

		if ( json.success ) {
			if ( window.Swal ) {
				window.Swal.fire( {
					title: 'Thành công',
					text: json.data?.message || 'Cảm ơn bạn đã liên hệ. Chúng tôi sẽ phản hồi sớm nhất.',
					icon: 'success',
					confirmButtonText: 'Đóng',
				} );
			} else {
				showNotice( form, json.data?.message || 'Gửi thành công.' );
			}

			form.reset();
			setStep( form, Array.from( form.querySelectorAll( '.laca-cf-step-panel' ) ), 0 );
		} else {
			showNotice( form, json.data?.message || 'Đã có lỗi xảy ra. Vui lòng thử lại.' );
		}
	} catch {
		showNotice( form, 'Không thể kết nối máy chủ. Vui lòng kiểm tra kết nối và thử lại.' );
	} finally {
		if ( submitButton ) {
			submitButton.disabled = false;
			submitButton.setAttribute( 'aria-busy', 'false' );
		}

		if ( buttonText ) {
			buttonText.hidden = false;
		}

		if ( buttonLoading ) {
			buttonLoading.hidden = true;
		}
	}
}

function initContactFormMultiStep( form ) {
	if ( form.dataset.lacaMultiStepReady === '1' || form.dataset[ BUNDLE_READY_FLAG ] === '1' ) {
		return;
	}

	const panels = Array.from( form.querySelectorAll( '.laca-cf-step-panel' ) );

	if ( panels.length < 2 ) {
		return;
	}

	form.dataset[ BUNDLE_READY_FLAG ] = '1';
	setStep( form, panels, 0 );

	form.addEventListener( 'click', event => {
		const nextButton = event.target.closest( '.laca-cf-step-btn--next' );
		const previousButton = event.target.closest( '.laca-cf-step-btn--prev' );

		if ( nextButton && form.contains( nextButton ) ) {
			event.preventDefault();

			const current = parseInt( form.dataset.currentStep || '0', 10 );

			if ( ! validatePanel( form, panels[ current ] ) ) {
				showNotice( form, 'Vui lòng kiểm tra lại các trường được đánh dấu.' );
				return;
			}

			setStep( form, panels, current + 1 );
		}

		if ( previousButton && form.contains( previousButton ) ) {
			event.preventDefault();
			setStep( form, panels, parseInt( form.dataset.currentStep || '0', 10 ) - 1 );
		}
	} );

	form.querySelectorAll( 'input, select, textarea' ).forEach( field => {
		field.addEventListener( 'input', () => {
			clearFieldError( field );
			syncConditionalFields( form );
		} );
		field.addEventListener( 'change', () => {
			clearFieldError( field );
			syncConditionalFields( form );
		} );
	} );

	form.addEventListener( 'submit', event => {
		event.preventDefault();

		const current = parseInt( form.dataset.currentStep || '0', 10 );

		if ( ! validatePanel( form, panels[ current ] ) ) {
			showNotice( form, 'Vui lòng kiểm tra lại các trường được đánh dấu.' );
			return;
		}

		submitForm( form );
	} );
}

export function initContactFormMultiSteps() {
	document.querySelectorAll( FORM_SELECTOR ).forEach( initContactFormMultiStep );
}
