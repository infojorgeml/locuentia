<?php
/**
 * Plugin Name:       Simple Translate
 * Plugin URI:        https://github.com/infojorgeml/simple-translate
 * Description:       Traducción manual mínima: detecta los textos de entradas y páginas, muestra campos para traducirlos en el editor y sirve la traducción en URLs con prefijo de idioma (/en/pagina/) o con ?lang=xx.
 * Version:           0.0.3
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Jorge Muñoz
 * License:           GPL-2.0-or-later
 * Text Domain:       simple-translate
 * Update URI:        false
 */

defined( 'ABSPATH' ) || exit;

define( 'SIMPLE_TRANSLATE_VERSION', '0.0.3' );
define( 'SIMPLE_TRANSLATE_DIR', plugin_dir_path( __FILE__ ) );

require_once SIMPLE_TRANSLATE_DIR . 'includes/class-simple-translate-detector.php';
require_once SIMPLE_TRANSLATE_DIR . 'includes/class-simple-translate-router.php';

final class Simple_Translate {

	const OPTION_LANGUAGES = 'simple_translate_languages';
	const OPTION_SOURCE    = 'simple_translate_source_language';
	const META_KEY         = '_simple_translate_translations';

	public static function init() {
		Simple_Translate_Router::init();

		if ( is_admin() ) {
			require_once SIMPLE_TRANSLATE_DIR . 'includes/class-simple-translate-admin.php';
			Simple_Translate_Admin::init();
		} else {
			require_once SIMPLE_TRANSLATE_DIR . 'includes/class-simple-translate-frontend.php';
			Simple_Translate_Frontend::init();
		}
	}

	/**
	 * Tipos de contenido donde se ofrecen traducciones.
	 *
	 * @return string[]
	 */
	public static function post_types() {
		return apply_filters( 'simple_translate_post_types', array( 'post', 'page' ) );
	}

	/**
	 * Idioma del contenido original (código, p. ej. 'es').
	 *
	 * Configurable en ajustes; por defecto se deriva del locale del sitio,
	 * que no siempre coincide con el idioma en que está escrito el contenido.
	 *
	 * @return string
	 */
	public static function original_language() {
		$code = self::sanitize_language_code( get_option( self::OPTION_SOURCE, '' ) );

		if ( '' === $code ) {
			$code = self::sanitize_language_code( strtok( strtolower( get_locale() ), '_' ) );
		}

		return '' === $code ? 'en' : $code;
	}

	/**
	 * Idiomas de destino configurados, p. ej. array( 'en', 'fr' ).
	 *
	 * Excluye el idioma original: su contenido ya vive en las URLs sin
	 * prefijo, y duplicarlo bajo /xx/ generaría contenido clonado y
	 * etiquetas hreflang repetidas.
	 *
	 * @return string[]
	 */
	public static function get_languages() {
		$raw      = get_option( self::OPTION_LANGUAGES, 'en' );
		$original = self::original_language();
		$codes    = array();

		foreach ( explode( ',', (string) $raw ) as $part ) {
			$code = self::sanitize_language_code( $part );
			if ( '' !== $code && $code !== $original ) {
				$codes[ $code ] = $code;
			}
		}

		return array_values( $codes );
	}

	/**
	 * Normaliza un código de idioma (en, pt-br, …). Devuelve '' si no es válido.
	 *
	 * @param string $code Código propuesto.
	 * @return string
	 */
	public static function sanitize_language_code( $code ) {
		$code = strtolower( trim( (string) $code ) );
		$code = preg_replace( '/[^a-z0-9_\-]/', '', $code );

		$length = strlen( $code );
		if ( $length < 2 || $length > 10 ) {
			return '';
		}

		return $code;
	}

	/**
	 * Traducciones guardadas de un post para un idioma: array( hash => texto ).
	 *
	 * @param int    $post_id ID del post.
	 * @param string $lang    Código de idioma.
	 * @return array
	 */
	public static function get_post_translations( $post_id, $lang ) {
		$data = get_post_meta( $post_id, self::META_KEY, true );

		if ( ! is_array( $data ) || empty( $data[ $lang ] ) || ! is_array( $data[ $lang ] ) ) {
			return array();
		}

		return $data[ $lang ];
	}

	/**
	 * Al activar: programa la regeneración de las reglas de reescritura para
	 * la próxima carga, cuando el plugin ya esté inicializado.
	 */
	public static function activate() {
		update_option( Simple_Translate_Router::FLUSH_FLAG, 1 );
	}

	/**
	 * Al desactivar: regenera las reglas sin las variantes de idioma.
	 */
	public static function deactivate() {
		remove_filter( 'rewrite_rules_array', array( 'Simple_Translate_Router', 'add_language_rules' ) );
		flush_rewrite_rules();
		delete_option( Simple_Translate_Router::FLUSH_FLAG );
	}
}

add_action( 'plugins_loaded', array( 'Simple_Translate', 'init' ) );

register_activation_hook( __FILE__, array( 'Simple_Translate', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Simple_Translate', 'deactivate' ) );
