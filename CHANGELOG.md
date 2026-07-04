# Changelog

## 0.0.20 — 2026-07-04

- **Taxonomy terms**: new "Taxonomy terms" editor on the Translate screen (linked from the queue) listing the names and descriptions of the terms of public taxonomies (200 most used), with language tabs and memory suggestions. Translations are stored **site-wide**, so they apply on archives, listings, widgets and wherever the term appears.
- Term names in archive document titles (`<title>`) are translated via the `single_term_title`/`single_cat_title`/`single_tag_title` filters; body occurrences were already covered by the full-page pass.
- Taxonomy list extensible via the `locuentia_taxonomies` filter.

## 0.0.19 — 2026-07-04

- **Browser language redirect** (new setting, **off by default**): first-time visitors of original URLs are 302-redirected to their preferred configured language based on `Accept-Language`. Exactly once per visitor (30-day cookie; visiting any language URL also counts as the decision, so switcher choices are respected), never for search engine bots or clients without a user agent, never when the browser prefers the original language, and the redirect response carries no-cache headers so it is never stored by page caches. Singular targets land directly on their translated slug.

## 0.0.18 — 2026-07-04

- **Queue filters** on the Translate screen: language, status (untranslated / in progress / complete) and content type. Status filters evaluate the whole list and paginate manually; the row links open the editor in the filtered language.
- **Pending counters on the language tabs** of the editor: number of texts left per language (post inventory + page texts of this page), or a check mark when done.
- **Save & translate next pending**: a second submit that saves and jumps to the next content with pending texts in that language (queue order, wrapping around), landing on the queue with an "all caught up" notice when nothing is left.

## 0.0.17 — 2026-07-04

- **Translation memory**: when an empty field's text was already translated somewhere else on the site (another post or the site-wide store), the field shows the suggestion with an **Apply** button — in the meta box and on the Translate screen (page texts included).
- **Apply all memory suggestions** button on the Translate screen: fills every empty field with its suggestion in one click. Nothing is saved until you save, so review stays explicit.

## 0.0.16 — 2026-07-04

- **Translatable meta keys**: a new setting (and the `locuentia_translatable_meta_keys` filter) lists post meta keys whose values become regular translatable texts — SEO titles and descriptions, custom fields. Plain keys translate the whole string value; `key.subkey` targets one string inside an array value (e.g. `slim_seo.title`). Works with any SEO plugin without plugin-specific integrations, covering strings printed in the `<head>`.
- Detector test suites now live in the repo (`bin/tests/`, excluded from the distribution ZIP).
- Plugin Check: annotated the deliberate core `the_content` usage in rendered detection.

## 0.0.15 — 2026-07-03

- **Full-page translation mode**: on language URLs, the final served HTML document is translated as a whole (output buffer + DOM pass), covering output that never goes through `the_content` — page builders like Bricks, menus, widgets and theme texts. Per-post translations take precedence; feeds, sitemaps and robots are untouched.
- **Page texts on the Translate screen**: the served page of a published post is fetched internally (loopback) and every text not covered by the post inventory is listed in a new "Page texts" section. These translations are stored **site-wide**: translating a text once applies wherever it appears (menus, footers, buttons…).
- The detector now handles full HTML documents (UTF-8 safe, doctype preserved) and also detects **form field placeholders** and **submit/button values**.
- New `locuentia_site_translations` option (removed on uninstall).

## 0.0.14 — 2026-07-03

- **Detection from rendered content**: translatable texts are now discovered from the content as the front end renders it (`the_content` filters — blocks, shortcodes, and whatever page builders hook there), instead of the raw database content. Shortcode output and dynamic block output become detectable and translatable. Existing text hashes are unchanged, so stored translations keep working.
- Safety net: if rendering fails or a third-party filter swallows the content, detection falls back to the raw content.
- Fixed the two Plugin Check sanitization warnings on the Translate screen language parameter.

## 0.0.13 — 2026-07-03

- **Translate screen** (new top-level view of the Locuentia menu): a queue of all translatable content with per-language progress badges and pagination, plus a focused full-width editor per post — same fields and saving pipeline as the meta box, one language at a time with tabs, translated slug included.
- Settings moved to the **Locuentia → Settings** submenu; the Translate screen is available to editors (`edit_posts`), settings remain admin-only.
- Translation saving now merges per language: saving one language never touches the others (needed by the new screen, harmless for the meta box).

## 0.0.12 — 2026-07-03

- **Translation progress column** in the post and page list tables: one badge per language with translated/total texts (green complete, amber partial, gray untouched), counting only translations whose text still exists in the content.
- The detector no longer treats bare shortcodes (`[locuentia_switcher]`) as translatable text.

## 0.0.11 — 2026-07-03

- The **featured image alt text** is now translatable: it shows up in the translations box and is served translated wherever the image is rendered via `wp_get_attachment_image` (featured images, dynamic galleries).
- Performance: `find_slug_collision()` no longer uses exclusionary query parameters (`post__not_in`); the edited post is filtered in PHP (fixes the Plugin Check VIP warnings).

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
