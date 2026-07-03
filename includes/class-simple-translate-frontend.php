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
			$post = get_post();
			$urls = array();

			foreach ( $languages as $lang ) {
				if ( ! empty( Simple_Translate::get_post_translations( $post->ID, $lang ) ) ) {
					$urls[ $lang ] = Simple_Translate_Router::permalink_for_language( $post, $lang );
				}
			}

			$base = Simple_Translate_Router::permalink_for_language( $post, '' );
		} else {
			$base = Simple_Translate_Router::current_url_unlocalized( false );
			$urls = array();

			foreach ( $languages as $lang ) {
				$urls[ $lang ] = Simple_Translate_Router::localize_url( $base, $lang );
			}
		}

		if ( empty( $urls ) || '' === $base ) {
			return;
		}

		$original = Simple_Translate::original_language();

		printf(
			'<link rel="alternate" hreflang="%s" href="%s" />' . "\n",
			esc_attr( $original ),
			esc_url( $base )
		);

		foreach ( $urls as $lang => $url ) {
			printf(
				'<link rel="alternate" hreflang="%s" href="%s" />' . "\n",
				esc_attr( $lang ),
				esc_url( $url )
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

		// En contenido individual se usan los permalinks (con slug traducido);
		// en el resto de vistas, la URL actual sin idioma.
		$post = is_singular( Simple_Translate::post_types() ) ? get_post() : null;
		$base = $post
			? Simple_Translate_Router::permalink_for_language( $post, '' )
			: Simple_Translate_Router::current_url_unlocalized();

		$items = '<li><a href="' . esc_url( $base ) . '"'
			. ( '' === $current ? ' style="font-weight:bold"' : '' ) . '>'
			. esc_html__( 'Original', 'simple-translate' )
			. '</a></li>';

		foreach ( $languages as $lang ) {
			$url = $post
				? Simple_Translate_Router::permalink_for_language( $post, $lang )
				: Simple_Translate_Router::localize_url( $base, $lang );

			$items .= '<li><a href="' . esc_url( $url ) . '"'
				. ( $lang === $current ? ' style="font-weight:bold"' : '' ) . '>'
				. esc_html( strtoupper( $lang ) )
				. '</a></li>';
		}

		return '<ul class="simple-translate-switcher">' . $items . '</ul>';
	}
}
