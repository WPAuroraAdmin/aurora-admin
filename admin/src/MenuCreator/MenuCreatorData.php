<?php
namespace AuroraAdmin\MenuCreator;

defined("ABSPATH") || exit();

/**
 * REST CRUD for saved menu configurations. GET/POST /menus,
 * GET/PUT/DELETE /menus/:id. All manage_options-gated.
 */
class MenuCreatorData
{
  public function __construct()
  {
    add_action("rest_api_init", [self::class, "register"]);
  }

  public static function register()
  {
    $permission = function () {
      return current_user_can("manage_options");
    };

    register_rest_route("aurora-admin/v1", "/menus", [
      [
        "methods" => "GET",
        "callback" => [self::class, "list_menus"],
        "permission_callback" => $permission,
        "args" => [
          "status" => ["type" => "string", "required" => false],
        ],
      ],
      [
        "methods" => "POST",
        "callback" => [self::class, "create"],
        "permission_callback" => $permission,
        "args" => [
          "name" => ["type" => "string", "required" => true],
          "status" => ["type" => "string", "required" => false],
          "roles" => ["type" => "array", "required" => false],
          "items" => ["type" => "array", "required" => false],
        ],
      ],
    ]);

    register_rest_route("aurora-admin/v1", "/menus/native-items", [
      "methods" => "GET",
      "callback" => [self::class, "native_items"],
      "permission_callback" => $permission,
    ]);

    register_rest_route("aurora-admin/v1", "/menus/(?P<id>\d+)", [
      [
        "methods" => "GET",
        "callback" => [self::class, "get_one"],
        "permission_callback" => $permission,
      ],
      [
        "methods" => "PUT",
        "callback" => [self::class, "update"],
        "permission_callback" => $permission,
        "args" => [
          "name" => ["type" => "string", "required" => false],
          "status" => ["type" => "string", "required" => false],
          "roles" => ["type" => "array", "required" => false],
          "items" => ["type" => "array", "required" => false],
        ],
      ],
      [
        "methods" => "DELETE",
        "callback" => [self::class, "delete"],
        "permission_callback" => $permission,
      ],
    ]);
  }

  /**
   * The real, current WordPress menu (same source MenuSerializer feeds to
   * the sidebar) flattened so the menu editor has something concrete to
   * toggle hide/rename on — top-level items plus their submenu items.
   */
  public static function native_items()
  {
    $menu = \AuroraAdmin\Shell\MenuSerializer::serialize();
    $flat = [];
    // Top-level items only. These are what the Menu Creator can reorder,
    // hide, and rename today; submenu editing isn't applied yet, so it isn't
    // offered (showing controls that do nothing is worse than not showing
    // them).
    foreach ($menu as $item) {
      if (!empty($item["separator"]) || empty($item["id"])) {
        continue;
      }
      $flat[] = ["native_id" => $item["id"], "title" => $item["title"], "icon" => $item["icon"]];
    }
    return new \WP_REST_Response($flat, 200);
  }

  public static function list_menus($request)
  {
    global $wpdb;
    $table = MenuCreator::table_name();
    $status = $request->get_param("status");

    if ($status) {
      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- {$table} is an internal, hardcoded {$wpdb->prefix}-based table name, not user input; $status is bound via the $wpdb->prepare() %s placeholder.
      $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE status = %s ORDER BY updated_at DESC", $status));
    } else {
      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- {$table} is an internal, hardcoded {$wpdb->prefix}-based table name, not user input; no user-supplied values in this query.
      $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY updated_at DESC");
    }

    return new \WP_REST_Response(array_map([self::class, "format"], $rows), 200);
  }

  public static function get_one($request)
  {
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- table name from an internal, hardcoded {$wpdb->prefix}-based constant, not user input; the %d value is passed through $wpdb->prepare() below.
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . MenuCreator::table_name() . " WHERE id = %d", (int) $request->get_param("id")));
    if (!$row) {
      return new \WP_Error("not_found", "Menu not found", ["status" => 404]);
    }
    return new \WP_REST_Response(self::format($row), 200);
  }

  public static function create($request)
  {
    global $wpdb;
    $now = current_time("mysql");

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $wpdb->insert() is WordPress's own escaping-safe API for writing to Aurora's custom menu-configs table.
    $wpdb->insert(MenuCreator::table_name(), [
      "name" => sanitize_text_field($request->get_param("name")),
      "status" => self::sanitize_status($request->get_param("status")),
      "roles" => wp_json_encode(self::sanitize_roles($request->get_param("roles"))),
      "items" => wp_json_encode(self::sanitize_items($request->get_param("items"))),
      "created_at" => $now,
      "updated_at" => $now,
    ]);

    $get_request = new \WP_REST_Request("GET");
    $get_request->set_url_params(["id" => $wpdb->insert_id]);
    return self::get_one($get_request);
  }

  public static function update($request)
  {
    global $wpdb;
    $id = (int) $request->get_param("id");
    $fields = ["updated_at" => current_time("mysql")];

    if ($request->get_param("name") !== null) {
      $fields["name"] = sanitize_text_field($request->get_param("name"));
    }
    if ($request->get_param("status") !== null) {
      $fields["status"] = self::sanitize_status($request->get_param("status"));
    }
    if ($request->get_param("roles") !== null) {
      $fields["roles"] = wp_json_encode(self::sanitize_roles($request->get_param("roles")));
    }
    if ($request->get_param("items") !== null) {
      $fields["items"] = wp_json_encode(self::sanitize_items($request->get_param("items")));
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $wpdb->update() is WordPress's own escaping-safe API for writing to Aurora's custom menu-configs table.
    $wpdb->update(MenuCreator::table_name(), $fields, ["id" => $id]);

    return self::get_one($request);
  }

  public static function delete($request)
  {
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $wpdb->delete() is WordPress's own escaping-safe API for writing to Aurora's custom menu-configs table.
    $wpdb->delete(MenuCreator::table_name(), ["id" => (int) $request->get_param("id")]);
    return new \WP_REST_Response(["deleted" => true], 200);
  }

  private static function format($row)
  {
    return [
      "id" => (int) $row->id,
      "name" => $row->name,
      "status" => $row->status,
      "roles" => json_decode($row->roles ?: "[]", true) ?: [],
      "items" => json_decode($row->items ?: "[]", true) ?: [],
      "createdAt" => $row->created_at,
      "updatedAt" => $row->updated_at,
    ];
  }

  private static function sanitize_status($status)
  {
    return $status === "published" ? "published" : "draft";
  }

  private static function sanitize_roles($roles)
  {
    if (!is_array($roles)) {
      return [];
    }
    $valid = array_keys(wp_roles()->roles);
    return array_values(array_intersect(array_map("sanitize_key", $roles), $valid));
  }

  private static function sanitize_items($items)
  {
    if (!is_array($items)) {
      return [];
    }
    $out = [];
    foreach ($items as $item) {
      if (!is_array($item) || empty($item["native_id"])) {
        continue;
      }
      $out[] = [
        "native_id" => sanitize_text_field($item["native_id"]),
        "label" => isset($item["label"]) ? sanitize_text_field($item["label"]) : "",
        "hidden" => !empty($item["hidden"]),
        "order" => isset($item["order"]) ? (int) $item["order"] : 0,
      ];
    }
    return $out;
  }
}
