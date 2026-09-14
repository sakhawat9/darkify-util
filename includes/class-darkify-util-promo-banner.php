<?php

/**
 * [darkify_promo_banner] and the darkify-util/promo-banner block.
 *
 * An announcement bar: a message, a countdown to a deadline, a button, and a
 * close control. Three messages, one per state — counting down, urgent (inside
 * the last few hours), and ended — so the copy changes on its own as the
 * deadline approaches, with nobody editing the page.
 *
 * Time is handled in one direction only. The author picks a wall-clock time,
 * stored with no offset and read in the site's timezone (wp_timezone()); that
 * becomes an epoch timestamp here, and everything after — the countdown, the
 * state, the recurrence — is arithmetic on that instant. The browser receives
 * the instant, never the wall-clock string, so a visitor's timezone cannot
 * shift the deadline; it only changes how {date} and {time} read.
 *
 * Dynamic, and the server renders the correct state for the moment of the
 * request. The view script then re-derives everything from the deadline, which
 * is what keeps a page served from a cache honest.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Darkify_Util_Promo_Banner')) {

    class Darkify_Util_Promo_Banner
    {
        const SHORTCODE  = 'darkify_promo_banner';
        const BLOCK_NAME = 'darkify-util/promo-banner';
        const CATEGORY   = 'darkify';

        /**
         * The wrapper's classes.
         *
         * `darkify_ignore`: the bar is dark by design in both modes, and the
         * engine repainting it would fight its own colours.
         */
        const WRAPPER_CLASS = 'darkify-promo darkify_ignore';

        /** The three states, in the order they happen. */
        const STATES = array('active', 'urgent', 'expired');

        /** Placeholders an author can write into a message. */
        const TOKENS = array('date', 'time', 'remaining');

        /** @var Darkify_Util_Promo_Banner|null */
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
            add_action('init', array($this, 'register_block'));
            add_shortcode(self::SHORTCODE, array($this, 'render_shortcode'));

            if (function_exists('get_block_categories')) {
                add_filter('block_categories_all', array($this, 'register_block_category'), 10, 1);
            } else {
                add_filter('block_categories', array($this, 'register_block_category'), 10, 1);
            }
        }

        /* --------------------------------------------------------------- */
        /* Registration                                                    */
        /* --------------------------------------------------------------- */

        /**
         * Register the block from its built block.json.
         */
        public function register_block()
        {
            $build = DARKIFY_UTIL_PATH . 'blocks/promo-banner/build';

            if (!file_exists($build . '/block.json')) {
                // Built assets missing (a fresh checkout before `npm run build`).
                // The shortcode still works; the block simply is not offered.
                return;
            }

            $type = register_block_type($build);

            if ($type && !empty($type->editor_script_handles)) {
                foreach ($type->editor_script_handles as $handle) {
                    wp_set_script_translations($handle, 'darkify-util', DARKIFY_UTIL_PATH . 'languages');
                }
            }
        }

        /**
         * Add a first-party "Darkify" category to the inserter.
         *
         * @param array $categories
         * @return array
         */
        public function register_block_category($categories)
        {
            foreach ((array) $categories as $category) {
                if (isset($category['slug']) && self::CATEGORY === $category['slug']) {
                    return $categories;
                }
            }

            array_unshift($categories, array(
                'slug'  => self::CATEGORY,
                'title' => __('Darkify', 'darkify-util'),
                'icon'  => null,
            ));

            return $categories;
        }

        /* --------------------------------------------------------------- */
        /* Rendering                                                       */
        /* --------------------------------------------------------------- */

        /**
         * Render the block. Called from blocks/promo-banner/build/render.php.
         *
         * @param array         $attributes
         * @param string        $content
         * @param WP_Block|null $block
         * @return string
         */
        public function render_block($attributes, $content = '', $block = null)
        {
            return $this->render($attributes, function_exists('get_block_wrapper_attributes'));
        }

        /**
         * [darkify_promo_banner].
         *
         * Attributes are the block's in snake_case; `deadline` is a site-timezone
         * date and time: [darkify_promo_banner deadline="2026-09-20 18:00"
         * message="Launch offer" button_text="Claim" button_url="/pricing/"].
         *
         * @param array $atts
         * @return string
         */
        public function render_shortcode($atts)
        {
            $atts = shortcode_atts(array(
                'id'              => '',
                'message'         => '',
                'urgent_message'  => '',
                'expired_message' => '',
                'button_text'     => '',
                'button_url'      => '',
                'new_tab'         => '',
                'deadline'        => '',
                'recurring_days'  => '',
                'urgent_hours'    => '',
                'expired'         => '',
                'show_countdown'  => '',
                'show_days'       => '',
                'dismissible'     => '',
                'dismiss_days'    => '',
                'position'        => '',
                'align'           => '',
            ), (array) $atts, self::SHORTCODE);

            $attributes = array();

            foreach (array(
                'id'              => 'bannerId',
                'message'         => 'message',
                'urgent_message'  => 'urgentMessage',
                'expired_message' => 'expiredMessage',
                'button_text'     => 'buttonText',
                'button_url'      => 'buttonUrl',
                'expired'         => 'expiredAction',
                'position'        => 'position',
                'align'           => 'contentAlign',
            ) as $att => $name) {
                if ('' !== $atts[$att]) {
                    $attributes[$name] = $atts[$att];
                }
            }

            if ('' !== $atts['deadline']) {
                // Accept the space a person types as well as the T the editor stores.
                $attributes['targetDate'] = str_replace(' ', 'T', trim($atts['deadline']));
            }

            if ('' !== $atts['recurring_days']) {
                $attributes['recurring']     = true;
                $attributes['recurringDays'] = (int) $atts['recurring_days'];
            }

            foreach (array('urgent_hours' => 'urgentHours', 'dismiss_days' => 'dismissDays') as $att => $name) {
                if ('' !== $atts[$att]) {
                    $attributes[$name] = (int) $atts[$att];
                }
            }

            foreach (array(
                'new_tab'        => 'openInNewTab',
                'show_countdown' => 'showCountdown',
                'show_days'      => 'showDays',
                'dismissible'    => 'dismissible',
            ) as $att => $name) {
                if ('' !== $atts[$att]) {
                    $attributes[$name] = in_array(strtolower($atts[$att]), array('1', 'true', 'yes', 'on'), true);
                }
            }

            return $this->render($attributes);
        }

        /**
         * Render from anywhere: block, shortcode, or a template calling in.
         *
         * @param array $attributes      Raw (unsanitised) attributes.
         * @param bool  $block_wrapper   Build the wrapper with get_block_wrapper_attributes().
         * @return string
         */
        public function render($attributes, $block_wrapper = false)
        {
            $clean = $this->sanitize_attributes($attributes);
            $data  = $this->prepare($clean, time());

            if (null === $data) {
                return '';
            }

            $classes = implode(' ', array_filter(array(
                self::WRAPPER_CLASS,
                'is-state-' . $data['state'],
                'is-position-' . $clean['position'],
                'is-align-' . $clean['contentAlign'],
                $clean['urgentPulse'] ? 'has-pulse' : '',
            )));

            $properties = $this->custom_properties($clean);

            if ($block_wrapper) {
                /*
                 * The custom properties go through get_block_wrapper_attributes()
                 * rather than beside it: both write a `style` attribute, and a
                 * browser keeps only the first.
                 */
                $data['wrapper'] = get_block_wrapper_attributes(array(
                    'class' => $classes,
                    'style' => $properties,
                ));
            } else {
                $data['wrapper'] = 'class="' . esc_attr($classes) . '"'
                    . ('' !== $properties ? ' style="' . esc_attr($properties) . '"' : '');
            }

            ob_start();
            include DARKIFY_UTIL_PATH . 'templates/promo-banner.php';
            return ob_get_clean();
        }

        /**
         * Work out what the banner says right now.
         *
         * @param array $attributes Sanitised attributes.
         * @param int   $now        Unix timestamp.
         * @return array|null Template data, or null when nothing should render.
         */
        public function prepare($attributes, $now)
        {
            $interval = $attributes['recurring'] ? $attributes['recurringDays'] * DAY_IN_SECONDS : 0;
            $deadline = self::resolve_deadline($this->timestamp($attributes['targetDate']), $interval, $now);
            $left     = $deadline ? max(0, $deadline - $now) : 0;
            $state    = self::state_for($deadline, $now, $attributes['urgentHours'] * HOUR_IN_SECONDS);

            if ('expired' === $state && 'hide' === $attributes['expiredAction']) {
                return null;
            }

            $tokens = array(
                'date'      => $deadline ? $this->format_date($deadline, $now) : '',
                'time'      => $deadline ? wp_date(get_option('time_format') . ' T', $deadline) : '',
                'remaining' => $deadline ? $this->human_remaining($left) : '',
            );

            $sources = array(
                'active'  => $attributes['message'],
                // An empty urgent message means "keep saying the main one",
                // not "go blank for the final hours".
                'urgent'  => '' !== $attributes['urgentMessage'] ? $attributes['urgentMessage'] : $attributes['message'],
                'expired' => $attributes['expiredMessage'],
            );

            $messages = array();

            foreach ($sources as $key => $html) {
                $messages[$key] = $this->fill_tokens($html, $tokens);
            }

            $button = null;

            if ($attributes['showButton'] && '' !== $attributes['buttonUrl'] && '' !== trim(wp_strip_all_tags($attributes['buttonText']))) {
                $button = array(
                    'text'   => $attributes['buttonText'],
                    'url'    => $attributes['buttonUrl'],
                    'newTab' => $attributes['openInNewTab'],
                );
            }

            return array(
                'state'         => $state,
                'deadline'      => $deadline,
                'interval'      => $interval,
                'urgentWithin'  => $attributes['urgentHours'] * HOUR_IN_SECONDS,
                'expiredAction' => $attributes['recurring'] ? 'hide' : $attributes['expiredAction'],
                'key'           => 'darkify-promo:' . ('' !== $attributes['bannerId'] ? $attributes['bannerId'] : 'banner'),
                'dismissible'   => $attributes['dismissible'],
                'dismissDays'   => $attributes['dismissDays'],
                'showCountdown' => $attributes['showCountdown'] && $deadline > 0,
                'showLabels'    => 'short' === $attributes['countdownLabels'],
                'units'         => $this->countdown_units($left, $attributes['showDays']),
                'messages'      => $messages,
                'tokens'        => $tokens,
                'button'        => $button,
            );
        }

        /**
         * The deadline currently in force; see resolveDeadline() in deadline.js.
         *
         * @param int $deadline Unix timestamp, or 0.
         * @param int $interval Recurrence in seconds, or 0.
         * @param int $now
         * @return int
         */
        public static function resolve_deadline($deadline, $interval, $now)
        {
            if (!$deadline || !$interval || $now < $deadline) {
                return $deadline;
            }

            return $deadline + ((int) floor(($now - $deadline) / $interval) + 1) * $interval;
        }

        /**
         * @param int $deadline     Resolved deadline, or 0.
         * @param int $now
         * @param int $urgent_within Seconds; 0 for never urgent.
         * @return string One of self::STATES.
         */
        public static function state_for($deadline, $now, $urgent_within)
        {
            if (!$deadline) {
                return 'active';
            }

            $left = $deadline - $now;

            if ($left <= 0) {
                return 'expired';
            }

            return $urgent_within && $left <= $urgent_within ? 'urgent' : 'active';
        }

        /**
         * The target date as a timestamp, read in the site's timezone.
         *
         * @param string $local 'Y-m-d\TH:i:s', already validated.
         * @return int Unix timestamp, or 0.
         */
        protected function timestamp($local)
        {
            if ('' === $local) {
                return 0;
            }

            try {
                $date = new DateTimeImmutable($local, wp_timezone());
            } catch (Exception $e) {
                return 0;
            }

            return $date->getTimestamp();
        }

        /**
         * The countdown's segments at render time; see countdownUnits() in deadline.js.
         *
         * @param int  $left      Seconds left.
         * @param bool $show_days
         * @return array
         */
        protected function countdown_units($left, $show_days)
        {
            $days  = (int) floor($left / DAY_IN_SECONDS);
            $hours = (int) floor(($left % DAY_IN_SECONDS) / HOUR_IN_SECONDS);

            $units = array();

            if ($show_days) {
                $units['days'] = $days;
            }

            $units['hours']   = $show_days ? $hours : $hours + $days * 24;
            $units['minutes'] = (int) floor(($left % HOUR_IN_SECONDS) / MINUTE_IN_SECONDS);
            $units['seconds'] = $left % MINUTE_IN_SECONDS;

            $labels = array(
                'days'    => _x('d', 'countdown: days', 'darkify-util'),
                'hours'   => _x('h', 'countdown: hours', 'darkify-util'),
                'minutes' => _x('m', 'countdown: minutes', 'darkify-util'),
                'seconds' => _x('s', 'countdown: seconds', 'darkify-util'),
            );

            $list = array();

            foreach ($units as $key => $value) {
                $list[] = array(
                    'key'   => $key,
                    'value' => str_pad((string) $value, 2, '0', STR_PAD_LEFT),
                    'label' => $labels[$key],
                );
            }

            return $list;
        }

        /**
         * @param int $deadline
         * @param int $now
         * @return string "Sep 20", or "Jan 3, 2027" outside the current year.
         */
        protected function format_date($deadline, $now)
        {
            $same_year = wp_date('Y', $deadline) === wp_date('Y', $now);

            return wp_date($same_year ? 'M j' : 'M j, Y', $deadline);
        }

        /**
         * Time left as words; see humanRemaining() in deadline.js for the rounding.
         *
         * @param int $left Seconds.
         * @return string
         */
        protected function human_remaining($left)
        {
            if ($left >= 2 * DAY_IN_SECONDS) {
                $days = (int) floor($left / DAY_IN_SECONDS);
                /* translators: %s: number of days. */
                return sprintf(_n('%s day', '%s days', $days, 'darkify-util'), number_format_i18n($days));
            }

            if ($left >= 2 * HOUR_IN_SECONDS) {
                $hours = (int) floor($left / HOUR_IN_SECONDS);
                /* translators: %s: number of hours. */
                return sprintf(_n('%s hour', '%s hours', $hours, 'darkify-util'), number_format_i18n($hours));
            }

            $minutes = max(1, (int) ceil($left / MINUTE_IN_SECONDS));
            /* translators: %s: number of minutes. */
            return sprintf(_n('%s minute', '%s minutes', $minutes, 'darkify-util'), number_format_i18n($minutes));
        }

        /**
         * Swap each {token} for a span the view script can keep current.
         *
         * @param string $html   Sanitised message HTML.
         * @param array  $tokens Rendered values.
         * @return string
         */
        public function fill_tokens($html, $tokens)
        {
            foreach (self::TOKENS as $token) {
                $html = str_replace(
                    '{' . $token . '}',
                    $this->token_markup($token, $tokens[$token]),
                    $html
                );
            }

            return $html;
        }

        /**
         * @param string $token
         * @param string $value
         * @return string
         */
        public function token_markup($token, $value)
        {
            return '<span class="darkify-promo__token" data-darkify-promo-token="' . esc_attr($token) . '">'
                . esc_html($value)
                . '</span>';
        }

        /**
         * Every colour setting the author touched, as custom properties.
         *
         * @param array $attributes Sanitised attributes.
         * @return string Declarations, or ''.
         */
        public function custom_properties($attributes)
        {
            $declarations = array();

            foreach (self::color_properties() as $attribute => $property) {
                if ('' !== $attributes[$attribute]) {
                    $declarations[] = $property . ': ' . $attributes[$attribute];
                }
            }

            foreach ($attributes['buttonPadding'] as $side => $value) {
                if ('' !== $value) {
                    $declarations[] = '--darkify-promo-button-padding-' . $side . ': ' . $value;
                }
            }

            if ('' !== $attributes['buttonBorderWidth']) {
                $declarations[] = '--darkify-promo-button-border-width: ' . $attributes['buttonBorderWidth'] . 'px';
            }

            if ('' !== $attributes['buttonRadius']) {
                $declarations[] = '--darkify-promo-button-radius: ' . $attributes['buttonRadius'] . 'px';
            }

            return implode('; ', $declarations);
        }

        /**
         * Colour attribute => custom property. Mirrored in edit.js.
         *
         * @return array
         */
        public static function color_properties()
        {
            return array(
                'bannerBackground'      => '--darkify-promo-bg',
                'bannerColor'           => '--darkify-promo-color',
                'countdownColor'        => '--darkify-promo-countdown',
                'urgentColor'           => '--darkify-promo-urgent',
                'buttonBackground'      => '--darkify-promo-button-bg',
                'buttonBackgroundHover' => '--darkify-promo-button-bg-hover',
                'buttonColor'           => '--darkify-promo-button-color',
                'buttonColorHover'      => '--darkify-promo-button-color-hover',
                'buttonBorderColor'      => '--darkify-promo-button-border',
                'buttonBorderColorHover' => '--darkify-promo-button-border-hover',
                'closeColor'            => '--darkify-promo-close',
            );
        }

        /* --------------------------------------------------------------- */
        /* Sanitising                                                      */
        /* --------------------------------------------------------------- */

        /**
         * Attribute defaults, matching block.json, for the shortcode path.
         *
         * @return array
         */
        public static function defaults()
        {
            return array(
                'schemaVersion'  => 1,
                'bannerId'       => '',

                'message'        => 'Launch offer — up to 50% off every Pro plan',
                'urgentMessage'  => 'Last chance — up to 50% off ends in {remaining}',
                'expiredMessage' => 'This offer has ended. Thanks for the love!',

                'showButton'     => true,
                'buttonText'     => 'Claim the discount',
                'buttonUrl'      => '',
                'openInNewTab'   => false,

                // Empty means "as designed": the stylesheet's values stand.
                'buttonPadding'     => array('top' => '', 'right' => '', 'bottom' => '', 'left' => ''),
                'buttonBorderWidth' => '',
                'buttonRadius'      => '',

                'targetDate'      => '',
                'recurring'       => false,
                'recurringDays'   => 7,
                'urgentHours'     => 5,
                'expiredAction'   => 'hide',
                'showCountdown'   => true,
                'showDays'        => true,
                'countdownLabels' => 'short',
                'urgentPulse'     => true,

                'dismissible'  => true,
                'dismissDays'  => 7,
                'position'     => 'inline',
                'contentAlign' => 'center',

                'bannerBackground'      => '',
                'bannerColor'           => '',
                'countdownColor'        => '',
                'urgentColor'           => '',
                'buttonBackground'      => '',
                'buttonBackgroundHover' => '',
                'buttonColor'           => '',
                'buttonColorHover'      => '',
                'buttonBorderColor'      => '',
                'buttonBorderColorHover' => '',
                'closeColor'            => '',
            );
        }

        /**
         * Sanitise every attribute. The render path trusts nothing in post_content.
         *
         * @param array $attributes
         * @return array
         */
        public function sanitize_attributes($attributes)
        {
            $attributes = is_array($attributes) ? $attributes : array();
            $defaults   = self::defaults();
            $clean      = array();

            $get = function ($name) use ($attributes, $defaults) {
                return array_key_exists($name, $attributes) ? $attributes[$name] : $defaults[$name];
            };

            $clean['schemaVersion'] = absint($get('schemaVersion'));
            $clean['bannerId']      = substr(sanitize_key((string) $get('bannerId')), 0, 40);

            foreach (array('message', 'urgentMessage', 'expiredMessage') as $name) {
                $clean[$name] = trim(wp_kses((string) $get($name), $this->allowed_message_html()));
            }

            $clean['buttonText'] = trim(wp_kses((string) $get('buttonText'), array(
                'strong' => array(),
                'em'     => array(),
            )));

            $clean['buttonUrl'] = esc_url_raw(trim((string) $get('buttonUrl')));

            foreach (array(
                'showButton',
                'openInNewTab',
                'recurring',
                'showCountdown',
                'showDays',
                'urgentPulse',
                'dismissible',
            ) as $flag) {
                $clean[$flag] = (bool) $get($flag);
            }

            // The shape DateTimePicker stores: a wall-clock time with no offset.
            $target = trim((string) $get('targetDate'));

            $clean['targetDate'] = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $target) ? $target : '';

            foreach (array(
                'recurringDays' => array(1, 365),
                'urgentHours'   => array(0, 168),
                'dismissDays'   => array(0, 365),
            ) as $name => $range) {
                $clean[$name] = max($range[0], min($range[1], absint($get($name))));
            }

            foreach (array(
                'expiredAction'   => array('hide', 'message'),
                'countdownLabels' => array('short', 'none'),
                'position'        => array('inline', 'sticky', 'fixed-bottom'),
                'contentAlign'    => array('center', 'left'),
            ) as $name => $allowed) {
                $value        = $get($name);
                $clean[$name] = in_array($value, $allowed, true) ? $value : $defaults[$name];
            }

            foreach (array_keys(self::color_properties()) as $name) {
                $clean[$name] = $this->color($get($name));
            }

            // Each side matched as a length, since it ends up in a style attribute.
            $padding = is_array($get('buttonPadding')) ? $get('buttonPadding') : array();

            $clean['buttonPadding'] = array();

            foreach (array('top', 'right', 'bottom', 'left') as $side) {
                $value = isset($padding[$side]) ? trim((string) $padding[$side]) : '';

                $clean['buttonPadding'][$side] = preg_match('/^[0-9]+(\.[0-9]+)?(px|rem|em)$/', $value) ? $value : '';
            }

            foreach (array('buttonBorderWidth' => 10, 'buttonRadius' => 100) as $name => $max) {
                $value = $get($name);

                $clean[$name] = '' === $value || null === $value ? '' : (string) min($max, absint($value));
            }

            return $clean;
        }

        /**
         * What a message may contain: RichText's inline formats, and nothing
         * that could break out of the bar.
         *
         * @return array
         */
        protected function allowed_message_html()
        {
            return array(
                'strong' => array(),
                'b'      => array(),
                'em'     => array(),
                'i'      => array(),
                's'      => array(),
                'br'     => array(),
                'a'      => array(
                    'href'   => true,
                    'target' => true,
                    'rel'    => true,
                ),
            );
        }

        /**
         * A CSS colour, or '' if it is not one.
         *
         * The same rules as the social share block: hex, a theme preset var(),
         * or rgb()/rgba() converted to hex — safecss_filter_attr() drops rgba()
         * from the style attribute, hex carries the same alpha without brackets.
         *
         * @param mixed $value
         * @return string
         */
        protected function color($value)
        {
            $value = trim((string) $value);

            if ('' === $value) {
                return '';
            }

            if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value)) {
                return $value;
            }

            if (preg_match('/^var\(\s*--wp--[a-z0-9-]+\s*\)$/i', $value)) {
                return $value;
            }

            if (preg_match('/^transparent$/i', $value)) {
                return $value;
            }

            $pattern = '/^rgba?\(\s*([0-9.]+)\s*[, ]\s*([0-9.]+)\s*[, ]\s*([0-9.]+)\s*(?:[,\/]\s*([0-9.]+)(%?)\s*)?\)$/i';

            if (!preg_match($pattern, $value, $parts)) {
                return '';
            }

            $hex = '#';

            foreach (array($parts[1], $parts[2], $parts[3]) as $channel) {
                $hex .= sprintf('%02x', max(0, min(255, (int) round((float) $channel))));
            }

            if (isset($parts[4]) && '' !== $parts[4]) {
                $alpha = (float) $parts[4] / ('%' === $parts[5] ? 100 : 1);
                $alpha = max(0, min(1, $alpha));

                if ($alpha < 1) {
                    $hex .= sprintf('%02x', (int) round($alpha * 255));
                }
            }

            return $hex;
        }
    }
}
