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

		// Covers images rendered via wp_get_attachment_image (featured image,
		// dynamic galleries); in-content <img> tags are handled by the detector.
		add_filter( 'wp_get_attachment_image_attributes', array( __CLASS__, 'filter_image_attributes' ) );

		// Serves configured meta values translated (SEO titles/descriptions…),
		// including strings printed in the <head>, which the full-page pass
		// deliberately does not touch.
		add_filter( 'get_post_metadata', array( __CLASS__, 'filter_meta' ), 10, 4 );

		add_action( 'wp_head', array( __CLASS__, 'print_hreflang' ), 2 );

		// Full-page pass: translates the final served HTML, covering output
		// that never goes through the_content (page builders like Bricks,
		// menus, widgets, theme texts). The content filters above still run
		// first for per-post precision and for feeds.
		add_action( 'template_redirect', array( __CLASS__, 'start_page_buffer' ), 1 );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_shortcode( 'locuentia_switcher', array( __CLASS__, 'render_switcher' ) );
	}

	/**
	 * Starts buffering the page output on language URLs so the final HTML
	 * document can be translated as a whole.
	 */
	public static function start_page_buffer() {
		if ( '' === Locuentia_Router::current_language() ) {
			return;
		}

		if ( is_feed() || is_robots() || is_embed() || is_preview() ) {
			return;
		}

		if ( get_query_var( 'sitemap' ) || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		ob_start( array( __CLASS__, 'translate_page_output' ) );
	}

	/**
	 * Output buffer callback: translates the full HTML document using the
	 * current post map plus the site-wide map.
	 *
	 * @param string $html Buffered page output.
	 * @return string
	 */
	public static function translate_page_output( $html ) {
		if ( ! is_string( $html ) || '' === $html || false === stripos( $html, '<html' ) ) {
			return $html;
		}

		try {
			$lang = Locuentia_Router::current_language();
			if ( '' === $lang ) {
				return $html;
			}

			$map = Locuentia::get_site_translations( $lang );

			$post = is_singular( Locuentia::post_types() ) ? get_post() : null;
			if ( $post ) {
				// Per-post translations win over site-wide ones.
				$map = Locuentia::get_post_translations( $post->ID, $lang ) + $map;
			}

			if ( empty( $map ) ) {
				return $html;
			}

			return Locuentia_Detector::translate_document( $html, $map );
		} catch ( \Throwable $e ) {
			return $html;
		}
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
	 * Translates the alt attribute of attachment images (featured image,
	 * dynamic galleries) using the translations of the post being displayed.
	 *
	 * @param array $attr Image attributes.
	 * @return array
	 */
	public static function filter_image_attributes( $attr ) {
		$lang = Locuentia_Router::current_language();
		if ( '' === $lang || ! is_array( $attr ) || empty( $attr['alt'] ) ) {
			return $attr;
		}

		$post = get_post();
		if ( ! $post || ! in_array( $post->post_type, Locuentia::post_types(), true ) ) {
			return $attr;
		}

		$map = Locuentia::get_post_translations( $post->ID, $lang );
		if ( empty( $map ) ) {
			return $attr;
		}

		$hash = Locuentia_Detector::hash_text( $attr['alt'] );
		if ( isset( $map[ $hash ] ) ) {
			$attr['alt'] = $map[ $hash ];
		}

		return $attr;
	}

	/**
	 * Serves configured translatable meta values translated.
	 *
	 * @param mixed  $value     Short-circuit value (null = not short-circuited).
	 * @param int    $object_id Post ID.
	 * @param string $meta_key  Requested meta key.
	 * @param bool   $single    Whether a single value was requested.
	 * @return mixed
	 */
	public static function filter_meta( $value, $object_id, $meta_key, $single ) {
		static $busy = false;

		if ( $busy || null !== $value || '' === (string) $meta_key ) {
			return $value;
		}

		$lang = Locuentia_Router::current_language();
		if ( '' === $lang ) {
			return $value;
		}

		$map_keys = Locuentia::meta_key_map();
		if ( ! isset( $map_keys[ $meta_key ] ) ) {
			return $value;
		}

		$post = get_post( $object_id );
		if ( ! $post || ! in_array( $post->post_type, Locuentia::post_types(), true ) ) {
			return $value;
		}

		$translations = Locuentia::get_post_translations( $object_id, $lang ) + Locuentia::get_site_translations( $lang );
		if ( empty( $translations ) ) {
			return $value;
		}

		// Fetch the real values with the filter disabled to avoid recursion.
		$busy = true;
		$raw  = get_post_meta( $object_id, $meta_key );
		$busy = false;

		if ( ! is_array( $raw ) || empty( $raw ) ) {
			return $value;
		}

		$spec       = $map_keys[ $meta_key ];
		$translated = array();

		foreach ( $raw as $item ) {
			if ( $spec['self'] && is_string( $item ) ) {
				$hash = Locuentia_Detector::hash_text( $item );
				if ( isset( $translations[ $hash ] ) ) {
					$item = $translations[ $hash ];
				}
			} elseif ( ! empty( $spec['children'] ) && is_array( $item ) ) {
				foreach ( $spec['children'] as $child ) {
					if ( isset( $item[ $child ] ) && is_string( $item[ $child ] ) ) {
						$hash = Locuentia_Detector::hash_text( $item[ $child ] );
						if ( isset( $translations[ $hash ] ) ) {
							$item[ $child ] = $translations[ $hash ];
						}
					}
				}
			}

			$translated[] = $item;
		}

		// get_metadata() unwraps the first element itself when $single is true.
		return $translated;
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
