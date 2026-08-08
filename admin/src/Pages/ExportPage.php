<?php
namespace AuroraAdmin\Pages;

defined("ABSPATH") || exit();

/**
 * Registers the Export screen (export.php) for the "full admin takeover"
 * initiative — a real Aurora-built form, not a reframed native page.
 *
 * Safe to fully rebuild (unlike Tools/Site Health): export.php's actual file
 * generation (the `?download=true` request) calls export_wp() and die()s
 * before ever reaching in_admin_header/admin_footer — the two hooks
 * ScreenHijack's buffering relies on — so that request is never touched by
 * the hijack at all and runs completely native, unmodified. Only the HTML
 * form (the plain GET view with no `download` param) is replaced; Aurora's
 * form submits via a real browser navigation to the same
 * export.php?download=true&... URL, so the download itself is byte-for-byte
 * what core would have produced.
 */
class ExportPage
{
  public function __construct()
  {
    ScreenHijack::register(
      "load-export.php",
      "export", // Matches core's own capability for this screen.
      "full_admin_takeover",
      "export",
      "aurora-admin-export-root",
      null,
      null,
      function () {
        return [
          "exportUrl" => esc_url_raw(admin_url("export.php")),
        ];
      }
    );
  }
}
