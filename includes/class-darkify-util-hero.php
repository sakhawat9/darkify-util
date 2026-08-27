<?php

/**
 * [darkify_hero_demo] — the auto-playing hero preview.
 *
 * Same machinery as [darkify_demo] (see Darkify_Util_Preview): a sample site in
 * an isolated frame, running the real Darkify engine against real Darkify
 * settings. What differs is who drives it. Nobody clicks anything here — the
 * preview flips itself on a timer, holds each mode long enough to be read, and
 * loops, so a visitor scrolling past a landing page sees the plugin work.
 *
 * The loop is Darkify's own switch, called on an interval inside the frame. It
 * is not a canned animation between two hand-drawn states: every colour in the
 * dark half is one the engine derived from this site's palette, the same way it
 * would on the visitor's own pages.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Darkify_Util_Hero')) {

    class Darkify_Util_Hero extends Darkify_Util_Preview
    {
        const SHORTCODE = 'darkify_hero_demo';

        /**
         * @var Darkify_Util_Hero|null
         */
        private static $instance = null;

        public static function instance()
        {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function render($atts, $content = null)
        {
            $atts = shortcode_atts(array(
                // The sample site.
                'brand'      => __('Your Brand', 'darkify-util'),
                'url'        => 'yoursite.com',
                'heading'    => __('Beautiful dark mode, automatically.', 'darkify-util'),
                'text'       => __('Darkify recolors every background, text, border, image and scrollbar on your site — with contrast that stays readable.', 'darkify-util'),
                'cta'        => __('Get Started', 'darkify-util'),
                'cta_alt'    => __('See Features', 'darkify-util'),
                'menu'       => '',
                'nav'        => '',
                'menu_limit' => '4',
                // The window.
                'max_width'  => '640',
                'switch'     => 'classic',
                'switch_size' => '80',
                'radius'     => '',
                'switcher'   => 'yes',
                'chrome'     => 'yes',
                'badge'      => 'yes',
                // The loop, in milliseconds.
                'autoplay'   => 'yes',
                'light_hold' => '2800',
                'dark_hold'  => '3600',
                'fade'       => '260',
                'start'      => 'light',
            ), $atts, self::SHORTCODE);

            if (!$this->darkify_frontend_active()) {
                return '<!-- darkify_hero_demo: Darkify is not active on the frontend. -->';
            }

            $this->enqueue();

            $variant = $this->switch_variant($atts['switch']);
            $this->enqueue_switcher_style($variant);

            $data = array(
                'instance'    => 'dkfdh_' . wp_rand(),
                'frame_css'   => self::frame_css_url('assets/css/darkify-hero-frame.css'),
                'variant'     => $variant,
                'switch_size' => max(40, min(200, (int) $atts['switch_size'])),
                'radius'      => $this->sanitize_radius($atts['radius']),
                'switcher'    => $this->is_truthy($atts['switcher']),
                'chrome'      => $this->is_truthy($atts['chrome']),
                'badge'       => $this->is_truthy($atts['badge']),
                'brand'       => $atts['brand'],
                'url'         => $atts['url'],
                'heading'     => $atts['heading'],
                'text'        => $atts['text'],
                'cta'         => $atts['cta'],
                'cta_alt'     => $atts['cta_alt'],
                // A real menu when one is named — same attributes and same
                // resolver the demo uses — otherwise short generic labels: this
                // is a hero mock-up, not a site map.
                'nav'         => $this->nav_items($atts['menu'], $atts['nav'], (int) $atts['menu_limit'], array(
                    __('Features', 'darkify-util'),
                    __('Pricing', 'darkify-util'),
                    __('Docs', 'darkify-util'),
                )),
                'max_width'   => max(320, (int) $atts['max_width']),
                'autoplay'    => $this->is_truthy($atts['autoplay']),
                // Clamped: a hold shorter than the fade reads as a flicker, and
                // a very long one looks like the loop has stopped.
                'light_hold'  => max(600, min(20000, (int) $atts['light_hold'])),
                'dark_hold'   => max(600, min(20000, (int) $atts['dark_hold'])),
                'fade'        => max(0, min(1200, (int) $atts['fade'])),
                'start'       => 'dark' === strtolower(trim($atts['start'])) ? 'dark' : 'light',
                'label_light' => __('Light', 'darkify-util'),
                'label_dark'  => __('Dark', 'darkify-util'),
            );

            ob_start();
            include DARKIFY_UTIL_PATH . 'templates/hero.php';
            return ob_get_clean();
        }

        /**
         * The sample site rendered into the preview frame.
         */
        public function frame_markup($data)
        {
            ob_start();
            include DARKIFY_UTIL_PATH . 'templates/hero-frame.php';
            return ob_get_clean();
        }
    }
}
