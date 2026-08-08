<?php
namespace AuroraAdmin\Pages;

use AuroraAdmin\Utility\Assets;
use AuroraAdmin\Options\Settings;

defined("ABSPATH") || exit();

/**
 * Replaces a native WordPress admin screen with an Aurora Vue page without
 * changing its URL, instead of SubApps' registered-page + redirect approach.
 *
 * WordPress fires "load-{hook_suffix}" once a screen is confirmed but before
 * it prints anything, so the recipe is: check access, buffer everything the
 * native screen would have printed, throw the buffer away once WordPress
 * reaches admin_footer, and print Aurora's own mount div in its place. The
 * URL the browser sees never changes, so there's no separate slug that can
 * fall out of sync with a plugin's own redirect, a bookmark, or any other
 * direct visit — the exact bug class SubApps::redirect_native_pages() has to
 * work around case by case (see its Plugins comment).
 *
 * Not every native screen can be hijacked this way — some (plugins.php is
 * the confirmed case so far) print output from within the load-{page}.php
 * callback itself or otherwise short-circuit before in_admin_header fires.
 * Those stay on SubApps' redirect mechanism instead.
 */
class ScreenHijack
{
  // Set true the moment any registered hijack actually activates for the
  // current request (all gating checks passed). Shell::is_own_page() reads
  // this — hijacked screens keep their native URL (no ?page=aurora-admin),
  // so the shell can't otherwise tell them apart from a real native page
  // when deciding whether to load native-dark.css's own-page exclusion.
  private static $hijack_active = false;

  public static function is_hijack_active()
  {
    return self::$hijack_active;
  }

  /**
   * @param string $hook_suffix "load-{page}.php", e.g. "load-edit.php"
   * @param string $capability Required capability, checked with current_user_can()
   * @param string|null $setting_key Settings::get() key gating this hijack
   *   (default true), or null to always hijack once capability passes
   * @param string $entry Vite entry name (without "src/entries/" or ".js")
   * @param string $root_id DOM id for the mount div
   * @param callable|null $screen_match fn(WP_Screen $screen): bool — extra
   *   match beyond the hook itself firing, e.g. distinguishing edit.php's
   *   several post types
   * @param callable|null $short_circuit fn(WP_Query $query): void, hooked to
   *   pre_get_posts only if provided — for list screens where letting
   *   WordPress's own query run would be wasted work
   * @param callable|null $data_callback fn(): array — extra data merged into
   *   the mount div's data-aurora-admin JSON, beyond the restUrl/restNonce
   *   defaults every page gets
   */
  public static function register(
    $hook_suffix,
    $capability,
    $setting_key,
    $entry,
    $root_id,
    $screen_match = null,
    $short_circuit = null,
    $data_callback = null
  ) {
    add_action(
      $hook_suffix,
      function () use (
        $capability,
        $setting_key,
        $entry,
        $root_id,
        $screen_match,
        $short_circuit,
        $data_callback
      ) {
        if (!current_user_can($capability)) {
          return;
        }

        if ($setting_key !== null && !Settings::get($setting_key, true)) {
          return;
        }

        if ($screen_match !== null) {
          $screen = get_current_screen();
          if (!$screen || !$screen_match($screen)) {
            return;
          }
        }

        self::$hijack_active = true;

        if ($short_circuit !== null) {
          add_filter("pre_get_posts", $short_circuit);
        }

        add_action(
          "in_admin_header",
          function () {
            ob_start();
          },
          999
        );

        add_action(
          "admin_footer",
          function () use ($entry, $root_id, $data_callback) {
            // A screen that exited early (its own wp_die(), a mid-render
            // redirect, a validation failure taking a different code path
            // than the initial GET) may never have reached in_admin_header,
            // or may have already closed its own buffer — calling
            // ob_end_clean() unconditionally in that case throws a notice
            // and can corrupt the response. Only discard a buffer that's
            // actually open.
            if (ob_get_level() > 0) {
              ob_end_clean();
            }

            $data = array_merge(
              [
                "restUrl" => esc_url_raw(get_rest_url()),
                "restNonce" => wp_create_nonce("wp_rest"),
              ],
              $data_callback !== null ? $data_callback() : []
            );
            // The buffer just discarded didn't just hold the native
            // screen's content — in_admin_header fires before WP prints
            // <div id="wpbody"><div id="wpbody-content">, and those
            // wrapper divs' closing tags print (in admin-footer.php)
            // before this admin_footer callback runs, so both got
            // captured and thrown away too. Re-print them around the
            // mount div so the DOM WP itself would have produced still
            // exists — windowChrome.js's traffic-light bar and the shell's
            // frame CSS both key off #wpbody-content being there.
            ?>
            <div id="wpbody" role="main">
            <div id="wpbody-content">
            <div
              id="<?php echo esc_attr($root_id); ?>"
              data-aurora-admin="<?php echo esc_attr(wp_json_encode($data)); ?>"
            ></div>
            </div>
            </div>
            <?php
          },
          0
        );

        add_action("admin_enqueue_scripts", function () use ($entry) {
          Assets::enqueue("aurora-admin-" . $entry, "entries/" . $entry . ".js");
        });
      },
      5
    );
  }
}
