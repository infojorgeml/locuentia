<?php
/**
 * Frontend: sirve las traducciones cuando la URL lleva /xx/ o ?locuentia_lang=xx.
 */

defined( 'ABSPATH' ) || exit;

class Locuentia_Frontend {

	public static function init() {
		// Prioridad 1 en the_title: hay que comparar el título antes de que
		// wptexturize lo retoque, que es como se guardó su hash en el editor.
		add_filter( 'the_title', array( __CLASS__, 'filter_title' ), 1, 2 );
		add_filter( 'single_post_title', array( __CLASS__, 'filter_title' ), 1, 2 );
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 20 );

		// Prioridad 5: antes de que wp_trim_excerpt (10) genere el extracto
		// automático. El manual se traduce por hash; el automático se recorta
		// del contenido, que ya pasa por filter_content.
		add_filter( 'get_the_excerpt', array( __CLASS__, 'filter_excerpt' ), 5, 2 );

		add_action( 'wp_head', array( __CLASS__, 'print_hreflang' ), 2 );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_shortcode( 'locuentia_switcher', array( __CLASS__, 'render_switcher' ) );
	}

	/**
	 * Registra la hoja del selector; solo se encola si el shortcode se usa.
	 */
	public static function register_assets() {
		wp_register_style(
			'locuentia-switcher',
			LOCUENTIA_URL . 'assets/css/switcher.css',
			array(),
			LOCUENTIA_VERSION
		);
	}

	/**
	 * Etiquetas hreflang: anuncia a los buscadores todas las versiones de
	 * idioma de la URL actual, incluida la propia.
	 */
	public static function print_hreflang() {
		if ( is_404() || is_search() || is_preview() || is_embed() ) {
			return;
		}

		$languages = Locuentia::get_languages();
		if ( empty( $languages ) ) {
			return;
		}

		// En contenido individual solo se anuncian los idiomas con alguna
		// traducción guardada; anunciar una "versión en inglés" idéntica al
		// original sería una señal falsa para los buscadores.
		if ( is_singular( Locuentia::post_types() ) ) {
			$post = get_post();
			$urls = array();

			foreach ( $languages as $lang ) {
				if ( ! empty( Locuentia::get_post_translations( $post->ID, $lang ) ) ) {
					$urls[ $lang ] = Locuentia_Router::permalink_for_language( $post, $lang );
				}
			}

			$base = Locuentia_Router::permalink_for_language( $post, '' );
		} else {
			$base = Locuentia_Router::current_url_unlocalized( false );
			$urls = array();

			// En vistas de listado solo se anuncian idiomas con alguna
			// traducción en el sitio; los vacíos mostrarían el original.
			foreach ( Locuentia::languages_in_use() as $lang ) {
				$urls[ $lang ] = Locuentia_Router::localize_url( $base, $lang );
			}
		}

		if ( empty( $urls ) || '' === $base ) {
			return;
		}

		$original = Locuentia::original_language();

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
		$lang = Locuentia_Router::current_language();
		if ( '' === $lang || ! $post ) {
			return $title;
		}

		$post = get_post( $post );
		if ( ! $post || ! in_array( $post->post_type, Locuentia::post_types(), true ) ) {
			return $title;
		}

		$map = Locuentia::get_post_translations( $post->ID, $lang );
		if ( empty( $map ) ) {
			return $title;
		}

		$hash = Locuentia_Detector::hash_text( $title );

		return isset( $map[ $hash ] ) ? $map[ $hash ] : $title;
	}

	/**
	 * Traduce el extracto manual si existe traducción para él.
	 *
	 * @param string       $excerpt Extracto (vacío si el post no tiene manual).
	 * @param WP_Post|null $post    Post del extracto.
	 * @return string
	 */
	public static function filter_excerpt( $excerpt, $post = null ) {
		$lang = Locuentia_Router::current_language();
		if ( '' === $lang || '' === (string) $excerpt ) {
			return $excerpt;
		}

		$post = get_post( $post );
		if ( ! $post || ! in_array( $post->post_type, Locuentia::post_types(), true ) ) {
			return $excerpt;
		}

		$map = Locuentia::get_post_translations( $post->ID, $lang );
		if ( empty( $map ) ) {
			return $excerpt;
		}

		$hash = Locuentia_Detector::hash_text( $excerpt );

		return isset( $map[ $hash ] ) ? $map[ $hash ] : $excerpt;
	}

	/**
	 * Traduce los textos del contenido que tengan traducción guardada.
	 *
	 * @param string $content Contenido ya renderizado.
	 * @return string
	 */
	public static function filter_content( $content ) {
		$lang = Locuentia_Router::current_language();
		if ( '' === $lang ) {
			return $content;
		}

		$post = get_post();
		if ( ! $post || ! in_array( $post->post_type, Locuentia::post_types(), true ) ) {
			return $content;
		}

		$map = Locuentia::get_post_translations( $post->ID, $lang );
		if ( empty( $map ) ) {
			return $content;
		}

		return Locuentia_Detector::translate_html( $content, $map );
	}

	/**
	 * Shortcode opcional [locuentia_switcher]: lista de enlaces de idioma
	 * a la página actual.
	 *
	 * @return string
	 */
	public static function render_switcher() {
		$languages = Locuentia::get_languages();
		if ( empty( $languages ) ) {
			return '';
		}

		wp_enqueue_style( 'locuentia-switcher' );

		$current = Locuentia_Router::current_language();

		// En contenido individual se usan los permalinks (con slug traducido);
		// en el resto de vistas, la URL actual sin idioma.
		$post = is_singular( Locuentia::post_types() ) ? get_post() : null;
		$base = $post
			? Locuentia_Router::permalink_for_language( $post, '' )
			: Locuentia_Router::current_url_unlocalized();

		$items = '<li><a href="' . esc_url( $base ) . '"'
			. ( '' === $current ? ' class="locuentia-current"' : '' ) . '>'
			. esc_html__( 'Original', 'locuentia' )
			. '</a></li>';

		foreach ( $languages as $lang ) {
			$url = $post
				? Locuentia_Router::permalink_for_language( $post, $lang )
				: Locuentia_Router::localize_url( $base, $lang );

			$items .= '<li><a href="' . esc_url( $url ) . '"'
				. ( $lang === $current ? ' class="locuentia-current"' : '' ) . '>'
				. esc_html( strtoupper( $lang ) )
				. '</a></li>';
		}

		return '<ul class="locuentia-switcher">' . $items . '</ul>';
	}
}
