<?php
namespace AuroraAdmin\Utility;

defined("ABSPATH") || exit();

/**
 * Reads the Vite build manifest and enqueues a single entry's JS (as an
 * ES module) and CSS. Kept deliberately simple — one entry in, manifest
 * lookup, two enqueue calls out. No scoping/theme tricks live here.
 */
class Assets
{
  private static $manifest = null;

  private static function get_manifest()
  {
    if (self::$manifest !== null) {
      return self::$manifest;
    }

    $path = AURORA_ADMIN_PATH . "app/dist/.vite/manifest.json";
    if (!file_exists($path)) {
      self::$manifest = [];
      return self::$manifest;
    }

    $contents = file_get_contents($path);
    $decoded = json_decode($contents, true);
    self::$manifest = is_array($decoded) ? $decoded : [];

    return self::$manifest;
  }

  /**
   * Enqueues a built entry's JS as a module script plus its CSS, if any.
   *
   * @param string $handle WP script/style handle prefix, e.g. "aurora-admin-dashboard"
   * @param string $entry Manifest key, e.g. "src/entries/dashboard.js"
   */
  public static function enqueue($handle, $entry)
  {
    $manifest = self::get_manifest();

    if (!isset($manifest[$entry])) {
      return;
    }

    $entry_data = $manifest[$entry];
    $base_url = AURORA_ADMIN_URL . "app/dist/";

    if (isset($entry_data["file"])) {
      wp_enqueue_script($handle, $base_url . $entry_data["file"], [], AURORA_ADMIN_VERSION, true);
      add_filter("script_loader_tag", function ($tag, $tag_handle) use ($handle) {
        if ($tag_handle !== $handle) {
          return $tag;
        }
        return str_replace(" src", " type=\"module\" src", $tag);
      }, 10, 2);
    }

    // Vite splits shared code (e.g. the AppShell used by every page) into
    // its own chunk with its own CSS file. The entry's own "css" key only
    // lists CSS pulled in directly by that entry — CSS from anything it
    // imports has to be collected by walking "imports" too, otherwise
    // shared-component styles silently never get linked on the page.
    $css_files = [];
    self::collect_css($manifest, $entry, $css_files);

    $index = 0;
    foreach (array_unique($css_files) as $css_file) {
      wp_enqueue_style($handle . "-" . $index, $base_url . $css_file, [], AURORA_ADMIN_VERSION);
      $index++;
    }
  }

  private static function collect_css($manifest, $entry, &$css_files)
  {
    if (!isset($manifest[$entry])) {
      return;
    }

    $entry_data = $manifest[$entry];

    if (!empty($entry_data["css"]) && is_array($entry_data["css"])) {
      foreach ($entry_data["css"] as $css_file) {
        $css_files[] = $css_file;
      }
    }

    if (!empty($entry_data["imports"]) && is_array($entry_data["imports"])) {
      foreach ($entry_data["imports"] as $imported_key) {
        self::collect_css($manifest, $imported_key, $css_files);
      }
    }
  }
}
