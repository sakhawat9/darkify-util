<?php

/**
 * Server-side render for darkify-util/promo-banner.
 *
 * Copied into build/ by wp-scripts and named by block.json's `render` field.
 * Deliberately thin: the markup lives in templates/promo-banner.php with the
 * rest of this plugin's markup, and everything else is the block class's job.
 *
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Darkify_Util_Promo_Banner')) {
    return;
}

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the template escapes every value it prints.
echo Darkify_Util_Promo_Banner::instance()->render_block($attributes, $content, $block);
