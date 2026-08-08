<?php
namespace AuroraAdmin\Options;

defined("ABSPATH") || exit();

/**
 * Single source of truth for the aurora_admin_settings option.
 * Deliberately simple — no request-lifetime caching layer, no schema
 * ceremony beyond what register_setting requires. Add fields as pages
 * actually need them.
 */
class Settings
{
  const OPTION_NAME = "aurora_admin_settings";

  public function __construct()
  {
    add_action("init", [self::class, "register"]);
    add_action("init", [self::class, "remove_retired_keys"]);
  }

  // One-time cleanup for settings keys that no longer exist in the UI:
  // the removed Remote Site Switcher feature (remote_sites stored a real
  // secret — application passwords — that nothing ever consumed) and the
  // removed licensing system (license_key, dev_license_override). Actively
  // deletes them from the database rather than just leaving them inertly
  // stored.
  public static function remove_retired_keys()
  {
    $settings = self::get();
    $retired = ["remote_sites", "remote_site_switcher_capability", "license_key", "dev_license_override"];
    $present = array_intersect($retired, array_keys($settings));

    if (empty($present)) {
      return;
    }

    foreach ($present as $key) {
      unset($settings[$key]);
    }
    update_option(self::OPTION_NAME, $settings);
  }

  public static function register()
  {
    register_setting("aurora-admin", self::OPTION_NAME, [
      "type" => "object",
      "default" => [],
      "capability" => "manage_options",
      // Merge incoming payloads into the stored value instead of replacing
      // it wholesale. This lets pages save just the keys they own (e.g. the
      // dashboard sends only dashboard_card_order, the shell only dark_mode)
      // without wiping settings they never received — which matters because
      // most pages get a sanitized copy (see get_safe()) and must never
      // write that stripped copy back.
      "sanitize_callback" => [self::class, "merge_on_save"],
      "show_in_rest" => [
        "schema" => [
          "type" => "object",
          "properties" => [
            // Two producers write this key with different types: the
            // toolbar's tri-state switcher sends 'light'/'dark'/'system'
            // (string), the settings page's Dark Mode toggle sends a plain
            // boolean. Leaving it untyped (falls through to
            // additionalProperties) lets both save instead of one
            // rejecting the other with a 400.
            "dashboard_card_order" => ["type" => "object"],
          ],
          "additionalProperties" => true,
        ],
      ],
    ]);
  }

  public static function merge_on_save($value)
  {
    $existing = get_option(self::OPTION_NAME, []);
    if (!is_array($existing)) {
      $existing = [];
    }
    if (!is_array($value)) {
      return $existing;
    }
    return array_merge($existing, self::sanitize($value));
  }

  /**
   * Type-appropriate sanitizer per known settings field (matches the field
   * "type" each one is registered with in app/src/pages/settings/
   * settingsConfig.js). Keys this plugin doesn't know about (e.g. a
   * companion plugin — Site Backup, etc. — sharing this same option to
   * store its own settings) pass through unchanged: this only tightens the
   * fields Aurora's own settings UI actually writes.
   */
  private static function sanitize($values)
  {
    $types = self::field_types();
    $out = [];
    foreach ($values as $key => $value) {
      $out[$key] = isset($types[$key]) ? self::sanitize_value($types[$key], $value) : $value;
    }
    return $out;
  }

  private static function field_types()
  {
    return [
      "logo" => "url",
      "dark_logo" => "url",
      "disable_theme" => "keys",
      "search_post_types" => "keys",
      "disable_global_search" => "key",
      "enable_analytics" => "bool",
      "track_admins" => "bool",
      "theme_preset" => "key",
      "font_family" => "text",
      "admin_favicon" => "url",
      "plugin_name" => "text",
      "text_replacements" => "deep",
      "style_login" => "bool",
      "login_logo" => "url",
      "login_bg_image" => "url",
      "login_bg_color" => "color",
      "login_form_bg" => "color",
      "login_button_color" => "color",
      "login_redirect_enabled" => "bool",
      "redirect_unauthenticated_url" => "url",
      "redirect_roles" => "keys",
      "redirect_after_login_url" => "url",
      "menu_search" => "bool",
      "use_custom_dashboard" => "bool",
      "dashboard_card_order" => "deep",
      "dark_mode" => "dark_mode",
      "modern_posts" => "bool",
      "modern_pages" => "bool",
      "modern_media" => "bool",
      "modern_users" => "bool",
      "modern_comments" => "bool",
      "modern_plugins" => "bool",
      "disable_gutenberg" => "bool",
      "full_admin_takeover" => "bool",
      "image_formats_enabled" => "bool",
      "image_formats_format" => "key",
      "image_formats_picture_element" => "bool",
      "enable_svg" => "bool",
      "disable_xmlrpc" => "bool",
      "disable_comments" => "bool",
      "remove_head_junk" => "bool",
      "disable_emojis" => "bool",
      "disable_oembed" => "bool",
      "heartbeat_mode" => "key",
    ];
  }

  private static function sanitize_value($type, $value)
  {
    switch ($type) {
      case "bool":
        return (bool) $value;
      case "url":
        return $value === "" ? "" : esc_url_raw((string) $value);
      case "color":
        return sanitize_hex_color((string) $value) ?: "";
      case "text":
        return sanitize_text_field((string) $value);
      case "key":
        return sanitize_key((string) $value);
      case "keys":
        return array_values(array_filter(array_map("sanitize_key", (array) $value)));
      case "dark_mode":
        // Two producers write this key with different types (see the
        // show_in_rest schema note above) — a tri-state string or a bool.
        if (is_bool($value)) {
          return $value;
        }
        return in_array($value, ["light", "dark", "system"], true) ? $value : "system";
      case "deep":
        return self::sanitize_deep($value);
      default:
        return $value;
    }
  }

  private static function sanitize_deep($value)
  {
    if (is_string($value)) {
      return sanitize_text_field($value);
    }
    if (is_array($value)) {
      return array_map([self::class, "sanitize_deep"], $value);
    }
    if (is_scalar($value) || $value === null) {
      return $value;
    }
    return null;
  }

  public static function get($key = null, $default = null)
  {
    $settings = get_option(self::OPTION_NAME, []);
    if (!is_array($settings)) {
      $settings = [];
    }

    if ($key === null) {
      return $settings;
    }

    return isset($settings[$key]) ? $settings[$key] : $default;
  }

  /**
   * Settings safe to expose on every admin page (the shell serializes this
   * for all logged-in users). Strips backup_s3_credentials/
   * backup_gdrive_credentials (contain a secret access key / OAuth client
   * secret + refresh token) — the exact "future field that shouldn't be
   * broadcast" case this method was originally kept separate from get()
   * to handle.
   */
  public static function get_safe()
  {
    $settings = self::get();
    unset($settings["backup_s3_credentials"], $settings["backup_gdrive_credentials"]);
    return $settings;
  }
}
