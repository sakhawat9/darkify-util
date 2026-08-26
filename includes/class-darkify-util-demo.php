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
                'switch_size' => '80',
                'brand'       => __('Your Brand', 'darkify-util'),
                'url'         => 'yoursite.com',
                'heading'     => '',
                'subtitle'    => '',
                'note'        => '',
                'max_width'   => '900',
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

            $data = array(
                'variant'     => $variant,
                'switch_size' => max(40, min(150, (int) $atts['switch_size'])),
                'brand'       => $atts['brand'],
                'url'         => $atts['url'],
                'heading'     => $atts['heading'],
                'subtitle'    => $atts['subtitle'],
                'note'        => $atts['note'],
                'max_width'   => max(320, (int) $atts['max_width']),
                'instance'    => 'dkfd_' . wp_rand(),
            );

            ob_start();
            include DARKIFY_UTIL_PATH . 'templates/demo.php';
            return ob_get_clean();
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
