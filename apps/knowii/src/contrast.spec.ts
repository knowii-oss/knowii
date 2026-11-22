import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { BADGE_COLORS } from './Components/ui/Badge';

// Read from the disk: Vitest gives an empty string for a CSS import, also with ?raw
const styles = readFileSync(`${import.meta.dirname}/styles.css`, 'utf8');

// The contrast of the color tokens (#1562, #1568, #1613): each text token on each surface where the application uses it,
// read from styles.css for both themes. WCAG 2 (1.4.3 and 1.4.11): 4.5:1 for text, 3:1 for large text and for the
// borders of controls. A token with an alpha (white at 70 %) is blended on the surface first. When a check fails,
// change the token, not the minimum.

const TEXT = 4.5;
const LARGE_TEXT = 3;
const CONTROL_BORDER = 3;

type Theme = 'dark' | 'light';
type Rgb = readonly [number, number, number];

interface Color {
  rgb: Rgb;
  alpha: number;
}

interface Pair {
  token: string;
  surfaces: readonly string[];
  min: number;
  usedBy: string;
}

const ALL_SURFACES = ['background', 'surface', 'surface-elevated', 'well'] as const;

// The palette of the member tags (#1389, ADR 0060): the border and the mark of a Badge, never text (WCAG 1.4.11)
const TAG_PAIRS: readonly Pair[] = BADGE_COLORS.map((color) => ({
  token: `tag-${color}`,
  surfaces: ALL_SURFACES,
  min: CONTROL_BORDER,
  usedBy: 'the border and the mark of a member tag (#1389)',
}));

const PAIRS: Record<Theme, readonly Pair[]> = {
  dark: [
    { token: 'primary', surfaces: ALL_SURFACES, min: TEXT, usedBy: 'all text' },
    { token: 'muted', surfaces: ALL_SURFACES, min: TEXT, usedBy: 'secondary text, the lines of the bell panel (#1568)' },
    { token: 'secondary-text', surfaces: ALL_SURFACES, min: TEXT, usedBy: 'links, mentions, "Pinned", the chat (#1562)' },
    { token: 'danger-text', surfaces: ALL_SURFACES, min: TEXT, usedBy: 'error messages, the alerts of raised panels (#1648)' },
    { token: 'success', surfaces: ['background', 'surface', 'well'], min: TEXT, usedBy: 'status messages, quiz results' },
    {
      token: 'border-strong',
      surfaces: ['surface', 'well'],
      min: CONTROL_BORDER,
      usedBy: 'fields, check boxes, the stepper, dashed boxes (#1648)',
    },
    ...TAG_PAIRS,
  ],
  light: [
    { token: 'primary', surfaces: ALL_SURFACES, min: TEXT, usedBy: 'all text' },
    { token: 'muted', surfaces: ALL_SURFACES, min: TEXT, usedBy: 'secondary text' },
    { token: 'secondary-text', surfaces: ALL_SURFACES, min: TEXT, usedBy: 'links, mentions, "Pinned", the chat' },
    { token: 'danger-text', surfaces: ALL_SURFACES, min: TEXT, usedBy: 'error messages' },
    { token: 'success', surfaces: ALL_SURFACES, min: TEXT, usedBy: 'status messages, quiz results on the well (#1648)' },
    { token: 'border-strong', surfaces: ['well'], min: CONTROL_BORDER, usedBy: 'fields, check boxes, radio buttons' },
    ...TAG_PAIRS,
  ],
};

/** The text between `start` and the first line that closes a block at the top level after it */
function block(start: string): string {
  const from = styles.indexOf(start);
  expect(from, start).toBeGreaterThanOrEqual(0);
  const to = styles.indexOf('\n}', from);
  return styles.slice(from, to);
}

function declarations(source: string): Map<string, string> {
  return new Map([...source.matchAll(/--color-([\w-]+):\s*([^;]+);/g)].map(([, name = '', value = '']) => [name, value.trim()]));
}

// The light theme sets only what changes: the other tokens come from @theme
const darkTokens = declarations(block('@theme {'));
const tokens: Record<Theme, Map<string, string>> = {
  dark: darkTokens,
  light: new Map([...darkTokens, ...declarations(block("[data-theme='light'] {"))]),
};

function parseColor(value: string): Color {
  const hex = /^#([\da-f]{2})([\da-f]{2})([\da-f]{2})$/i.exec(value);
  if (hex) {
    const [, r = '', g = '', b = ''] = hex;
    return { rgb: [parseInt(r, 16), parseInt(g, 16), parseInt(b, 16)], alpha: 1 };
  }
  const rgb = /^rgb\((\d+) (\d+) (\d+) \/ ([\d.]+)\)$/.exec(value);
  if (rgb) {
    const [, r = '', g = '', b = '', alpha = ''] = rgb;
    return { rgb: [Number(r), Number(g), Number(b)], alpha: Number(alpha) };
  }
  throw new Error(`Unknown color value: ${value}`);
}

function token(theme: Theme, name: string): Color {
  const value = tokens[theme].get(name);
  if (value === undefined) {
    throw new Error(`No token --color-${name} in the ${theme} theme`);
  }
  return parseColor(value);
}

function luminance([r, g, b]: Rgb): number {
  const channel = (value: number) => {
    const c = value / 255;
    return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
  };
  return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
}

/** A color with an alpha, blended on an opaque surface */
function blendOn(color: Color, surface: Color): Rgb {
  const [r = 0, g = 0, b = 0] = color.rgb.map((value, i) => value * color.alpha + (surface.rgb[i] ?? 0) * (1 - color.alpha));
  return [r, g, b];
}

/** WCAG 2 contrast of a color (blended on the surface when it has an alpha) on an opaque surface */
function contrast(color: Color, surface: Color): number {
  const a = luminance(blendOn(color, surface));
  const s = luminance(surface.rgb);
  return (Math.max(a, s) + 0.05) / (Math.min(a, s) + 0.05);
}

// The stops of the gradient word of the home headline (text-gradient-brand): the base is the light theme, the
// `@variant dark` block the dark theme
function gradientStops(theme: Theme): Color[] {
  const utility = block('@utility text-gradient-brand {');
  const darkAt = utility.indexOf('@variant dark {');
  const source = theme === 'dark' ? utility.slice(darkAt) : utility.slice(0, darkAt);
  const gradient = /linear-gradient\(90deg, ([^)]+)\)/.exec(source)?.[1] ?? '';
  return gradient.split(',').map((stop) => parseColor(stop.trim()));
}

describe.each<Theme>(['dark', 'light'])('the contrast of the %s theme', (theme) => {
  it('reads the tokens from styles.css', () => {
    expect(tokens[theme].size).toBeGreaterThan(10);
    // The light theme sets its own palette of tags: none comes from the dark one
    BADGE_COLORS.forEach((color) =>
      expect(declarations(block(theme === 'dark' ? '@theme {' : "[data-theme='light'] {")).has(`tag-${color}`), color).toBe(true),
    );
    ALL_SURFACES.forEach((surface) => expect(token(theme, surface).alpha, surface).toBe(1));
  });

  describe.each(PAIRS[theme])('$token ($usedBy)', ({ token: name, surfaces, min }) => {
    it.each(surfaces)(`reaches ${min}:1 on %s`, (surface) => {
      expect(contrast(token(theme, name), token(theme, surface))).toBeGreaterThanOrEqual(min);
    });
  });

  // A row of a raised panel under the pointer or with the focus (the lines of the bell panel, the entries of a menu):
  // the overlay at 5 % or 10 % on surface-elevated (#1648)
  it.each([5, 10])(`gives muted text ${TEXT}:1 on a row of surface-elevated with the overlay at %i %%`, (percent) => {
    const elevated = token(theme, 'surface-elevated');
    const overlay = token(theme, 'overlay');
    const fill: Color = { rgb: blendOn({ rgb: overlay.rgb, alpha: percent / 100 }, elevated), alpha: 1 };
    expect(contrast(token(theme, 'muted'), fill)).toBeGreaterThanOrEqual(TEXT);
  });

  it(`gives the gradient word of the home headline ${LARGE_TEXT}:1 on the background at each stop`, () => {
    const stops = gradientStops(theme);
    expect(stops).toHaveLength(3);
    stops.forEach((stop) => expect(contrast(stop, token(theme, 'background'))).toBeGreaterThanOrEqual(LARGE_TEXT));
  });
});
