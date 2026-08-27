<?php

/**
 * Shared machinery for the Darkify previews.
 *
 * Both previews — the interactive [darkify_demo] and the auto-playing
 * [darkify_hero_demo] — are the same idea: a sample site rendered into an
 * isolated same-origin frame, booted with the assets, settings and inline
 * configuration the host page already carries. What differs is the sample site
 * and what drives the switch. Everything else lives here.
 *
 * Isolation is the reason for the frame. Darkify's engine is document-wide by
 * design (it toggles a class on <html> and repaints everything below it), so the
 * only way to show it working on one part of a page without touching the rest is
 * to give it a document of its own. The frame also gets an in-memory
 * localStorage stand-in, so a preview never writes the visitor's real dark-mode
 * preference and never leaks into the site's own switcher.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Darkify_Util_Preview')) {

    abstract class Darkify_Util_Preview
    {
        /** One stylesheet and one script serve every preview on a page. */
        const HANDLE = 'darkify-util-preview';

        /** @var bool Assets registered for this request. */
        protected static $registered = false;

        /** @var bool Assets enqueued for this request. */
        protected static $enqueued = false;

        /**
         * Switcher styles, keyed by the numeric values Darkify's own [darkify]
         * shortcode accepts. 1-5 exist in both editions; the rest are Pro's.
         */
        protected $switch_map = array(
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

        protected function __construct()
        {
            add_shortcode(static::SHORTCODE, array($this, 'render'));

            // Registration is unconditional (cheap, and page builders can ask
            // for the handles); the enqueue only happens when the shortcode is
            // actually on the page. Priority 999 so Darkify's own enqueue
            // (priority 100) has already registered its handles.
            add_action('wp_enqueue_scripts', array($this, 'prepare_assets'), 999);
        }

        /**
         * Render the shortcode.
         *
         * @param array       $atts
         * @param string|null $content
         * @return string
         */
        abstract public function render($atts, $content = null);

        /* --------------------------------------------------------------- */
        /* Assets                                                          */
        /* --------------------------------------------------------------- */

        public function prepare_assets()
        {
            $this->register_assets();
            $this->maybe_enqueue_for_content();
        }

        protected function register_assets()
        {
            if (self::$registered) {
                return;
            }
            self::$registered = true;

            wp_register_style(
                self::HANDLE,
                DARKIFY_UTIL_URL . 'assets/css/darkify-preview.css',
                array(),
                self::asset_version('assets/css/darkify-preview.css')
            );

            wp_register_script(
                self::HANDLE,
                DARKIFY_UTIL_URL . 'assets/js/darkify-preview.js',
                array(),
                self::asset_version('assets/js/darkify-preview.js'),
                true
            );

            wp_localize_script(self::HANDLE, 'DarkifyPreviewData', array(
                // Everything served from these directories is a Darkify asset
                // and gets carried into the frame verbatim.
                'assetBases' => $this->darkify_asset_bases(),
                // Only used if the host page somehow has no Darkify script tag
                // to copy (e.g. an aggressive optimisation plugin inlined it).
                'engineJs'   => $this->darkify_engine_url(),
            ));
        }

        /**
         * Enqueue when this shortcode is present in the queried content, and
         * pre-load the switcher style each instance asks for so it lands in the
         * <head> rather than the footer.
         */
        protected function maybe_enqueue_for_content()
        {
            $post = get_post();
            if (!$post || empty($post->post_content) || !has_shortcode($post->post_content, static::SHORTCODE)) {
                return;
            }

            $this->enqueue();

            preg_match_all('/\[' . static::SHORTCODE . '\b[^\]]*\]/i', $post->post_content, $matches);
            foreach ($matches[0] as $tag) {
                $atts = shortcode_parse_atts(trim(substr($tag, 1, -1)));
                $switch = is_array($atts) && isset($atts['switch']) ? $atts['switch'] : 'classic';
                $this->enqueue_switcher_style($this->switch_variant($switch));
            }
        }

        /**
         * Safe to call late (from render()) for shortcodes that live outside
         * post content — widgets, template parts, page-builder blocks — where
         * the content scan above cannot see them.
         */
        protected function enqueue()
        {
            if (self::$enqueued) {
                return;
            }
            wp_enqueue_style(self::HANDLE);
            wp_enqueue_script(self::HANDLE);
            self::$enqueued = true;
        }

        /**
         * Darkify registers one stylesheet per switcher style. Enqueueing it on
         * the host page is what makes it available to the frame, which copies
         * Darkify's stylesheets across rather than shipping a second copy.
         */
        protected function enqueue_switcher_style($variant)
        {
            $styles = wp_styles();
            if (isset($styles->registered['theme-' . $variant])) {
                wp_enqueue_style('theme-' . $variant);
            }
        }

        protected static function asset_version($relative_path)
        {
            $file = DARKIFY_UTIL_PATH . $relative_path;
            return file_exists($file) ? (string) filemtime($file) : DARKIFY_UTIL_VERSION;
        }

        /**
         * URL of a stylesheet the preview frame links (never enqueued on the
         * host page — it styles the sample site inside the frame).
         */
        protected static function frame_css_url($relative_path)
        {
            return DARKIFY_UTIL_URL . $relative_path . '?ver=' . self::asset_version($relative_path);
        }

        /* --------------------------------------------------------------- */
        /* Darkify lookups                                                 */
        /* --------------------------------------------------------------- */

        /**
         * Base URLs of every installed Darkify plugin, used by the front-end to
         * tell Darkify's own <link>/<script> tags apart from the rest of the page.
         */
        protected function darkify_asset_bases()
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
        protected function darkify_base_url()
        {
            $bases = $this->darkify_asset_bases();
            return $bases ? $bases[0] : '';
        }

        /**
         * URL of Darkify's client engine, taken from its own registration so the
         * preview always boots the same build (minified or not) the host runs.
         */
        protected function darkify_engine_url()
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
        protected function darkify_frontend_active()
        {
            if (!shortcode_exists('darkify') || !$this->darkify_base_url()) {
                return false;
            }

            // The same master control Darkify gates its whole frontend on.
            $options = get_option('darkify');
            return isset($options['enable_dark_mode_switch']) ? (bool) $options['enable_dark_mode_switch'] : true;
        }

        /**
         * Resolve a Darkify class in whichever edition is running.
         *
         * Both editions can be active at once (Pro alongside the free plugin),
         * and then only one of them actually registers the settings schema —
         * the other's registry class exists but is empty. So the edition is
         * chosen by which one holds the schema, not by which class happens to
         * load first; picking on existence alone silently costs the demo its
         * Color preset control.
         */
        protected function darkify_class($relative)
        {
            $namespace = $this->darkify_schema_namespace();
            if ($namespace && class_exists($namespace . $relative)) {
                return $namespace . $relative;
            }

            foreach ($this->darkify_namespaces() as $candidate) {
                if (class_exists($candidate . $relative)) {
                    return $candidate . $relative;
                }
            }

            return null;
        }

        protected function darkify_namespaces()
        {
            return array('ThemeAtelier\\Darkify\\', 'ThemeAtelier\\DarkifyPro\\');
        }

        /**
         * The namespace of the edition whose settings schema is registered.
         */
        protected function darkify_schema_namespace()
        {
            foreach ($this->darkify_namespaces() as $namespace) {
                $registry = $namespace . 'Admin\\Schema\\SchemaRegistry';
                if (class_exists($registry) && !empty($registry::$sections['darkify'])) {
                    return $namespace;
                }
            }

            return null;
        }

        /* --------------------------------------------------------------- */
        /* Sample site navigation                                          */
        /* --------------------------------------------------------------- */

        /**
         * The links across the top of the sample site.
         *
         * A real menu is the point: the demo shows the site's own navigation
         * and follows it when it is edited, instead of carrying a copy that
         * drifts. Block-theme navigations and classic menus both work.
         *
         * @return array<int,array{label:string,url:string}>
         */
        protected function nav_items($menu, $manual, $limit, $defaults = array())
        {
            $limit = $limit > 0 ? $limit : 6;
            $items = array();

            if ('' !== trim((string) $menu)) {
                $items = $this->nav_items_from_menu(trim((string) $menu));
            }

            if (!$items && '' !== trim((string) $manual)) {
                foreach (explode(',', (string) $manual) as $item) {
                    $parts = explode('|', trim($item), 2);
                    $label = trim($parts[0]);
                    if ('' === $label) {
                        continue;
                    }
                    $items[] = array(
                        'label' => $label,
                        'url'   => isset($parts[1]) ? esc_url_raw(trim($parts[1])) : '',
                    );
                }
            }

            if (!$items) {
                foreach ($defaults as $label) {
                    $items[] = array('label' => $label, 'url' => '');
                }
            }

            return array_slice($items, 0, $limit);
        }

        /**
         * Top-level items of a menu named by title, slug or ID — a block
         * theme's `wp_navigation` first, then a classic nav menu.
         */
        protected function nav_items_from_menu($menu)
        {
            $navigation = $this->find_block_navigation($menu);
            if ($navigation) {
                return $this->nav_items_from_blocks(parse_blocks($navigation->post_content));
            }

            $classic = wp_get_nav_menu_object($menu);
            if (!$classic) {
                return array();
            }

            $items = array();
            foreach ((array) wp_get_nav_menu_items($classic->term_id) as $item) {
                if (!empty($item->menu_item_parent)) {
                    continue;
                }
                $items[] = array(
                    'label' => $item->title,
                    'url'   => $item->url,
                );
            }

            return $items;
        }

        protected function find_block_navigation($menu)
        {
            if (is_numeric($menu)) {
                $post = get_post((int) $menu);
                return ($post && 'wp_navigation' === $post->post_type) ? $post : null;
            }

            foreach (array('title' => $menu, 'name' => sanitize_title($menu)) as $field => $value) {
                $found = get_posts(array(
                    'post_type'        => 'wp_navigation',
                    'post_status'      => 'publish',
                    'posts_per_page'   => 1,
                    'no_found_rows'    => true,
                    'suppress_filters' => false,
                    $field             => $value,
                ));
                if ($found) {
                    return $found[0];
                }
            }

            return null;
        }

        /**
         * Walks a navigation block tree. Submenus contribute their own top-level
         * entry; their children stay out of a five-item mock-up.
         */
        protected function nav_items_from_blocks($blocks)
        {
            $items = array();

            foreach ($blocks as $block) {
                $name = isset($block['blockName']) ? $block['blockName'] : '';

                if ('core/navigation-link' === $name || 'core/navigation-submenu' === $name) {
                    $label = isset($block['attrs']['label']) ? $block['attrs']['label'] : '';
                    if ('' !== $label) {
                        $items[] = array(
                            'label' => wp_strip_all_tags($label),
                            'url'   => isset($block['attrs']['url']) ? $block['attrs']['url'] : '',
                        );
                    }
                    continue;
                }

                // Group, row and spacer wrappers nest the real items.
                if (!empty($block['innerBlocks'])) {
                    $items = array_merge($items, $this->nav_items_from_blocks($block['innerBlocks']));
                }
            }

            return $items;
        }

        /* --------------------------------------------------------------- */
        /* Switcher attributes                                             */
        /* --------------------------------------------------------------- */

        /**
         * Normalise a `switch` attribute — Darkify accepts both the style name
         * and the numeric value used by its own shortcode.
         */
        protected function switch_variant($value)
        {
            $value = strtolower(trim((string) $value));

            if (isset($this->switch_map[$value])) {
                $value = $this->switch_map[$value];
            }

            $value = preg_replace('/[^a-z0-9-]/', '', $value);

            return ($value && $this->switcher_style_exists($value)) ? $value : 'classic';
        }

        /**
         * Whether the installed Darkify edition has a stylesheet for a switcher
         * style. The registry is authoritative once Darkify has registered its
         * handles; before that (a shortcode rendered early by a block theme)
         * the plugin's own switcher directory answers the same question.
         */
        protected function switcher_style_exists($variant)
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

        /**
         * The switcher's border width, as a CSS length.
         *
         * Darkify stores this setting as a bare number ("1"). Its floating
         * switcher appends the unit when it writes the `:root` variables
         * (DarkifyUtils::generateSwitchStyles), but its `[darkify]` shortcode
         * passes the raw value straight into `--darkify_switch_border`, where
         * `border-width: 1` is invalid CSS and the browser falls back to
         * `medium` — 3px, whatever the setting says.
         *
         * At full size that reads as a slightly thick edge. On a small switcher
         * it is most of the switcher: at 50% an Orbit pill is 35×15, so a 3px
         * border on every side swallows it.
         */
        protected function switch_border_width()
        {
            $options = get_option('darkify');
            $border  = isset($options['switch_border']) && is_array($options['switch_border']) ? $options['switch_border'] : array();
            $width   = isset($border['all']) ? trim((string) $border['all']) : '';

            if ('' === $width) {
                return '0px';
            }

            return preg_match('/^\d+(\.\d+)?$/', $width) ? $width . 'px' : $width;
        }

        /**
         * A CSS length or percentage; a bare number is treated as pixels.
         */
        protected function sanitize_radius($value)
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

        protected function is_truthy($value)
        {
            return !in_array(strtolower(trim((string) $value)), array('no', 'false', '0', 'off', ''), true);
        }

        /**
         * Darkify's own switcher, rendered by Darkify.
         *
         * The size is passed through the shortcode's own attribute so the first
         * paint already matches; the border width is passed explicitly (see
         * switch_border_width()).
         *
         * @param array $args variant, switch_size, radius
         * @return string
         */
        protected function switcher_markup($args)
        {
            $shortcode = sprintf(
                '[darkify switch="%s" switch_size="%d" border="%s"',
                esc_attr($args['variant']),
                (int) $args['switch_size'],
                esc_attr($this->switch_border_width())
            );

            if (!empty($args['radius'])) {
                $shortcode .= sprintf(' border_radius="%s"', esc_attr($args['radius']));
            }

            // do_shortcode() returns the switcher markup with every value
            // escaped by Darkify itself.
            return do_shortcode($shortcode . ']');
        }
    }
}
