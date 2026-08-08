<?php
namespace AuroraAdmin\Rest;

defined("ABSPATH") || exit();

/**
 * REST endpoints for Aurora's custom Comments page.
 */
class CommentsData
{
  public function __construct()
  {
    add_action("rest_api_init", [self::class, "register"]);
  }

  public static function register()
  {
    register_rest_route("aurora-admin/v1", "/comments", [
      "methods" => "GET",
      "callback" => [self::class, "list_comments"],
      "permission_callback" => function () {
        return current_user_can("moderate_comments");
      },
      "args" => [
        "status" => ["type" => "string", "required" => false],
        "search" => ["type" => "string", "required" => false],
        "page" => ["type" => "integer", "required" => false],
        "perPage" => ["type" => "integer", "required" => false],
      ],
    ]);

    register_rest_route("aurora-admin/v1", "/comments/status", [
      "methods" => "POST",
      "callback" => [self::class, "update_status"],
      "permission_callback" => function () {
        return current_user_can("moderate_comments");
      },
      "args" => [
        "id" => ["type" => "integer", "required" => true],
        "status" => ["type" => "string", "required" => true],
      ],
    ]);

    register_rest_route("aurora-admin/v1", "/comments/(?P<id>\d+)", [
      "methods" => "DELETE",
      "callback" => [self::class, "delete_comment"],
      "permission_callback" => function () {
        return current_user_can("moderate_comments");
      },
      "args" => [
        "id" => ["type" => "integer", "required" => true],
      ],
    ]);
  }

  public static function list_comments($request)
  {
    $status = self::query_status($request->get_param("status"));
    $page = max(1, (int) ($request->get_param("page") ?: 1));
    $per_page = min(100, max(10, (int) ($request->get_param("perPage") ?: 50)));
    $search = sanitize_text_field((string) $request->get_param("search"));

    $args = [
      "status" => $status,
      "number" => $per_page,
      "offset" => ($page - 1) * $per_page,
      "orderby" => "comment_date_gmt",
      "order" => "DESC",
      "type" => "comment",
    ];

    if ($search !== "") {
      $args["search"] = $search;
    }

    $query = new \WP_Comment_Query();
    $comments = $query->query($args);
    $total = (int) $query->found_comments;

    return new \WP_REST_Response([
      "items" => array_map([self::class, "serialize_comment"], $comments),
      "stats" => self::stats(),
      "pagination" => [
        "page" => $page,
        "perPage" => $per_page,
        "total" => $total,
        "totalPages" => max(1, (int) ceil($total / $per_page)),
      ],
    ], 200);
  }

  public static function update_status($request)
  {
    $id = (int) $request->get_param("id");
    $status = self::action_status($request->get_param("status"));

    if (!$id || !$status || !get_comment($id)) {
      return new \WP_Error("aurora_comment_status_invalid", __("Invalid comment action.", "aurora-admin"), ["status" => 400]);
    }

    $result = wp_set_comment_status($id, $status);
    if (!$result) {
      return new \WP_Error("aurora_comment_status_failed", __("Could not update the comment.", "aurora-admin"), ["status" => 500]);
    }

    return new \WP_REST_Response(["success" => true, "message" => __("Comment updated.", "aurora-admin")], 200);
  }

  public static function delete_comment($request)
  {
    $id = (int) $request->get_param("id");
    if (!$id || !get_comment($id)) {
      return new \WP_Error("aurora_comment_delete_invalid", __("Invalid comment.", "aurora-admin"), ["status" => 400]);
    }

    $result = wp_delete_comment($id, true);
    if (!$result) {
      return new \WP_Error("aurora_comment_delete_failed", __("Could not delete the comment.", "aurora-admin"), ["status" => 500]);
    }

    return new \WP_REST_Response(["success" => true, "message" => __("Comment deleted.", "aurora-admin")], 200);
  }

  private static function serialize_comment($comment)
  {
    $post_id = (int) $comment->comment_post_ID;
    $status = wp_get_comment_status($comment);

    return [
      "id" => (int) $comment->comment_ID,
      "author" => $comment->comment_author ?: __("Anonymous", "aurora-admin"),
      "email" => $comment->comment_author_email,
      "avatar" => get_avatar_url($comment, ["size" => 64]),
      "content" => wp_trim_words(wp_strip_all_tags($comment->comment_content), 28),
      "rawContent" => $comment->comment_content,
      "postId" => $post_id,
      "postTitle" => get_the_title($post_id) ?: __("(No title)", "aurora-admin"),
      "postEditUrl" => get_edit_post_link($post_id, "raw") ?: "",
      "date" => mysql2date("M j, Y", $comment->comment_date),
      // translators: %s is a human-readable relative time (e.g. "3 hours", "2 days").
      "relativeDate" => sprintf(__("%s ago", "aurora-admin"), human_time_diff(strtotime($comment->comment_date_gmt), current_time("timestamp", true))),
      "status" => $status,
      "statusLabel" => self::status_label($status),
      "editUrl" => admin_url("comment.php?action=editcomment&c=" . (int) $comment->comment_ID),
      "canEdit" => current_user_can("edit_comment", (int) $comment->comment_ID),
    ];
  }

  private static function stats()
  {
    $counts = wp_count_comments();
    $approved = isset($counts->approved) ? (int) $counts->approved : 0;
    $pending = isset($counts->moderated) ? (int) $counts->moderated : 0;
    $spam = isset($counts->spam) ? (int) $counts->spam : 0;
    $trash = isset($counts->trash) ? (int) $counts->trash : 0;

    return [
      "all" => $approved + $pending + $spam,
      "approved" => $approved,
      "pending" => $pending,
      "spam" => $spam,
      "trash" => $trash,
    ];
  }

  private static function query_status($status)
  {
    switch ($status) {
      case "approved":
        return "approve";
      case "pending":
        return "hold";
      case "spam":
      case "trash":
        return $status;
      default:
        return "all";
    }
  }

  private static function action_status($status)
  {
    switch ($status) {
      case "approve":
      case "hold":
      case "spam":
      case "trash":
        return $status;
      default:
        return "";
    }
  }

  private static function status_label($status)
  {
    switch ($status) {
      case "approved":
        return __("Approved", "aurora-admin");
      case "unapproved":
        return __("Pending", "aurora-admin");
      case "spam":
        return __("Spam", "aurora-admin");
      case "trash":
        return __("Trash", "aurora-admin");
      default:
        return ucfirst($status);
    }
  }
}
