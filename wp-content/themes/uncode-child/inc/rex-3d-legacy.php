<?php
/* Standalone fallback for pages whose editable builder content has not been imported. */

defined('ABSPATH') || exit;

// Set the page custom field rex_3d_folder to a folder beside wp-content.
// Leave it empty to display the existing rex-3d folder.
$folder = trim((string) get_post_meta(get_queried_object_id(), 'rex_3d_folder', true));
$folder = $folder !== '' ? $folder : 'rex-3d';

if (!preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9_-]*\z/', $folder)) {
    wp_die('Use a folder name containing only letters, numbers, hyphens or underscores.');
}

$root = realpath(ABSPATH);
$directory = realpath(ABSPATH . $folder);
$file = $directory !== false ? realpath($directory . '/index.html') : false;

if ($directory === false || $file === false || !is_readable($file)
    || strcasecmp(dirname($directory), $root) !== 0
    || strcasecmp(dirname($file), $directory) !== 0) {
    wp_die('The selected 3D folder must contain a readable index.html in the WordPress root.');
}

$html = file_get_contents($file);
if ($html === false) {
    wp_die('The 3D page could not be read.');
}

preg_match('/<style\b[^>]*>(.*?)<\/style>/is', $html, $styles);
preg_match('/<body\b[^>]*>(.*?)<\/body>/is', $html, $body);
if (empty($styles[1]) || empty($body[1])) {
    wp_die('The 3D document must contain its styles and body content.');
}

// Scope the existing stylesheet at render time; leave the source HTML intact.
$css = preg_replace('/\/\*.*?\*\//s', '', $styles[1]);
$css = preg_replace_callback('/([^{}]+)\{/', static function ($match) {
    $selectors = trim($match[1]);
    if ($selectors[0] === '@' || preg_match('/\A(?:from|to|[\d.]+%)(?:\s*,|\s*$)/', $selectors)) {
        return $match[0];
    }
    $selectors = array_map(static function ($selector) {
        $selector = trim($selector);
        if (preg_match('/\A(?:body|html|:root)(?=[\s.:#\[]|$)/', $selector)) {
            return preg_replace('/\A(?:body|html|:root)/', '#rex-3d-page', $selector);
        }
        return '#rex-3d-page ' . $selector;
    }, explode(',', $selectors));
    return implode(',', $selectors) . '{';
}, $css);

$content = preg_replace('/<(header|footer)\b[^>]*>.*?<\/\1>/is', '', $body[1]);
$content = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $content);
wp_enqueue_style('rex-3d-fonts', 'https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..900&family=IBM+Plex+Mono:wght@400;500&display=swap', [], null);
wp_enqueue_script('rex-3d-renderer', site_url('/' . $folder . '/app.js'), [], (string) filemtime($directory . '/app.js'), true);

get_header();
echo '<style>' . $css . '#rex-3d-page{position:relative;isolation:isolate;clip-path:inset(0);width:100%;}#rex-3d-page h1,#rex-3d-page h2,#rex-3d-page h3{font-family:var(--font);color:var(--text)}.menu-wrapper .navbar{opacity:1!important}.menu-wrapper .navbar.menu-light{background:#fff}' . '</style>';
echo '<article id="post-' . get_queried_object_id() . '" class="page-body"><div class="post-wrapper"><div class="post-body"><div id="rex-3d-page">' . $content . '</div></div></div></article>';
get_footer();
