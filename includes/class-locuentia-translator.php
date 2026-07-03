<?php
/**
 * Translate screen: the translation queue and the focused per-post editor.
 *
 * Same fields and saving pipeline as the editor meta box, but full width,
 * one language at a time, and reachable from a single place.
 */

defined( 'ABSPATH' ) || exit;

class Locuentia_Translator {

	public static function init() {
		add_action( 'admin_post_locuentia_save_translation', array( __CLASS__, 'handle_save' ) );
	}

	/**
	 * Router of the Locuentia top-level page: queue or per-post editor.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		// Read-only navigation parameters.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

		if ( $post_id ) {
			self::render_editor( $post_id );
			return;
		}

		self::render_queue();
	}

	/* ---------- Queue ---------- */

	/**
	 * Renders the translation queue: all translatable content with its
	 * per-language progress.
	 */
	private static function render_queue() {
		$languages = Locuentia::get_languages();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

		$query = new WP_Query(
			array(
				'post_type'      => Locuentia::post_types(),
				'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page' => 20,
				'paged'          => $paged,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Translate', 'locuentia' ); ?></h1>

			<?php if ( empty( $languages ) ) : ?>
				<p><?php esc_html_e( 'Set up at least one target language in the Locuentia settings.', 'locuentia' ); ?></p>
			<?php elseif ( ! $query->have_posts() ) : ?>
				<p><?php esc_html_e( 'No translatable content found.', 'locuentia' ); ?></p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'Pick a content item to translate it. Badges show translated/total texts per language.', 'locuentia' ); ?></p>

				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Title', 'locuentia' ); ?></th>
							<th><?php esc_html_e( 'Type', 'locuentia' ); ?></th>
							<th><?php esc_html_e( 'Progress', 'locuentia' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $query->posts as $post ) {
							$translate_url = add_query_arg(
								array(
									'page' => 'locuentia',
									'post' => $post->ID,
								),
								admin_url( 'admin.php' )
							);

							$type  = get_post_type_object( $post->post_type );
							$title = get_the_title( $post );
							$title = '' !== $title ? $title : __( '(no title)', 'locuentia' );

							echo '<tr>';
							echo '<td><strong><a href="' . esc_url( $translate_url ) . '">' . esc_html( $title ) . '</a></strong></td>';
							echo '<td>' . esc_html( $type ? $type->labels->singular_name : $post->post_type ) . '</td>';
							echo '<td>';
							Locuentia_Admin::render_progress_badges( $post );
							echo '</td>';
							echo '</tr>';
						}
						?>
					</tbody>
				</table>

				<?php
				$total_pages = (int) $query->max_num_pages;
				if ( $total_pages > 1 ) {
					$links = paginate_links(
						array(
							'base'    => add_query_arg( 'paged', '%#%' ),
							'format'  => '',
							'current' => $paged,
							'total'   => $total_pages,
						)
					);
					if ( $links ) {
						echo '<p class="locuentia-pagination">' . wp_kses_post( $links ) . '</p>';
					}
				}
				?>
			<?php endif; ?>
		</div>
		<?php
	}

	/* ---------- Per-post editor ---------- */

	/**
	 * Renders the focused translation editor for one post and language.
	 *
	 * @param int $post_id Post to translate.
	 */
	private static function render_editor( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post || ! in_array( $post->post_type, Locuentia::post_types(), true ) || ! current_user_can( 'edit_post', $post_id ) ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Translate', 'locuentia' ) . '</h1><p>' . esc_html__( 'This content cannot be translated.', 'locuentia' ) . '</p></div>';
			return;
		}

		$languages = Locuentia::get_languages();
		if ( empty( $languages ) ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Translate', 'locuentia' ) . '</h1><p>' . esc_html__( 'Set up at least one target language in the Locuentia settings.', 'locuentia' ) . '</p></div>';
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$lang = isset( $_GET['lang'] ) ? Locuentia::sanitize_language_code( sanitize_key( wp_unslash( $_GET['lang'] ) ) ) : '';
		if ( '' === $lang || ! in_array( $lang, $languages, true ) ) {
			$lang = $languages[0];
		}

		$strings = Locuentia_Admin::detect_strings( $post );
		$saved   = Locuentia::get_post_translations( $post_id, $lang );
		$slug    = Locuentia::get_translated_slug( $post_id, $lang );

		// Page-level texts: everything the served page contains that the
		// post inventory does not cover (builder output, menus, theme).
		$page_strings = array();
		$page_error   = '';
		if ( 'publish' === $post->post_status ) {
			$page_strings = self::detect_page_strings( $post, $page_error );
			$page_strings = array_diff_key( $page_strings, $strings );
		}
		$site_saved = Locuentia::get_site_translations( $lang );

		$queue_url = add_query_arg( 'page', 'locuentia', admin_url( 'admin.php' ) );

		$title = get_the_title( $post );
		$title = '' !== $title ? $title : __( '(no title)', 'locuentia' );
		?>
		<div class="wrap locuentia-translator">
			<h1>
				<?php
				/* translators: %s: content title. */
				echo esc_html( sprintf( __( 'Translate: %s', 'locuentia' ), $title ) );
				?>
			</h1>

			<p>
				<a href="<?php echo esc_url( $queue_url ); ?>">&larr; <?php esc_html_e( 'Back to the list', 'locuentia' ); ?></a>
				&nbsp;|&nbsp;
				<a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>"><?php esc_html_e( 'Edit content', 'locuentia' ); ?></a>
				&nbsp;|&nbsp;
				<a href="<?php echo esc_url( Locuentia_Router::permalink_for_language( $post, $lang ) ); ?>" target="_blank"><?php esc_html_e( 'View in this language', 'locuentia' ); ?></a>
			</p>

			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['updated'] ) ) {
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Translations saved.', 'locuentia' ) . '</p></div>';
			}
			?>

			<nav class="nav-tab-wrapper">
				<?php
				foreach ( $languages as $code ) {
					$tab_url = add_query_arg(
						array(
							'page' => 'locuentia',
							'post' => $post_id,
							'lang' => $code,
						),
						admin_url( 'admin.php' )
					);

					echo '<a href="' . esc_url( $tab_url ) . '" class="nav-tab' . ( $code === $lang ? ' nav-tab-active' : '' ) . '">'
						. esc_html( Locuentia::language_label( $code ) . ' (' . strtoupper( $code ) . ')' )
						. '</a>';
				}
				?>
			</nav>

			<?php if ( empty( $strings ) ) : ?>
				<p><?php esc_html_e( 'No translatable text detected. Write some content first.', 'locuentia' ); ?></p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="locuentia_save_translation" />
					<input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>" />
					<input type="hidden" name="lang" value="<?php echo esc_attr( $lang ); ?>" />
					<?php wp_nonce_field( 'locuentia_translate_' . $post_id, 'locuentia_translate_nonce' ); ?>

					<p>
						<label><?php esc_html_e( 'Translated slug (optional):', 'locuentia' ); ?>
							<input type="text" class="regular-text" name="locuentia_slug[<?php echo esc_attr( $lang ); ?>]" value="<?php echo esc_attr( $slug ); ?>" placeholder="<?php echo esc_attr( $post->post_name ); ?>" />
						</label>
					</p>

					<table class="widefat striped">
						<thead>
							<tr>
								<th class="locuentia-col-original"><?php esc_html_e( 'Original text', 'locuentia' ); ?></th>
								<th><?php esc_html_e( 'Translation', 'locuentia' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $strings as $hash => $text ) : ?>
								<tr>
									<td><?php echo esc_html( $text ); ?></td>
									<td><textarea rows="2" name="locuentia_tr[<?php echo esc_attr( $lang ); ?>][<?php echo esc_attr( $hash ); ?>]"><?php echo esc_textarea( isset( $saved[ $hash ] ) ? $saved[ $hash ] : '' ); ?></textarea></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<?php if ( ! empty( $page_strings ) ) : ?>
						<h2><?php esc_html_e( 'Page texts', 'locuentia' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Detected from the page as it is actually served (builder output, menus, widgets and theme texts). These translations are saved site-wide: translating a text here applies wherever it appears.', 'locuentia' ); ?></p>

						<table class="widefat striped">
							<thead>
								<tr>
									<th class="locuentia-col-original"><?php esc_html_e( 'Original text', 'locuentia' ); ?></th>
									<th><?php esc_html_e( 'Translation', 'locuentia' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $page_strings as $hash => $text ) : ?>
									<tr>
										<td><?php echo esc_html( $text ); ?></td>
										<td><textarea rows="2" name="locuentia_site_tr[<?php echo esc_attr( $lang ); ?>][<?php echo esc_attr( $hash ); ?>]"><?php echo esc_textarea( isset( $site_saved[ $hash ] ) ? $site_saved[ $hash ] : '' ); ?></textarea></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php elseif ( '' !== $page_error ) : ?>
						<p class="description"><?php echo esc_html( $page_error ); ?></p>
					<?php endif; ?>

					<?php submit_button( __( 'Save translations', 'locuentia' ) ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Texts of the page as it is actually served, via an internal loopback
	 * request to the post's original (unprefixed) URL.
	 *
	 * @param WP_Post $post   Post whose page is fetched.
	 * @param string  $error  Filled with a user-facing message on failure.
	 * @return array array( hash => text ).
	 */
	private static function detect_page_strings( $post, &$error ) {
		static $cache = array();

		if ( isset( $cache[ $post->ID ] ) ) {
			return $cache[ $post->ID ];
		}

		$error = '';
		$url   = Locuentia_Router::unlocalized_permalink( $post );

		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 15,
				'sslverify' => false,
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			$error = __( 'The rendered page could not be fetched, so page-level texts (builder, menus, theme) are not listed.', 'locuentia' );

			$cache[ $post->ID ] = array();
			return array();
		}

		$strings = Locuentia_Detector::extract_document_strings( wp_remote_retrieve_body( $response ) );

		$cache[ $post->ID ] = $strings;

		return $strings;
	}

	/* ---------- Saving ---------- */

	/**
	 * admin-post.php handler: saves one language of one post and redirects
	 * back to the editor.
	 */
	public static function handle_save() {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		$nonce = isset( $_POST['locuentia_translate_nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['locuentia_translate_nonce'] ) )
			: '';

		if ( ! $post_id || ! wp_verify_nonce( $nonce, 'locuentia_translate_' . $post_id ) ) {
			wp_die( esc_html__( 'The link you followed has expired.', 'locuentia' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, Locuentia::post_types(), true ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to translate this content.', 'locuentia' ) );
		}

		$lang = isset( $_POST['lang'] ) ? Locuentia::sanitize_language_code( sanitize_key( wp_unslash( $_POST['lang'] ) ) ) : '';

		if ( isset( $_POST['locuentia_tr'] ) && is_array( $_POST['locuentia_tr'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized item by item in update_translations().
			Locuentia_Admin::update_translations( $post_id, wp_unslash( $_POST['locuentia_tr'] ) );
		}

		if ( isset( $_POST['locuentia_site_tr'] ) && is_array( $_POST['locuentia_site_tr'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized item by item in update_site_translations().
			Locuentia_Admin::update_site_translations( wp_unslash( $_POST['locuentia_site_tr'] ) );
		}

		if ( isset( $_POST['locuentia_slug'] ) && is_array( $_POST['locuentia_slug'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized with sanitize_title() in update_translated_slugs().
			$rejected = Locuentia_Admin::update_translated_slugs( $post_id, wp_unslash( $_POST['locuentia_slug'] ) );

			if ( ! empty( $rejected ) ) {
				set_transient( 'locuentia_slug_rejected_' . get_current_user_id(), $rejected, 5 * MINUTE_IN_SECONDS );
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'locuentia',
					'post'    => $post_id,
					'lang'    => $lang,
					'updated' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
