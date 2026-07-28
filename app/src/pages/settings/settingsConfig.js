/**
 * Declarative settings schema for the Aurora Admin settings page.
 *
 * Categories are organized into top-level groups. Each group renders as a
 * collapsible section in the left nav; each category within it renders as a
 * nav item, and its fields render in the right panel. Field `key`s map
 * directly into the aurora_admin_settings option, saved via the
 * wp/v2/settings REST route.
 */
export const settingsGroups = [
  {
    id: 'general',
    title: 'General',
    icon: 'dashicons-admin-settings',
    categories: [
      {
        id: 'general',
        title: 'General',
        subtitle: 'Core plugin settings',
        icon: 'dashicons-admin-settings',
        fields: [
          {
            key: 'logo',
            type: 'image',
            label: 'Logo',
            description: 'Optional logo to be displayed in the menu.',
          },
          {
            key: 'dark_logo',
            type: 'image',
            label: 'Dark mode logo',
            description:
              "Set an alternative logo to use in dark mode. If one isn't set it will fall back to the standard logo.",
          },
          {
            key: 'disable_theme',
            type: 'multiselect',
            label: 'Disable theme',
            description: 'Choose roles to disable the theme for.',
            optionsSource: 'roles',
          },
          {
            key: 'search_post_types',
            type: 'multiselect',
            label: 'Search post types',
            description: 'Choose which post types are available in the global search.',
            optionsSource: 'postTypes',
          },
          {
            key: 'disable_global_search',
            type: 'segmented',
            label: 'Disable global search',
            description: 'Disable global search for all users.',
          },
        ],
      },
      {
        id: 'analytics',
        title: 'Analytics',
        subtitle: 'Built-in analytics and tracking settings',
        icon: 'dashicons-chart-bar',
        fields: [
          {
            key: 'enable_analytics',
            type: 'toggle',
            label: 'Enable analytics',
            description: 'Track first-party visitor analytics for the dashboard.',
            default: true,
          },
          {
            key: 'track_admins',
            type: 'toggle',
            label: 'Track logged-in admins',
            description: 'Include administrator visits in analytics (off by default).',
          },
        ],
      },
    ],
  },
  {
    id: 'appearance',
    title: 'Appearance',
    icon: 'dashicons-admin-appearance',
    categories: [
      {
        id: 'theme',
        title: 'Theme',
        subtitle: 'Colors, styling, and visual customization',
        icon: 'dashicons-admin-appearance',
        fields: [
          {
            key: 'theme_preset',
            type: 'theme-picker',
            label: 'Theme',
            description: 'Pick a color for the whole admin interface.',
            default: 'indigo',
          },
          {
            key: 'font_family',
            type: 'font-picker',
            label: 'Font family',
            description:
              'Optional. Leave empty to use your system font (no external requests). ' +
              'Choosing a font loads it from Google Fonts (fonts.googleapis.com) on each admin page.',
          },
        ],
      },
      {
        id: 'whitelabel',
        title: 'White Label',
        subtitle: 'Rebrand and customize interface text and icons',
        icon: 'dashicons-tag',
        fields: [
          {
            key: 'admin_favicon',
            type: 'image',
            label: 'Admin favicon',
            description: 'Optional favicon to replace the WordPress favicon in the admin.',
          },
          {
            key: 'plugin_name',
            type: 'text',
            label: 'Rename Aurora Admin',
            description:
              'This will rename Aurora Admin in the plugins list as well as renaming its settings link.',
            placeholder: 'Aurora Admin',
          },
          {
            key: 'text_replacements',
            type: 'repeater',
            label: 'Text Replacement',
            description:
              'Customize the text displayed in the WordPress admin area. Enter original words or phrases and their replacements to white-label the interface.',
            columns: ['Find', 'Replace with'],
          },
        ],
      },
      {
        id: 'login',
        title: 'Login',
        subtitle: 'Customize login page appearance and security',
        icon: 'dashicons-lock',
        fields: [
          {
            key: 'style_login',
            type: 'toggle',
            label: 'Style login page',
            description: 'Apply the Aurora theme to the WordPress login screen.',
          },
          {
            key: 'login_logo',
            type: 'image',
            label: 'Login logo',
            description: 'Replace the WordPress logo on the login screen.',
            dependsOn: 'style_login',
          },
          {
            key: 'login_bg_image',
            type: 'image',
            label: 'Background image',
            description: 'Full-screen background for the login page. Overrides the background color below.',
            dependsOn: 'style_login',
          },
          {
            key: 'login_bg_color',
            type: 'color',
            label: 'Background color',
            description: 'Solid background color for the login page (used when no background image is set).',
            default: '#0f1420',
            dependsOn: 'style_login',
          },
          {
            key: 'login_form_bg',
            type: 'color',
            label: 'Form background',
            description: 'Background color of the login form panel.',
            default: '#1c2333',
            dependsOn: 'style_login',
          },
          {
            key: 'login_button_color',
            type: 'color',
            label: 'Button color',
            description: 'Color of the primary “Log In” button and the field focus accent.',
            default: '#4f7cff',
            dependsOn: 'style_login',
          },
          {
            key: 'login_custom_css',
            type: 'textarea',
            mono: true,
            rows: 8,
            label: 'Custom login CSS',
            description: 'Extra CSS applied to the login screen, after Aurora’s own styles.',
            placeholder: 'body.login { … }',
            dependsOn: 'style_login',
          },
        ],
      },
      {
        id: 'login-redirect',
        title: 'Login Redirect',
        subtitle: 'Control where visitors and users land, before and after login',
        icon: 'dashicons-redo',
        fields: [
          {
            key: 'login_redirect_enabled',
            type: 'toggle',
            label: 'Enable login redirects',
            description:
              'Turns on the redirects below. This never changes, hides, or blocks your login page — wp-login.php always stays reachable at its normal address, so this can’t lock you out of logging in.',
          },
          {
            key: 'redirect_unauthenticated_url',
            type: 'text',
            label: 'Redirect logged-out wp-admin visitors',
            description:
              'Send visitors who try to access wp-admin without being logged in to this URL instead of the login screen. Leave empty to keep the normal WordPress behavior.',
            placeholder: 'https://example.com/',
            dependsOn: 'login_redirect_enabled',
          },
          {
            key: 'redirect_roles',
            type: 'multiselect',
            label: 'Redirect these roles after login',
            description: 'Users with these roles are sent to the URL below after logging in, instead of the dashboard.',
            optionsSource: 'roles',
            dependsOn: 'login_redirect_enabled',
          },
          {
            key: 'redirect_after_login_url',
            type: 'text',
            label: 'Redirect to',
            description: 'Where the roles above land after logging in.',
            placeholder: 'https://example.com/welcome',
            dependsOn: 'login_redirect_enabled',
          },
        ],
      },
      {
        id: 'menu',
        title: 'Menu',
        subtitle: 'Admin menu behavior and interaction settings',
        icon: 'dashicons-menu',
        fields: [
          {
            key: 'menu_search',
            type: 'toggle',
            label: 'Menu search',
            description: 'Show a search box at the top of the admin menu.',
          },
        ],
      },
    ],
  },
  {
    id: 'screens',
    title: 'Screens',
    icon: 'dashicons-desktop',
    categories: [
      {
        id: 'dashboard',
        title: 'Dashboard',
        subtitle: 'Custom dashboard and admin interface settings',
        icon: 'dashicons-dashboard',
        fields: [
          {
            key: 'use_custom_dashboard',
            type: 'toggle',
            label: 'Custom dashboard',
            description: "Replace the WordPress dashboard with Aurora's dashboard.",
            default: true,
          },
        ],
      },
      {
        id: 'content-tables',
        title: 'Content tables',
        subtitle: 'Choose which admin list screens use the Aurora interface',
        icon: 'dashicons-editor-table',
        fields: [
          {
            key: 'modern_posts',
            type: 'toggle',
            label: 'Posts',
            description: 'Use the Aurora posts management interface.',
            default: true,
          },
          {
            key: 'modern_pages',
            type: 'toggle',
            label: 'Pages',
            description: 'Use the Aurora pages management interface.',
            default: true,
          },
          {
            key: 'modern_media',
            type: 'toggle',
            label: 'Media library',
            description: 'Use the Aurora media library interface.',
            default: true,
          },
          {
            key: 'modern_users',
            type: 'toggle',
            label: 'Users',
            description: 'Use the Aurora users management interface.',
            default: true,
          },
          {
            key: 'modern_comments',
            type: 'toggle',
            label: 'Comments',
            description: 'Use the Aurora comments management interface.',
            default: true,
          },
          {
            key: 'modern_plugins',
            type: 'toggle',
            label: 'Plugins',
            description: 'Use the Aurora plugins management interface.',
            default: true,
          },
        ],
      },
      {
        id: 'editor',
        title: 'Editor',
        subtitle: 'Choose the editor used for posts and pages',
        icon: 'dashicons-edit',
        fields: [
          {
            key: 'disable_gutenberg',
            type: 'toggle',
            label: 'Disable Gutenberg',
            description:
              'Use WordPress’s classic editor instead of the block editor for all post types. Off by default.',
            default: false,
          },
        ],
      },
      {
        id: 'admin-takeover',
        title: 'Admin Takeover',
        subtitle: 'Replace the remaining native WordPress admin screens with Aurora',
        icon: 'dashicons-shield',
        fields: [
          {
            key: 'full_admin_takeover',
            type: 'toggle',
            label: 'Full admin takeover',
            description:
              'Replace Media Upload, Add User, Profile, Appearance, Tools, and native Settings screens with Aurora equivalents. Off by default — these cover more ground than Posts/Pages/Media/Users/Comments, so it’s opt-in rather than on by default.',
            default: false,
          },
        ],
      },
    ],
  },
  {
    id: 'productivity',
    title: 'Productivity',
    icon: 'dashicons-superhero-alt',
    categories: [
      {
        id: 'image-formats',
        title: 'Modern Image Formats',
        subtitle: 'Automatically generate WebP/AVIF versions of uploaded images',
        icon: 'dashicons-format-image',
        fields: [
          {
            key: 'image_formats_enabled',
            type: 'toggle',
            label: 'Enable modern image formats',
            description:
              'Automatically generate a WebP or AVIF copy of every size of a newly uploaded JPEG or PNG image, and serve it to browsers that support it. Only affects new uploads — existing library images are unaffected until re-uploaded or regenerated with a tool like Regenerate Thumbnails.',
            default: true,
          },
          {
            key: 'image_formats_format',
            type: 'select',
            label: 'Output format',
            description:
              'Automatic prefers AVIF (smaller files, wider quality range) when the server supports it, falling back to WebP. If your server supports neither, generation is silently skipped — ask your host about enabling GD or Imagick WebP/AVIF support.',
            dependsOn: 'image_formats_enabled',
            default: 'auto',
            options: [
              { value: 'auto', label: 'Automatic (AVIF, falling back to WebP)' },
              { value: 'webp', label: 'WebP only' },
              { value: 'avif', label: 'AVIF only' },
            ],
          },
          {
            key: 'image_formats_picture_element',
            type: 'toggle',
            label: 'Serve via <picture> element',
            description:
              'Rewrite <img> tags (post content and post thumbnails) into a <picture> element offering the modern format with the original JPEG/PNG as a fallback. Turn this off if it conflicts with your theme’s image styling, while still generating the files for other tools to use.',
            dependsOn: 'image_formats_enabled',
            default: true,
          },
        ],
      },
      {
        id: 'media',
        title: 'Media',
        subtitle: 'Media library and upload options',
        icon: 'dashicons-admin-media',
        fields: [
          {
            key: 'enable_svg',
            type: 'toggle',
            label: 'Allow SVG uploads',
            description: 'Permit SVG files to be uploaded to the media library.',
          },
        ],
      },
    ],
  },
  {
    id: 'security',
    title: 'Security & performance',
    icon: 'dashicons-shield',
    categories: [
      {
        id: 'security-hardening',
        title: 'Hardening',
        subtitle: 'Reduce your site’s attack surface and trim unnecessary output',
        icon: 'dashicons-shield-alt',
        fields: [
          {
            key: 'disable_xmlrpc',
            type: 'toggle',
            label: 'Disable XML-RPC',
            description:
              'Turn off the XML-RPC endpoint, a common brute-force and pingback-DDoS vector. Leave on only if you rely on the WordPress mobile app or remote publishing.',
          },
          {
            key: 'disable_comments',
            type: 'toggle',
            label: 'Disable comments',
            description:
              'Turn comments off site-wide — closes them on every post type, hides existing ones, and removes the Comments admin screen and dashboard widget.',
          },
          {
            key: 'remove_head_junk',
            type: 'toggle',
            label: 'Clean up <head>',
            description:
              'Remove the WordPress generator/version tag, RSD link, WLW manifest, and shortlink meta from your site’s <head>.',
          },
        ],
      },
      {
        id: 'performance-tweaks',
        title: 'Performance',
        subtitle: 'Trim requests and scripts WordPress loads by default',
        icon: 'dashicons-performance',
        fields: [
          {
            key: 'disable_emojis',
            type: 'toggle',
            label: 'Disable emoji scripts',
            description:
              'Stop WordPress loading its emoji-detection script and styles on every front-end and admin page.',
          },
          {
            key: 'disable_oembed',
            type: 'toggle',
            label: 'Disable oEmbeds',
            description:
              'Remove oEmbed discovery links and the wp-embed script (stops auto-embedding of pasted URLs and the embed REST route).',
          },
          {
            key: 'heartbeat_mode',
            type: 'select',
            label: 'Heartbeat API',
            description:
              'The Heartbeat API polls the server on a timer (autosave, post-lock notices). Slow it down or disable it to cut admin-ajax load.',
            default: 'default',
            options: [
              { value: 'default', label: 'Default (15–60s)' },
              { value: 'slow', label: 'Slow (60s)' },
              { value: 'disabled', label: 'Disabled' },
            ],
          },
        ],
      },
    ],
  },
  {
    id: 'code',
    title: 'Code',
    icon: 'dashicons-editor-code',
    categories: [
      {
        id: 'custom-code',
        title: 'Custom CSS & JS',
        subtitle: 'Inject your own CSS and JavaScript into the WordPress admin',
        icon: 'dashicons-admin-customizer',
        fields: [
          {
            key: 'custom_admin_css',
            type: 'textarea',
            mono: true,
            rows: 10,
            label: 'Admin CSS',
            description:
              'CSS added to every wp-admin page, inside a <style> tag in the admin <head>. Enter CSS only — no <style> tags needed.',
            placeholder: '.wrap { max-width: 1200px; }',
          },
          {
            key: 'custom_admin_js',
            type: 'textarea',
            mono: true,
            rows: 10,
            label: 'Admin JavaScript',
            description:
              'JavaScript added to the admin footer, inside a <script> tag. Enter JS only — no <script> tags needed.',
            placeholder: "console.log('Hello from Aurora');",
          },
        ],
      },
      {
        id: 'header-footer',
        title: 'Header & footer scripts',
        subtitle: 'Inject raw code into your site’s front-end <head> and footer',
        icon: 'dashicons-editor-code',
        fields: [
          {
            key: 'header_scripts',
            type: 'textarea',
            mono: true,
            rows: 8,
            label: 'Header scripts',
            description:
              'Raw HTML/JS printed in the front-end <head> — analytics snippets, verification meta tags, etc. Include the full <script>/<meta> tags.',
            placeholder: '<!-- e.g. analytics -->\n<script>…</script>',
          },
          {
            key: 'footer_scripts',
            type: 'textarea',
            mono: true,
            rows: 8,
            label: 'Footer scripts',
            description:
              'Raw HTML/JS printed just before </body> on the front end. Include the full <script> tags.',
            placeholder: '<script>…</script>',
          },
        ],
      },
    ],
  },
];

/**
 * Flattened category list — every category across all groups, in order.
 * Used for default-seeding, active-category lookup, and URL deep-links.
 */
export const settingsCategories = settingsGroups.flatMap((g) => g.categories);
