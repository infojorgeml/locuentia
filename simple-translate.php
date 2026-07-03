<?php
/**
 * Plugin Name:       Simple Translate
 * Plugin URI:        https://github.com/infojorgeml/simple-translate
 * Description:       Traducción manual mínima: detecta los textos de entradas y páginas, muestra campos para traducirlos en el editor y sirve la traducción añadiendo ?lang=xx a la URL.
 * Version:           0.0.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Jorge Muñoz
 * License:           GPL-2.0-or-later
 * Text Domain:       simple-translate
 * Update URI:        false
 */

defined( 'ABSPATH' ) || exit;

define( 'SIMPLE_TRANSLATE_VERSION', '0.0.1' );
define( 'SIMPLE_TRANSLATE_DIR', plugin_dir_path( __FILE__ ) );

require_once SIMPLE_TRANSLATE_DIR . 'includes/class-simple-translate-detector.php';

final class Simple_Translate {

	const OPTION_LANGUAGES = 'simple_translate_languages';
	const META_KEY         = '_simple_translate_translations';

	public static function init() {
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
	 * Idiomas de destino configurados, p. ej. array( 'en', 'fr' ).
	 *
	 * @return string[]
	 */
	public static function get_languages() {
		$raw   = get_option( self::OPTION_LANGUAGES, 'en' );
		$codes = array();

		foreach ( explode( ',', (string) $raw ) as $part ) {
			$code = self::sanitize_language_code( $part );
			if ( '' !== $code ) {
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
}

add_action( 'plugins_loaded', array( 'Simple_Translate', 'init' ) );
