<?php
namespace AuroraAdmin\Login;

use AuroraAdmin\Options\Settings;

defined("ABSPATH") || exit();

/**
 * Settings > Login > "Login Redirect": optionally sends logged-out wp-admin
 * visitors to a custom URL instead of the login screen, and/or sends
 * specific roles to a custom URL after logging in instead of the dashboard.
 * Both are opt-in and empty by default (normal WordPress behavior).
 *
 * Deliberately does NOT touch wp-login.php itself — it is never hidden,
 * blocked, or moved, so this can only change where someone lands
 * before/after that point, never whether they can reach the login form.
 * Changing/hiding the login URL itself is a separate, higher-risk feature
 * not implemented here.
 */
class LoginRedirect
{
  public function __construct()
  {
    add_filter("login_redirect", [self::class, "after_login"], 10, 3);
    add_filter("allowed_redirect_hosts", [self::class, "allow_configured_hosts"]);
    // wp_loaded, not admin_init: WordPress's own auth_redirect() (called
    // from wp-admin/admin.php, before admin_init fires) already redirects
    // an unauthenticated visitor to wp-login.php and exits — admin_init
    // would never run for the case this needs to catch. wp_loaded fires
    // from inside wp-settings.php, which wp-admin/admin.php requires and
    // waits on BEFORE calling auth_redirect(), so it reliably runs first.
    add_action("wp_loaded", [self::class, "guard_admin"]);
  }

  private static function enabled()
  {
    return (bool) Settings::get("login_redirect_enabled", false);
  }

  /** Destination after a successful login, for the configured roles. */
  public static function after_login($redirect_to, $requested_redirect_to, $user)
  {
    if (!self::enabled() || !($user instanceof \WP_User) || !$user->exists()) {
      return $redirect_to;
    }

    $roles = (array) Settings::get("redirect_roles", []);
    if (!$roles || !array_intersect((array) $user->roles, $roles)) {
      return $redirect_to;
    }

    $target = trim((string) Settings::get("redirect_after_login_url", ""));
    return $target !== "" ? esc_url_raw($target) : $redirect_to;
  }

  /**
   * Sends logged-out visitors to a custom URL instead of the default
   * wp-login.php screen when they try to open wp-admin. Scoped tightly to
   * real browser page loads under wp-admin — admin-ajax.php and
   * admin-post.php (both used by logged-out requests via the *_nopriv_*
   * hooks other plugins rely on for public form handling), async uploads,
   * REST, and multisite's network admin are all left untouched, so nothing
   * outside the login screen itself is affected.
   */
  public static function guard_admin()
  {
    if (!self::enabled() || is_user_logged_in()) {
      return;
    }
    if (!is_admin() || is_network_admin() || wp_doing_ajax()) {
      return;
    }
    if (defined("REST_REQUEST") && REST_REQUEST) {
      return;
    }
    $excluded_scripts = ["admin-post.php", "admin-ajax.php", "async-upload.php"];
    if (in_array($GLOBALS["pagenow"] ?? "", $excluded_scripts, true)) {
      return;
    }

    $target = trim((string) Settings::get("redirect_unauthenticated_url", ""));
    // Empty, or would send a logged-out visitor straight back into
    // wp-admin — the latter is almost certainly a misconfiguration and
    // would otherwise create a redirect loop.
    if ($target === "" || stripos($target, "wp-admin") !== false) {
      return;
    }

    wp_safe_redirect(esc_url_raw($target));
    exit();
  }

  /**
   * Lets wp_safe_redirect() honor an external target the admin configured
   * for either redirect above, instead of silently falling back to the site
   * home URL — both settings come from manage_options-gated Settings, never
   * from request input, so there's no open-redirect exposure in trusting
   * their host.
   */
  public static function allow_configured_hosts($hosts)
  {
    foreach (["redirect_unauthenticated_url", "redirect_after_login_url"] as $key) {
      $host = wp_parse_url(trim((string) Settings::get($key, "")), PHP_URL_HOST);
      if ($host) {
        $hosts[] = $host;
      }
    }
    return $hosts;
  }
}
