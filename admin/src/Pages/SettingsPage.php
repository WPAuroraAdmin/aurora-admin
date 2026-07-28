<?php
namespace AuroraAdmin\Pages;

use AuroraAdmin\Utility\Assets;
use AuroraAdmin\Options\Settings;

defined("ABSPATH") || exit();

class SettingsPage
{
  private static $hook_suffix = null;

  public function __construct()
  {
    add_action("admin_menu", [self::class, "register_menu"]);
    add_action("admin_enqueue_scripts", [self::class, "maybe_enqueue"]);
  }

  public static function register_menu()
  {
    // Top-level "Aurora Admin" menu opens the Settings page directly. The
    // Aurora dashboard is reached through WordPress's own Dashboard menu
    // (taken over on index.php), so this menu has no submenu of its own.
    self::$hook_suffix = add_menu_page(
      __("Aurora Admin", "aurora-admin"),
      __("Aurora Admin", "aurora-admin"),
      "manage_options",
      "aurora-admin",
      [self::class, "render"],
      "dashicons-admin-generic",
      3
    );
  }

  public static function maybe_enqueue($hook_suffix)
  {
    if ($hook_suffix !== self::$hook_suffix) {
      return;
    }

    // Without this, window.wp.media is undefined client-side and every
    // image field (Logo, Dark mode logo, Login logo, Admin favicon)
    // silently falls back to a plain URL prompt instead of the real media
    // library picker (see SettingsField.vue's pickImage()).
    wp_enqueue_media();

    Assets::enqueue("aurora-admin-settings", "entries/settings.js");
  }

  public static function render()
  {
    // Option lists for multiselect fields (roles for "Disable theme",
    // public post types for "Search post types").
    $roles = [];
    foreach (wp_roles()->roles as $slug => $info) {
      $roles[] = ["value" => $slug, "label" => translate_user_role($info["name"])];
    }

    $post_types = [];
    foreach (get_post_types(["public" => true], "objects") as $pt) {
      if ($pt->name === "attachment") {
        continue;
      }
      $post_types[] = ["value" => $pt->name, "label" => $pt->labels->name];
    }

    $data = [
      "restUrl" => esc_url_raw(get_rest_url()),
      "restNonce" => wp_create_nonce("wp_rest"),
      "userName" => wp_get_current_user()->display_name,
      // Full settings (including secrets) — this page is manage_options-only
      // and needs to display/edit them.
      "settings" => Settings::get(),
      "roles" => $roles,
      "postTypes" => $post_types,
    ];
    ?>
    <div
      id="aurora-admin-settings-root"
      data-aurora-admin="<?php echo esc_attr(wp_json_encode($data)); ?>"
    ></div>
    <?php
  }
}
