<?php
namespace AuroraAdmin\Pages;

use AuroraAdmin\Utility\Assets;
use AuroraAdmin\Options\Settings;

defined("ABSPATH") || exit();

/**
 * Registers Aurora's secondary full-page apps (Menu Creator, Admin Notices,
 * Role Editor, Activity Log, Database Explorer, Plugins, Comments). Each is
 * a real WP admin page (proper capability check, working URL, its own
 * hook_suffix for asset gating) registered as a submenu of "aurora-admin"
 * (or "plugins.php" for the Plugins page).
 *
 * These stay registered in WordPress's own $submenu — they are NOT hidden
 * via remove_submenu_page(). An earlier version did call it (the standard
 * "register but hide" trick), but that turned out to corrupt WP core's own
 * access-control bookkeeping: user_can_access_admin_page() partly relies on
 * $submenu still containing the entry to resolve the page's required
 * capability, so removing it caused every user (including admins) to be
 * denied with "Sorry, you are not allowed to access this page" — confirmed
 * by reproducing the bug, disabling the remove_submenu_page() call, and
 * watching it resolve immediately. Since Aurora already hides WordPress's
 * entire native admin menu chrome (replaced by its own Vue sidebar), a
 * still-registered-but-native-menu-invisible entry has no visible effect on
 * its own — MenuSerializer::serialize() is what keeps these out of the
 * sidebar's own WP-native-menu section (skips slugs prefixed
 * "aurora-admin-", plus the "aurora-admin" top-level slug itself), so they
 * aren't duplicated there. They're presented instead in the sidebar's
 * dedicated Aurora nav section (see Shell::aurora_nav()).
 */
class SubApps
{
  public static function pages()
  {
    return [
      "aurora-admin-modules" => ["title" => __("Modules", "aurora-admin"), "entry" => "modules"],
      "aurora-admin-menu-creator" => ["title" => __("Menu Creator", "aurora-admin"), "entry" => "menu-creator"],
      "aurora-admin-notices" => ["title" => __("Admin Notices", "aurora-admin"), "entry" => "admin-notices"],
      "aurora-admin-roles" => ["title" => __("Role Editor", "aurora-admin"), "entry" => "role-editor"],
      "aurora-admin-activity-log" => ["title" => __("Activity Log", "aurora-admin"), "entry" => "activity-log"],
      "aurora-admin-plugins" => ["title" => __("Installed Plugins", "aurora-admin"), "entry" => "plugins"],
      "aurora-admin-bug-report" => ["title" => __("Report a Bug", "aurora-admin"), "entry" => "bug-report"],
    ];
  }

  private static $hook_suffixes = [];

  public function __construct()
  {
    add_action("admin_menu", [self::class, "register_menus"]);
    add_action("admin_enqueue_scripts", [self::class, "maybe_enqueue"]);
    add_action("admin_init", [self::class, "redirect_native_pages"]);
  }

  public static function register_menus()
  {
    foreach (self::pages() as $slug => $page) {
      $parent = self::parent_slug($slug);
      $hook = add_submenu_page(
        $parent,
        $page["title"],
        $page["title"],
        self::capability($slug),
        $slug,
        [self::class, "render"]
      );
      self::$hook_suffixes[$slug] = $hook;
    }
  }

  private static function parent_slug($slug)
  {
    if ($slug === "aurora-admin-plugins") {
      return "plugins.php";
    }
    return "aurora-admin";
  }

  private static function capability($slug)
  {
    if ($slug === "aurora-admin-plugins") {
      return "activate_plugins";
    }
    return "manage_options";
  }

  public static function maybe_enqueue($hook_suffix)
  {
    $slug = array_search($hook_suffix, self::$hook_suffixes, true);
    $pages = self::pages();
    if ($slug === false || !isset($pages[$slug])) {
      return;
    }

    $page = $pages[$slug];
    Assets::enqueue("aurora-admin-" . $page["entry"], "entries/" . $page["entry"] . ".js");
  }

  public static function redirect_native_pages()
  {
    global $pagenow;

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only isset() presence check (no value read), used only to skip pages that already have a ?page= param; no state change.
    if (isset($_GET["page"]) || wp_doing_ajax()) {
      return;
    }

    // Settings > Plugins > "Modern plugins page". Unlike the others below,
    // this one was previously only handled by MenuSerializer rewriting the
    // sidebar's own link — direct visits to plugins.php never redirected,
    // which is exactly what WordPress's own post-activate/post-install
    // redirect does (wp_redirect(self_admin_url('plugins.php?activate=true'))
    // — no ?page= param, so it always landed on the true native page,
    // fixed only once the user next clicked the (correctly rewritten)
    // sidebar link themselves. Redirects to plugins.php?page=... (not
    // admin.php?page=...) to match the URL admin_url("plugins.php?page=
    // aurora-admin-plugins") that the sidebar link itself already uses.
    if (
      $pagenow === "plugins.php" &&
      current_user_can("activate_plugins") &&
      Settings::get("modern_plugins", true)
    ) {
      wp_safe_redirect(admin_url("plugins.php?page=aurora-admin-plugins"));
      exit;
    }

  }

  public static function render()
  {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, routes rendering to the matching page's Vue root by slug; no state change.
    $page = isset($_GET["page"]) ? sanitize_text_field(wp_unslash($_GET["page"])) : "";
    $pages = self::pages();
    if (!isset($pages[$page])) {
      return;
    }

    $roles = [];
    foreach (wp_roles()->roles as $slug => $info) {
      $roles[] = ["value" => $slug, "label" => translate_user_role($info["name"])];
    }

    global $wpdb;

    $data = [
      "restUrl" => esc_url_raw(get_rest_url()),
      "restNonce" => wp_create_nonce("wp_rest"),
      "userName" => wp_get_current_user()->display_name,
      "roles" => $roles,
      "tablePrefix" => $wpdb->prefix,
      "newPostUrl" => esc_url_raw(admin_url("post-new.php")),
      "newPageUrl" => esc_url_raw(admin_url("post-new.php?post_type=page")),
      "newMediaUrl" => esc_url_raw(admin_url("media-new.php")),
      "newUserUrl" => esc_url_raw(admin_url("user-new.php")),
    ];

    // Report a Bug auto-fills its diagnostic fields from these — saves the
    // reporter from having to look any of it up themselves.
    if ($page === "aurora-admin-bug-report") {
      $data["pluginVersion"] = defined("AURORA_ADMIN_VERSION") ? AURORA_ADMIN_VERSION : "";
      $data["wpVersion"] = get_bloginfo("version");
      $data["phpVersion"] = phpversion();
      $data["theme"] = wp_get_theme()->get("Name");
    }

    // The Menu Creator editor needs the raw top-level menu, which can't be
    // read over REST (the admin $menu isn't built there). Pass the snapshot
    // captured during this admin page load instead.
    if ($page === "aurora-admin-menu-creator") {
      $data["nativeItems"] = \AuroraAdmin\MenuCreator\MenuCreator::native_snapshot();
    }

    $root_id = "aurora-admin-" . $pages[$page]["entry"] . "-root";
    ?>
    <div
      id="<?php echo esc_attr($root_id); ?>"
      data-aurora-admin="<?php echo esc_attr(wp_json_encode($data)); ?>"
    ></div>
    <?php
  }
}
