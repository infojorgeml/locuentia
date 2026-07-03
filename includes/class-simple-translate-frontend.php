<?php
/**
 * Frontend: sirve las traducciones cuando la URL lleva ?lang=xx.
 */

defined( 'ABSPATH' ) || exit;

class Simple_Translate_Frontend {

	public static function init() {
		// Prioridad 1 en the_title: hay que comparar el título antes de que
		// wptexturize lo retoque, que es como se guardó su hash en el editor.
		add_filter( 'the_title', array( __CLASS__, 'filter_title' ), 1, 2 );
		add_filter( 'single_post_title', array( __CLASS__, 'filter_title' ), 1, 2 );
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 20 );
		add_shortcode( 'simple_translate_switcher', array( __CLASS__, 'render_switcher' ) );
	}

	/**
	 * Idioma pedido vía ?lang=xx, validado contra los configurados.
	 *
	 * @return string Código de idioma o '' para el texto original.
	 */
	public static function current_language() {
		static $cached = null;

		if ( null !== $cached ) {
			return $cached;
		}

		$cached = '';

		// Lectura pública sin nonce: solo decide en qué idioma se muestra la página.
		if ( isset( $_GET['lang'] ) && is_string( $_GET['lang'] ) ) {
			$requested = Simple_Translate::sanitize_language_code( sanitize_key( wp_unslash( $_GET['lang'] ) ) );
			if ( '' !== $requested && in_array( $requested, Simple_Translate::get_languages(), true ) ) {
				$cached = $requested;
			}
		}

		return $cached;
	}

	/**
	 * Traduce el título si existe traducción para él.
	 *
	 * @param string      $title Título original.
	 * @param int|WP_Post $post  ID u objeto del post (según el filtro; no siempre disponible).
	 * @return string
	 */
	public static function filter_title( $title, $post = 0 ) {
		$lang = self::current_language();
		if ( '' === $lang || ! $post ) {
			return $title;
		}

		$post = get_post( $post );
		if ( ! $post || ! in_array( $post->post_type, Simple_Translate::post_types(), true ) ) {
			return $title;
		}

		$map = Simple_Translate::get_post_translations( $post->ID, $lang );
		if ( empty( $map ) ) {
			return $title;
		}

		$hash = Simple_Translate_Detector::hash_text( $title );

		return isset( $map[ $hash ] ) ? $map[ $hash ] : $title;
	}

	/**
	 * Traduce los textos del contenido que tengan traducción guardada.
	 *
	 * @param string $content Contenido ya renderizado.
	 * @return string
	 */
	public static function filter_content( $content ) {
		$lang = self::current_language();
		if ( '' === $lang ) {
			return $content;
		}

		$post = get_post();
		if ( ! $post || ! in_array( $post->post_type, Simple_Translate::post_types(), true ) ) {
			return $content;
		}

		$map = Simple_Translate::get_post_translations( $post->ID, $lang );
		if ( empty( $map ) ) {
			return $content;
		}

		return Simple_Translate_Detector::translate_html( $content, $map );
	}

	/**
	 * Shortcode opcional [simple_translate_switcher]: lista de enlaces de idioma.
	 *
	 * @return string
	 */
	public static function render_switcher() {
		$languages = Simple_Translate::get_languages();
		if ( empty( $languages ) ) {
			return '';
		}

		$current = self::current_language();

		$items = '<li><a href="' . esc_url( remove_query_arg( 'lang' ) ) . '"'
			. ( '' === $current ? ' style="font-weight:bold"' : '' ) . '>'
			. esc_html__( 'Original', 'simple-translate' )
			. '</a></li>';

		foreach ( $languages as $lang ) {
			$items .= '<li><a href="' . esc_url( add_query_arg( 'lang', $lang ) ) . '"'
				. ( $lang === $current ? ' style="font-weight:bold"' : '' ) . '>'
				. esc_html( strtoupper( $lang ) )
				. '</a></li>';
		}

		return '<ul class="simple-translate-switcher">' . $items . '</ul>';
	}
}
