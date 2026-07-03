<?php
/**
 * Frontend: sirve las traducciones cuando la URL lleva /xx/ o ?lang=xx.
 */

defined( 'ABSPATH' ) || exit;

class Simple_Translate_Frontend {

	public static function init() {
		// Prioridad 1 en the_title: hay que comparar el título antes de que
		// wptexturize lo retoque, que es como se guardó su hash en el editor.
		add_filter( 'the_title', array( __CLASS__, 'filter_title' ), 1, 2 );
		add_filter( 'single_post_title', array( __CLASS__, 'filter_title' ), 1, 2 );
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 20 );

		// Los enlaces internos conservan el idioma activo al navegar.
		add_filter( 'post_link', array( __CLASS__, 'localize_permalink' ) );
		add_filter( 'page_link', array( __CLASS__, 'localize_permalink' ) );
		add_filter( 'post_type_link', array( __CLASS__, 'localize_permalink' ) );
		add_filter( 'term_link', array( __CLASS__, 'localize_permalink' ) );

		// La redirección canónica de WordPress no conoce el prefijo y sacaría
		// al visitante del idioma (p. ej. /en/ → /); se lo reponemos.
		add_filter( 'redirect_canonical', array( __CLASS__, 'localize_canonical' ), 10, 2 );

		add_action( 'wp_head', array( __CLASS__, 'print_hreflang' ), 2 );

		add_shortcode( 'simple_translate_switcher', array( __CLASS__, 'render_switcher' ) );
	}

	/**
	 * Etiquetas hreflang: anuncia a los buscadores todas las versiones de
	 * idioma de la URL actual, incluida la propia.
	 */
	public static function print_hreflang() {
		if ( is_404() || is_search() || is_preview() || is_embed() ) {
			return;
		}

		$languages = Simple_Translate::get_languages();
		if ( empty( $languages ) ) {
			return;
		}

		// En contenido individual solo se anuncian los idiomas con alguna
		// traducción guardada; anunciar una "versión en inglés" idéntica al
		// original sería una señal falsa para los buscadores.
		if ( is_singular( Simple_Translate::post_types() ) ) {
			$post    = get_post();
			$targets = array();

			foreach ( $languages as $lang ) {
				if ( ! empty( Simple_Translate::get_post_translations( $post->ID, $lang ) ) ) {
					$targets[] = $lang;
				}
			}
		} else {
			$targets = $languages;
		}

		if ( empty( $targets ) ) {
			return;
		}

		$original = Simple_Translate::original_language();
		$base     = Simple_Translate_Router::current_url_unlocalized( false );

		printf(
			'<link rel="alternate" hreflang="%s" href="%s" />' . "\n",
			esc_attr( $original ),
			esc_url( $base )
		);

		foreach ( $targets as $lang ) {
			printf(
				'<link rel="alternate" hreflang="%s" href="%s" />' . "\n",
				esc_attr( $lang ),
				esc_url( Simple_Translate_Router::localize_url( $base, $lang ) )
			);
		}

		printf(
			'<link rel="alternate" hreflang="x-default" href="%s" />' . "\n",
			esc_url( $base )
		);
	}

	/**
	 * Traduce el título si existe traducción para él.
	 *
	 * @param string      $title Título original.
	 * @param int|WP_Post $post  ID u objeto del post (según el filtro; no siempre disponible).
	 * @return string
	 */
	public static function filter_title( $title, $post = 0 ) {
		$lang = Simple_Translate_Router::current_language();
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
		$lang = Simple_Translate_Router::current_language();
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
	 * Prefija los permalinks con el idioma activo.
	 *
	 * @param string $url Permalink original.
	 * @return string
	 */
	public static function localize_permalink( $url ) {
		$lang = Simple_Translate_Router::current_language();

		return '' === $lang ? $url : Simple_Translate_Router::localize_url( $url, $lang );
	}

	/**
	 * Mantiene el prefijo de idioma en las redirecciones canónicas.
	 *
	 * @param string|false $redirect_url  URL canónica propuesta.
	 * @param string       $requested_url URL solicitada.
	 * @return string|false
	 */
	public static function localize_canonical( $redirect_url, $requested_url ) {
		$lang = Simple_Translate_Router::current_language();
		if ( '' === $lang || ! $redirect_url ) {
			return $redirect_url;
		}

		return Simple_Translate_Router::localize_url( $redirect_url, $lang );
	}

	/**
	 * Shortcode opcional [simple_translate_switcher]: lista de enlaces de idioma
	 * a la página actual.
	 *
	 * @return string
	 */
	public static function render_switcher() {
		$languages = Simple_Translate::get_languages();
		if ( empty( $languages ) ) {
			return '';
		}

		$current = Simple_Translate_Router::current_language();
		$base    = Simple_Translate_Router::current_url_unlocalized();

		$items = '<li><a href="' . esc_url( $base ) . '"'
			. ( '' === $current ? ' style="font-weight:bold"' : '' ) . '>'
			. esc_html__( 'Original', 'simple-translate' )
			. '</a></li>';

		foreach ( $languages as $lang ) {
			$items .= '<li><a href="' . esc_url( Simple_Translate_Router::localize_url( $base, $lang ) ) . '"'
				. ( $lang === $current ? ' style="font-weight:bold"' : '' ) . '>'
				. esc_html( strtoupper( $lang ) )
				. '</a></li>';
		}

		return '<ul class="simple-translate-switcher">' . $items . '</ul>';
	}
}
