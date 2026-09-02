<?php

/**
 * SVG uploads.
 *
 * WordPress refuses SVG out of the box, and the refusal is not arbitrary: an
 * SVG is an XML document that can carry scripts, so allowing the format without
 * cleaning the files turns "can upload media" into "can run JavaScript on this
 * domain". The whole point of this class is that the format is enabled and the
 * files are scrubbed in the same breath — the mime type is only ever added
 * alongside the upload filter that sanitises it (see
 * Darkify_Util_SVG_Sanitizer, which does the actual cleaning).
 *
 * Three problems have to be solved for an SVG to behave like an image here:
 *
 * 1. Getting it past the uploader. Both ends refuse it — the browser, because
 *    plupload is handed the allowed extensions and shows "This file cannot be
 *    processed by the web server", and PHP, because WordPress checks the real
 *    mime of the file and gets something that does not match .svg.
 * 2. Cleaning it, before it is ever written to a public URL.
 * 3. Giving it a size. An SVG has no pixel dimensions unless you read them out
 *    of the markup, and without them the media library shows a blank tile and
 *    every template renders an <img> with nothing to lay out around — including
 *    the collection block's cards.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Darkify_Util_SVG')) {

    class Darkify_Util_SVG
    {
        const MIME = 'image/svg+xml';

        /** @var Darkify_Util_SVG|null */
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
            add_filter('upload_mimes', array($this, 'allow_mime'));
            add_filter('wp_check_filetype_and_ext', array($this, 'check_filetype'), 10, 4);
            add_filter('wp_handle_upload_prefilter', array($this, 'sanitize_upload'));

            add_filter('wp_generate_attachment_metadata', array($this, 'add_metadata'), 10, 2);
            add_filter('wp_get_attachment_metadata', array($this, 'fill_metadata'), 10, 2);
            add_filter('wp_get_attachment_image_src', array($this, 'image_src'), 10, 4);
            add_filter('wp_calculate_image_srcset_meta', array($this, 'no_srcset'), 10, 4);
            add_filter('wp_prepare_attachment_for_js', array($this, 'prepare_for_js'), 10, 2);

            add_action('admin_enqueue_scripts', array($this, 'admin_styles'));
        }

        /* --------------------------------------------------------------- */
        /* Getting the file in                                             */
        /* --------------------------------------------------------------- */

        /**
         * Whether the current user may upload an SVG.
         *
         * `upload_files` — the same bar as any other media — because every file
         * is sanitised on the way in, so an author uploading an icon is not
         * being trusted with markup. A site that would rather keep the format to
         * administrators can tighten it:
         *
         *     add_filter('darkify_util_svg_capability', fn() => 'manage_options');
         *
         * @return bool
         */
        public function user_can_upload()
        {
            $capability = apply_filters('darkify_util_svg_capability', 'upload_files');

            return current_user_can($capability);
        }

        /**
         * Add the mime type.
         *
         * This is what the browser reads too: WordPress builds plupload's filter
         * from the same list, which is why an unmodified site rejects the file
         * before it is ever sent.
         *
         * @param array $mimes
         * @return array
         */
        public function allow_mime($mimes)
        {
            if (!$this->user_can_upload()) {
                return $mimes;
            }

            $mimes['svg']  = self::MIME;
            $mimes['svgz'] = self::MIME;

            return $mimes;
        }

        /**
         * Agree that a .svg is an SVG.
         *
         * WordPress sniffs the real mime with finfo and compares it to the
         * extension. For SVG the two rarely match — finfo reports image/svg,
         * text/xml, text/plain or text/html depending on the file and the
         * platform's magic database — so core blanks the type and the upload is
         * refused. The extension and the sniff are reconciled here, and the
         * contents are still not trusted: the prefilter below is what decides
         * whether the file may stay.
         *
         * @param array       $checked
         * @param string      $file     Full path to the file.
         * @param string      $filename Name of the file.
         * @param string[]    $mimes
         * @return array
         */
        public function check_filetype($checked, $file, $filename, $mimes)
        {
            if (!empty($checked['type'])) {
                return $checked;
            }

            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (!in_array($extension, array('svg', 'svgz'), true)) {
                return $checked;
            }

            if (!$this->user_can_upload()) {
                return $checked;
            }

            return array(
                'ext'             => $extension,
                'type'            => self::MIME,
                'proper_filename' => $checked['proper_filename'],
            );
        }

        /**
         * Clean the file before WordPress moves it into uploads.
         *
         * The prefilter runs while the upload is still a temporary file, which
         * is the only point where rejecting it means nothing was ever written to
         * a public URL. Returning the array with an `error` set is how an upload
         * is refused and the reason shown in the media library.
         *
         * @param array $file $_FILES entry.
         * @return array
         */
        public function sanitize_upload($file)
        {
            if (empty($file['tmp_name']) || empty($file['name'])) {
                return $file;
            }

            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($extension, array('svg', 'svgz'), true)) {
                return $file;
            }

            if (!$this->user_can_upload()) {
                $file['error'] = __('You are not allowed to upload SVG files.', 'darkify-util');
                return $file;
            }

            $result = Darkify_Util_SVG_Sanitizer::sanitize_file($file['tmp_name']);

            if (is_wp_error($result)) {
                $file['error'] = $result->get_error_message();
                return $file;
            }

            // Written back uncompressed, so the stored file matches what was
            // checked. Renaming keeps the extension honest about the contents.
            if ('svgz' === $extension) {
                $file['name'] = substr($file['name'], 0, -1);
            }

            return $file;
        }

        /* --------------------------------------------------------------- */
        /* Giving it a size                                                */
        /* --------------------------------------------------------------- */

        /**
         * Record the SVG's own dimensions when it is added to the library.
         *
         * There are no intermediate sizes to generate — the file scales — so
         * `sizes` stays empty and every request for a size falls back to the
         * full file.
         *
         * @param array $metadata
         * @param int   $attachment_id
         * @return array
         */
        public function add_metadata($metadata, $attachment_id)
        {
            if (!$this->is_svg($attachment_id)) {
                return $metadata;
            }

            $file = get_attached_file($attachment_id);
            $size = Darkify_Util_SVG_Sanitizer::dimensions($file);

            if (!$size) {
                return $metadata;
            }

            $metadata = is_array($metadata) ? $metadata : array();

            $metadata['width']  = $size['width'];
            $metadata['height'] = $size['height'];
            $metadata['file']   = _wp_relative_upload_path($file);

            if (!isset($metadata['sizes'])) {
                $metadata['sizes'] = array();
            }

            return $metadata;
        }

        /**
         * Fill in dimensions for SVGs that were already in the library.
         *
         * Anything uploaded before this class existed — or by a plugin that
         * bypassed the metadata filter — has no width or height stored. Reading
         * it on the way out means those files start behaving without anyone
         * having to re-upload them.
         *
         * @param array $metadata
         * @param int   $attachment_id
         * @return array
         */
        public function fill_metadata($metadata, $attachment_id)
        {
            if (!$this->is_svg($attachment_id)) {
                return $metadata;
            }

            $metadata = is_array($metadata) ? $metadata : array();

            if (!empty($metadata['width']) && !empty($metadata['height'])) {
                return $metadata;
            }

            $size = Darkify_Util_SVG_Sanitizer::dimensions(get_attached_file($attachment_id));

            if (!$size) {
                return $metadata;
            }

            $metadata['width']  = $size['width'];
            $metadata['height'] = $size['height'];

            if (!isset($metadata['sizes'])) {
                $metadata['sizes'] = array();
            }

            return $metadata;
        }

        /**
         * Answer size requests with the file itself.
         *
         * image_downsize() looks for a generated size, finds none and returns
         * the full file with no dimensions, which is what leaves templates with
         * a dimensionless <img>. The stored size is put back here, so
         * wp_get_attachment_image() emits width and height and the browser can
         * reserve the space before the file loads.
         *
         * @param array|false  $image
         * @param int          $attachment_id
         * @param string|int[] $size
         * @param bool         $icon
         * @return array|false
         */
        public function image_src($image, $attachment_id, $size, $icon)
        {
            if (!$image || !$this->is_svg($attachment_id)) {
                return $image;
            }

            $metadata = wp_get_attachment_metadata($attachment_id);

            if (empty($metadata['width']) || empty($metadata['height'])) {
                return $image;
            }

            $width  = (int) $metadata['width'];
            $height = (int) $metadata['height'];

            // A named size is a box to fit inside, not a file to fetch: the one
            // file scales to whatever is asked for, and reporting the box keeps
            // the aspect ratio right in the markup.
            $box = $this->requested_box($size);

            if ($box) {
                list($width, $height) = wp_constrain_dimensions($width, $height, $box[0], $box[1]);
            }

            $image[1] = $width;
            $image[2] = $height;

            return $image;
        }

        /**
         * The pixel box a size request means.
         *
         * @param string|int[] $size
         * @return array{0:int,1:int}|null
         */
        protected function requested_box($size)
        {
            if (is_array($size) && 2 === count($size)) {
                return array((int) $size[0], (int) $size[1]);
            }

            if (!is_string($size) || 'full' === $size) {
                return null;
            }

            $sizes = wp_get_registered_image_subsizes();

            if (isset($sizes[$size])) {
                return array((int) $sizes[$size]['width'], (int) $sizes[$size]['height']);
            }

            return null;
        }

        /**
         * No srcset for a vector.
         *
         * There is one file at every size, so a srcset would list the same URL
         * repeatedly and a sizes attribute would tell the browser to pick
         * between identical candidates.
         *
         * @param array  $image_meta
         * @param int[]  $size_array
         * @param string $image_src
         * @param int    $attachment_id
         * @return array
         */
        public function no_srcset($image_meta, $size_array, $image_src, $attachment_id)
        {
            if ($this->is_svg($attachment_id)) {
                $image_meta['sizes'] = array();
            }

            return $image_meta;
        }

        /**
         * Give the media modal something to draw.
         *
         * Without dimensions the grid renders a zero-height tile — the file is
         * there, the thumbnail is invisible, and it looks broken.
         *
         * @param array   $response
         * @param WP_Post $attachment
         * @return array
         */
        public function prepare_for_js($response, $attachment)
        {
            if (self::MIME !== $attachment->post_mime_type) {
                return $response;
            }

            $url  = wp_get_attachment_url($attachment->ID);
            $size = Darkify_Util_SVG_Sanitizer::dimensions(get_attached_file($attachment->ID));

            $width  = $size ? $size['width'] : 150;
            $height = $size ? $size['height'] : 150;

            $response['icon']   = $url;
            $response['url']    = $url;
            $response['width']  = $width;
            $response['height'] = $height;

            $response['sizes']['full'] = array(
                'url'         => $url,
                'width'       => $width,
                'height'      => $height,
                'orientation' => $height > $width ? 'portrait' : 'landscape',
            );

            // The modal asks for `thumbnail` when it draws a tile, and falls
            // back to a generic document icon when there is not one.
            $response['sizes']['thumbnail'] = $response['sizes']['full'];

            return $response;
        }

        /**
         * A little admin CSS so SVG tiles are visible.
         *
         * The media grid sizes its thumbnails from the image; an SVG with a
         * percentage width collapses to nothing inside them whatever metadata
         * says, and that has to be fixed in CSS rather than in PHP.
         *
         * @param string $hook
         */
        public function admin_styles($hook)
        {
            if (!in_array($hook, array('upload.php', 'post.php', 'post-new.php', 'site-editor.php', 'widgets.php'), true)) {
                return;
            }

            wp_register_style('darkify-util-svg-admin', false, array(), DARKIFY_UTIL_VERSION);
            wp_enqueue_style('darkify-util-svg-admin');
            wp_add_inline_style(
                'darkify-util-svg-admin',
                '.media-icon img[src$=".svg"],'
                . '.attachment .thumbnail img[src$=".svg"],'
                . '.attachment-preview .thumbnail img[src$=".svg"],'
                . '.media-frame .attachment img[src$=".svg"],'
                . '.wp-block-image img[src$=".svg"]{width:100%;height:auto;min-width:48px;}'
            );
        }

        /* --------------------------------------------------------------- */

        /**
         * @param int $attachment_id
         * @return bool
         */
        protected function is_svg($attachment_id)
        {
            return self::MIME === get_post_mime_type($attachment_id);
        }
    }
}
