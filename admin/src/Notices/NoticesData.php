<?php
namespace AuroraAdmin\Notices;

defined("ABSPATH") || exit();

/**
 * REST CRUD for admin notices. GET/POST /notices, PUT/DELETE /notices/:id.
 */
class NoticesData
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

    register_rest_route("aurora-admin/v1", "/notices", [
      [
        "methods" => "GET",
        "callback" => [self::class, "list_notices"],
        "permission_callback" => $permission,
      ],
      [
        "methods" => "POST",
        "callback" => [self::class, "create"],
        "permission_callback" => $permission,
        "args" => [
          "title" => ["type" => "string", "required" => true],
        ],
      ],
    ]);

    register_rest_route("aurora-admin/v1", "/notices/(?P<id>\d+)", [
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

  public static function list_notices()
  {
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- table name from an internal, hardcoded {$wpdb->prefix}-based constant, not user input; no user-supplied values in this query.
    $rows = $wpdb->get_results("SELECT * FROM " . Notices::table_name() . " ORDER BY updated_at DESC");
    return new \WP_REST_Response(array_map([self::class, "format"], $rows), 200);
  }

  public static function create($request)
  {
    global $wpdb;
    $now = current_time("mysql");
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $wpdb->insert() is WordPress's own escaping-safe API for writing to Aurora's custom notices table.
    $wpdb->insert(Notices::table_name(), [
      "title" => sanitize_text_field($request->get_param("title")),
      "message" => sanitize_textarea_field((string) $request->get_param("message")),
      "type" => self::sanitize_type($request->get_param("type")),
      "roles" => wp_json_encode(self::sanitize_list($request->get_param("roles"))),
      "pages" => wp_json_encode(self::sanitize_list($request->get_param("pages"))),
      "dismissible" => $request->get_param("dismissible") === false ? 0 : 1,
      "active" => $request->get_param("active") === false ? 0 : 1,
      "created_at" => $now,
      "updated_at" => $now,
    ]);

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- table name from an internal, hardcoded {$wpdb->prefix}-based constant, not user input; the %d value is passed through $wpdb->prepare() below.
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . Notices::table_name() . " WHERE id = %d", $wpdb->insert_id));
    return new \WP_REST_Response(self::format($row), 200);
  }

  public static function update($request)
  {
    global $wpdb;
    $id = (int) $request->get_param("id");
    $fields = ["updated_at" => current_time("mysql")];

    foreach (["title" => "sanitize_text_field", "message" => "sanitize_textarea_field"] as $key => $fn) {
      if ($request->get_param($key) !== null) {
        $fields[$key] = call_user_func($fn, (string) $request->get_param($key));
      }
    }
    if ($request->get_param("type") !== null) {
      $fields["type"] = self::sanitize_type($request->get_param("type"));
    }
    if ($request->get_param("roles") !== null) {
      $fields["roles"] = wp_json_encode(self::sanitize_list($request->get_param("roles")));
    }
    if ($request->get_param("pages") !== null) {
      $fields["pages"] = wp_json_encode(self::sanitize_list($request->get_param("pages")));
    }
    if ($request->get_param("dismissible") !== null) {
      $fields["dismissible"] = $request->get_param("dismissible") ? 1 : 0;
    }
    if ($request->get_param("active") !== null) {
      $fields["active"] = $request->get_param("active") ? 1 : 0;
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $wpdb->update() is WordPress's own escaping-safe API for writing to Aurora's custom notices table.
    $wpdb->update(Notices::table_name(), $fields, ["id" => $id]);

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- table name from an internal, hardcoded {$wpdb->prefix}-based constant, not user input; the %d value is passed through $wpdb->prepare() below.
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . Notices::table_name() . " WHERE id = %d", $id));
    return new \WP_REST_Response(self::format($row), 200);
  }

  public static function delete($request)
  {
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $wpdb->delete() is WordPress's own escaping-safe API for writing to Aurora's custom notices table.
    $wpdb->delete(Notices::table_name(), ["id" => (int) $request->get_param("id")]);
    return new \WP_REST_Response(["deleted" => true], 200);
  }

  private static function format($row)
  {
    return [
      "id" => (int) $row->id,
      "title" => $row->title,
      "message" => $row->message,
      "type" => $row->type,
      "roles" => json_decode($row->roles ?: "[]", true) ?: [],
      "pages" => json_decode($row->pages ?: "[]", true) ?: [],
      "dismissible" => (bool) $row->dismissible,
      "active" => (bool) $row->active,
      "createdAt" => $row->created_at,
      "updatedAt" => $row->updated_at,
    ];
  }

  private static function sanitize_type($type)
  {
    return in_array($type, ["success", "warning", "error", "info"], true) ? $type : "info";
  }

  private static function sanitize_list($list)
  {
    if (!is_array($list)) {
      return [];
    }
    return array_values(array_map("sanitize_text_field", $list));
  }
}
