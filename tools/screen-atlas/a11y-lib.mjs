// Shared helpers of the accessibility review (reviewer "accessibility", knowii-oss/knowii#1523).
// Same approach as atlas.mjs: theme in localStorage before the first paint, every request to another host refused.
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';

export const HERE = path.dirname(new URL(import.meta.url).pathname);
// The root of the repository: two folders above this file
export const REPO = process.env.ATLAS_REPO ?? path.resolve(HERE, '../..');
export const BASE = process.env.ATLAS_BASE_URL ?? 'http://localhost:4200';
export const OUT = path.join(HERE, 'a11y-out');
export const manifest = JSON.parse(fs.readFileSync(path.join(REPO, 'storage/app/atlas-manifest.json'), 'utf8'));
export const AXE = fs.readFileSync(path.join(REPO, 'node_modules/axe-core/axe.min.js'), 'utf8');
const ALLOWED_HOSTS = new Set(['localhost', '127.0.0.1']);
// For a step that may fail without a consequence for the check
export const ignore = () => undefined;
fs.mkdirSync(OUT, { recursive: true });

export async function launch() {
  return chromium.launch();
}

export async function newContext(browser, { width = 1920, height, theme = 'light', forcedColors, reducedMotion = 'no-preference' } = {}) {
  const context = await browser.newContext({
    viewport: { width, height: height ?? (width >= 1000 ? 1080 : 800) },
    colorScheme: theme,
    locale: 'en-GB',
    timezoneId: 'Europe/Brussels',
    reducedMotion,
    forcedColors: forcedColors ?? 'none',
    permissions: ['clipboard-read', 'clipboard-write'],
  });
  await context.addInitScript(
    ([key, value]) => {
      try {
        window.localStorage.setItem(key, value);
      } catch {
        // No storage on this page: the theme of the browser stays
      }
    },
    ['knowii-theme', theme],
  );
  await context.route('**/*', (route) => {
    let host = '';
    try {
      host = new URL(route.request().url()).hostname;
    } catch {
      // Not an address with a host (data:, about:): let it pass
    }
    if (host === '' || ALLOWED_HOSTS.has(host)) return route.fallback();
    return route.abort();
  });
  context.setDefaultTimeout(12000);
  context.setDefaultNavigationTimeout(90000);
  return context;
}

export async function signIn(page, role) {
  await page.goto(BASE + '/login', { waitUntil: 'load' });
  await page.fill('#email', manifest.accounts[role]);
  await page.fill('#password', manifest.password);
  await Promise.all([page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 90000 }), page.click('button[type=submit]')]);
}

export async function settle(page, ms = 1500) {
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(ignore);
  await page
    .evaluate(
      (quiet) =>
        new Promise((resolve) => {
          let timer = setTimeout(done, quiet);
          const observer = new MutationObserver(() => {
            clearTimeout(timer);
            timer = setTimeout(done, quiet);
          });
          const limit = setTimeout(done, 8000);
          function done() {
            observer.disconnect();
            clearTimeout(timer);
            clearTimeout(limit);
            resolve();
          }
          observer.observe(document.body, { subtree: true, childList: true, attributes: true, characterData: true });
        }),
      600,
    )
    .catch(ignore);
  await page.waitForTimeout(ms > 600 ? 300 : 0);
}

export async function goto(page, address) {
  for (let attempt = 0; attempt < 6; attempt++) {
    const response = await page.goto(BASE + address, { waitUntil: 'load' }).catch(() => null);
    if (response && response.status() < 500) {
      await settle(page);
      return response.status();
    }
    await page.waitForTimeout(20000);
  }
  throw new Error('application away: ' + address);
}

export async function runAxe(page, tags = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']) {
  await page.evaluate(AXE);
  return page.evaluate(async (runTags) => {
    const result = await window.axe.run(document, { runOnly: { type: 'tag', values: runTags }, resultTypes: ['violations', 'incomplete'] });
    const map = (items) =>
      items.map((v) => ({
        id: v.id,
        impact: v.impact,
        tags: v.tags.filter((t) => /^wcag\d/.test(t)),
        help: v.help,
        nodes: v.nodes.slice(0, 12).map((n) => ({
          target: n.target.join(' '),
          html: n.html.slice(0, 220),
          summary: (n.failureSummary || '').slice(0, 300),
          data: n.any?.[0]?.data ?? null,
        })),
        count: v.nodes.length,
      }));
    return { violations: map(result.violations), incomplete: map(result.incomplete) };
  }, tags);
}

export async function step(page, [verb, first, second]) {
  switch (verb) {
    case 'click':
      return page
        .locator(first)
        .first()
        .click(second ?? {});
    case 'fill':
      return page.locator(first).first().fill(second);
    case 'type':
      return page.locator(first).first().pressSequentially(second, { delay: 20 });
    case 'press':
      return second === undefined ? page.keyboard.press(first) : page.locator(first).first().press(second);
    case 'wait':
      return page.waitForTimeout(first);
    case 'waitFor':
      return page
        .locator(first)
        .first()
        .waitFor({ state: 'visible', timeout: second ?? 20000 });
    case 'waitDialog':
      await page.locator('[role=dialog], [role=alertdialog]').first().waitFor({ state: 'attached', timeout: 20000 });
      return page.waitForTimeout(450);
    case 'settle':
      return settle(page);
    case 'submit':
      return page
        .locator(first)
        .first()
        .evaluate((form) => {
          form.noValidate = true;
          form.requestSubmit();
        });
    case 'scroll':
      return page.locator(first).first().scrollIntoViewIfNeeded();
    default:
      throw new Error('unknown step ' + verb);
  }
}

/** The focused element, described */
export async function focusInfo(page) {
  return page.evaluate(() => {
    let el = document.activeElement;
    while (el && el.shadowRoot && el.shadowRoot.activeElement) el = el.shadowRoot.activeElement;
    if (!el || el === document.body) return { tag: 'BODY' };
    const r = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    const name = (
      el.getAttribute('aria-label') ||
      el.innerText ||
      el.getAttribute('title') ||
      el.value ||
      el.getAttribute('placeholder') ||
      ''
    )
      .trim()
      .replace(/\s+/g, ' ')
      .slice(0, 60);
    // Is the element under a fixed or sticky layer (header)?
    const cx = Math.min(Math.max(r.left + r.width / 2, 0), innerWidth - 1);
    const cy = Math.min(Math.max(r.top + r.height / 2, 0), innerHeight - 1);
    const top = document.elementFromPoint(cx, cy);
    const covered = top && !(el === top || el.contains(top) || top.contains(el));
    return {
      tag: el.tagName,
      role: el.getAttribute('role'),
      name,
      id: el.id,
      rect: [Math.round(r.left), Math.round(r.top), Math.round(r.width), Math.round(r.height)],
      outline: `${cs.outlineStyle} ${cs.outlineWidth} ${cs.outlineColor} off ${cs.outlineOffset}`,
      shadow: cs.boxShadow !== 'none' ? cs.boxShadow.slice(0, 90) : '',
      inView: r.bottom > 0 && r.top < innerHeight,
      covered: covered ? (top.closest('header,nav,[role=dialog]')?.tagName ?? top.tagName) : null,
    };
  });
}
