<?php
// Standalone detector suite: full-document mode. Run: php bin/tests/test-document.php
define( 'ABSPATH', '/tmp/' );
require dirname( __DIR__, 2 ) . '/includes/class-locuentia-detector.php';

$doc = <<<HTML
<!DOCTYPE html>
<html lang="en-US">
<head>
<meta charset="UTF-8" />
<title>Bricks Page – Site</title>
<style>.brxe-heading{font-weight:700}</style>
<script>var config = {"greeting":"I am a heading"};</script>
</head>
<body class="brxe-body">
<header><nav><a href="/">Home page</a> <a href="/about/">Sobre nosotros</a></nav></header>
<main>
<h1 class="brxe-heading">I am a heading</h1>
<p>Here goes your text ... Select any part of your text.</p>
<a class="brxe-button">I am a button</a>
<form>
<input type="text" placeholder="Your Name" />
<textarea placeholder="Your Message"></textarea>
<input type="submit" value="Send" />
<input type="hidden" value="do-not-touch" />
</form>
<img src="/pic.jpg" alt="Team photo" />
</main>
<footer><p>Proudly powered by WordPress · Español</p></footer>
</body>
</html>
HTML;

$strings = Locuentia_Detector::extract_document_strings( $doc );

$checks = array(
	'detects heading'              => in_array( 'I am a heading', $strings, true ),
	'detects menu text'            => in_array( 'Home page', $strings, true ),
	'detects footer'               => in_array( 'Proudly powered by WordPress · Español', $strings, true ),
	'detects input placeholder'    => in_array( 'Your Name', $strings, true ),
	'detects textarea placeholder' => in_array( 'Your Message', $strings, true ),
	'detects submit value'         => in_array( 'Send', $strings, true ),
	'detects alt'                  => in_array( 'Team photo', $strings, true ),
	'ignores head <title>'         => ! in_array( 'Bricks Page – Site', $strings, true ),
	'ignores head script'          => ! in_array( 'var config = {"greeting":"I am a heading"};', $strings, true ),
	'ignores hidden value'         => ! in_array( 'do-not-touch', $strings, true ),
);

$map = array(
	Locuentia_Detector::hash_text( 'I am a heading' ) => 'Soy un encabezado',
	Locuentia_Detector::hash_text( 'Your Name' )      => 'Tu nombre',
	Locuentia_Detector::hash_text( 'Send' )           => 'Enviar',
	Locuentia_Detector::hash_text( 'Home page' )      => 'Página de inicio',
	Locuentia_Detector::hash_text( 'Team photo' )     => 'Foto del equipo',
);

$out = Locuentia_Detector::translate_document( $doc, $map );

$checks['replaces heading']           = false !== strpos( $out, '<h1 class="brxe-heading">Soy un encabezado</h1>' );
$checks['replaces placeholder']       = false !== strpos( $out, 'placeholder="Tu nombre"' );
$checks['replaces submit value']      = false !== strpos( $out, 'value="Enviar"' );
$checks['replaces menu (raw UTF-8)']  = false !== strpos( $out, 'Página de inicio' );
$checks['replaces alt']               = false !== strpos( $out, 'alt="Foto del equipo"' );
$checks['keeps DOCTYPE']              = 0 === stripos( trim( $out ), '<!DOCTYPE html' );
$checks['no xml PI in output']        = false === strpos( $out, '<?xml' );
$checks['keeps script untouched']     = false !== strpos( $out, '"greeting":"I am a heading"' );
$checks['keeps head <title>']         = false !== strpos( $out, '<title>Bricks Page' );
$checks['keeps raw UTF-8 (Español)']  = false !== strpos( $out, 'Español' );
$checks['untranslated stays']         = false !== strpos( $out, 'I am a button' );
$checks['empty map returns original'] = Locuentia_Detector::translate_document( $doc, array() ) === $doc;

$fail = 0;
foreach ( $checks as $name => $ok ) {
	echo ( $ok ? 'PASS' : 'FAIL' ) . "  $name\n";
	if ( ! $ok ) { $fail++; }
}
exit( $fail > 0 ? 1 : 0 );
