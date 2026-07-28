<?php
namespace AuroraAdmin\Editor;

use AuroraAdmin\Options\Settings;

defined("ABSPATH") || exit();

/**
 * Settings > Screens > Editor: "Disable Gutenberg" — opt-in, off by default.
 * Falls back to WordPress core's own classic (meta-box/TinyMCE) post editor
 * via the same core-supported filter the official Classic Editor plugin
 * uses, rather than framing or rebuilding the block editor itself — the
 * block editor stays one of the two deliberate "still native" screens in
 * the admin takeover (alongside the Theme/Plugin File Editor), so this is
 * an escape hatch around it, not a takeover of it.
 */
class DisableGutenberg
{
  public function __construct()
  {
    add_filter("use_block_editor_for_post_type", [self::class, "maybe_disable"], 100);
  }

  public static function maybe_disable($use_block_editor)
  {
    if ((bool) Settings::get("disable_gutenberg", false)) {
      return false;
    }
    return $use_block_editor;
  }
}
