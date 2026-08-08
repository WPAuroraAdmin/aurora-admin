<?php
namespace AuroraAdmin\Pages;

defined("ABSPATH") || exit();

/**
 * Registers the 5 native Settings screens (General/Writing/Reading/
 * Discussion/Permalinks) via ScreenHijack, part of the "full admin
 * takeover" initiative (see PROJECT_STATUS.md). Unlike Posts/Pages/Media/
 * Users/Comments, these are gated on the master `full_admin_takeover`
 * setting (default off), since they're new ground rather than a migration
 * of something already shipped.
 *
 * All 5 share one Vue entry (native-settings.js / NativeSettings.vue) and
 * one mount id — only one native Settings screen is ever hijacked per
 * request, so there's no collision. Each just supplies a different field
 * schema + current values via its data_callback. Every field except
 * Permalinks' structure is one of core's own REST-exposed settings
 * (`/wp/v2/settings`), so the Vue side reads/writes that route directly;
 * Permalinks has no core REST route for rewrite rules and uses
 * PermalinksData's own aurora-admin/v1/permalinks route instead.
 */
class NativeSettingsPages
{
  // Core's REST settings route exposes some fields under a different name
  // than the wp_options row they actually live in (registered via
  // register_setting()'s show_in_rest 'name' argument) — reading them back
  // with get_option() needs the real option name, not the REST one, or the
  // field just reads back empty.
  const OPTION_NAME_MAP = [
    "title" => "blogname",
    "description" => "blogdescription",
    "url" => "siteurl",
  ];

  public function __construct()
  {
    self::register_screen(
      "load-options-general.php",
      "general",
      "General",
      "Site title, tagline, timezone, and date/time formats.",
      [
        ["key" => "title", "type" => "text", "label" => "Site Title"],
        ["key" => "description", "type" => "text", "label" => "Tagline"],
        ["key" => "url", "type" => "text", "label" => "Site Address (URL)", "readonly" => true],
        ["key" => "admin_email", "type" => "text", "label" => "Administration Email Address"],
        ["key" => "timezone_string", "type" => "text", "label" => "Timezone", "placeholder" => "e.g. America/New_York"],
        ["key" => "date_format", "type" => "text", "label" => "Date Format"],
        ["key" => "time_format", "type" => "text", "label" => "Time Format"],
        [
          "key" => "start_of_week",
          "type" => "select",
          "label" => "Week Starts On",
          "options" => [
            ["value" => "0", "label" => "Sunday"],
            ["value" => "1", "label" => "Monday"],
            ["value" => "2", "label" => "Tuesday"],
            ["value" => "3", "label" => "Wednesday"],
            ["value" => "4", "label" => "Thursday"],
            ["value" => "5", "label" => "Friday"],
            ["value" => "6", "label" => "Saturday"],
          ],
        ],
      ]
    );

    self::register_screen(
      "load-options-writing.php",
      "writing",
      "Writing",
      "Defaults used when publishing new content.",
      [
        // use_smilies is registered in core's REST schema (register_initial_settings())
        // but was never added here — a real gap, not a deliberate omission.
        ["key" => "use_smilies", "type" => "toggle", "label" => "Convert emoticons like :-) and :-P to graphics on display"],
        ["key" => "default_category", "type" => "select", "label" => "Default Post Category", "options" => self::category_options()],
        ["key" => "default_post_format", "type" => "select", "label" => "Default Post Format", "options" => self::post_format_options()],
      ]
    );

    self::register_screen(
      "load-options-reading.php",
      "reading",
      "Reading",
      "What visitors see on the front page and how many posts are listed.",
      [
        [
          "key" => "show_on_front",
          "type" => "select",
          "label" => "Homepage Displays",
          "options" => [
            ["value" => "posts", "label" => "Your latest posts"],
            ["value" => "page", "label" => "A static page"],
          ],
        ],
        [
          "key" => "page_on_front",
          "type" => "select",
          "label" => "Homepage",
          "options" => self::page_options(),
          "showIf" => ["key" => "show_on_front", "equals" => "page"],
        ],
        [
          "key" => "page_for_posts",
          "type" => "select",
          "label" => "Posts Page",
          "options" => self::page_options(),
          "showIf" => ["key" => "show_on_front", "equals" => "page"],
        ],
        ["key" => "posts_per_page", "type" => "text", "label" => "Posts Per Page"],
      ]
    );

    self::register_screen(
      "load-options-discussion.php",
      "discussion",
      "Discussion",
      "Default article settings for pings and comments.",
      [
        // Saved via DiscussionData's own /discussion-settings route, not
        // wp/v2/settings — core's REST schema only exposes the first two
        // of these fields (see DiscussionData.php's docblock for why).
        ["key" => "default_pingback_flag", "type" => "toggle", "label" => "Attempt to notify any blogs linked to from the article"],
        [
          "key" => "default_ping_status",
          "type" => "select",
          "label" => "Allow link notifications from other blogs (pingbacks and trackbacks)",
          "options" => [
            ["value" => "open", "label" => "Open"],
            ["value" => "closed", "label" => "Closed"],
          ],
        ],
        [
          "key" => "default_comment_status",
          "type" => "select",
          "label" => "Allow people to submit comments on new posts",
          "options" => [
            ["value" => "open", "label" => "Open"],
            ["value" => "closed", "label" => "Closed"],
          ],
        ],

        ["key" => "require_name_email", "type" => "toggle", "label" => "Comment author must fill out name and email"],
        ["key" => "comment_registration", "type" => "toggle", "label" => "Users must be registered and logged in to comment"],
        ["key" => "close_comments_for_old_posts", "type" => "toggle", "label" => "Automatically close comments on old posts"],
        [
          "key" => "close_comments_days_old",
          "type" => "text",
          "label" => "Close comments when post is how many days old",
          "showIf" => ["key" => "close_comments_for_old_posts", "equals" => true],
        ],
        ["key" => "show_comments_cookies_opt_in", "type" => "toggle", "label" => "Show comments cookies opt-in checkbox"],
        ["key" => "thread_comments", "type" => "toggle", "label" => "Enable threaded (nested) comments"],
        [
          "key" => "thread_comments_depth",
          "type" => "select",
          "label" => "Number of levels for threaded (nested) comments",
          "options" => array_map(
            function ($n) {
              return ["value" => (string) $n, "label" => (string) $n];
            },
            range(2, 10)
          ),
          "showIf" => ["key" => "thread_comments", "equals" => true],
        ],
        ["key" => "page_comments", "type" => "toggle", "label" => "Break comments into pages"],
        [
          "key" => "comments_per_page",
          "type" => "text",
          "label" => "Top level comments per page",
          "showIf" => ["key" => "page_comments", "equals" => true],
        ],
        [
          "key" => "default_comments_page",
          "type" => "select",
          "label" => "Comments page to display by default",
          "options" => [
            ["value" => "newest", "label" => "Last page"],
            ["value" => "oldest", "label" => "First page"],
          ],
          "showIf" => ["key" => "page_comments", "equals" => true],
        ],
        [
          "key" => "comment_order",
          "type" => "select",
          "label" => "Comments to display at the top of each page",
          "options" => [
            ["value" => "asc", "label" => "Older"],
            ["value" => "desc", "label" => "Newer"],
          ],
        ],

        ["key" => "comments_notify", "type" => "toggle", "label" => "Email me whenever: anyone posts a comment"],
        ["key" => "moderation_notify", "type" => "toggle", "label" => "Email me whenever: a comment is held for moderation"],
        ["key" => "wp_notes_notify", "type" => "toggle", "label" => "Email me whenever: WordPress news and updates", "default" => true],

        ["key" => "comment_moderation", "type" => "toggle", "label" => "Comment must be manually approved"],
        ["key" => "comment_previously_approved", "type" => "toggle", "label" => "Comment author must have a previously approved comment"],
        ["key" => "comment_max_links", "type" => "text", "label" => "Hold a comment in the queue if it contains this many links"],
        [
          "key" => "moderation_keys",
          "type" => "textarea",
          "label" => "Comment Moderation",
          "description" => "When a comment contains any of these words in its content, author name, URL, email, IP address, or browser's user agent string, it will be held in the moderation queue. One word or IP address per line.",
        ],
        [
          "key" => "disallowed_keys",
          "type" => "textarea",
          "label" => "Disallowed Comment Keys",
          "description" => "When a comment contains any of these words in its content, author name, URL, email, IP address, or browser's user agent string, it will be put in the Trash. One word or IP address per line.",
        ],

        ["key" => "show_avatars", "type" => "toggle", "label" => "Show Avatars"],
        [
          "key" => "avatar_rating",
          "type" => "select",
          "label" => "Maximum Rating",
          "options" => [
            ["value" => "G", "label" => "G — Suitable for all audiences"],
            ["value" => "PG", "label" => "PG — Possibly offensive, usually for audiences 13 and above"],
            ["value" => "R", "label" => "R — Intended for adult audiences above 17"],
            ["value" => "X", "label" => "X — Even more mature than above"],
          ],
          "showIf" => ["key" => "show_avatars", "equals" => true],
        ],
        [
          "key" => "avatar_default",
          "type" => "select",
          "label" => "Default Avatar",
          "options" => [
            ["value" => "mystery", "label" => "Mystery Person"],
            ["value" => "blank", "label" => "Blank"],
            ["value" => "gravatar_default", "label" => "Gravatar Logo"],
            ["value" => "identicon", "label" => "Identicon (Generated)"],
            ["value" => "wavatar", "label" => "Wavatar (Generated)"],
            ["value" => "monsterid", "label" => "MonsterID (Generated)"],
            ["value" => "retro", "label" => "Retro (Generated)"],
            ["value" => "robohash", "label" => "RoboHash (Generated)"],
            ["value" => "initials", "label" => "Initials (Generated)"],
            ["value" => "color", "label" => "Color (Generated)"],
          ],
          "default" => "mystery",
          "showIf" => ["key" => "show_avatars", "equals" => true],
        ],
      ]
    );

    self::register_screen(
      "load-options-permalink.php",
      "permalinks",
      "Permalinks",
      "The URL structure used for posts and pages.",
      [["key" => "permalink_structure", "type" => "text", "label" => "Custom Structure"]]
    );
  }

  private static function category_options()
  {
    $categories = get_categories(["hide_empty" => false]);
    return array_map(
      function ($cat) {
        return ["value" => (string) $cat->term_id, "label" => $cat->name];
      },
      $categories
    );
  }

  private static function post_format_options()
  {
    // get_post_format_strings() doesn't include "Standard" (the '0'/default
    // value native WP stores when no format is set) — added manually to
    // match the native Writing screen's own dropdown exactly.
    $options = [["value" => "0", "label" => "Standard"]];
    foreach (get_post_format_strings() as $slug => $label) {
      if ($slug === "standard") {
        continue;
      }
      $options[] = ["value" => $slug, "label" => $label];
    }
    return $options;
  }

  private static function page_options()
  {
    $options = [["value" => "0", "label" => "— Select —"]];
    $pages = get_pages();
    foreach ($pages as $page) {
      $options[] = ["value" => (string) $page->ID, "label" => $page->post_title ?: "(no title)"];
    }
    return $options;
  }

  private static function register_screen($hook_suffix, $screen, $title, $subtitle, $fields)
  {
    ScreenHijack::register(
      $hook_suffix,
      "manage_options",
      "full_admin_takeover",
      "native-settings",
      "aurora-admin-native-settings-root",
      null,
      null,
      function () use ($screen, $title, $subtitle, $fields) {
        $values = [];
        foreach ($fields as $field) {
          $option_name = self::OPTION_NAME_MAP[$field["key"]] ?? $field["key"];
          $value = get_option($option_name, $field["default"] ?? "");
          // Toggle fields need a real boolean — get_option() returns
          // whatever's actually stored ("1"/""/true/false depending on how
          // it was last saved), and a falsy non-bool like "" would
          // otherwise still satisfy Vue's `!!val` check inconsistently.
          $values[$field["key"]] = $field["type"] === "toggle" ? (bool) $value : $value;
        }

        return [
          "screen" => $screen,
          "title" => $title,
          "subtitle" => $subtitle,
          "fields" => $fields,
          "values" => $values,
        ];
      },
      "admin-takeover"
    );
  }
}
