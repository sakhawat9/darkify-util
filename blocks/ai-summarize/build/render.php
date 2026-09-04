<?php

/**
 * Server-side render for darkify-util/ai-summarize.
 *
 * Copied into build/ by wp-scripts and named by block.json's `render` field.
 * Deliberately thin: the markup lives in templates/ai-summarize.php with the
 * rest of this plugin's markup, and everything else is the block class's job.
 *
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Darkify_Util_AI_Summarize')) {
    return;
}

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the template escapes every value it prints.
echo Darkify_Util_AI_Summarize::instance()->render_block($attributes, $content, $block);
