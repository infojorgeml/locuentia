# Changelog

## 0.0.6 — 2026-07-03

- Renombrado a **Locuentia** (antes prototipo "Simple Translate"), siguiendo las guías del directorio de WordPress.org: nombre distintivo y prefijo único `locuentia_` en todas las clases, opciones, metas, shortcode y query var.
- La query var pública pasa de `lang` a `locuentia_lang` (evita colisiones con otros plugins de idiomas). Las URLs `/en/…` no cambian.
- El sitemap por idioma pasa a `wp-sitemap-locuentia-{idioma}-1.xml`.
- Estilos del selector y del metabox movidos a hojas encoladas (`assets/css/`), sin estilos inline.
- Sanitización formal de `REQUEST_URI` y anotaciones PHPCS justificadas; se elimina la cabecera `Update URI`.
- Nuevo `readme.txt` en inglés (formato WordPress.org), licencia GPL-2.0 en el repo, y `bin/build-zip.sh` que genera `dist/locuentia.zip` limpio para el envío.

## 0.0.5 — 2026-07-03

- Sitemap por idioma integrado en los sitemaps nativos de WordPress: `wp-sitemap.xml` incluye ahora `wp-sitemap-translations-{idioma}-1.xml`.
- Cada sitemap de idioma lista la portada del idioma y el contenido con traducciones guardadas (mismo criterio que hreflang), con el slug traducido y `lastmod`.
- El contenido sin traducciones, protegido con contraseña o no publicado se excluye.

## 0.0.4 — 2026-07-03

- Slugs traducidos: campo «Slug traducido» por idioma en la caja de traducciones; la URL pasa de `/en/sobre-nosotros/` a `/en/about-us/`.
- El slug sin traducir bajo prefijo (`/en/sobre-nosotros/`) redirige 301 a la URL con slug traducido.
- Enlaces internos, hreflang, switcher y redirecciones canónicas usan el slug traducido.
- En páginas jerárquicas se traduce el slug propio; los de las páginas ancestro se mantienen.

## 0.0.3 — 2026-07-03

- Etiquetas `hreflang` en el `<head>`: cada URL anuncia su versión original, sus traducciones y `x-default`.
- En entradas/páginas solo se anuncian los idiomas que tienen alguna traducción guardada (no se anuncian versiones idénticas al original).
- Nuevo ajuste «Idioma del contenido original»: por defecto se deriva del idioma del sitio, pero es configurable porque no siempre coinciden (por ejemplo, un WordPress instalado en inglés con contenido en español).
- Si un idioma de destino coincide con el original, se ignora: el original ya vive sin prefijo, y duplicarlo bajo `/xx/` crearía contenido clonado y hreflang repetidos.

## 0.0.2 — 2026-07-03

- URLs con prefijo de idioma: `/en/mi-pagina/`, `/en/` para la portada (requiere enlaces permanentes bonitos).
- Los enlaces internos (menús de páginas, listados, permalinks) conservan el idioma mientras navegas.
- Las redirecciones canónicas de WordPress respetan el prefijo de idioma.
- El selector `[locuentia_switcher]` enlaza a las URLs bonitas.
- `?lang=xx` sigue funcionando como alternativa (y como único modo si no hay enlaces permanentes bonitos).
- Las reglas de reescritura se regeneran solas al activar/desactivar el plugin o cambiar los idiomas.

## 0.0.1 — 2026-07-03

- Versión inicial: detección de textos traducibles (título y contenido), campos de traducción en un metabox del editor, sustitución en el frontend vía `?lang=xx`, página de ajustes de idiomas, shortcode de selector y desinstalación limpia.
