/**
 * Structural setup for the takeover Dashboard.
 *
 * DashboardTakeover.php renders the dashboard into a separate
 * #aurora-admin-dashboard-root sibling (above the hidden #wpbody), so unlike
 * every other page it never sits inside #wpbody-content and never got that
 * element's card frame. Wrap it in .aurora-dashboard-frame so it gets the
 * same card treatment, and pull the custom admin notices
 * (Notices::render_for_dashboard(), printed as a sibling just before the
 * mount point) into that frame so they render as part of the card.
 *
 * This is the only DOM restructuring the shell does — there is no window
 * chrome (title bars, traffic-light controls, minimize taskbar); that whole
 * macOS-window metaphor was removed.
 */
export function initDashboardFrame() {
  const dashboardRoot = document.getElementById('aurora-admin-dashboard-root');
  if (!dashboardRoot) {
    return;
  }
  // Dedupe: if SiteShell re-mounts, don't wrap a second time.
  if (dashboardRoot.parentNode.querySelector('.aurora-dashboard-frame')) {
    return;
  }

  const frame = document.createElement('div');
  frame.className = 'aurora-dashboard-frame';
  dashboardRoot.parentNode.insertBefore(frame, dashboardRoot);

  const notices = document.getElementById('aurora-admin-dashboard-notices');
  if (notices) {
    frame.appendChild(notices);
  }
  frame.appendChild(dashboardRoot);
}
