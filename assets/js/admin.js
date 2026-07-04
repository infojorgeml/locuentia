/* Locuentia admin: language grid live filter on the settings screen. */
document.addEventListener( 'input', function ( event ) {
	var search = event.target;

	if ( ! search.classList || ! search.classList.contains( 'locuentia-language-search' ) ) {
		return;
	}

	var needle = search.value.toLowerCase();

	document.querySelectorAll( '.locuentia-language-option' ).forEach( function ( option ) {
		option.hidden = '' !== needle && -1 === option.textContent.toLowerCase().indexOf( needle );
	} );
} );

/* Locuentia admin: apply translation-memory suggestions to empty fields. */
document.addEventListener( 'click', function ( event ) {
	var apply = event.target.closest ? event.target.closest( '.locuentia-apply' ) : null;

	if ( apply && undefined !== apply.dataset.locuentiaSuggestion ) {
		var cell     = apply.closest( 'td' );
		var textarea = cell ? cell.querySelector( 'textarea' ) : null;

		if ( textarea && '' === textarea.value ) {
			textarea.value = apply.dataset.locuentiaSuggestion;
		}

		return;
	}

	var applyAll = event.target.closest ? event.target.closest( '.locuentia-apply-all' ) : null;

	if ( applyAll ) {
		document.querySelectorAll( '.locuentia-apply' ).forEach( function ( button ) {
			button.click();
		} );
	}
} );
