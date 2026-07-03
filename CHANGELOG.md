# Changelog

## 0.0.2 — 2026-07-03

- URLs con prefijo de idioma: `/en/mi-pagina/`, `/en/` para la portada (requiere enlaces permanentes bonitos).
- Los enlaces internos (menús de páginas, listados, permalinks) conservan el idioma mientras navegas.
- Las redirecciones canónicas de WordPress respetan el prefijo de idioma.
- El selector `[simple_translate_switcher]` enlaza a las URLs bonitas.
- `?lang=xx` sigue funcionando como alternativa (y como único modo si no hay enlaces permanentes bonitos).
- Las reglas de reescritura se regeneran solas al activar/desactivar el plugin o cambiar los idiomas.

## 0.0.1 — 2026-07-03

- Versión inicial: detección de textos traducibles (título y contenido), campos de traducción en un metabox del editor, sustitución en el frontend vía `?lang=xx`, página de ajustes de idiomas, shortcode de selector y desinstalación limpia.
