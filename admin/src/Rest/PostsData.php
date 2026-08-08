<?php
namespace AuroraAdmin\Rest;

defined("ABSPATH") || exit();

/**
 * REST endpoints for Aurora's custom Posts page — a real replacement for
 * the native edit.php list table (unlike Settings > Posts' old "Modern
 * posts table" toggle, which described this but was never actually built;
 * that toggle was removed once this landed).
 */
class PostsData
{
  public function __construct()
  {
    add_action("rest_api_init", [self::class, "register"]);
  }

  public static function register()
  {
    register_rest_route("aurora-admin/v1", "/posts", [
      "methods" => "GET",
      "callback" => [self::class, "list_posts"],
      "permission_callback" => function () {
        return current_user_can("edit_posts");
      },
      "args" => [
        "status" => ["type" => "string", "required" => false],
        "search" => ["type" => "string", "required" => false],
        "page" => ["type" => "integer", "required" => false],
        "perPage" => ["type" => "integer", "required" => false],
      ],
    ]);

    register_rest_route("aurora-admin/v1", "/posts/trash", [
      "methods" => "POST",
      "callback" => [self::class, "trash_post"],
      "permission_callback" => [self::class, "can_edit_param_post"],
      "args" => [
        "id" => ["type" => "integer", "required" => true],
      ],
    ]);

    register_rest_route("aurora-admin/v1", "/posts/restore", [
      "methods" => "POST",
      "callback" => [self::class, "restore_post"],
      "permission_callback" => [self::class, "can_edit_param_post"],
      "args" => [
        "id" => ["type" => "integer", "required" => true],
      ],
    ]);

    register_rest_route("aurora-admin/v1", "/posts/(?P<id>\d+)", [
      "methods" => "DELETE",
      "callback" => [self::class, "delete_post"],
      "permission_callback" => [self::class, "can_delete_route_post"],
      "args" => [
        "id" => ["type" => "integer", "required" => true],
      ],
    ]);
  }

  public static function can_edit_param_post($request)
  {
    $id = (int) $request->get_param("id");
    return $id && current_user_can("edit_post", $id);
  }

  public static function can_delete_route_post($request)
  {
    $id = (int) $request["id"];
    return $id && current_user_can("delete_post", $id);
  }

  public static function list_posts($request)
  {
    $status = self::query_status($request->get_param("status"));
    $page = max(1, (int) ($request->get_param("page") ?: 1));
    $per_page = min(100, max(10, (int) ($request->get_param("perPage") ?: 20)));
    $search = sanitize_text_field((string) $request->get_param("search"));

    $args = [
      "post_type" => "post",
      "post_status" => $status,
      "posts_per_page" => $per_page,
      "paged" => $page,
      "orderby" => "date",
      "order" => "DESC",
      "ignore_sticky_posts" => true,
    ];

    if ($search !== "") {
      $args["s"] = $search;
    }

    $query = new \WP_Query($args);

    return new \WP_REST_Response([
      "items" => array_map([self::class, "serialize_post"], $query->posts),
      "stats" => self::stats(),
      "pagination" => [
        "page" => $page,
        "perPage" => $per_page,
        "total" => (int) $query->found_posts,
        "totalPages" => max(1, (int) $query->max_num_pages),
      ],
    ], 200);
  }

  public static function trash_post($request)
  {
    $id = (int) $request->get_param("id");
    $post = get_post($id);
    if (!$post || $post->post_type !== "post") {
      return new \WP_Error("aurora_post_invalid", __("Invalid post.", "aurora-admin"), ["status" => 400]);
    }

    $result = wp_trash_post($id);
    if (!$result) {
      return new \WP_Error("aurora_post_trash_failed", __("Could not trash the post.", "aurora-admin"), ["status" => 500]);
    }

    return new \WP_REST_Response(["success" => true, "message" => __("Post moved to trash.", "aurora-admin")], 200);
  }

  public static function restore_post($request)
  {
    $id = (int) $request->get_param("id");
    $post = get_post($id);
    if (!$post || $post->post_type !== "post") {
      return new \WP_Error("aurora_post_invalid", __("Invalid post.", "aurora-admin"), ["status" => 400]);
    }

    $result = wp_untrash_post($id);
    if (!$result) {
      return new \WP_Error("aurora_post_restore_failed", __("Could not restore the post.", "aurora-admin"), ["status" => 500]);
    }

    return new \WP_REST_Response(["success" => true, "message" => __("Post restored.", "aurora-admin")], 200);
  }

  public static function delete_post($request)
  {
    $id = (int) $request["id"];
    $post = get_post($id);
    if (!$post || $post->post_type !== "post") {
      return new \WP_Error("aurora_post_invalid", __("Invalid post.", "aurora-admin"), ["status" => 400]);
    }

    $result = wp_delete_post($id, true);
    if (!$result) {
      return new \WP_Error("aurora_post_delete_failed", __("Could not delete the post.", "aurora-admin"), ["status" => 500]);
    }

    return new \WP_REST_Response(["success" => true, "message" => __("Post deleted.", "aurora-admin")], 200);
  }

  private static function serialize_post($post)
  {
    $author = get_userdata($post->post_author);
    $categories = wp_get_post_categories($post->ID, ["fields" => "names"]);
    $status = $post->post_status;

    return [
      "id" => (int) $post->ID,
      "title" => get_the_title($post) ?: __("(No title)", "aurora-admin"),
      "author" => $author ? $author->display_name : __("Unknown", "aurora-admin"),
      "categories" => is_array($categories) ? $categories : [],
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
      "canDelete" => current_user_can("delete_post", $post->ID),
    ];
  }

  private static function stats()
  {
    $counts = wp_count_posts("post");

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
