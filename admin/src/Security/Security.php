<?php
namespace AuroraAdmin\Security;

use AuroraAdmin\Options\Settings;

defined("ABSPATH") || exit();

/**
 * Security + performance hardening toggles (Settings > Security & performance).
 *
 * Each feature is gated on its own setting and wired here by adding/removing
 * core hooks — no output is buffered or rewritten, so anything left off
 * behaves exactly like stock WordPress. Settings are read once at construct
 * time (plugins_loaded), the same pattern the rest of the plugin uses.
 */
class Security
{
  public function __construct()
  {
    if (Settings::get("disable_xmlrpc")) {
      self::disable_xmlrpc();
    }
    if (Settings::get("disable_comments")) {
      self::disable_comments();
    }
    if (Settings::get("remove_head_junk")) {
      self::clean_head();
    }
    if (Settings::get("disable_emojis")) {
      self::disable_emojis();
    }
    if (Settings::get("disable_oembed")) {
      self::disable_oembed();
    }

    $heartbeat = Settings::get("heartbeat_mode", "default");
    if ($heartbeat === "disabled") {
      add_action("init", function () {
        wp_deregister_script("heartbeat");
      }, 1);
    } elseif ($heartbeat === "slow") {
      add_filter("heartbeat_settings", function ($settings) {
        $settings["interval"] = 60;
        return $settings;
      });
    }
  }

  private static function disable_xmlrpc()
  {
    add_filter("xmlrpc_enabled", "__return_false");
    // Drop the X-Pingback header that advertises the endpoint.
    add_filter("wp_headers", function ($headers) {
      unset($headers["X-Pingback"]);
      return $headers;
    });
    add_filter("pings_open", "__return_false", 20);
  }

  private static function disable_comments()
  {
    // Front end: closed everywhere, and existing comments never render.
    add_filter("comments_open", "__return_false", 20);
    add_filter("pings_open", "__return_false", 20);
    add_filter("comments_array", "__return_empty_array", 20);

    // Strip comment support from every post type.
    add_action("init", function () {
      foreach (get_post_types() as $post_type) {
        if (post_type_supports($post_type, "comments")) {
          remove_post_type_support($post_type, "comments");
          remove_post_type_support($post_type, "trackbacks");
        }
      }
    }, 100);

    // Admin: remove the Comments menu, the dashboard widget, and the toolbar
    // bubble so there's no dangling entry point.
    add_action("admin_menu", function () {
      remove_menu_page("edit-comments.php");
    });
    add_action("wp_dashboard_setup", function () {
      remove_meta_box("dashboard_recent_comments", "dashboard", "normal");
    });
    add_action("admin_bar_menu", function ($bar) {
      $bar->remove_node("comments");
    }, 999);
  }

  private static function clean_head()
  {
    remove_action("wp_head", "rsd_link");
    remove_action("wp_head", "wlwmanifest_link");
    remove_action("wp_head", "wp_generator");
    remove_action("wp_head", "wp_shortlink_wp_head");
    add_filter("the_generator", "__return_empty_string");
  }

  private static function disable_emojis()
  {
    remove_action("wp_head", "print_emoji_detection_script", 7);
    remove_action("admin_print_scripts", "print_emoji_detection_script");
    remove_action("wp_print_styles", "print_emoji_styles");
    remove_action("admin_print_styles", "print_emoji_styles");
    remove_filter("the_content_feed", "wp_staticize_emoji");
    remove_filter("comment_text_rss", "wp_staticize_emoji");
    remove_filter("wp_mail", "wp_staticize_emoji_for_email");
    add_filter("tiny_mce_plugins", function ($plugins) {
      return is_array($plugins) ? array_diff($plugins, ["wpemoji"]) : [];
    });
    // Drop the s.w.org DNS-prefetch the emoji script adds.
    add_filter("wp_resource_hints", function ($hints, $relation_type) {
      if ($relation_type === "dns-prefetch") {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- "emoji_svg_url" is a WordPress core filter (wp-includes/formatting.php), not a hook this plugin owns; it is applied here only to read core's own emoji SVG base URL so the matching s.w.org dns-prefetch hint can be identified and removed.
        $emoji_svg = apply_filters("emoji_svg_url", "https://s.w.org/images/core/emoji/");
        $hints = array_filter($hints, function ($h) use ($emoji_svg) {
          return strpos((string) $h, $emoji_svg) === false && strpos((string) $h, "s.w.org") === false;
        });
      }
      return $hints;
    }, 10, 2);
  }

  private static function disable_oembed()
  {
    // Core registers these on wp_head at a non-default priority (the
    // discovery links sit at priority 4, not 10), so match whatever priority
    // they're actually hooked at rather than assuming the default.
    self::remove_wp_head_action("wp_oembed_add_discovery_links");
    self::remove_wp_head_action("wp_oembed_add_host_js");
    add_filter("embed_oembed_discover", "__return_false");
    remove_action("rest_api_init", "wp_oembed_register_route");
    // Stop the front-end wp-embed.min.js from loading.
    add_action("wp_footer", function () {
      wp_dequeue_script("wp-embed");
    });
  }

  /**
   * Remove a wp_head callback at whatever priority (or priorities) it's
   * registered at — core hooks the oEmbed discovery links at a non-default
   * priority, and it can end up registered at more than one, so loop until
   * none remain (bounded, so a stubborn callback can't spin forever).
   */
  private static function remove_wp_head_action($function)
  {
    $guard = 0;
    while (($priority = has_action("wp_head", $function)) !== false && $guard++ < 20) {
      remove_action("wp_head", $function, $priority);
    }
  }
}
