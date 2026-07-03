<?php
/**
 * Backend: página de ajustes y caja de traducciones en el editor.
 */

defined( 'ABSPATH' ) || exit;

class Locuentia_Admin {

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_settings_page' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_post' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Estilos de la caja de traducciones, solo en las pantallas de edición.
	 *
	 * @param string $hook_suffix Pantalla de admin actual.
	 */
	public static function enqueue_assets( $hook_suffix ) {
		if ( 'post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'locuentia-admin',
			LOCUENTIA_URL . 'assets/css/admin.css',
			array(),
			LOCUENTIA_VERSION
		);
	}

	/* ---------- Ajustes ---------- */

	public static function register_settings() {
		register_setting(
			'locuentia',
			Locuentia::OPTION_LANGUAGES,
			array(
				'type'              => 'string',
				'default'           => 'en',
				'sanitize_callback' => array( __CLASS__, 'sanitize_languages_option' ),
			)
		);

		register_setting(
			'locuentia',
			Locuentia::OPTION_SOURCE,
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => array( 'Locuentia', 'sanitize_language_code' ),
			)
		);

		add_settings_section( 'locuentia_main', '', '__return_false', 'locuentia' );

		add_settings_field(
			'locuentia_source',
			__( 'Idioma del contenido original', 'locuentia' ),
			array( __CLASS__, 'render_source_field' ),
			'locuentia',
			'locuentia_main'
		);

		add_settings_field(
			'locuentia_languages',
			__( 'Idiomas de destino', 'locuentia' ),
			array( __CLASS__, 'render_languages_field' ),
			'locuentia',
			'locuentia_main'
		);
	}

	public static function render_source_field() {
		$value = get_option( Locuentia::OPTION_SOURCE, '' );
		?>
		<input type="text" class="small-text" name="<?php echo esc_attr( Locuentia::OPTION_SOURCE ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( Locuentia::original_language() ); ?>" />
		<p class="description"><?php esc_html_e( 'Código del idioma en que escribes el contenido (por ejemplo: es). Si se deja vacío se usa el idioma del sitio. Se emplea en las etiquetas hreflang y evita duplicar el original bajo /xx/.', 'locuentia' ); ?></p>
		<?php
	}

	/**
	 * Deja la opción como una lista limpia de códigos: "en, fr".
	 *
	 * @param string $value Valor enviado.
	 * @return string
	 */
	public static function sanitize_languages_option( $value ) {
		$codes = array();

		foreach ( explode( ',', (string) $value ) as $part ) {
			$code = Locuentia::sanitize_language_code( $part );
			if ( '' !== $code ) {
				$codes[ $code ] = $code;
			}
		}

		return implode( ', ', $codes );
	}

	public static function render_languages_field() {
		$value = get_option( Locuentia::OPTION_LANGUAGES, 'en' );
		?>
		<input type="text" class="regular-text" name="<?php echo esc_attr( Locuentia::OPTION_LANGUAGES ); ?>" value="<?php echo esc_attr( $value ); ?>" />
		<p class="description"><?php esc_html_e( 'Códigos de idioma separados por comas, por ejemplo: en, fr, de.', 'locuentia' ); ?></p>
		<?php
	}

	public static function register_settings_page() {
		add_options_page(
			__( 'Locuentia', 'locuentia' ),
			__( 'Locuentia', 'locuentia' ),
			'manage_options',
			'locuentia',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Locuentia', 'locuentia' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'locuentia' );
				do_settings_sections( 'locuentia' );
				submit_button();
				?>
			</form>
			<p class="description">
				<?php esc_html_e( 'Las traducciones se rellenan al editar cada entrada o página, y se sirven en URLs con prefijo de idioma, por ejemplo: /en/mi-pagina/ (también funciona ?locuentia_lang=en).', 'locuentia' ); ?>
			</p>
		</div>
		<?php
	}

	/* ---------- Meta box ---------- */

	public static function add_meta_boxes() {
		foreach ( Locuentia::post_types() as $post_type ) {
			add_meta_box(
				'locuentia',
				__( 'Traducciones', 'locuentia' ),
				array( __CLASS__, 'render_meta_box' ),
				$post_type,
				'normal',
				'default'
			);
		}
	}

	/**
	 * Textos traducibles de un post (título + contenido): array( hash => texto ).
	 *
	 * @param WP_Post $post Post en edición.
	 * @return array
	 */
	public static function detect_strings( $post ) {
		$strings = array();

		$title = Locuentia_Detector::normalize_text( $post->post_title );
		if ( Locuentia_Detector::is_translatable( $title ) ) {
			$strings[ md5( $title ) ] = $title;
		}

		$content = (string) $post->post_content;
		if ( '' !== trim( $content ) ) {
			// El contenido clásico pasa por wpautop al renderizarse; hay que
			// imitarlo aquí para que los textos detectados coincidan con los
			// del frontend (el contenido de bloques no lo necesita).
			if ( ! function_exists( 'has_blocks' ) || ! has_blocks( $content ) ) {
				$content = wpautop( $content );
			}
			$strings = $strings + Locuentia_Detector::extract_strings( $content );
		}

		return $strings;
	}

	/**
	 * Pinta la caja con un campo de traducción por texto e idioma.
	 *
	 * @param WP_Post $post Post en edición.
	 */
	public static function render_meta_box( $post ) {
		$languages = Locuentia::get_languages();
		if ( empty( $languages ) ) {
			echo '<p>' . esc_html__( 'Configura al menos un idioma en Ajustes → Locuentia.', 'locuentia' ) . '</p>';
			return;
		}

		$strings = self::detect_strings( $post );
		if ( empty( $strings ) ) {
			echo '<p>' . esc_html__( 'No se ha detectado texto traducible. Escribe el contenido, guarda y vuelve a cargar el editor.', 'locuentia' ) . '</p>';
			return;
		}

		wp_nonce_field( 'locuentia_save_' . $post->ID, 'locuentia_nonce' );

		$saved = get_post_meta( $post->ID, Locuentia::META_KEY, true );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		echo '<p class="description">' . esc_html__( 'Los textos se detectan del último contenido guardado. Deja un campo vacío para mostrar el texto original.', 'locuentia' ) . '</p>';

		foreach ( $languages as $lang ) {
			/* translators: %s: código de idioma en mayúsculas. */
			echo '<h3>' . esc_html( sprintf( __( 'Idioma: %s', 'locuentia' ), strtoupper( $lang ) ) ) . '</h3>';

			$slug = Locuentia::get_translated_slug( $post->ID, $lang );
			echo '<p><label>' . esc_html__( 'Slug traducido (opcional):', 'locuentia' ) . ' ';
			echo '<input type="text" class="regular-text" name="locuentia_slug[' . esc_attr( $lang ) . ']" value="' . esc_attr( $slug ) . '" placeholder="' . esc_attr( $post->post_name ) . '" />';
			echo '</label><br /><span class="description">'
				. esc_html( sprintf( /* translators: %s: código de idioma. */ __( 'Cambia la URL en este idioma, p. ej. /%s/mi-slug-traducido/. Vacío = mismo slug que el original.', 'locuentia' ), $lang ) )
				. '</span></p>';

			echo '<table class="widefat striped">';
			echo '<thead><tr>';
			echo '<th class="locuentia-col-original">' . esc_html__( 'Texto original', 'locuentia' ) . '</th>';
			echo '<th>' . esc_html__( 'Traducción', 'locuentia' ) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ( $strings as $hash => $text ) {
				$value = isset( $saved[ $lang ][ $hash ] ) ? $saved[ $lang ][ $hash ] : '';

				echo '<tr>';
				echo '<td>' . esc_html( $text ) . '</td>';
				echo '<td><textarea rows="2" name="locuentia_tr[' . esc_attr( $lang ) . '][' . esc_attr( $hash ) . ']">' . esc_textarea( $value ) . '</textarea></td>';
				echo '</tr>';
			}

			echo '</tbody></table>';
		}
	}

	/**
	 * Guarda las traducciones enviadas desde la caja.
	 *
	 * @param int     $post_id ID del post.
	 * @param WP_Post $post    Post guardado.
	 */
	public static function save_post( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || ! in_array( $post->post_type, Locuentia::post_types(), true ) ) {
			return;
		}

		if ( ! isset( $_POST['locuentia_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['locuentia_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'locuentia_save_' . $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$languages = Locuentia::get_languages();

		if ( isset( $_POST['locuentia_tr'] ) && is_array( $_POST['locuentia_tr'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- se sanitiza elemento a elemento en el bucle.
			$raw   = wp_unslash( $_POST['locuentia_tr'] );
			$clean = array();

			foreach ( $raw as $lang => $items ) {
				$lang = Locuentia::sanitize_language_code( $lang );
				if ( '' === $lang || ! in_array( $lang, $languages, true ) || ! is_array( $items ) ) {
					continue;
				}

				foreach ( $items as $hash => $value ) {
					if ( ! is_string( $value ) || ! preg_match( '/^[a-f0-9]{32}$/', (string) $hash ) ) {
						continue;
					}

					$value = sanitize_textarea_field( $value );
					if ( '' !== $value ) {
						$clean[ $lang ][ $hash ] = $value;
					}
				}
			}

			if ( empty( $clean ) ) {
				delete_post_meta( $post_id, Locuentia::META_KEY );
			} else {
				update_post_meta( $post_id, Locuentia::META_KEY, $clean );
			}
		}

		if ( isset( $_POST['locuentia_slug'] ) && is_array( $_POST['locuentia_slug'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- se sanitiza con sanitize_title() en el bucle.
			$raw_slugs = wp_unslash( $_POST['locuentia_slug'] );

			foreach ( $languages as $lang ) {
				if ( ! isset( $raw_slugs[ $lang ] ) || ! is_string( $raw_slugs[ $lang ] ) ) {
					continue;
				}

				$slug     = sanitize_title( $raw_slugs[ $lang ] );
				$meta_key = Locuentia::SLUG_META_PREFIX . $lang;

				if ( '' === $slug ) {
					delete_post_meta( $post_id, $meta_key );
				} else {
					update_post_meta( $post_id, $meta_key, $slug );
				}
			}
		}
	}
}
