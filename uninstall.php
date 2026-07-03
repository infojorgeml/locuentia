<?php
/**
 * Limpieza al desinstalar: elimina la opción y todas las traducciones guardadas.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'locuentia_languages' );
delete_option( 'locuentia_source_language' );
delete_option( 'locuentia_flush_rewrite' );
delete_post_meta_by_key( '_locuentia_translations' );

// Slugs traducidos: un meta por idioma (_locuentia_slug_en, …),
// incluidos los de idiomas que ya no estén configurados. Borrado masivo
// único en desinstalación; no hay API de alto nivel para LIKE ni caché
// que invalidar después.
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( '_locuentia_slug_' ) . '%'
	)
);
