<?php
namespace AuroraAdmin\Code;

use AuroraAdmin\Options\Settings;

defined("ABSPATH") || exit();

/**
 * Custom code injection (Settings > Code).
 *
 * Four admin-authored fields, only settable by manage_options users on the
 * settings page:
 *  - Admin CSS  → wrapped in a <style> in the admin <head>
 *  - Admin JS   → wrapped in a <script> in the admin footer
 *  - Header scripts → printed raw in the front-end <head>
 *  - Footer scripts → printed raw before the front-end </body>
 *
 * Header/footer scripts are printed verbatim (they're meant to hold full
 * <script>/<meta> tags — analytics, verification, etc.), the same trusted-
 * admin model as any "insert headers and footers" tool. Admin CSS/JS is the
 * user's own code too, but is wrapped in the right tag for them and guarded
 * against accidentally breaking out of that tag.
 */
class CodeInjection
{
  public function __construct()
  {
    add_action("admin_head", [self::class, "admin_css"], 999);
    add_action("admin_print_footer_scripts", [self::class, "admin_js"], 999);
    add_action("wp_head", [self::class, "header_scripts"], 999);
    add_action("wp_footer", [self::class, "footer_scripts"], 999);
  }

  public static function admin_css()
  {
    $css = trim((string) Settings::get("custom_admin_css", ""));
    if ($css === "") {
      return;
    }
    // Keep the CSS from breaking out of its own <style> element.
    $css = str_ireplace("</style>", "", $css);
    echo "\n<style id=\"aurora-custom-admin-css\">\n" . $css . "\n</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- admin-authored stylesheet, output raw inside a <style> tag by design; </style> stripped above to prevent breakout.
  }

  public static function admin_js()
  {
    $js = trim((string) Settings::get("custom_admin_js", ""));
    if ($js === "") {
      return;
    }
    $js = str_ireplace("</script>", "<\\/script>", $js);
    echo "\n<script id=\"aurora-custom-admin-js\">\n" . $js . "\n</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- admin-authored script, output raw inside a <script> tag by design.
  }

  public static function header_scripts()
  {
    $code = trim((string) Settings::get("header_scripts", ""));
    if ($code === "") {
      return;
    }
    echo "\n" . $code . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- admin-authored header markup (scripts/meta), printed verbatim by design.
  }

  public static function footer_scripts()
  {
    $code = trim((string) Settings::get("footer_scripts", ""));
    if ($code === "") {
      return;
    }
    echo "\n" . $code . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- admin-authored footer markup (scripts), printed verbatim by design.
  }
}
