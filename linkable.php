<?php

/**
 * Plugin Name: Linkable
 * Plugin URI: https://void.dk
 * Description: Linkable makes internal linking easy by automatically linking keywords in your posts to other posts or pages on your site.
 * Version: 1.0.0
 * Author: void
 * Author URI: https://void.dk
 */

namespace Void;

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('Void\Linkable')) {
    class Linkable
    {
        /**
         * Linkable constructor.
         * Hooks into plugin and init actions.
         */
        public function __construct()
        {
            add_action('plugins_loaded', [$this, 'init']);

            add_action('init', function () {
                $this->registerLinkableMetaFields();
            });
        }

        /**
         * Initializes the plugin by registering frontend filters and admin assets.
         */
        public function init(): void
        {
            add_filter('the_content', [$this, 'linkable'], 20);

            add_action('admin_menu', [$this, 'addSettingsPage']);
            add_action('admin_init', [$this, 'registerSettings']);

            add_action('enqueue_block_editor_assets', function () {
                wp_enqueue_script(
                    'linkable-sidebar-script',
                    plugins_url('static/js/admin-sidebar-panel.js', __FILE__),
                    ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data'],
                    filemtime(plugin_dir_path(__FILE__).'static/js/admin-sidebar-panel.js'),
                    true
                );
            });
        }

        /**
         * Registers custom meta-field "linkable_tags" for posts and pages.
         */
        protected function registerLinkableMetaFields(): void
        {
            foreach (['post', 'page'] as $postType) {
                register_post_meta($postType, 'linkable_tags', [
                    'type' => 'string',
                    'single' => true,
                    'show_in_rest' => true,
                    'auth_callback' => fn () => current_user_can('edit_posts'),
                    'sanitize_callback' => 'sanitize_text_field',
                ]);
            }
        }

        /**
         * Main content filter that inserts internal links based on keywords.
         */
        public function linkable(string $content): string
        {
            if (! is_singular(['post', 'page'])) {
                return $content;
            }

            global $post;

            $map = $this->getGlobalLinkableTagMap();
            if (empty($map)) {
                return $content;
            }

            $currentPermalink = get_permalink($post);
            $map = array_filter($map, fn ($data) => $data['url'] !== $currentPermalink);
            if (empty($map)) {
                return $content;
            }

            $normalizedMap = $this->normalizeMapKeys($map);

            $shortcodeMap = [];
            $content = $this->backupShortcodes($content, $shortcodeMap);

            $linkMap = [];
            $content = $this->backupLinks($content, $linkMap);

            $content = $this->replaceInContentBlocks($content, $normalizedMap, $post);

            $content = $this->restorePlaceholders($content, $linkMap, 'link');

            return $this->restorePlaceholders($content, $shortcodeMap, 'shortcode');
        }

        /**
         * Retrieves all linkable keywords across posts and pages.
         * Uses caching via transients for performance.
         */
        protected function getGlobalLinkableTagMap(): array
        {
            $cacheKey = 'global_linkable_tag_map';
            $cached = get_transient($cacheKey);
            if ($cached !== false) {
                return $cached;
            }

            $posts = get_posts([
                'post_type' => ['post', 'page'],
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'meta_key' => 'linkable_tags',
                'meta_compare' => 'EXISTS',
                'fields' => 'ids',
                'no_found_rows' => true,
                'cache_results' => false,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ]);

            $map = $this->buildTagMapFromPosts($posts);

            set_transient($cacheKey, $map, HOUR_IN_SECONDS);

            return $map;
        }

        /**
         * Builds a mapping of keywords to post-IDs and permalinks.
         */
        protected function buildTagMapFromPosts(array $postIds): array
        {
            $map = [];

            foreach ($postIds as $postId) {
                $tags = json_decode(get_post_meta($postId, 'linkable_tags', true), true);
                if (empty($tags) || ! is_array($tags)) {
                    continue;
                }

                foreach ($tags as $tag) {
                    $word = trim($tag);
                    if ($word !== '' && ! isset($map[$word])) {
                        $map[$word] = [
                            'postId' => $postId,
                            'url' => get_permalink($postId),
                        ];
                    }
                }
            }

            return $map;
        }

        /**
         * Normalizes map keys to the lowercase for case-insensitive matching.
         */
        protected function normalizeMapKeys(array $map): array
        {
            $normalized = [];
            foreach ($map as $word => $data) {
                $normalized[mb_strtolower($word)] = $data;
            }

            return $normalized;
        }

        /**
         * Backs up shortcodes by replacing them with hash placeholders.
         */
        protected function backupShortcodes(string $content, array &$shortcodeMap): string
        {
            return preg_replace_callback('/\[(.*?)\]/', function ($match) use (&$shortcodeMap) {
                $hash = md5($match[0]);
                $shortcodeMap[$hash] = $match[0];

                return '<!--shortcode-->'.$hash.'<!--/shortcode-->';
            }, $content);
        }

        /**
         * Backs up anchor tags by replacing them with hash placeholders.
         */
        protected function backupLinks(string $content, array &$linkMap): string
        {
            return preg_replace_callback('/<a\b[^>]*>.*?<\/a>/is', function ($match) use (&$linkMap) {
                $hash = md5($match[0]);
                $linkMap[$hash] = $match[0];

                return '<!--link-->'.$hash.'<!--/link-->';
            }, $content);
        }

        /**
         * Restores previously backed up content from placeholders.
         */
        protected function restorePlaceholders(string $content, array $map, string $type): string
        {
            foreach ($map as $hash => $original) {
                $content = str_replace("<!--{$type}-->{$hash}<!--/{$type}-->", $original, $content);
            }

            return $content;
        }

        /**
         * Replaces matching words inside allowed HTML tags with internal links.
         * Respects plugin settings for first match only and max links per target.
         *
         * @param string   $content
         * @param array    $normalizedMap
         * @param \WP_Post $post
         * @return string
         */
        protected function replaceInContentBlocks(string $content, array $normalizedMap, \WP_Post $post): string
        {
            $firstOccurrenceOnly = $this->getSetting('first_occurrence_only', false);
            $maxLinksPerTarget = (int) $this->getSetting('max_links_per_target', 1);

            $linkCounts = [];

            return preg_replace_callback('/<(p|li|b|em|i)>(.*?)<\/\1>/is', function ($match) use (&$normalizedMap, $post, &$linkCounts, $firstOccurrenceOnly, $maxLinksPerTarget) {
                $tag = $match[1];
                $text = $match[2];
                $textLower = mb_strtolower($text);

                foreach ($normalizedMap as $word => $page) {
                    if ($page['postId'] === $post->ID) {
                        continue; // Don't link to self
                    }

                    // Skip if this target post has reached its max link count
                    $linkCounts[$page['postId']] = $linkCounts[$page['postId']] ?? 0;
                    if ($linkCounts[$page['postId']] >= $maxLinksPerTarget) {
                        continue;
                    }

                    if (mb_strpos($textLower, $word) === false) {
                        continue;
                    }

                    $pattern = '/(?<!["\'>])(?<!\w)(' . preg_quote($word, '/') . ')(?!\w)(?![^<]*?>)/iu';

                    $replacement = function ($m) use ($page) {
                        $title = htmlspecialchars($this->getPostYoastTitle($page['postId']), ENT_QUOTES, 'UTF-8');
                        return '<a class="s-link" href="' . esc_url($page['url']) . '" title="' . $title . '">' . $m[0] . '</a>';
                    };

                    $limit = $firstOccurrenceOnly ? 1 : -1;

                    $newText = preg_replace_callback($pattern, function ($m) use ($replacement, &$linkCounts, $page, $maxLinksPerTarget) {
                        if ($linkCounts[$page['postId']] < $maxLinksPerTarget) {
                            $linkCounts[$page['postId']]++;
                            return $replacement($m);
                        }

                        return $m[0]; // No replacement if over the limit
                    }, $text, $limit);

                    if ($newText !== $text) {
                        $text = $newText;
                        if ($firstOccurrenceOnly) {
                            unset($normalizedMap[$word]);
                        }
                    }

                    if (empty($normalizedMap)) {
                        break;
                    }
                }

                return "<$tag>$text</$tag>";
            }, $content);
        }
        /**
         * Attempts to retrieve the Yoast SEO title for a given post.
         * Falls back to the WordPress title if Yoast is not installed or a table is missing.
         */
        protected function getPostYoastTitle(int $postId): string
        {
            global $wpdb;

            // Check if Yoast is active
            if (! defined('WPSEO_VERSION')) {
                return get_the_title($postId);
            }

            // Check if the yoast_indexable table exists
            $tableName = $wpdb->prefix.'yoast_indexable';
            $tableExists = $wpdb->get_var($wpdb->prepare(
                'SHOW TABLES LIKE %s', $tableName
            ));

            if (! $tableExists) {
                return get_the_title($postId);
            }

            // Query the Yoast indexable table
            $title = $wpdb->get_var($wpdb->prepare(
                "SELECT title FROM {$tableName} WHERE object_id = %d LIMIT 1",
                $postId
            ));

            if (! $title) {
                return get_the_title($postId);
            }

            return trim(str_replace(['%%sep%%', '%%sitename%%', '%%page%%'], '', $title));
        }

        /**
         * Registers plugin settings, section, and fields.
         */
        public function registerSettings(): void
        {
            register_setting('linkable_settings_group', 'linkable_settings');

            add_settings_section(
                'linkable_main',
                'General Settings',
                function () {
                    echo '<p>Customize how Linkable inserts internal links in your content.</p>';
                },
                'linkable'
            );

            // Max links to the same target post
            add_settings_field('max_links_per_target', 'Max links to same page per post', function () {
                $value = $this->getSetting('max_links_per_target', 1);
                ?>
                <input type="number"
                       name="linkable_settings[max_links_per_target]"
                       value="<?= esc_attr($value) ?>"
                       min="1"
                       step="1"
                       class="small-text" />
                <p class="description">Limit how many times a single page can be linked from the same post. Default is 1.</p>
                <?php
            }, 'linkable', 'linkable_main');

            // Only link first occurrence
            add_settings_field('first_occurrence_only', 'Only link first keyword match?', function () {
                $value = $this->getSetting('first_occurrence_only', false);
                ?>
                <label>
                    <input type="checkbox"
                           name="linkable_settings[first_occurrence_only]"
                           value="1" <?= checked(1, $value, false) ?> />
                    Yes, only link the first time a keyword appears.
                </label>
                <p class="description">If enabled, each keyword will only be linked once per post.</p>
                <?php
            }, 'linkable', 'linkable_main');
        }

        /**
         * Adds a settings page to the WordPress admin menu.
         *
         * @return void
         */
        public function addSettingsPage(): void
        {
            add_options_page('Linkable Settings', 'Linkable', 'manage_options', 'linkable', function () {
                ?>
                <div class="wrap">
                    <h1>Linkable Settings</h1>
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('linkable_settings_group');
                        do_settings_sections('linkable');
                        submit_button();
                        ?>
                    </form>
                </div>
                <?php
            });
        }

        /**
         * Returns a setting value or default.
         *
         * @param string $key
         * @param mixed|null $default
         * @return mixed
         */
        protected function getSetting(string $key, mixed $default = null): mixed
        {
            $settings = get_option('linkable_settings', []);

            return $settings[$key] ?? $default;
        }
    }

    (new Linkable)->init();
}
