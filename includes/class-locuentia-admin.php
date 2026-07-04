<?php
/**
 * Backend: settings page and translations box in the editor.
 */

defined( 'ABSPATH' ) || exit;

class Locuentia_Admin {

	public static function init() {
		require_once LOCUENTIA_DIR . 'includes/class-locuentia-translator.php';
		Locuentia_Translator::init();

		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_settings_page' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_post' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'show_slug_collision_notice' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_list_columns' ) );
	}

	/**
	 * Adds the translation progress column to the post list tables.
	 *
	 * Registered on admin_init so third-party locuentia_post_types filters
	 * are already in place.
	 */
	public static function register_list_columns() {
		if ( empty( Locuentia::get_languages() ) ) {
			return;
		}

		foreach ( Locuentia::post_types() as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( __CLASS__, 'add_translations_column' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( __CLASS__, 'render_translations_column' ), 10, 2 );
		}
	}

	/**
	 * Inserts the Translations column right before the Date column.
	 *
	 * @param array $columns List table columns.
	 * @return array
	 */
	public static function add_translations_column( $columns ) {
		$new = array();

		foreach ( $columns as $key => $label ) {
			if ( 'date' === $key ) {
				$new['locuentia'] = __( 'Translations', 'locuentia' );
			}
			$new[ $key ] = $label;
		}

		if ( ! isset( $new['locuentia'] ) ) {
			$new['locuentia'] = __( 'Translations', 'locuentia' );
		}

		return $new;
	}

	/**
	 * Renders one progress badge per language: translated/total texts.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Row post ID.
	 */
	public static function render_translations_column( $column, $post_id ) {
		if ( 'locuentia' !== $column ) {
			return;
		}

		$post = get_post( $post_id );
		if ( $post ) {
			self::render_progress_badges( $post );
		}
	}

	/**
	 * Prints one progress badge per language for a post: translated/total texts.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_progress_badges( $post ) {
		$strings = self::detect_strings( $post );
		$total   = count( $strings );

		if ( 0 === $total ) {
			echo '<span class="locuentia-badge locuentia-badge--none">' . esc_html__( 'No text', 'locuentia' ) . '</span>';
			return;
		}

		foreach ( Locuentia::get_languages() as $lang ) {
			// Only translations whose hash still matches a current text count.
			$done = count( array_intersect_key( Locuentia::get_post_translations( $post->ID, $lang ), $strings ) );

			if ( $done >= $total ) {
				$state = 'full';
			} elseif ( $done > 0 ) {
				$state = 'partial';
			} else {
				$state = 'none';
			}

			printf(
				'<span class="locuentia-badge locuentia-badge--%1$s">%2$s %3$d/%4$d</span> ',
				esc_attr( $state ),
				esc_html( strtoupper( $lang ) ),
				(int) $done,
				(int) $total
			);
		}
	}

	/**
	 * Shows the notice for translated slugs rejected on the last save.
	 */
	public static function show_slug_collision_notice() {
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && ! in_array( $screen->base, array( 'post', 'toplevel_page_locuentia' ), true ) ) {
				return;
			}
		}

		$key      = 'locuentia_slug_rejected_' . get_current_user_id();
		$rejected = get_transient( $key );

		if ( empty( $rejected ) || ! is_array( $rejected ) ) {
			return;
		}

		delete_transient( $key );

		echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Locuentia:', 'locuentia' ) . '</strong></p>';

		foreach ( $rejected as $item ) {
			$title = get_the_title( $item['with_id'] );
			$title = '' !== $title ? $title : '#' . (int) $item['with_id'];
			$link  = get_edit_post_link( $item['with_id'] );

			$title_html = $link
				? '<a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a>'
				: esc_html( $title );

			echo '<p>' . sprintf(
				/* translators: 1: rejected slug, 2: uppercase language code, 3: title of the colliding content. */
				esc_html__( 'The translated slug “%1$s” (%2$s) was not saved: it is already in use by %3$s. Choose a different slug.', 'locuentia' ),
				esc_html( $item['slug'] ),
				esc_html( strtoupper( $item['lang'] ) ),
				wp_kses( $title_html, array( 'a' => array( 'href' => array() ) ) )
			) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * Translations box and list column styles, only where they render.
	 *
	 * @param string $hook_suffix Current admin screen.
	 */
	public static function enqueue_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php', 'edit.php', 'toplevel_page_locuentia' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'locuentia-admin',
			LOCUENTIA_URL . 'assets/css/admin.css',
			array(),
			LOCUENTIA_VERSION
		);
	}

	/* ---------- Settings ---------- */

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
			__( 'Original content language', 'locuentia' ),
			array( __CLASS__, 'render_source_field' ),
			'locuentia',
			'locuentia_main'
		);

		register_setting(
			'locuentia',
			Locuentia::OPTION_META_KEYS,
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => array( __CLASS__, 'sanitize_meta_keys_option' ),
			)
		);

		add_settings_field(
			'locuentia_languages',
			__( 'Target languages', 'locuentia' ),
			array( __CLASS__, 'render_languages_field' ),
			'locuentia',
			'locuentia_main'
		);

		add_settings_field(
			'locuentia_meta_keys',
			__( 'Translatable meta keys', 'locuentia' ),
			array( __CLASS__, 'render_meta_keys_field' ),
			'locuentia',
			'locuentia_main'
		);
	}

	/**
	 * Normalizes the meta keys option: one clean key (or key.subkey) per line.
	 *
	 * @param string $value Submitted value.
	 * @return string
	 */
	public static function sanitize_meta_keys_option( $value ) {
		$keys = array();

		foreach ( preg_split( '/[\r\n,]+/', (string) $value ) as $line ) {
			$line = preg_replace( '/[^A-Za-z0-9_\-\.]/', '', trim( $line ) );
			if ( '' !== $line ) {
				$keys[ $line ] = $line;
			}
		}

		return implode( "\n", $keys );
	}

	public static function render_meta_keys_field() {
		$value = get_option( Locuentia::OPTION_META_KEYS, '' );
		?>
		<textarea class="large-text code" rows="4" name="<?php echo esc_attr( Locuentia::OPTION_META_KEYS ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'Post meta keys whose value should be translatable, one per line. The values show up as regular texts in the translations box. Use key.subkey for one string inside an array value.', 'locuentia' ); ?>
			<br />
			<?php esc_html_e( 'Common examples — Yoast:', 'locuentia' ); ?> <code>_yoast_wpseo_title</code> <code>_yoast_wpseo_metadesc</code>
			· Rank Math: <code>rank_math_title</code> <code>rank_math_description</code>
			· SEOPress: <code>_seopress_titles_title</code> <code>_seopress_titles_desc</code>
			· Slim SEO: <code>slim_seo.title</code> <code>slim_seo.description</code>
		</p>
		<?php
	}

	public static function render_source_field() {
		$value = get_option( Locuentia::OPTION_SOURCE, '' );
		?>
		<input type="text" class="small-text" name="<?php echo esc_attr( Locuentia::OPTION_SOURCE ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( Locuentia::original_language() ); ?>" />
		<p class="description"><?php esc_html_e( 'Code of the language your content is written in (for example: es). Leave empty to use the site language. Used in the hreflang tags, and a target language equal to it is ignored to avoid duplicated content under /xx/.', 'locuentia' ); ?></p>
		<?php
	}

	/**
	 * Stores the option as a clean list of codes: "en, fr".
	 *
	 * @param string $value Submitted value.
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
		<p class="description"><?php esc_html_e( 'Comma-separated language codes, for example: en, fr, de.', 'locuentia' ); ?></p>
		<?php
	}

	public static function register_settings_page() {
		// The top-level page is the translation queue (the everyday tool);
		// settings live in a submenu.
		add_menu_page(
			__( 'Locuentia', 'locuentia' ),
			__( 'Locuentia', 'locuentia' ),
			'edit_posts',
			'locuentia',
			array( 'Locuentia_Translator', 'render_page' ),
			'dashicons-translation',
			80
		);

		add_submenu_page(
			'locuentia',
			__( 'Translate', 'locuentia' ),
			__( 'Translate', 'locuentia' ),
			'edit_posts',
			'locuentia',
			array( 'Locuentia_Translator', 'render_page' )
		);

		add_submenu_page(
			'locuentia',
			__( 'Settings', 'locuentia' ),
			__( 'Settings', 'locuentia' ),
			'manage_options',
			'locuentia-settings',
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

			<hr />

			<h2><?php esc_html_e( 'How content gets translated', 'locuentia' ); ?></h2>
			<p><?php esc_html_e( 'Translations are filled in while editing each post or page (the “Translations” box below the editor) and are served on language-prefixed URLs, for example: /en/my-page/ (?locuentia_lang=en also works). The home page of each language lives at /en/, /fr/, and so on.', 'locuentia' ); ?></p>

			<h2><?php esc_html_e( 'Language switcher (shortcode)', 'locuentia' ); ?></h2>
			<p><?php esc_html_e( 'Place the shortcode wherever you want the switcher. Being a shortcode it works in any editor or builder: the Gutenberg “Shortcode” block, an Elementor widget, a Bricks element, classic widgets…', 'locuentia' ); ?></p>

			<p><code>[locuentia_switcher]</code></p>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Attribute', 'locuentia' ); ?></th>
						<th><?php esc_html_e( 'Values', 'locuentia' ); ?></th>
						<th><?php esc_html_e( 'What it does', 'locuentia' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>style</code></td>
						<td><code>list</code> <?php esc_html_e( '(default)', 'locuentia' ); ?>, <code>inline</code>, <code>dropdown</code></td>
						<td><?php esc_html_e( 'Vertical list, horizontal list, or a dropdown menu.', 'locuentia' ); ?></td>
					</tr>
					<tr>
						<td><code>show</code></td>
						<td><code>name</code> <?php esc_html_e( '(default)', 'locuentia' ); ?>, <code>code</code></td>
						<td><?php esc_html_e( 'Native language name (Español, English…) or its code (ES, EN…).', 'locuentia' ); ?></td>
					</tr>
					<tr>
						<td><code>hide_current</code></td>
						<td><code>no</code> <?php esc_html_e( '(default)', 'locuentia' ); ?>, <code>yes</code></td>
						<td><?php esc_html_e( 'Hides the language being viewed.', 'locuentia' ); ?></td>
					</tr>
					<tr>
						<td><code>separator</code></td>
						<td><?php esc_html_e( 'any text', 'locuentia' ); ?></td>
						<td><?php esc_html_e( 'Separator between items in the list and inline styles, e.g. "|" or "·".', 'locuentia' ); ?></td>
					</tr>
					<tr>
						<td><code>original_label</code></td>
						<td><?php esc_html_e( 'any text', 'locuentia' ); ?></td>
						<td><?php esc_html_e( 'Label for the original language (defaults to its native name).', 'locuentia' ); ?></td>
					</tr>
				</tbody>
			</table>

			<p>
				<?php esc_html_e( 'Examples:', 'locuentia' ); ?>
				<code>[locuentia_switcher style="dropdown"]</code>
				<code>[locuentia_switcher style="inline" show="code" separator="|"]</code>
				<code>[locuentia_switcher style="inline" hide_current="yes"]</code>
			</p>

			<p class="description"><?php esc_html_e( 'The active language item carries the locuentia-current class in case you want to style it from your theme.', 'locuentia' ); ?></p>
		</div>
		<?php
	}

	/* ---------- Meta box ---------- */

	public static function add_meta_boxes() {
		foreach ( Locuentia::post_types() as $post_type ) {
			add_meta_box(
				'locuentia',
				__( 'Translations', 'locuentia' ),
				array( __CLASS__, 'render_meta_box' ),
				$post_type,
				'normal',
				'default'
			);
		}
	}

	/**
	 * Translatable texts of a post (title + excerpt + content): array( hash => text ).
	 *
	 * @param WP_Post $post Post being edited.
	 * @return array
	 */
	public static function detect_strings( $post ) {
		$strings = array();

		$title = Locuentia_Detector::normalize_text( $post->post_title );
		if ( Locuentia_Detector::is_translatable( $title ) ) {
			$strings[ md5( $title ) ] = $title;
		}

		$excerpt = Locuentia_Detector::normalize_text( $post->post_excerpt );
		if ( Locuentia_Detector::is_translatable( $excerpt ) ) {
			$strings[ md5( $excerpt ) ] = $excerpt;
		}

		// Featured image alt text: it lives in the attachment meta, not in
		// the post content, so it is collected explicitly.
		$thumbnail_id = get_post_thumbnail_id( $post );
		if ( $thumbnail_id ) {
			$thumb_alt = Locuentia_Detector::normalize_text( get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) );
			if ( Locuentia_Detector::is_translatable( $thumb_alt ) ) {
				$strings[ md5( $thumb_alt ) ] = $thumb_alt;
			}
		}

		// Configured translatable meta values (SEO titles/descriptions, custom fields).
		foreach ( Locuentia::meta_key_map() as $meta_key => $spec ) {
			$value = get_post_meta( $post->ID, $meta_key, true );

			if ( $spec['self'] && is_string( $value ) ) {
				$text = Locuentia_Detector::normalize_text( $value );
				if ( Locuentia_Detector::is_translatable( $text ) ) {
					$strings[ md5( $text ) ] = $text;
				}
			}

			if ( ! empty( $spec['children'] ) && is_array( $value ) ) {
				foreach ( $spec['children'] as $child ) {
					if ( isset( $value[ $child ] ) && is_string( $value[ $child ] ) ) {
						$text = Locuentia_Detector::normalize_text( $value[ $child ] );
						if ( Locuentia_Detector::is_translatable( $text ) ) {
							$strings[ md5( $text ) ] = $text;
						}
					}
				}
			}
		}

		$content = self::rendered_content( $post );
		if ( '' !== trim( $content ) ) {
			$strings = $strings + Locuentia_Detector::extract_strings( $content );
		}

		return $strings;
	}

	/**
	 * Content of a post as the front end renders it (the_content filters:
	 * blocks, shortcodes, wpautop and whatever builders hook there), so
	 * detection matches what visitors actually see, whoever generates it.
	 *
	 * Falls back to the raw content if rendering fails or returns nothing.
	 * Cached per request: list tables call this once per row.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	private static function rendered_content( $post ) {
		static $cache = array();

		if ( isset( $cache[ $post->ID ] ) ) {
			return $cache[ $post->ID ];
		}

		$html = '';

		try {
			$backup          = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
			$GLOBALS['post'] = $post;
			setup_postdata( $post );

			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- deliberately applying the core the_content filter to render like the front end.
			$html = (string) apply_filters( 'the_content', $post->post_content );
		} catch ( \Throwable $e ) {
			$html = '';
		} finally {
			wp_reset_postdata();
			$GLOBALS['post'] = $backup;
		}

		if ( '' === trim( $html ) && '' !== trim( (string) $post->post_content ) ) {
			// A third-party filter swallowed the content: fall back to the
			// raw content, mimicking the classic wpautop pipeline.
			$html = (string) $post->post_content;
			if ( ! has_blocks( $html ) ) {
				$html = wpautop( $html );
			}
		}

		$cache[ $post->ID ] = $html;

		return $html;
	}

	/**
	 * Renders the box with one translation field per text and language.
	 *
	 * @param WP_Post $post Post being edited.
	 */
	public static function render_meta_box( $post ) {
		$languages = Locuentia::get_languages();
		if ( empty( $languages ) ) {
			echo '<p>' . esc_html__( 'Set up at least one target language in the Locuentia settings.', 'locuentia' ) . '</p>';
			return;
		}

		$strings = self::detect_strings( $post );
		if ( empty( $strings ) ) {
			echo '<p>' . esc_html__( 'No translatable text detected. Write some content, save, and reload the editor.', 'locuentia' ) . '</p>';
			return;
		}

		wp_nonce_field( 'locuentia_save_' . $post->ID, 'locuentia_nonce' );

		$saved = get_post_meta( $post->ID, Locuentia::META_KEY, true );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		echo '<p class="description">' . esc_html__( 'Texts are detected from the last saved content. Leave a field empty to show the original text.', 'locuentia' ) . '</p>';

		foreach ( $languages as $lang ) {
			/* translators: %s: uppercase language code. */
			echo '<h3>' . esc_html( sprintf( __( 'Language: %s', 'locuentia' ), strtoupper( $lang ) ) ) . '</h3>';

			$slug = Locuentia::get_translated_slug( $post->ID, $lang );
			echo '<p><label>' . esc_html__( 'Translated slug (optional):', 'locuentia' ) . ' ';
			echo '<input type="text" class="regular-text" name="locuentia_slug[' . esc_attr( $lang ) . ']" value="' . esc_attr( $slug ) . '" placeholder="' . esc_attr( $post->post_name ) . '" />';
			echo '</label><br /><span class="description">'
				. esc_html( sprintf( /* translators: %s: language code. */ __( 'Changes the URL in this language, e.g. /%s/my-translated-slug/. Empty = same slug as the original.', 'locuentia' ), $lang ) )
				. '</span>';

			// Collisions can appear after saving (another post adopting this
			// slug), so the stored value is re-checked on every render.
			$collision = '' !== $slug ? Locuentia::find_slug_collision( $slug, $lang, $post->ID ) : null;
			if ( $collision ) {
				echo '<br /><span class="locuentia-slug-warning">'
					. sprintf(
						/* translators: %s: title of the colliding content. */
						esc_html__( 'Warning: this slug is also in use by “%s”, which becomes unreachable in this language. Consider changing it.', 'locuentia' ),
						esc_html( $collision->post_title )
					)
					. '</span>';
			}

			echo '</p>';

			echo '<table class="widefat striped">';
			echo '<thead><tr>';
			echo '<th class="locuentia-col-original">' . esc_html__( 'Original text', 'locuentia' ) . '</th>';
			echo '<th>' . esc_html__( 'Translation', 'locuentia' ) . '</th>';
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
	 * Saves the translations submitted from the box.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Saved post.
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

		if ( isset( $_POST['locuentia_tr'] ) && is_array( $_POST['locuentia_tr'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized item by item in update_translations().
			self::update_translations( $post_id, wp_unslash( $_POST['locuentia_tr'] ) );
		}

		if ( isset( $_POST['locuentia_slug'] ) && is_array( $_POST['locuentia_slug'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized with sanitize_title() in update_translated_slugs().
			$rejected = self::update_translated_slugs( $post_id, wp_unslash( $_POST['locuentia_slug'] ) );

			if ( ! empty( $rejected ) ) {
				set_transient( 'locuentia_slug_rejected_' . get_current_user_id(), $rejected, 5 * MINUTE_IN_SECONDS );
			}
		}
	}

	/**
	 * Sanitizes and stores submitted translations, merging per language:
	 * only the languages present in the input are rebuilt, the rest keep
	 * their stored values (allows saving a single language at a time).
	 *
	 * @param int   $post_id Post ID.
	 * @param array $raw     Unslashed input: lang => ( hash => text ).
	 */
	public static function update_translations( $post_id, array $raw ) {
		$languages = Locuentia::get_languages();

		$data = get_post_meta( $post_id, Locuentia::META_KEY, true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		foreach ( $raw as $lang => $items ) {
			$lang = Locuentia::sanitize_language_code( $lang );
			if ( '' === $lang || ! in_array( $lang, $languages, true ) || ! is_array( $items ) ) {
				continue;
			}

			$clean = array();

			foreach ( $items as $hash => $value ) {
				if ( ! is_string( $value ) || ! preg_match( '/^[a-f0-9]{32}$/', (string) $hash ) ) {
					continue;
				}

				$value = sanitize_textarea_field( $value );
				if ( '' !== $value ) {
					$clean[ $hash ] = $value;
				}
			}

			if ( empty( $clean ) ) {
				unset( $data[ $lang ] );
			} else {
				$data[ $lang ] = $clean;
			}
		}

		if ( empty( $data ) ) {
			delete_post_meta( $post_id, Locuentia::META_KEY );
		} else {
			update_post_meta( $post_id, Locuentia::META_KEY, $data );
		}
	}

	/**
	 * Sanitizes and stores submitted site-wide translations, merging per
	 * language and per hash: only the hashes present in the input change.
	 * Empty values remove the stored translation for that hash.
	 *
	 * @param array $raw Unslashed input: lang => ( hash => text ).
	 */
	public static function update_site_translations( array $raw ) {
		$languages = Locuentia::get_languages();

		$data = get_option( Locuentia::OPTION_SITE_TRANSLATIONS, array() );
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		foreach ( $raw as $lang => $items ) {
			$lang = Locuentia::sanitize_language_code( $lang );
			if ( '' === $lang || ! in_array( $lang, $languages, true ) || ! is_array( $items ) ) {
				continue;
			}

			if ( ! isset( $data[ $lang ] ) || ! is_array( $data[ $lang ] ) ) {
				$data[ $lang ] = array();
			}

			foreach ( $items as $hash => $value ) {
				if ( ! is_string( $value ) || ! preg_match( '/^[a-f0-9]{32}$/', (string) $hash ) ) {
					continue;
				}

				$value = sanitize_textarea_field( $value );

				if ( '' === $value ) {
					unset( $data[ $lang ][ $hash ] );
				} else {
					$data[ $lang ][ $hash ] = $value;
				}
			}

			if ( empty( $data[ $lang ] ) ) {
				unset( $data[ $lang ] );
			}
		}

		if ( empty( $data ) ) {
			delete_option( Locuentia::OPTION_SITE_TRANSLATIONS );
		} else {
			update_option( Locuentia::OPTION_SITE_TRANSLATIONS, $data );
		}
	}

	/**
	 * Sanitizes and stores submitted translated slugs (one meta per language,
	 * only for the languages present in the input), rejecting collisions.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $raw     Unslashed input: lang => slug.
	 * @return array Rejected slugs: array of ( lang, slug, with_id ).
	 */
	public static function update_translated_slugs( $post_id, array $raw ) {
		$languages = Locuentia::get_languages();
		$rejected  = array();

		foreach ( $languages as $lang ) {
			if ( ! isset( $raw[ $lang ] ) || ! is_string( $raw[ $lang ] ) ) {
				continue;
			}

			$slug     = sanitize_title( $raw[ $lang ] );
			$meta_key = Locuentia::SLUG_META_PREFIX . $lang;

			if ( '' === $slug ) {
				delete_post_meta( $post_id, $meta_key );
				continue;
			}

			// A colliding slug is rejected (the previous value, if any,
			// is kept) and reported via an admin notice.
			$collision = Locuentia::find_slug_collision( $slug, $lang, $post_id );
			if ( $collision ) {
				$rejected[] = array(
					'lang'    => $lang,
					'slug'    => $slug,
					'with_id' => $collision->ID,
				);
				continue;
			}

			update_post_meta( $post_id, $meta_key, $slug );
		}

		return $rejected;
	}
}
