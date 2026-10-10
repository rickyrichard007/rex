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

// "My Biography" button: scroll to the Journal section (row 2 on desktop, row 3 on tablet/mobile).
(function () {
    var btn = document.querySelector('#row-unique-1 .btn-container a.btn');
    if (!btn) return;
    btn.addEventListener('click', function (e) {
        var target = [document.getElementById('row-unique-2'), document.getElementById('row-unique-3')]
            .filter(function (el) { return el && el.offsetParent !== null; })[0];
        if (!target) return;
        e.preventDefault();
        var header = document.getElementById('masthead');
        var offset = header ? header.getBoundingClientRect().height : 0;
        var top = target.getBoundingClientRect().top + window.pageYOffset - offset;
        var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.scrollTo({ top: top, behavior: reduce ? 'auto' : 'smooth' });
    });
})();
</script>
    <?php
});

// Post cards on the About page: show the full excerpt and replace the date with a custom field.
add_action('add_meta_boxes_post', static function () {
    add_meta_box('rex-card-top-text', 'Card Top Text', static function ($post) {
        wp_nonce_field('rex_card_top_text', 'rex_card_top_text_nonce');
        echo '<label class="screen-reader-text" for="rex_card_top_text">Card Top Text</label>'
            . '<input type="text" class="widefat" id="rex_card_top_text" name="rex_card_top_text" value="'
            . esc_attr(get_post_meta($post->ID, 'rex_card_top_text', true)) . '" />'
            . '<p class="description">Shown above the title on About page post cards, in place of the date. Leave empty to show nothing.</p>';
    }, 'post', 'side');
});

add_action('save_post_post', static function ($post_id) {
    if (!isset($_POST['rex_card_top_text_nonce'])
        || !wp_verify_nonce(sanitize_key($_POST['rex_card_top_text_nonce']), 'rex_card_top_text')
        || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        || !current_user_can('edit_post', $post_id)) {
        return;
    }

    $value = isset($_POST['rex_card_top_text']) ? sanitize_text_field(wp_unslash($_POST['rex_card_top_text'])) : '';
    if ($value === '') {
        delete_post_meta($post_id, 'rex_card_top_text');
    } else {
        update_post_meta($post_id, 'rex_card_top_text', $value);
    }
});

// Swap in the field / lift the excerpt limit only while Uncode renders posts modules on the About page.
$rex_card_overrides = static function () {
    static $filters = null;
    $filters = $filters ?: array(
        'length' => static function () {
            return PHP_INT_MAX;
        },
        'date' => static function ($date, $format, $post) {
            return esc_html(get_post_meta(get_post($post)->ID, 'rex_card_top_text', true));
        },
    );
    return $filters;
};

add_filter('pre_do_shortcode_tag', static function ($output, $tag) use ($rex_card_overrides) {
    if ($tag === 'uncode_index' && is_page('about-us')) {
        $filters = $rex_card_overrides();
        add_filter('uncode_block_data_text_length', $filters['length']);
        add_filter('get_the_date', $filters['date'], 10, 3);
    }
    return $output;
}, 10, 2);

add_filter('do_shortcode_tag', static function ($output, $tag) use ($rex_card_overrides) {
    if ($tag === 'uncode_index' && is_page('about-us')) {
        $filters = $rex_card_overrides();
        remove_filter('uncode_block_data_text_length', $filters['length']);
        remove_filter('get_the_date', $filters['date'], 10);
        // Drop the empty meta line left by posts without Card Top Text.
        $output = preg_replace('#<p class="t-entry-meta">\s*<span class="t-entry-date"></span>\s*</p>#', '', $output);
        $output = str_replace('<p class="t-entry-meta"><span class="t-entry-date">', '<p class="t-entry-meta rex-card-top-text"><span class="t-entry-date">', $output);
        // Uncode crops every thumbnail to the 4:5 card frame; for images of another shape use the
        // original file and size the frame to the image's own ratio, so it shows whole and fills it.
        $output = preg_replace_callback(
            '#<div class="dummy" style="padding-top: [\d.]+%;"></div>(\s*<a\b(?:(?!<img\b).)*?)(<img\b[^>]*\bsrcset-async\b[^>]*>)#s',
            static function ($m) {
                $img = $m[2];
                if (!preg_match('#data-width="(\d+)"#', $img, $w) || !preg_match('#data-height="(\d+)"#', $img, $h)
                    || !preg_match('#data-guid="([^"]+)"#', $img, $src) || !(int) $w[1] || !(int) $h[1]
                    || abs(((int) $w[1] / (int) $h[1]) - 0.8) < 0.05) {
                    return $m[0];
                }
                $img = preg_replace('#(?<=\s)src="[^"]*"#', 'src="' . $src[1] . '"', $img);
                $img = preg_replace('#(?<=\s)width="\d+"#', 'width="' . $w[1] . '"', $img);
                $img = preg_replace('#(?<=\s)height="\d+"#', 'height="' . $h[1] . '"', $img);
                // Drop Uncode's lazy-load placeholder; its script only swaps it on .srcset-async images.
                $img = preg_replace('#\s(?:data-srcset|srcset|sizes)="[^"]*"#', '', $img);
                $img = str_replace(array('srcset-async srcset-auto', 'srcset-async'), 'rex-fit-contain', $img);
                $ratio = round((int) $h[1] / (int) $w[1] * 100, 4);
                return '<div class="dummy" style="padding-top: ' . $ratio . '%;"></div>' . $m[1] . $img;
            },
            $output
        );
    }
    return $output;
}, 10, 2);

// Card Top Text is set larger than the post title (35px).
add_action('wp_head', static function () {
    if (!is_page('about-us')) {
        return;
    }
    echo '<style id="rex-card-top-text-style">'
        . '.tmb .t-entry p.t-entry-meta.rex-card-top-text{margin-bottom:.4em}'
        // Journal section (desktop row 2, mobile row 3): Instrument Sans instead of the builder's EB Garamond.
        . '#row-unique-2 .font-165032,#row-unique-3 .font-165032{font-family:"Instrument Sans",sans-serif!important}'
        . '.tmb .t-entry p.t-entry-meta.rex-card-top-text span.t-entry-date{font-family:"Instrument Sans",sans-serif;font-size:46px;line-height:1;font-weight:600;text-transform:none;letter-spacing:0}'
        . '@media (max-width:569px){.tmb .t-entry p.t-entry-meta.rex-card-top-text span.t-entry-date{font-size:40px}}'
        . '.tmb .t-entry-visual img.rex-fit-contain{object-fit:contain;object-position:center}'
        // Journal cards side by side: wider text column; every image cropped to one frame ratio (foundry.jpg, 394x261).
        . '@media (min-width:570px){'
        . ':is(#row-unique-2,#row-unique-3) .tmb-content-lateral.tmb>.t-inside{display:flex!important;align-items:flex-start!important}'
        . ':is(#row-unique-2,#row-unique-3) .tmb-content-lateral.tmb>.t-inside>.t-entry-visual{--rex-img-scale:1.2;width:calc(45% * var(--rex-img-scale))!important;flex:0 0 auto}'
        . ':is(#row-unique-2,#row-unique-3) .tmb-content-lateral.tmb>.t-inside .t-entry-text{width:55%!important;flex:0 0 auto}'
        . ':is(#row-unique-2,#row-unique-3) .tmb .t-entry-text-tc{padding-left:48px!important}'
        . ':is(#row-unique-2,#row-unique-3) .tmb .t-entry-visual .dummy{padding-top:66.24%!important}'
        . ':is(#row-unique-2,#row-unique-3) .tmb .t-entry-visual img{object-fit:cover!important;object-position:center}'
        . '}'
        // Align card text with the image top: drop the builder's leading spacer while image and text are side by side.
        . '@media (min-width:570px){#row-unique-2 .tmb .t-entry>.spacer:first-child,#row-unique-3 .tmb .t-entry>.spacer:first-child{display:none}'
        . '#row-unique-2 .tmb .t-entry>.spacer:first-child+*,#row-unique-3 .tmb .t-entry>.spacer:first-child+*{margin-top:0!important}}'
        . '</style>';
});
