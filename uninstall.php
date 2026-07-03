<?php
/**
 * Uninstall cleanup: removes the options and every stored translation.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'locuentia_languages' );
delete_option( 'locuentia_source_language' );
delete_option( 'locuentia_site_translations' );
delete_option( 'locuentia_flush_rewrite' );
delete_post_meta_by_key( '_locuentia_translations' );

// Translated slugs: one meta per language (_locuentia_slug_en, …),
// including languages no longer configured. One-off bulk delete on
// uninstall; there is no high-level API for LIKE and no cache to
// invalidate afterwards.
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( '_locuentia_slug_' ) . '%'
	)
);
