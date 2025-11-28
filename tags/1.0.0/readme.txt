=== Linkable ===
Contributors: voiddk
Tags: internal linking, internal links, seo, autolink, gutenberg
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically link keywords in your content to internal pages or posts. Simple, fast, and Gutenberg-compatible.

== Description ==

Linkable is a lightweight WordPress plugin that automates internal linking by converting defined keywords into links to selected posts or pages.

**Core functionality:**
* Automatically links keywords to chosen posts or pages
* Works on singular posts and pages by filtering `the_content` at priority 20
* Searches for keyword matches inside `<p>`, `<li>`, `<b>`, `<em>`, and `<i>` tags
* Skips self-links by excluding the current post from the keyword map
* Uses the Yoast SEO title for link tooltips when Yoast is active, otherwise falls back to the WordPress title
* Preserves existing `<a>` tags and shortcodes by backing them up before processing and restoring them afterward

**Keyword management:**
* Keywords are stored per post and per page as JSON in the `linkable_tags` post meta field
* `linkable_tags` is registered with REST support, sanitized via `sanitize_text_field`, and restricted to users with `edit_posts`

**Settings:**
* Accessible via Settings → Linkable
* `max_links_per_target`: limits how many times the same target can be linked from one post (default 1)
* `first_occurrence_only`: when enabled, each keyword is linked only once per post

**Performance:**
* Builds a global keyword map from all published posts and pages with `linkable_tags`
* Caches the keyword map with a transient (`global_linkable_tag_map`) for one hour
* Query optimized to fetch only IDs and avoid unnecessary meta/term caching

**Editor compatibility:**
* Works with both the block editor and the classic editor
* Enqueues an admin sidebar panel script (`static/js/admin-sidebar-panel.js`) with WordPress dependencies and versioning via `filemtime`

**Security:**
* URLs are properly escaped with `esc_url`
* Link titles are sanitized and HTML-escaped
* Meta registration includes capability and sanitization checks

== Installation ==

1. Upload the plugin to the `/wp-content/plugins/` directory or install via WordPress admin
2. Activate the plugin
3. Go to any post or page and add keywords in the Linkable panel (in the sidebar)
4. Configure plugin settings under **Settings > Linkable**

== Frequently Asked Questions ==

= Does it support custom post types? =

Not yet, but it’s on the roadmap.

= Does it work with shortcodes and existing links? =

Yes – existing links and shortcodes are safely ignored during replacement.

= Does it slow down my site? =

No – it uses transients and in-memory caching to keep things fast.

== Screenshots ==

1. Linkable panel in Gutenberg sidebar
2. Plugin settings page

== Changelog ==

= 1.0.0 =
* First public release
* Internal linking based on keywords
* Settings page with basic controls

== Upgrade Notice ==

= 1.0.0 =
Initial release

== License ==

This plugin is licensed under the GPLv2 or later.
