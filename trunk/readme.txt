=== Linkable ===
Contributors: voiddk
Tags: internal linking, seo, keywords, links, automation
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically link keywords in your content to internal pages or posts. Simple, fast, and Gutenberg-compatible.

== Description ==

Linkable is a lightweight plugin that helps improve SEO and user experience by automatically turning keywords into internal links.

* Automatically links keywords to selected posts or pages
* Avoids duplicate links and respects shortcodes/existing links
* Works with block and classic editors
* Includes settings for:
  - Max links per target post
  - First-occurence-only toggle
* Optional integration with Yoast SEO title for link tooltips

All keyword configurations are stored per post/page as meta data – no bloated UI.

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