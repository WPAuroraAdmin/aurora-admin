<?php
namespace AuroraAdmin\Rest;


defined("ABSPATH") || exit();

/**
 * REST endpoints for a deliberately simplified Widgets replacement:
 * list registered sidebars (+ the "Inactive Widgets" pseudo-sidebar core
 * itself uses to park deactivated instances), move an existing widget
 * instance between sidebars, reorder within a sidebar, and remove an
 * already-inactive instance entirely.
 *
 * What's NOT built here, deliberately: adding a brand-new instance of a
 * widget type. Each widget class defines its own default instance settings
 * and its own settings form — replicating that generically for every
 * registered widget (core's + every theme/plugin's) is a full block-widget
 * editor's worth of scope on its own, the same "don't rebuild the real
 * editor" call made for the block editor and full drag-and-drop widget
 * blocks. Users can still create a new instance via the native block
 * widgets screen if this toggle is off; this covers reorganizing what's
 * already there.
 */
class WidgetsData
{
  const INACTIVE_ID = "wp_inactive_widgets";

  public function __construct()
  {
    add_action("rest_api_init", [self::class, "register"]);
  }

  public static function check_permission()
  {
    return current_user_can("edit_theme_options");
  }

  /**
   * wp_get_sidebars_widgets() is marked for internal core use only, so we
   * read the same option it wraps directly. Unlike core's version this
   * doesn't auto-assign never-placed widgets to a default sidebar — an
   * edge case acceptable to skip given this is a deliberately simplified
   * replacement (see class docblock).
   */
  private static function get_sidebars_widgets()
  {
    $assignments = get_option("sidebars_widgets", []);
    unset($assignments["array_version"]);
    return $assignments;
  }

  public static function register()
  {
    register_rest_route("aurora-admin/v1", "/widgets", [
      "methods" => "GET",
      "callback" => [self::class, "list_widgets"],
      "permission_callback" => [self::class, "check_permission"],
    ]);

    register_rest_route("aurora-admin/v1", "/widgets/move", [
      "methods" => "POST",
      "callback" => [self::class, "move_widget"],
      "permission_callback" => [self::class, "check_permission"],
    ]);

    register_rest_route("aurora-admin/v1", "/widgets/(?P<id>[^/]+)", [
      "methods" => "DELETE",
      "callback" => [self::class, "delete_widget"],
      "permission_callback" => [self::class, "check_permission"],
    ]);
  }

  public static function list_widgets()
  {
    global $wp_registered_sidebars, $wp_registered_widgets;

    $assignments = self::get_sidebars_widgets();

    $sidebars = [];
    foreach ($wp_registered_sidebars as $id => $sidebar) {
      $sidebars[] = [
        "id" => $id,
        "name" => $sidebar["name"],
        "widgets" => self::serialize_widget_list($assignments[$id] ?? []),
      ];
    }

    $sidebars[] = [
      "id" => self::INACTIVE_ID,
      "name" => __("Inactive Widgets", "aurora-admin"),
      "widgets" => self::serialize_widget_list($assignments[self::INACTIVE_ID] ?? []),
    ];

    return new \WP_REST_Response(["sidebars" => $sidebars], 200);
  }

  private static function serialize_widget_list($widget_ids)
  {
    global $wp_registered_widgets;

    $out = [];
    foreach (array_values($widget_ids) as $order => $widget_id) {
      if (!preg_match('/^(.+)-(\d+)$/', $widget_id, $m)) {
        // Some legacy widgets aren't multi-instance and use a bare id.
        $id_base = $widget_id;
        $number = null;
      } else {
        $id_base = $m[1];
        $number = (int) $m[2];
      }

      $title = "";
      if ($number !== null) {
        $instance_option = get_option("widget_{$id_base}");
        if (is_array($instance_option) && isset($instance_option[$number]["title"])) {
          $title = (string) $instance_option[$number]["title"];
        }
      }

      $out[] = [
        "id" => $widget_id,
        "idBase" => $id_base,
        "name" => isset($wp_registered_widgets[$widget_id]["name"])
          ? $wp_registered_widgets[$widget_id]["name"]
          : ucwords(str_replace(["-", "_"], " ", $id_base)),
        "title" => $title,
        "order" => $order,
      ];
    }

    return $out;
  }

  public static function move_widget($request)
  {
    $body = $request->get_json_params();
    $widget_id = sanitize_text_field((string) ($body["widgetId"] ?? ""));
    $from = sanitize_key((string) ($body["fromSidebar"] ?? ""));
    $to = sanitize_key((string) ($body["toSidebar"] ?? ""));
    $order = (int) ($body["order"] ?? -1);

    if ($widget_id === "" || $to === "") {
      return new \WP_Error("aurora_widget_invalid", __("Invalid widget move request.", "aurora-admin"), ["status" => 400]);
    }

    $assignments = self::get_sidebars_widgets();
    if (!isset($assignments[$to])) {
      $assignments[$to] = [];
    }

    if ($from !== "" && isset($assignments[$from])) {
      $assignments[$from] = array_values(array_diff($assignments[$from], [$widget_id]));
    }

    $assignments[$to] = array_values(array_diff($assignments[$to], [$widget_id]));
    if ($order < 0 || $order >= count($assignments[$to])) {
      $assignments[$to][] = $widget_id;
    } else {
      array_splice($assignments[$to], $order, 0, [$widget_id]);
    }

    wp_set_sidebars_widgets($assignments);

    return new \WP_REST_Response(["success" => true], 200);
  }

  public static function delete_widget($request)
  {
    $widget_id = sanitize_text_field((string) $request["id"]);

    $assignments = self::get_sidebars_widgets();
    $in_inactive = in_array($widget_id, $assignments[self::INACTIVE_ID] ?? [], true);
    if (!$in_inactive) {
      return new \WP_Error(
        "aurora_widget_delete_requires_inactive",
        __("Move the widget to Inactive Widgets before deleting it.", "aurora-admin"),
        ["status" => 400]
      );
    }

    $assignments[self::INACTIVE_ID] = array_values(array_diff($assignments[self::INACTIVE_ID], [$widget_id]));
    wp_set_sidebars_widgets($assignments);

    if (preg_match('/^(.+)-(\d+)$/', $widget_id, $m)) {
      $id_base = $m[1];
      $number = (int) $m[2];
      $instances = get_option("widget_{$id_base}");
      if (is_array($instances) && isset($instances[$number])) {
        unset($instances[$number]);
        update_option("widget_{$id_base}", $instances);
      }
    }

    return new \WP_REST_Response(["success" => true], 200);
  }
}
