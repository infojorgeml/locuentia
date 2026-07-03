<?php
/**
 * Sitemap provider for the translated URLs.
 *
 * Plugs into the native WordPress sitemaps (wp-sitemap.xml) adding one
 * sitemap per language: wp-sitemap-locuentia-en-1.xml, with the language
 * home page and the content that has stored translations (the same
 * criterion as the hreflang tags).
 *
 * This file is loaded on demand from the wp_sitemaps_init hook.
 */

defined( 'ABSPATH' ) || exit;

class Locuentia_Sitemap_Provider extends WP_Sitemaps_Provider {

	public function __construct() {
		$this->name        = 'locuentia';
		$this->object_type = 'post';
	}

	/**
	 * One sitemap subtype per language with translations on the site.
	 *
	 * @return object[]
	 */
	public function get_object_subtypes() {
		$subtypes = array();

		foreach ( Locuentia::languages_in_use() as $lang ) {
			$subtypes[ $lang ] = (object) array( 'name' => $lang );
		}

		return $subtypes;
	}

	/**
	 * URLs of one page of a language sitemap.
	 *
	 * @param int    $page_num       Sitemap page number.
	 * @param string $object_subtype Language code.
	 * @return array[]
	 */
	public function get_url_list( $page_num, $object_subtype = '' ) {
		$lang = $this->validate_language( $object_subtype );
		if ( '' === $lang ) {
			return array();
		}

		$per_page = wp_sitemaps_get_max_urls( $this->object_type );
		$offset   = ( max( 1, (int) $page_num ) - 1 ) * $per_page;

		return array_slice( $this->get_language_urls( $lang ), $offset, $per_page );
	}

	/**
	 * Number of sitemap pages of a language.
	 *
	 * @param string $object_subtype Language code.
	 * @return int
	 */
	public function get_max_num_pages( $object_subtype = '' ) {
		$lang = $this->validate_language( $object_subtype );
		if ( '' === $lang ) {
			return 0;
		}

		return (int) ceil( count( $this->get_language_urls( $lang ) ) / wp_sitemaps_get_max_urls( $this->object_type ) );
	}

	/**
	 * Validates that the subtype is a language in use.
	 *
	 * @param string $object_subtype Code received in the sitemap URL.
	 * @return string Valid code, or ''.
	 */
	private function validate_language( $object_subtype ) {
		$lang = Locuentia::sanitize_language_code( $object_subtype );

		if ( '' === $lang || ! in_array( $lang, Locuentia::languages_in_use(), true ) ) {
			return '';
		}

		return $lang;
	}

	/**
	 * All URLs of a language: home page + content with translations.
	 * Computed once per language and request.
	 *
	 * @param string $lang Language code.
	 * @return array[]
	 */
	private function get_language_urls( $lang ) {
		static $cache = array();

		if ( isset( $cache[ $lang ] ) ) {
			return $cache[ $lang ];
		}

		$urls = array(
			array( 'loc' => Locuentia_Router::localize_url( home_url( '/' ), $lang ) ),
		);

		$ids = get_posts(
			array(
				'post_type'      => Locuentia::post_types(),
				'post_status'    => 'publish',
				'has_password'   => false,
				'posts_per_page' => -1,
				'fields'         => 'ids',
				// EXISTS on an indexed key: only posts with translations.
				'meta_key'       => Locuentia::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_compare'   => 'EXISTS',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		update_meta_cache( 'post', $ids );

		foreach ( $ids as $post_id ) {
			if ( empty( Locuentia::get_post_translations( $post_id, $lang ) ) ) {
				continue;
			}

			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			$entry = array(
				'loc' => Locuentia_Router::permalink_for_language( $post, $lang ),
			);

			if ( $post->post_modified_gmt && '0000-00-00 00:00:00' !== $post->post_modified_gmt ) {
				$entry['lastmod'] = gmdate( DATE_W3C, strtotime( $post->post_modified_gmt . ' +0000' ) );
			}

			$urls[] = $entry;
		}

		$cache[ $lang ] = $urls;

		return $urls;
	}
}
