<?php

defined('ABSPATH') || exit;

// The REX 3D page is rendered directly by page-rex-3d.php.
// This file replaces the former builder/import hooks during copy-based deployment.

// Uncode renders the homepage's first builder row as a separate page header.
add_action('wp_head', static function () {
    if (is_front_page() && is_readable(ABSPATH . 'rex-3d/rex-logo-hero/index.html')) {
        echo '<style id="rex-home-3d-hero-style">#page-header{display:none}'
            . 'body.home .menu-wrapper #masthead.menu-transparent:not(.is_stuck),'
            . 'body.home .menu-wrapper #masthead.menu-transparent:not(.is_stuck) .menu-container{background-color:transparent!important}'
            . 'body.home .menu-wrapper #masthead.menu-transparent:not(.is_stuck) .menu-horizontal-inner>.nav>.menu-smart>li>a,'
            . 'body.home .menu-wrapper #masthead.menu-transparent:not(.is_stuck) .menu-horizontal-inner>.nav>.menu-smart>li>a:hover,'
            . 'body.home .menu-wrapper #masthead.menu-transparent:not(.is_stuck) .menu-horizontal-inner>.nav>.menu-smart>li>a:focus,'
            . 'body.home .menu-wrapper #masthead.menu-transparent:not(.is_stuck) .navbar-brand,'
            . 'body.home .menu-wrapper #masthead.menu-transparent:not(.is_stuck) .social-menu-link{color:#f3e9e7!important;opacity:1!important}'
            . 'body.home .menu-wrapper #masthead.menu-transparent:not(.is_stuck) .logo-skinnable svg,'
            . 'body.home .menu-wrapper #masthead.menu-transparent:not(.is_stuck) .logo-canvas,'
            . 'body.home .menu-wrapper #masthead.menu-transparent:not(.is_stuck) .mobile-menu-button{filter:brightness(0) invert(1)}'
            . '</style>';
    }
});

// Place the standalone logo scene before the existing homepage content.
add_filter('uncode_single_content_final_output', static function ($content) {
    if (!is_front_page() || (int) get_the_ID() !== (int) get_option('page_on_front')
        || !is_readable(ABSPATH . 'rex-3d/rex-logo-hero/index.html')) {
        return $content;
    }

    $scene_url = esc_url(site_url('/rex-3d/rex-logo-hero/index.html'));
    $hero = '<section id="rex-home-3d-hero" aria-label="REX 3D logo" style="width:100%;height:100vh;overflow:hidden;background:#050101">'
        . '<iframe src="' . $scene_url . '" title="Interactive REX 3D logo" loading="eager" style="display:block;width:100%;height:100%;border:0"></iframe>'
        . '</section>';

    return $hero . $content;
});
