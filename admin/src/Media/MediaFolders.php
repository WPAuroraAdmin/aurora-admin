<?php
namespace AuroraAdmin\Media;

defined("ABSPATH") || exit();

/**
 * Storage + model for Media Library folders — duplicates the organizing
 * capability of the FileBird Pro plugin, at the user's request, with its
 * own independent schema rather than reusing FileBird's tables.
 *
 * Deliberately simpler than FileBird's own model in two ways:
 *  - No folder "type" (folder vs. collection/saved-search) — collections
 *    add no organizational value over folders, skipped unless asked for.
 *  - One folder per attachment, enforced here (assign_attachments() always
 *    removes any existing membership row first) — avoids the ambiguity of
 *    multi-folder membership in the UI, even though the join table's shape
 *    would technically allow it.
 *
 * REST surface lives in MediaFolderData.php; this class only owns storage.
 */
class MediaFolders
{
  const TABLE_FOLDERS = "aurora_media_folders";
  const TABLE_ITEMS = "aurora_media_folder_items";
  const DB_VERSION = "1";
  const DB_OPTION = "aurora_admin_media_folders_db_version";

  // Pseudo-folder ids the UI always shows above the real tree. Never
  // written to the folders table — resolved specially wherever a folder id
  // is accepted (get_tree(), MediaData::list_media()).
  const ALL_MEDIA = 0;
  const UNCATEGORIZED = -1;

  public function __construct()
  {
    add_action("init", [self::class, "maybe_install"]);
  }

  public static function folders_table()
  {
    global $wpdb;
    return $wpdb->prefix . self::TABLE_FOLDERS;
  }

  public static function items_table()
  {
    global $wpdb;
    return $wpdb->prefix . self::TABLE_ITEMS;
  }

  public static function maybe_install()
  {
    if (get_option(self::DB_OPTION) === self::DB_VERSION) {
      return;
    }

    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    $folders_sql = "CREATE TABLE " . self::folders_table() . " (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      name VARCHAR(191) NOT NULL,
      parent BIGINT UNSIGNED NOT NULL DEFAULT 0,
      ord INT NOT NULL DEFAULT 0,
      color VARCHAR(7) NULL,
      created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY idx_parent (parent)
    ) {$charset};";

    $items_sql = "CREATE TABLE " . self::items_table() . " (
      folder_id BIGINT UNSIGNED NOT NULL,
      attachment_id BIGINT UNSIGNED NOT NULL,
      PRIMARY KEY (folder_id, attachment_id),
      KEY idx_attachment (attachment_id)
    ) {$charset};";

    require_once ABSPATH . "wp-admin/includes/upgrade.php";
    dbDelta($folders_sql);
    dbDelta($items_sql);

    update_option(self::DB_OPTION, self::DB_VERSION);
  }

  /**
   * Full folder tree (nested children[]) with per-folder attachment counts,
   * plus the two pseudo-folders pinned above it by the caller (REST layer),
   * not here — this only returns the real, user-created folders.
   */
  public static function get_tree()
  {
    global $wpdb;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from an internal, hardcoded {$wpdb->prefix}-based constant, not user input; no user-supplied values in this query.
    $rows = $wpdb->get_results("SELECT id, name, parent, ord, color FROM " . self::folders_table() . " ORDER BY parent ASC, ord ASC, name ASC", ARRAY_A);

    $counts = self::counts_by_folder();

    $by_parent = [];
    foreach ($rows as $row) {
      $row["id"] = (int) $row["id"];
      $row["parent"] = (int) $row["parent"];
      $row["ord"] = (int) $row["ord"];
      $row["count"] = $counts[$row["id"]] ?? 0;
      $row["children"] = [];
      $by_parent[$row["parent"]][] = $row;
    }

    $build = function ($parent_id) use (&$build, &$by_parent) {
      $children = $by_parent[$parent_id] ?? [];
      foreach ($children as &$child) {
        $child["children"] = $build($child["id"]);
      }
      return $children;
    };

    return $build(0);
  }

  private static function counts_by_folder()
  {
    global $wpdb;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table names from internal, hardcoded {$wpdb->prefix}-based constants, not user input; no user-supplied values in this query.
    $rows = $wpdb->get_results("SELECT fi.folder_id, COUNT(*) AS c FROM " . self::items_table() . " fi INNER JOIN {$wpdb->posts} p ON p.ID = fi.attachment_id WHERE p.post_type = 'attachment' AND p.post_status = 'inherit' GROUP BY fi.folder_id");

    $counts = [];
    foreach ($rows as $row) {
      $counts[(int) $row->folder_id] = (int) $row->c;
    }
    return $counts;
  }

  public static function total_attachment_count()
  {
    return (int) wp_count_posts("attachment")->inherit;
  }

  public static function uncategorized_count()
  {
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from an internal, hardcoded {$wpdb->prefix}-based constant, not user input; no user-supplied values in this query.
    return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.post_type = 'attachment' AND p.post_status = 'inherit' AND p.ID NOT IN (SELECT attachment_id FROM " . self::items_table() . ")");
  }

  public static function create_folder($name, $parent)
  {
    global $wpdb;
    $name = self::unique_name(trim((string) $name), (int) $parent);
    if ($name === "") {
      return false;
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from an internal, hardcoded {$wpdb->prefix}-based constant, not user input; the %d value is passed through $wpdb->prepare() below.
    $ord = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(MAX(ord), -1) + 1 FROM " . self::folders_table() . " WHERE parent = %d", (int) $parent));

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $wpdb->insert() is WordPress's own escaping-safe API for writing to Aurora's custom folders table.
    $wpdb->insert(
      self::folders_table(),
      [
        "name" => $name,
        "parent" => (int) $parent,
        "ord" => $ord,
        "created_by" => get_current_user_id(),
        "created_at" => current_time("mysql"),
      ],
      ["%s", "%d", "%d", "%d", "%s"]
    );

    return (int) $wpdb->insert_id;
  }

  private static function unique_name($name, $parent)
  {
    if ($name === "") {
      return "";
    }

    global $wpdb;
    $base = $name;
    $suffix = 2;

    while (self::name_taken($parent, $name)) {
      $name = $base . " ({$suffix})";
      $suffix++;
    }

    return $name;
  }

  private static function name_taken($parent, $name)
  {
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from an internal, hardcoded {$wpdb->prefix}-based constant, not user input; the %d/%s values are passed through $wpdb->prepare() below.
    return (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM " . self::folders_table() . " WHERE parent = %d AND name = %s", (int) $parent, $name));
  }

  public static function rename_folder($id, $name)
  {
    global $wpdb;
    $id = (int) $id;
    $name = trim((string) $name);
    if (!$id || $name === "") {
      return false;
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from an internal, hardcoded {$wpdb->prefix}-based constant, not user input; the %d value is passed through $wpdb->prepare() below.
    $parent = (int) $wpdb->get_var($wpdb->prepare("SELECT parent FROM " . self::folders_table() . " WHERE id = %d", $id));
    $name = self::unique_name($name, $parent);

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $wpdb->update() is WordPress's own escaping-safe API for writing to Aurora's custom folders table.
    $wpdb->update(self::folders_table(), ["name" => $name], ["id" => $id], ["%s"], ["%d"]);
    return true;
  }

  /**
   * Reparents a folder and renumbers the destination parent's children by
   * the caller-supplied order. Simpler than FileBird's live insert-shift
   * math (which recomputes `ord` for every sibling on every drag step) —
   * the UI sends the full sibling id list in its new order after a drop,
   * and this just writes that order out in one pass.
   */
  public static function move_folder($id, $new_parent, $sibling_order = [])
  {
    global $wpdb;
    $id = (int) $id;
    $new_parent = (int) $new_parent;

    if ($id === $new_parent) {
      return false;
    }
    if (self::is_descendant($new_parent, $id)) {
      // Refuse to move a folder into its own descendant — would orphan
      // the subtree into an unreachable cycle.
      return false;
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from an internal, hardcoded {$wpdb->prefix}-based constant, not user input; the %d value is passed through $wpdb->prepare() below.
    $name = $wpdb->get_var($wpdb->prepare("SELECT name FROM " . self::folders_table() . " WHERE id = %d", $id));
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from an internal, hardcoded {$wpdb->prefix}-based constant, not user input; the %d value is passed through $wpdb->prepare() below.
    $current_parent = (int) $wpdb->get_var($wpdb->prepare("SELECT parent FROM " . self::folders_table() . " WHERE id = %d", $id));

    if ($current_parent !== $new_parent) {
      $renamed = self::unique_name((string) $name, $new_parent);
      if ($renamed !== $name) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $wpdb->update() is WordPress's own escaping-safe API for writing to Aurora's custom folders table.
        $wpdb->update(self::folders_table(), ["name" => $renamed], ["id" => $id], ["%s"], ["%d"]);
      }
    }

    $order = array_map("intval", $sibling_order);
    if (!in_array($id, $order, true)) {
      $order[] = $id;
    }

    foreach ($order as $index => $sibling_id) {
      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $wpdb->update() is WordPress's own escaping-safe API for writing to Aurora's custom folders table.
      $wpdb->update(
        self::folders_table(),
        ["parent" => $new_parent, "ord" => $index],
        ["id" => $sibling_id],
        ["%d", "%d"],
        ["%d"]
      );
    }

    return true;
  }

  private static function is_descendant($maybe_descendant_id, $ancestor_id)
  {
    global $wpdb;
    $current = (int) $maybe_descendant_id;

    // Bounded walk up the parent chain — folder depth is never large
    // enough for this to matter, and it guarantees termination even if
    // data were ever corrupted into a cycle.
    for ($i = 0; $i < 100 && $current > 0; $i++) {
      if ($current === (int) $ancestor_id) {
        return true;
      }
      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from an internal, hardcoded {$wpdb->prefix}-based constant, not user input; the %d value is passed through $wpdb->prepare() below.
      $current = (int) $wpdb->get_var($wpdb->prepare("SELECT parent FROM " . self::folders_table() . " WHERE id = %d", $current));
    }
    return false;
  }

  /**
   * Deletes a folder. Child folders are reparented to root rather than
   * cascade-deleted — less destructive default, matches the plan's choice
   * over FileBird's own recursive delete.
   */
  public static function delete_folder($id)
  {
    global $wpdb;
    $id = (int) $id;
    if (!$id) {
      return false;
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $wpdb->update() is WordPress's own escaping-safe API for writing to Aurora's custom folders table.
    $wpdb->update(self::folders_table(), ["parent" => 0], ["parent" => $id], ["%d"], ["%d"]);
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $wpdb->delete() is WordPress's own escaping-safe API for writing to Aurora's custom folder-items table.
    $wpdb->delete(self::items_table(), ["folder_id" => $id], ["%d"]);
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $wpdb->delete() is WordPress's own escaping-safe API for writing to Aurora's custom folders table.
    $wpdb->delete(self::folders_table(), ["id" => $id], ["%d"]);

    return true;
  }

  /**
   * Moves the given attachments into $folder_id, replacing any existing
   * folder membership (enforces one-folder-per-attachment). $folder_id of
   * UNCATEGORIZED just removes membership without assigning a new folder.
   */
  public static function assign_attachments($folder_id, $attachment_ids)
  {
    global $wpdb;
    $folder_id = (int) $folder_id;
    $attachment_ids = array_map("intval", (array) $attachment_ids);
    $attachment_ids = array_filter($attachment_ids);

    if (empty($attachment_ids)) {
      return false;
    }

    $placeholders = implode(",", array_fill(0, count($attachment_ids), "%d"));
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is an internal, hardcoded {$wpdb->prefix}-based constant, not user input; {$placeholders} is a dynamically built run of %d tokens (one per element of $attachment_ids) consumed by the prepare() call immediately below, so the placeholders are in fact present and filled.
    $wpdb->query($wpdb->prepare("DELETE FROM " . self::items_table() . " WHERE attachment_id IN ({$placeholders})", ...$attachment_ids));

    if ($folder_id === self::UNCATEGORIZED || $folder_id === self::ALL_MEDIA) {
      return true;
    }

    foreach ($attachment_ids as $attachment_id) {
      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $wpdb->insert() is WordPress's own escaping-safe API for writing to Aurora's custom folder-items table.
      $wpdb->insert(
        self::items_table(),
        ["folder_id" => $folder_id, "attachment_id" => $attachment_id],
        ["%d", "%d"]
      );
    }

    return true;
  }

  public static function attachment_ids_in_folder($folder_id)
  {
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from an internal, hardcoded {$wpdb->prefix}-based constant, not user input; the %d value is passed through $wpdb->prepare() below.
    return array_map("intval", $wpdb->get_col($wpdb->prepare("SELECT attachment_id FROM " . self::items_table() . " WHERE folder_id = %d", (int) $folder_id)));
  }

  public static function folder_id_for_attachment($attachment_id)
  {
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from an internal, hardcoded {$wpdb->prefix}-based constant, not user input; the %d value is passed through $wpdb->prepare() below.
    $folder_id = $wpdb->get_var($wpdb->prepare("SELECT folder_id FROM " . self::items_table() . " WHERE attachment_id = %d", (int) $attachment_id));
    return $folder_id !== null ? (int) $folder_id : self::UNCATEGORIZED;
  }

  public static function all_assigned_attachment_ids()
  {
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from an internal, hardcoded {$wpdb->prefix}-based constant, not user input; no user-supplied values in this query.
    return array_map("intval", $wpdb->get_col("SELECT DISTINCT attachment_id FROM " . self::items_table()));
  }

  /**
   * Batch lookup, used by MediaData::list_media() so a page of results
   * costs one query instead of one per attachment.
   */
  public static function folder_ids_for_attachments($attachment_ids)
  {
    $attachment_ids = array_map("intval", array_filter((array) $attachment_ids));
    if (empty($attachment_ids)) {
      return [];
    }

    global $wpdb;
    $placeholders = implode(",", array_fill(0, count($attachment_ids), "%d"));
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is an internal, hardcoded {$wpdb->prefix}-based constant, not user input; {$placeholders} is a dynamically built run of %d tokens (one per element of $attachment_ids, each cast via array_map("intval", ...) above) consumed by the prepare() call immediately below via PHP's ...$attachment_ids spread — the placeholder count and value count are mechanically the same array, so they always match; this exact pattern (dynamic IN-clause placeholder count built from array_fill(), spread into prepare()) is WordPress core's own documented way to safely parameterize a variable-length IN clause, since prepare() has no native "bind an array" placeholder.
    $rows = $wpdb->get_results($wpdb->prepare("SELECT attachment_id, folder_id FROM " . self::items_table() . " WHERE attachment_id IN ({$placeholders})", ...$attachment_ids));

    $map = [];
    foreach ($rows as $row) {
      $map[(int) $row->attachment_id] = (int) $row->folder_id;
    }
    return $map;
  }
}
