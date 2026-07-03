<?php
/**
 * Extracción y sustitución de textos traducibles en HTML.
 *
 * Solo usa DOM (sin funciones de WordPress) para poder probarla de forma aislada.
 * Cada texto se identifica por el hash MD5 de su versión normalizada, de modo
 * que la detección en el editor y la sustitución en el frontend coincidan
 * aunque cambien espacios, entidades o la tipografía de wptexturize.
 */

defined( 'ABSPATH' ) || exit;

class Locuentia_Detector {

	/**
	 * Normaliza un texto para identificarlo de forma estable.
	 *
	 * @param string $text Texto original.
	 * @return string
	 */
	public static function normalize_text( $text ) {
		$text = html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Neutraliza las transformaciones tipográficas de wptexturize (comillas
		// curvas, guiones largos, nbsp…) para que el hash sea el mismo antes y
		// después de renderizar el contenido.
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
	 * Identificador estable de un texto.
	 *
	 * @param string $text Texto original.
	 * @return string
	 */
	public static function hash_text( $text ) {
		return md5( self::normalize_text( $text ) );
	}

	/**
	 * Un texto es traducible si contiene al menos una letra.
	 *
	 * @param string $text Texto ya normalizado.
	 * @return bool
	 */
	public static function is_translatable( $text ) {
		return '' !== $text && (bool) preg_match( '/\p{L}/u', $text );
	}

	/**
	 * Devuelve los textos traducibles de un HTML: array( hash => texto ).
	 *
	 * @param string $html Fragmento HTML.
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
	 * Sustituye en el HTML los textos que tengan traducción.
	 *
	 * @param string $html Fragmento HTML.
	 * @param array  $map  Traducciones: array( hash => texto traducido ).
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

			// Conserva el espacio en blanco de los extremos para no pegar
			// palabras con el texto de los nodos vecinos.
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
	 * Carga un fragmento HTML en un DOMDocument (UTF-8).
	 *
	 * @param string $html Fragmento HTML.
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
	 * Imágenes del contenido con atributo alt no vacío.
	 *
	 * @param DOMDocument $dom Documento cargado.
	 * @return DOMNodeList|array
	 */
	private static function image_alts( DOMDocument $dom ) {
		$xpath = new DOMXPath( $dom );
		$nodes = $xpath->query( '//body//img[@alt and string-length(@alt) > 0]' );

		return $nodes ? $nodes : array();
	}

	/**
	 * Nodos de texto del contenido, excluyendo bloques que no deben traducirse.
	 *
	 * @param DOMDocument $dom Documento cargado.
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
	 * Serializa de vuelta solo el contenido del <body> (el fragmento original).
	 *
	 * @param DOMDocument $dom Documento cargado.
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
