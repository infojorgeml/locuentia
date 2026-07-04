<?php
// Standalone detector suite: translatable attributes. Run: php bin/tests/test-attributes.php
define( 'ABSPATH', '/tmp/' );
require dirname( __DIR__, 2 ) . '/includes/class-locuentia-detector.php';

$html = '<figure class="wp-block-image"><img src="/foto.jpg" alt="Perro jugando en el parque"/></figure>'
	. '<p>Texto normal.</p>'
	. '<img src="/a.jpg" alt="" /><img src="/b.jpg" /><img src="/c.jpg" alt="1234" />'
	. '<form><input type="text" placeholder="Tu correo" /><textarea placeholder="Tu mensaje"></textarea><input type="submit" value="Enviar ahora" /><input type="hidden" value="secreto" /></form>';

$strings = Locuentia_Detector::extract_strings( $html );

$map = array(
	Locuentia_Detector::hash_text( 'Perro jugando en el parque' ) => 'Dog playing in the park',
	Locuentia_Detector::hash_text( 'Texto normal.' )              => 'Normal text.',
	Locuentia_Detector::hash_text( 'Tu correo' )                  => 'Your email',
	Locuentia_Detector::hash_text( 'Enviar ahora' )               => 'Send now',
);
$out = Locuentia_Detector::translate_html( $html, $map );

$checks = array(
	'detects alt'                 => in_array( 'Perro jugando en el parque', $strings, true ),
	'detects input placeholder'   => in_array( 'Tu correo', $strings, true ),
	'detects textarea placeholder'=> in_array( 'Tu mensaje', $strings, true ),
	'detects submit value'        => in_array( 'Enviar ahora', $strings, true ),
	'ignores hidden value'        => ! in_array( 'secreto', $strings, true ),
	'ignores numeric alt'         => ! in_array( '1234', $strings, true ),
	'replaces alt'                => false !== strpos( $out, 'alt="Dog playing in the park"' ),
	'replaces placeholder'        => false !== strpos( $out, 'placeholder="Your email"' ),
	'replaces submit value'       => false !== strpos( $out, 'value="Send now"' ),
	'replaces plain text too'     => false !== strpos( $out, 'Normal text.' ),
	'keeps src'                   => false !== strpos( $out, 'src="/foto.jpg"' ),
	'keeps numeric alt untouched' => false !== strpos( $out, 'alt="1234"' ),
);

$fail = 0;
foreach ( $checks as $name => $ok ) {
	echo ( $ok ? 'PASS' : 'FAIL' ) . "  $name\n";
	if ( ! $ok ) { $fail++; }
}
exit( $fail > 0 ? 1 : 0 );
