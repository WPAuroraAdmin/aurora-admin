<?php
namespace AuroraAdmin\App;

// Guard placed immediately after the namespace declaration (rather than
// after the use-import block below, this file's only departure from the
// rest of the codebase's convention) — this file's unusually large
// number of imports pushed the guard past the first several dozen
// lines, past where at least one automated file-access-protection
// scanner looks.
defined("ABSPATH") || exit();

use AuroraAdmin\Options\Settings;
use AuroraAdmin\Shell\Shell;
use AuroraAdmin\Pages\DashboardTakeover;
use AuroraAdmin\Pages\SettingsPage;
use AuroraAdmin\Pages\SetupWizard;
use AuroraAdmin\Rest\DashboardData;
use AuroraAdmin\Analytics\Analytics;
use AuroraAdmin\Rest\AnalyticsData;
use AuroraAdmin\Rest\PluginsData;
use AuroraAdmin\Rest\CommentsData;
use AuroraAdmin\Rest\PostsData;
use AuroraAdmin\Rest\PagesData;
use AuroraAdmin\Rest\MediaData;
use AuroraAdmin\Rest\UsersData;
use AuroraAdmin\Posts\ScheduledDayFilter;
use AuroraAdmin\WhiteLabel\WhiteLabel;
use AuroraAdmin\Pages\SubApps;
use AuroraAdmin\Pages\HijackedNativePages;
use AuroraAdmin\Pages\NativeSettingsPages;
use AuroraAdmin\Rest\PermalinksData;
use AuroraAdmin\Rest\DiscussionData;
use AuroraAdmin\Pages\AppearancePages;
use AuroraAdmin\Pages\ToolsPage;
use AuroraAdmin\Pages\ExportPage;
use AuroraAdmin\Pages\ImportPage;
use AuroraAdmin\Rest\NavMenusData;
use AuroraAdmin\Rest\ThemesData;
use AuroraAdmin\Rest\WidgetsData;
use AuroraAdmin\Rest\ProfileData;
use AuroraAdmin\MenuCreator\MenuCreator;
use AuroraAdmin\MenuCreator\MenuCreatorData;
use AuroraAdmin\Notices\Notices;
use AuroraAdmin\Notices\NoticesData;
use AuroraAdmin\Roles\RolesData;
use AuroraAdmin\ActivityLog\ActivityLog;
use AuroraAdmin\ActivityLog\ActivityLogData;
use AuroraAdmin\Login\LoginStyle;
use AuroraAdmin\Login\LoginRedirect;
use AuroraAdmin\Editor\DisableGutenberg;
use AuroraAdmin\Media\SvgUploads;
use AuroraAdmin\Media\MediaFolders;
use AuroraAdmin\ImageFormats\ImageFormats;
use AuroraAdmin\Security\Security;
use AuroraAdmin\Rest\MediaFolderData;
use AuroraAdmin\Rest\BugReportData;
use AuroraAdmin\Rest\CompanionPluginData;

/**
 * Plugin bootstrap. Deliberately minimal: each piece of functionality
 * registers its own hooks in its own constructor, instantiated here.
 * No global state, no manifest-parsing gymnastics beyond Assets::enqueue,
 * no body-hider/temporary-style tricks.
 */
class AuroraAdmin
{
  public function __construct()
  {
    new Settings();
    new Shell();
    new DashboardTakeover();
    new SettingsPage();
    new SetupWizard();
    new DashboardData();
    new Analytics();
    new AnalyticsData();
    new PluginsData();
    new CommentsData();
    new PostsData();
    new PagesData();
    new MediaData();
    new UsersData();
    new ScheduledDayFilter();
    new WhiteLabel();
    new SubApps();
    new HijackedNativePages();
    new NativeSettingsPages();
    new PermalinksData();
    new DiscussionData();
    new AppearancePages();
    new ToolsPage();
    new ExportPage();
    new ImportPage();
    new NavMenusData();
    new ThemesData();
    new WidgetsData();
    new ProfileData();
    new MenuCreator();
    new MenuCreatorData();
    new Notices();
    new NoticesData();
    new RolesData();
    new ActivityLog();
    new ActivityLogData();
    new LoginStyle();
    new LoginRedirect();
    new DisableGutenberg();
    new SvgUploads();
    new MediaFolders();
    new ImageFormats();
    new Security();
    new MediaFolderData();
    new BugReportData();
    new CompanionPluginData();
  }
}
