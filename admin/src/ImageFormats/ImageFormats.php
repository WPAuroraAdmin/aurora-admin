<?php
namespace AuroraAdmin\ImageFormats;

use AuroraAdmin\Options\Settings;

defined("ABSPATH") || exit();

/**
 * Settings > Modern Image Formats: generates a WebP or AVIF sibling file
 * for every size WordPress already produces for a new JPEG/PNG upload
 * (full size plus every registered subsize), then optionally rewrites
 * <img> markup into a <picture> element offering the modern format with
 * the original as a fallback source.
 *
 * Deliberately additive, never destructive: the original JPEG/PNG files
 * WordPress generates are always left in place untouched. This plugin
 * only ever adds sibling files alongside them and points browsers that
 * understand the modern format at those siblings via <picture> — nothing
 * about the attachment's own post_mime_type, the original files, or any
 * other plugin/theme reading wp_get_attachment_image_src() the normal way
 * is changed. Existing library images are unaffected until re-uploaded or
 * regenerated (e.g. via the Regenerate Thumbnails plugin or `wp media
 * regenerate`), same as any other subsize-generation change.
 *
 * Generated sibling files are recorded in the attachment's own metadata
 * under the 'aurora_image_formats' key (a sibling structure to core's own
 * 'sizes' key, one entry per size name plus 'full') so cleanup on delete
 * and markup rewriting both have a single source of truth for what was
 * actually generated.
 */
class ImageFormats
{
  const ELIGIBLE_MIME_TYPES = ["image/jpeg", "image/png"];

  public function __construct()
  {
    add_filter("wp_generate_attachment_metadata", [self::class, "generate_sibling_formats"], 20, 2);
    add_action("delete_attachment", [self::class, "cleanup_sibling_formats"]);
    add_filter("wp_editor_set_quality", [self::class, "set_quality"], 10, 2);
    add_filter("wp_content_img_tag", [self::class, "filter_content_img_tag"], 10, 3);
    add_filter("wp_get_attachment_image_html", [self::class, "filter_attachment_image_html"], 10, 5);
  }

  private static function enabled()
  {
    return (bool) Settings::get("image_formats_enabled", true);
  }

  /**
   * A single target mime type, not a list — matches the Settings UI's
   * single "Output format" choice. 'auto' prefers AVIF (smaller files at
   * a given quality) and falls back to WebP; an explicit choice that the
   * server can't actually produce (checked via wp_image_editor_supports(),
   * the same core function any image-editor-capability check should use)
   * returns null, which callers treat as "generate nothing" rather than
   * erroring — a host without modern format support just doesn't get the
   * feature, silently, same as the rest of this plugin's graceful-
   * degradation posture elsewhere (e.g. AVIF write support genuinely
   * varies a lot by ImageMagick build).
   */
  private static function target_mime_type()
  {
    $format = (string) Settings::get("image_formats_format", "auto");

    if ($format === "webp") {
      return wp_image_editor_supports(["mime_type" => "image/webp"]) ? "image/webp" : null;
    }
    if ($format === "avif") {
      return wp_image_editor_supports(["mime_type" => "image/avif"]) ? "image/avif" : null;
    }

    if (wp_image_editor_supports(["mime_type" => "image/avif"])) {
      return "image/avif";
    }
    if (wp_image_editor_supports(["mime_type" => "image/webp"])) {
      return "image/webp";
    }
    return null;
  }

  /**
   * Runs after WordPress's own subsize generation (priority 20, and in
   * any case wp_generate_attachment_metadata() only fires this filter
   * once 'sizes' is already fully populated) — so every size this method
   * sees already has a real file on disk to re-encode.
   */
  public static function generate_sibling_formats($metadata, $attachment_id)
  {
    if (!self::enabled() || !is_array($metadata)) {
      return $metadata;
    }

    $mime = get_post_mime_type($attachment_id);
    if (!in_array($mime, self::ELIGIBLE_MIME_TYPES, true)) {
      return $metadata;
    }

    $target_mime = self::target_mime_type();
    if ($target_mime === null) {
      return $metadata;
    }

    $file = get_attached_file($attachment_id);
    if (!$file || !file_exists($file)) {
      return $metadata;
    }

    $formats = [];

    $full = self::generate_sibling($file, $target_mime);
    if ($full !== null) {
      $formats["full"] = $full;
    }

    if (!empty($metadata["sizes"]) && is_array($metadata["sizes"])) {
      $base_dir = dirname($file);
      foreach ($metadata["sizes"] as $size_name => $size_data) {
        if (empty($size_data["file"])) {
          continue;
        }
        $size_path = $base_dir . "/" . $size_data["file"];
        if (!file_exists($size_path)) {
          continue;
        }
        $sibling = self::generate_sibling($size_path, $target_mime);
        if ($sibling !== null) {
          $formats[$size_name] = $sibling;
        }
      }
    }

    if (!empty($formats)) {
      $metadata["aurora_image_formats"] = $formats;
    }

    return $metadata;
  }

  /**
   * Re-encodes a single already-generated size file into the target mime
   * type, in the same directory (WP_Image_Editor::save() with a null
   * filename derives a sibling filename from the source automatically).
   * Discards the result if it isn't actually smaller than the source —
   * there's no point keeping a "modern format" copy around that's bigger
   * than the file it's meant to replace on the wire.
   */
  private static function generate_sibling($source_path, $target_mime)
  {
    $editor = wp_get_image_editor($source_path);
    if (is_wp_error($editor)) {
      return null;
    }

    $saved = $editor->save(null, $target_mime);
    if (is_wp_error($saved) || empty($saved["file"])) {
      return null;
    }

    $original_size = @filesize($source_path);
    if ($original_size && !empty($saved["filesize"]) && $saved["filesize"] >= $original_size) {
      if (!empty($saved["path"]) && file_exists($saved["path"])) {
        wp_delete_file($saved["path"]);
      }
      return null;
    }

    return [
      "file" => $saved["file"],
      "filesize" => $saved["filesize"] ?? null,
      "mime-type" => $saved["mime-type"] ?? $target_mime,
    ];
  }

  /**
   * Quality for the modern formats specifically — core's own default
   * (82 for most editors) is a reasonable target for WebP/AVIF too, but
   * setting it explicitly here means a future change to core's JPEG
   * default doesn't silently change modern-format quality along with it.
   * Untouched for every other mime type.
   */
  public static function set_quality($quality, $mime_type)
  {
    if ($mime_type === "image/webp" || $mime_type === "image/avif") {
      return 82;
    }
    return $quality;
  }

  /**
   * Deletes every generated sibling file when the attachment itself is
   * deleted — WordPress core only knows to clean up the files it
   * generated itself (recorded in 'sizes'), not this plugin's additions,
   * so without this every deleted image would leave orphaned .webp/.avif
   * files behind indefinitely.
   */
  public static function cleanup_sibling_formats($attachment_id)
  {
    $metadata = wp_get_attachment_metadata($attachment_id);
    if (empty($metadata["aurora_image_formats"]) || !is_array($metadata["aurora_image_formats"])) {
      return;
    }

    $file = get_attached_file($attachment_id);
    if (!$file) {
      return;
    }
    $base_dir = dirname($file);

    foreach ($metadata["aurora_image_formats"] as $entry) {
      if (empty($entry["file"])) {
        continue;
      }
      $path = $base_dir . "/" . $entry["file"];
      if (file_exists($path)) {
        wp_delete_file($path);
      }
    }
  }

  // --- <picture> markup rewriting -----------------------------------------

  /**
   * Covers images WordPress finds inside post content, widgets, and
   * similar rendered text (wp_filter_content_tags() applies this core
   * filter to each <img> tag it detects).
   */
  public static function filter_content_img_tag($filtered_image, $context, $attachment_id)
  {
    $picture = self::build_picture($filtered_image, (int) $attachment_id);
    return $picture ?? $filtered_image;
  }

  /**
   * Covers images rendered directly via wp_get_attachment_image() (post
   * thumbnails, block-editor image blocks that call it, etc.) — a
   * separate core filter from wp_content_img_tag above since core itself
   * doesn't route wp_get_attachment_image() output back through that one.
   */
  public static function filter_attachment_image_html($html, $attachment_id, $size, $icon, $attr)
  {
    $picture = self::build_picture($html, (int) $attachment_id);
    return $picture ?? $html;
  }

  /**
   * Returns a <picture> wrapping the original $html as the fallback
   * child, with one <source> offering the generated modern format, or
   * null if there's nothing to rewrite (feature/picture-element disabled,
   * no <img> tag present, or no sibling formats were ever generated for
   * this attachment).
   */
  private static function build_picture($html, $attachment_id)
  {
    if (!self::enabled() || !Settings::get("image_formats_picture_element", true)) {
      return null;
    }
    if ($attachment_id <= 0 || strpos($html, "<img") === false) {
      return null;
    }

    $metadata = wp_get_attachment_metadata($attachment_id);
    if (empty($metadata["aurora_image_formats"]) || !is_array($metadata["aurora_image_formats"])) {
      return null;
    }

    $srcset = self::build_srcset($metadata);
    if ($srcset === "") {
      return null;
    }

    $formats = $metadata["aurora_image_formats"];
    $representative = $formats["full"] ?? reset($formats);
    $mime = $representative["mime-type"] ?? "";
    if ($mime === "") {
      return null;
    }

    // The original <img>'s own `sizes` attribute already reflects the
    // theme/layout's responsive-width logic (core computed it once when
    // building the <img> tag) — reusing it on the <source> is correct and
    // avoids re-deriving the same value a second way.
    $sizes_attr = "";
    if (preg_match('/\ssizes=["\']([^"\']+)["\']/', $html, $m)) {
      $sizes_attr = ' sizes="' . esc_attr($m[1]) . '"';
    }

    $source = sprintf(
      '<source type="%1$s" srcset="%2$s"%3$s>',
      esc_attr($mime),
      esc_attr($srcset),
      $sizes_attr
    );

    return "<picture>" . $source . $html . "</picture>";
  }

  private static function build_srcset($metadata)
  {
    $formats = $metadata["aurora_image_formats"];
    $entries = [];

    if (!empty($formats["full"]) && !empty($metadata["width"])) {
      $entry = self::srcset_entry($metadata, $formats["full"], (int) $metadata["width"]);
      if ($entry !== "") {
        $entries[] = $entry;
      }
    }

    if (!empty($metadata["sizes"]) && is_array($metadata["sizes"])) {
      foreach ($metadata["sizes"] as $size_name => $size_data) {
        if (empty($formats[$size_name]) || empty($size_data["width"])) {
          continue;
        }
        $entry = self::srcset_entry($metadata, $formats[$size_name], (int) $size_data["width"]);
        if ($entry !== "") {
          $entries[] = $entry;
        }
      }
    }

    return implode(", ", array_unique($entries));
  }

  /**
   * Sibling files live in the same directory as the size file they were
   * generated from, which for a standard (non-year/month-disabled)
   * install is dirname($metadata['file']) relative to the uploads
   * baseurl — the same relative-path convention core's own subsize URLs
   * use, not something specific to this plugin's file layout.
   */
  private static function srcset_entry($metadata, $format_entry, $width)
  {
    if (empty($format_entry["file"]) || $width <= 0 || empty($metadata["file"])) {
      return "";
    }

    $upload_dir = wp_get_upload_dir();
    $relative_dir = trailingslashit(dirname($metadata["file"]));
    if ($relative_dir === "./") {
      $relative_dir = "";
    }
    $url = $upload_dir["baseurl"] . "/" . $relative_dir . $format_entry["file"];

    return esc_url_raw($url) . " " . $width . "w";
  }
}
