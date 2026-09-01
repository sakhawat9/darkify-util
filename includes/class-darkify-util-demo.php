<?php

/**
 * [darkify_demo] — the "Try it yourself — right here" section.
 *
 * The demo is not a mock-up of Darkify: it is Darkify. The sample site is
 * rendered into an isolated same-origin preview frame, booted with the very
 * assets, settings and inline configuration the host page already carries — the
 * real client engine, the real switcher markup (through Darkify's own [darkify]
 * shortcode) and the site's own palette. Clicking the switcher runs the actual
 * plugin against the sample page.
 *
 * Darkify_Util_Preview holds the frame machinery and the plugin lookups this
 * shares with the auto-playing hero preview; what lives here is this demo's own
 * sample site, its controls and its navigation.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Darkify_Util_Demo')) {

    class Darkify_Util_Demo extends Darkify_Util_Preview
    {
        const SHORTCODE = 'darkify_demo';

        /**
         * @var Darkify_Util_Demo|null
         */
        private static $instance = null;

        public static function instance()
        {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /* --------------------------------------------------------------- */
        /* Rendering                                                       */
        /* --------------------------------------------------------------- */

        public function render($atts, $content = null)
        {
            $atts = shortcode_atts(array(
                'switch'      => 'classic',
                'switch_size' => '100',
                'brand'       => __('Your Brand', 'darkify-util'),
                'url'         => 'yoursite.com',
                // The sample site's navigation. `menu` names a real WordPress
                // menu — a block theme's navigation or a classic nav menu — by
                // title, slug or ID, so the demo shows the site's own menu and
                // follows it when it changes. `nav` is the manual alternative:
                // `Label|https://…` items, comma separated. With neither, the
                // sample site keeps its generic, unlinked labels.
                'menu'        => '',
                'nav'         => '',
                'menu_limit'  => '6',
                'heading'     => '',
                'subtitle'    => '',
                'note'        => '',
                'max_width'   => '900',
                // Demo controls. `presets` takes Darkify colour-preset keys
                // (empty = the presets the installed edition ships for free),
                // `sizes` takes `Label:percent` items (the percent is Darkify's
                // own switch_size), and `positions` names the placements
                // offered — all four of Darkify's corners by default, listed in
                // the order its own Switcher Style setting lists them. Set
                // `controls="no"` for the preview on its own.
                'controls'    => 'yes',
                'presets'     => '',
                'preset'      => '',
                'sizes'       => 'XS:50,S:60,M:75,L:85,XL:100,XXL:125',
                'positions'   => 'bottom-right,bottom-left,top-right,top-left',
                'position'    => 'bottom-right',
                // Optional override for the switcher's corner radius (e.g.
                // "50%" for a circle). Empty keeps whatever Darkify's own
                // Switcher Style settings say.
                'radius'      => '',
            ), $atts, self::SHORTCODE);

            // Darkify itself provides the engine and the switcher. Without a
            // running Darkify frontend there is nothing to demonstrate, and the
            // demo will not stand in for it with a lookalike.
            if (!$this->darkify_frontend_active()) {
                return '<!-- darkify_demo: Darkify is not active on the frontend. -->';
            }

            $this->enqueue();

            $variant = $this->switch_variant($atts['switch']);
            $this->enqueue_switcher_style($variant);

            $presets     = $this->color_presets($atts['presets']);
            $sizes       = $this->parse_sizes($atts['sizes']);
            $positions   = $this->parse_positions($atts['positions']);
            $switch_size = max(40, min(200, (int) $atts['switch_size']));

            // The control that starts selected and the preview's own starting
            // values are the same thing, so the first paint already matches the
            // controls — no flash, and no state to reconcile. The preset
            // defaults to the one the site itself is set to, so the demo opens
            // showing the real thing.
            $preset   = $this->selected_preset($presets, $atts['preset']);
            $size     = $this->selected_size($sizes, $switch_size);
            $position = $this->selected_position($positions, $atts['position']);

            $data = array(
                'variant'     => $variant,
                'switch_size' => $size ? $size['value'] : $switch_size,
                'preset'      => $preset,
                'radius'      => $this->sanitize_radius($atts['radius']),
                'border'      => $this->switch_border_width(),
                'position'    => $position,
                'brand'       => $atts['brand'],
                'nav'         => $this->nav_items($atts['menu'], $atts['nav'], (int) $atts['menu_limit'], array(
                    __('Home', 'darkify-util'),
                    __('Shop', 'darkify-util'),
                    __('Blog', 'darkify-util'),
                    __('Contact', 'darkify-util'),
                )),
                'url'         => $atts['url'],
                'heading'     => $atts['heading'],
                'subtitle'    => $atts['subtitle'],
                'note'        => $atts['note'],
                'max_width'   => max(320, (int) $atts['max_width']),
                'instance'    => 'dkfd_' . wp_rand(),
                'frame_css'   => self::frame_css_url('assets/css/darkify-demo-frame.css'),
                'controls'    => $this->is_truthy($atts['controls']),
                'presets'     => $presets,
                'sizes'       => $sizes,
                'positions'   => $positions,
            );

            ob_start();
            include DARKIFY_UTIL_PATH . 'templates/demo.php';
            return ob_get_clean();
        }

        /* --------------------------------------------------------------- */
        /* Control options                                                 */
        /* --------------------------------------------------------------- */

        /**
         * `Label:percent`, comma separated. The percent is passed straight to
         * Darkify's own `switch_size` attribute.
         */
        private function parse_sizes($raw)
        {
            $sizes = array();

            foreach (explode(',', (string) $raw) as $item) {
                $parts = explode(':', trim($item), 2);
                $label = trim($parts[0]);
                $value = isset($parts[1]) ? (int) $parts[1] : 0;
                if ($label === '' || $value < 20 || $value > 300) {
                    continue;
                }
                $sizes[] = array(
                    'value' => $value,
                    'label' => $label,
                );
            }

            return $sizes;
        }

        private function parse_positions($raw)
        {
            $labels    = $this->position_labels();
            $positions = array();

            foreach (explode(',', (string) $raw) as $item) {
                $slug = strtolower(trim($item));
                if (!isset($labels[$slug]) || isset($positions[$slug])) {
                    continue;
                }
                $positions[$slug] = array(
                    'value' => $slug,
                    'label' => $labels[$slug],
                );
            }

            return array_values($positions);
        }

        private function position_labels()
        {
            return array(
                'bottom-right' => __('Bottom Right', 'darkify-util'),
                'bottom-left'  => __('Bottom Left', 'darkify-util'),
                'top-right'    => __('Top Right', 'darkify-util'),
                'top-left'     => __('Top Left', 'darkify-util'),
            );
        }

        /**
         * Which preset starts selected: the shortcode's choice, else the one
         * the site itself is set to, else the first offered.
         */
        private function selected_preset($presets, $requested)
        {
            if (!$presets) {
                return '';
            }

            $options = get_option('darkify');
            $site    = is_array($options) && !empty($options['color_pallets']) ? $options['color_pallets'] : '';

            foreach (array(strtolower(trim((string) $requested)), strtolower($site)) as $candidate) {
                if ('' === $candidate) {
                    continue;
                }
                foreach ($presets as $preset) {
                    if (strtolower($preset['value']) === $candidate) {
                        return $preset['value'];
                    }
                }
            }

            return $presets[0]['value'];
        }

        /**
         * The preset closest to the requested `switch_size`, so an explicit size
         * still lights up the control nearest to it instead of nothing.
         */
        private function selected_size($sizes, $switch_size)
        {
            if (!$sizes) {
                return null;
            }

            $closest = null;
            foreach ($sizes as $size) {
                if (null === $closest || abs($size['value'] - $switch_size) < abs($closest['value'] - $switch_size)) {
                    $closest = $size;
                }
            }

            return $closest;
        }

        private function selected_position($positions, $requested)
        {
            $requested = strtolower(trim((string) $requested));
            foreach ($positions as $position) {
                if ($position['value'] === $requested) {
                    return $requested;
                }
            }

            return $positions ? $positions[0]['value'] : 'bottom-right';
        }

        /**
         * The sample site rendered inside the preview frame. Kept out of the
         * wrapper template so the demo's content can be edited (or overridden)
         * on its own.
         */
        public function frame_markup($data)
        {
            ob_start();
            include DARKIFY_UTIL_PATH . 'templates/demo-frame.php';
            return ob_get_clean();
        }
    }
}
