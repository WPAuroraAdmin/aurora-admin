<?php
namespace AuroraAdmin\Shell;

use AuroraAdmin\Options\Settings;

defined("ABSPATH") || exit();

/**
 * Turns WordPress's global $menu / $submenu structures into a clean,
 * JSON-friendly array the Vue sidebar can render.
 *
 * This is the deliberate, readable replacement for the original plugin's
 * reverse-engineered menu reconstruction. It reads the same source of
 * truth WordPress itself uses to paint the admin menu, so third-party
 * plugin menu items appear automatically.
 */
class MenuSerializer
{
  /**
   * @return array List of top-level menu items, each:
   *   [ id, title, url, icon, count, separator(bool), submenu[] ]
   */
  public static function serialize()
  {
    global $menu, $submenu;

    if (!is_array($menu)) {
      return [];
    }

    $items = [];

    foreach ($menu as $item) {
      // $item indexes (WordPress core):
      // 0 => menu title (may contain HTML), 1 => capability,
      // 2 => menu slug, 4 => classes, 6 => icon
      $raw_title = isset($item[0]) ? $item[0] : "";
      $capability = isset($item[1]) ? $item[1] : "";
      $slug = isset($item[2]) ? $item[2] : "";
      $classes = isset($item[4]) ? $item[4] : "";
      $icon = isset($item[6]) ? $item[6] : "";

      // Separators have a wp-menu-separator class and no real title.
      if (strpos($classes, "wp-menu-separator") !== false) {
        $items[] = ["separator" => true];
        continue;
      }

      if (empty($capability) || !current_user_can($capability)) {
        continue;
      }

      // Aurora's own top-level page is represented in the sidebar's
      // dedicated branded nav section (see Shell::print_mount()'s
      // "auroraNav"), not in this WP-native-menu list — skip it here so it
      // isn't shown twice.
      if ($slug === "aurora-admin") {
        continue;
      }

      $title_data = self::clean_title($raw_title);

      $entry = [
        "id" => $slug,
        "title" => $title_data["title"],
        "url" => self::resolve_url($slug),
        "icon" => self::resolve_icon($icon),
        "count" => $title_data["count"],
        "separator" => false,
        "submenu" => [],
      ];

      if (isset($submenu[$slug]) && is_array($submenu[$slug])) {
        foreach ($submenu[$slug] as $sub) {
          $sub_cap = isset($sub[1]) ? $sub[1] : "";
          if (empty($sub_cap) || !current_user_can($sub_cap)) {
            continue;
          }

          $sub_title = self::clean_title(isset($sub[0]) ? $sub[0] : "");
          $sub_slug = isset($sub[2]) ? $sub[2] : "";

          // Aurora's own sub-apps (Menu Creator, Comments, etc.) are
          // presented in the sidebar's dedicated Aurora nav section
          // instead — skip them here so they aren't duplicated. Most are
          // parented under "aurora-admin" itself, already skipped above
          // wholesale, but "aurora-admin-plugins" is parented under the
          // real "plugins.php" menu and needs this explicit check.
          if (strpos($sub_slug, "aurora-admin-") === 0) {
            continue;
          }

          $entry["submenu"][] = [
            "id" => $sub_slug,
            "title" => $sub_title["title"],
            "url" => self::resolve_url($sub_slug, $slug),
            "count" => $sub_title["count"],
          ];
        }
      }

      $items[] = $entry;
    }

    // Apply the Menu Creator's saved order (if any) for the current user.
    // Configured items come first in their saved order; anything not in the
    // config — separators, and newly installed plugins' menu items — keeps
    // its original position after them, so new menu items never disappear.
    $order = \AuroraAdmin\MenuCreator\MenuCreator::ordered_slugs();
    if ($order) {
      $rank = array_flip($order);
      $tail = count($order);
      $decorated = [];
      foreach ($items as $i => $entry) {
        $slug = isset($entry["id"]) ? $entry["id"] : "";
        $r = $slug !== "" && isset($rank[$slug]) ? $rank[$slug] : $tail + $i;
        $decorated[] = ["r" => $r, "i" => $i, "entry" => $entry];
      }
      usort($decorated, function ($a, $b) {
        return $a["r"] <=> $b["r"] ?: $a["i"] <=> $b["i"];
      });
      $items = array_map(function ($d) {
        return $d["entry"];
      }, $decorated);
    }

    return $items;
  }

  /**
   * Raw top-level menu items ({native_id,title,icon}) for the Menu Creator
   * editor. Unlike serialize() this must be captured BEFORE MenuCreator hides
   * anything (so hidden items still appear and can be toggled back on) — and
   * it can't run over REST, where the admin $menu isn't built. See
   * MenuCreator::capture_native().
   */
  public static function top_level_items()
  {
    global $menu;
    if (!is_array($menu)) {
      return [];
    }
    $items = [];
    foreach ($menu as $item) {
      $classes = isset($item[4]) ? $item[4] : "";
      if (strpos($classes, "wp-menu-separator") !== false) {
        continue;
      }
      $cap = isset($item[1]) ? $item[1] : "";
      $slug = isset($item[2]) ? $item[2] : "";
      if ($slug === "" || $slug === "aurora-admin" || empty($cap) || !current_user_can($cap)) {
        continue;
      }
      $title = self::clean_title(isset($item[0]) ? $item[0] : "");
      $items[] = [
        "native_id" => $slug,
        "title" => $title["title"],
        "icon" => self::resolve_icon(isset($item[6]) ? $item[6] : ""),
      ];
    }
    return $items;
  }

  /**
   * Strips the markup WordPress packs into menu titles (update/count
   * bubbles) and pulls the numeric count out separately.
   *
   * @return array{title:string,count:int|null}
   */
  private static function clean_title($raw)
  {
    $count = null;

    if (preg_match('/<span[^>]*>(\d+)/', $raw, $matches)) {
      $count = (int) $matches[1];
    }

    // WordPress menu titles are "Label <span ...>count bubble</span>" — the
    // human label is always the plain text before the first tag, so take
    // that rather than stripping tags (which would leave the count digits
    // dangling in the title).
    $label = $raw;
    $first_tag = strpos($raw, "<");
    if ($first_tag !== false) {
      $label = substr($raw, 0, $first_tag);
    }

    $title = trim(wp_strip_all_tags($label));

    return ["title" => $title, "count" => $count];
  }

  /**
   * Builds the admin URL for a menu slug, mirroring how WordPress decides
   * between a direct file (foo.php) and a registered page (admin.php?page=).
   */
  private static function resolve_url($slug, $parent_slug = "")
  {
    if (empty($slug)) {
      return "";
    }

    // Plugins is the one screen still on the redirect mechanism (plugins.php
    // can't be cleanly output-buffered — see SubApps::redirect_native_pages()
    // for why) — its sidebar link still needs rewriting to the Aurora URL.
    // Posts/Pages/Media/Users/Comments hijack their native URLs in place
    // instead (see HijackedNativePages), so their native sidebar links
    // already point at the right place with no rewrite needed.
    if ($slug === "plugins.php" && Settings::get("modern_plugins", true)) {
      return admin_url("plugins.php?page=aurora-admin-plugins");
    }

    // Already an absolute URL.
    if (preg_match('#^https?://#', $slug)) {
      return $slug;
    }

    // A slug pointing at a real admin file, possibly with a query string.
    if (strpos($slug, ".php") !== false) {
      return admin_url($slug);
    }

    // Submenu of a file-based parent (e.g. edit.php) — the child slug is a
    // file too, handled above; otherwise it's a page registered under that
    // parent's screen, reached via the parent file with ?page=.
    if (!empty($parent_slug) && strpos($parent_slug, ".php") !== false) {
      return admin_url(
        $parent_slug . (strpos($parent_slug, "?") !== false ? "&" : "?") . "page=" . $slug
      );
    }

    return admin_url("admin.php?page=" . $slug);
  }

  /**
   * Normalizes the menu icon into something the Vue side can render:
   * a dashicon class name, an image URL, or null for "use a default".
   */
  private static function resolve_icon($icon)
  {
    if (empty($icon) || $icon === "none" || $icon === "div") {
      return null;
    }

    if (strpos($icon, "dashicons-") === 0) {
      return ["type" => "dashicon", "value" => $icon];
    }

    if (strpos($icon, "data:image") === 0 || preg_match('#^https?://#', $icon)) {
      return ["type" => "image", "value" => $icon];
    }

    return null;
  }
}
