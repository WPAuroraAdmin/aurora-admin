<?php
namespace AuroraAdmin\Rest;


defined("ABSPATH") || exit();

/**
 * REST endpoints for Aurora's Themes replacement (Appearance > Themes'
 * browse/activate/delete — not the Customizer, which Shell already
 * deliberately excludes from framing/hijacking).
 */
class ThemesData
{
  public function __construct()
  {
    add_action("rest_api_init", [self::class, "register"]);
  }

  public static function check_permission()
  {
    return current_user_can("switch_themes");
  }

  public static function register()
  {
    register_rest_route("aurora-admin/v1", "/themes", [
      "methods" => "GET",
      "callback" => [self::class, "list_themes"],
      "permission_callback" => [self::class, "check_permission"],
    ]);

    register_rest_route("aurora-admin/v1", "/themes/activate", [
      "methods" => "POST",
      "callback" => [self::class, "activate_theme"],
      "permission_callback" => [self::class, "check_permission"],
      "args" => ["stylesheet" => ["type" => "string", "required" => true]],
    ]);

    register_rest_route("aurora-admin/v1", "/themes/(?P<stylesheet>[^/]+)", [
      "methods" => "DELETE",
      "callback" => [self::class, "delete_theme"],
      "permission_callback" => function () {
        return current_user_can("delete_themes");
      },
    ]);
  }

  public static function list_themes()
  {
    $themes = wp_get_themes();
    $active = get_stylesheet();

    $out = [];
    foreach ($themes as $stylesheet => $theme) {
      $out[] = [
        "stylesheet" => $stylesheet,
        "name" => $theme->get("Name"),
        "version" => $theme->get("Version"),
        "description" => $theme->get("Description"),
        "screenshot" => $theme->get_screenshot() ?: "",
        "active" => $stylesheet === $active,
      ];
    }

    // Active theme first, alphabetical after.
    usort($out, function ($a, $b) {
      if ($a["active"] !== $b["active"]) {
        return $b["active"] <=> $a["active"];
      }
      return strcasecmp($a["name"], $b["name"]);
    });

    return new \WP_REST_Response($out, 200);
  }

  public static function activate_theme($request)
  {
    $stylesheet = sanitize_text_field((string) $request->get_param("stylesheet"));
    $theme = wp_get_theme($stylesheet);

    if (!$theme->exists()) {
      return new \WP_Error("aurora_theme_invalid", __("Theme not found.", "aurora-admin"), ["status" => 404]);
    }

    switch_theme($stylesheet);

    return new \WP_REST_Response(["success" => true, "stylesheet" => $stylesheet], 200);
  }

  public static function delete_theme($request)
  {
    $stylesheet = sanitize_text_field((string) $request["stylesheet"]);

    if ($stylesheet === get_stylesheet()) {
      return new \WP_Error("aurora_theme_active", __("Can't delete the active theme.", "aurora-admin"), ["status" => 400]);
    }

    require_once ABSPATH . "wp-admin/includes/file.php";
    require_once ABSPATH . "wp-admin/includes/theme.php";

    $result = delete_theme($stylesheet);
    if (is_wp_error($result)) {
      return new \WP_Error("aurora_theme_delete_failed", $result->get_error_message(), ["status" => 500]);
    }

    return new \WP_REST_Response(["success" => true], 200);
  }
}
