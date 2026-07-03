<?php
/**
 * Extraction and replacement of translatable texts in HTML.
 *
 * Uses DOM only (no WordPress functions) so it can be tested in isolation.
 * Each text is identified by the MD5 hash of its normalized version, so
 * detection in the editor and replacement on the front end match even when
 * whitespace, entities or wptexturize typography differ.
 */

defined( 'ABSPATH' ) || exit;

class Locuentia_Detector {

	/**
	 * Normalizes a text so it can be identified in a stable way.
	 *
	 * @param string $text Original text.
	 * @return string
	 */
	public static function normalize_text( $text ) {
		$text = html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Neutralize wptexturize typographic transformations (curly quotes,
		// long dashes, nbsp…) so the hash is the same before and after the
		// content is rendered.
		$text = strtr(
			$text,
			array(
				"\xC2\xA0" => ' ',
				'“'        => '"',
				'”'        => '"',
				'„'        => '"',
				'«'        => '"',
				'»'        => '"',
				'‘'        => "'",
				'’'        => "'",
				'–'        => '-',
				'—'        => '-',
				'…'        => '...',
			)
		);

		$text = preg_replace( '/\s+/u', ' ', $text );

		return trim( (string) $text );
	}

	/**
	 * Stable identifier of a text.
	 *
	 * @param string $text Original text.
	 * @return string
	 */
	public static function hash_text( $text ) {
		return md5( self::normalize_text( $text ) );
	}

	/**
	 * A text is translatable when it contains at least one letter.
	 *
	 * @param string $text Already normalized text.
	 * @return bool
	 */
	public static function is_translatable( $text ) {
		return '' !== $text && (bool) preg_match( '/\p{L}/u', $text );
	}

	/**
	 * Returns the translatable texts of an HTML fragment: array( hash => text ).
	 *
	 * @param string $html HTML fragment.
	 * @return array
	 */
	public static function extract_strings( $html ) {
		$strings = array();

		$dom = self::load_dom( $html );
		if ( ! $dom ) {
			return $strings;
		}

		foreach ( self::text_nodes( $dom ) as $node ) {
			$text = self::normalize_text( $node->nodeValue );
			if ( self::is_translatable( $text ) ) {
				$strings[ md5( $text ) ] = $text;
			}
		}

		foreach ( self::image_alts( $dom ) as $img ) {
			$alt = self::normalize_text( $img->getAttribute( 'alt' ) );
			if ( self::is_translatable( $alt ) ) {
				$strings[ md5( $alt ) ] = $alt;
			}
		}

		return $strings;
	}

	/**
	 * Replaces in the HTML the texts that have a translation.
	 *
	 * @param string $html HTML fragment.
	 * @param array  $map  Translations: array( hash => translated text ).
	 * @return string
	 */
	public static function translate_html( $html, $map ) {
		if ( empty( $map ) || ! is_array( $map ) ) {
			return $html;
		}

		$dom = self::load_dom( $html );
		if ( ! $dom ) {
			return $html;
		}

		$changed = false;

		foreach ( self::text_nodes( $dom ) as $node ) {
			$text = self::normalize_text( $node->nodeValue );
			if ( '' === $text ) {
				continue;
			}

			$hash = md5( $text );
			if ( ! isset( $map[ $hash ] ) || '' === (string) $map[ $hash ] ) {
				continue;
			}

			// Preserve leading/trailing whitespace so words do not get glued
			// to the text of neighboring nodes.
			$prefix = preg_match( '/^\s+/u', $node->nodeValue, $m_pre ) ? $m_pre[0] : '';
			$suffix = preg_match( '/\s+$/u', $node->nodeValue, $m_suf ) ? $m_suf[0] : '';

			$node->nodeValue = $prefix . (string) $map[ $hash ] . $suffix;
			$changed         = true;
		}

		foreach ( self::image_alts( $dom ) as $img ) {
			$alt = self::normalize_text( $img->getAttribute( 'alt' ) );
			if ( '' === $alt ) {
				continue;
			}

			$hash = md5( $alt );
			if ( ! isset( $map[ $hash ] ) || '' === (string) $map[ $hash ] ) {
				continue;
			}

			$img->setAttribute( 'alt', (string) $map[ $hash ] );
			$changed = true;
		}

		if ( ! $changed ) {
			return $html;
		}

		$out = self::serialize_body( $dom );

		return null === $out ? $html : $out;
	}

	/**
	 * Loads an HTML fragment into a DOMDocument (UTF-8).
	 *
	 * @param string $html HTML fragment.
	 * @return DOMDocument|null
	 */
	private static function load_dom( $html ) {
		if ( ! class_exists( 'DOMDocument' ) || '' === trim( (string) $html ) ) {
			return null;
		}

		$previous = libxml_use_internal_errors( true );

		$dom     = new DOMDocument( '1.0', 'UTF-8' );
		$wrapped = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"></head><body>'
			. $html
			. '</body></html>';

		$loaded = $dom->loadHTML( $wrapped, LIBXML_NOERROR | LIBXML_NOWARNING );

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		return $loaded ? $dom : null;
	}

	/**
	 * Content images with a non-empty alt attribute.
	 *
	 * @param DOMDocument $dom Loaded document.
	 * @return DOMNodeList|array
	 */
	private static function image_alts( DOMDocument $dom ) {
		$xpath = new DOMXPath( $dom );
		$nodes = $xpath->query( '//body//img[@alt and string-length(@alt) > 0]' );

		return $nodes ? $nodes : array();
	}

	/**
	 * Content text nodes, excluding blocks that must not be translated.
	 *
	 * @param DOMDocument $dom Loaded document.
	 * @return DOMNodeList|array
	 */
	private static function text_nodes( DOMDocument $dom ) {
		$xpath = new DOMXPath( $dom );
		$nodes = $xpath->query(
			'//body//text()[not(ancestor::script) and not(ancestor::style) and not(ancestor::code) and not(ancestor::pre) and not(ancestor::textarea)]'
		);

		return $nodes ? $nodes : array();
	}

	/**
	 * Serializes back only the <body> contents (the original fragment).
	 *
	 * @param DOMDocument $dom Loaded document.
	 * @return string|null
	 */
	private static function serialize_body( DOMDocument $dom ) {
		$body = $dom->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $body ) {
			return null;
		}

		$out = '';
		foreach ( $body->childNodes as $child ) {
			$out .= $dom->saveHTML( $child );
		}

		return $out;
	}
}
