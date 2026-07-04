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
		add_action( 'admin_post_locuentia_save_terms', array( __CLASS__, 'handle_save_terms' ) );
	}

	/**
	 * Router of the Locuentia top-level page: queue, per-post editor or
	 * taxonomy terms editor.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		// Read-only navigation parameters.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$view    = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( 'terms' === $view ) {
			self::render_terms_editor();
			return;
		}

		if ( $post_id ) {
			self::render_editor( $post_id );
			return;
		}

		self::render_queue();
	}

	/**
	 * Public taxonomies whose terms are offered for translation.
	 *
	 * @return string[] Taxonomy names.
	 */
	private static function translatable_taxonomies() {
		$taxonomies = array();

		foreach ( get_object_taxonomies( Locuentia::post_types(), 'objects' ) as $taxonomy ) {
			if ( ! empty( $taxonomy->public ) && ! empty( $taxonomy->show_ui ) ) {
				$taxonomies[] = $taxonomy->name;
			}
		}

		return apply_filters( 'locuentia_taxonomies', $taxonomies );
	}

	/* ---------- Queue ---------- */

	/**
	 * Post statuses listed in the queue.
	 *
	 * @return string[]
	 */
	private static function queue_statuses() {
		return array( 'publish', 'future', 'draft', 'pending', 'private' );
	}

	/**
	 * Renders the translation queue: all translatable content with its
	 * per-language progress, optionally filtered by language/status/type.
	 */
	private static function render_queue() {
		$languages = Locuentia::get_languages();
		$per_page  = 20;

		// Read-only listing filters.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$paged   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$flang   = isset( $_GET['flang'] ) ? Locuentia::sanitize_language_code( sanitize_key( wp_unslash( $_GET['flang'] ) ) ) : '';
		$fstatus = isset( $_GET['fstatus'] ) ? sanitize_key( wp_unslash( $_GET['fstatus'] ) ) : '';
		$ftype   = isset( $_GET['ftype'] ) ? sanitize_key( wp_unslash( $_GET['ftype'] ) ) : '';
		$fsearch = isset( $_GET['fsearch'] ) ? sanitize_text_field( wp_unslash( $_GET['fsearch'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! in_array( $flang, $languages, true ) ) {
			$flang = '';
		}
		if ( ! in_array( $fstatus, array( 'untranslated', 'partial', 'complete' ), true ) ) {
			$fstatus = '';
		}
		if ( ! in_array( $ftype, Locuentia::post_types(), true ) ) {
			$ftype = '';
		}

		$post_types = '' !== $ftype ? array( $ftype ) : Locuentia::post_types();

		$rows        = array();
		$total_pages = 0;

		if ( '' !== $fstatus && ! empty( $languages ) ) {
			// Status filters evaluate the progress of every item, so the
			// whole list is scanned and paginated manually.
			$status_lang = '' !== $flang ? $flang : $languages[0];

			$ids = get_posts(
				array(
					'post_type'      => $post_types,
					'post_status'    => self::queue_statuses(),
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'orderby'        => 'modified',
					'order'          => 'DESC',
					'no_found_rows'  => true,
					's'              => $fsearch,
				)
			);

			$matches = array();

			foreach ( $ids as $id ) {
				$item = get_post( $id );
				if ( ! $item ) {
					continue;
				}

				$progress = Locuentia_Admin::translation_progress( $item, $status_lang );
				if ( 0 === $progress['total'] ) {
					continue;
				}

				$state = $progress['done'] >= $progress['total'] ? 'complete' : ( $progress['done'] > 0 ? 'partial' : 'untranslated' );

				if ( $state === $fstatus ) {
					$matches[] = $item;
				}
			}

			$total_pages = (int) ceil( count( $matches ) / $per_page );
			$rows        = array_slice( $matches, ( $paged - 1 ) * $per_page, $per_page );
		} else {
			$query = new WP_Query(
				array(
					'post_type'      => $post_types,
					'post_status'    => self::queue_statuses(),
					'posts_per_page' => $per_page,
					'paged'          => $paged,
					'orderby'        => 'modified',
					'order'          => 'DESC',
					's'              => $fsearch,
				)
			);

			$rows        = $query->posts;
			$total_pages = (int) $query->max_num_pages;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Translate', 'locuentia' ); ?></h1>

			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['done'] ) ) {
				echo '<div class="notice notice-success"><p>' . esc_html__( 'All caught up — no pending content found in this language.', 'locuentia' ) . '</p></div>';
			}
			?>

			<?php if ( empty( $languages ) ) : ?>
				<p><?php esc_html_e( 'Set up at least one target language in the Locuentia settings.', 'locuentia' ); ?></p>
			<?php else : ?>
				<form method="get" class="locuentia-filters">
					<input type="hidden" name="page" value="locuentia" />

					<label>
						<?php esc_html_e( 'Language:', 'locuentia' ); ?>
						<select name="flang">
							<option value=""><?php esc_html_e( 'All languages', 'locuentia' ); ?></option>
							<?php foreach ( $languages as $code ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $flang, $code ); ?>><?php echo esc_html( Locuentia::language_label( $code ) . ' (' . strtoupper( $code ) . ')' ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>

					<label>
						<?php esc_html_e( 'Status:', 'locuentia' ); ?>
						<select name="fstatus">
							<option value=""><?php esc_html_e( 'Any status', 'locuentia' ); ?></option>
							<option value="untranslated" <?php selected( $fstatus, 'untranslated' ); ?>><?php esc_html_e( 'Untranslated', 'locuentia' ); ?></option>
							<option value="partial" <?php selected( $fstatus, 'partial' ); ?>><?php esc_html_e( 'In progress', 'locuentia' ); ?></option>
							<option value="complete" <?php selected( $fstatus, 'complete' ); ?>><?php esc_html_e( 'Complete', 'locuentia' ); ?></option>
						</select>
					</label>

					<label>
						<?php esc_html_e( 'Type:', 'locuentia' ); ?>
						<select name="ftype">
							<option value=""><?php esc_html_e( 'Any type', 'locuentia' ); ?></option>
							<?php foreach ( Locuentia::post_types() as $type ) : ?>
								<?php $type_object = get_post_type_object( $type ); ?>
								<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $ftype, $type ); ?>><?php echo esc_html( $type_object ? $type_object->labels->singular_name : $type ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>

					<input type="search" name="fsearch" value="<?php echo esc_attr( $fsearch ); ?>" placeholder="<?php esc_attr_e( 'Search…', 'locuentia' ); ?>" aria-label="<?php esc_attr_e( 'Search content', 'locuentia' ); ?>" />

					<?php submit_button( __( 'Filter', 'locuentia' ), 'secondary', '', false ); ?>

					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'locuentia', 'view' => 'terms' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Translate site texts →', 'locuentia' ); ?></a>
				</form>

				<?php if ( empty( $rows ) ) : ?>
					<p><?php esc_html_e( 'No content matches the current filters.', 'locuentia' ); ?></p>
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
							foreach ( $rows as $item ) {
								$translate_args = array(
									'page' => 'locuentia',
									'post' => $item->ID,
								);
								if ( '' !== $flang ) {
									$translate_args['lang'] = $flang;
								}
								$translate_url = add_query_arg( $translate_args, admin_url( 'admin.php' ) );

								$type  = get_post_type_object( $item->post_type );
								$title = get_the_title( $item );
								$title = '' !== $title ? $title : __( '(no title)', 'locuentia' );

								echo '<tr>';
								echo '<td><strong><a href="' . esc_url( $translate_url ) . '">' . esc_html( $title ) . '</a></strong></td>';
								echo '<td>' . esc_html( $type ? $type->labels->singular_name : $item->post_type ) . '</td>';
								echo '<td>';
								Locuentia_Admin::render_progress_badges( $item );
								echo '</td>';
								echo '</tr>';
							}
							?>
						</tbody>
					</table>

					<?php
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

		$memory = Locuentia::translation_memory( $lang );

		// Whether any empty field has a memory suggestion (enables Apply all).
		$has_suggestions = false;
		foreach ( $strings as $hash => $text ) {
			if ( isset( $memory[ $hash ] ) && ! isset( $saved[ $hash ] ) ) {
				$has_suggestions = true;
				break;
			}
		}
		if ( ! $has_suggestions ) {
			foreach ( $page_strings as $hash => $text ) {
				if ( isset( $memory[ $hash ] ) && ! isset( $site_saved[ $hash ] ) ) {
					$has_suggestions = true;
					break;
				}
			}
		}

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

					// Pending texts in this language: post inventory plus the
					// page-level texts of this page not yet translated site-wide.
					$pending  = count( array_diff_key( $strings, Locuentia::get_post_translations( $post_id, $code ) ) );
					$pending += count( array_diff_key( $page_strings, Locuentia::get_site_translations( $code ) ) );

					$count_html = $pending > 0
						? '<span class="locuentia-tab-count">' . (int) $pending . '</span>'
						: '<span class="locuentia-tab-done" aria-hidden="true">✓</span>';

					echo '<a href="' . esc_url( $tab_url ) . '" class="nav-tab' . ( $code === $lang ? ' nav-tab-active' : '' ) . '">'
						. esc_html( Locuentia::language_label( $code ) . ' (' . strtoupper( $code ) . ')' ) . ' ' . $count_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped/int parts above.
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

					<?php if ( $has_suggestions ) : ?>
						<p>
							<button type="button" class="button locuentia-apply-all"><?php esc_html_e( 'Apply all memory suggestions', 'locuentia' ); ?></button>
							<span class="description"><?php esc_html_e( 'Fills the empty fields with translations already used elsewhere on the site. Nothing is saved until you save.', 'locuentia' ); ?></span>
						</p>
					<?php endif; ?>

					<table class="widefat striped">
						<thead>
							<tr>
								<th class="locuentia-col-original"><?php esc_html_e( 'Original text', 'locuentia' ); ?></th>
								<th><?php esc_html_e( 'Translation', 'locuentia' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $strings as $hash => $text ) : ?>
								<?php $value = isset( $saved[ $hash ] ) ? $saved[ $hash ] : ''; ?>
								<tr>
									<td><?php echo esc_html( $text ); ?></td>
									<td>
										<textarea rows="2" name="locuentia_tr[<?php echo esc_attr( $lang ); ?>][<?php echo esc_attr( $hash ); ?>]"><?php echo esc_textarea( $value ); ?></textarea>
										<?php Locuentia_Admin::render_memory_suggestion( $hash, $value, $memory ); ?>
									</td>
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
									<?php $value = isset( $site_saved[ $hash ] ) ? $site_saved[ $hash ] : ''; ?>
									<tr>
										<td><?php echo esc_html( $text ); ?></td>
										<td>
											<textarea rows="2" name="locuentia_site_tr[<?php echo esc_attr( $lang ); ?>][<?php echo esc_attr( $hash ); ?>]"><?php echo esc_textarea( $value ); ?></textarea>
											<?php Locuentia_Admin::render_memory_suggestion( $hash, $value, $memory ); ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php elseif ( '' !== $page_error ) : ?>
						<p class="description"><?php echo esc_html( $page_error ); ?></p>
					<?php endif; ?>

					<p class="submit">
						<?php submit_button( __( 'Save translations', 'locuentia' ), 'primary', 'submit', false ); ?>
						<?php submit_button( __( 'Save & translate next pending', 'locuentia' ), 'secondary', 'locuentia_next', false ); ?>
					</p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders the taxonomy terms editor: names and descriptions of the
	 * terms of public taxonomies, stored site-wide so they apply on
	 * archives, listings and widgets alike.
	 */
	private static function render_terms_editor() {
		$languages = Locuentia::get_languages();

		if ( empty( $languages ) ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Site texts', 'locuentia' ) . '</h1><p>' . esc_html__( 'Set up at least one target language in the Locuentia settings.', 'locuentia' ) . '</p></div>';
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$lang = isset( $_GET['lang'] ) ? Locuentia::sanitize_language_code( sanitize_key( wp_unslash( $_GET['lang'] ) ) ) : '';
		if ( '' === $lang || ! in_array( $lang, $languages, true ) ) {
			$lang = $languages[0];
		}

		$limit = 200;
		$terms = get_terms(
			array(
				'taxonomy'   => self::translatable_taxonomies(),
				'hide_empty' => false,
				'number'     => $limit,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);
		$terms = is_wp_error( $terms ) ? array() : $terms;

		// One row per translatable string: site identity plus term names
		// and descriptions.
		$rows = array();

		$blogname = Locuentia_Detector::normalize_text( get_option( 'blogname' ) );
		if ( Locuentia_Detector::is_translatable( $blogname ) ) {
			$rows[ md5( $blogname ) ] = array( $blogname, __( 'Site title', 'locuentia' ) );
		}

		$tagline = Locuentia_Detector::normalize_text( get_option( 'blogdescription' ) );
		if ( Locuentia_Detector::is_translatable( $tagline ) ) {
			$rows[ md5( $tagline ) ] = array( $tagline, __( 'Tagline', 'locuentia' ) );
		}

		foreach ( $terms as $term ) {
			$taxonomy = get_taxonomy( $term->taxonomy );
			$label    = $taxonomy ? $taxonomy->labels->singular_name : $term->taxonomy;

			$name = Locuentia_Detector::normalize_text( $term->name );
			if ( Locuentia_Detector::is_translatable( $name ) ) {
				/* translators: %s: taxonomy singular name. */
				$rows[ md5( $name ) ] = array( $name, sprintf( __( '%s · name', 'locuentia' ), $label ) );
			}

			$description = Locuentia_Detector::normalize_text( $term->description );
			if ( Locuentia_Detector::is_translatable( $description ) ) {
				/* translators: %s: taxonomy singular name. */
				$rows[ md5( $description ) ] = array( $description, sprintf( __( '%s · description', 'locuentia' ), $label ) );
			}
		}

		$site_saved = Locuentia::get_site_translations( $lang );
		$memory     = Locuentia::translation_memory( $lang );

		$has_suggestions = false;
		foreach ( $rows as $hash => $row ) {
			if ( isset( $memory[ $hash ] ) && ! isset( $site_saved[ $hash ] ) ) {
				$has_suggestions = true;
				break;
			}
		}

		$queue_url = add_query_arg( 'page', 'locuentia', admin_url( 'admin.php' ) );
		?>
		<div class="wrap locuentia-translator">
			<h1><?php esc_html_e( 'Translate: Site texts', 'locuentia' ); ?></h1>

			<p><a href="<?php echo esc_url( $queue_url ); ?>">&larr; <?php esc_html_e( 'Back to the list', 'locuentia' ); ?></a></p>

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
							'view' => 'terms',
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

			<?php if ( empty( $rows ) ) : ?>
				<p><?php esc_html_e( 'No translatable terms found.', 'locuentia' ); ?></p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'The site title, tagline and term names/descriptions are saved site-wide: they apply on archives, listings, widgets, document titles and wherever the text appears.', 'locuentia' ); ?></p>

				<?php if ( count( $terms ) >= $limit ) : ?>
					<p class="description"><?php echo esc_html( sprintf( /* translators: %d: number of terms shown. */ __( 'Showing the %d most used terms.', 'locuentia' ), $limit ) ); ?></p>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="locuentia_save_terms" />
					<input type="hidden" name="lang" value="<?php echo esc_attr( $lang ); ?>" />
					<?php wp_nonce_field( 'locuentia_translate_terms', 'locuentia_terms_nonce' ); ?>

					<?php if ( $has_suggestions ) : ?>
						<p>
							<button type="button" class="button locuentia-apply-all"><?php esc_html_e( 'Apply all memory suggestions', 'locuentia' ); ?></button>
							<span class="description"><?php esc_html_e( 'Fills the empty fields with translations already used elsewhere on the site. Nothing is saved until you save.', 'locuentia' ); ?></span>
						</p>
					<?php endif; ?>

					<table class="widefat striped">
						<thead>
							<tr>
								<th class="locuentia-col-original"><?php esc_html_e( 'Original text', 'locuentia' ); ?></th>
								<th><?php esc_html_e( 'Translation', 'locuentia' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $rows as $hash => $row ) : ?>
								<?php $value = isset( $site_saved[ $hash ] ) ? $site_saved[ $hash ] : ''; ?>
								<tr>
									<td>
										<?php echo esc_html( $row[0] ); ?>
										<br /><span class="description"><?php echo esc_html( $row[1] ); ?></span>
									</td>
									<td>
										<textarea rows="2" name="locuentia_site_tr[<?php echo esc_attr( $lang ); ?>][<?php echo esc_attr( $hash ); ?>]"><?php echo esc_textarea( $value ); ?></textarea>
										<?php Locuentia_Admin::render_memory_suggestion( $hash, $value, $memory ); ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<?php submit_button( __( 'Save translations', 'locuentia' ) ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * admin-post.php handler for the taxonomy terms editor.
	 */
	public static function handle_save_terms() {
		$nonce = isset( $_POST['locuentia_terms_nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['locuentia_terms_nonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, 'locuentia_translate_terms' ) ) {
			wp_die( esc_html__( 'The link you followed has expired.', 'locuentia' ) );
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You are not allowed to translate this content.', 'locuentia' ) );
		}

		$lang = isset( $_POST['lang'] ) ? Locuentia::sanitize_language_code( sanitize_key( wp_unslash( $_POST['lang'] ) ) ) : '';

		if ( isset( $_POST['locuentia_site_tr'] ) && is_array( $_POST['locuentia_site_tr'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized item by item in update_site_translations().
			Locuentia_Admin::update_site_translations( wp_unslash( $_POST['locuentia_site_tr'] ) );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'locuentia',
					'view'    => 'terms',
					'lang'    => $lang,
					'updated' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
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

		if ( isset( $_POST['locuentia_next'] ) && '' !== $lang ) {
			$next_id = self::find_next_pending( $post_id, $lang );

			if ( $next_id ) {
				wp_safe_redirect(
					add_query_arg(
						array(
							'page'    => 'locuentia',
							'post'    => $next_id,
							'lang'    => $lang,
							'updated' => 1,
						),
						admin_url( 'admin.php' )
					)
				);
				exit;
			}

			wp_safe_redirect(
				add_query_arg(
					array(
						'page' => 'locuentia',
						'done' => 1,
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
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

	/**
	 * Next content with pending texts in a language, starting after the
	 * current one in queue order and wrapping around.
	 *
	 * @param int    $current_id Post just saved.
	 * @param string $lang       Language code.
	 * @return int Next post ID, or 0 when nothing is pending.
	 */
	public static function find_next_pending( $current_id, $lang ) {
		$ids = get_posts(
			array(
				'post_type'      => Locuentia::post_types(),
				'post_status'    => self::queue_statuses(),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		$position = array_search( (int) $current_id, array_map( 'intval', $ids ), true );
		$ordered  = false === $position
			? $ids
			: array_merge( array_slice( $ids, $position + 1 ), array_slice( $ids, 0, $position ) );

		foreach ( $ordered as $id ) {
			$item = get_post( $id );
			if ( ! $item || ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}

			$progress = Locuentia_Admin::translation_progress( $item, $lang );
			if ( $progress['total'] > 0 && $progress['done'] < $progress['total'] ) {
				return (int) $id;
			}
		}

		return 0;
	}
}
