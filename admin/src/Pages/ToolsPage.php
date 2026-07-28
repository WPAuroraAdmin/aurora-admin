<?php
namespace AuroraAdmin\Pages;

defined("ABSPATH") || exit();

/**
 * Registers the "Available Tools" screen (tools.php) for the "full admin
 * takeover" initiative — a real hijacked replacement, not just a reframed
 * native page.
 *
 * tools.php's whole purpose is being an extension point: other plugins add
 * their own cards to it via the `tool_box` action (WooCommerce, migration
 * tools, etc. have historically used it). A pure from-scratch rebuild would
 * silently drop that content the moment Aurora takes over the screen, so
 * this still captures and re-renders whatever `tool_box` produces — Aurora
 * owns the page shell (header, card styling, layout) but not the content of
 * hooks it doesn't control.
 *
 * Import, Export, Site Health, Export/Erase Personal Data, and the Theme/
 * Plugin File Editors are separate screens (their own load-{file} hooks) and
 * are not covered by this — Available Tools only.
 *
 * Native tools.php's "Categories and Tags Converter" card is NOT produced by
 * the tool_box hook — it's hardcoded directly in core's own template, gated
 * on the `import` capability plus category/tag manage_terms. Replicated here
 * with the identical gate so visibility exactly matches what core itself
 * would have shown.
 */
class ToolsPage
{
  public function __construct()
  {
    ScreenHijack::register(
      "load-tools.php",
      "edit_posts", // Matches core's own capability for this screen.
      "full_admin_takeover",
      "tools",
      "aurora-admin-tools-root",
      null,
      null,
      function () {
        // Same gate core's own tools.php applies before showing the
        // Categories and Tags Converter card.
        $show_converter = false;
        if (current_user_can("import")) {
          $cats = get_taxonomy("category");
          $tags = get_taxonomy("post_tag");
          $show_converter =
            ($cats && current_user_can($cats->cap->manage_terms)) ||
            ($tags && current_user_can($tags->cap->manage_terms));
        }

        // The native call to do_action('tool_box') already happened during
        // this same request (inside the buffer ScreenHijack discards) — this
        // is a second, independent invocation solely to capture its output
        // for Aurora's own page, so any OTHER plugin's tool_box card still
        // shows up here. Standard "buffer a hook to capture its markup"
        // pattern; safe for the well-behaved, output-only callbacks this
        // hook is meant for.
        ob_start();
        do_action("tool_box");
        $native_tools_html = trim((string) ob_get_clean());

        return [
          "showCategoriesConverter" => $show_converter,
          "importUrl" => esc_url_raw(admin_url("import.php")),
          "nativeToolsHtml" => $native_tools_html,
        ];
      }
    );
  }
}
