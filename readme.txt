=== Locuentia – Multilingual Translations ===
Contributors: jorgemml
Tags: translation, multilingual, languages, hreflang, multilingual sitemap
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.0.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Minimal manual translations for posts and pages: translation fields in the editor, language-prefixed URLs, hreflang tags and per-language sitemaps.

== Description ==

Locuentia is a deliberately minimal translation plugin. No page builders, no visual editors, no external services: it detects the texts of each post or page and gives you one field per text and language, right below the editor.

**Features**

* Detects translatable texts from the saved content of posts and pages, including the title, the manual excerpt and the image alt texts.
* One translation field per text and language, in a meta box below the editor. Empty fields fall back to the original text.
* Language-prefixed URLs: `/en/my-page/`, `/en/` for the home page (pretty permalinks required; `?locuentia_lang=xx` works as a fallback).
* Optional translated slugs per language (`/en/about-us/` instead of `/en/sobre-nosotros/`), with automatic 301 redirects from the untranslated slug.
* Internal links (menus, listings) keep the visitor in the active language.
* `hreflang` tags on every URL, announcing only languages that actually have translations.
* Per-language sitemaps integrated into the native WordPress sitemaps (`wp-sitemap-locuentia-en-1.xml`).
* Configurable source language, independent from the site locale.
* Clean uninstall: removes all options and stored translations.

**How it works**

Each text is identified by a hash of its normalized version, so detection in the editor matches the rendered front end. Translations are stored as plain text in post meta. Language URLs are resolved by duplicating the WordPress rewrite rules under each language prefix; rules are regenerated automatically on activation and when the language settings change.

**Limitations (by design, to keep it simple)**

* Only the post title, content, manual excerpt and in-content image alt texts are translated: menus with custom labels, widgets, theme strings, featured image alt texts and dynamic shortcode/block output are not.
* Text with inline formatting (bold, links) is split into fragments, each translated separately.
* Translations are plain text (no HTML).
* The original language always lives at the unprefixed URL.
* If you edit a text, its previous translation stops applying: save, reload the editor and fill in the new field.

== Installation ==

1. Install and activate the plugin.
2. Go to **Settings → Locuentia**: set the language your content is written in (for example `es`) and the target languages, comma separated (for example `en, fr`).
3. Edit any post or page and fill in the **Translations** box below the editor.
4. Visit the translated URL, for example `/en/my-page/`. Optionally place the `[locuentia_switcher]` shortcode wherever you want a language switcher — being a shortcode it works in any editor or builder (Gutenberg, Elementor, Bricks, classic widgets).

The switcher supports display options: `style` (list, inline or dropdown), `show` (native language name or code), `hide_current`, `separator` and `original_label`. For example: `[locuentia_switcher style="dropdown"]`. The full reference lives in the Locuentia admin page.

== Frequently Asked Questions ==

= Does it use any external translation service? =

No. All translations are written by you and stored in your database. The plugin makes no external requests.

= Does it work with page builders? =

It is designed for the block editor and the classic editor. It translates the text nodes of the rendered post content, so static builder output may partially work, but it is not a supported target.

= Do I need to translate everything? =

No. Any text without a translation is served in its original language.

= Does it create extra pages or duplicate content? =

No. Translations are served on virtual language-prefixed URLs backed by the same post, with correct `hreflang` and canonical redirects.

== Changelog ==

= 0.0.9 =
* The whole plugin is now in English (UI source strings, code comments and docs), ready for language packs via translate.wordpress.org.

= 0.0.8 =
* Locuentia now has its own top-level admin menu with the switcher documentation built in.
* Language switcher display options: style (list, inline, dropdown), show (native name or code), hide_current, separator and original_label. Native names for ~70 languages, extensible via the locuentia_language_names filter.

= 0.0.7 =
* Manual excerpts are now translatable, served on listings and feeds. Automatic excerpts were already generated from the translated content.
* Image alt texts in the post content are now detected and translated.

= 0.0.6 =
* Renamed to Locuentia (formerly a working prototype called Simple Translate). All options, meta keys and identifiers now use the `locuentia_` prefix.
* The language query variable is now `locuentia_lang` to avoid collisions.
* Switcher and meta box styles moved to enqueued stylesheets.

= 0.0.5 =
* Per-language sitemaps integrated into the native WordPress sitemaps, listing only content with translations, with translated slugs and lastmod.

= 0.0.4 =
* Translated slugs per language, with 301 redirects from the untranslated slug and localized permalinks everywhere.

= 0.0.3 =
* hreflang tags (original, translations and x-default). New configurable source language setting.

= 0.0.2 =
* Language-prefixed URLs (/en/page/) with automatic rewrite rule management and canonical redirects.

= 0.0.1 =
* Initial version: text detection, translation fields in the editor, language switching via query parameter.
