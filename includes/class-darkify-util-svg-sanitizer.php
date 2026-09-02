<?php

/**
 * The SVG scrubber.
 *
 * An SVG is a document, not a picture: it can carry <script>, event handlers,
 * <foreignObject> full of HTML, external entity references and links to
 * javascript: URLs. WordPress refuses the format by default for exactly that
 * reason, and turning the format on without cleaning the file first hands
 * anyone who can upload a media item a stored-XSS primitive that fires whenever
 * the SVG is opened directly.
 *
 * So nothing here trusts the uploaded bytes. Every file is parsed, rebuilt from
 * an allowlist of elements, and written back — and a file that will not parse is
 * rejected outright rather than passed through.
 *
 * The strategy is an allowlist for elements and rules for attributes, in that
 * order of importance:
 *
 * - The element allowlist is what removes the entire dangerous surface at a
 *   stroke: script, foreignObject, iframe, embed, handler and anything else
 *   nobody thought of are gone because they are not on the list, not because
 *   they were spotted.
 * - Attributes are then filtered by rule, because an allowlist of every legal
 *   SVG attribute is long enough that maintaining it would quietly break real
 *   artwork. The rules cover the ways an attribute can execute: on* handlers,
 *   javascript: and data: URLs, and animation that rewrites a link's target.
 *
 * Kept separate from the integration class the way the changelog's parser is:
 * one file decides what is safe, the other decides when to ask.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Darkify_Util_SVG_Sanitizer')) {

    class Darkify_Util_SVG_Sanitizer
    {
        /**
         * Elements that may stay.
         *
         * Drawing, grouping, gradients, filters, text and the animation family.
         * Deliberately absent: script, foreignObject (arbitrary HTML, and the
         * usual way an SVG smuggles a payload), and the font/glyph elements,
         * which no logo needs and which widen the parser surface for nothing.
         *
         * @var string[]
         */
        protected static $elements = array(
            // Structure.
            'svg', 'g', 'defs', 'symbol', 'use', 'switch', 'view', 'a',
            'title', 'desc', 'metadata', 'style',
            // Shapes.
            'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
            // Text.
            'text', 'tspan', 'textpath',
            // Paint servers and masking.
            'lineargradient', 'radialgradient', 'stop', 'pattern',
            'clippath', 'mask', 'marker', 'image',
            // Filters.
            'filter', 'feblend', 'fecolormatrix', 'fecomponenttransfer',
            'fecomposite', 'feconvolvematrix', 'fediffuselighting',
            'fedisplacementmap', 'fedistantlight', 'fedropshadow', 'feflood',
            'fefunca', 'fefuncb', 'fefuncg', 'fefuncr', 'fegaussianblur',
            'feimage', 'femerge', 'femergenode', 'femorphology', 'feoffset',
            'fepointlight', 'fespecularlighting', 'fespotlight', 'fetile',
            'feturbulence',
            // Animation.
            'animate', 'animatemotion', 'animatetransform', 'set', 'mpath',
        );

        /**
         * Attributes an animation may not drive.
         *
         * `<set attributeName="href" to="javascript:…">` is a working XSS in an
         * SVG that contains no script tag and no javascript: URL anywhere a
         * scanner would look — the payload only exists once the animation runs.
         * Animation stays; pointing it at a link target does not.
         *
         * @var string[]
         */
        protected static $unanimatable = array('href', 'xlink:href', 'from', 'to', 'values');

        /**
         * Sanitise an SVG file in place.
         *
         * @param string $path Absolute path to the file.
         * @return true|WP_Error True when the file is now safe to keep.
         */
        public static function sanitize_file($path)
        {
            if (!is_readable($path)) {
                return new WP_Error('darkify_svg_unreadable', __('The uploaded file could not be read.', 'darkify-util'));
            }

            $contents = file_get_contents($path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local upload, not a remote fetch.

            if (false === $contents || '' === trim($contents)) {
                return new WP_Error('darkify_svg_empty', __('The uploaded file is empty.', 'darkify-util'));
            }

            $clean = self::sanitize($contents);

            if (is_wp_error($clean)) {
                return $clean;
            }

            if (false === file_put_contents($path, $clean)) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- writing back the file just uploaded.
                return new WP_Error('darkify_svg_unwritable', __('The sanitised file could not be saved.', 'darkify-util'));
            }

            return true;
        }

        /**
         * Sanitise SVG markup.
         *
         * @param string $svg Raw markup.
         * @return string|WP_Error Cleaned markup, or an error if it is not SVG.
         */
        public static function sanitize($svg)
        {
            // Gzipped SVG (.svgz) arrives compressed. Inflate it so the same
            // rules apply; it is written back uncompressed, which is a change of
            // bytes but not of meaning.
            if (0 === strncmp($svg, "\x1f\x8b", 2)) {
                $inflated = @gzdecode($svg);

                if (false === $inflated) {
                    return new WP_Error('darkify_svg_gzip', __('The compressed SVG could not be read.', 'darkify-util'));
                }

                $svg = $inflated;
            }

            // A DOCTYPE is the way in for entity expansion — both the billion
            // laughs denial of service and file disclosure through XXE. There is
            // no legitimate reason for an uploaded icon to declare one, so it
            // goes before the parser ever sees it.
            $svg = preg_replace('/<!DOCTYPE[^>]*(\[[^\]]*\])?>/is', '', $svg);
            $svg = preg_replace('/<!ENTITY[^>]*>/is', '', $svg);

            $document = new DOMDocument();
            $document->preserveWhiteSpace = false;
            $document->strictErrorChecking = false;
            $document->formatOutput = false;

            $previous = libxml_use_internal_errors(true);

            // LIBXML_NONET refuses network access during parsing; NOENT is
            // deliberately *not* passed, so entities are left unexpanded.
            $loaded = $document->loadXML($svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            if (!$loaded || !$document->documentElement) {
                return new WP_Error(
                    'darkify_svg_invalid',
                    __('That file is not valid SVG, so it was not uploaded.', 'darkify-util')
                );
            }

            if ('svg' !== strtolower($document->documentElement->nodeName)) {
                return new WP_Error(
                    'darkify_svg_not_svg',
                    __('That file does not contain an SVG image, so it was not uploaded.', 'darkify-util')
                );
            }

            // Any doctype that survived the regex is dropped here as well.
            if ($document->doctype) {
                $document->doctype->parentNode->removeChild($document->doctype);
            }

            self::clean_element($document->documentElement);

            $output = $document->saveXML($document->documentElement, LIBXML_NOEMPTYTAG);

            if (false === $output) {
                return new WP_Error('darkify_svg_write', __('The SVG could not be rewritten safely.', 'darkify-util'));
            }

            return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $output;
        }

        /**
         * Strip one element and everything under it.
         *
         * @param DOMElement $element Element to clean.
         */
        protected static function clean_element($element)
        {
            /*
             * <style> carries its CSS as a text node, not as attributes, so it
             * is checked here rather than alongside the attribute rules.
             *
             * It lived in clean_attributes() first, which was a real hole: that
             * method returns early for an element with no attributes, and a
             * <style> block almost never has any — so `<style>rect{background:
             * url(javascript:…)}</style>` and `@import url(//evil/x.css)` both
             * sailed through untouched.
             */
            if ('style' === strtolower($element->nodeName) && self::has_unsafe_css($element->textContent)) {
                $element->parentNode->removeChild($element);
                return;
            }

            // Children are walked backwards because removing a node while
            // iterating a live DOMNodeList forwards skips the node after it —
            // which would leave every other disallowed element in place.
            for ($i = $element->childNodes->length - 1; $i >= 0; $i--) {
                $child = $element->childNodes->item($i);

                if (XML_ELEMENT_NODE === $child->nodeType) {
                    if (!in_array(strtolower($child->nodeName), self::$elements, true)) {
                        $element->removeChild($child);
                        continue;
                    }

                    self::clean_element($child);
                    continue;
                }

                // Processing instructions can carry a stylesheet reference; they
                // have no business in an uploaded image.
                if (XML_PI_NODE === $child->nodeType) {
                    $element->removeChild($child);
                }
            }

            self::clean_attributes($element);
        }

        /**
         * @param DOMElement $element Element whose attributes to filter.
         */
        protected static function clean_attributes($element)
        {
            if (!$element->hasAttributes()) {
                return;
            }

            $tag = strtolower($element->nodeName);

            for ($i = $element->attributes->length - 1; $i >= 0; $i--) {
                $attribute = $element->attributes->item($i);
                $name      = strtolower($attribute->nodeName);
                $value     = $attribute->nodeValue;

                // Event handlers: onload, onclick, onmouseover and the rest.
                if (0 === strpos($name, 'on')) {
                    $element->removeAttribute($attribute->nodeName);
                    continue;
                }

                // An animation that rewrites a link target, which is how an SVG
                // with no script and no javascript: URL still runs one.
                if (in_array($tag, array('animate', 'animatetransform', 'animatemotion', 'set'), true)
                    && 'attributename' === $name
                    && in_array(strtolower(trim($value)), self::$unanimatable, true)
                ) {
                    // The whole element goes: an animation whose target was
                    // stripped would otherwise animate whatever it defaulted to.
                    $element->parentNode->removeChild($element);
                    return;
                }

                if (self::is_link_attribute($name)) {
                    if (!self::is_safe_url($value)) {
                        $element->removeAttribute($attribute->nodeName);
                    }
                    continue;
                }

                if ('style' === $name && self::has_unsafe_css($value)) {
                    $element->removeAttribute($attribute->nodeName);
                    continue;
                }

                // Anything left whose value still smuggles a scheme.
                if (self::has_unsafe_scheme($value)) {
                    $element->removeAttribute($attribute->nodeName);
                }
            }
        }

        /**
         * @param string $name Attribute name, lower-cased.
         * @return bool
         */
        protected static function is_link_attribute($name)
        {
            return in_array($name, array('href', 'xlink:href', 'src', 'from', 'to', 'by', 'values', 'path'), true);
        }

        /**
         * Whether a URL may stay.
         *
         * Fragments (`#gradient-1`) are how an SVG refers to its own defs and
         * are the common case. Relative and http(s) links are allowed on the
         * elements that can carry them. Everything else — javascript:, data:,
         * file:, and the whitespace-and-entity tricks used to disguise them — is
         * refused.
         *
         * @param string $value Attribute value.
         * @return bool
         */
        protected static function is_safe_url($value)
        {
            $url = strtolower(trim($value));

            // Strip the characters a browser ignores but a naive check does not:
            // NUL, tabs, newlines and HTML entities spliced into the scheme.
            $url = preg_replace('/[\x00-\x20]+/', '', $url);
            $url = preg_replace('/&#x?[0-9a-f]+;?/i', '', $url);

            if ('' === $url) {
                return false;
            }

            if ('#' === $url[0]) {
                return true;
            }

            if (preg_match('/^(https?:|mailto:)/', $url)) {
                return true;
            }

            // A relative path with no scheme at all.
            return !preg_match('/^[a-z0-9.+-]*:/', $url);
        }

        /**
         * @param string $value Attribute value.
         * @return bool
         */
        protected static function has_unsafe_scheme($value)
        {
            $flat = strtolower(preg_replace('/[\x00-\x20]+/', '', (string) $value));

            return false !== strpos($flat, 'javascript:')
                || false !== strpos($flat, 'vbscript:')
                || false !== strpos($flat, 'data:text/html');
        }

        /**
         * @param string $css CSS text.
         * @return bool
         */
        protected static function has_unsafe_css($css)
        {
            $flat = strtolower(preg_replace('/[\x00-\x20]+/', '', (string) $css));

            return false !== strpos($flat, 'javascript:')
                || false !== strpos($flat, 'expression(')
                || false !== strpos($flat, '@import')
                || false !== strpos($flat, 'behavior:')
                || false !== strpos($flat, '-moz-binding');
        }

        /**
         * The intrinsic size of an SVG, for attachment metadata.
         *
         * Read from width/height when they are absolute, and from the viewBox
         * otherwise — which is the common case, because an SVG sized in
         * percentages has no pixel size of its own. Without this WordPress
         * stores no dimensions at all and every template that renders the image
         * gets an <img> with no width or height to lay out around.
         *
         * @param string $path Absolute path to the file.
         * @return array{width:int,height:int}|null
         */
        public static function dimensions($path)
        {
            if (!is_readable($path)) {
                return null;
            }

            $contents = file_get_contents($path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.

            if (false === $contents) {
                return null;
            }

            if (0 === strncmp($contents, "\x1f\x8b", 2)) {
                $contents = @gzdecode($contents);
            }

            if (!$contents) {
                return null;
            }

            $previous = libxml_use_internal_errors(true);
            $xml      = simplexml_load_string($contents, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            if (false === $xml) {
                return null;
            }

            $attributes = $xml->attributes();

            $width  = isset($attributes->width) ? self::to_pixels((string) $attributes->width) : 0;
            $height = isset($attributes->height) ? self::to_pixels((string) $attributes->height) : 0;

            if (!$width || !$height) {
                $box = isset($attributes->viewBox) ? preg_split('/[\s,]+/', trim((string) $attributes->viewBox)) : array();

                if (4 === count($box)) {
                    $width  = (int) round((float) $box[2]);
                    $height = (int) round((float) $box[3]);
                }
            }

            if ($width < 1 || $height < 1) {
                return null;
            }

            return array('width' => $width, 'height' => $height);
        }

        /**
         * A CSS length as pixels. Percentages are not a size, and return 0 so
         * the viewBox is used instead.
         *
         * @param string $value
         * @return int
         */
        protected static function to_pixels($value)
        {
            $value = trim($value);

            if ('' === $value || false !== strpos($value, '%')) {
                return 0;
            }

            if (!preg_match('/^([0-9.]+)\s*([a-z]*)$/i', $value, $matches)) {
                return 0;
            }

            $number = (float) $matches[1];

            // The absolute units, at CSS's own 96dpi. Anything else (em, ex, and
            // friends) depends on context this file does not have.
            $units = array(
                ''   => 1,
                'px' => 1,
                'pt' => 96 / 72,
                'pc' => 16,
                'in' => 96,
                'cm' => 96 / 2.54,
                'mm' => 96 / 25.4,
                'q'  => 96 / 101.6,
            );

            $unit = strtolower($matches[2]);

            if (!isset($units[$unit])) {
                return 0;
            }

            return (int) round($number * $units[$unit]);
        }
    }
}
