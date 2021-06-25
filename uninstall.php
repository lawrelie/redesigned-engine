<?php
use Lawrelie\RedesignedEngine as lre;
foreach (get_posts(['numberposts' => -1]) as $post) {
    foreach (lre\POST_META_KEYS as $key) {
        delete_post_meta($post->ID, lre\metaKey($key));
    }
}
$terms = get_terms();
if (!is_wp_error($terms)) {
    foreach ($terms as $term) {
        foreach (lre\TERM_META_KEYS as $key) {
            delete_term_meta($term->term_id, lre\metaKey($key));
        }
    }
}
