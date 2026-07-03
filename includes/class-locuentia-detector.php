<?php
/**
 * Extraction and replacement of translatable texts in HTML.
 *
 * Uses DOM only (no WordPress functions) so it can be tested in isolation.
 * Each text is identified by the MD5 hash of its normalized version, so
 * detection in the editor and replacement on the front end match even when
 * whitespace, entities or wptexturize typography differ.
 *
 * Works in two modes: HTML fragments (post content) and full HTML documents
 * (whole served pages), sharing the same collection/replacement logic.
 */

defined( 'ABSPATH' ) || exit;

class Locuentia_Detector {

	/**
	 * Encoding hint prepended when parsing full documents; stripped on output.
	 */
	const ENCODING_PI = '<?xml encoding="utf-8" ?>';

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
	 * A text is translatable when it contains at least one letter and is
	 * not a bare shortcode (translating "[locuentia_switcher]" would break it).
	 *
	 * @param string $text Already normalized text.
	 * @return bool
	 */
	public static function is_translatable( $text ) {
		if ( '' === $text || ! preg_match( '/\p{L}/u', $text ) ) {
			return false;
		}

		if ( preg_match( '/^\[[^\[\]]+\]$/', $text ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns the translatable texts of an HTML fragment: array( hash => text ).
	 *
	 * @param string $html HTML fragment.
	 * @return array
	 */
	public static function extract_strings( $html ) {
		$dom = self::load_dom( $html );

		return $dom ? self::collect_strings( $dom ) : array();
	}

	/**
	 * Returns the translatable texts of a full HTML document (body only).
	 *
	 * @param string $html Full HTML document, as served.
	 * @return array
	 */
	public static function extract_document_strings( $html ) {
		$dom = self::load_dom( $html, true );

		return $dom ? self::collect_strings( $dom ) : array();
	}

	/**
	 * Replaces in an HTML fragment the texts that have a translation.
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
		if ( ! $dom || ! self::apply_translations( $dom, $map ) ) {
			return $html;
		}

		$out = self::serialize_body( $dom );

		return null === $out ? $html : $out;
	}

	/**
	 * Replaces in a full HTML document the texts that have a translation.
	 *
	 * @param string $html Full HTML document, as served.
	 * @param array  $map  Translations: array( hash => translated text ).
	 * @return string
	 */
	public static function translate_document( $html, $map ) {
		if ( empty( $map ) || ! is_array( $map ) ) {
			return $html;
		}

		$dom = self::load_dom( $html, true );
		if ( ! $dom || ! $dom->documentElement || ! self::apply_translations( $dom, $map ) ) {
			return $html;
		}

		// Serializing the root node (instead of the whole document) keeps
		// raw UTF-8 output and drops the encoding PI in one go. The parser
		// discards the doctype when the PI precedes it, so it is restored
		// from the original source.
		$doctype = preg_match( '/^\s*<!DOCTYPE[^>]*>/i', $html, $m ) ? trim( $m[0] ) . "\n" : '';

		$out = (string) $dom->saveHTML( $dom->documentElement );

		return '' === trim( $out ) ? $html : $doctype . $out;
	}

	/**
	 * Collects the translatable texts of a loaded document.
	 *
	 * @param DOMDocument $dom Loaded document.
	 * @return array array( hash => text ).
	 */
	private static function collect_strings( DOMDocument $dom ) {
		$strings = array();

		foreach ( self::text_nodes( $dom ) as $node ) {
			$text = self::normalize_text( $node->nodeValue );
			if ( self::is_translatable( $text ) ) {
				$strings[ md5( $text ) ] = $text;
			}
		}

		foreach ( self::attribute_nodes( $dom ) as $pair ) {
			$text = self::normalize_text( $pair[0]->getAttribute( $pair[1] ) );
			if ( self::is_translatable( $text ) ) {
				$strings[ md5( $text ) ] = $text;
			}
		}

		return $strings;
	}

	/**
	 * Applies a translation map to a loaded document.
	 *
	 * @param DOMDocument $dom Loaded document.
	 * @param array       $map Translations: array( hash => translated text ).
	 * @return bool Whether anything was replaced.
	 */
	private static function apply_translations( DOMDocument $dom, array $map ) {
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

		foreach ( self::attribute_nodes( $dom ) as $pair ) {
			$text = self::normalize_text( $pair[0]->getAttribute( $pair[1] ) );
			if ( '' === $text ) {
				continue;
			}

			$hash = md5( $text );
			if ( ! isset( $map[ $hash ] ) || '' === (string) $map[ $hash ] ) {
				continue;
			}

			$pair[0]->setAttribute( $pair[1], (string) $map[ $hash ] );
			$changed = true;
		}

		return $changed;
	}

	/**
	 * Loads HTML into a DOMDocument (UTF-8).
	 *
	 * @param string $html        HTML fragment or full document.
	 * @param bool   $is_document True when $html is a full document (not wrapped).
	 * @return DOMDocument|null
	 */
	private static function load_dom( $html, $is_document = false ) {
		if ( ! class_exists( 'DOMDocument' ) || '' === trim( (string) $html ) ) {
			return null;
		}

		$previous = libxml_use_internal_errors( true );

		$dom = new DOMDocument( '1.0', 'UTF-8' );

		if ( $is_document ) {
			// The XML PI forces UTF-8 regardless of how libxml reads the
			// document's own meta tags; it is stripped when serializing.
			$source = self::ENCODING_PI . $html;
		} else {
			$source = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"></head><body>'
				. $html
				. '</body></html>';
		}

		$loaded = $dom->loadHTML( $source, LIBXML_NOERROR | LIBXML_NOWARNING );

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			return null;
		}

		// Make the HTML serializer emit raw UTF-8 instead of turning every
		// non-ASCII character into an entity (which would bloat non-Latin pages).
		$dom->encoding = 'UTF-8';

		return $dom;
	}

	/**
	 * Elements with translatable attributes in the body: image alt texts,
	 * form field placeholders and submit/button values.
	 *
	 * @param DOMDocument $dom Loaded document.
	 * @return array Array of ( DOMElement, attribute name ) pairs.
	 */
	private static function attribute_nodes( DOMDocument $dom ) {
		$xpath = new DOMXPath( $dom );

		$queries = array(
			'alt'         => '//body//img[@alt and string-length(@alt) > 0]',
			'placeholder' => '//body//*[self::input or self::textarea][@placeholder and string-length(@placeholder) > 0]',
			'value'       => '//body//input[(@type="submit" or @type="button") and @value and string-length(@value) > 0]',
		);

		$pairs = array();

		foreach ( $queries as $attribute => $query ) {
			$nodes = $xpath->query( $query );
			if ( ! $nodes ) {
				continue;
			}
			foreach ( $nodes as $node ) {
				$pairs[] = array( $node, $attribute );
			}
		}

		return $pairs;
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
