<?php
use Lawrelie\WordPress\SlugManager as lwsm;
require_once __DIR__ . '\lawrelie-slug-manager.php';
foreach (get_posts(['numberposts' => -1]) as $post) {
    foreach (lwsm\POST_META_KEYS as $key) {
        delete_post_meta($post->ID, lwsm\metaKey($key));
    }
}
$terms = get_terms();
if (!is_wp_error($terms)) {
    foreach ($terms as $term) {
        foreach (lwsm\TERM_META_KEYS as $key) {
            delete_term_meta($term->term_id, lwsm\metaKey($key));
        }
    }
}
