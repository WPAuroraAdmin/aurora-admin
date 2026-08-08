<?php
namespace AuroraAdmin\Rest;

defined("ABSPATH") || exit();

/**
 * REST endpoints for Aurora's custom Pages page — same pattern as
 * PostsData.php, adapted for the "page" post type (no categories, but
 * shows the parent page instead).
 */
class PagesData
{
  public function __construct()
  {
    add_action("rest_api_init", [self::class, "register"]);
  }

  public static function register()
  {
    register_rest_route("aurora-admin/v1", "/pages", [
      "methods" => "GET",
      "callback" => [self::class, "list_pages"],
      "permission_callback" => function () {
        return current_user_can("edit_pages");
      },
      "args" => [
        "status" => ["type" => "string", "required" => false],
        "search" => ["type" => "string", "required" => false],
        "page" => ["type" => "integer", "required" => false],
        "perPage" => ["type" => "integer", "required" => false],
      ],
    ]);

    register_rest_route("aurora-admin/v1", "/pages/trash", [
      "methods" => "POST",
      "callback" => [self::class, "trash_page"],
      "permission_callback" => [self::class, "can_delete_param_page"],
      "args" => [
        "id" => ["type" => "integer", "required" => true],
      ],
    ]);

    register_rest_route("aurora-admin/v1", "/pages/restore", [
      "methods" => "POST",
      "callback" => [self::class, "restore_page"],
      "permission_callback" => [self::class, "can_delete_param_page"],
      "args" => [
        "id" => ["type" => "integer", "required" => true],
      ],
    ]);

    register_rest_route("aurora-admin/v1", "/pages/(?P<id>\d+)", [
      "methods" => "DELETE",
      "callback" => [self::class, "delete_page"],
      "permission_callback" => [self::class, "can_delete_route_page"],
      "args" => [
        "id" => ["type" => "integer", "required" => true],
      ],
    ]);
  }

  // Trashing/restoring is a deletion-adjacent action (undoing it returns the
  // page to circulation), so it's gated the same as permanent delete rather
  // than plain edit — matches core's own trash/untrash capability check.
  public static function can_delete_param_page($request)
  {
    $id = (int) $request->get_param("id");
    return $id && current_user_can("delete_page", $id);
  }

  public static function can_delete_route_page($request)
  {
    $id = (int) $request["id"];
    return $id && current_user_can("delete_page", $id);
  }

  public static function list_pages($request)
  {
    $status = self::query_status($request->get_param("status"));
    $page = max(1, (int) ($request->get_param("page") ?: 1));
    $per_page = min(100, max(10, (int) ($request->get_param("perPage") ?: 20)));
    $search = sanitize_text_field((string) $request->get_param("search"));

    $args = [
      "post_type" => "page",
      "post_status" => $status,
      "posts_per_page" => $per_page,
      "paged" => $page,
      "orderby" => "title",
      "order" => "ASC",
      "ignore_sticky_posts" => true,
    ];

    if ($search !== "") {
      $args["s"] = $search;
    }

    $query = new \WP_Query($args);

    return new \WP_REST_Response([
      "items" => array_map([self::class, "serialize_page"], $query->posts),
      "stats" => self::stats(),
      "pagination" => [
        "page" => $page,
        "perPage" => $per_page,
        "total" => (int) $query->found_posts,
        "totalPages" => max(1, (int) $query->max_num_pages),
      ],
    ], 200);
  }

  public static function trash_page($request)
  {
    $id = (int) $request->get_param("id");
    $post = get_post($id);
    if (!$post || $post->post_type !== "page") {
      return new \WP_Error("aurora_page_invalid", __("Invalid page.", "aurora-admin"), ["status" => 400]);
    }

    $result = wp_trash_post($id);
    if (!$result) {
      return new \WP_Error("aurora_page_trash_failed", __("Could not trash the page.", "aurora-admin"), ["status" => 500]);
    }

    return new \WP_REST_Response(["success" => true, "message" => __("Page moved to trash.", "aurora-admin")], 200);
  }

  public static function restore_page($request)
  {
    $id = (int) $request->get_param("id");
    $post = get_post($id);
    if (!$post || $post->post_type !== "page") {
      return new \WP_Error("aurora_page_invalid", __("Invalid page.", "aurora-admin"), ["status" => 400]);
    }

    $result = wp_untrash_post($id);
    if (!$result) {
      return new \WP_Error("aurora_page_restore_failed", __("Could not restore the page.", "aurora-admin"), ["status" => 500]);
    }

    return new \WP_REST_Response(["success" => true, "message" => __("Page restored.", "aurora-admin")], 200);
  }

  public static function delete_page($request)
  {
    $id = (int) $request["id"];
    $post = get_post($id);
    if (!$post || $post->post_type !== "page") {
      return new \WP_Error("aurora_page_invalid", __("Invalid page.", "aurora-admin"), ["status" => 400]);
    }

    $result = wp_delete_post($id, true);
    if (!$result) {
      return new \WP_Error("aurora_page_delete_failed", __("Could not delete the page.", "aurora-admin"), ["status" => 500]);
    }

    return new \WP_REST_Response(["success" => true, "message" => __("Page deleted.", "aurora-admin")], 200);
  }

  private static function serialize_page($post)
  {
    $author = get_userdata($post->post_author);
    $status = $post->post_status;
    $parent_title = $post->post_parent ? get_the_title($post->post_parent) : "";

    return [
      "id" => (int) $post->ID,
      "title" => get_the_title($post) ?: __("(No title)", "aurora-admin"),
      "author" => $author ? $author->display_name : __("Unknown", "aurora-admin"),
      "parentTitle" => $parent_title,
      "commentCount" => (int) $post->comment_count,
      "date" => get_the_date("M j, Y", $post),
      "dateLabel" =>
        $status === "publish"
          ? __("Published", "aurora-admin")
          : ($status === "future"
            ? __("Scheduled", "aurora-admin")
            : __("Last Modified", "aurora-admin")),
      "status" => $status,
      "statusLabel" => self::status_label($status),
      "editUrl" => get_edit_post_link($post->ID, "raw") ?: "",
      "viewUrl" => $status === "publish" ? get_permalink($post) : get_preview_post_link($post),
      "canDelete" => current_user_can("delete_page", $post->ID),
    ];
  }

  private static function stats()
  {
    $counts = wp_count_posts("page");

    return [
      "all" => (int) $counts->publish + (int) $counts->draft + (int) $counts->pending + (int) $counts->private + (int) $counts->future,
      "publish" => (int) $counts->publish,
      "draft" => (int) $counts->draft,
      "pending" => (int) $counts->pending,
      "trash" => (int) ($counts->trash ?? 0),
    ];
  }

  private static function query_status($status)
  {
    switch ($status) {
      case "publish":
      case "draft":
      case "pending":
      case "trash":
        return $status;
      case "all":
      default:
        return ["publish", "draft", "pending", "private", "future"];
    }
  }

  private static function status_label($status)
  {
    switch ($status) {
      case "publish":
        return __("Published", "aurora-admin");
      case "draft":
        return __("Draft", "aurora-admin");
      case "pending":
        return __("Pending", "aurora-admin");
      case "future":
        return __("Scheduled", "aurora-admin");
      case "private":
        return __("Private", "aurora-admin");
      case "trash":
        return __("Trash", "aurora-admin");
      default:
        return ucfirst($status);
    }
  }
}
