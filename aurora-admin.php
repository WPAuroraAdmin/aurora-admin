<?php
/*
 * Plugin Name: Aurora Admin
 * Plugin URI: https://auroraadmin.dev
 * Description: A modern, fast WordPress admin interface.
 * Version: 1.0
 * Author: Aurora Dragon Studio
 * Author URI: https://auroradragon.studio
 * Text Domain: aurora-admin
 * Requires PHP: 7.4
 * Requires at least: 5.5
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined("ABSPATH") || exit();

define("AURORA_ADMIN_VERSION", "1.0");
define("AURORA_ADMIN_PATH", plugin_dir_path(__FILE__));
define("AURORA_ADMIN_URL", plugins_url("aurora-admin/"));

require AURORA_ADMIN_PATH . "admin/src/App/AuroraAdmin.php";
require AURORA_ADMIN_PATH . "admin/src/Options/Settings.php";
require AURORA_ADMIN_PATH . "admin/src/Utility/Assets.php";
require AURORA_ADMIN_PATH . "admin/src/Shell/MenuSerializer.php";
require AURORA_ADMIN_PATH . "admin/src/Shell/Shell.php";
require AURORA_ADMIN_PATH . "admin/src/Pages/DashboardTakeover.php";
require AURORA_ADMIN_PATH . "admin/src/Pages/SettingsPage.php";
require AURORA_ADMIN_PATH . "admin/src/Pages/SetupWizard.php";
require AURORA_ADMIN_PATH . "admin/src/Rest/DashboardData.php";
require AURORA_ADMIN_PATH . "admin/src/Analytics/Analytics.php";
require AURORA_ADMIN_PATH . "admin/src/Rest/AnalyticsData.php";
require AURORA_ADMIN_PATH . "admin/src/Rest/PluginsData.php";
require AURORA_ADMIN_PATH . "admin/src/Rest/CommentsData.php";
require AURORA_ADMIN_PATH . "admin/src/Rest/PostsData.php";
require AURORA_ADMIN_PATH . "admin/src/Rest/PagesData.php";
require AURORA_ADMIN_PATH . "admin/src/Rest/MediaData.php";
require AURORA_ADMIN_PATH . "admin/src/Rest/UsersData.php";
require AURORA_ADMIN_PATH . "admin/src/Posts/ScheduledDayFilter.php";
require AURORA_ADMIN_PATH . "admin/src/WhiteLabel/WhiteLabel.php";
require AURORA_ADMIN_PATH . "admin/src/Pages/SubApps.php";
require AURORA_ADMIN_PATH . "admin/src/Pages/ScreenHijack.php";
require AURORA_ADMIN_PATH . "admin/src/Pages/HijackedNativePages.php";
require AURORA_ADMIN_PATH . "admin/src/Pages/NativeSettingsPages.php";
require AURORA_ADMIN_PATH . "admin/src/Rest/PermalinksData.php";
require AURORA_ADMIN_PATH . "admin/src/Rest/DiscussionData.php";
require AURORA_ADMIN_PATH . "admin/src/Pages/AppearancePages.php";
require AURORA_ADMIN_PATH . "admin/src/Pages/ToolsPage.php";
require AURORA_ADMIN_PATH . "admin/src/Pages/ExportPage.php";
require AURORA_ADMIN_PATH . "admin/src/Pages/ImportPage.php";
require AURORA_ADMIN_PATH . "admin/src/Rest/NavMenusData.php";
require AURORA_ADMIN_PATH . "admin/src/Rest/ThemesData.php";
require AURORA_ADMIN_PATH . "admin/src/Rest/WidgetsData.php";
require AURORA_ADMIN_PATH . "admin/src/Rest/ProfileData.php";
require AURORA_ADMIN_PATH . "admin/src/MenuCreator/MenuCreator.php";
require AURORA_ADMIN_PATH . "admin/src/MenuCreator/MenuCreatorData.php";
require AURORA_ADMIN_PATH . "admin/src/Notices/Notices.php";
require AURORA_ADMIN_PATH . "admin/src/Notices/NoticesData.php";
require AURORA_ADMIN_PATH . "admin/src/Roles/RolesData.php";
require AURORA_ADMIN_PATH . "admin/src/ActivityLog/ActivityLog.php";
require AURORA_ADMIN_PATH . "admin/src/ActivityLog/ActivityLogData.php";
require AURORA_ADMIN_PATH . "admin/src/Login/LoginStyle.php";
require AURORA_ADMIN_PATH . "admin/src/Login/LoginRedirect.php";
require AURORA_ADMIN_PATH . "admin/src/Editor/DisableGutenberg.php";
require AURORA_ADMIN_PATH . "admin/src/Media/SvgUploads.php";
require AURORA_ADMIN_PATH . "admin/src/Media/MediaFolders.php";
require AURORA_ADMIN_PATH . "admin/src/ImageFormats/ImageFormats.php";
require AURORA_ADMIN_PATH . "admin/src/Security/Security.php";
require AURORA_ADMIN_PATH . "admin/src/Code/CodeInjection.php";
require AURORA_ADMIN_PATH . "admin/src/Rest/MediaFolderData.php";
require AURORA_ADMIN_PATH . "admin/src/Rest/BugReportData.php";
require AURORA_ADMIN_PATH . "admin/src/Rest/CompanionPluginData.php";

new AuroraAdmin\App\AuroraAdmin();
