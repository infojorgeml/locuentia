<?php
/**
 * URLs por idioma (/en/pagina/): reglas de reescritura y detección del idioma activo.
 *
 * Se carga siempre (admin y frontend) para que cualquier regeneración de las
 * reglas de reescritura —por ejemplo al guardar Ajustes → Enlaces permanentes—
 * incluya las variantes con prefijo de idioma.
 */

defined( 'ABSPATH' ) || exit;

class Locuentia_Router {

	const FLUSH_FLAG = 'locuentia_flush_rewrite';

	/**
	 * Mientras está a true, los filtros de permalink no aplican idioma
	 * (permite obtener la URL original de un post).
	 *
	 * @var bool
	 */
	private static $suspended = false;

	public static function init() {
		add_filter( 'rewrite_rules_array', array( __CLASS__, 'add_language_rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_var' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 20 );

		// Resolución de slugs traducidos en la petición entrante.
		add_filter( 'request', array( __CLASS__, 'resolve_translated_slug' ) );

		// URLs salientes: con idioma activo, los enlaces internos llevan el
		// prefijo y el slug traducido. En admin no hay idioma activo, así que
		// estos filtros no alteran nada allí.
		add_filter( 'page_link', array( __CLASS__, 'localize_post_url' ), 10, 2 );
		add_filter( 'post_link', array( __CLASS__, 'localize_post_url' ), 10, 2 );
		add_filter( 'post_type_link', array( __CLASS__, 'localize_post_url' ), 10, 2 );
		add_filter( 'term_link', array( __CLASS__, 'localize_plain_url' ) );

		// La redirección canónica de WordPress no conoce el prefijo y sacaría
		// al visitante del idioma (p. ej. /en/ → /); se lo reponemos.
		add_filter( 'redirect_canonical', array( __CLASS__, 'localize_canonical' ), 10, 2 );

		// El core considera canónica la URL con el slug real, así que la
		// redirección al slug traducido (/en/sobre-nosotros/ → /en/about-us/)
		// hay que hacerla aquí.
		add_action( 'template_redirect', array( __CLASS__, 'redirect_untranslated_slug' ) );
		add_action( 'add_option_' . Locuentia::OPTION_LANGUAGES, array( __CLASS__, 'schedule_flush' ) );
		add_action( 'update_option_' . Locuentia::OPTION_LANGUAGES, array( __CLASS__, 'schedule_flush' ) );
		add_action( 'add_option_' . Locuentia::OPTION_SOURCE, array( __CLASS__, 'schedule_flush' ) );
		add_action( 'update_option_' . Locuentia::OPTION_SOURCE, array( __CLASS__, 'schedule_flush' ) );
	}

	/**
	 * Idioma activo de la petición: prefijo /xx/ de la URL o ?locuentia_lang=xx.
	 *
	 * @return string Código de idioma o '' para el texto original.
	 */
	public static function current_language() {
		static $cached = null;

		if ( null !== $cached ) {
			return $cached;
		}

		$cached = '';

		$languages = Locuentia::get_languages();
		if ( empty( $languages ) ) {
			return $cached;
		}

		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$request_uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			$path        = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
			$home_path   = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );

			if ( '' !== $home_path && 0 === strpos( $path, $home_path ) ) {
				$path = substr( $path, strlen( $home_path ) );
			}

			$segment = strtok( ltrim( $path, '/' ), '/' );
			if ( false !== $segment && in_array( $segment, $languages, true ) ) {
				$cached = $segment;
				return $cached;
			}
		}

		// Alternativa ?locuentia_lang=xx. Lectura pública: solo decide el idioma mostrado.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['locuentia_lang'] ) && is_string( $_GET['locuentia_lang'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$requested = Locuentia::sanitize_language_code( sanitize_key( wp_unslash( $_GET['locuentia_lang'] ) ) );
			if ( '' !== $requested && in_array( $requested, $languages, true ) ) {
				$cached = $requested;
			}
		}

		return $cached;
	}

	/**
	 * Duplica cada regla de reescritura bajo el prefijo de idioma: (en|fr)/…
	 *
	 * El grupo del idioma pasa a ser $matches[1], así que los índices de la
	 * consulta original se desplazan en uno.
	 *
	 * @param array $rules Reglas generadas por WordPress.
	 * @return array
	 */
	public static function add_language_rules( $rules ) {
		if ( ! is_array( $rules ) || empty( $rules ) ) {
			return $rules;
		}

		$languages = Locuentia::get_languages();
		if ( empty( $languages ) ) {
			return $rules;
		}

		$quoted = array();
		foreach ( $languages as $code ) {
			$quoted[] = preg_quote( $code, '#' );
		}
		$group = '(' . implode( '|', $quoted ) . ')';

		$localized = array(
			// Portada de cada idioma: /en/
			$group . '/?$' => 'index.php?locuentia_lang=$matches[1]',
		);

		foreach ( $rules as $regex => $query ) {
			$shifted = preg_replace_callback(
				'/\$matches\[(\d+)\]/',
				function ( $m ) {
					return '$matches[' . ( (int) $m[1] + 1 ) . ']';
				},
				$query
			);

			$localized[ $group . '/' . ltrim( $regex, '^' ) ] = $shifted . '&locuentia_lang=$matches[1]';
		}

		return $localized + $rules;
	}

	/**
	 * Registra locuentia_lang como query var pública.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public static function register_query_var( $vars ) {
		$vars[] = 'locuentia_lang';
		return $vars;
	}

	/**
	 * Marca que hay que regenerar las reglas en la próxima carga.
	 */
	public static function schedule_flush() {
		update_option( self::FLUSH_FLAG, 1 );
	}

	/**
	 * Regenera las reglas si está pendiente (tras activar o cambiar idiomas).
	 */
	public static function maybe_flush() {
		if ( get_option( self::FLUSH_FLAG ) ) {
			delete_option( self::FLUSH_FLAG );
			flush_rewrite_rules();
		}
	}

	/**
	 * Devuelve la URL apuntando a la versión en un idioma.
	 *
	 * Con enlaces permanentes bonitos inserta el prefijo (/en/…); sin ellos
	 * recurre a ?locuentia_lang=xx. Es idempotente: si la URL ya lleva idioma,
	 * lo cambia.
	 *
	 * @param string $url  URL absoluta dentro del sitio.
	 * @param string $lang Código de idioma de destino.
	 * @return string
	 */
	public static function localize_url( $url, $lang ) {
		$lang = Locuentia::sanitize_language_code( $lang );
		if ( '' === $lang || ! in_array( $lang, Locuentia::get_languages(), true ) ) {
			return $url;
		}

		if ( ! get_option( 'permalink_structure' ) ) {
			return add_query_arg( 'locuentia_lang', $lang, $url );
		}

		$home = home_url( '/' );
		if ( 0 !== strpos( $url, $home ) ) {
			return $url;
		}

		$relative = self::strip_language_prefix( substr( $url, strlen( $home ) ) );

		return $home . $lang . '/' . ltrim( $relative, '/' );
	}

	/**
	 * Resuelve slugs traducidos en la petición: /en/about-us/ carga la
	 * entrada cuyo slug traducido en "en" es "about-us".
	 *
	 * @param array $query_vars Query vars parseadas de la URL.
	 * @return array
	 */
	public static function resolve_translated_slug( $query_vars ) {
		$lang = self::current_language();
		if ( '' === $lang || ! is_array( $query_vars ) ) {
			return $query_vars;
		}

		$requested = '';
		if ( ! empty( $query_vars['pagename'] ) && is_string( $query_vars['pagename'] ) ) {
			// En páginas jerárquicas (padre/hijo) solo se traduce el slug propio.
			$parts     = explode( '/', trim( $query_vars['pagename'], '/' ) );
			$requested = (string) end( $parts );
		} elseif ( ! empty( $query_vars['name'] ) && is_string( $query_vars['name'] ) ) {
			$requested = $query_vars['name'];
		}

		if ( '' === $requested ) {
			return $query_vars;
		}

		$found = get_posts(
			array(
				'post_type'      => Locuentia::post_types(),
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				// Búsqueda puntual por clave indexada, acotada a 1 resultado.
				'meta_key'       => Locuentia::SLUG_META_PREFIX . $lang, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $requested, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'no_found_rows'  => true,
			)
		);

		if ( empty( $found ) ) {
			return $query_vars;
		}

		$post = $found[0];

		unset( $query_vars['pagename'], $query_vars['name'] );

		if ( 'page' === $post->post_type ) {
			$query_vars['page_id'] = $post->ID;
		} else {
			$query_vars['p'] = $post->ID;
			if ( 'post' !== $post->post_type ) {
				$query_vars['post_type'] = $post->post_type;
			}
		}

		return $query_vars;
	}

	/**
	 * Redirige 301 la URL con el slug original bajo prefijo de idioma a la
	 * URL con el slug traducido: /en/sobre-nosotros/ → /en/about-us/.
	 */
	public static function redirect_untranslated_slug() {
		$lang = self::current_language();
		if ( '' === $lang || ! is_singular( Locuentia::post_types() ) ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post instanceof WP_Post || '' === (string) $post->post_name ) {
			return;
		}

		$translated = Locuentia::get_translated_slug( $post->ID, $lang );
		if ( '' === $translated || $translated === $post->post_name ) {
			return;
		}

		if ( ! isset( $_SERVER['REQUEST_URI'] ) || ! is_string( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}

		$request_uri  = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$request_path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );

		// Sustituye el segmento del slug original conservando lo que le siga
		// (paginación, feed…). Si no aparece, la URL ya usa el slug traducido.
		$new_path = preg_replace(
			'#/' . preg_quote( $post->post_name, '#' ) . '(/|$)#',
			'/' . $translated . '$1',
			$request_path,
			1
		);

		if ( null === $new_path || $new_path === $request_path ) {
			return;
		}

		$query  = (string) wp_parse_url( $request_uri, PHP_URL_QUERY );
		$target = $new_path . ( '' !== $query ? '?' . $query : '' );

		wp_safe_redirect( $target, 301 );
		exit;
	}

	/**
	 * Permalink original de un post (sin prefijo ni slug traducido).
	 *
	 * @param int|WP_Post $post Post.
	 * @return string
	 */
	public static function unlocalized_permalink( $post ) {
		self::$suspended = true;
		$url             = get_permalink( $post );
		self::$suspended = false;

		return $url ? $url : '';
	}

	/**
	 * Permalink de un post en un idioma (prefijo + slug traducido si tiene).
	 * Con $lang vacío devuelve el permalink original.
	 *
	 * @param int|WP_Post $post Post.
	 * @param string      $lang Código de idioma o ''.
	 * @return string
	 */
	public static function permalink_for_language( $post, $lang ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return '';
		}

		$url = self::unlocalized_permalink( $post );
		if ( '' === $lang || '' === $url ) {
			return $url;
		}

		return self::swap_slug( self::localize_url( $url, $lang ), $post, $lang );
	}

	/**
	 * Filtro de page_link/post_link/post_type_link: aplica el idioma activo
	 * (prefijo + slug traducido).
	 *
	 * @param string      $url  Permalink original.
	 * @param int|WP_Post $post Post del enlace (según el filtro).
	 * @return string
	 */
	public static function localize_post_url( $url, $post ) {
		if ( self::$suspended ) {
			return $url;
		}

		$lang = self::current_language();
		if ( '' === $lang ) {
			return $url;
		}

		return self::swap_slug( self::localize_url( $url, $lang ), get_post( $post ), $lang );
	}

	/**
	 * Filtro de term_link: con idioma activo solo se añade el prefijo.
	 *
	 * @param string $url URL del término.
	 * @return string
	 */
	public static function localize_plain_url( $url ) {
		if ( self::$suspended ) {
			return $url;
		}

		$lang = self::current_language();

		return '' === $lang ? $url : self::localize_url( $url, $lang );
	}

	/**
	 * Mantiene el prefijo de idioma en las redirecciones canónicas.
	 *
	 * @param string|false $redirect_url  URL canónica propuesta.
	 * @param string       $requested_url URL solicitada.
	 * @return string|false
	 */
	public static function localize_canonical( $redirect_url, $requested_url ) {
		$lang = self::current_language();
		if ( '' === $lang || ! $redirect_url ) {
			return $redirect_url;
		}

		return self::localize_url( $redirect_url, $lang );
	}

	/**
	 * Sustituye el último segmento de la URL (el slug del post) por su
	 * versión traducida, si existe.
	 *
	 * @param string       $url  URL ya con prefijo de idioma.
	 * @param WP_Post|null $post Post al que pertenece la URL.
	 * @param string       $lang Código de idioma.
	 * @return string
	 */
	private static function swap_slug( $url, $post, $lang ) {
		if ( ! $post instanceof WP_Post || '' === (string) $post->post_name ) {
			return $url;
		}

		$translated = Locuentia::get_translated_slug( $post->ID, $lang );
		if ( '' === $translated || $translated === $post->post_name ) {
			return $url;
		}

		return preg_replace(
			'#/' . preg_quote( $post->post_name, '#' ) . '(/?)$#',
			'/' . $translated . '$1',
			$url
		);
	}

	/**
	 * URL de la petición actual sin el prefijo de idioma (versión original).
	 *
	 * @param bool $keep_query Conservar la query string (sin el parámetro de idioma).
	 * @return string
	 */
	public static function current_url_unlocalized( $keep_query = true ) {
		$request = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] )
			? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '/';

		$request = remove_query_arg( 'locuentia_lang', $request );

		if ( ! $keep_query ) {
			$request = (string) wp_parse_url( $request, PHP_URL_PATH );
		}

		$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( '' !== $home_path && 0 === strpos( $request, $home_path ) ) {
			$request = substr( $request, strlen( $home_path ) );
		}

		return home_url( '/' . self::strip_language_prefix( ltrim( $request, '/' ) ) );
	}

	/**
	 * Quita el prefijo de idioma inicial de una ruta relativa al home.
	 *
	 * @param string $relative Ruta relativa, p. ej. "en/pagina/".
	 * @return string
	 */
	public static function strip_language_prefix( $relative ) {
		$relative = (string) $relative;

		foreach ( Locuentia::get_languages() as $code ) {
			if ( $relative === $code || $relative === $code . '/' ) {
				return '';
			}
			if ( 0 === strpos( $relative, $code . '/' ) ) {
				return substr( $relative, strlen( $code ) + 1 );
			}
			if ( 0 === strpos( $relative, $code . '?' ) ) {
				return substr( $relative, strlen( $code ) );
			}
		}

		return $relative;
	}
}
