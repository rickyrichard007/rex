<?php

defined('ABSPATH') || exit;

require_once __DIR__ . '/inc/import-rd500n.php';

function rex_3d_views()
{
    return [
        'top' => '0,0.60,0.05,-38,11,3.7,-0.30',
        'problem' => '0.10,0.72,0.08,-62,22,1.95,0.32',
        'cutter' => '0.06,0.89,0.05,-12,6,0.92,-0.34',
        'power' => '-0.06,1.02,0,58,16,1.35,0.30',
        'anatomy' => '0.02,0.66,0.05,-48,14,2.55,-0.28',
        'specs' => '0,0.60,0,-90,6,3.35,-0.02',
        'use' => '0,0.62,0,-150,14,3,0.30',
        'range' => '0,0.62,0.05,20,10,3.3,-0.30',
        'contact' => '0,0.60,0.05,-38,10,3.9,-0.35',
    ];
}

add_action('wp_enqueue_scripts', function () {
    $post = get_post();
    if (!is_page_template('page-rex-3d.php') || !$post || !has_shortcode($post->post_content, 'rex_3d_viewer')) {
        return;
    }
    wp_enqueue_style('rex-3d-page', get_stylesheet_directory_uri() . '/rex-3d.css', [], (string) filemtime(__DIR__ . '/rex-3d.css'));
    // Uncode builds this palette from the global Theme Options.
    $accent = $GLOBALS['front_background_colors']['accent'] ?? '';
    if (is_string($accent) && sanitize_hex_color($accent)) {
        wp_add_inline_style('rex-3d-page', '#rex-3d-page{--rex-accent:' . sanitize_hex_color($accent) . '}');
    }
    foreach (['light', 'dark'] as $skin) {
        $color = $GLOBALS['front_background_colors'][ot_get_option('_uncode_background_color_' . $skin)] ?? '';
        if (is_string($color) && sanitize_hex_color($color)) {
            $selector = '#rex-3d-page .style-' . $skin;
            $page_skin = get_post_meta(get_queried_object_id(), '_uncode_specific_style', true) ?: ot_get_option('_uncode_general_style');
            if ($skin === $page_skin) {
                $selector .= ',#rex-3d-page';
            }
            wp_add_inline_style('rex-3d-page', $selector . '{--rex-surface:' . sanitize_hex_color($color) . '}');
        }
    }
});

add_filter('uncode_single_content_final_output', function ($content) {
    return is_page_template('page-rex-3d.php') ? '<div id="rex-3d-page" class="rex-3d-builder">' . $content . '</div>' : $content;
});

add_shortcode('rex_3d_viewer', function ($atts) {
    $atts = shortcode_atts([
        'folder' => 'rex-3d', 'loading_text' => 'Loading RD500N', 'drag_text' => 'Drag to rotate',
        'canvas_label' => 'Interactive 3D model of the RD500N. Drag to rotate.',
        'error_text' => '3D view unavailable on this device.',
    ], $atts, 'rex_3d_viewer');
    $folder = $atts['folder'];
    if (!preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9_-]*\z/', $folder) || !is_readable(ABSPATH . $folder . '/app.js')) {
        return '<p>' . esc_html__('The 3D model folder is unavailable.', 'uncode-child') . '</p>';
    }
    if (function_exists('vc_is_page_editable') && vc_is_page_editable()) {
        return '<div class="rex-3d-editor-preview">' . esc_html($atts['canvas_label']) . '</div>';
    }
    wp_enqueue_script('rex-3d-renderer', site_url('/' . $folder . '/app.js'), [], (string) filemtime(ABSPATH . $folder . '/app.js'), true);
    return '<div id="rex-3d-viewer" data-error-text="' . esc_attr($atts['error_text']) . '">'
        . '<div id="loader" role="status"><div>' . esc_html($atts['loading_text']) . '</div></div>'
        . '<div id="stage"><canvas id="gl" aria-label="' . esc_attr($atts['canvas_label']) . '"></canvas></div>'
        . '<div id="hot" aria-hidden="true"></div><div id="rail" aria-label="' . esc_attr__('Page sections', 'uncode-child') . '"></div>'
        . '<div id="dragTip" class="drag-tip">' . esc_html($atts['drag_text']) . '</div></div>';
});

add_shortcode('rex_3d_controls', function ($atts) {
    $atts = shortcode_atts([
        'start_text' => 'Start', 'stop_text' => 'Stop', 'running_text' => 'Running',
        'stopped_text' => 'Stopped', 'speed_text' => 'Cutter speed', 'label' => 'Motor control demo',
    ], $atts, 'rex_3d_controls');
    return '<div class="rex-3d-controls" role="group" aria-label="' . esc_attr($atts['label']) . '">'
        . '<button id="btnStart" class="btn btn-default btn-accent">' . esc_html($atts['start_text']) . '</button>'
        . '<button id="btnStop" class="btn btn-default btn-outline">' . esc_html($atts['stop_text']) . '</button>'
        . '<span class="rex-3d-readout"><span id="led" class="led"></span>'
        . '<span id="state" aria-live="polite" data-running="' . esc_attr($atts['running_text']) . '" data-stopped="' . esc_attr($atts['stopped_text']) . '">' . esc_html($atts['stopped_text']) . '</span>'
        . '<strong id="rpm">0%</strong> ' . esc_html($atts['speed_text']) . '</span></div>';
});

add_action('vc_after_init', function () {
    if (!function_exists('vc_map') || !function_exists('vc_add_params')) {
        return;
    }
    vc_add_params('vc_row', [
        ['type' => 'dropdown', 'heading' => '3D camera view', 'param_name' => 'rex_3d_view', 'value' => ['No 3D scene' => '', 'Intro' => 'top', 'Why' => 'problem', 'Cutter' => 'cutter', 'Power' => 'power', 'Anatomy' => 'anatomy', 'Specifications' => 'specs', 'Use' => 'use', 'Range' => 'range', 'Contact' => 'contact'], 'group' => 'REX 3D'],
        ['type' => 'textfield', 'heading' => 'Navigation label', 'param_name' => 'rex_3d_label', 'group' => 'REX 3D'],
    ]);
    foreach (['vc_column', 'vc_column_inner'] as $column) {
        vc_add_param($column, ['type' => 'dropdown', 'heading' => 'Highlight 3D part', 'param_name' => 'rex_3d_hotspot', 'value' => ['None' => '', 'Push-button station' => 'switch', 'Motor' => 'motor', 'Cutter head' => 'cutter', 'Hand lever' => 'lever', 'Chute' => 'chute', 'Base plate' => 'base'], 'group' => 'REX 3D', 'description' => 'The heading in this column supplies the model hotspot label.']);
    }
    foreach ([
        'rex_3d_viewer' => ['REX 3D Viewer', ['folder' => ['Model folder', 'rex-3d'], 'loading_text' => ['Loading text', 'Loading RD500N'], 'drag_text' => ['Drag hint', 'Drag to rotate'], 'canvas_label' => ['Accessible model description', 'Interactive 3D model of the RD500N. Drag to rotate.'], 'error_text' => ['Unavailable message', '3D view unavailable on this device.']]],
        'rex_3d_controls' => ['REX 3D Motor Controls', ['start_text' => ['Start button', 'Start'], 'stop_text' => ['Stop button', 'Stop'], 'running_text' => ['Running status', 'Running'], 'stopped_text' => ['Stopped status', 'Stopped'], 'speed_text' => ['Speed label', 'Cutter speed'], 'label' => ['Accessible controls description', 'Motor control demo']]],
    ] as $base => $element) {
        $params = [];
        foreach ($element[1] as $name => $field) {
            $params[] = ['type' => 'textfield', 'heading' => $field[0], 'param_name' => $name, 'value' => $field[1], 'save_always' => true, 'admin_label' => $name === 'loading_text' || $name === 'start_text'];
        }
        vc_map(['name' => $element[0], 'base' => $base, 'category' => 'REX 3D', 'params' => $params]);
    }
});

add_filter('vc_shortcode_output', function ($output, $shortcode, $atts, $tag) {
    $attributes = '';
    if ($tag === 'vc_row' && !empty($atts['rex_3d_view'])) {
        $view = $atts['rex_3d_view'];
        $views = rex_3d_views();
        if (!isset($views[$view])) {
            return $output;
        }
        $attributes = ' data-cam="' . esc_attr($views[$view]) . '" data-label="' . esc_attr($atts['rex_3d_label'] ?? '') . '"';
        if (in_array($view, ['cutter', 'anatomy', 'specs'], true)) {
            $attributes .= ' data-' . ['cutter' => 'spin', 'anatomy' => 'hs', 'specs' => 'turn'][$view] . '="1"';
        }
    } elseif (in_array($tag, ['vc_column', 'vc_column_inner'], true) && !empty($atts['rex_3d_hotspot'])) {
        if (!in_array($atts['rex_3d_hotspot'], ['switch', 'motor', 'cutter', 'lever', 'chute', 'base'], true)) {
            return $output;
        }
        $attributes = ' data-hs="' . esc_attr($atts['rex_3d_hotspot']) . '" data-rex-part="1" role="button" tabindex="0" aria-pressed="false"';
    }
    if ($attributes !== '') {
        $output = preg_replace_callback('/<div\b/', static function ($match) use ($attributes) {
            return $match[0] . $attributes;
        }, $output, 1);
    }
    return $output;
}, 10, 4);
