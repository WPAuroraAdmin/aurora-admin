<?php
namespace AuroraAdmin\Shell;

use AuroraAdmin\Utility\Assets;
use AuroraAdmin\Options\Settings;
use AuroraAdmin\Pages\ScreenHijack;

defined("ABSPATH") || exit();

/**
 * The site-wide immersive admin shell.
 *
 * Renders a custom Vue sidebar + toolbar on every admin screen and frames
 * WordPress's own content area inside a styled container. Crucially it does
 * NOT relocate or re-implement WP's content — native pages render normally
 * inside the frame, so they keep working without any reskinning.
 *
 * Native chrome (admin menu, toolbar, footer) is hidden via a small set of
 * explicit ID selectors gated on an html.aurora-loading class. A failsafe
 * reveals native admin again if the Vue app never signals ready, so a
 * broken build can never lock the user out of wp-admin.
 */
class Shell
{
  public function __construct()
  {
    // Print the chrome-hiding CSS + loading class as early as possible so
    // the native menu/toolbar never flash before the shell takes over.
    add_action("admin_head", [self::class, "print_early_styles"], 0);

    add_action("admin_enqueue_scripts", [self::class, "enqueue"]);

    // Mount point + serialized data, output at the end of the admin body.
    add_action("admin_footer", [self::class, "print_mount"]);
  }

  /**
   * Whether the shell should run on the current request. Skipped on the
   * Customizer (its own full-screen live-preview UI with a fundamentally
   * different structure Aurora doesn't attempt to frame). The Site Editor
   * used to be excluded here too — it's now handled instead: it turns out
   * to be built on the same @wordpress/interface package as the block
   * editor (post.php/post-new.php both get body.block-editor-page too),
   * so shell-frame.css/windowChrome.js's existing block-editor handling
   * covers it once #site-editor (its actual position:fixed, full-viewport
   * root — unlike the post editor, its .interface-interface-skeleton is
   * merely position:relative, nested inside #site-editor) is framed the
   * same way .interface-interface-skeleton is everywhere else.
   */
  private static function is_enabled()
  {
    if (!is_admin()) {
      return false;
    }

    $screen = function_exists("get_current_screen") ? get_current_screen() : null;
    if ($screen && in_array($screen->id, ["customize"], true)) {
      return false;
    }

    // Settings > General > "Disable theme": roles the shell is turned off
    // for — those users get the native WordPress admin.
    $disabled_roles = Settings::get("disable_theme", []);
    if (is_array($disabled_roles) && $disabled_roles) {
      $user = wp_get_current_user();
      if ($user && $user->exists() && array_intersect((array) $user->roles, $disabled_roles)) {
        return false;
      }
    }

    return apply_filters("aurora_admin_shell_enabled", true);
  }

  /**
   * Whether the current screen is one of Aurora's own pages (vs a native
   * WordPress admin page). Used to decide whether dark mode themes the
   * content area or leaves it in WP's native light theme.
   */
  private static function is_own_page()
  {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, used only to decide light/dark chrome theming for the current screen; no state change.
    $page = isset($_GET["page"]) ? sanitize_text_field(wp_unslash($_GET["page"])) : "";
    if (strpos($page, "aurora-admin") === 0) {
      return true;
    }

    // The dashboard (index.php) is taken over by Aurora's own dashboard, so
    // treat it as one of our pages: dark frame, and the native-dark theme
    // sheet is skipped there.
    if (isset($GLOBALS["pagenow"]) && $GLOBALS["pagenow"] === "index.php") {
      return true;
    }

    // ScreenHijack-based pages (Posts, Pages, Media, Users, Comments) keep
    // their native URL — no ?page=aurora-admin to key off — so they'd
    // otherwise be misdetected as native pages and get native-dark.css's
    // own-page exclusion skipped, letting its generic `a` color rules leak
    // into the real Aurora UI these screens render.
    return ScreenHijack::is_hijack_active();
  }

  public static function print_early_styles()
  {
    if (!self::is_enabled()) {
      return;
    }

    $native_class = self::is_own_page() ? "" : "aurora-native-page";
    ?>
    <script>
      // Synchronous: marks the document active before first paint so the
      // hide-native-chrome CSS below applies with no flash. This class
      // persists for the whole session (chrome stays hidden); aurora-ready
      // is added later by the Vue shell only to reveal the framed content.
      document.documentElement.classList.add("aurora-active");
      <?php if ($native_class): ?>
      document.documentElement.classList.add("<?php echo esc_js($native_class); ?>");
      <?php endif; ?>
      // Failsafe: if the Vue shell never signals ready, drop aurora-active
      // so native admin returns and a failed build can't lock the user out.
      window.setTimeout(function () {
        if (!document.documentElement.classList.contains("aurora-ready")) {
          document.documentElement.classList.remove("aurora-active");
        }
      }, 8000);
    </script>
    <style id="aurora-admin-early">
      /* Hide native chrome for the whole active session (not just loading),
         so it stays hidden once the shell is ready. */
      html.aurora-active #adminmenumain,
      html.aurora-active #wpadminbar,
      html.aurora-active #wpfooter {
        display: none !important;
      }
      html.aurora-active.wp-toolbar {
        padding-top: 0 !important;
      }
      /* Keep the framed content hidden only until the shell mounts, to avoid
         a flash of unframed/unpositioned content. No !important and no
         margin rules here, so the framing rules in shell-frame.css win
         cleanly once aurora-ready is set. */
      html.aurora-active:not(.aurora-ready) #wpbody {
        opacity: 0;
      }
      html.aurora-ready #wpbody {
        opacity: 1;
        transition: opacity 0.15s ease;
      }
    </style>
    <?php
  }

  public static function enqueue()
  {
    if (!self::is_enabled()) {
      return;
    }

    // The sidebar renders WordPress's own dashicon menu glyphs, so make
    // sure the dashicons font is present (core registers it under this
    // handle; it's normally enqueued already, this just guarantees it).
    wp_enqueue_style("dashicons");

    Assets::enqueue("aurora-admin-shell", "entries/shell.js");

    // Full dark theme for native WP pages. Loaded only on native pages
    // (Aurora's own pages style themselves) and inert unless dark mode is
    // active (every rule is gated on html.aurora-dark in the file itself).
    if (!self::is_own_page()) {
      // Version static assets by file mtime so edits bust the browser cache
      // even when the plugin version hasn't changed.
      $native_dark = AURORA_ADMIN_PATH . "admin/assets/native-dark.css";
      $css_ver = file_exists($native_dark) ? (string) filemtime($native_dark) : AURORA_ADMIN_VERSION;
      wp_enqueue_style(
        "aurora-admin-native-dark",
        AURORA_ADMIN_URL . "admin/assets/native-dark.css",
        ["aurora-admin-shell-0"],
        $css_ver
      );

      // Progressive enhancement (boxed sections on the Updates page, etc.)
      $native_js = AURORA_ADMIN_PATH . "admin/assets/native-enhance.js";
      $js_ver = file_exists($native_js) ? (string) filemtime($native_js) : AURORA_ADMIN_VERSION;
      wp_enqueue_script(
        "aurora-admin-native-enhance",
        AURORA_ADMIN_URL . "admin/assets/native-enhance.js",
        [],
        $js_ver,
        true
      );
    }
  }

  public static function print_mount()
  {
    if (!self::is_enabled()) {
      return;
    }

    $current_user = wp_get_current_user();

    // WP core sets this global on pages that are logically "under" a menu
    // section but aren't themselves a listed submenu item — e.g.
    // theme-install.php sets $parent_file = 'themes.php' (see wp-admin/
    // theme-install.php), media-new.php sets 'upload.php', user-new.php
    // sets 'users.php'. Native admin uses it to keep the *parent* menu
    // section highlighted/expanded while on these "hidden child" screens;
    // the sidebar needs the same signal, since MenuSerializer::serialize()
    // only scrapes $submenu (which these pages deliberately aren't part
    // of — WP's own native left menu doesn't list them as links either).
    global $parent_file;

    $data = [
      "restUrl" => esc_url_raw(get_rest_url()),
      "restNonce" => wp_create_nonce("wp_rest"),
      "adminUrl" => esc_url_raw(admin_url()),
      "siteUrl" => esc_url_raw(home_url("/")),
      "logoutUrl" => esc_url_raw(wp_logout_url()),
      "userName" => $current_user->display_name,
      "userEmail" => $current_user->user_email,
      "avatar" => get_avatar_url($current_user->ID, ["size" => 64]),
      "currentUrl" => esc_url_raw(self::current_admin_url()),
      "activeParentFile" => is_string($parent_file) ? $parent_file : "",
      // Secrets stripped — this is serialized for every admin user.
      "settings" => Settings::get_safe(),
      "menu" => MenuSerializer::serialize(),
      "auroraNav" => self::aurora_nav(),
    ];
    ?>
    <div
      id="aurora-admin-shell-root"
      data-aurora-admin="<?php echo esc_attr(wp_json_encode($data)); ?>"
    ></div>
    <?php
  }

  private static function current_admin_url()
  {
    $request = isset($_SERVER["REQUEST_URI"]) ? sanitize_text_field(wp_unslash($_SERVER["REQUEST_URI"])) : "";
    return home_url($request);
  }

  /**
   * Aurora's own branded nav section, rendered in a dedicated block at the
   * bottom of the sidebar (separate from the WP-native menu list above it).
   * A small static list rather than menu-scraped, since these are our own
   * pages, not WordPress's.
   */
  private static function aurora_nav()
  {
    $items = [
      ["id" => "aurora-admin", "title" => __("Settings", "aurora-admin"), "icon" => "dashicons-admin-generic"],
      ["id" => "aurora-admin-modules", "title" => __("Modules", "aurora-admin"), "icon" => "dashicons-screenoptions"],
      ["id" => "aurora-admin-menu-creator", "title" => __("Menu Creator", "aurora-admin"), "icon" => "dashicons-menu-alt"],
      ["id" => "aurora-admin-notices", "title" => __("Admin Notices", "aurora-admin"), "icon" => "dashicons-megaphone"],
      ["id" => "aurora-admin-roles", "title" => __("Role Editor", "aurora-admin"), "icon" => "dashicons-groups"],
      ["id" => "aurora-admin-activity-log", "title" => __("Activity Log", "aurora-admin"), "icon" => "dashicons-clock"],
      ["id" => "aurora-admin-bug-report", "title" => __("Report a Bug", "aurora-admin"), "icon" => "dashicons-warning"],
    ];

    $nav = [];
    foreach ($items as $item) {
      if (!current_user_can("manage_options")) {
        continue;
      }
      $nav[] = [
        "id" => $item["id"],
        "title" => $item["title"],
        "url" => esc_url_raw(admin_url("admin.php?page=" . $item["id"])),
        "icon" => ["type" => "dashicon", "value" => $item["icon"]],
      ];
    }

    return $nav;
  }
}
