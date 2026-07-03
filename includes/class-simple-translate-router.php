<?php
/**
 * URLs por idioma (/en/pagina/): reglas de reescritura y detección del idioma activo.
 *
 * Se carga siempre (admin y frontend) para que cualquier regeneración de las
 * reglas de reescritura —por ejemplo al guardar Ajustes → Enlaces permanentes—
 * incluya las variantes con prefijo de idioma.
 */

defined( 'ABSPATH' ) || exit;

class Simple_Translate_Router {

	const FLUSH_FLAG = 'simple_translate_flush_rewrite';

	public static function init() {
		add_filter( 'rewrite_rules_array', array( __CLASS__, 'add_language_rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_var' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 20 );
		add_action( 'add_option_' . Simple_Translate::OPTION_LANGUAGES, array( __CLASS__, 'schedule_flush' ) );
		add_action( 'update_option_' . Simple_Translate::OPTION_LANGUAGES, array( __CLASS__, 'schedule_flush' ) );
		add_action( 'add_option_' . Simple_Translate::OPTION_SOURCE, array( __CLASS__, 'schedule_flush' ) );
		add_action( 'update_option_' . Simple_Translate::OPTION_SOURCE, array( __CLASS__, 'schedule_flush' ) );
	}

	/**
	 * Idioma activo de la petición: prefijo /xx/ de la URL o ?lang=xx.
	 *
	 * @return string Código de idioma o '' para el texto original.
	 */
	public static function current_language() {
		static $cached = null;

		if ( null !== $cached ) {
			return $cached;
		}

		$cached = '';

		$languages = Simple_Translate::get_languages();
		if ( empty( $languages ) ) {
			return $cached;
		}

		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$path      = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
			$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );

			if ( '' !== $home_path && 0 === strpos( $path, $home_path ) ) {
				$path = substr( $path, strlen( $home_path ) );
			}

			$segment = strtok( ltrim( $path, '/' ), '/' );
			if ( false !== $segment && in_array( $segment, $languages, true ) ) {
				$cached = $segment;
				return $cached;
			}
		}

		// Alternativa ?lang=xx. Lectura pública sin nonce: solo decide el idioma mostrado.
		if ( isset( $_GET['lang'] ) && is_string( $_GET['lang'] ) ) {
			$requested = Simple_Translate::sanitize_language_code( sanitize_key( wp_unslash( $_GET['lang'] ) ) );
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

		$languages = Simple_Translate::get_languages();
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
			$group . '/?$' => 'index.php?lang=$matches[1]',
		);

		foreach ( $rules as $regex => $query ) {
			$shifted = preg_replace_callback(
				'/\$matches\[(\d+)\]/',
				function ( $m ) {
					return '$matches[' . ( (int) $m[1] + 1 ) . ']';
				},
				$query
			);

			$localized[ $group . '/' . ltrim( $regex, '^' ) ] = $shifted . '&lang=$matches[1]';
		}

		return $localized + $rules;
	}

	/**
	 * Registra lang como query var pública.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public static function register_query_var( $vars ) {
		$vars[] = 'lang';
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
	 * recurre a ?lang=xx. Es idempotente: si la URL ya lleva idioma, lo cambia.
	 *
	 * @param string $url  URL absoluta dentro del sitio.
	 * @param string $lang Código de idioma de destino.
	 * @return string
	 */
	public static function localize_url( $url, $lang ) {
		$lang = Simple_Translate::sanitize_language_code( $lang );
		if ( '' === $lang || ! in_array( $lang, Simple_Translate::get_languages(), true ) ) {
			return $url;
		}

		if ( ! get_option( 'permalink_structure' ) ) {
			return add_query_arg( 'lang', $lang, $url );
		}

		$home = home_url( '/' );
		if ( 0 !== strpos( $url, $home ) ) {
			return $url;
		}

		$relative = self::strip_language_prefix( substr( $url, strlen( $home ) ) );

		return $home . $lang . '/' . ltrim( $relative, '/' );
	}

	/**
	 * URL de la petición actual sin el prefijo de idioma (versión original).
	 *
	 * @param bool $keep_query Conservar la query string (sin el parámetro lang).
	 * @return string
	 */
	public static function current_url_unlocalized( $keep_query = true ) {
		$request = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] )
			? wp_unslash( $_SERVER['REQUEST_URI'] )
			: '/';

		$request = remove_query_arg( 'lang', $request );

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

		foreach ( Simple_Translate::get_languages() as $code ) {
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
