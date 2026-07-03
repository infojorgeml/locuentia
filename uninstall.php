<?php
/**
 * Limpieza al desinstalar: elimina la opción y todas las traducciones guardadas.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'simple_translate_languages' );
delete_option( 'simple_translate_source_language' );
delete_option( 'simple_translate_flush_rewrite' );
delete_post_meta_by_key( '_simple_translate_translations' );

// Slugs traducidos: un meta por idioma (_simple_translate_slug_en, …),
// incluidos los de idiomas que ya no estén configurados.
global $wpdb;
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( '_simple_translate_slug_' ) . '%'
	)
);
