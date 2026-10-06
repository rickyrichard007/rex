<?php
/** Run from a terminal: php wp-content/themes/uncode-child/tools/import-rd500n.php --page-id=179741 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require dirname(__DIR__, 4) . '/wp-load.php';

$options = getopt('', ['page-id:', 'replace']);
$id = isset($options['page-id']) ? (int) $options['page-id'] : 0;
$page = get_post($id);
$content = file_get_contents(dirname(__DIR__) . '/content/rd500n.txt');
if (!$page || $page->post_type !== 'page' || get_post_meta($id, '_wp_page_template', true) !== 'page-rex-3d.php') {
    fwrite(STDERR, "Specify the existing REX 3D WordPress page with --page-id=ID.\n");
    exit(1);
}
if ($page->post_content === $content) {
    echo "Editable content is already installed; no changes made.\n";
    exit;
}
if (trim($page->post_content) !== '' && !isset($options['replace'])) {
    fwrite(STDERR, "The page already has content. Import stopped to preserve your edits.\n");
    exit(1);
}

$backup = tempnam(sys_get_temp_dir(), 'rex-rd500n-');
if ($backup === false || file_put_contents($backup, wp_json_encode(['page' => $page->to_array(), 'meta' => get_post_meta($id)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
    fwrite(STDERR, "Could not back up the page; no changes made.\n");
    exit(1);
}
wp_save_post_revision($id);
$result = wp_update_post(wp_slash(['ID' => $id, 'post_content' => $content]), true);
if (is_wp_error($result)) {
    fwrite(STDERR, $result->get_error_message() . "\n");
    exit(1);
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
echo "Installed editable RD500N content on page $id.\nPrevious content and settings saved to: $backup\n";
