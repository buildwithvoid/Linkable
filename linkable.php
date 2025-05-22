<?php
/**
 * Plugin Name: Linkable
 * Plugin URI: https://tykfyr.media
 * Description: Linkable makes internal linking easy by automatically linking keywords in your posts to other posts or pages on your site.
 * Version: 1.0.0
 * Author: tykfyr.media
 * Author URI: https://tykfyr.media
 */
namespace Tykfyr;


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if (!class_exists('Tykfyr\Linkable')) {
    class Linkable
    {
        public function __construct()
        {
            add_action('plugins_loaded', [$this, 'init']);

            add_action('init', function () {
                foreach (['post', 'page'] as $post_type) {
                    register_post_meta($post_type, 'linkable_tags', [
                        'type'              => 'string',
                        'single'            => true,
                        'show_in_rest'      => true,
                        'auth_callback'     => function () {
                            return current_user_can('edit_posts');
                        },
                        'sanitize_callback' => 'sanitize_text_field',
                    ]);
                }
            });
        }

        protected function getGlobalLinkableTagMap(): array
        {
            $cacheKey = 'global_linkable_tag_map';
            $cached = get_transient($cacheKey);
            if ($cached !== false) {
                return $cached;
            }

            $posts = get_posts([
                'post_type'      => ['post', 'page'],
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'meta_key'       => 'linkable_tags',
                'meta_compare'   => 'EXISTS',
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'cache_results'  => false,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ]);

            $map = [];

            foreach ($posts as $postId) {
                $tags = json_decode(get_post_meta($postId, 'linkable_tags', true), true);

                if (empty($tags) || !is_array($tags)) {
                    continue;
                }

                foreach ($tags as $tag) {
                    $word = trim($tag);
                    if ($word !== '' && !isset($map[$word])) {
                        $map[$word] = [
                            'postId' => $postId,
                            'url'    => get_permalink($postId),
                        ];
                    }
                }
            }

            set_transient($cacheKey, $map, HOUR_IN_SECONDS);
            return $map;
        }

        public function linkable(string $content): string
        {
            if (!is_singular(['post', 'page'])) {
                return $content;
            }

            global $post;
            $map = $this->getGlobalLinkableTagMap();

            if (empty($map)) {
                return $content;
            }

            $currentPermalink = get_permalink($post);
            $map = array_filter($map, fn($data) => $data['url'] !== $currentPermalink);

            if (empty($map)) {
                return $content;
            }

            // Normalisér map til lowercase keys
            $normalizedMap = [];
            foreach ($map as $word => $data) {
                $normalizedMap[mb_strtolower($word)] = $data;
            }

            // Backup shortcodes
            $shortcodeMap = [];
            $content = preg_replace_callback('/\[(.*?)\]/', function ($match) use (&$shortcodeMap) {
                $hash = md5($match[0]);
                $shortcodeMap[$hash] = $match[0];
                return "<!--shortcode-->" . $hash . "<!--/shortcode-->";
            }, $content);

            // Backup existing links
            $linkMap = [];
            $content = preg_replace_callback('/<a\b[^>]*>.*?<\/a>/is', function ($match) use (&$linkMap) {
                $hash = md5($match[0]);
                $linkMap[$hash] = $match[0];
                return "<!--link-->" . $hash . "<!--/link-->";
            }, $content);

            // Process <p>, <li>, <b>, <em>, <i>
            $content = preg_replace_callback('/<(p|li|b|em|i)>(.*?)<\/\1>/is', function ($match) use (&$normalizedMap, $post) {
                $tag = $match[1];
                $text = $match[2];
                $textLower = mb_strtolower($text);

                foreach ($normalizedMap as $word => $page) {
                    if ($page['postId'] === $post->ID) {
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

                    $new = preg_replace_callback($pattern, $replacement, $text, 1);

                    if ($new !== $text) {
                        $text = $new;
                        unset($normalizedMap[$word]);
                    }

                    if (empty($normalizedMap)) {
                        break;
                    }
                }

                return "<$tag>$text</$tag>";
            }, $content);

            // Restore original links
            foreach ($linkMap as $hash => $original) {
                $content = str_replace("<!--link-->" . $hash . "<!--/link-->", $original, $content);
            }

            // Restore original shortcodes
            foreach ($shortcodeMap as $hash => $original) {
                $content = str_replace("<!--shortcode-->" . $hash . "<!--/shortcode-->", $original, $content);
            }

            return $content;
        }

        protected function getPostYoastTitle(int $postId): string
        {
            global $wpdb;

            $title = $wpdb->get_var($wpdb->prepare(
                "SELECT title FROM {$wpdb->prefix}yoast_indexable WHERE object_id = %d LIMIT 1",
                $postId
            ));

            if (!$title) {
                return get_the_title($postId);
            }

            return trim(str_replace(['%%sep%%', '%%sitename%%', '%%page%%'], '', $title));
        }

        public function init(): void
        {
            add_filter('the_content', [$this, 'linkable'], 20);

            add_action('enqueue_block_editor_assets', function () {
                wp_enqueue_script(
                    'linkable-sidebar-script',
                    plugins_url('static/js/admin-sidebar-panel.js', __FILE__),
                    ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data'],
                    filemtime(plugin_dir_path(__FILE__) . 'static/js/admin-sidebar-panel.js'),
                    true
                );
            });
        }
    }

    (new Linkable())->init();
}