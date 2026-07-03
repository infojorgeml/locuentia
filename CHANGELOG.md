# Changelog

## 0.0.10 — 2026-07-03

- Translated slug collision validation: a slug already used by other content — as a translated slug for the same language or as its real slug — is rejected on save, keeping the previous value, and reported with an admin notice linking to the colliding content.
- The translations box re-checks stored slugs on every load and shows an inline warning for collisions that appeared afterwards (e.g. another post adopting that slug).

## 0.0.9 — 2026-07-03

- The whole plugin is now in English: UI strings (source strings for translate.wordpress.org), code comments and developer docs. Spanish (and any other language) will arrive as regular language packs once the plugin is on WordPress.org.

## 0.0.8 — 2026-07-03

- Locuentia now has its own top-level admin menu (translation icon) instead of living under Settings.
- The admin page documents the switcher shortcode with all its attributes and examples.
- Language switcher display options: `style` (list, inline, dropdown), `show` (native name or code), `hide_current`, `separator` and `original_label`.
- The switcher shows the native name of each language by default (Español, English, Українська…) with a map of ~70 languages, extensible via the `locuentia_language_names` filter.
- The dropdown mode navigates on change (minimal JS enqueued only when used).

## 0.0.7 — 2026-07-03

- The **manual excerpt** is translatable: it shows up as one more text in the translations box and is served translated on listings and feeds. The automatic excerpt was already generated from the translated content.
- **Image `alt` texts** in the content are detected as one more text and replaced when serving the page.
- New release flow: `bin/build-zip.sh` builds `releases/locuentia-<version>.zip` (one clean ZIP per version). `Contributors: jorgemml` in the readme.

## 0.0.6 — 2026-07-03

- Renamed to **Locuentia** (formerly the "Simple Translate" prototype), following the WordPress.org directory guidelines: distinctive name and unique `locuentia_` prefix across classes, options, metas, shortcode and query var.
- The public query var went from `lang` to `locuentia_lang` (avoids collisions with other language plugins). The `/en/…` URLs do not change.
- The per-language sitemap moved to `wp-sitemap-locuentia-{language}-1.xml`.
- Switcher and meta box styles moved to enqueued stylesheets (`assets/css/`), no inline styles.
- Formal sanitization of `REQUEST_URI` and justified PHPCS annotations; the `Update URI` header was removed.
- New English `readme.txt` (WordPress.org format), GPL-2.0 license in the repo, and `bin/build-zip.sh` to build the clean submission ZIP.

## 0.0.5 — 2026-07-03

- Per-language sitemap integrated into the native WordPress sitemaps: `wp-sitemap.xml` now includes `wp-sitemap-locuentia-{language}-1.xml`.
- Each language sitemap lists the language home page and the content with stored translations (same criterion as hreflang), with the translated slug and `lastmod`.
- Content without translations, password-protected or unpublished is excluded.

## 0.0.4 — 2026-07-03

- Translated slugs: a "Translated slug" field per language in the translations box; the URL goes from `/en/sobre-nosotros/` to `/en/about-us/`.
- The untranslated slug under a prefix (`/en/sobre-nosotros/`) 301-redirects to the URL with the translated slug.
- Internal links, hreflang, switcher and canonical redirects use the translated slug.
- On hierarchical pages the page's own slug is translated; ancestor slugs are kept.

## 0.0.3 — 2026-07-03

- `hreflang` tags in the `<head>`: every URL announces its original version, its translations and `x-default`.
- On posts/pages only languages with at least one stored translation are announced (no versions identical to the original are announced).
- New "Original content language" setting: derived from the site language by default, but configurable because they do not always match (for example, a WordPress installed in English with Spanish content).
- A target language equal to the original is ignored: the original already lives unprefixed, and duplicating it under `/xx/` would create cloned content and repeated hreflang entries.

## 0.0.2 — 2026-07-03

- Language-prefixed URLs: `/en/my-page/`, `/en/` for the home page (pretty permalinks required).
- Internal links (page menus, listings, permalinks) keep the language while browsing.
- WordPress canonical redirects respect the language prefix.
- The `[locuentia_switcher]` selector links to the pretty URLs.
- The query parameter keeps working as a fallback (and as the only mode without pretty permalinks).
- Rewrite rules regenerate themselves when activating/deactivating the plugin or changing the languages.

## 0.0.1 — 2026-07-03

- Initial version: translatable text detection (title and content), translation fields in an editor meta box, front-end replacement via query parameter, language settings page, switcher shortcode and clean uninstall.
