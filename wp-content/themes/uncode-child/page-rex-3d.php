<?php
/*
Template Name: REX 3D
Template Post Type: page
*/

defined('ABSPATH') || exit;

// Keep the existing live page available until its builder content is imported.
if (trim((string) get_post_field('post_content', get_queried_object_id())) === '') {
    require __DIR__ . '/inc/rex-3d-legacy.php';
    return;
}

// Use Uncode's normal rendering and inherited Theme/Page Options.
require get_template_directory() . '/page.php';
