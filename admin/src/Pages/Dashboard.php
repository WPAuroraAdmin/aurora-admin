<?php
namespace AuroraAdmin\Pages;

use AuroraAdmin\Utility\Assets;
use AuroraAdmin\Options\Settings;

defined("ABSPATH") || exit();

class Dashboard
{
  private static $hook_suffix = null;

  public function __construct()
  {
    add_action("admin_menu", [self::class, "register_menu"]);
    // Runs late (after submenus are registered) to drop the duplicate
    // first submenu item WordPress auto-creates for a top-level menu.
    add_action("admin_menu", [self::class, "remove_duplicate_submenu"], 100);
    add_action("admin_enqueue_scripts", [self::class, "maybe_enqueue"]);
  }

  public static function register_menu()
  {
    self::$hook_suffix = add_menu_page(
      __("Aurora Admin", "aurora-admin"),
      __("Aurora Admin", "aurora-admin"),
      "read",
      "aurora-admin",
      [self::class, "render"],
      "dashicons-admin-generic",
      3
    );
  }

  /**
   * WordPress auto-adds a submenu item duplicating the top-level menu
   * (same "Aurora Admin" label + slug). Remove it so the submenu only
   * shows the real child pages (e.g. Settings).
   */
  public static function remove_duplicate_submenu()
  {
    remove_submenu_page("aurora-admin", "aurora-admin");
  }

  public static function maybe_enqueue($hook_suffix)
  {
    if ($hook_suffix !== self::$hook_suffix) {
      return;
    }

    Assets::enqueue("aurora-admin-dashboard", "entries/dashboard.js");
  }

  public static function render()
  {
    $current_user = wp_get_current_user();

    $data = [
      "restUrl" => esc_url_raw(get_rest_url()),
      "restNonce" => wp_create_nonce("wp_rest"),
      "userName" => $current_user->display_name,
      "settings" => Settings::get(),
    ];
    ?>
    <div
      id="aurora-admin-dashboard-root"
      data-aurora-admin="<?php echo esc_attr(wp_json_encode($data)); ?>"
    ></div>
    <?php
  }
}
