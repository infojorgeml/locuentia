/* Selector de idioma de Locuentia en modo dropdown: navega al cambiar. */
document.addEventListener( 'change', function ( event ) {
	var select = event.target;

	if ( select && select.classList && select.classList.contains( 'locuentia-switcher--dropdown' ) && select.value ) {
		window.location.href = select.value;
	}
} );
