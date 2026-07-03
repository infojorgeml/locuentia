<?php
/**
 * Proveedor de sitemap para las URLs traducidas.
 *
 * Se acopla a los sitemaps nativos de WordPress (wp-sitemap.xml) añadiendo
 * un sitemap por idioma: wp-sitemap-locuentia-en-1.xml, con la portada
 * del idioma y el contenido que tiene traducciones guardadas (el mismo
 * criterio que las etiquetas hreflang).
 *
 * Este archivo se carga bajo demanda desde el hook wp_sitemaps_init.
 */

defined( 'ABSPATH' ) || exit;

class Locuentia_Sitemap_Provider extends WP_Sitemaps_Provider {

	public function __construct() {
		$this->name        = 'locuentia';
		$this->object_type = 'post';
	}

	/**
	 * Un subtipo de sitemap por idioma con traducciones en el sitio.
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
	 * URLs de una página del sitemap de un idioma.
	 *
	 * @param int    $page_num       Número de página del sitemap.
	 * @param string $object_subtype Código de idioma.
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
	 * Número de páginas del sitemap de un idioma.
	 *
	 * @param string $object_subtype Código de idioma.
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
	 * Valida que el subtipo sea un idioma configurado.
	 *
	 * @param string $object_subtype Código recibido en la URL del sitemap.
	 * @return string Código válido o ''.
	 */
	private function validate_language( $object_subtype ) {
		$lang = Locuentia::sanitize_language_code( $object_subtype );

		if ( '' === $lang || ! in_array( $lang, Locuentia::languages_in_use(), true ) ) {
			return '';
		}

		return $lang;
	}

	/**
	 * Todas las URLs de un idioma: portada + contenido con traducciones.
	 * Se calcula una vez por idioma y petición.
	 *
	 * @param string $lang Código de idioma.
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
				// EXISTS sobre clave indexada: solo posts con traducciones.
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
