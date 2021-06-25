<?php
namespace Lawrelie\WordPress\SlugManager;
// Plugin Name: lawrelie-slug-manager
// Description: スラッグを階層別に管理するプラグイン
// Version: 0.1.0-alpha
// Requires at least: 4.4
// Tested up to: 5.7
// Requires PHP: 7.4
// Text Domain: lawrelie-slug-manager
use WP_Post, WP_Term;
$constantName = fn(string $name): string => __NAMESPACE__ . '\\' . $name;
$define = fn(string $name, ...$args): bool => \define($constantName($name), ...$args);
$define('POST_META_KEYS', ['postSlug']);
$define('TERM_META_KEYS', ['termSlug']);
function fillMetaBox(WP_Post $post): void {
    ?>
    <table>
        <tbody>
            <tr>
                <?php
                $metaKey = metaKey('postSlug');
                $id = \esc_attr("$metaKey--{$post->ID}");
                ?>
                <th><label for="<?php echo $id; ?>">スラッグ</label></th>
                <td>
                    <input id="<?php echo $id; ?>" name="<?php echo \esc_attr($metaKey); ?>" pattern="^[0-9a-z\-]+$" size="40" type="text" value="<?php
                        echo \esc_attr(\get_post_meta($post->ID, $metaKey, true));
                    ?>">
                </td>
            </tr>
        </tbody>
    </table>
    <?php
}
function filter_addMetaBoxes(string $postType, $post): void {
    $post = \get_post($post);
    \add_meta_box($post->ID, 'lawrelie-slug-manager', __NAMESPACE__ . '\fillMetaBox');
}
function filter_addedPostMeta(int $mid, int $objectId, string $metaKey, $_metaValue): void {
    if (!$objectId) {
        return;
    }
    $post = \get_post($objectId);
    if (!!$post) {
        modifyPostSlug($post);
    }
}
function filter_addedTermMeta(int $mid, int $objectId, string $metaKey, $_metaValue): void {
    if (!$objectId) {
        return;
    }
    $term = \get_term($objectId);
    if (!\is_wp_error($term)) {
        modifyTermSlug($term);
    }
}
function filter_attachmentUpdated(int $postId, WP_Post $postAfter, WP_Post $postBefore): void {
    filter_savePost($postId, $postAfter, true);
}
function filter_defaultMetaTypeMetadata($value, int $objectId, string $metaKey, bool $single, string $metaType) {
    if (0 === \strpos($metaKey, metaKey(''))) {
        return sanitizeMeta($value, $metaKey, $single, $metaType);
    }
    return $value;
}
function filter_editTerm(int $termId, int $ttId, string $taxonomy): void {
    if (!\is_admin() || !\current_user_can('manage_categories') || !\current_user_can('edit_posts') || !\current_user_can('edit_others_posts')) {
        return;
    }
    foreach (TERM_META_KEYS as $key) {
        $metaKey = metaKey($key);
        if (!isset($_POST['tag_ID'], $_POST[$metaKey]) || $termId !== (int) \filter_var($_POST['tag_ID'], \FILTER_SANITIZE_NUMBER_INT)) {
            continue;
        }
        \delete_term_meta($termId, $metaKey);
        \add_term_meta($termId, $metaKey, $_POST[$metaKey], true);
    }
}
function filter_init(): void {
    foreach (\get_taxonomies(['object_type' => ['post']], 'names') as $name) {
        \add_filter("{$name}_edit_form", __NAMESPACE__ . '\filter_taxonomyEditForm', 10, 2);
    }
}
function filter_preGetPosts(\WP_Query $query): void {
    if (!$query->is_category || !$query->is_main_query() || \is_admin()) {
        return;
    }
    $query->set('post_parent', 0);
    $category = $query->get_queried_object();
    if ($category instanceof WP_Term) {
        $query->set('tax_query', [['taxonomy' => $category->taxonomy, 'terms' => $category->term_id, 'include_children' => false]]);
    }
}
function filter_preTermLink(string $termlink, WP_Term $term): string {
    return \strtr($termlink, ['%' . $term->taxonomy . '%' => $term->slug]);
}
function filter_savePost(int $postId, WP_Post $post, bool $update): void {
    if (!\is_admin() || !\current_user_can('edit_posts') || !\current_user_can('edit_others_posts')) {
        return;
    }
    foreach (POST_META_KEYS as $key) {
        $metaKey = metaKey($key);
        if (!isset($_POST['post_ID'], $_POST[$metaKey]) || $postId !== (int) \filter_var($_POST['post_ID'], \FILTER_SANITIZE_NUMBER_INT)) {
            continue;
        }
        \delete_post_meta($postId, $metaKey);
        \add_post_meta($postId, $metaKey, $_POST[$metaKey], true);
    }
}
function filter_taxonomyEditForm(WP_Term $tag, string $taxonomy): void {
    ?>
    <fieldset>
        <legend>lawrelie-slug-manager</legend>
        <p>階層別のスラッグの管理</p>
        <table class="form-table" role="presentation">
            <tbody>
                <tr class="form-field">
                    <?php
                    $metaKey = metaKey('termSlug');
                    $id = \esc_attr("$metaKey--{$tag->term_id}");
                    ?>
                    <th scope="row"><label for="<?php echo $id; ?>">スラッグ</label></th>
                    <td>
                        <input id="<?php echo $id; ?>" name="<?php echo \esc_attr($metaKey); ?>" pattern="^[0-9a-z\-]+$" size="40" type="text" value="<?php
                            echo \esc_attr(\get_term_meta($tag->term_id, $metaKey, true));
                        ?>">
                    </td>
                </tr>
            </tbody>
        </table>
    </fieldset>
    <?php
}
function metaKey(string $key): string {
    return "lawrelieSlugManager_$key";
}
function modifyPostSlug(WP_Post $post, string $parentSlug = ''): string {
    $metaKey = metaKey('postSlug');
    if ('' === $parentSlug) {
        $ancestor = $post;
        $slugs = [];
        while (true) {
            $slug = \get_post_meta($ancestor->ID, $metaKey, true);
            if (empty($slug)) {
                break;
            }
            $slugs[] = $slug;
            if (!$ancestor->post_parent) {
                $category = \get_the_category($ancestor->ID);
                if (1 !== \count($category)) {
                    break;
                }
                $category = \reset($category);
                $termMetaKey = metaKey('termSlug');
                while ($category instanceof WP_Term) {
                    $slug = \get_term_meta($category->term_id, $termMetaKey, true);
                    if ('' !== $slug) {
                        $slugs[] = $slug;
                    }
                    $category = \get_term($category->parent, $category->taxonomy);
                }
                break;
            }
            $ancestor = \get_post($ancestor->post_parent);
        }
        if (!$slugs) {
            return '';
        }
        $slug = \implode('-', \array_reverse($slugs));
    } else {
        $slug = \get_post_meta($post->ID, $metaKey, true);
        $slug = !empty($slug) ? "$parentSlug-$slug" : '';
    }
    if ('' === $slug || $post->post_name === $slug || !!\get_posts(['numberposts' => 1, 'name' => $slug]) || $post->ID !== \wp_update_post(['ID' => $post->ID, 'post_name' => $slug])) {
        return $slug;
    }
    foreach (\get_posts(['numberposts' => -1, 'post_parent' => $post->ID]) as $child) {
        modifyPostSlug($child, $slug);
    }
    return $slug;
}
function modifyTermSlug(WP_Term $term, string $parentSlug = ''): string {
    if ('' === $parentSlug) {
        $ancestor = $term;
        $slugs = [];
        while ($ancestor instanceof WP_Term) {
            $slug = \get_term_meta($ancestor->term_id, metaKey('termSlug'), true);
            if (empty($slug)) {
                break;
            }
            $slugs[] = $slug;
            $ancestor = \get_term($ancestor->parent, $ancestor->taxonomy);
        }
        $slug = \implode('-', \array_reverse($slugs));
    } else {
        $slug = \get_term_meta($term->term_id, metaKey('termSlug'), true);
        $slug = !empty($slug) ? "$parentSlug-$slug" : '';
    }
    if (
        '' === $slug
        || $term->slug === $slug
        || !!\get_terms(['taxonomy' => $term->taxonomy, 'hide_empty' => false, 'number' => 1, 'slug' => $slug])
        || \is_wp_error(\wp_update_term($term->term_id, $term->taxonomy, ['description' => $term->description, 'parent' => $term->parent, 'slug' => $slug]))
    ) {
        return $slug;
    }
    $terms = \get_terms(['taxonomy' => $term->taxonomy, 'hide_empty' => false, 'parent' => $term->term_id]);
    if (!\is_wp_error($terms)) {
        foreach ($terms as $child) {
            modifyTermSlug($child, $slug);
        }
    }
    if ('category' === $term->taxonomy) {
        foreach (\get_posts(['numberposts' => -1, 'post_parent' => 0, 'tax_query' => [['taxonomy' => $term->taxonomy, 'terms' => $term->term_id, 'include_children' => false]]]) as $post) {
            if (1 === \count(\get_the_category($post->ID))) {
                modifyPostSlug($post, $slug);
            }
        }
    }
    return $slug;
}
function sanitizeMeta($value, $metaKey, $single, $metaType) {
    $sanitized = [];
    foreach ($single || !\is_iterable($value) ? [$value] : $value as $v) {
        $sanitized[] = \sanitize_meta($metaKey, $v, $metaType);
    }
    return !$single ? $sanitized : $sanitized[0];
}
function sanitizeSlug($var): string {
    $charset = \get_bloginfo('charset');
    return !\is_scalar($var) ? '' : \preg_replace('/[^0-9a-z\-]/u', '', \mb_strtolower(\mb_convert_kana($var, 'a', $charset), $charset));
}
$filters = [
    'default_post_metadata' => ['filter_defaultMetaTypeMetadata' => [10, 5]],
    'default_term_metadata' => ['filter_defaultMetaTypeMetadata' => [10, 5]],
    'add_meta_boxes' => ['filter_addMetaBoxes' => [10, 2]],
    'added_post_meta' => ['filter_addedPostMeta' => [10, 4]],
    'added_term_meta' => ['filter_addedTermMeta' => [10, 4]],
    'attachment_updated' => ['filter_attachmentUpdated' => [10, 3]],
    'edit_term' => ['filter_editTerm' => [10, 3]],
    'init' => ['filter_init' => []],
    'pre_get_posts' => ['filter_preGetPosts' => []],
    'pre_term_link' => ['filter_preTermLink' => [10, 2]],
    'sanitie_post_meta_' . metaKey('postSlug') => ['sanitizeSlug' => []],
    'sanitie_term_meta_' . metaKey('termSlug') => ['sanitizeSlug' => []],
    'save_post' => ['filter_savePost' => [10, 3]],
];
foreach ($filters as $tag => $functionsToAdd) {
    foreach ($functionsToAdd as $functionToAdd => $args) {
        \add_filter($tag, $constantName($functionToAdd), ...$args);
    }
}
