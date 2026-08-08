<?php
namespace AuroraAdmin\Rest;

use AuroraAdmin\Media\MediaFolders;

defined("ABSPATH") || exit();

/**
 * REST endpoints for Media Folders.
 */
class MediaFolderData
{
  public function __construct()
  {
    add_action("rest_api_init", [self::class, "register"]);
  }

  public static function register()
  {
    register_rest_route("aurora-admin/v1", "/media-folders", [
      "methods" => "GET",
      "callback" => [self::class, "get_tree"],
      "permission_callback" => [self::class, "can_manage"],
    ]);

    register_rest_route("aurora-admin/v1", "/media-folders", [
      "methods" => "POST",
      "callback" => [self::class, "create_folder"],
      "permission_callback" => [self::class, "can_manage"],
      "args" => [
        "name" => ["type" => "string", "required" => true],
        "parent" => ["type" => "integer", "required" => false],
      ],
    ]);

    register_rest_route("aurora-admin/v1", "/media-folders/(?P<id>\d+)", [
      "methods" => "POST",
      "callback" => [self::class, "update_folder"],
      "permission_callback" => [self::class, "can_manage"],
      "args" => [
        "id" => ["type" => "integer", "required" => true],
        "name" => ["type" => "string", "required" => false],
        "parent" => ["type" => "integer", "required" => false],
        "siblingOrder" => ["type" => "array", "required" => false],
      ],
    ]);

    register_rest_route("aurora-admin/v1", "/media-folders/(?P<id>\d+)", [
      "methods" => "DELETE",
      "callback" => [self::class, "delete_folder"],
      "permission_callback" => [self::class, "can_manage"],
      "args" => [
        "id" => ["type" => "integer", "required" => true],
      ],
    ]);

    register_rest_route("aurora-admin/v1", "/media-folders/assign", [
      "methods" => "POST",
      "callback" => [self::class, "assign"],
      "permission_callback" => [self::class, "can_manage"],
      "args" => [
        "folderId" => ["type" => "integer", "required" => true],
        "attachmentIds" => ["type" => "array", "required" => true],
      ],
    ]);
  }

  public static function can_manage()
  {
    return current_user_can("upload_files");
  }

  public static function get_tree($request)
  {
    return new \WP_REST_Response(
      [
        "tree" => MediaFolders::get_tree(),
        "allMediaCount" => MediaFolders::total_attachment_count(),
        "uncategorizedCount" => MediaFolders::uncategorized_count(),
      ],
      200
    );
  }

  public static function create_folder($request)
  {
    $name = sanitize_text_field((string) $request->get_param("name"));
    $parent = (int) ($request->get_param("parent") ?: 0);

    $id = MediaFolders::create_folder($name, $parent);
    if (!$id) {
      return new \WP_Error("aurora_folder_invalid", __("Folder name is required.", "aurora-admin"), ["status" => 400]);
    }

    return new \WP_REST_Response(["id" => $id], 201);
  }

  public static function update_folder($request)
  {
    $id = (int) $request["id"];
    $name = $request->get_param("name");
    $parent = $request->get_param("parent");

    if ($name !== null && $name !== "") {
      MediaFolders::rename_folder($id, sanitize_text_field((string) $name));
    }

    if ($parent !== null) {
      $sibling_order = (array) ($request->get_param("siblingOrder") ?: []);
      $moved = MediaFolders::move_folder($id, (int) $parent, $sibling_order);
      if (!$moved) {
        return new \WP_Error(
          "aurora_folder_move_failed",
          __("Could not move that folder there.", "aurora-admin"),
          ["status" => 400]
        );
      }
    }

    return new \WP_REST_Response(["success" => true], 200);
  }

  public static function delete_folder($request)
  {
    $id = (int) $request["id"];
    MediaFolders::delete_folder($id);
    return new \WP_REST_Response(["success" => true], 200);
  }

  public static function assign($request)
  {
    $folder_id = (int) $request->get_param("folderId");
    $ids = (array) $request->get_param("attachmentIds");

    $ids = array_values(
      array_filter($ids, function ($id) {
        return current_user_can("edit_post", (int) $id);
      })
    );

    if (empty($ids)) {
      return new \WP_Error(
        "aurora_folder_assign_failed",
        __("There is nothing to move.", "aurora-admin"),
        ["status" => 400]
      );
    }

    MediaFolders::assign_attachments($folder_id, $ids);
    return new \WP_REST_Response(["success" => true], 200);
  }
}
