<?php
namespace AuroraAdmin\Rest;

defined("ABSPATH") || exit();

/**
 * The one REST route Aurora's Profile reskin needs beyond core's own
 * /wp/v2/users/me (which already covers name/contact/bio fields): core
 * deliberately excludes password from that endpoint's schema, so changing
 * it needs this tiny wrapper around wp_set_password() — the same function
 * the native Your Profile screen itself calls.
 */
class ProfileData
{
  public function __construct()
  {
    add_action("rest_api_init", [self::class, "register"]);
  }

  public static function register()
  {
    register_rest_route("aurora-admin/v1", "/profile/password", [
      "methods" => "POST",
      "callback" => [self::class, "update_password"],
      "permission_callback" => function () {
        return is_user_logged_in();
      },
      "args" => [
        "password" => ["type" => "string", "required" => true],
      ],
    ]);
  }

  public static function update_password($request)
  {
    $password = (string) $request->get_param("password");

    if (strlen($password) < 8) {
      return new \WP_Error("aurora_profile_password_short", __("Password must be at least 8 characters.", "aurora-admin"), ["status" => 400]);
    }

    $user_id = get_current_user_id();
    wp_set_password($password, $user_id);

    // wp_set_password() invalidates every session's auth cookie (it's
    // derived in part from the password hash) — including the one this
    // very request came in on. Re-issue it immediately, same as core's own
    // user-edit.php does when you change your own password, or the next
    // request (the REST response reaching the browser) would already be
    // logged out.
    wp_clear_auth_cookie();
    wp_set_auth_cookie($user_id);

    return new \WP_REST_Response(["success" => true], 200);
  }
}
