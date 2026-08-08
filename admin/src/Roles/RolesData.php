<?php
namespace AuroraAdmin\Roles;


defined("ABSPATH") || exit();

/**
 * REST CRUD wrapping WordPress's own roles/capabilities API — no custom
 * table, this reads and writes the real wp_user_roles option via WP_Roles.
 */
class RolesData
{
  // WordPress has no built-in "is this a default role" check — these are
  // the five core roles, hard-coded here as delete-protected.
  const PROTECTED_ROLES = ["administrator", "editor", "author", "contributor", "subscriber"];

  public function __construct()
  {
    add_action("rest_api_init", [self::class, "register"]);
  }

  public static function register()
  {
    $permission = function () {
      return current_user_can("manage_options");
    };

    register_rest_route("aurora-admin/v1", "/roles", [
      [
        "methods" => "GET",
        "callback" => [self::class, "list_roles"],
        "permission_callback" => $permission,
      ],
      [
        "methods" => "POST",
        "callback" => [self::class, "create"],
        "permission_callback" => $permission,
        "args" => [
          "slug" => ["type" => "string", "required" => true],
          "displayName" => ["type" => "string", "required" => true],
        ],
      ],
    ]);

    register_rest_route("aurora-admin/v1", "/roles/(?P<slug>[a-z0-9_\-]+)", [
      [
        "methods" => "PUT",
        "callback" => [self::class, "update"],
        "permission_callback" => $permission,
      ],
      [
        "methods" => "DELETE",
        "callback" => [self::class, "delete"],
        "permission_callback" => $permission,
      ],
    ]);
  }

  public static function list_roles()
  {
    // get_editable_roles() lives in wp-admin/includes/user.php, which WP
    // only auto-loads for actual wp-admin page requests — REST API requests
    // (this endpoint) don't get it for free, so it's undefined there
    // without this.
    if (!function_exists("get_editable_roles")) {
      require_once ABSPATH . "wp-admin/includes/user.php";
    }
    $editable = get_editable_roles();
    $all_caps = self::all_known_capabilities();

    $out = [];
    foreach ($editable as $slug => $role) {
      $user_count = count(get_users(["role" => $slug, "fields" => "ID"]));
      $out[] = [
        "slug" => $slug,
        "name" => translate_user_role($role["name"]),
        "capabilities" => array_keys(array_filter($role["capabilities"])),
        "userCount" => $user_count,
        "isDefault" => in_array($slug, self::PROTECTED_ROLES, true),
      ];
    }

    return new \WP_REST_Response(["roles" => $out, "allCapabilities" => $all_caps], 200);
  }

  public static function create($request)
  {
    $slug = sanitize_key($request->get_param("slug"));
    $display_name = sanitize_text_field($request->get_param("displayName"));

    if (!$slug || !$display_name) {
      return new \WP_Error("invalid_role", "A role slug and name are required", ["status" => 400]);
    }
    if (get_role($slug)) {
      return new \WP_Error("role_exists", "A role with this slug already exists", ["status" => 409]);
    }

    $caps = self::sanitize_caps($request->get_param("capabilities"));
    add_role($slug, $display_name, array_fill_keys($caps, true));

    return new \WP_REST_Response(["slug" => $slug, "name" => $display_name, "capabilities" => $caps], 200);
  }

  public static function update($request)
  {
    $slug = $request->get_param("slug");
    $role = get_role($slug);
    if (!$role) {
      return new \WP_Error("not_found", "Role not found", ["status" => 404]);
    }

    if ($request->get_param("capabilities") !== null) {
      $desired = self::sanitize_caps($request->get_param("capabilities"));
      foreach (array_keys($role->capabilities) as $existing_cap) {
        if (!in_array($existing_cap, $desired, true)) {
          $role->remove_cap($existing_cap);
        }
      }
      foreach ($desired as $cap) {
        $role->add_cap($cap);
      }
    }

    if ($request->get_param("displayName")) {
      global $wp_roles;
      $wp_roles->roles[$slug]["name"] = sanitize_text_field($request->get_param("displayName"));
      $wp_roles->role_names[$slug] = $wp_roles->roles[$slug]["name"];
      update_option($wp_roles->role_key, $wp_roles->roles);
    }

    return new \WP_REST_Response(["updated" => true], 200);
  }

  public static function delete($request)
  {
    $slug = $request->get_param("slug");

    if (in_array($slug, self::PROTECTED_ROLES, true)) {
      return new \WP_Error("protected_role", "Default WordPress roles can't be deleted", ["status" => 403]);
    }
    if (!get_role($slug)) {
      return new \WP_Error("not_found", "Role not found", ["status" => 404]);
    }

    remove_role($slug);
    return new \WP_REST_Response(["deleted" => true], 200);
  }

  private static function sanitize_caps($caps)
  {
    if (!is_array($caps)) {
      return [];
    }
    return array_values(array_unique(array_map("sanitize_key", $caps)));
  }

  /**
   * WordPress has no single "list all capabilities" API — build one from
   * every capability already assigned across existing roles, which is what
   * every native role-editor plugin does in practice.
   */
  private static function all_known_capabilities()
  {
    $caps = [];
    foreach (wp_roles()->roles as $role) {
      foreach (array_keys($role["capabilities"]) as $cap) {
        $caps[$cap] = true;
      }
    }
    $caps = array_keys($caps);
    sort($caps);
    return $caps;
  }
}
