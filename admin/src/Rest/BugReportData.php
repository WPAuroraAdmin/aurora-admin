<?php
namespace AuroraAdmin\Rest;

defined("ABSPATH") || exit();

/**
 * POST /bug-report — forwards a bug report to the Aurora Dragon Studio
 * feedback endpoint, tagged with basic diagnostics (plugin/WP/PHP version,
 * active theme, domain) so reports can be triaged without a separate
 * ticketing system.
 */
class BugReportData
{
  public function __construct()
  {
    add_action("rest_api_init", [self::class, "register"]);
  }

  public static function register()
  {
    register_rest_route("aurora-admin/v1", "/bug-report", [
      "methods" => "POST",
      "callback" => [self::class, "submit"],
      "permission_callback" => function () {
        return current_user_can("manage_options");
      },
      "args" => [
        "description" => ["type" => "string", "required" => true],
      ],
    ]);
  }

  const API_URL = "https://auroraplugins.com/wp-json/aurora-licensing/v1/report-bug";

  public static function submit($request)
  {
    $description = sanitize_textarea_field((string) $request->get_param("description"));
    if (trim($description) === "") {
      return new \WP_REST_Response(["success" => false, "message" => "Description is required."], 400);
    }

    $response = wp_remote_post(self::API_URL, [
      "timeout" => 8,
      "headers" => ["Content-Type" => "application/json"],
      "body" => wp_json_encode([
        "description" => $description,
        "domain" => (string) wp_parse_url(home_url(), PHP_URL_HOST),
        "plugin_version" => defined("AURORA_ADMIN_VERSION") ? AURORA_ADMIN_VERSION : "",
        "wp_version" => get_bloginfo("version"),
        "php_version" => phpversion(),
        "theme" => wp_get_theme()->get("Name"),
      ]),
    ]);

    if (is_wp_error($response)) {
      return new \WP_REST_Response([
        "success" => false,
        "message" => $response->get_error_message(),
      ], 502);
    }

    $decoded = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($decoded["success"])) {
      return new \WP_REST_Response([
        "success" => false,
        "message" => $decoded["message"] ?? "Could not send the report.",
      ], 502);
    }

    return new \WP_REST_Response(["success" => true], 200);
  }
}
