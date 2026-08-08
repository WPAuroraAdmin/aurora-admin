<?php
namespace AuroraAdmin\ActivityLog;


defined("ABSPATH") || exit();

/**
 * REST list for the activity log. GET /activity-log?action_type=&object_type=&page=&per_page=
 */
class ActivityLogData
{
  public function __construct()
  {
    add_action("rest_api_init", [self::class, "register"]);
  }

  public static function register()
  {
    register_rest_route("aurora-admin/v1", "/activity-log", [
      "methods" => "GET",
      "callback" => [self::class, "handle"],
      "permission_callback" => function () {
        return current_user_can("manage_options");
      },
      "args" => [
        // "admin" = site/content activity (everything except user-account
        // events), "user" = account activity (login/logout/registration/
        // profile updates) — the Activity Log page's two tabs. Omit for
        // unfiltered (both).
        "scope" => ["type" => "string", "required" => false, "enum" => ["admin", "user"]],
        "action_type" => ["type" => "string", "required" => false],
        "object_type" => ["type" => "string", "required" => false],
        "search" => ["type" => "string", "required" => false],
        "page" => ["type" => "integer", "required" => false, "default" => 1],
        "per_page" => ["type" => "integer", "required" => false, "default" => 20],
      ],
    ]);
  }

  public static function handle($request)
  {
    global $wpdb;
    $table = ActivityLog::table_name();

    $where = ["1=1"];
    $params = [];

    $scope = $request->get_param("scope");
    if ($scope === "user") {
      $where[] = "object_type = 'user'";
    } elseif ($scope === "admin") {
      $where[] = "object_type != 'user'";
    }

    if ($request->get_param("action_type")) {
      $where[] = "action_type = %s";
      $params[] = sanitize_text_field($request->get_param("action_type"));
    }
    if ($request->get_param("object_type")) {
      $where[] = "object_type = %s";
      $params[] = sanitize_text_field($request->get_param("object_type"));
    }
    if ($request->get_param("search")) {
      $where[] = "(object_label LIKE %s OR user_name LIKE %s)";
      $like = "%" . $wpdb->esc_like(sanitize_text_field($request->get_param("search"))) . "%";
      $params[] = $like;
      $params[] = $like;
    }

    $where_sql = implode(" AND ", $where);

    $per_page = max(1, min(100, (int) $request->get_param("per_page") ?: 20));
    $page = max(1, (int) $request->get_param("page") ?: 1);
    $offset = ($page - 1) * $per_page;

    // $table is an internal, hardcoded {$wpdb->prefix}-based table name
    // (ActivityLog::table_name()); $where_sql is built only from the fixed
    // "1=1"/"col = %s" fragments above (never raw user input).
    $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- reading Aurora's own custom activity-log table, no core API for that; $count_sql is prepare()'d whenever $params is non-empty (the only case with real values to bind) — when $params is empty, $count_sql is just the literal "SELECT COUNT(*) FROM {$table} WHERE 1=1" with nothing user-controlled in it at all.
    $total = (int) ($params ? $wpdb->get_var($wpdb->prepare($count_sql, $params)) : $wpdb->get_var($count_sql));

    // $table/$where_sql: same as $count_sql above.
    $sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- reading Aurora's own custom activity-log table; always passed through $wpdb->prepare() with $params plus the trailing $per_page/$offset bound via %d/%d.
    $rows = $wpdb->get_results($wpdb->prepare($sql, array_merge($params, [$per_page, $offset])));

    // Live user info (avatar/role/email) rather than freezing it into each
    // log row at record time — reflects the account's CURRENT role/email
    // (correct if either changes later) and avoids duplicating PII into
    // every single event row when user_id is already a foreign key back to
    // it. Batch-fetched once for every distinct user_id on this page,
    // rather than one get_userdata() call per row.
    $user_ids = array_values(array_unique(array_filter(array_map(function ($row) {
      return $row->user_id ? (int) $row->user_id : 0;
    }, $rows))));
    $users_by_id = [];
    if ($user_ids) {
      // $roles[0] is a role SLUG (e.g. "shop_manager") — its translated
      // display name has to come from wp_roles()'s own registered name for
      // that slug, not a guess like ucfirst($slug); that only happens to
      // work for the single-word built-in roles.
      $all_roles = wp_roles()->roles;
      foreach (get_users(["include" => $user_ids, "fields" => "all_with_meta"]) as $u) {
        $slug = $u->roles ? $u->roles[0] : "";
        $role_name = $slug && isset($all_roles[$slug]) ? translate_user_role($all_roles[$slug]["name"]) : "";
        $users_by_id[$u->ID] = [
          "avatar" => get_avatar_url($u->ID, ["size" => 64]),
          "role" => $role_name,
          "email" => $u->user_email,
          "editUrl" => esc_url_raw(admin_url("user-edit.php?user_id=" . $u->ID)),
        ];
      }
    }

    $items = array_map(function ($row) use ($users_by_id) {
      $uid = $row->user_id ? (int) $row->user_id : null;
      $u = $uid && isset($users_by_id[$uid]) ? $users_by_id[$uid] : null;
      return [
        "id" => (int) $row->id,
        "actionType" => $row->action_type,
        "objectType" => $row->object_type,
        "objectId" => $row->object_id ? (int) $row->object_id : null,
        "objectLabel" => $row->object_label,
        "userId" => $uid,
        "userName" => $row->user_name,
        "userAvatar" => $u ? $u["avatar"] : null,
        "userRole" => $u ? $u["role"] : "",
        "userEmail" => $u ? $u["email"] : "",
        "userEditUrl" => $u ? $u["editUrl"] : "",
        "ipAddress" => $row->ip_address,
        "details" => $row->details ? json_decode($row->details, true) : null,
        "createdAt" => $row->created_at,
      ];
    }, $rows);

    return new \WP_REST_Response([
      "items" => $items,
      "total" => $total,
      "page" => $page,
      "perPage" => $per_page,
      "totalPages" => (int) ceil($total / $per_page),
    ], 200);
  }
}
