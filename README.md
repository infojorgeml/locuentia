# Simple Translate

Traducción manual mínima para WordPress. Sin builders, sin editores visuales: detecta los textos de cada entrada o página y te da un campo por texto e idioma en el propio editor.

## Cómo se usa

1. Activa el plugin.
2. Ve a **Ajustes → Simple Translate** y escribe los idiomas de destino separados por comas (por defecto: `en`).
3. Edita cualquier entrada o página: debajo del editor aparece la caja **Traducciones** con todos los textos detectados (título incluido) y un campo para cada idioma.
4. Guarda. La página traducida vive en la URL con prefijo de idioma, por ejemplo:
   `https://misitio.local/en/mi-pagina/` (y la portada en `https://misitio.local/en/`).
   También funciona `?lang=CODIGO`, y es el único modo si el sitio no usa enlaces permanentes bonitos.
5. Opcional: coloca el shortcode `[simple_translate_switcher]` donde quieras un pequeño listado de enlaces para cambiar de idioma.

Mientras navegas dentro de `/en/…`, los enlaces internos (menús de páginas, listados) mantienen el prefijo, así que la navegación se queda en ese idioma.

Los campos vacíos muestran el texto original (no hace falta traducirlo todo).

## Cómo funciona

- Los textos se detectan recorriendo los nodos de texto del contenido guardado (se ignoran `script`, `style`, `code` y `pre`, y los fragmentos sin letras, como números sueltos).
- Cada texto se identifica por un hash de su versión normalizada (espacios y tipografía unificados), y las traducciones se guardan como texto plano en el post meta `_simple_translate_translations`.
- Las URLs de idioma se resuelven duplicando las reglas de reescritura de WordPress bajo cada prefijo (`/en/…`); las reglas se regeneran solas al activar el plugin o cambiar los idiomas. Si alguna URL de idioma diera 404, guarda en Ajustes → Enlaces permanentes para regenerarlas a mano.
- Con un idioma activo se filtran `the_title` y `the_content` sustituyendo cada texto por su traducción, y los permalinks se prefijan para mantener la navegación en ese idioma.
- Por defecto funciona en entradas y páginas; se puede ampliar con el filtro `simple_translate_post_types`.

## Limitaciones (a propósito, para mantenerlo simple)

- Solo traduce el título y el contenido del post (incluido el `<title>` de la pestaña): no traduce menús ni navegación, widgets, textos del tema ni salidas de shortcodes/bloques dinámicos.
- El texto con formato interno (negritas, enlaces) se divide en fragmentos: cada fragmento se traduce por separado.
- Las traducciones son texto plano (sin HTML).
- Si cambias un texto del contenido, su traducción anterior deja de aplicarse: guarda, recarga el editor y rellena el campo del texto nuevo.
- El idioma original siempre va sin prefijo (no hay ruta `/es/` para el origen) y los slugs no se traducen (`/en/sobre-nosotros/`, no `/en/about-us/`).
- Todavía no se emiten etiquetas `hreflang` ni un sitemap por idioma.

Al desinstalar el plugin se borran la opción de idiomas y todas las traducciones guardadas.
