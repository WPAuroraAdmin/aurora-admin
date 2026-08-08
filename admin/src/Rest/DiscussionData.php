<?php
namespace AuroraAdmin\Rest;


defined("ABSPATH") || exit();

/**
 * Core's REST settings schema (register_initial_settings(), wp-includes/
 * option.php) only exposes 2 of options-discussion.php's ~15 real fields
 * (default_ping_status/default_comment_status) — everything else on that
 * native screen (moderation, threading, notification emails, avatars) has
 * no REST route at all. This is the same situation Permalinks was in
 * (PermalinksData.php) — a dedicated controller doing raw get_option()/
 * update_option() reads/writes instead of going through wp/v2/settings.
 *
 * Every key this accepts is explicitly allowlisted below — the client
 * sends a {values: {...}} object, but only keys this class already knows
 * about are ever written, so a modified request can't use this route to
 * write an arbitrary WP option.
 */
class DiscussionData
{
  const BOOLEAN_KEYS = [
    "default_pingback_flag",
    "require_name_email",
    "comment_registration",
    "close_comments_for_old_posts",
    "show_comments_cookies_opt_in",
    "thread_comments",
    "page_comments",
    "comments_notify",
    "moderation_notify",
    "wp_notes_notify",
    "comment_moderation",
    "comment_previously_approved",
    "show_avatars",
  ];

  const INTEGER_KEYS = ["close_comments_days_old", "comments_per_page", "thread_comments_depth", "comment_max_links"];

  const TEXTAREA_KEYS = ["moderation_keys", "disallowed_keys"];

  const ENUM_KEYS = [
    "default_ping_status" => ["open", "closed"],
    "default_comment_status" => ["open", "closed"],
    "default_comments_page" => ["newest", "oldest"],
    "comment_order" => ["asc", "desc"],
    "avatar_rating" => ["G", "PG", "R", "X"],
    "avatar_default" => [
      "mystery",
      "blank",
      "gravatar_default",
      "identicon",
      "wavatar",
      "monsterid",
      "retro",
      "robohash",
      "initials",
      "color",
    ],
  ];

  // Matches native options-discussion.php's own get_option() defaults —
  // everything else defaults to empty/false when never saved.
  const DEFAULTS = [
    "wp_notes_notify" => true,
    "avatar_default" => "mystery",
  ];

  public function __construct()
  {
    add_action("rest_api_init", [self::class, "register"]);
  }

  public static function register()
  {
    register_rest_route("aurora-admin/v1", "/discussion-settings", [
      "methods" => "GET",
      "callback" => [self::class, "get_values"],
      "permission_callback" => [self::class, "check_permission"],
    ]);

    register_rest_route("aurora-admin/v1", "/discussion-settings", [
      "methods" => "POST",
      "callback" => [self::class, "update_values"],
      "permission_callback" => [self::class, "check_permission"],
      "args" => [
        "values" => ["type" => "object", "required" => true],
      ],
    ]);
  }

  public static function check_permission()
  {
    return current_user_can("manage_options");
  }

  private static function all_keys()
  {
    return array_merge(self::BOOLEAN_KEYS, self::INTEGER_KEYS, self::TEXTAREA_KEYS, array_keys(self::ENUM_KEYS));
  }

  public static function get_values()
  {
    $out = [];
    foreach (self::all_keys() as $key) {
      $default = self::DEFAULTS[$key] ?? "";
      $value = get_option($key, $default);

      if (in_array($key, self::BOOLEAN_KEYS, true)) {
        $out[$key] = (bool) $value;
      } elseif (in_array($key, self::INTEGER_KEYS, true)) {
        $out[$key] = (int) $value;
      } else {
        $out[$key] = (string) $value;
      }
    }

    return new \WP_REST_Response($out, 200);
  }

  public static function update_values($request)
  {
    $values = (array) $request->get_param("values");

    foreach ($values as $key => $value) {
      if (in_array($key, self::BOOLEAN_KEYS, true)) {
        update_option($key, $value ? "1" : "");
      } elseif (in_array($key, self::INTEGER_KEYS, true)) {
        update_option($key, max(0, (int) $value));
      } elseif (in_array($key, self::TEXTAREA_KEYS, true)) {
        update_option($key, sanitize_textarea_field((string) $value));
      } elseif (isset(self::ENUM_KEYS[$key]) && in_array($value, self::ENUM_KEYS[$key], true)) {
        update_option($key, $value);
      }
      // Any other key (unknown/unauthorized) is silently ignored rather
      // than erroring — keeps a partial-field save from failing outright.
    }

    return new \WP_REST_Response(["success" => true], 200);
  }
}
