<?php
namespace AuroraAdmin\Rest;

defined("ABSPATH") || exit();

/**
 * REST endpoints for Aurora's custom Users page — same list-table pattern
 * as Posts/Pages/Comments/Media. "Add New User" still opens the native
 * user-new.php (account creation involves email verification/role
 * assignment forms not worth rebuilding, same reasoning as leaving
 * media-new.php/post-new.php alone for Media/Posts/Pages).
 *
 * Deleting a user reassigns their content to whichever admin performs the
 * deletion, rather than presenting WP core's "delete or reassign to
 * someone else" choice screen — a deliberate simplification, not an
 * oversight: wp_delete_user() with a null $reassign deletes all of the
 * user's content too, which is much more destructive and surprising as a
 * one-click REST action than always reassigning safely.
 */
class UsersData
{
  public function __construct()
  {
    add_action("rest_api_init", [self::class, "register"]);
  }

  public static function register()
  {
    register_rest_route("aurora-admin/v1", "/users", [
      "methods" => "GET",
      "callback" => [self::class, "list_users"],
      "permission_callback" => function () {
        return current_user_can("list_users");
      },
      "args" => [
        "role" => ["type" => "string", "required" => false],
        "search" => ["type" => "string", "required" => false],
        "page" => ["type" => "integer", "required" => false],
        "perPage" => ["type" => "integer", "required" => false],
      ],
    ]);

    register_rest_route("aurora-admin/v1", "/users/(?P<id>\d+)", [
      "methods" => "DELETE",
      "callback" => [self::class, "delete_user"],
      "permission_callback" => function ($request) {
        $id = (int) $request["id"];
        return $id && current_user_can("delete_user", $id) && $id !== get_current_user_id();
      },
      "args" => [
        "id" => ["type" => "integer", "required" => true],
      ],
    ]);
  }

  public static function list_users($request)
  {
    $role = sanitize_key((string) $request->get_param("role"));
    $page = max(1, (int) ($request->get_param("page") ?: 1));
    $per_page = min(100, max(10, (int) ($request->get_param("perPage") ?: 20)));
    $search = sanitize_text_field((string) $request->get_param("search"));

    $args = [
      "number" => $per_page,
      "offset" => ($page - 1) * $per_page,
      "orderby" => "display_name",
      "order" => "ASC",
      "count_total" => true,
    ];

    if ($role !== "" && $role !== "all") {
      $args["role"] = $role;
    }

    if ($search !== "") {
      $args["search"] = "*{$search}*";
      $args["search_columns"] = ["user_login", "user_email", "display_name"];
    }

    $query = new \WP_User_Query($args);

    return new \WP_REST_Response([
      "items" => array_map([self::class, "serialize_user"], $query->get_results()),
      "stats" => self::stats(),
      "pagination" => [
        "page" => $page,
        "perPage" => $per_page,
        "total" => (int) $query->get_total(),
        "totalPages" => max(1, (int) ceil($query->get_total() / $per_page)),
      ],
    ], 200);
  }

  public static function delete_user($request)
  {
    $id = (int) $request["id"];
    $user = get_userdata($id);
    if (!$user) {
      return new \WP_Error("aurora_user_invalid", __("Invalid user.", "aurora-admin"), ["status" => 400]);
    }

    require_once ABSPATH . "wp-admin/includes/user.php";
    $result = wp_delete_user($id, get_current_user_id());
    if (!$result) {
      return new \WP_Error("aurora_user_delete_failed", __("Could not delete the user.", "aurora-admin"), ["status" => 500]);
    }

    return new \WP_REST_Response(["success" => true, "message" => __("User deleted; their content was reassigned to you.", "aurora-admin")], 200);
  }

  private static function serialize_user($user)
  {
    $roles = array_map("translate_user_role", $user->roles);

    return [
      "id" => (int) $user->ID,
      "username" => $user->user_login,
      "displayName" => $user->display_name,
      "email" => $user->user_email,
      "avatarUrl" => get_avatar_url($user->ID, ["size" => 64]),
      "roles" => array_values($roles),
      "postCount" => (int) count_user_posts($user->ID, "post"),
      "registeredDate" => mysql2date("M j, Y", $user->user_registered),
      "editUrl" => get_edit_user_link($user->ID),
      "canDelete" => current_user_can("delete_user", $user->ID) && $user->ID !== get_current_user_id(),
    ];
  }

  private static function stats()
  {
    $counts = count_users();
    $by_role = [];
    foreach ($counts["avail_roles"] as $role => $count) {
      $by_role[$role] = (int) $count;
    }

    return [
      "all" => (int) $counts["total_users"],
      "byRole" => $by_role,
    ];
  }
}
