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

// Fade in the About page intro section (image, headings, divider, text, button) on scroll.
add_action('wp_head', static function () {
    if (!is_page('about-us')) {
        return;
    }

    $items = '#row-unique-1 :is(.uncode-single-media,.vc_custom_heading_wrap,.divider-wrapper,.btn-container)';
    echo '<style id="rex-about-fade-style">'
        . 'html.rex-about-fade ' . $items . '{opacity:0;transform:translateY(24px);transition:opacity .8s ease,transform .8s ease}'
        . 'html.rex-about-fade #row-unique-1 .btn-container{display:inline-block}'
        . 'html.rex-about-fade ' . $items . '.rex-in{opacity:1;transform:none}'
        . '@media (prefers-reduced-motion:reduce){html.rex-about-fade ' . $items . '{opacity:1;transform:none;transition:none}}'
        . '</style>'
        . '<script>if("IntersectionObserver"in window)document.documentElement.classList.add("rex-about-fade");</script>';
});

add_action('wp_footer', static function () {
    if (!is_page('about-us')) {
        return;
    }
    ?>
<script id="rex-about-fade-script">
(function () {
    var row = document.getElementById('row-unique-1');
    if (!row || !document.documentElement.classList.contains('rex-about-fade')) {
        document.documentElement.classList.remove('rex-about-fade');
        return;
    }
    var items = row.querySelectorAll('.uncode-single-media, .vc_custom_heading_wrap, .divider-wrapper, .btn-container');
    items.forEach(function (el, i) { el.style.transitionDelay = (i * 0.12) + 's'; });
    var reveal = function () {
        reveal = function () {};
        var observer = new IntersectionObserver(function (entries) {
            if (!entries[0].isIntersecting) return;
            items.forEach(function (el) { el.classList.add('rex-in'); });
            observer.disconnect();
        }, { threshold: 0.15 });
        observer.observe(row);
    };

    // Uncode swaps the image srcset on window load, so wait for that and for the
    // final image source to load and decode before fading anything in.
    var img = row.querySelector('.uncode-single-media img');
    var whenImageReady = function () {
        if (!img || (img.complete && img.naturalWidth && !img.classList.contains('srcset-fetching'))) {
            (img && img.decode ? img.decode() : Promise.resolve()).catch(function () {}).then(function () { reveal(); });
            return;
        }
        img.addEventListener('load', whenImageReady, { once: true });
        img.addEventListener('error', function () { reveal(); }, { once: true });
    };
    var start = function () { setTimeout(whenImageReady, 100); };
    if (document.readyState === 'complete') {
        start();
    } else {
        window.addEventListener('load', start, { once: true });
    }
    setTimeout(function () { reveal(); }, 6000);
})();
</script>
    <?php
});
