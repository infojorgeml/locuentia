/* Locuentia language switcher in dropdown mode: navigate on change. */
document.addEventListener( 'change', function ( event ) {
	var select = event.target;

	if ( select && select.classList && select.classList.contains( 'locuentia-switcher--dropdown' ) && select.value ) {
		window.location.href = select.value;
	}
} );
