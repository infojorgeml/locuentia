<?php
/**
 * Plugin Name:       Locuentia – Multilingual Translations
 * Plugin URI:        https://github.com/infojorgeml/locuentia
 * Description:       Minimal manual translations for posts and pages: translation fields in the editor, language-prefixed URLs (/en/page/), translated slugs, hreflang tags and per-language sitemaps.
 * Version:           0.0.23
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Jorge Muñoz
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       locuentia
 */

defined( 'ABSPATH' ) || exit;

define( 'LOCUENTIA_VERSION', '0.0.23' );
define( 'LOCUENTIA_DIR', plugin_dir_path( __FILE__ ) );
define( 'LOCUENTIA_URL', plugin_dir_url( __FILE__ ) );

require_once LOCUENTIA_DIR . 'includes/class-locuentia-detector.php';
require_once LOCUENTIA_DIR . 'includes/class-locuentia-router.php';

final class Locuentia {

	const OPTION_LANGUAGES         = 'locuentia_languages';
	const OPTION_SOURCE            = 'locuentia_source_language';
	const OPTION_SITE_TRANSLATIONS = 'locuentia_site_translations';
	const OPTION_META_KEYS         = 'locuentia_meta_keys';
	const OPTION_BROWSER_REDIRECT  = 'locuentia_browser_redirect';
	const OPTION_CACHE_GENERATION  = 'locuentia_cache_generation';
	const META_KEY                 = '_locuentia_translations';
	const SLUG_META_PREFIX         = '_locuentia_slug_';
	const STRINGS_CACHE_KEY        = '_locuentia_strings_cache';
	const ORIGINALS_KEY            = '_locuentia_originals';

	public static function init() {
		Locuentia_Router::init();

		add_action( 'wp_sitemaps_init', array( __CLASS__, 'register_sitemap_provider' ) );

		if ( is_admin() ) {
			require_once LOCUENTIA_DIR . 'includes/class-locuentia-admin.php';
			Locuentia_Admin::init();
		} else {
			require_once LOCUENTIA_DIR . 'includes/class-locuentia-frontend.php';
			Locuentia_Frontend::init();
		}
	}

	/**
	 * Post types where translations are offered.
	 *
	 * @return string[]
	 */
	public static function post_types() {
		return apply_filters( 'locuentia_post_types', array( 'post', 'page' ) );
	}

	/**
	 * Language of the original content (code, e.g. 'es').
	 *
	 * Configurable in settings; by default it is derived from the site
	 * locale, which does not always match the language the content is
	 * actually written in.
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
	 * Configured target languages, e.g. array( 'en', 'fr' ).
	 *
	 * Excludes the original language: its content already lives at the
	 * unprefixed URLs, and duplicating it under /xx/ would create cloned
	 * content and repeated hreflang entries.
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
	 * Normalizes a language code (en, pt-br, …). Returns '' when invalid.
	 *
	 * @param string $code Proposed code.
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
	 * Stored translations of a post for a language: array( hash => text ).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $lang    Language code.
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
	 * Translated slug of a post for a language ('' when it has none).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $lang    Language code.
	 * @return string
	 */
	public static function get_translated_slug( $post_id, $lang ) {
		return (string) get_post_meta( $post_id, self::SLUG_META_PREFIX . $lang, true );
	}

	/**
	 * Site-wide translations for a language: array( hash => text ).
	 *
	 * These come from page-level texts (menus, theme, builder output): a
	 * text translated here applies wherever it appears on the site.
	 *
	 * @param string $lang Language code.
	 * @return array
	 */
	public static function get_site_translations( $lang ) {
		$data = get_option( self::OPTION_SITE_TRANSLATIONS, array() );

		if ( ! is_array( $data ) || empty( $data[ $lang ] ) || ! is_array( $data[ $lang ] ) ) {
			return array();
		}

		return $data[ $lang ];
	}

	/**
	 * Translation memory for a language: every stored translation on the
	 * site (site-wide store + every post), indexed by text hash.
	 *
	 * Used to suggest already-made translations when the same text appears
	 * somewhere else. Computed once per request and language.
	 *
	 * @param string $lang Language code.
	 * @return array array( hash => translated text ).
	 */
	public static function translation_memory( $lang ) {
		static $cache = array();

		if ( isset( $cache[ $lang ] ) ) {
			return $cache[ $lang ];
		}

		$memory = self::get_site_translations( $lang );

		$ids = get_posts(
			array(
				'post_type'      => self::post_types(),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				// EXISTS on an indexed key: only posts with translations.
				'meta_key'       => self::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_compare'   => 'EXISTS',
				'no_found_rows'  => true,
			)
		);

		update_meta_cache( 'post', $ids );

		foreach ( $ids as $post_id ) {
			$data = get_post_meta( $post_id, self::META_KEY, true );
			if ( is_array( $data ) && ! empty( $data[ $lang ] ) && is_array( $data[ $lang ] ) ) {
				// += keeps the first translation found for each hash.
				$memory += $data[ $lang ];
			}
		}

		$cache[ $lang ] = $memory;

		return $memory;
	}

	/**
	 * Configured translatable post meta keys.
	 *
	 * One key per line in settings (commas also accepted). Plain keys mean
	 * the whole value is a translatable string (e.g. _yoast_wpseo_metadesc);
	 * a key.subkey spec targets one string inside an array value (e.g.
	 * slim_seo.title). Extensible via the locuentia_translatable_meta_keys
	 * filter.
	 *
	 * @return string[]
	 */
	public static function translatable_meta_keys() {
		$keys = array();

		foreach ( preg_split( '/[\r\n,]+/', (string) get_option( self::OPTION_META_KEYS, '' ) ) as $line ) {
			$line = preg_replace( '/[^A-Za-z0-9_\-\.]/', '', trim( $line ) );
			if ( '' !== $line ) {
				$keys[ $line ] = $line;
			}
		}

		return apply_filters( 'locuentia_translatable_meta_keys', array_values( $keys ) );
	}

	/**
	 * Translatable meta keys grouped by parent key.
	 *
	 * @return array meta_key => array( 'self' => bool, 'children' => string[] ).
	 */
	public static function meta_key_map() {
		$map = array();

		foreach ( self::translatable_meta_keys() as $spec ) {
			if ( ! is_string( $spec ) || '' === $spec ) {
				continue;
			}

			$parent = $spec;
			$child  = '';

			if ( false !== strpos( $spec, '.' ) ) {
				list( $parent, $child ) = explode( '.', $spec, 2 );
			}

			if ( '' === $parent ) {
				continue;
			}

			if ( ! isset( $map[ $parent ] ) ) {
				$map[ $parent ] = array(
					'self'     => false,
					'children' => array(),
				);
			}

			if ( '' === $child ) {
				$map[ $parent ]['self'] = true;
			} elseif ( ! in_array( $child, $map[ $parent ]['children'], true ) ) {
				$map[ $parent ]['children'][] = $child;
			}
		}

		return $map;
	}

	/**
	 * Finds content colliding with a translated slug for a language.
	 *
	 * A collision is another post using the slug either as its translated
	 * slug for the same language, or as its real slug (which the translated
	 * slug would hide under /lang/slug/, since translated slugs win).
	 *
	 * @param string $slug            Candidate translated slug.
	 * @param string $lang            Language code.
	 * @param int    $exclude_post_id Post being edited (excluded from the search).
	 * @return WP_Post|null The colliding post, or null when the slug is free.
	 */
	public static function find_slug_collision( $slug, $lang, $exclude_post_id ) {
		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			return null;
		}

		$statuses = array( 'publish', 'future', 'private' );

		// The edited post is filtered out in PHP instead of with
		// post__not_in (exclusionary parameters degrade the query plan),
		// so two results are enough: at most one of them is the post itself.
		$found = get_posts(
			array(
				'post_type'      => self::post_types(),
				'post_status'    => $statuses,
				'posts_per_page' => 2,
				// Targeted lookup on an indexed key.
				'meta_key'       => self::SLUG_META_PREFIX . $lang, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $slug, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'no_found_rows'  => true,
			)
		);

		foreach ( $found as $candidate ) {
			if ( (int) $candidate->ID !== (int) $exclude_post_id ) {
				return $candidate;
			}
		}

		$found = get_posts(
			array(
				'post_type'      => self::post_types(),
				'post_status'    => $statuses,
				'posts_per_page' => 2,
				'post_name__in'  => array( $slug ),
				'no_found_rows'  => true,
			)
		);

		foreach ( $found as $candidate ) {
			if ( (int) $candidate->ID !== (int) $exclude_post_id ) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * Native names of the most common languages (ISO 639-1 code => name).
	 *
	 * Extensible/overridable via the locuentia_language_names filter.
	 *
	 * @return array
	 */
	public static function language_names() {
		$names = array(
			'af'    => 'Afrikaans',
			'am'    => 'አማርኛ',
			'ar'    => 'العربية',
			'az'    => 'Azərbaycan',
			'bg'    => 'Български',
			'bn'    => 'বাংলা',
			'ca'    => 'Català',
			'cs'    => 'Čeština',
			'cy'    => 'Cymraeg',
			'da'    => 'Dansk',
			'de'    => 'Deutsch',
			'el'    => 'Ελληνικά',
			'en'    => 'English',
			'es'    => 'Español',
			'et'    => 'Eesti',
			'eu'    => 'Euskara',
			'fa'    => 'فارسی',
			'fi'    => 'Suomi',
			'fr'    => 'Français',
			'ga'    => 'Gaeilge',
			'gl'    => 'Galego',
			'gu'    => 'ગુજરાતી',
			'he'    => 'עברית',
			'hi'    => 'हिन्दी',
			'hr'    => 'Hrvatski',
			'hu'    => 'Magyar',
			'hy'    => 'Հայերեն',
			'id'    => 'Bahasa Indonesia',
			'is'    => 'Íslenska',
			'it'    => 'Italiano',
			'ja'    => '日本語',
			'ka'    => 'ქართული',
			'kk'    => 'Қазақша',
			'km'    => 'ខ្មែរ',
			'ko'    => '한국어',
			'lt'    => 'Lietuvių',
			'lv'    => 'Latviešu',
			'mk'    => 'Македонски',
			'ml'    => 'മലയാളം',
			'mn'    => 'Монгол',
			'mr'    => 'मराठी',
			'ms'    => 'Bahasa Melayu',
			'my'    => 'မြန်မာ',
			'ne'    => 'नेपाली',
			'nl'    => 'Nederlands',
			'no'    => 'Norsk',
			'pa'    => 'ਪੰਜਾਬੀ',
			'pl'    => 'Polski',
			'pt'    => 'Português',
			'pt-br' => 'Português do Brasil',
			'ro'    => 'Română',
			'ru'    => 'Русский',
			'si'    => 'සිංහල',
			'sk'    => 'Slovenčina',
			'sl'    => 'Slovenščina',
			'sq'    => 'Shqip',
			'sr'    => 'Српски',
			'sv'    => 'Svenska',
			'sw'    => 'Kiswahili',
			'ta'    => 'தமிழ்',
			'te'    => 'తెలుగు',
			'th'    => 'ไทย',
			'tr'    => 'Türkçe',
			'uk'    => 'Українська',
			'ur'    => 'اردو',
			'uz'    => 'Oʻzbekcha',
			'vi'    => 'Tiếng Việt',
			'zh'    => '中文',
			'zh-cn' => '简体中文',
			'zh-tw' => '繁體中文',
		);

		return apply_filters( 'locuentia_language_names', $names );
	}

	/**
	 * Label of a language for the switcher.
	 *
	 * @param string $code Language code.
	 * @param string $show 'name' (native name) or 'code' (uppercase code).
	 * @return string
	 */
	public static function language_label( $code, $show = 'name' ) {
		if ( 'code' === $show ) {
			return strtoupper( $code );
		}

		$names = self::language_names();

		return isset( $names[ $code ] ) ? $names[ $code ] : strtoupper( $code );
	}

	/**
	 * Emoji flag of a language (pragmatic language → main-country mapping,
	 * 🌐 when a language has no clear country flag). Overridable via the
	 * locuentia_language_flags filter.
	 *
	 * @param string $code Language code.
	 * @return string
	 */
	public static function language_flag( $code ) {
		$flags = apply_filters(
			'locuentia_language_flags',
			array(
				'af'    => '🇿🇦',
				'am'    => '🇪🇹',
				'ar'    => '🇸🇦',
				'az'    => '🇦🇿',
				'bg'    => '🇧🇬',
				'bn'    => '🇧🇩',
				'cs'    => '🇨🇿',
				'cy'    => '🏴󠁧󠁢󠁷󠁬󠁳󠁿',
				'da'    => '🇩🇰',
				'de'    => '🇩🇪',
				'el'    => '🇬🇷',
				'en'    => '🇬🇧',
				'es'    => '🇪🇸',
				'et'    => '🇪🇪',
				'fa'    => '🇮🇷',
				'fi'    => '🇫🇮',
				'fr'    => '🇫🇷',
				'ga'    => '🇮🇪',
				'gu'    => '🇮🇳',
				'he'    => '🇮🇱',
				'hi'    => '🇮🇳',
				'hr'    => '🇭🇷',
				'hu'    => '🇭🇺',
				'hy'    => '🇦🇲',
				'id'    => '🇮🇩',
				'is'    => '🇮🇸',
				'it'    => '🇮🇹',
				'ja'    => '🇯🇵',
				'ka'    => '🇬🇪',
				'kk'    => '🇰🇿',
				'km'    => '🇰🇭',
				'ko'    => '🇰🇷',
				'lt'    => '🇱🇹',
				'lv'    => '🇱🇻',
				'mk'    => '🇲🇰',
				'ml'    => '🇮🇳',
				'mn'    => '🇲🇳',
				'mr'    => '🇮🇳',
				'ms'    => '🇲🇾',
				'my'    => '🇲🇲',
				'ne'    => '🇳🇵',
				'nl'    => '🇳🇱',
				'no'    => '🇳🇴',
				'pa'    => '🇮🇳',
				'pl'    => '🇵🇱',
				'pt'    => '🇵🇹',
				'pt-br' => '🇧🇷',
				'ro'    => '🇷🇴',
				'ru'    => '🇷🇺',
				'si'    => '🇱🇰',
				'sk'    => '🇸🇰',
				'sl'    => '🇸🇮',
				'sq'    => '🇦🇱',
				'sr'    => '🇷🇸',
				'sv'    => '🇸🇪',
				'sw'    => '🇰🇪',
				'ta'    => '🇮🇳',
				'te'    => '🇮🇳',
				'th'    => '🇹🇭',
				'tr'    => '🇹🇷',
				'uk'    => '🇺🇦',
				'ur'    => '🇵🇰',
				'uz'    => '🇺🇿',
				'vi'    => '🇻🇳',
				'zh'    => '🇨🇳',
				'zh-cn' => '🇨🇳',
				'zh-tw' => '🇹🇼',
			)
		);

		return isset( $flags[ $code ] ) ? $flags[ $code ] : '🌐';
	}

	/**
	 * Language codes stored in the languages option, unfiltered (the
	 * original language is kept here; get_languages() excludes it at
	 * runtime). Used by the settings UI to render the saved selection.
	 *
	 * @return string[]
	 */
	public static function saved_language_codes() {
		$codes = array();

		foreach ( explode( ',', (string) get_option( self::OPTION_LANGUAGES, 'en' ) ) as $part ) {
			$code = self::sanitize_language_code( $part );
			if ( '' !== $code ) {
				$codes[ $code ] = $code;
			}
		}

		return array_values( $codes );
	}

	/**
	 * Target languages that have at least one stored translation on the site.
	 *
	 * Used to avoid announcing (hreflang, sitemap) empty languages, whose
	 * URLs would show content identical to the original.
	 *
	 * @return string[]
	 */
	public static function languages_in_use() {
		static $in_use = null;

		if ( null !== $in_use ) {
			return $in_use;
		}

		$found = array();

		// Site-wide translations count too: builder-based sites may keep
		// every translation in the site store rather than in post meta.
		$site = get_option( self::OPTION_SITE_TRANSLATIONS, array() );
		if ( is_array( $site ) ) {
			foreach ( $site as $lang => $entries ) {
				if ( is_array( $entries ) && ! empty( $entries ) ) {
					$found[ $lang ] = true;
				}
			}
		}

		$ids = get_posts(
			array(
				'post_type'      => self::post_types(),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				// EXISTS on an indexed key: only posts with translations.
				'meta_key'       => self::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_compare'   => 'EXISTS',
				'no_found_rows'  => true,
			)
		);

		update_meta_cache( 'post', $ids );

		foreach ( $ids as $post_id ) {
			$data = get_post_meta( $post_id, self::META_KEY, true );
			if ( ! is_array( $data ) ) {
				continue;
			}
			foreach ( array_keys( $data ) as $lang ) {
				$found[ $lang ] = true;
			}
		}

		$in_use = array_values( array_intersect( self::get_languages(), array_keys( $found ) ) );

		return $in_use;
	}

	/**
	 * Adds a per-language sitemap with the translated URLs to the native sitemaps.
	 *
	 * @param WP_Sitemaps $sitemaps WordPress sitemaps server.
	 */
	public static function register_sitemap_provider( $sitemaps ) {
		if ( empty( self::get_languages() ) || ! isset( $sitemaps->registry ) ) {
			return;
		}

		require_once LOCUENTIA_DIR . 'includes/class-locuentia-sitemap.php';

		$sitemaps->registry->add_provider( 'locuentia', new Locuentia_Sitemap_Provider() );
	}

	/**
	 * On activation: schedule the rewrite rules regeneration for the next
	 * load, once the plugin is fully initialized.
	 */
	public static function activate() {
		update_option( Locuentia_Router::FLUSH_FLAG, 1 );
	}

	/**
	 * On deactivation: regenerate the rules without the language variants.
	 */
	public static function deactivate() {
		remove_filter( 'rewrite_rules_array', array( 'Locuentia_Router', 'add_language_rules' ) );
		flush_rewrite_rules();
		delete_option( Locuentia_Router::FLUSH_FLAG );
	}
}

add_action( 'plugins_loaded', array( 'Locuentia', 'init' ) );

register_activation_hook( __FILE__, array( 'Locuentia', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Locuentia', 'deactivate' ) );
