<?php
namespace AuroraAdmin\Pages;

defined("ABSPATH") || exit();

/**
 * Registers every native screen Aurora replaces in place (same URL, no
 * redirect) via ScreenHijack. Kept as its own bootstrap class rather than
 * inline calls in AuroraAdmin's constructor since this list is expected to
 * grow past a dozen entries as more native screens are covered.
 *
 * Each of these previously lived in SubApps::PAGES as a registered
 * "aurora-admin-*" submenu with a matching redirect_native_pages() branch.
 * Plugins is the one screen that still uses that mechanism — plugins.php
 * prints output from within its own load hook in a way that can't be
 * cleanly buffered, confirmed by the old reference plugin's own abandoned
 * attempt at hijacking it — so it's deliberately not listed here.
 */
class HijackedNativePages
{
  public function __construct()
  {
    ScreenHijack::register(
      "load-edit-comments.php",
      "moderate_comments",
      "modern_comments",
      "comments",
      "aurora-admin-comments-root"
    );

    // "edit.php" is shared by Posts (the default post type) and every other
    // post type registered on the site, including Pages — screen_match is
    // what tells them apart; other custom post types fall through untouched.
    ScreenHijack::register(
      "load-edit.php",
      "edit_posts",
      "modern_posts",
      "posts",
      "aurora-admin-posts-root",
      function ($screen) {
        return $screen->post_type === "post";
      },
      null,
      function () {
        return ["newPostUrl" => esc_url_raw(admin_url("post-new.php"))];
      }
    );

    ScreenHijack::register(
      "load-edit.php",
      "edit_pages",
      "modern_pages",
      "pages",
      "aurora-admin-pages-root",
      function ($screen) {
        return $screen->post_type === "page";
      },
      null,
      function () {
        return ["newPageUrl" => esc_url_raw(admin_url("post-new.php?post_type=page"))];
      }
    );

    ScreenHijack::register(
      "load-upload.php",
      "upload_files",
      "modern_media",
      "media",
      "aurora-admin-media-root",
      null,
      null,
      function () {
        return [
          "newMediaUrl" => esc_url_raw(admin_url("media-new.php")),
        ];
      }
    );

    ScreenHijack::register(
      "load-users.php",
      "list_users",
      "modern_users",
      "users",
      "aurora-admin-users-root",
      null,
      null,
      function () {
        return ["newUserUrl" => esc_url_raw(admin_url("user-new.php"))];
      }
    );

    // media-new.php / user-new.php: Media Upload and Add New User. Free,
    // gated by the same modern_media / modern_users toggles as their
    // already-free list-view companions (Media Library / Users) — no
    // reason to paywall mid-workflow between "browse" and "add".
    ScreenHijack::register(
      "load-media-new.php",
      "upload_files",
      "modern_media",
      "media-upload",
      "aurora-admin-media-upload-root",
      null,
      null,
      function () {
        return ["libraryUrl" => esc_url_raw(admin_url("upload.php"))];
      }
    );

    ScreenHijack::register(
      "load-user-new.php",
      "create_users",
      "modern_users",
      "add-user",
      "aurora-admin-add-user-root",
      null,
      null,
      function () {
        $roles = [];
        foreach (wp_roles()->roles as $slug => $info) {
          $roles[] = ["value" => $slug, "label" => translate_user_role($info["name"])];
        }

        return [
          "usersUrl" => esc_url_raw(admin_url("users.php")),
          "roles" => $roles,
          "defaultRole" => get_option("default_role", "subscriber"),
        ];
      }
    );

    // profile.php: reskins only WordPress's own fields (name, contact
    // info, bio, password). If any plugin has hooked show_user_profile or
    // personal_options — the two documented extension points for adding
    // fields to this exact screen (2FA setup, WooCommerce customer fields,
    // etc.) — this deliberately does NOT hijack, falling through to the
    // real native screen instead, since a Vue reskin has no way to host
    // another plugin's raw HTML/JS output safely. Checked at hijack time
    // (load-profile.php), which fires after every plugin's own init/
    // admin_init hooks have already registered their callbacks.
    ScreenHijack::register(
      "load-profile.php",
      "read",
      "modern_users",
      "profile",
      "aurora-admin-profile-root",
      function () {
        return !has_action("show_user_profile") && !has_action("personal_options");
      }
    );
  }
}
