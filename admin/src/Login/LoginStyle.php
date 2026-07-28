<?php
namespace AuroraAdmin\Login;

use AuroraAdmin\Options\Settings;

defined("ABSPATH") || exit();

/**
 * Applies Settings > Login's "Style login page" and "Login logo" fields to
 * wp-login.php. Both settings existed in the UI with no implementation
 * behind them until now — this is that implementation.
 *
 * Deliberately a single fixed dark theme rather than reusing the admin
 * shell's per-preset palette system (themePresets.js): the login screen
 * loads independently of wp-admin, so none of the --aurora-* CSS variables
 * set on html.aurora-ready are available there, and duplicating the
 * hue-ramp logic in PHP just to match whichever preset happens to be
 * selected wasn't worth the upkeep for a first pass.
 */
class LoginStyle
{
  public function __construct()
  {
    add_action("login_enqueue_scripts", [self::class, "print_styles"]);
    add_filter("login_headerurl", [self::class, "header_url"]);
  }

  private static function enabled()
  {
    return (bool) Settings::get("style_login", false);
  }

  public static function header_url($url)
  {
    return self::enabled() ? home_url("/") : $url;
  }

  public static function print_styles()
  {
    if (!self::enabled()) {
      return;
    }

    $logo = trim((string) Settings::get("login_logo", ""));
    $bg_image = trim((string) Settings::get("login_bg_image", ""));
    // Colors fall back to the original fixed dark theme when unset; invalid
    // values are dropped by sanitize_hex_color (returns null) then fall back.
    $bg_color = sanitize_hex_color((string) Settings::get("login_bg_color", "")) ?: "";
    $form_bg = sanitize_hex_color((string) Settings::get("login_form_bg", "")) ?: "#1c2333";
    $accent = sanitize_hex_color((string) Settings::get("login_button_color", "")) ?: "#4f7cff";
    $custom_css = str_ireplace("</style>", "", (string) Settings::get("login_custom_css", ""));

    if ($bg_image) {
      $background = "url(" . esc_url($bg_image) . ") center / cover no-repeat";
    } elseif ($bg_color) {
      $background = $bg_color;
    } else {
      $background = "linear-gradient(135deg, #1b2333 0%, #0f1420 100%)";
    }
    ?>
    <style>
      body.login {
        background: <?php echo $background; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- composed from a sanitize_hex_color() color or esc_url()'d image above. ?>;
      }
      body.login #login h1 a {
        <?php if ($logo): ?>
        background-image: url(<?php echo esc_url($logo); ?>);
        background-size: contain;
        background-position: center;
        width: 100%;
        height: 80px;
        <?php else: ?>
        filter: brightness(0) invert(1);
        <?php endif; ?>
      }
      body.login #login {
        width: 360px;
      }
      body.login form#loginform,
      body.login form#registerform,
      body.login form#lostpasswordform {
        background: <?php echo esc_attr($form_bg); ?>;
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.35);
        padding: 26px 24px 24px;
      }
      body.login label {
        color: #cbd3e1;
      }
      body.login input[type="text"],
      body.login input[type="password"],
      body.login input[type="email"] {
        background: #10141f;
        border-color: rgba(255, 255, 255, 0.12);
        color: #fff;
        box-shadow: none;
      }
      body.login input[type="text"]:focus,
      body.login input[type="password"]:focus,
      body.login input[type="email"]:focus {
        border-color: <?php echo esc_attr($accent); ?>;
        box-shadow: 0 0 0 1px <?php echo esc_attr($accent); ?>;
      }
      body.login .forgetmenot label {
        color: #9fb0d0;
      }
      body.login #backtoblog a,
      body.login #nav a {
        color: #9fb0d0;
      }
      body.login #backtoblog a:hover,
      body.login #nav a:hover {
        color: #fff;
      }
      body.login .wp-core-ui .button-primary {
        background: <?php echo esc_attr($accent); ?>;
        border-color: <?php echo esc_attr($accent); ?>;
        box-shadow: none;
        text-shadow: none;
      }
      body.login .wp-core-ui .button-primary:hover,
      body.login .wp-core-ui .button-primary:focus {
        filter: brightness(0.92);
      }
      body.login #login_error,
      body.login .message,
      body.login .success {
        background: #232b40;
        border-left-color: <?php echo esc_attr($accent); ?>;
        color: #e2e8f4;
      }
      <?php echo $custom_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- admin-authored login CSS, output raw by design; </style> stripped above. ?>
    </style>
    <?php
  }
}
