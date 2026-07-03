<?php
/**
 * Limpieza al desinstalar: elimina la opción y todas las traducciones guardadas.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'simple_translate_languages' );
delete_post_meta_by_key( '_simple_translate_translations' );
