<?php

/**
 * [darkify_demo] — the "Try it yourself — right here" section.
 *
 * The demo is not a mock-up of Darkify: it is Darkify. The sample site is
 * rendered into an isolated same-origin preview frame, and that frame is booted
 * with the very assets, settings and inline configuration the host page already
 * carries — the real client engine, the real switcher markup (through Darkify's
 * own [darkify] shortcode) and the site's own palette. Clicking the switcher
 * runs the actual plugin against the sample page.
 *
 * Isolation is the reason for the frame. The engine is document-wide by design
 * (it toggles a class on <html> and repaints everything below it), so the only
 * way to show it working on one part of a page without touching the rest is to
 * give it a document of its own. The frame also gets an in-memory localStorage
 * stand-in so playing with the demo never writes the visitor's real dark-mode
 * preference, and never leaks into the site's own switcher.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Darkify_Util_Demo')) {

    class Darkify_Util_Demo
    {
        const SHORTCODE = 'darkify_demo';
        const HANDLE    = 'darkify-util-demo';

        /**
         * Switcher styles, keyed by the numeric values Darkify's own [darkify]
         * shortcode accepts. 1-5 exist in both editions; the rest are Pro's.
         */
        private $switch_map = array(
            '1'  => 'classic',
            '2'  => 'expand',
            '3'  => 'inner-moon',
            '4'  => 'within',
            '5'  => 'orbit',
            '6'  => 'around',
            '7'  => 'dark-side',
            '8'  => 'horizon',
            '9'  => 'eclipse',
            '10' => 'lightbulb',
            '11' => 'dark-inner',
            '12' => 'half-sun',
            '13' => 'simple',
            '14' => 'duality',
            '15' => 'dual',
            '16' => 'shift',
        );

        /**
         * Whether the shortcode assets have been enqueued for this request.
         *
         * @var bool
         */
        private $assets_enqueued = false;

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

        private function __construct()
        {
            add_shortcode(self::SHORTCODE, array($this, 'render'));

            // Registration is unconditional (cheap, and page builders can ask
            // for the handles); the enqueue below only happens when the
            // shortcode is actually on the page. Priority 999 so Darkify's own
            // enqueue (priority 100) has already registered its handles.
            add_action('wp_enqueue_scripts', array($this, 'register_assets'), 999);
        }

        /* --------------------------------------------------------------- */
        /* Assets                                                          */
        /* --------------------------------------------------------------- */

        public function register_assets()
        {
            wp_register_style(
                self::HANDLE,
                DARKIFY_UTIL_URL . 'assets/css/darkify-demo.css',
                array(),
                $this->asset_version('assets/css/darkify-demo.css')
            );

            wp_register_script(
                self::HANDLE,
                DARKIFY_UTIL_URL . 'assets/js/darkify-demo.js',
                array(),
                $this->asset_version('assets/js/darkify-demo.js'),
                true
            );

            wp_localize_script(self::HANDLE, 'DarkifyDemoData', array(
                // Stylesheet for the sample site living inside the preview
                // frame. Never enqueued on the host page — the frame links it.
                'frameCss'   => DARKIFY_UTIL_URL . 'assets/css/darkify-demo-frame.css?ver=' . $this->asset_version('assets/css/darkify-demo-frame.css'),
                // Everything served from these directories is a Darkify asset
                // and gets carried into the frame verbatim.
                'assetBases' => $this->darkify_asset_bases(),
                // Only used if the host page somehow has no Darkify script tag
                // to copy (e.g. an aggressive optimisation plugin inlined it).
                'engineJs'   => $this->darkify_engine_url(),
            ));

            $this->maybe_enqueue_for_content();
        }

        /**
         * Enqueue when the shortcode is present in the queried content, and
         * pre-load the switcher style each instance asks for so it is in the
         * <head> rather than the footer.
         */
        private function maybe_enqueue_for_content()
        {
            $post = get_post();
            if (!$post || empty($post->post_content) || !has_shortcode($post->post_content, self::SHORTCODE)) {
                return;
            }

            $this->enqueue();

            preg_match_all('/\[' . self::SHORTCODE . '\b[^\]]*\]/i', $post->post_content, $matches);
            foreach ($matches[0] as $tag) {
                $atts = shortcode_parse_atts(trim(substr($tag, 1, -1)));
                $switch = is_array($atts) && isset($atts['switch']) ? $atts['switch'] : 'classic';
                $this->enqueue_switcher_style($this->switch_variant($switch));
            }
        }

        /**
         * Enqueue the demo's own assets. Safe to call late (from render()) for
         * shortcodes that live outside post content — widgets, template parts,
         * page-builder blocks — where the content scan above cannot see them.
         */
        private function enqueue()
        {
            if ($this->assets_enqueued) {
                return;
            }
            wp_enqueue_style(self::HANDLE);
            wp_enqueue_script(self::HANDLE);
            $this->assets_enqueued = true;
        }

        /**
         * Darkify registers one stylesheet per switcher style. Enqueueing it on
         * the host page is what makes it available to the frame, which copies
         * Darkify's stylesheets across rather than shipping a second copy of
         * them.
         */
        private function enqueue_switcher_style($variant)
        {
            $styles = wp_styles();
            if (isset($styles->registered['theme-' . $variant])) {
                wp_enqueue_style('theme-' . $variant);
            }
        }

        private function asset_version($relative_path)
        {
            $file = DARKIFY_UTIL_PATH . $relative_path;
            return file_exists($file) ? (string) filemtime($file) : DARKIFY_UTIL_VERSION;
        }

        /**
         * Base URLs of every installed Darkify plugin, used by the front-end to
         * tell Darkify's own <link>/<script> tags apart from the rest of the page.
         */
        private function darkify_asset_bases()
        {
            $bases = array();
            foreach (array('DARKIFY_DIR_URL', 'DARKIFY_PRO_PLUGIN_DIR_URL') as $constant) {
                if (defined($constant)) {
                    $bases[] = constant($constant);
                }
            }
            return $bases;
        }

        /**
         * Base URL of whichever Darkify edition is running.
         */
        private function darkify_base_url()
        {
            $bases = $this->darkify_asset_bases();
            return $bases ? $bases[0] : '';
        }

        /**
         * URL of Darkify's client engine, taken from its own registration so the
         * demo always boots the same build (minified or not) the host page runs.
         */
        private function darkify_engine_url()
        {
            $scripts = wp_scripts();
            if (isset($scripts->registered['darkify-client-main']) && $scripts->registered['darkify-client-main']->src) {
                $registered = $scripts->registered['darkify-client-main'];
                $version = $registered->ver ? $registered->ver : (defined('DARKIFY_VERSION') ? DARKIFY_VERSION : null);
                return $version ? add_query_arg('ver', $version, $registered->src) : $registered->src;
            }

            $base = $this->darkify_base_url();
            if ($base) {
                $min = (apply_filters('darkify_dev_mode', false) || (defined('WP_DEBUG') && WP_DEBUG)) ? '' : '.min';
                return $base . 'src/assets/js/client_main' . $min . '.js';
            }

            return '';
        }

        /**
         * Whether Darkify is running on the frontend.
         *
         * Deliberately based on the plugin's own master setting rather than on
         * whether its assets have been enqueued yet: block themes and SEO
         * plugins render post content well before `wp_enqueue_scripts` fires,
         * so asset state says nothing useful at shortcode time.
         */
        private function darkify_frontend_active()
        {
            if (!shortcode_exists('darkify') || !$this->darkify_base_url()) {
                return false;
            }

            // The same master control Darkify gates its whole frontend on.
            $options = get_option('darkify');
            return isset($options['enable_dark_mode_switch']) ? (bool) $options['enable_dark_mode_switch'] : true;
        }

        /* --------------------------------------------------------------- */
        /* Rendering                                                       */
        /* --------------------------------------------------------------- */

        /**
         * Normalise a `switch` attribute — Darkify accepts both the style name
         * and the numeric value used by its own shortcode.
         */
        private function switch_variant($value)
        {
            $value = strtolower(trim((string) $value));

            if (isset($this->switch_map[$value])) {
                $value = $this->switch_map[$value];
            }

            $value = preg_replace('/[^a-z0-9-]/', '', $value);

            // Darkify ships one stylesheet per switcher style, and the two
            // editions ship different sets. Asking the running plugin which
            // ones exist means Pro's extra styles work here as-is, and a style
            // the installed edition doesn't have falls back cleanly.
            return ($value && $this->switcher_style_exists($value)) ? $value : 'classic';
        }

        /**
         * Whether the installed Darkify edition has a stylesheet for a switcher
         * style. The registry is authoritative once Darkify has registered its
         * handles; before that (a shortcode rendered early by a block theme)
         * the plugin's own switcher directory answers the same question.
         */
        private function switcher_style_exists($variant)
        {
            $styles = wp_styles();
            if (isset($styles->registered['theme-' . $variant])) {
                return true;
            }

            foreach (array('DARKIFY_PATH', 'DARKIFY_PRO_PATH') as $constant) {
                if (!defined($constant)) {
                    continue;
                }
                if (file_exists(constant($constant) . 'src/assets/css/switcher/' . $variant . '.css')) {
                    return true;
                }
            }

            return false;
        }

        public function render($atts, $content = null)
        {
            $atts = shortcode_atts(array(
                'switch'      => 'classic',
                'switch_size' => '100',
                'brand'       => __('Your Brand', 'darkify-util'),
                'url'         => 'yoursite.com',
                'heading'     => '',
                'subtitle'    => '',
                'note'        => '',
                'max_width'   => '900',
                // Demo controls. `presets` takes Darkify colour-preset keys
                // (empty = the presets the installed edition ships for free),
                // `sizes` takes `Label:percent` items (the percent is Darkify's
                // own switch_size), and `positions` names the placements
                // offered. Set `controls="no"` for the preview on its own.
                'controls'    => 'yes',
                'presets'     => '',
                'preset'      => '',
                'sizes'       => 'XS:40,S:55,M:70,L:85,XL:100,XXL:125',
                'positions'   => 'bottom-left,bottom-right',
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
                'position'    => $position,
                'brand'       => $atts['brand'],
                'url'         => $atts['url'],
                'heading'     => $atts['heading'],
                'subtitle'    => $atts['subtitle'],
                'note'        => $atts['note'],
                'max_width'   => max(320, (int) $atts['max_width']),
                'instance'    => 'dkfd_' . wp_rand(),
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
         * A CSS length or percentage; a bare number is treated as pixels.
         */
        private function sanitize_radius($value)
        {
            $value = trim((string) $value);
            if ($value === '') {
                return '';
            }
            if (preg_match('/^\d+(\.\d+)?$/', $value)) {
                return $value . 'px';
            }
            return preg_match('/^\d+(\.\d+)?(px|%|em|rem)$/', $value) ? $value : '';
        }

        private function is_truthy($value)
        {
            return !in_array(strtolower(trim((string) $value)), array('no', 'false', '0', 'off', ''), true);
        }

        /**
         * Darkify's own colour presets, read from the schema the plugin
         * registers on every request (`SchemaRegistry`) rather than copied
         * here: the names, the swatch colours and the per-preset colour values
         * are the plugin's, including anything the site has customised in
         * Settings → Colors.
         *
         * @param string $requested Comma-separated preset keys, or '' for the
         *                          presets the installed edition ships free.
         * @return array
         */
        private function color_presets($requested)
        {
            $field = $this->preset_schema();
            if (!$field || empty($field['options'])) {
                return array();
            }

            $wanted = array_filter(array_map('trim', explode(',', strtolower((string) $requested))));

            $presets = array();
            foreach ($field['options'] as $key => $option) {
                if ($wanted) {
                    if (!in_array(strtolower($key), $wanted, true)) {
                        continue;
                    }
                } elseif (!empty($option['pro_only'])) {
                    // Default: the presets this site can actually use.
                    continue;
                }

                $vars = $this->palette_vars($key);
                if (!$vars) {
                    continue;
                }

                $presets[] = array(
                    'value' => $key,
                    'label' => isset($option['name']) ? $option['name'] : $key,
                    'vars'  => $vars,
                    // Two-tone chip: the preset's page background and its link
                    // colour — the pair that tells the presets apart at a glance.
                    'chip'  => array(
                        $vars['--darkify_dark_mode_bg'],
                        $vars['--darkify_dark_mode_link_color'],
                    ),
                );
            }

            if ($wanted) {
                // Keep the order the shortcode asked for.
                usort($presets, function ($a, $b) use ($wanted) {
                    return array_search(strtolower($a['value']), $wanted, true)
                        - array_search(strtolower($b['value']), $wanted, true);
                });
            }

            return $presets;
        }

        /**
         * The `color_pallets` field from Darkify's registered settings schema.
         */
        private function preset_schema()
        {
            $registry = $this->darkify_class('Admin\\Schema\\SchemaRegistry');
            if (!$registry || empty($registry::$sections['darkify'])) {
                return null;
            }

            $found = null;
            $walk = function ($fields) use (&$walk, &$found) {
                foreach ($fields as $field) {
                    if (!is_array($field) || $found) {
                        continue;
                    }
                    if (isset($field['id']) && 'color_pallets' === $field['id']) {
                        $found = $field;
                        return;
                    }
                    foreach (array('fields', 'tabs') as $child) {
                        if (!empty($field[$child]) && is_array($field[$child])) {
                            $walk($field[$child]);
                        }
                    }
                }
            };

            foreach ($registry::$sections['darkify'] as $section) {
                if (!empty($section['fields'])) {
                    $walk($section['fields']);
                }
            }

            return $found;
        }

        /**
         * The CSS variables one preset resolves to.
         *
         * Same field-to-variable mapping Darkify's own header template uses,
         * over the same values: the site's saved colours where it has any, the
         * schema's declared defaults otherwise.
         */
        private function palette_vars($set)
        {
            $defaults_class = $this->darkify_class('Admin\\Schema\\SchemaDefaults');
            $defaults = $defaults_class ? $defaults_class::for_option('darkify') : array();
            $options  = get_option('darkify');
            $options  = is_array($options) ? $options : array();

            $group = function ($id) use ($options, $defaults) {
                $default = isset($defaults[$id]) ? $defaults[$id] : array();
                $saved   = isset($options[$id]) ? $options[$id] : null;

                if (is_array($default) && is_array($saved)) {
                    return array_merge($default, $saved);
                }

                return null !== $saved && '' !== $saved ? $saved : $default;
            };

            $background = $group('dark_mode_color_' . $set);
            $link       = $group('dark_mode_link_color_' . $set);
            $input      = $group('dark_mode_input_color_' . $set);
            $border     = $group('dark_mode_border_color_' . $set);
            $button     = $group('dark_mode_btn_color_' . $set);

            if (!is_array($background) || empty($background['background'])) {
                return array();
            }

            $vars = array(
                '--darkify_dark_mode_bg'                     => $background['background'],
                '--darkify_dark_mode_secondary_bg'           => isset($background['secondary_background']) ? $background['secondary_background'] : '',
                '--darkify_dark_mode_text_color'             => isset($link['text']) ? $link['text'] : '',
                '--darkify_dark_mode_link_color'             => isset($link['color']) ? $link['color'] : '',
                '--darkify_dark_mode_link_hover_color'       => isset($link['hover']) ? $link['hover'] : '',
                '--darkify_dark_mode_input_bg'               => isset($input['background']) ? $input['background'] : '',
                '--darkify_dark_mode_input_text_color'       => isset($input['color']) ? $input['color'] : '',
                '--darkify_dark_mode_input_placeholder_color' => isset($input['placeholder']) ? $input['placeholder'] : '',
                '--darkify_dark_mode_border_color'           => is_string($border) ? $border : '',
                '--darkify_dark_mode_btn_text_color'         => isset($button['color']) ? $button['color'] : '',
                '--darkify_dark_mode_btn_bg'                 => isset($button['background']) ? $button['background'] : '',
                '--darkify_dark_mode_btn_text_hover_color'   => isset($button['hover_color']) ? $button['hover_color'] : '',
                '--darkify_dark_mode_btn_hover_bg'           => isset($button['hover_background']) ? $button['hover_background'] : '',
                '--darkify_dark_mode_btn_border_color'       => isset($button['border']) ? $button['border'] : '',
                '--darkify_dark_mode_btn_hover_border_color' => isset($button['hover_border']) ? $button['hover_border'] : '',
            );

            return array_filter($vars, function ($value) {
                return is_string($value) && '' !== $value;
            });
        }

        /**
         * Resolve a Darkify class in whichever edition is installed.
         */
        private function darkify_class($relative)
        {
            foreach (array('ThemeAtelier\\Darkify\\', 'ThemeAtelier\\DarkifyPro\\') as $namespace) {
                $class = $namespace . $relative;
                if (class_exists($class)) {
                    return $class;
                }
            }

            return null;
        }

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
