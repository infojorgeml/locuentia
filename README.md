# Locuentia – Multilingual Translations

Traducción manual mínima para WordPress. Sin builders, sin editores visuales: detecta los textos de cada entrada o página y te da un campo por texto e idioma en el propio editor.

> Este README es la documentación de desarrollo (en español). El `readme.txt` en inglés es el que consume el directorio de WordPress.org.

## Cómo se usa

1. Activa el plugin.
2. Ve a **Locuentia** (menú propio en la barra lateral): indica el idioma en que escribes el contenido (por ejemplo `es`; si se deja vacío se usa el idioma del sitio) y los idiomas de destino separados por comas (por defecto: `en`). La misma página documenta el shortcode del selector.
3. Edita cualquier entrada o página: debajo del editor aparece la caja **Traducciones** con todos los textos detectados (título, extracto manual y textos `alt` de las imágenes incluidos) y un campo para cada idioma. Cada idioma tiene además un campo **Slug traducido** opcional para que la URL también se traduzca (`/en/about-us/` en vez de `/en/sobre-nosotros/`).
4. Guarda. La página traducida vive en la URL con prefijo de idioma, por ejemplo:
   `https://misitio.local/en/mi-pagina/` (y la portada en `https://misitio.local/en/`).
   También funciona `?locuentia_lang=CODIGO`, y es el único modo si el sitio no usa enlaces permanentes bonitos.
5. Opcional: coloca el shortcode `[locuentia_switcher]` donde quieras el selector de idioma. Al ser un shortcode funciona en cualquier builder (Gutenberg, Elementor, Bricks, widgets clásicos). Admite `style="list|inline|dropdown"`, `show="name|code"` (nombre nativo o código), `hide_current="yes"`, `separator="|"` y `original_label="…"`; el elemento activo lleva la clase `locuentia-current`.

Mientras navegas dentro de `/en/…`, los enlaces internos (menús de páginas, listados) mantienen el prefijo, así que la navegación se queda en ese idioma.

Los campos vacíos muestran el texto original (no hace falta traducirlo todo).

## Cómo funciona

- Los textos se detectan recorriendo los nodos de texto y los atributos `alt` de las imágenes del contenido guardado (se ignoran `script`, `style`, `code` y `pre`, y los fragmentos sin letras, como números sueltos).
- El extracto manual se traduce como un texto más; el extracto automático ya se genera a partir del contenido traducido.
- Cada texto se identifica por un hash de su versión normalizada (espacios y tipografía unificados), y las traducciones se guardan como texto plano en el post meta `_locuentia_translations`.
- Las URLs de idioma se resuelven duplicando las reglas de reescritura de WordPress bajo cada prefijo (`/en/…`); las reglas se regeneran solas al activar el plugin o cambiar los idiomas. Si alguna URL de idioma diera 404, guarda en Ajustes → Enlaces permanentes para regenerarlas a mano.
- Cada URL emite etiquetas `hreflang` (original, traducciones y `x-default`). En contenido individual solo se anuncian los idiomas con alguna traducción guardada. Un idioma de destino igual al original se ignora para no duplicar contenido.
- Los slugs traducidos se guardan en un meta por idioma (`_locuentia_slug_en`, …). La URL con el slug original bajo prefijo redirige 301 a la traducida, y los enlaces internos, hreflang y switcher usan siempre el slug traducido.
- El sitemap nativo (`wp-sitemap.xml`) incluye un sitemap por idioma (`wp-sitemap-locuentia-en-1.xml`) con la portada del idioma y el contenido que tiene traducciones, usando los slugs traducidos. Requiere los sitemaps nativos de WordPress activos (los plugins SEO tipo Yoast los sustituyen por los suyos).
- Con un idioma activo se filtran `the_title` y `the_content` sustituyendo cada texto por su traducción, y los permalinks se prefijan para mantener la navegación en ese idioma.
- Por defecto funciona en entradas y páginas; se puede ampliar con el filtro `locuentia_post_types`.

## Limitaciones (a propósito, para mantenerlo simple)

- Solo traduce el título, el contenido, el extracto manual y los `alt` de las imágenes del contenido (incluido el `<title>` de la pestaña): no traduce menús ni navegación, widgets, textos del tema, el `alt` de la imagen destacada ni salidas de shortcodes/bloques dinámicos.
- El texto con formato interno (negritas, enlaces) se divide en fragmentos: cada fragmento se traduce por separado.
- Las traducciones son texto plano (sin HTML).
- Si cambias un texto del contenido, su traducción anterior deja de aplicarse: guarda, recarga el editor y rellena el campo del texto nuevo.
- El idioma original siempre va sin prefijo (no hay ruta `/es/` para el origen).
- En páginas jerárquicas solo se traduce el slug propio de cada página; los segmentos de las páginas ancestro mantienen su slug original.
- No se valida que un slug traducido no colisione con el de otro contenido: si dos coinciden, gana el traducido.

Al desinstalar el plugin se borran la opción de idiomas y todas las traducciones guardadas.

## Desarrollo

- Repo: [github.com/infojorgeml/locuentia](https://github.com/infojorgeml/locuentia). Licencia GPL-2.0.
- `bin/build-zip.sh` genera `releases/locuentia-<versión>.zip` totalmente limpio (sin archivos de desarrollo, vía los `export-ignore` de `.gitattributes`); un ZIP por versión para probar en producción. La carpeta `releases/` no se versiona.
- Antes de cada release: pasar [Plugin Check](https://wordpress.org/plugins/plugin-check/) sobre el ZIP generado. Para el envío inicial a WordPress.org, renombrar el ZIP a `locuentia.zip`.
