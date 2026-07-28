<?php
namespace AuroraAdmin\Rest;


defined("ABSPATH") || exit();

/**
 * Core has no REST route for the permalink structure (rewrite rules aren't
 * a registered setting), so options-permalink.php's one real field needs its
 * own tiny controller — same options_permalink_structure() logic core's own
 * settings page runs, called directly instead of going through wp-admin's
 * form handler.
 */
class PermalinksData
{
  public function __construct()
  {
    add_action("rest_api_init", [self::class, "register"]);
  }

  public static function register()
  {
    register_rest_route("aurora-admin/v1", "/permalinks", [
      "methods" => "GET",
      "callback" => [self::class, "get_structure"],
      "permission_callback" => [self::class, "check_permission"],
    ]);

    register_rest_route("aurora-admin/v1", "/permalinks", [
      "methods" => "POST",
      "callback" => [self::class, "update_structure"],
      "permission_callback" => [self::class, "check_permission"],
      "args" => [
        "structure" => ["type" => "string", "required" => true],
      ],
    ]);
  }

  public static function check_permission()
  {
    return current_user_can("manage_options");
  }

  public static function get_structure()
  {
    return new \WP_REST_Response([
      "structure" => get_option("permalink_structure", ""),
    ], 200);
  }

  public static function update_structure($request)
  {
    $structure = sanitize_text_field((string) $request->get_param("structure"));

    global $wp_rewrite;
    $wp_rewrite->set_permalink_structure($structure);
    $wp_rewrite->flush_rules();

    return new \WP_REST_Response([
      "success" => true,
      "structure" => get_option("permalink_structure", ""),
    ], 200);
  }
}
