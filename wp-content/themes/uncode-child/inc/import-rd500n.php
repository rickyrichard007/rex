<?php

defined('ABSPATH') || exit;

/** Import the initial builder content without overwriting saved edits. */
function rex_3d_import_rd500n($id, $replace = false)
{
    $page = get_post($id);
    $file = dirname(__DIR__) . '/content/rd500n.txt';
    if (!$page || $page->post_type !== 'page' || get_post_meta($id, '_wp_page_template', true) !== 'page-rex-3d.php') {
        return new WP_Error('rex_3d_page', 'Choose the existing WordPress Page that uses the REX 3D template.');
    }
    if (!is_readable($file) || ($content = file_get_contents($file)) === false || trim($content) === '') {
        return new WP_Error('rex_3d_content', 'The editable content file is missing. Deploy the complete child theme first.');
    }
    if ($page->post_content === $content) {
        return ['unchanged' => true];
    }
    if (trim($page->post_content) !== '' && !$replace) {
        return new WP_Error('rex_3d_exists', 'The page already has content. Import stopped to preserve your edits.');
    }
    $backup = tempnam(sys_get_temp_dir(), 'rex-rd500n-');
    if ($backup === false || file_put_contents($backup, wp_json_encode(['page' => $page->to_array(), 'meta' => get_post_meta($id)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
        return new WP_Error('rex_3d_backup', 'Could not back up the page; no changes made.');
    }
    wp_save_post_revision($id);
    $result = wp_update_post(wp_slash(['ID' => $id, 'post_content' => $content]), true);
    if (is_wp_error($result)) {
        return $result;
    }
    // Empty overrides mean Inherit in Uncode; unrelated page data stays intact.
    foreach (array_keys(get_post_meta($id)) as $key) {
        if (preg_match('/^_uncode_(?:specific_|header_|sidebar_|fullpage_|scroll_|empty_dots)/', $key)) {
            delete_post_meta($id, $key);
        }
    }
    delete_post_meta($id, '_uncode_blocks_list');
    update_post_meta($id, '_wpb_vc_js_status', 'true');
    update_post_meta($id, '_wpb_vc_editor_type', 'backend');
    clean_post_cache($id);
    return ['unchanged' => false, 'backup' => $backup];
}

add_action('admin_notices', function () {
    $screen = get_current_screen();
    global $post;
    if (!$screen || $screen->base !== 'post' || !$post || $post->post_type !== 'page' || !current_user_can('edit_post', $post->ID)) {
        return;
    }
    if (get_post_meta($post->ID, '_wp_page_template', true) !== 'page-rex-3d.php') {
        return;
    }
    if (!empty($_GET['rex_3d_imported'])) {
        echo '<div class="notice notice-success"><p>Editable RD500N content is installed. Open the Uncode/WPBakery builder to edit it. Clear your website cache before checking the public page.</p></div>';
    }
    if (trim($post->post_content) !== '') {
        return;
    }
    echo '<div class="notice notice-warning"><p>The REX 3D code is installed, but this page has no editable WordPress content yet. Import it once to replace the old layout and inherit Uncode Theme Options.</p>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="rex_3d_import_content"><input type="hidden" name="page_id" value="' . (int) $post->ID . '">';
    wp_nonce_field('rex_3d_import_content_' . $post->ID);
    echo '<p><button type="submit" class="button button-primary">Import editable RD500N content</button></p></form></div>';
});

add_action('admin_post_rex_3d_import_content', function () {
    $id = isset($_POST['page_id']) ? absint($_POST['page_id']) : 0;
    if (!current_user_can('edit_post', $id)) {
        wp_die('You do not have permission to edit this page.', '', ['response' => 403]);
    }
    check_admin_referer('rex_3d_import_content_' . $id);
    $result = rex_3d_import_rd500n($id);
    if (is_wp_error($result)) {
        wp_die(esc_html($result->get_error_message()), 'REX 3D import', ['back_link' => true]);
    }
    wp_safe_redirect(add_query_arg('rex_3d_imported', '1', get_edit_post_link($id, 'raw')));
    exit;
});
