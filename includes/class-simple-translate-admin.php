<?php
/**
 * Backend: página de ajustes y caja de traducciones en el editor.
 */

defined( 'ABSPATH' ) || exit;

class Simple_Translate_Admin {

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_settings_page' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_post' ), 10, 2 );
	}

	/* ---------- Ajustes ---------- */

	public static function register_settings() {
		register_setting(
			'simple_translate',
			Simple_Translate::OPTION_LANGUAGES,
			array(
				'type'              => 'string',
				'default'           => 'en',
				'sanitize_callback' => array( __CLASS__, 'sanitize_languages_option' ),
			)
		);

		add_settings_section( 'simple_translate_main', '', '__return_false', 'simple-translate' );

		add_settings_field(
			'simple_translate_languages',
			__( 'Idiomas de destino', 'simple-translate' ),
			array( __CLASS__, 'render_languages_field' ),
			'simple-translate',
			'simple_translate_main'
		);
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
			$code = Simple_Translate::sanitize_language_code( $part );
			if ( '' !== $code ) {
				$codes[ $code ] = $code;
			}
		}

		return implode( ', ', $codes );
	}

	public static function render_languages_field() {
		$value = get_option( Simple_Translate::OPTION_LANGUAGES, 'en' );
		?>
		<input type="text" class="regular-text" name="<?php echo esc_attr( Simple_Translate::OPTION_LANGUAGES ); ?>" value="<?php echo esc_attr( $value ); ?>" />
		<p class="description"><?php esc_html_e( 'Códigos de idioma separados por comas, por ejemplo: en, fr, de.', 'simple-translate' ); ?></p>
		<?php
	}

	public static function register_settings_page() {
		add_options_page(
			__( 'Simple Translate', 'simple-translate' ),
			__( 'Simple Translate', 'simple-translate' ),
			'manage_options',
			'simple-translate',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Simple Translate', 'simple-translate' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'simple_translate' );
				do_settings_sections( 'simple-translate' );
				submit_button();
				?>
			</form>
			<p class="description">
				<?php esc_html_e( 'Las traducciones se rellenan al editar cada entrada o página. Para verlas, añade ?lang=CODIGO a la URL, por ejemplo: /mi-pagina/?lang=en', 'simple-translate' ); ?>
			</p>
		</div>
		<?php
	}

	/* ---------- Meta box ---------- */

	public static function add_meta_boxes() {
		foreach ( Simple_Translate::post_types() as $post_type ) {
			add_meta_box(
				'simple-translate',
				__( 'Traducciones', 'simple-translate' ),
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

		$title = Simple_Translate_Detector::normalize_text( $post->post_title );
		if ( Simple_Translate_Detector::is_translatable( $title ) ) {
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
			$strings = $strings + Simple_Translate_Detector::extract_strings( $content );
		}

		return $strings;
	}

	/**
	 * Pinta la caja con un campo de traducción por texto e idioma.
	 *
	 * @param WP_Post $post Post en edición.
	 */
	public static function render_meta_box( $post ) {
		$languages = Simple_Translate::get_languages();
		if ( empty( $languages ) ) {
			echo '<p>' . esc_html__( 'Configura al menos un idioma en Ajustes → Simple Translate.', 'simple-translate' ) . '</p>';
			return;
		}

		$strings = self::detect_strings( $post );
		if ( empty( $strings ) ) {
			echo '<p>' . esc_html__( 'No se ha detectado texto traducible. Escribe el contenido, guarda y vuelve a cargar el editor.', 'simple-translate' ) . '</p>';
			return;
		}

		wp_nonce_field( 'simple_translate_save_' . $post->ID, 'simple_translate_nonce' );

		$saved = get_post_meta( $post->ID, Simple_Translate::META_KEY, true );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		echo '<p class="description">' . esc_html__( 'Los textos se detectan del último contenido guardado. Deja un campo vacío para mostrar el texto original.', 'simple-translate' ) . '</p>';

		foreach ( $languages as $lang ) {
			/* translators: %s: código de idioma en mayúsculas. */
			echo '<h3>' . esc_html( sprintf( __( 'Idioma: %s', 'simple-translate' ), strtoupper( $lang ) ) ) . '</h3>';
			echo '<table class="widefat striped">';
			echo '<thead><tr>';
			echo '<th style="width:50%">' . esc_html__( 'Texto original', 'simple-translate' ) . '</th>';
			echo '<th>' . esc_html__( 'Traducción', 'simple-translate' ) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ( $strings as $hash => $text ) {
				$value = isset( $saved[ $lang ][ $hash ] ) ? $saved[ $lang ][ $hash ] : '';

				echo '<tr>';
				echo '<td>' . esc_html( $text ) . '</td>';
				echo '<td><textarea rows="2" style="width:100%" name="simple_translate_tr[' . esc_attr( $lang ) . '][' . esc_attr( $hash ) . ']">' . esc_textarea( $value ) . '</textarea></td>';
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

		if ( wp_is_post_revision( $post_id ) || ! in_array( $post->post_type, Simple_Translate::post_types(), true ) ) {
			return;
		}

		if ( ! isset( $_POST['simple_translate_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['simple_translate_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'simple_translate_save_' . $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['simple_translate_tr'] ) || ! is_array( $_POST['simple_translate_tr'] ) ) {
			return;
		}

		$raw       = wp_unslash( $_POST['simple_translate_tr'] );
		$languages = Simple_Translate::get_languages();
		$clean     = array();

		foreach ( $raw as $lang => $items ) {
			$lang = Simple_Translate::sanitize_language_code( $lang );
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
			delete_post_meta( $post_id, Simple_Translate::META_KEY );
		} else {
			update_post_meta( $post_id, Simple_Translate::META_KEY, $clean );
		}
	}
}
