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
 * The loop is Darkify's own switch, thrown on an interval inside the frame — by
 * an animated cursor that walks over and clicks it, so the sequence reads as
 * somebody using the plugin rather than a slideshow. It is not a canned
 * animation between two hand-drawn states either: every colour in the dark half
 * is one the engine derived from this site's palette, the same way it would on
 * the visitor's own pages.
 *
 * The loop then covers the plugin's other half. Once the front end has shown
 * the flip, the preview walks to wp-admin — a mock of Darkify's own settings
 * screen, in the same frame and under the same engine — throws the switch from
 * the admin bar, and comes back. Two views, one document, one continuous loop,
 * and the address in the window chrome follows along.
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
                // The part of the heading set in the brand colour, the way the
                // site's own H1 closes on an orange phrase. '' for none.
                'heading_accent' => __('automatically.', 'darkify-util'),
                'eyebrow'    => __('Dark mode ready', 'darkify-util'),
                'text'       => __('Darkify recolors every background, text, border, image and scrollbar on your site — with contrast that stays readable.', 'darkify-util'),
                'cta'        => __('Get Started', 'darkify-util'),
                'cta_alt'    => __('See Features', 'darkify-util'),
                // The star row under the buttons. '' hides it.
                'proof'      => __('Loved by readers, day and night', 'darkify-util'),
                'menu'       => '',
                'nav'        => '',
                'menu_limit' => '4',
                // The admin half of the loop.
                'admin'      => 'yes',
                'admin_url'  => 'yoursite.com/wp-admin',
                'admin_brand' => 'Darkify Pro',
                'admin_version' => 'v2.1.0',
                'admin_user' => 'admin',
                // The preset the loop picks in the admin's palette dropdown,
                // by name or by set key. '' skips that step.
                'admin_palette' => 'Verdant Depths',
                // The window.
                'max_width'  => '640',
                'switch'     => 'classic',
                'switch_size' => '80',
                'radius'     => '',
                'switcher'   => 'yes',
                'cursor'     => 'yes',
                'chrome'     => 'yes',
                'badge'      => 'yes',
                // The "Live preview" chip beside the window.
                'highlight'  => 'yes',
                // The loop, in milliseconds.
                'autoplay'   => 'yes',
                'light_hold' => '3000',
                'dark_hold'  => '3800',
                'admin_hold' => '3000',
                'admin_dark_hold' => '3800',
                'palette_hold' => '3600',
                'fade'       => '260',
                'start'      => 'light',
            ), $atts, self::SHORTCODE);

            if (!$this->darkify_frontend_active()) {
                return '<!-- darkify_hero_demo: Darkify is not active on the frontend. -->';
            }

            $this->enqueue();

            $variant = $this->switch_variant($atts['switch']);
            $this->enqueue_switcher_style($variant);

            $admin = $this->is_truthy($atts['admin']) && $this->is_truthy($atts['autoplay']);

            /*
             * The palette step is opt-in on the plugin actually offering it.
             * The free edition's schema, a customised install, or a Darkify old
             * enough not to register `color_pallets` all resolve to nothing —
             * and then the dropdown is not rendered and the loop simply does
             * not have that step, rather than miming a control that is not
             * there.
             */
            $admin_presets = $admin ? $this->color_presets('') : array();
            $admin_palette = $admin_presets ? $this->find_preset($admin_presets, $atts['admin_palette']) : null;

            if (!$admin_palette) {
                $admin_presets = array();
            }

            /*
             * The heading is split around its accent rather than taking markup
             * from the attribute, so every piece is still escaped as plain text.
             * The last occurrence wins: an accent is the phrase a heading lands
             * on. A heading that does not contain it simply renders unaccented.
             */
            $heading = (string) $atts['heading'];
            $accent  = trim((string) $atts['heading_accent']);
            $at      = '' !== $accent ? strrpos($heading, $accent) : false;

            $data = array(
                'instance'    => 'dkfdh_' . wp_rand(),
                'highlight'   => $this->is_truthy($atts['highlight']),
                'eyebrow'     => $atts['eyebrow'],
                'proof'       => $atts['proof'],
                'heading_lead'   => false === $at ? $heading : substr($heading, 0, $at),
                'heading_accent' => false === $at ? '' : $accent,
                'heading_tail'   => false === $at ? '' : substr($heading, $at + strlen($accent)),
                // The three cards on the sample page. Real titles rather than
                // placeholder bars: text is what the engine recolours best, and
                // it makes the mock read as a page rather than a wireframe.
                'features'    => array(
                    array(
                        'title' => __('Lightweight', 'darkify-util'),
                        'icon'  => '<path d="M13 3 5 13.5h6L10 21l8-10.5h-6L13 3Z"/>',
                    ),
                    array(
                        'title' => __('Accessible', 'darkify-util'),
                        'icon'  => '<circle cx="12" cy="12" r="8.5"/><path d="M12 3.5v17a8.5 8.5 0 0 0 0-17Z" fill="currentColor"/>',
                    ),
                    array(
                        'title' => __('Automatic', 'darkify-util'),
                        'icon'  => '<path d="m12 3.5 1.8 4.7 4.7 1.8-4.7 1.8L12 16.5l-1.8-4.7L5.5 10l4.7-1.8L12 3.5ZM18.5 15l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8.8-2Z"/>',
                    ),
                ),
                'frame_css'   => self::frame_css_url('assets/css/darkify-hero-frame.css'),
                'variant'     => $variant,
                'switch_size' => max(40, min(200, (int) $atts['switch_size'])),
                'radius'      => $this->sanitize_radius($atts['radius']),
                'switcher'    => $this->is_truthy($atts['switcher']),
                'cursor'      => $this->is_truthy($atts['cursor']) && $this->is_truthy($atts['switcher']),
                'chrome'      => $this->is_truthy($atts['chrome']),
                'badge'       => $this->is_truthy($atts['badge']),
                'brand'       => $atts['brand'],
                'url'         => $atts['url'],
                // The admin view is the second half of the loop: the same flip,
                // shown where the plugin's other half lives. It needs the loop
                // to drive it, so it follows autoplay.
                'admin'       => $admin,
                'admin_url'   => $atts['admin_url'],
                'admin_brand' => $atts['admin_brand'],
                'admin_version' => $atts['admin_version'],
                'admin_user'  => $atts['admin_user'],
                // Darkify's real presets, read from the schema it registers —
                // the same list, names and swatch colours the plugin's own
                // Colors screen offers, including anything this site has
                // customised. The dropdown in the mock is not a drawing of a
                // control; it is that control's actual contents.
                'admin_presets' => $admin_presets,
                'admin_palette' => $admin_palette,
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
                'admin_hold'  => max(600, min(20000, (int) $atts['admin_hold'])),
                'admin_dark_hold' => max(600, min(20000, (int) $atts['admin_dark_hold'])),
                'palette_hold' => max(600, min(20000, (int) $atts['palette_hold'])),
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
         * The preset the loop reaches for, matched on the name Darkify shows in
         * its own Colors screen ("Verdant Depths") or on the raw set key
         * ("set9") — whichever the shortcode was given.
         *
         * @return array|null
         */
        private function find_preset($presets, $requested)
        {
            $requested = strtolower(trim((string) $requested));
            if ('' === $requested) {
                return null;
            }

            foreach ($presets as $preset) {
                if (strtolower($preset['label']) === $requested || strtolower($preset['value']) === $requested) {
                    return $preset;
                }
            }

            return null;
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
