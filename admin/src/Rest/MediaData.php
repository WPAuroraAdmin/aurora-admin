<?php
namespace AuroraAdmin\Rest;

use AuroraAdmin\Media\MediaFolders;

defined("ABSPATH") || exit();

/**
 * REST endpoints for Aurora's custom Media Library page — same list-table
 * pattern as Posts/Pages/Comments. Deliberately doesn't rebuild the
 * upload flow (drag-drop, chunked uploads, the grid picker used inside
 * post editors) — "Add Media File" still opens the native media-new.php,
 * same way Posts'/Pages' "Add" buttons still open the native, already
 * themed block editor. This replaces the *library/browse* experience,
 * not uploading.
 */
class MediaData
{
  public function __construct()
  {
    add_action("rest_api_init", [self::class, "register"]);
  }

  public static function register()
  {
    register_rest_route("aurora-admin/v1", "/media", [
      "methods" => "GET",
      "callback" => [self::class, "list_media"],
      "permission_callback" => function () {
        return current_user_can("upload_files");
      },
      "args" => [
        "type" => ["type" => "string", "required" => false],
        "search" => ["type" => "string", "required" => false],
        "page" => ["type" => "integer", "required" => false],
        "perPage" => ["type" => "integer", "required" => false],
        "folderId" => ["type" => "integer", "required" => false],
      ],
    ]);

    register_rest_route("aurora-admin/v1", "/media/(?P<id>\d+)", [
      "methods" => "DELETE",
      "callback" => [self::class, "delete_media"],
      "permission_callback" => [self::class, "can_delete_route_media"],
      "args" => [
        "id" => ["type" => "integer", "required" => true],
      ],
    ]);
  }

  public static function can_delete_route_media($request)
  {
    $id = (int) $request["id"];
    return $id && current_user_can("delete_post", $id);
  }

  public static function list_media($request)
  {
    $type = self::query_mime_type($request->get_param("type"));
    $page = max(1, (int) ($request->get_param("page") ?: 1));
    $per_page = min(100, max(10, (int) ($request->get_param("perPage") ?: 24)));
    $search = sanitize_text_field((string) $request->get_param("search"));

    $args = [
      "post_type" => "attachment",
      "post_status" => "inherit",
      "posts_per_page" => $per_page,
      "paged" => $page,
      "orderby" => "date",
      "order" => "DESC",
    ];

    if ($type === "unattached") {
      $args["post_parent"] = 0;
    } elseif ($type !== "") {
      $args["post_mime_type"] = $type;
    }

    if ($search !== "") {
      $args["s"] = $search;
    }

    $folder_param = $request->get_param("folderId");
    if ($folder_param !== null && $folder_param !== "") {
      $folder_id = (int) $folder_param;
      if ($folder_id === MediaFolders::UNCATEGORIZED) {
        $in_any_folder = MediaFolders::all_assigned_attachment_ids();
        // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- a WordPress VIP-scale performance advisory, not a WP.org Plugin Directory requirement; "show media not assigned to any folder" has no other query shape without a real inverse-membership index, and Media Folders is bounded by how many attachments a site actually has, not VIP-scale traffic.
        $args["post__not_in"] = !empty($in_any_folder) ? $in_any_folder : [0];
      } elseif ($folder_id !== MediaFolders::ALL_MEDIA) {
        $ids = MediaFolders::attachment_ids_in_folder($folder_id);
        $args["post__in"] = !empty($ids) ? $ids : [0];
      }
    }

    $query = new \WP_Query($args);

    $folder_map = MediaFolders::folder_ids_for_attachments(wp_list_pluck($query->posts, "ID"));

    return new \WP_REST_Response([
      "items" => array_map(
        function ($post) use ($folder_map) {
          return self::serialize_attachment($post, $folder_map[$post->ID] ?? MediaFolders::UNCATEGORIZED);
        },
        $query->posts
      ),
      "stats" => self::stats(),
      "pagination" => [
        "page" => $page,
        "perPage" => $per_page,
        "total" => (int) $query->found_posts,
        "totalPages" => max(1, (int) $query->max_num_pages),
      ],
    ], 200);
  }

  public static function delete_media($request)
  {
    $id = (int) $request["id"];
    $post = get_post($id);
    if (!$post || $post->post_type !== "attachment") {
      return new \WP_Error("aurora_media_invalid", __("Invalid media item.", "aurora-admin"), ["status" => 400]);
    }

    $result = wp_delete_attachment($id, true);
    if (!$result) {
      return new \WP_Error("aurora_media_delete_failed", __("Could not delete the media item.", "aurora-admin"), ["status" => 500]);
    }

    return new \WP_REST_Response(["success" => true, "message" => __("Media item deleted.", "aurora-admin")], 200);
  }

  private static function serialize_attachment($post, $folder_id = null)
  {
    $author = get_userdata($post->post_author);
    $mime = $post->post_mime_type;
    $is_image = wp_attachment_is_image($post->ID);
    $meta = wp_get_attachment_metadata($post->ID);
    $file_path = get_attached_file($post->ID);

    $parent_title = "";
    $parent_edit_url = "";
    if ($post->post_parent) {
      $parent_title = get_the_title($post->post_parent);
      $parent_edit_url = get_edit_post_link($post->post_parent, "raw") ?: "";
    }

    return [
      "id" => (int) $post->ID,
      "title" => get_the_title($post) ?: basename($file_path),
      "filename" => basename($file_path),
      "mimeType" => $mime,
      "typeLabel" => self::type_label($mime),
      "isImage" => $is_image,
      "thumbnailUrl" => $is_image ? (wp_get_attachment_image_url($post->ID, "thumbnail") ?: "") : "",
      "fileUrl" => wp_get_attachment_url($post->ID) ?: "",
      "dimensions" => $is_image && !empty($meta["width"]) ? "{$meta['width']} × {$meta['height']}" : "",
      "fileSize" => !empty($meta["filesize"]) ? size_format($meta["filesize"]) : "",
      "author" => $author ? $author->display_name : __("Unknown", "aurora-admin"),
      "attachedToTitle" => $parent_title,
      "attachedToEditUrl" => $parent_edit_url,
      "date" => get_the_date("M j, Y", $post),
      "editUrl" => get_edit_post_link($post->ID, "raw") ?: "",
      "canDelete" => current_user_can("delete_post", $post->ID),
      "folderId" => $folder_id,
    ];
  }

  private static function stats()
  {
    global $wpdb;

    $counts = [
      "all" => (int) wp_count_posts("attachment")->inherit,
      "image" => 0,
      "audio" => 0,
      "video" => 0,
      "application" => 0,
      "unattached" => 0,
    ];

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- {$wpdb->posts} is WordPress core's own table, no user-supplied value in the query; there's no WP_Query/get_posts() shape for "counts grouped by mime-type prefix", which is exactly what this powers (the Media Library's type-filter tab counts).
    $rows = $wpdb->get_results(
      "SELECT post_mime_type, COUNT(*) AS c FROM {$wpdb->posts}
       WHERE post_type = 'attachment' AND post_status = 'inherit'
       GROUP BY post_mime_type"
    );
    foreach ($rows as $row) {
      $group = strtok((string) $row->post_mime_type, "/");
      if (isset($counts[$group])) {
        $counts[$group] += (int) $row->c;
      } else {
        $counts["application"] += (int) $row->c;
      }
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- {$wpdb->posts} is WordPress core's own table, no user-supplied value in the query.
    $counts["unattached"] = (int) $wpdb->get_var(
      "SELECT COUNT(*) FROM {$wpdb->posts}
       WHERE post_type = 'attachment' AND post_status = 'inherit' AND post_parent = 0"
    );

    return $counts;
  }

  private static function query_mime_type($type)
  {
    switch ($type) {
      case "image":
      case "audio":
      case "video":
        return $type;
      case "application":
        return "application";
      case "unattached":
        return "unattached";
      case "all":
      default:
        return "";
    }
  }

  private static function type_label($mime)
  {
    $group = strtok((string) $mime, "/");
    switch ($group) {
      case "image":
        return __("Image", "aurora-admin");
      case "audio":
        return __("Audio", "aurora-admin");
      case "video":
        return __("Video", "aurora-admin");
      default:
        return __("Document", "aurora-admin");
    }
  }
}
