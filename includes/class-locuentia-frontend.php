<?php
/**
 * Front end: serves the translations when the URL carries /xx/ or ?locuentia_lang=xx.
 */

defined( 'ABSPATH' ) || exit;

class Locuentia_Frontend {

	public static function init() {
		// Priority 1 on the_title: the title must be compared before
		// wptexturize touches it, which is how its hash was stored in the editor.
		add_filter( 'the_title', array( __CLASS__, 'filter_title' ), 1, 2 );
		add_filter( 'single_post_title', array( __CLASS__, 'filter_title' ), 1, 2 );
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 20 );

		// Priority 5: before wp_trim_excerpt (10) generates the automatic
		// excerpt. Manual excerpts are translated by hash; automatic ones are
		// trimmed from the content, which already goes through filter_content.
		add_filter( 'get_the_excerpt', array( __CLASS__, 'filter_excerpt' ), 5, 2 );

		add_action( 'wp_head', array( __CLASS__, 'print_hreflang' ), 2 );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_shortcode( 'locuentia_switcher', array( __CLASS__, 'render_switcher' ) );
	}

	/**
	 * Registers the switcher assets; they are only enqueued when the
	 * shortcode is used.
	 */
	public static function register_assets() {
		wp_register_style(
			'locuentia-switcher',
			LOCUENTIA_URL . 'assets/css/switcher.css',
			array(),
			LOCUENTIA_VERSION
		);

		wp_register_script(
			'locuentia-switcher',
			LOCUENTIA_URL . 'assets/js/switcher.js',
			array(),
			LOCUENTIA_VERSION,
			true
		);
	}

	/**
	 * hreflang tags: announce to search engines every language version of
	 * the current URL, including its own.
	 */
	public static function print_hreflang() {
		if ( is_404() || is_search() || is_preview() || is_embed() ) {
			return;
		}

		$languages = Locuentia::get_languages();
		if ( empty( $languages ) ) {
			return;
		}

		// On singular content only languages with a stored translation are
		// announced; announcing an "English version" identical to the
		// original would be a false signal to search engines.
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

			// On listing views only languages with at least one translation
			// on the site are announced; empty ones would show the original.
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
	 * Translates the title when a translation exists for it.
	 *
	 * @param string      $title Original title.
	 * @param int|WP_Post $post  Post ID or object (depending on the filter; not always available).
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
	 * Translates the manual excerpt when a translation exists for it.
	 *
	 * @param string       $excerpt Excerpt (empty when the post has no manual one).
	 * @param WP_Post|null $post    Post of the excerpt.
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
	 * Translates the content texts that have a stored translation.
	 *
	 * @param string $content Rendered content.
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
	 * [locuentia_switcher] shortcode: language switcher for the current page.
	 *
	 * Attributes:
	 * - style:          list (default) | inline | dropdown
	 * - show:           name (native name, default) | code (EN, ES…)
	 * - hide_current:   yes to hide the language being viewed
	 * - separator:      text between items in list/inline (e.g. "|")
	 * - original_label: custom label for the original language
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_switcher( $atts = array() ) {
		$languages = Locuentia::get_languages();
		if ( empty( $languages ) ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'style'          => 'list',
				'show'           => 'name',
				'hide_current'   => 'no',
				'separator'      => '',
				'original_label' => '',
			),
			$atts,
			'locuentia_switcher'
		);

		$style        = in_array( $atts['style'], array( 'list', 'inline', 'dropdown' ), true ) ? $atts['style'] : 'list';
		$show         = 'code' === $atts['show'] ? 'code' : 'name';
		$hide_current = in_array( strtolower( (string) $atts['hide_current'] ), array( 'yes', '1', 'true' ), true );

		$current = Locuentia_Router::current_language();

		// On singular content permalinks are used (with the translated slug);
		// on any other view, the current URL without its language.
		$post = is_singular( Locuentia::post_types() ) ? get_post() : null;
		$base = $post
			? Locuentia_Router::permalink_for_language( $post, '' )
			: Locuentia_Router::current_url_unlocalized();

		$original_label = '' !== trim( (string) $atts['original_label'] )
			? trim( (string) $atts['original_label'] )
			: Locuentia::language_label( Locuentia::original_language(), $show );

		$items = array(
			array(
				'label'   => $original_label,
				'url'     => $base,
				'current' => '' === $current,
			),
		);

		foreach ( $languages as $lang ) {
			$items[] = array(
				'label'   => Locuentia::language_label( $lang, $show ),
				'url'     => $post
					? Locuentia_Router::permalink_for_language( $post, $lang )
					: Locuentia_Router::localize_url( $base, $lang ),
				'current' => $lang === $current,
			);
		}

		if ( $hide_current ) {
			$items = array_values(
				array_filter(
					$items,
					function ( $item ) {
						return ! $item['current'];
					}
				)
			);
		}

		if ( empty( $items ) ) {
			return '';
		}

		wp_enqueue_style( 'locuentia-switcher' );

		if ( 'dropdown' === $style ) {
			wp_enqueue_script( 'locuentia-switcher' );

			$out = '<select class="locuentia-switcher locuentia-switcher--dropdown" aria-label="'
				. esc_attr__( 'Select language', 'locuentia' ) . '">';

			foreach ( $items as $item ) {
				$out .= '<option value="' . esc_url( $item['url'] ) . '"'
					. selected( $item['current'], true, false ) . '>'
					. esc_html( $item['label'] )
					. '</option>';
			}

			return $out . '</select>';
		}

		$separator = trim( (string) $atts['separator'] );
		$sep_html  = '' !== $separator
			? '<li class="locuentia-switcher-sep" aria-hidden="true">' . esc_html( $separator ) . '</li>'
			: '';

		$lis = array();
		foreach ( $items as $item ) {
			$lis[] = '<li><a href="' . esc_url( $item['url'] ) . '"'
				. ( $item['current'] ? ' class="locuentia-current"' : '' ) . '>'
				. esc_html( $item['label'] )
				. '</a></li>';
		}

		return '<ul class="locuentia-switcher locuentia-switcher--' . esc_attr( $style ) . '">'
			. implode( $sep_html, $lis )
			. '</ul>';
	}
}
