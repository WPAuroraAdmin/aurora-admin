<?php
namespace AuroraAdmin\Pages;

use AuroraAdmin\Utility\Assets;
use AuroraAdmin\Options\Settings;

defined("ABSPATH") || exit();

/**
 * First-run onboarding wizard.
 *
 * A full-screen, self-contained page (no Aurora sidebar/toolbar shell, no
 * native WP chrome) walking a new user through appearance, branding, and
 * which replacement screens to enable. Registered as a hidden submenu of the
 * "aurora-admin" menu — MenuSerializer skips that slug, so it never shows in
 * the Aurora sidebar, and the native menu it does technically live under is
 * hidden by the shell on every other page anyway.
 *
 * First run: on activation an option flags a one-time redirect here; the
 * wizard clears it and sets a "complete" option when finished or skipped, so
 * it never auto-opens again (but stays reachable at its URL).
 */
class SetupWizard
{
  const PAGE = "aurora-admin-setup";
  const OPT_COMPLETE = "aurora_admin_setup_complete";
  const OPT_REDIRECT = "aurora_admin_setup_redirect";

  private static $hook_suffix = null;

  public function __construct()
  {
    register_activation_hook(AURORA_ADMIN_PATH . "aurora-admin.php", [self::class, "on_activate"]);
    add_action("admin_menu", [self::class, "register_menu"]);
    add_action("admin_enqueue_scripts", [self::class, "maybe_enqueue"]);
    add_action("admin_init", [self::class, "maybe_redirect"]);
    add_action("rest_api_init", [self::class, "register_rest"]);
    // Turn the immersive shell off on this page so the wizard owns the whole
    // viewport; native chrome is hidden separately (printed below).
    add_filter("aurora_admin_shell_enabled", [self::class, "disable_shell_here"]);
  }

  /** Flag a one-time redirect to the wizard, unless setup was already done. */
  public static function on_activate()
  {
    if (!get_option(self::OPT_COMPLETE)) {
      update_option(self::OPT_REDIRECT, 1);
    }
  }

  private static function is_wizard_page()
  {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page match; no state change.
    return isset($_GET["page"]) && sanitize_text_field(wp_unslash($_GET["page"])) === self::PAGE;
  }

  public static function disable_shell_here($enabled)
  {
    return self::is_wizard_page() ? false : $enabled;
  }

  public static function register_menu()
  {
    self::$hook_suffix = add_submenu_page(
      "aurora-admin",
      __("Setup", "aurora-admin"),
      __("Setup", "aurora-admin"),
      "manage_options",
      self::PAGE,
      [self::class, "render"]
    );
  }

  public static function maybe_redirect()
  {
    if (!get_option(self::OPT_REDIRECT)) {
      return;
    }
    if (wp_doing_ajax() || !current_user_can("manage_options") || self::is_wizard_page()) {
      return;
    }
    delete_option(self::OPT_REDIRECT);
    wp_safe_redirect(admin_url("admin.php?page=" . self::PAGE));
    exit();
  }

  public static function maybe_enqueue($hook_suffix)
  {
    if ($hook_suffix !== self::$hook_suffix) {
      return;
    }
    // Image fields (logo, dark logo, favicon) use the real media library.
    wp_enqueue_media();
    Assets::enqueue("aurora-admin-setup", "entries/setup-wizard.js");

    // Hide native WP chrome on the wizard page (the shell, which normally
    // does this, is turned off here). The Vue app also renders as a
    // full-screen overlay, but this keeps the admin bar/menu from flashing
    // first.
    wp_register_style("aurora-admin-setup-hide-chrome", false, [], AURORA_ADMIN_VERSION);
    wp_enqueue_style("aurora-admin-setup-hide-chrome");
    wp_add_inline_style(
      "aurora-admin-setup-hide-chrome",
      "#adminmenumain,#wpadminbar,#wpfooter{display:none !important;}" .
      "html.wp-toolbar{padding-top:0 !important;}" .
      "#wpcontent{margin-left:0 !important;}" .
      "#wpbody-content{padding:0 !important;float:none;width:auto;}"
    );
  }

  public static function register_rest()
  {
    register_rest_route("aurora-admin/v1", "/setup/complete", [
      "methods" => "POST",
      "callback" => [self::class, "rest_complete"],
      "permission_callback" => function () {
        return current_user_can("manage_options");
      },
    ]);
  }

  public static function rest_complete()
  {
    update_option(self::OPT_COMPLETE, 1);
    delete_option(self::OPT_REDIRECT);
    return ["success" => true];
  }

  public static function render()
  {
    $data = [
      "restUrl" => esc_url_raw(get_rest_url()),
      "restNonce" => wp_create_nonce("wp_rest"),
      "userName" => wp_get_current_user()->display_name,
      "settings" => Settings::get(),
      "dashboardUrl" => esc_url_raw(admin_url("index.php")),
      "settingsUrl" => esc_url_raw(admin_url("admin.php?page=aurora-admin")),
    ];
    ?>
    <div
      id="aurora-admin-setup-root"
      data-aurora-admin="<?php echo esc_attr(wp_json_encode($data)); ?>"
    ></div>
    <?php
  }
}
