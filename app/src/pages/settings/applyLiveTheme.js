/**
 * Shared logic for applying the theme/font settings to the live document —
 * used both for the settings page's live preview (Settings.vue) and the
 * real per-page-load application (SiteShell.vue::onMounted).
 */
import { resolvePreset, buildPalette } from './themePresets.js';

export function applyThemePalette(themePresetId) {
  const palette = buildPalette(resolvePreset(themePresetId));
  const root = document.documentElement.style;
  Object.entries(palette).forEach(([prop, value]) => root.setProperty(prop, value));
}

export function applyFontFamily(family) {
  if (!family) return;
  const linkId = 'aurora-google-font';
  let link = document.getElementById(linkId);
  if (!link) {
    link = document.createElement('link');
    link.id = linkId;
    link.rel = 'stylesheet';
    document.head.appendChild(link);
  }
  link.href = `https://fonts.googleapis.com/css2?family=${encodeURIComponent(family)}:wght@400;500;600;700&display=swap`;
  document.documentElement.style.setProperty('--aurora-font-family', `'${family}', sans-serif`);
}
