<?php
/** Run from a terminal: php wp-content/themes/uncode-child/tools/import-rd500n.php --page-id=179741 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require dirname(__DIR__, 4) . '/wp-load.php';
require_once dirname(__DIR__) . '/inc/import-rd500n.php';

$options = getopt('', ['page-id:', 'replace']);
$id = isset($options['page-id']) ? (int) $options['page-id'] : 0;
$result = rex_3d_import_rd500n($id, isset($options['replace']));
if (is_wp_error($result)) {
    fwrite(STDERR, $result->get_error_message() . "\n");
    exit(1);
}
if ($result['unchanged']) {
    echo "Editable content is already installed; no changes made.\n";
} else {
    echo "Installed editable RD500N content on page $id.\nPrevious content and settings saved to: " . $result['backup'] . "\n";
}
