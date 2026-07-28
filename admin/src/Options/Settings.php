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
    return array_merge($existing, $value);
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
