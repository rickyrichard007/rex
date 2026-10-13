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

// Keep the founder message first on small screens, but show the portrait on the left on desktop.
add_filter('uncode_get_single_content_raw', static function ($content) {
    if (!is_page('about-us')) {
        return $content;
    }

    $content = preg_replace_callback(
        '#\[vc_row\b[^\]]*\buncode_shortcode_id="162845"[^\]]*\]#',
        static function ($row) {
            return preg_replace('#\bel_class="([^"]*)"#', 'el_class="$1 rex-founder-portrait-left"', $row[0], 1);
        },
        $content,
        1
    );

    // Use Uncode's animated Counter in place of this card's 92% Pie Chart.
    $content = preg_replace(
        '#\[vc_pie\b(?=[^\]]*\buncode_shortcode_id="449705")[^\]]*\]#',
        '[uncode_counter value="1977" prefix="Since" font="font-161747" size="h1" weight="500" el_class="rex-since-1977" uncode_shortcode_id="449705"]',
        $content,
        1
    );

    $content = preg_replace('#\[vc_button\b(?=[^\]]*\buncode_shortcode_id="679721")[^\]]*\].*?\[/vc_button\]#s', '', $content, 1);

    return preg_replace('#\[vc_gallery\b(?=[^\]]*\buncode_shortcode_id="205795")[^\]]*\]#', '[rex_about_values]', $content, 1);
});

add_shortcode('rex_about_values', static function () {
    $values = array(
        'Teamwork' => '<circle cx="8" cy="8" r="2.5"/><circle cx="16" cy="8" r="2.5"/><path d="M2.5 19v-1a5.5 5.5 0 0 1 11 0v1M13 13a5.5 5.5 0 0 1 8.5 5v1"/>',
        'Innovation' => '<path d="M9 18h6M10 21h4M8.5 14.5a6 6 0 1 1 7 0c-.9.7-1.5 1.5-1.5 2.5h-4c0-1-.6-1.8-1.5-2.5Z"/>',
        'Compliance' => '<path d="M12 2 20 5v6c0 5-3.2 8.5-8 11-4.8-2.5-8-6-8-11V5l8-3Z"/><path d="m8.5 12 2.5 2.5 4.5-5"/>',
        'Integrity' => '<path d="M12 3v18M5 21h14M4 7h16M7 7l-3 6h6l-3-6Zm10 0-3 6h6l-3-6Z"/>',
        'Accountability' => '<rect x="5" y="4" width="14" height="18" rx="2"/><rect x="9" y="2" width="6" height="4" rx="1"/><path d="m9 14 2 2 4-4"/>',
        'Reliability' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    );

    $output = '<ul class="rex-about-values" aria-label="Our values">';
    foreach ($values as $label => $icon) {
        $output .= '<li><span class="rex-about-value-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' . $icon . '</svg></span><span>' . esc_html($label) . '</span></li>';
    }
    return $output . '</ul>';
});

// A Git-deployed page can still have the Pie Chart in its database; load Counter assets there too.
add_filter('uncode_enqueue_jquery_counterup', static function ($enqueue) {
    return $enqueue || is_page('about-us');
});

add_action('wp_head', static function () {
    if (!is_page('about-us')) {
        return;
    }

    echo '<style id="rex-about-values-style">'
        . '.rex-about-values{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;width:100%;margin:0!important;padding:0!important;list-style:none!important}'
        . '.rex-about-values li{display:flex;align-items:center;gap:8px;min-width:0;min-height:48px;padding:6px;border:1px solid rgba(255,255,255,.17);border-radius:12px;background:rgba(255,255,255,.07);color:#fff;box-sizing:border-box}'
        . '.rex-about-value-icon{display:grid;place-items:center;flex:0 0 28px;width:28px;height:28px;border-radius:50%;background:rgba(167,242,216,.14);color:#a7f2d8}'
        . '.rex-about-value-icon svg{width:17px;height:17px}'
        . '.rex-about-values li>span:last-child{font-size:12px;font-weight:600;line-height:1.2;overflow-wrap:anywhere}'
        . '@media(max-width:600px){.rex-about-values{grid-template-columns:repeat(2,minmax(0,1fr))}}'
        . '</style>';
});

add_action('wp_head', static function () {
    if (is_page('about-us')) {
        echo '<style id="rex-since-counter-style">'
            . '.rex-since-1977{margin-bottom:0}'
            . '.uncell:has(> .uncont > .rex-since-1977){padding-top:40px!important;padding-bottom:40px!important}'
            . '.uncell:has(> .uncont > .rex-since-1977) .uncont{justify-content:flex-start!important}'
            . '.uncell:has(> .uncont > .rex-since-1977) .uncont>.vc_custom_heading_wrap{margin-top:20px!important}'
            . '.rex-since-1977>p{font-size:clamp(50px,6vw,72px)!important;line-height:1.05;margin:0!important}'
            . '.rex-since-1977 .counter-prefix{display:block;color:#74faba;font-size:15px;line-height:1.2;letter-spacing:.16em;text-transform:uppercase;margin-bottom:8px}'
            . '.rex-since-1977 .uncode-counter{color:#fff}'
            . '</style>';
    }
});

add_action('wp_head', static function () {
    if (is_page('about-us')) {
        $portrait = wp_get_attachment_image_url(179943, 'full');
        echo '<style id="rex-founder-layout">@media (min-width:960px){'
            . '.rex-founder-portrait-left{position:relative}'
            . '.rex-founder-portrait-left>.row-parent{display:block!important;width:100%;max-width:none;padding-top:100px!important;padding-bottom:100px!important}'
            . '.rex-founder-portrait-left>.row-parent>.row-inner{display:flex;flex-direction:row-reverse}'
            . '.rex-founder-portrait-left>.row-parent>.row-inner>.wpb_column{flex:0 0 50%;max-width:50%;height:auto!important}'
            . ($portrait ? '.rex-founder-portrait-left>.row-parent>.row-inner>.wpb_column:nth-child(2)>.uncol{display:none}'
                . '.rex-founder-portrait-left:after{content:"";position:absolute;inset:-100px 50% 0 0;background:url("' . esc_url($portrait) . '") center bottom/contain no-repeat;pointer-events:none;z-index:1}' : '')
            . '.rex-founder-portrait-left>.row-parent>.row-inner>.wpb_column:first-child>.uncol>.uncoltable>.uncell>.uncont{max-width:80%!important}'
            . '}</style>';
    }
});

// Fade in the About page intro section (image, headings, divider, text, button) on scroll.
add_action('wp_head', static function () {
    if (!is_page('about-us')) {
        return;
    }

    $items = '#row-unique-1 :is(.uncode-single-media,.vc_custom_heading_wrap,.divider-wrapper,.btn-container)';
    echo '<style id="rex-about-fade-style">'
        . '#row-unique-1 .uncode-single-media-wrapper{border-radius:20px;overflow:hidden}'
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

add_filter('do_shortcode_tag', static function ($output, $tag, $attr) use ($rex_card_overrides) {
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

        // The Journal carousel is informational: its image and title must not open posts.
        if (($attr['el_id'] ?? '') === 'index-950589'
            && in_array($attr['index_type'] ?? '', array('sticky-scroll', 'css_grid'), true)) {
            $remove_card_link = static function ($match) {
                return preg_replace('#\s(?:href|target|role|tabindex)="[^"]*"#i', '', $match[0]);
            };
            $output = preg_replace_callback('#<a\b(?=[^>]*\bclass="[^"]*\bpushed\b)[^>]*>#i', $remove_card_link, $output);
            $output = preg_replace_callback(
                '#<h[1-6]\b[^>]*\bclass="[^"]*\bt-entry-title\b[^"]*"[^>]*>\s*\K<a\b[^>]*>#i',
                $remove_card_link,
                $output
            );
        }
    }
    return $output;
}, 10, 3);

// Card Top Text is set larger than the post title (35px).
add_action('wp_head', static function () {
    if (!is_page('about-us')) {
        return;
    }
    echo '<style id="rex-card-top-text-style">'
        . '.tmb .t-entry p.t-entry-meta.rex-card-top-text{margin-bottom:.4em}'
        // Journal section (desktop row 2, mobile row 3): Instrument Sans instead of the builder's EB Garamond.
        . '#row-unique-2 .font-165032,#row-unique-3 .font-165032{font-family:"Instrument Sans",sans-serif!important}'
        . ':is(#row-unique-2,#row-unique-3) .tmb .t-entry-title a,'
        . ':is(#row-unique-2,#row-unique-3) .tmb .t-entry-title a:hover{color:#fff!important;cursor:default}'
        . ':is(#row-unique-2,#row-unique-3) .tmb .t-entry-visual-cont a.pushed{cursor:default}'
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
