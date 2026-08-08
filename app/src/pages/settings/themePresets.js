/**
 * Fixed set of theme presets for the theme-picker field. No custom color
 * picker — exactly these 10 predetermined options. Each preset is a hue;
 * the main background gradient is mostly two vivid, saturated stops of
 * that hue (like FleekDash's "Fresh"/"Sunrise" palette bars), only
 * dipping to a near-black anchor in the last stretch — not a 50/50
 * dark-to-vivid split, which reads washed out rather than "poppy".
 * Cards/borders/text derive as solid, more muted shades of the primary
 * hue so the gradient stays a deliberate hero background rather than
 * making every surface busy.
 */
export const themePresets = [
  { id: 'indigo', label: 'Indigo', hue: 235 },
  { id: 'sky', label: 'Sky', hue: 205 },
  { id: 'teal', label: 'Teal', hue: 175 },
  { id: 'forest', label: 'Forest', hue: 140 },
  { id: 'lime', label: 'Lime', hue: 95 },
  { id: 'amber', label: 'Amber', hue: 45 },
  { id: 'orange', label: 'Orange', hue: 22 },
  { id: 'rose', label: 'Rose', hue: 345 },
  { id: 'berry', label: 'Berry', hue: 320 },
  { id: 'violet', label: 'Violet', hue: 265 },
  // Two flat neutrals, no gradient — for anyone who finds the color
  // themes above too much. Same values the shell originally shipped with
  // before theme presets existed (zinc dark / zinc light).
  {
    id: 'mono',
    label: 'Black & Grey',
    palette: {
      '--aurora-app-bg': '#09090b',
      '--aurora-app-bg-solid': '#09090b',
      '--aurora-bg': '#18181b',
      '--aurora-card-bg': '#1f1f23',
      '--aurora-card-bg-deep': '#131316',
      '--aurora-bg-subtle': '#27272a',
      '--aurora-text': '#f4f4f5',
      '--aurora-text-muted': '#a1a1aa',
      '--aurora-border': '#27272a',
      '--aurora-card-border': '#2e2e33',
      '--aurora-frame-bg': '#1c1c20',
      '--aurora-frame-border': '#34343b',
      '--aurora-accent': 'rgb(125 158 255)',
      '--aurora-accent-soft': 'rgb(125 158 255 / 0.14)',
    },
  },
  {
    id: 'white',
    label: 'White',
    palette: {
      '--aurora-app-bg': '#fafafa',
      '--aurora-app-bg-solid': '#fafafa',
      '--aurora-bg': '#ffffff',
      '--aurora-card-bg': '#fafafa',
      '--aurora-card-bg-deep': '#f0f0f2',
      '--aurora-bg-subtle': '#f4f4f5',
      '--aurora-text': '#18181b',
      '--aurora-text-muted': '#71717a',
      '--aurora-border': '#e4e4e7',
      '--aurora-card-border': '#ececef',
      '--aurora-frame-bg': '#ffffff',
      '--aurora-frame-border': '#e0e0e3',
      '--aurora-accent': 'rgb(71 114 250)',
      '--aurora-accent-soft': 'rgb(71 114 250 / 0.1)',
    },
  },
];

const hsl = (h, s, l, a) =>
  a === undefined ? `hsl(${h} ${s}% ${l}%)` : `hsl(${h} ${s}% ${l}% / ${a})`;

/** The full set of --aurora-* color tokens for a preset. */
export function buildPalette(preset) {
  if (preset.palette) return preset.palette;
  const { hue } = preset;
  // Two vivid, bright stops of the same hue family carry most of the bar
  // (0% → 55%); the near-black anchor only takes over in the final
  // stretch (100%). That's the "mostly color, dark tail" look the
  // FleekDash palette bars have — full saturation start to middle keeps
  // it feeling poppy instead of muddy.
  const hue2 = (hue + 35) % 360;
  const solidAppBg = hsl(hue, 45, 16);
  return {
    '--aurora-app-bg': `linear-gradient(135deg, ${hsl(hue, 78, 48)} 0%, ${hsl(hue2, 78, 45)} 55%, ${hsl(hue, 32, 9)} 100%)`,
    '--aurora-app-bg-solid': solidAppBg,
    '--aurora-bg': hsl(hue, 40, 20),
    '--aurora-card-bg': hsl(hue, 36, 24),
    '--aurora-card-bg-deep': hsl(hue, 38, 14),
    '--aurora-bg-subtle': hsl(hue, 32, 28),
    '--aurora-text': hsl(hue, 20, 97),
    '--aurora-text-muted': hsl(hue, 14, 74),
    '--aurora-border': hsl(hue, 28, 30),
    '--aurora-card-border': hsl(hue, 26, 33),
    '--aurora-frame-bg': hsl(hue, 34, 22),
    '--aurora-frame-border': hsl(hue, 26, 33),
    '--aurora-accent': hsl(hue, 85, 62),
    '--aurora-accent-soft': hsl(hue, 85, 62, 0.16),
  };
}

/** Resolve a theme_preset id to the preset object (falls back to the first preset). */
export function resolvePreset(themePresetId) {
  return themePresets.find((p) => p.id === themePresetId) || themePresets[0];
}
