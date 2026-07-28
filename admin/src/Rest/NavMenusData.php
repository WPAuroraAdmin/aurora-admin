<?php
namespace AuroraAdmin\Rest;


defined("ABSPATH") || exit();

/**
 * REST endpoints for Aurora's Nav Menus replacement (Appearance > Menus).
 * Not to be confused with MenuCreator/MenuCreatorData, which manages
 * Aurora's own admin sidebar — this is WordPress's front-end navigation
 * menu system (wp_nav_menu, theme locations), a completely different
 * feature that happens to share a similar name.
 *
 * Thin wrapper around core's own nav-menu functions (wp_get_nav_menus(),
 * wp_update_nav_menu_item(), etc.) — the same functions options-nav-menus.php
 * itself calls, just driven by REST instead of a giant form post.
 */
class NavMenusData
{
  public function __construct()
  {
    add_action("rest_api_init", [self::class, "register"]);
  }

  public static function check_permission()
  {
    return current_user_can("edit_theme_options");
  }

  public static function register()
  {
    register_rest_route("aurora-admin/v1", "/nav-menus", [
      [
        "methods" => "GET",
        "callback" => [self::class, "list_menus"],
        "permission_callback" => [self::class, "check_permission"],
      ],
      [
        "methods" => "POST",
        "callback" => [self::class, "create_menu"],
        "permission_callback" => [self::class, "check_permission"],
        "args" => ["name" => ["type" => "string", "required" => true]],
      ],
    ]);

    register_rest_route("aurora-admin/v1", "/nav-menus/(?P<id>\d+)", [
      "methods" => "DELETE",
      "callback" => [self::class, "delete_menu"],
      "permission_callback" => [self::class, "check_permission"],
    ]);

    register_rest_route("aurora-admin/v1", "/nav-menus/(?P<id>\d+)/items", [
      [
        "methods" => "GET",
        "callback" => [self::class, "list_items"],
        "permission_callback" => [self::class, "check_permission"],
      ],
      [
        "methods" => "POST",
        "callback" => [self::class, "add_item"],
        "permission_callback" => [self::class, "check_permission"],
      ],
    ]);

    register_rest_route("aurora-admin/v1", "/nav-menus/items/(?P<id>\d+)", [
      [
        "methods" => "PUT",
        "callback" => [self::class, "update_item"],
        "permission_callback" => [self::class, "check_permission"],
      ],
      [
        "methods" => "DELETE",
        "callback" => [self::class, "delete_item"],
        "permission_callback" => [self::class, "check_permission"],
      ],
    ]);

    register_rest_route("aurora-admin/v1", "/nav-menus/(?P<id>\d+)/reorder", [
      "methods" => "POST",
      "callback" => [self::class, "reorder_items"],
      "permission_callback" => [self::class, "check_permission"],
    ]);

    register_rest_route("aurora-admin/v1", "/nav-menus/locations", [
      [
        "methods" => "GET",
        "callback" => [self::class, "list_locations"],
        "permission_callback" => [self::class, "check_permission"],
      ],
      [
        "methods" => "POST",
        "callback" => [self::class, "assign_location"],
        "permission_callback" => [self::class, "check_permission"],
      ],
    ]);

    register_rest_route("aurora-admin/v1", "/nav-menus/pickers", [
      "methods" => "GET",
      "callback" => [self::class, "pickers"],
      "permission_callback" => [self::class, "check_permission"],
    ]);
  }

  public static function list_menus()
  {
    $menus = wp_get_nav_menus();
    $locations = get_nav_menu_locations();

    $out = [];
    foreach ($menus as $menu) {
      $assigned = array_keys($locations, $menu->term_id, true);
      $out[] = [
        "id" => (int) $menu->term_id,
        "name" => $menu->name,
        "itemCount" => (int) $menu->count,
        "locations" => $assigned,
      ];
    }

    return new \WP_REST_Response($out, 200);
  }

  public static function create_menu($request)
  {
    $name = sanitize_text_field((string) $request->get_param("name"));
    if ($name === "") {
      return new \WP_Error("aurora_menu_invalid", __("Menu name is required.", "aurora-admin"), ["status" => 400]);
    }

    $id = wp_create_nav_menu($name);
    if (is_wp_error($id)) {
      return new \WP_Error("aurora_menu_create_failed", $id->get_error_message(), ["status" => 500]);
    }

    return new \WP_REST_Response(["id" => (int) $id, "name" => $name, "itemCount" => 0, "locations" => []], 201);
  }

  public static function delete_menu($request)
  {
    $id = (int) $request["id"];
    $result = wp_delete_nav_menu($id);
    if (is_wp_error($result) || !$result) {
      return new \WP_Error("aurora_menu_delete_failed", __("Could not delete the menu.", "aurora-admin"), ["status" => 500]);
    }

    return new \WP_REST_Response(["success" => true], 200);
  }

  public static function list_items($request)
  {
    $menu_id = (int) $request["id"];
    $items = wp_get_nav_menu_items($menu_id);
    if ($items === false) {
      return new \WP_Error("aurora_menu_invalid", __("Invalid menu.", "aurora-admin"), ["status" => 404]);
    }

    return new \WP_REST_Response(array_map([self::class, "serialize_item"], $items), 200);
  }

  public static function add_item($request)
  {
    $menu_id = (int) $request["id"];
    $body = $request->get_json_params();

    $args = self::build_item_args($body);
    $item_id = wp_update_nav_menu_item($menu_id, 0, $args);
    if (is_wp_error($item_id)) {
      return new \WP_Error("aurora_menu_item_failed", $item_id->get_error_message(), ["status" => 500]);
    }

    $item = get_post($item_id);
    return new \WP_REST_Response(self::serialize_item(wp_setup_nav_menu_item($item)), 201);
  }

  public static function update_item($request)
  {
    $item_id = (int) $request["id"];
    $body = $request->get_json_params();
    $menu_id = (int) ($body["menuId"] ?? 0);

    $existing = get_post($item_id);
    if (!$existing || $existing->post_type !== "nav_menu_item") {
      return new \WP_Error("aurora_menu_item_invalid", __("Invalid menu item.", "aurora-admin"), ["status" => 404]);
    }

    $args = self::build_item_args($body, $item_id);
    $result = wp_update_nav_menu_item($menu_id ?: self::menu_id_for_item($item_id), $item_id, $args);
    if (is_wp_error($result)) {
      return new \WP_Error("aurora_menu_item_failed", $result->get_error_message(), ["status" => 500]);
    }

    return new \WP_REST_Response(self::serialize_item(wp_setup_nav_menu_item(get_post($item_id))), 200);
  }

  public static function delete_item($request)
  {
    $item_id = (int) $request["id"];
    $result = wp_delete_post($item_id, true);
    if (!$result) {
      return new \WP_Error("aurora_menu_item_delete_failed", __("Could not delete the menu item.", "aurora-admin"), ["status" => 500]);
    }

    return new \WP_REST_Response(["success" => true], 200);
  }

  // Bulk reorder / re-parent after a drag or up/down move in the UI —
  // clients send the full flat list of {id, parent, order} once, since
  // nav menu item order/hierarchy is only meaningful as a whole set.
  public static function reorder_items($request)
  {
    $menu_id = (int) $request["id"];
    $body = $request->get_json_params();
    $items = is_array($body["items"] ?? null) ? $body["items"] : [];

    foreach ($items as $item) {
      $item_id = (int) ($item["id"] ?? 0);
      if (!$item_id) {
        continue;
      }
      wp_update_nav_menu_item($menu_id, $item_id, [
        "menu-item-parent-id" => (int) ($item["parent"] ?? 0),
        "menu-item-position" => (int) ($item["order"] ?? 0),
      ]);
    }

    return new \WP_REST_Response(["success" => true], 200);
  }

  public static function list_locations()
  {
    $registered = get_registered_nav_menus();
    $assigned = get_nav_menu_locations();

    $out = [];
    foreach ($registered as $slug => $description) {
      $out[] = [
        "slug" => $slug,
        "description" => $description,
        "menuId" => isset($assigned[$slug]) ? (int) $assigned[$slug] : null,
      ];
    }

    return new \WP_REST_Response($out, 200);
  }

  public static function assign_location($request)
  {
    $body = $request->get_json_params();
    $slug = sanitize_key((string) ($body["location"] ?? ""));
    $menu_id = (int) ($body["menuId"] ?? 0);

    if ($slug === "" || !array_key_exists($slug, get_registered_nav_menus())) {
      return new \WP_Error("aurora_menu_location_invalid", __("Invalid theme location.", "aurora-admin"), ["status" => 400]);
    }

    $locations = get_theme_mod("nav_menu_locations", []);
    if ($menu_id > 0) {
      $locations[$slug] = $menu_id;
    } else {
      unset($locations[$slug]);
    }
    set_theme_mod("nav_menu_locations", $locations);

    return new \WP_REST_Response(["success" => true], 200);
  }

  // Data the "add items" panel needs: recent pages/posts/categories to
  // pick from, plus a custom-link option handled entirely client-side.
  public static function pickers()
  {
    $pages = get_posts(["post_type" => "page", "numberposts" => 50, "orderby" => "title", "order" => "ASC"]);
    $posts = get_posts(["post_type" => "post", "numberposts" => 50, "orderby" => "date", "order" => "DESC"]);
    $categories = get_categories(["hide_empty" => false, "number" => 50]);

    return new \WP_REST_Response([
      "pages" => array_map(fn($p) => ["id" => $p->ID, "title" => $p->post_title ?: __("(no title)", "aurora-admin")], $pages),
      "posts" => array_map(fn($p) => ["id" => $p->ID, "title" => $p->post_title ?: __("(no title)", "aurora-admin")], $posts),
      "categories" => array_map(fn($c) => ["id" => $c->term_id, "title" => $c->name], $categories),
    ], 200);
  }

  private static function serialize_item($item)
  {
    return [
      "id" => (int) $item->ID,
      "title" => $item->title,
      "url" => $item->url,
      "type" => $item->type, // "custom" | "post_type" | "taxonomy"
      "objectId" => (int) $item->object_id,
      "parent" => (int) $item->menu_item_parent,
      "order" => (int) $item->menu_order,
    ];
  }

  private static function build_item_args($body, $existing_id = 0)
  {
    $type = sanitize_key((string) ($body["type"] ?? "custom"));
    $title = sanitize_text_field((string) ($body["title"] ?? ""));

    $args = [
      "menu-item-title" => $title,
      "menu-item-status" => "publish",
      "menu-item-parent-id" => (int) ($body["parent"] ?? 0),
    ];

    if ($existing_id) {
      $args["menu-item-position"] = (int) ($body["order"] ?? 0);
    }

    if ($type === "custom") {
      $args["menu-item-type"] = "custom";
      $args["menu-item-url"] = esc_url_raw((string) ($body["url"] ?? "#"));
    } else {
      $object_id = (int) ($body["objectId"] ?? 0);
      $args["menu-item-object-id"] = $object_id;
      if ($type === "category") {
        $args["menu-item-type"] = "taxonomy";
        $args["menu-item-object"] = "category";
      } else {
        $args["menu-item-type"] = "post_type";
        $args["menu-item-object"] = $type === "page" ? "page" : "post";
      }
      if ($title === "") {
        $args["menu-item-title"] = get_the_title($object_id);
      }
    }

    return $args;
  }

  private static function menu_id_for_item($item_id)
  {
    $menus = wp_get_object_terms($item_id, "nav_menu");
    return !empty($menus) && !is_wp_error($menus) ? (int) $menus[0]->term_id : 0;
  }
}
