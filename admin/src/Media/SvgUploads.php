<?php
namespace AuroraAdmin\Media;

use AuroraAdmin\Options\Settings;

defined("ABSPATH") || exit();

/**
 * Implements Settings > Media's "Allow SVG uploads" toggle — previously
 * existed in the UI with no backend behind it, so every SVG upload was
 * rejected by WordPress's default checks regardless of the setting.
 *
 * Two separate WP checks both have to pass, not just one:
 *  1. upload_mimes — the allowlist of extension => mime type pairs.
 *  2. wp_check_filetype_and_ext()'s real-mime sniff (finfo) — SVG is XML
 *     text, so finfo reports something like text/plain, which doesn't
 *     match image/svg+xml even after (1) allowlists it, and WordPress
 *     nulls out ext/type as a security measure. Fixed here by restoring
 *     them for .svg files specifically, which is the standard, documented
 *     workaround for this exact WP behavior.
 *
 * Also does basic sanitization on upload (strips <script> tags, inline
 * event-handler attributes, javascript: URIs) since SVG can carry
 * executable content and is a known stored-XSS vector once uploads are
 * opened up. This is a regex-based pass, not a full XML-parser-based
 * sanitizer — no Composer dependency in this project to lean on one.
 * Good enough to block the common attack patterns; not a substitute for
 * a hardened library (e.g. enshrined/svg-sanitize) if that matters more
 * than keeping this dependency-free.
 */
class SvgUploads
{
  public function __construct()
  {
    if (!Settings::get("enable_svg", false)) {
      return;
    }

    add_filter("upload_mimes", [self::class, "allow_mime"]);
    add_filter("wp_check_filetype_and_ext", [self::class, "fix_filetype_check"], 10, 4);
    add_filter("wp_handle_upload_prefilter", [self::class, "sanitize"]);
    add_filter("wp_generate_attachment_metadata", [self::class, "add_dimensions"], 10, 2);
  }

  public static function allow_mime($mimes)
  {
    $mimes["svg"] = "image/svg+xml";
    return $mimes;
  }

  public static function fix_filetype_check($data, $file, $filename, $mimes)
  {
    if (!empty($data["ext"]) && !empty($data["type"])) {
      return $data;
    }

    $wp_filetype = wp_check_filetype($filename, $mimes);
    if ($wp_filetype["ext"] === "svg" && $wp_filetype["type"] === "image/svg+xml") {
      $data["ext"] = "svg";
      $data["type"] = "image/svg+xml";
    }

    return $data;
  }

  public static function sanitize($file)
  {
    if (!isset($file["tmp_name"], $file["name"])) {
      return $file;
    }
    if (strtolower(pathinfo($file["name"], PATHINFO_EXTENSION)) !== "svg") {
      return $file;
    }

    $content = file_get_contents($file["tmp_name"]);
    if ($content === false || stripos($content, "<svg") === false) {
      $file["error"] = __("File does not appear to be a valid SVG.", "aurora-admin");
      return $file;
    }

    $clean = preg_replace(
      [
        "/<script\b[^>]*>.*?<\/script>/is",
        "/<(iframe|object|embed|foreignObject)\b[^>]*>.*?<\/\\1>/is",
        "/<(iframe|object|embed)\b[^>]*\/?>/is",
        '/\son\w+\s*=\s*"[^"]*"/i',
        "/\son\w+\s*=\s*'[^']*'/i",
        '/\son\w+\s*=\s*[^\s>]+/i',
        // Any attribute (href, xlink:href, formaction, …) whose value opens
        // with the javascript: scheme, in any of the three attribute-value
        // quoting styles — not just a double-quoted href/xlink:href.
        '/[\w:\-]+\s*=\s*"\s*javascript:[^"]*"/i',
        "/[\w:\-]+\s*=\s*'\s*javascript:[^']*'/i",
        '/[\w:\-]+\s*=\s*javascript:[^\s>]*/i',
      ],
      "",
      $content
    );

    file_put_contents($file["tmp_name"], $clean);

    return $file;
  }

  /**
   * SVGs have no raster dimensions getimagesize()/Imagick/GD can read, so
   * WordPress's own metadata generation leaves 'width'/'height' out of the
   * attachment metadata entirely for them. That's not just cosmetic —
   * wp_get_missing_image_subsizes() (wp-admin/includes/image.php, called
   * right after generation) unconditionally reads $image_meta['width']/
   * ['height'] in its no-imagesize-available branch, throwing "Undefined
   * array key" warnings. On any environment with warnings displayed
   * inline (common on staging/debug setups), that warning text gets
   * prepended to the REST/AJAX response body, which the media uploader's
   * JS can't parse as JSON — surfacing to the user as "upload failed"
   * with no useful message. Parsing real dimensions out of the SVG here
   * fixes both the correctness gap and that failure mode.
   */
  public static function add_dimensions($metadata, $attachment_id)
  {
    $file = get_attached_file($attachment_id);
    if (!$file || strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== "svg") {
      return $metadata;
    }

    $size = self::svg_dimensions($file);
    $metadata["width"] = $size["width"];
    $metadata["height"] = $size["height"];

    return $metadata;
  }

  private static function svg_dimensions($file)
  {
    $width = 100;
    $height = 100;

    $content = file_get_contents($file);
    if ($content !== false) {
      if (preg_match('/<svg\b[^>]*\bviewBox=["\']\s*[\d.\-]+[\s,]+[\d.\-]+[\s,]+([\d.\-]+)[\s,]+([\d.\-]+)/i', $content, $m)) {
        $width = (float) $m[1];
        $height = (float) $m[2];
      } elseif (
        preg_match('/<svg\b[^>]*\bwidth=["\']([\d.]+)/i', $content, $mw) &&
        preg_match('/<svg\b[^>]*\bheight=["\']([\d.]+)/i', $content, $mh)
      ) {
        $width = (float) $mw[1];
        $height = (float) $mh[1];
      }
    }

    return ["width" => max(1, (int) round($width)), "height" => max(1, (int) round($height))];
  }
}
