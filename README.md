# Locuentia – Multilingual Translations

Minimal manual translations for WordPress. No builders, no visual editors: it detects the texts of each post or page and gives you one field per text and language, right in the editor.

> This README is the development documentation. The `readme.txt` file is the one consumed by the WordPress.org directory.

## How to use it

1. Activate the plugin.
2. Go to **Locuentia → Settings**: set the language your content is written in (for example `es`; empty = the site language) and the comma-separated target languages (default: `en`). The same page documents the switcher shortcode.
3. Translate from **Locuentia → Translate** — a queue of your content with per-language progress and a focused full-width editor (one language at a time) — or from the **Translations** box below the editor of any post or page. Both list every detected text (title, manual excerpt and image `alt` texts included) with one field per language, plus an optional **Translated slug** field so the URL gets translated too (`/en/about-us/` instead of `/en/sobre-nosotros/`).
4. Save. The translated page lives at the language-prefixed URL, for example:
   `https://mysite.local/en/my-page/` (and the home page at `https://mysite.local/en/`).
   `?locuentia_lang=CODE` also works, and is the only mode when the site does not use pretty permalinks.
5. Optional: place the `[locuentia_switcher]` shortcode wherever you want the language switcher. Being a shortcode it works in any builder (Gutenberg, Elementor, Bricks, classic widgets). It supports `style="list|inline|dropdown"`, `show="name|code"` (native name or code), `hide_current="yes"`, `separator="|"` and `original_label="…"`; the active item carries the `locuentia-current` class.

While browsing inside `/en/…`, internal links (page menus, listings) keep the prefix, so navigation stays in that language.

Empty fields show the original text (you do not need to translate everything).

## How it works

- Texts are detected by walking the text nodes and the translatable attributes (image `alt`, form `placeholder`, submit `value`) of the content **as the front end renders it**, with a safe fallback to the raw content. `script`, `style`, `code` and `pre` are ignored, as are fragments without letters and bare shortcodes.
- **Full-page mode**: on language URLs the final served HTML document is translated as a whole (output buffer + DOM pass), covering builders like Bricks or Elementor, menus, widgets and theme texts — no builder-specific integrations. The Translate screen fetches the served page internally and lists those extra texts in a "Page texts" section, stored **site-wide** (`locuentia_site_translations`): translate a text once and it applies wherever it appears. Per-post translations take precedence over site-wide ones.
- The manual excerpt is translated as one more text; the automatic excerpt is already generated from the translated content.
- Each text is identified by a hash of its normalized version (unified whitespace and typography), and translations are stored as plain text in the `_locuentia_translations` post meta.
- Language URLs are resolved by duplicating the WordPress rewrite rules under each prefix (`/en/…`); the rules regenerate themselves when the plugin is activated or the languages change. If a language URL ever 404s, save Settings → Permalinks to regenerate them manually.
- Every URL outputs `hreflang` tags (original, translations and `x-default`). On singular content only languages with a stored translation are announced. A target language equal to the original is ignored to avoid duplicated content.
- Translated slugs are stored in one meta per language (`_locuentia_slug_en`, …). The URL with the original slug under a prefix 301-redirects to the translated one, and internal links, hreflang and the switcher always use the translated slug.
- The native sitemap (`wp-sitemap.xml`) includes one sitemap per language (`wp-sitemap-locuentia-en-1.xml`) with the language home page and the content that has translations, using the translated slugs. Requires the native WordPress sitemaps to be active (SEO plugins like Yoast replace them with their own).
- With an active language, `the_title` and `the_content` are filtered replacing each text with its translation, and permalinks are prefixed to keep navigation in that language.
- By default it works on posts and pages; extensible via the `locuentia_post_types` filter.

## Limitations (on purpose, to keep it simple)

- On language URLs the whole served page is translatable: content, menus, widgets, theme and builder texts (via the site-wide "Page texts" of the Translate screen). Emails, admin screens and content served outside regular pages are not.
- Text with inline formatting (bold, links) is split into fragments: each fragment is translated separately.
- Translations are plain text (no HTML).
- If you edit a text, its previous translation stops applying: save, reload the editor and fill in the new field.
- The original language always lives at the unprefixed URL (there is no `/es/` route for the source).
- On hierarchical pages only the page's own slug is translated; ancestor segments keep their original slug.
- Translated slugs are validated against collisions when saving from the editor (colliding slugs are rejected with an admin notice, and the translations box warns about collisions that appear afterwards). Slugs written directly to the database are not re-validated until the post is edited again.

Uninstalling the plugin removes the language options and every stored translation.

## Development

- Repo: [github.com/infojorgeml/locuentia](https://github.com/infojorgeml/locuentia). GPL-2.0 license.
- `bin/build-zip.sh` builds a fully clean `releases/locuentia-<version>.zip` (no development files, via the `export-ignore` entries in `.gitattributes`); one ZIP per version to test in production. The `releases/` folder is not versioned.
- Before every release: run [Plugin Check](https://wordpress.org/plugins/plugin-check/) against the built ZIP. For the initial WordPress.org submission, rename the ZIP to `locuentia.zip`.
