<?php
// Standalone detector suite: fragment mode. Run: php bin/tests/test-fragment.php
define( 'ABSPATH', '/tmp/' );
require dirname( __DIR__, 2 ) . '/includes/class-locuentia-detector.php';

$html = <<<HTML
<!-- wp:heading -->
<h2 class="wp-block-heading">Hola mundo</h2>
<!-- /wp:heading -->
<p>Este párrafo tiene <strong>texto en negrita</strong> y un <a href="#x">enlace añadido</a>.</p>
<p>Fish &amp; Chips — “rápido”</p>
<script>var noTraducir = "Hola mundo";</script>
<pre>echo "Hola mundo";</pre>
<ul><li>Elemento uno</li><li>2024</li></ul>
HTML;

$strings = Locuentia_Detector::extract_strings( $html );

$checks = array(
	'detects h2 text'            => in_array( 'Hola mundo', $strings, true ),
	'detects bold text'          => in_array( 'texto en negrita', $strings, true ),
	'detects link text'          => in_array( 'enlace añadido', $strings, true ),
	'normalizes entities/dashes' => in_array( 'Fish & Chips - "rápido"', $strings, true ),
	'detects li'                 => in_array( 'Elemento uno', $strings, true ),
	'ignores bare number'        => ! in_array( '2024', $strings, true ),
	'ignores script'             => ! in_array( 'var noTraducir = "Hola mundo";', $strings, true ),
	'ignores pre'                => ! in_array( 'echo "Hola mundo";', $strings, true ),
	'ignores bare shortcode'     => ! in_array( '[locuentia_switcher]', Locuentia_Detector::extract_strings( '<p>[locuentia_switcher]</p>' ), true ),
);

$map = array(
	Locuentia_Detector::hash_text( 'Hola mundo' )                  => 'Hello world',
	Locuentia_Detector::hash_text( 'texto en negrita' )            => 'bold text',
	Locuentia_Detector::hash_text( 'Fish &amp; Chips — “rápido”' ) => 'Fish & Chips ("fast")',
	Locuentia_Detector::hash_text( 'Elemento uno' )                => 'Item one',
);

$out = Locuentia_Detector::translate_html( $html, $map );

$checks['replaces h2']                = false !== strpos( $out, '<h2 class="wp-block-heading">Hello world</h2>' );
$checks['replaces bold']              = false !== strpos( $out, '<strong>bold text</strong>' );
$checks['keeps inter-node spacing']   = false !== strpos( $out, 'tiene <strong>bold text</strong> y un' );
$checks['replaces entity text']       = false !== strpos( $out, 'Fish &amp; Chips ("fast")' );
$checks['keeps script untouched']     = false !== strpos( $out, 'var noTraducir = "Hola mundo";' );
$checks['keeps pre untouched']        = false !== strpos( $out, 'echo "Hola mundo";' );
$checks['keeps untranslated UTF-8']   = false !== strpos( $out, 'Este párrafo' );
$checks['keeps attributes']           = false !== strpos( $out, '<a href="#x">' );
$checks['keeps block comments']       = false !== strpos( $out, '<!-- wp:heading -->' );
$checks['no match returns original']  = Locuentia_Detector::translate_html( $html, array( 'ffffffffffffffffffffffffffffffff' => 'x' ) ) === $html;

$fail = 0;
foreach ( $checks as $name => $ok ) {
	echo ( $ok ? 'PASS' : 'FAIL' ) . "  $name\n";
	if ( ! $ok ) { $fail++; }
}
exit( $fail > 0 ? 1 : 0 );
