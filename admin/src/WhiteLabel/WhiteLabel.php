<?php
namespace AuroraAdmin\WhiteLabel;

use AuroraAdmin\Options\Settings;

defined("ABSPATH") || exit();

/**
 * Applies the White Label settings:
 *  - admin_favicon: replaces the favicon across wp-admin
 *  - plugin_name: renames the plugin in the plugins list and the admin menu
 *  - text_replacements: find/replace pairs applied to translated admin text
 */
class WhiteLabel
{
  /** @var array<string,string>|null Cached find=>replace map for gettext. */
  private static $replacements = null;

  public function __construct()
  {
    add_action("admin_head", [self::class, "favicon"]);
    add_filter("all_plugins", [self::class, "rename_plugin"]);
    add_action("admin_menu", [self::class, "rename_menu"], 999);
    add_filter("gettext", [self::class, "replace_text"], 20, 3);
  }

  public static function favicon()
  {
    $url = Settings::get("admin_favicon", "");
    if (!$url) {
      return;
    }
    printf('<link rel="icon" href="%s" />' . "\n", esc_url($url));
  }

  public static function rename_plugin($plugins)
  {
    $name = trim((string) Settings::get("plugin_name", ""));
    if ($name === "") {
      return $plugins;
    }

    $basename = "aurora-admin/aurora-admin.php";
    if (isset($plugins[$basename])) {
      $plugins[$basename]["Name"] = $name;
      $plugins[$basename]["Title"] = $name;
    }

    return $plugins;
  }

  public static function rename_menu()
  {
    $name = trim((string) Settings::get("plugin_name", ""));
    if ($name === "") {
      return;
    }

    global $menu;
    if (!is_array($menu)) {
      return;
    }
    foreach ($menu as $index => $item) {
      if (isset($item[2]) && $item[2] === "aurora-admin") {
        $menu[$index][0] = $name;
        break;
      }
    }
  }

  public static function replace_text($translation, $text, $domain)
  {
    // Front end and login screens stay untouched.
    if (!is_admin()) {
      return $translation;
    }

    if (self::$replacements === null) {
      self::$replacements = [];
      $pairs = Settings::get("text_replacements", []);
      if (is_array($pairs)) {
        foreach ($pairs as $pair) {
          if (
            is_array($pair) &&
            isset($pair["find"], $pair["replace"]) &&
            $pair["find"] !== ""
          ) {
            self::$replacements[$pair["find"]] = (string) $pair["replace"];
          }
        }
      }
    }

    if (!self::$replacements) {
      return $translation;
    }

    return strtr($translation, self::$replacements);
  }
}
