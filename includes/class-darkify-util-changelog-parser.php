<?php

/**
 * Turns a pasted changelog into structured data.
 *
 * Written against the real thing rather than an idealised format: the 42
 * versions and 134 entries already on the Changelogs page arrive in two heading
 * shapes and two entry shapes, with ten different spellings of six categories,
 * and both of the following are in there verbatim —
 *
 *     = 2.0.1 – 31 July 2026 =        (en dash)
 *     = 1.0.4 (08 November 2024) =    (parenthesised)
 *     * Added: text                   (bulleted)
 *     Added: text                     (no bullet at all — 44 of them)
 *
 * so the parser accepts all of it and normalises on the way in. The category
 * table it normalises against lives in changelog-categories.json, which the
 * editor's JavaScript parser imports as well: one file, so a "Fix:" entry can
 * never land in a different bucket depending on which side did the parsing.
 *
 * Nothing here is allowed to fail an entry. An unreadable date keeps its text
 * and loses only `dateISO`; an unknown category becomes a new category rather
 * than being dropped.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Darkify_Util_Changelog_Parser')) {

    class Darkify_Util_Changelog_Parser
    {
        /** @var array|null Decoded changelog-categories.json. */
        protected static $table = null;

        /* --------------------------------------------------------------- */
        /* Category table                                                  */
        /* --------------------------------------------------------------- */

        /**
         * The shared category table, read once per request.
         *
         * @return array
         */
        public static function table()
        {
            if (null !== self::$table) {
                return self::$table;
            }

            $defaults = array(
                'canonical'       => array(),
                'fallback'        => array('slug' => 'uncategorised', 'label' => 'Note', 'color' => '#475569'),
                'aliases'         => array(),
                'generatedColors' => array('#0f766e'),
            );

            $path = DARKIFY_UTIL_PATH . 'includes/changelog-categories.json';
            $data = is_readable($path) ? json_decode(file_get_contents($path), true) : null;

            self::$table = is_array($data) ? array_merge($defaults, $data) : $defaults;

            return self::$table;
        }

        /**
         * The categories a fresh block starts with.
         *
         * @return array<int,array{slug:string,label:string,color:string}>
         */
        public static function default_categories()
        {
            $table = self::table();
            return isset($table['canonical']) ? $table['canonical'] : array();
        }

        /**
         * Resolve a label as written into a canonical slug.
         *
         * Case and surrounding punctuation are ignored, so "Fix", "fix" and
         * "FIXED" all land on `fixed`. A label with no alias keeps its own
         * slugified form — an unknown category is a new category, not an error.
         *
         * @param string $label
         * @return string
         */
        public static function normalize_category($label)
        {
            $table = self::table();
            $key   = strtolower(trim((string) $label));
            $key   = trim($key, " \t\n\r\0\x0B:.-");

            if ('' === $key) {
                return $table['fallback']['slug'];
            }

            if (isset($table['aliases'][$key])) {
                return $table['aliases'][$key];
            }

            $slug = sanitize_title($key);

            return '' !== $slug ? $slug : $table['fallback']['slug'];
        }

        /**
         * A colour for a category the table has never seen, picked from a fixed
         * palette by hashing the slug — the same slug always gets the same
         * colour, in the editor and in a migration, without storing a counter.
         *
         * The hash is deliberately this simple one rather than md5: the editor's
         * parser has to produce the identical colour in JavaScript, and a
         * `Security` category invented in the editor picking a different colour
         * from the same category arriving through a migration is exactly the
         * kind of split-brain the shared table exists to prevent. Slugs are
         * sanitize_title()'d, so ord() and charCodeAt() agree on every byte.
         *
         * @param string $slug
         * @return string
         */
        public static function generated_color($slug)
        {
            $table  = self::table();
            $colors = !empty($table['generatedColors']) ? $table['generatedColors'] : array('#0f766e');
            $hash   = 0;
            $length = strlen($slug);

            for ($i = 0; $i < $length; $i++) {
                $hash = ($hash * 31 + ord($slug[$i])) % 100000;
            }

            return $colors[$hash % count($colors)];
        }

        /* --------------------------------------------------------------- */
        /* Parsing                                                         */
        /* --------------------------------------------------------------- */

        /**
         * Parse raw changelog text into the block's `versions` structure.
         *
         * @param string $text
         * @return array {
         *     @type array $versions   Structured versions.
         *     @type array $categories Every category used, canonical first.
         *     @type array $warnings   Lines that could not be classified, with line numbers.
         * }
         */
        public static function parse($text)
        {
            $lines      = preg_split('/\r\n|\r|\n/', (string) $text);
            $versions   = array();
            $warnings   = array();
            $used       = array();
            $current    = null;
            $line_no    = 0;

            foreach ($lines as $line) {
                $line_no++;
                $trimmed = trim($line);

                if ('' === $trimmed) {
                    continue;
                }

                $heading = self::parse_heading($trimmed);

                if (null !== $heading) {
                    if (null !== $current) {
                        $versions[] = $current;
                    }

                    $current = array(
                        'id'      => self::id('v'),
                        'version' => $heading['version'],
                        'date'    => $heading['date'],
                        'dateISO' => self::parse_date($heading['date']),
                        'label'   => $heading['label'],
                        'entries' => array(),
                    );

                    continue;
                }

                $entry = self::parse_entry($trimmed);

                if (null === $entry) {
                    // Kept, not dropped: reported with its line number so a
                    // migration can show exactly what it could not read.
                    $warnings[] = array(
                        'line' => $line_no,
                        'text' => $trimmed,
                    );
                    continue;
                }

                if (null === $current) {
                    // Entries before the first heading still belong somewhere.
                    $current = array(
                        'id'      => self::id('v'),
                        'version' => '',
                        'date'    => '',
                        'dateISO' => '',
                        'label'   => '',
                        'entries' => array(),
                    );
                }

                $used[$entry['category']] = isset($entry['sourceLabel']) ? $entry['sourceLabel'] : '';
                $current['entries'][]     = $entry;
            }

            if (null !== $current) {
                $versions[] = $current;
            }

            return array(
                'versions'   => $versions,
                'categories' => self::categories_for(array_keys($used), $used),
                'warnings'   => $warnings,
            );
        }

        /**
         * A version heading, in any of the shapes our own changelog uses.
         *
         *     = 2.0.1 – 31 July 2026 =
         *     = 1.0.4 (08 November 2024) =
         *     = 1.2.3 - 4 May 2025 =
         *     = 1.2.3 =
         *     == 1.2.3 ==            (readme.txt sections, tolerated)
         *
         * The separator may be a hyphen, en dash or em dash. Anything after a
         * second separator becomes the optional version label, so
         * `= 2.0.0 – 1 May 2026 – Security release =` keeps all three parts.
         *
         * @param string $line
         * @return array|null
         */
        protected static function parse_heading($line)
        {
            if (!preg_match('/^={1,3}\s*(.+?)\s*={1,3}$/u', $line, $matches)) {
                return null;
            }

            $inside = trim($matches[1]);

            if ('' === $inside) {
                return null;
            }

            $version = $inside;
            $date    = '';
            $label   = '';

            // `1.0.4 (08 November 2024)` — parenthesised date.
            if (preg_match('/^(\S+)\s*\(([^)]+)\)\s*(.*)$/u', $inside, $parts)) {
                $version = $parts[1];
                $date    = trim($parts[2]);
                $label   = trim($parts[3]);
            } elseif (preg_match('/^(\S+)\s*[–—-]\s*(.+)$/u', $inside, $parts)) {
                // `2.0.1 – 31 July 2026` and `2.0.1 – 31 July 2026 – Label`.
                $version   = $parts[1];
                $remainder = trim($parts[2]);

                if (preg_match('/^(.+?)\s*[–—-]\s*(.+)$/u', $remainder, $tail)) {
                    $date  = trim($tail[1]);
                    $label = trim($tail[2]);
                } else {
                    $date = $remainder;
                }
            }

            // `1.4.15 16 December 2025` — no separator at all, which one
            // heading in our own changelog uses. Only split when what follows
            // actually reads as a date, so a genuine label ("2.0.0 beta") is
            // not mistaken for one.
            if ('' === $date && preg_match('/^(\S+)\s+(.+)$/u', $version, $parts)) {
                if ('' !== self::parse_date($parts[2])) {
                    $version = $parts[1];
                    $date    = trim($parts[2]);
                }
            }

            // A heading with no digits at all is a readme.txt section such as
            // `== Changelog ==`, not a version.
            if (!preg_match('/\d/', $version)) {
                return null;
            }

            return array(
                'version' => trim($version, " \t\"'"),
                'date'    => $date,
                'label'   => $label,
            );
        }

        /**
         * One entry line, with or without a bullet and with or without a
         * `Category:` prefix.
         *
         * @param string $line
         * @return array|null
         */
        protected static function parse_entry($line)
        {
            $text = preg_replace('/^\s*[*\-•·–]\s+/u', '', $line, 1);
            $text = trim($text);

            if ('' === $text) {
                return null;
            }

            $table         = self::table();
            $category      = $table['fallback']['slug'];
            $source_label  = '';

            // `Added: text`. Deliberately narrow — a label is one or two short
            // words, so a sentence containing a colon ("Note: the following…"
            // aside) is not mistaken for a category, and a URL never is.
            if (preg_match('/^([A-Za-z][A-Za-z ]{1,20}):\s*(.+)$/u', $text, $matches)) {
                $source_label = trim($matches[1]);
                $category     = self::normalize_category($source_label);
                $text         = trim($matches[2]);
            }

            if ('' === $text) {
                return null;
            }

            return array(
                'id'          => self::id('e'),
                'category'    => $category,
                'sourceLabel' => $source_label,
                'text'        => self::format_text($text),
                'link'        => array('url' => '', 'label' => ''),
            );
        }

        /**
         * Entry text as it will be stored.
         *
         * Backticks become `<code>` (our own changelog uses them for things
         * like `::after` and `element.matches()`), and the result is run
         * through wp_kses_post so the inline HTML already present in the source
         * survives while anything scriptable does not.
         *
         * @param string $text
         * @return string
         */
        public static function format_text($text)
        {
            $text = preg_replace_callback(
                '/`([^`]+)`/u',
                function ($matches) {
                    return '<code>' . esc_html($matches[1]) . '</code>';
                },
                (string) $text
            );

            return trim(wp_kses_post($text));
        }

        /**
         * Best-effort ISO date. Never throws, never fails the version: an
         * unreadable date simply has no machine-readable twin.
         *
         * @param string $date
         * @return string `Y-m-d`, or '' when the date cannot be read.
         */
        public static function parse_date($date)
        {
            $date = trim((string) $date);

            if ('' === $date) {
                return '';
            }

            // strtotime() does not understand ordinals ("1st May 2026").
            $normalised = preg_replace('/(\d+)(st|nd|rd|th)/i', '$1', $date);
            $timestamp  = strtotime($normalised);

            return false === $timestamp ? '' : gmdate('Y-m-d', $timestamp);
        }

        /**
         * Build the category list for a parsed document: canonical entries in
         * their declared order first, then anything new the text introduced.
         *
         * @param array $slugs
         * @param array $labels slug => label as originally written
         * @return array
         */
        public static function categories_for($slugs, $labels = array())
        {
            $table      = self::table();
            $categories = array();
            $seen       = array();

            foreach ($table['canonical'] as $category) {
                if (in_array($category['slug'], $slugs, true)) {
                    $categories[]              = $category;
                    $seen[$category['slug']]   = true;
                }
            }

            foreach ($slugs as $slug) {
                if (isset($seen[$slug])) {
                    continue;
                }

                if ($slug === $table['fallback']['slug']) {
                    $categories[] = $table['fallback'];
                } else {
                    $label        = !empty($labels[$slug]) ? $labels[$slug] : ucwords(str_replace('-', ' ', $slug));
                    $categories[] = array(
                        'slug'  => $slug,
                        'label' => $label,
                        'color' => self::generated_color($slug),
                    );
                }

                $seen[$slug] = true;
            }

            // A block with no categories at all would have nothing to colour a
            // badge with, so fall back to the canonical set.
            return $categories ? $categories : self::default_categories();
        }

        /**
         * The anchor a version is reachable at: `v-2-1-1`.
         *
         * @param string $version
         * @return string
         */
        public static function version_slug($version)
        {
            $slug = sanitize_title('v-' . (string) $version);

            return '' !== $slug ? $slug : 'v-' . substr(md5((string) $version), 0, 6);
        }

        /**
         * Stable-enough identifier for a version or entry. Only has to be
         * unique inside one block's attributes.
         *
         * @param string $prefix
         * @return string
         */
        public static function id($prefix)
        {
            return $prefix . '-' . substr(md5(uniqid((string) wp_rand(), true)), 0, 10);
        }
    }
}
