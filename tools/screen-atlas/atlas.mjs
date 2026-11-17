#!/usr/bin/env node
// Screen atlas of the Knowii Platform for the UI review (knowii-oss/knowii#1523).
//
// One script, one data file (screens.json). Each entry of the data file is one screen in one state; the script takes it
// for each role of the entry, at each width, in each theme:
//
//   <atlas>/<area>/<screen>--<state>--<role>--<width>--<theme>.png
//
// Usage:
//   node atlas.mjs                        every screen, 1920 light, 360 light, 1920 dark, 360 dark (in that order)
//   node atlas.mjs --widths 1920 --themes light
//   node atlas.mjs --only auth/,settings/  the screens whose "<area>/<screen>--<state>" starts with one of these texts
//   node atlas.mjs --roles member,owner
//   node atlas.mjs --resume               keep the pictures that exist
//   node atlas.mjs --no-seed              do not run the seed before each width and theme
//   node atlas.mjs --probe                print what is on the screen after the steps (to write new entries)
//   node atlas.mjs --count                print the number of screens and pictures, take nothing
//   node atlas.mjs --index-only           write index.md again from screens.json and the last run
//
// Nothing leaves this machine: every request to a host that is not the application is refused.

import { chromium } from 'playwright';
import { execSync } from 'node:child_process';
import { createHmac } from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';

const HERE = path.dirname(new URL(import.meta.url).pathname);
// The root of the repository: two folders above this file
const REPO = process.env.ATLAS_REPO ?? path.resolve(HERE, '../..');
const BASE = process.env.ATLAS_BASE_URL ?? 'http://localhost:4200';
// The pictures and the index. Not in the repository (see .gitignore in this folder)
const OUT = process.env.ATLAS_OUT ?? path.join(HERE, 'atlas-out');
const MANIFEST = path.join(REPO, 'storage/app/atlas-manifest.json');
const SEED_COMMAND =
  process.env.ATLAS_SEED_COMMAND ??
  'echo "docker compose exec -T -u sail laravel.test php artisan tinker tools/screen-atlas/atlas-seed.php" | newgrp docker';
// The log of the runs. A probe run (--probe) is a trial: it keeps its own log
const RESULTS = path.join(HERE, process.argv.includes('--probe') ? 'last-probe.json' : 'last-run.json');
const VIEWPORTS = { 1920: { width: 1920, height: 1080 }, 360: { width: 360, height: 800 } };
const THEME_STORAGE_KEY = 'knowii-theme';
const ALLOWED_HOSTS = new Set([new URL(BASE).hostname, 'localhost', '127.0.0.1']);
// For a step that may fail without a consequence for the picture
const ignore = () => undefined;
// The roles of the review, in the order of the run. "admin" is the role between the owner and the moderator
const ROLE_ORDER = ['visitor', 'new-member', 'member', 'moderator', 'admin', 'owner'];

// ---------------------------------------------------------------------------------------------------------------------
// Arguments
// ---------------------------------------------------------------------------------------------------------------------

const args = process.argv.slice(2);
const flag = (name) => args.includes(`--${name}`);
const option = (name, fallback) => {
  const index = args.indexOf(`--${name}`);
  return index >= 0 && args[index + 1] ? args[index + 1] : fallback;
};
const widths = option('widths', '1920,360').split(',').map(Number);
const themes = option('themes', 'light,dark').split(',');
// --only: a list of texts separated by commas. A screen is taken when its key starts with one of them
const only =
  option('only', null)
    ?.split(',')
    .filter((text) => text !== '') ?? null;
const onlyRoles = option('roles', null)?.split(',') ?? null;
const concurrency = Number(option('concurrency', '3'));
const probe = flag('probe');

// ---------------------------------------------------------------------------------------------------------------------
// The data file
// ---------------------------------------------------------------------------------------------------------------------

const manifest = fs.existsSync(MANIFEST) ? JSON.parse(fs.readFileSync(MANIFEST, 'utf8')) : null;
if (manifest === null) {
  console.error(`The manifest of the seed is missing: ${MANIFEST}. Run the seed first.`);
  process.exit(1);
}

/** "{spaces.chat}" gives the value of manifest.spaces.chat */
function fill(text) {
  return text.replace(/\{([a-zA-Z0-9_.-]+)\}/g, (whole, key) => {
    const value = key.split('.').reduce((node, part) => (node == null ? undefined : node[part]), manifest);
    if (typeof value !== 'string') {
      throw new Error(`screens.json: "${whole}" is not in the manifest of the seed`);
    }
    return value;
  });
}

function fillDeep(value) {
  if (typeof value === 'string') {
    return fill(value);
  }
  if (Array.isArray(value)) {
    return value.map(fillDeep);
  }
  if (value !== null && typeof value === 'object') {
    return Object.fromEntries(Object.entries(value).map(([key, item]) => [key, fillDeep(item)]));
  }
  return value;
}

const data = JSON.parse(fs.readFileSync(process.env.ATLAS_SCREENS ?? path.join(HERE, 'screens.json'), 'utf8'));
const NAME = /^[a-z0-9]+(-[a-z0-9]+)*$/;
const seen = new Set();
const screens = data.screens.map((raw) => {
  const screen = fillDeep(raw);
  for (const part of [screen.area, screen.screen, screen.state]) {
    if (!NAME.test(part ?? '')) {
      throw new Error(`screens.json: "${part}" is not lower case with hyphens (${JSON.stringify(raw).slice(0, 120)})`);
    }
  }
  screen.key = `${screen.area}/${screen.screen}--${screen.state}`;
  if (seen.has(screen.key)) {
    throw new Error(`screens.json: "${screen.key}" is there twice`);
  }
  seen.add(screen.key);
  for (const role of screen.roles) {
    if (!ROLE_ORDER.includes(role)) {
      throw new Error(`screens.json: unknown role "${role}" in ${screen.key}`);
    }
  }
  return screen;
});

// --with-steps: only the entries that do something after the page loads (to try new entries)
const selected = screens.filter(
  (screen) =>
    (only === null || only.some((text) => screen.key.startsWith(text))) &&
    (!flag('with-steps') || (screen.steps ?? []).length > 0 || (screen.mocks ?? []).length > 0),
);
const shotsOf = (screen) => screen.roles.filter((role) => onlyRoles === null || onlyRoles.includes(role));
const widthsOf = (screen) => widths.filter((width) => !screen.widths || screen.widths.includes(width));
const fileOf = (screen, role, width, theme) => `${screen.area}/${screen.screen}--${screen.state}--${role}--${width}--${theme}.png`;

function counts() {
  let rows = 0;
  let pictures = 0;
  for (const screen of selected) {
    rows += shotsOf(screen).length;
    pictures += shotsOf(screen).length * widthsOf(screen).length * themes.length;
  }
  return { entries: selected.length, rows, pictures };
}

// ---------------------------------------------------------------------------------------------------------------------
// The index
// ---------------------------------------------------------------------------------------------------------------------

function writeIndex() {
  const last = fs.existsSync(RESULTS) ? JSON.parse(fs.readFileSync(RESULTS, 'utf8')) : { shots: {} };
  const commit = (() => {
    try {
      return execSync('git rev-parse --short=8 HEAD', { cwd: REPO }).toString().trim();
    } catch {
      return 'unknown';
    }
  })();
  const today = new Date().toISOString().slice(0, 10);
  const allWidths = [1920, 360];
  const allThemes = ['light', 'dark'];
  const lines = [];
  const areas = [...new Set(screens.map((screen) => screen.area))];
  let rows = 0;
  let expected = 0;
  let present = 0;
  const missing = [];

  const body = [];
  for (const area of areas) {
    body.push(
      '',
      `## ${data.areas?.[area] ?? area}`,
      '',
      '| Screen | Address | Role | State | Page component | Base file name | Note |',
      '| --- | --- | --- | --- | --- | --- | --- |',
    );
    for (const screen of screens.filter((item) => item.area === area)) {
      for (const role of screen.roles) {
        rows++;
        const base = `${screen.area}/${screen.screen}--${screen.state}--${role}`;
        const notes = [];
        if (screen.note) {
          notes.push(screen.note);
        }
        const gaps = [];
        for (const width of allWidths.filter((item) => !screen.widths || screen.widths.includes(item))) {
          for (const theme of allThemes) {
            expected++;
            const file = `${base}--${width}--${theme}.png`;
            if (fs.existsSync(path.join(OUT, file))) {
              present++;
            } else {
              gaps.push(`${width} ${theme}`);
              missing.push(file);
            }
            const problem = last.shots?.[file]?.problem;
            if (problem && !notes.includes(`Check: ${problem}`)) {
              notes.push(`Check: ${problem}`);
            }
          }
        }
        if (screen.widths) {
          notes.push(`Only at ${screen.widths.join(' and ')} px.`);
        }
        if (gaps.length > 0) {
          notes.push(`Missing: ${gaps.join(', ')}.`);
        }
        const cell = (text) => String(text).replaceAll('|', '\\|');
        body.push(
          `| ${cell(screen.name)} | \`${cell(screen.path)}\` | ${role} | ${screen.state} | ${screen.component ? '`' + screen.component + '`' : '-'} | \`${base}\` | ${cell(notes.join(' '))} |`,
        );
      }
    }
  }

  lines.push(
    '# Screen atlas of the Knowii Platform',
    '',
    `Made on ${today} from the commit \`${commit}\` of \`knowii-oss/knowii\` (branch \`main\`), for the UI review (issue 1523).`,
    'The application ran in the development containers, with the production build of the front-end (`npm run build`).',
    '',
    `${rows} rows (one screen, in one state, for one role). ${present} pictures of ${expected}.`,
    '',
    '## How to read the file names',
    '',
    '`<area>/<screen>--<state>--<role>--<width>--<theme>.png`, next to this file. The table gives the base file name:',
    'add `--1920--light.png`, `--360--light.png`, `--1920--dark.png` or `--360--dark.png`.',
    'Every picture is a full-page screenshot. Width 1920: viewport 1920 x 1080. Width 360: viewport 360 x 800.',
    '',
    '## Accounts',
    '',
    `Password of every account: \`${manifest.password}\`. Sign in at \`${BASE}/login\`.`,
    '',
    '| Role | Account | Who | In "Atlas Garden" and "Atlas Empty" |',
    '| --- | --- | --- | --- |',
    '| visitor | no account | Not signed in | - |',
    `| new-member | \`${manifest.accounts['new-member']}\` | Nina Newcomer. Signed up, address verified. Only the personal community, no user name chosen, no time zone chosen | not a member |`,
    `| member | \`${manifest.accounts.member}\` | Marc Member. Posts, comments, chat, course progress, notifications, direct messages. Was away for nine days | member |`,
    `| moderator | \`${manifest.accounts.moderator}\` | Maya Moderator. In the access group "Patrons" | moderator |`,
    `| admin | \`${manifest.accounts.admin}\` | Adam Admin. The role between the owner and the moderator | admin |`,
    `| owner | \`${manifest.accounts.owner}\` | Olivia Owner. Owns both communities | owner |`,
    '',
    'The application has three stored roles (`admin`, `moderator`, `member`, in `App\\Enums\\KnowiiCommunityMemberRole`) and the owner of the community.',
    'Other accounts of the seed: `atlas-tomas@example.com` and `atlas-lena@example.com` (members who write content),',
    `\`${manifest.accounts.unverified}\` (address not verified: the "verify your email address" page), \`${manifest.accounts['two-factor']}\` (two-factor authentication is on: the challenge page).`,
    '',
    '## How to make it again',
    '',
    '1. The seed (safe to run again; it also puts back the read and unread state that a visit changes), from the root of the repository:',
    '',
    '   ```sh',
    '   echo "docker compose exec -T -u sail laravel.test php artisan tinker tools/screen-atlas/atlas-seed.php" | newgrp docker',
    '   ```',
    '',
    '2. The front-end build, when the code changed (the pictures need the build, not the dev server), from the root of the repository:',
    '',
    '   ```sh',
    '   echo "docker compose exec -T -u sail laravel.test npm run build" | newgrp docker',
    '   ```',
    '',
    '3. The tool, from `tools/screen-atlas` (it runs the seed again before each width and theme):',
    '',
    '   ```sh',
    '   npm ci',
    '   node atlas.mjs',
    '   ```',
    '',
    '   One part only: `node atlas.mjs --only settings/ --widths 1920 --themes light`. The options are at the top of `atlas.mjs`.',
    '   The screens are in `screens.json`: one entry per screen and state.',
    '',
    '4. To remove the data of the seed (not run): `php artisan tinker tools/screen-atlas/atlas-unseed.php` in the container.',
    '',
    '## What to know before you look',
    '',
    ...(data.notes ?? []).map((note) => `- ${note}`),
    ...body,
    '',
  );
  if (missing.length > 0) {
    lines.push(
      '## Missing pictures',
      '',
      ...missing.map(
        (file) =>
          `- \`${file}\`${last.shots?.[file]?.error ? ': ' + String(last.shots[file].error).replaceAll('\n', ' ').slice(0, 200) : ''}`,
      ),
      '',
    );
  }
  fs.mkdirSync(OUT, { recursive: true });
  fs.writeFileSync(path.join(OUT, 'index.md'), lines.join('\n'));
  console.log(`index.md: ${rows} rows, ${present} pictures of ${expected}`);
}

if (flag('count')) {
  console.log(JSON.stringify(counts()));
  process.exit(0);
}
if (flag('index-only')) {
  writeIndex();
  process.exit(0);
}

// ---------------------------------------------------------------------------------------------------------------------
// The browser
// ---------------------------------------------------------------------------------------------------------------------

const results = fs.existsSync(RESULTS) ? JSON.parse(fs.readFileSync(RESULTS, 'utf8')) : { shots: {} };
results.shots ??= {};
const saveResults = () => fs.writeFileSync(RESULTS, JSON.stringify(results, null, 1));

function runSeed() {
  if (flag('no-seed')) {
    return;
  }
  const output = execSync(SEED_COMMAND, { cwd: REPO, shell: '/bin/bash', stdio: ['ignore', 'pipe', 'pipe'], timeout: 300000 }).toString();
  if (!output.includes('[atlas-seed] done')) {
    throw new Error('The seed did not finish:\n' + output.slice(-1500));
  }
}

async function newContext(browser, width, theme, timezone = 'Europe/Brussels') {
  const context = await browser.newContext({
    viewport: VIEWPORTS[width],
    colorScheme: theme,
    locale: 'en-GB',
    timezoneId: timezone,
    reducedMotion: 'reduce',
    // "Copy the link" writes to the clipboard
    permissions: ['clipboard-read', 'clipboard-write'],
  });
  // The theme that the member chose, before the first paint (the same key as the application)
  await context.addInitScript(
    ([key, value]) => {
      try {
        window.localStorage.setItem(key, value);
      } catch {
        // No storage on this page
      }
    },
    [THEME_STORAGE_KEY, theme],
  );
  // Nothing leaves this machine
  await context.route('**/*', (route) => {
    let host = '';
    try {
      host = new URL(route.request().url()).hostname;
    } catch {
      // data: and blob: addresses
    }
    if (host === '' || ALLOWED_HOSTS.has(host)) {
      return route.fallback();
    }
    return route.abort();
  });
  context.setDefaultTimeout(12000);
  context.setDefaultNavigationTimeout(90000);
  return context;
}

/** The six digits of an authenticator app (RFC 6238: SHA-1, 30 seconds) for the two-factor account of the seed */
function oneTimeCode(secret) {
  const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  let bits = '';
  for (const letter of secret.replace(/=+$/, '').toUpperCase()) {
    bits += alphabet.indexOf(letter).toString(2).padStart(5, '0');
  }
  const key = Buffer.from(bits.match(/.{8}/g).map((byte) => parseInt(byte, 2)));
  const counter = Buffer.alloc(8);
  counter.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 30000)));
  const digest = createHmac('sha1', key).update(counter).digest();
  const offset = digest[digest.length - 1] & 0xf;
  return String((digest.readUInt32BE(offset) & 0x7fffffff) % 1000000).padStart(6, '0');
}

async function signIn(page, email) {
  await page.goto(BASE + '/login', { waitUntil: 'load' });
  await page.fill('#email', email);
  await page.fill('#password', manifest.password);
  await Promise.all([page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 90000 }), page.click('button[type=submit]')]);
  if (new URL(page.url()).pathname.startsWith('/two-factor-challenge')) {
    await page.locator('#code').waitFor({ state: 'visible' });
    await page.fill('#code', oneTimeCode(manifest.twoFactorSecret));
    await Promise.all([
      page.waitForURL((url) => !url.pathname.startsWith('/two-factor-challenge'), { timeout: 90000 }),
      page.click('button[type=submit]'),
    ]);
  }
}

/** Wait until the document did not change for a moment (the lists that come with a web socket, the late requests) */
async function domQuiet(page, quietFor = 700, atMost = 9000) {
  await page
    .evaluate(
      ([quiet, most]) =>
        new Promise((resolve) => {
          let timer = setTimeout(done, quiet);
          const observer = new MutationObserver(() => {
            clearTimeout(timer);
            timer = setTimeout(done, quiet);
          });
          const limit = setTimeout(done, most);
          function done() {
            observer.disconnect();
            clearTimeout(timer);
            clearTimeout(limit);
            resolve();
          }
          observer.observe(document.body, { subtree: true, childList: true, attributes: true, characterData: true });
        }),
      [quietFor, atMost],
    )
    .catch(ignore);
}

/** Count the requests of a page that are not answered yet (the "networkidle" of the browser only fires once per page) */
function trackRequests(page) {
  const open = new Set();
  const state = { open, lastChange: Date.now() };
  const touch = () => {
    state.lastChange = Date.now();
  };
  page.on('request', (request) => {
    open.add(request);
    touch();
  });
  for (const event of ['requestfinished', 'requestfailed']) {
    page.on(event, (request) => {
      open.delete(request);
      touch();
    });
  }
  page.on('framenavigated', () => {
    open.clear();
    touch();
  });
  page.atlasRequests = state;
  return state;
}

/** No request open for a moment */
async function requestsQuiet(page, quietFor = 600, atMost = 20000) {
  const state = page.atlasRequests;
  const start = Date.now();
  while (Date.now() - start < atMost) {
    if (state.open.size === 0 && Date.now() - state.lastChange >= quietFor) {
      return;
    }
    await page.waitForTimeout(100);
  }
}

/** Wait until the page is quiet: requests done, fonts and images there, no progress bar, no busy region */
async function settle(page, { network = true } = {}) {
  if (network) {
    await requestsQuiet(page);
  } else {
    await page.waitForTimeout(1500);
  }
  await page
    .evaluate(async () => {
      await document.fonts.ready;
      // Lazy pictures load when they come near the viewport: go down the page once, then back
      const height = document.documentElement.scrollHeight;
      for (let y = 0; y < height; y += 600) {
        window.scrollTo(0, y);
        await new Promise((resolve) => setTimeout(resolve, 30));
      }
      window.scrollTo(0, 0);
    })
    .catch(ignore);
  if (network) {
    // The application starts some data requests after the first paint, and the list of the members who are online
    // comes with the web socket: wait until the requests and the document are both quiet
    for (let round = 0; round < 4; round++) {
      await domQuiet(page);
      if (page.atlasRequests.open.size === 0 && Date.now() - page.atlasRequests.lastChange >= 600) {
        break;
      }
      await requestsQuiet(page);
    }
    await page
      .waitForFunction(
        () =>
          [...document.images].every((image) => image.complete) &&
          document.querySelector('#nprogress') === null &&
          document.querySelector('[aria-busy="true"]') === null &&
          // The member who looks is online: the empty list is the state before the presence channel answers
          !/Nobody is online/.test(document.querySelector('aside')?.innerText ?? ''),
        null,
        { timeout: 8000 },
      )
      .catch(ignore);
    await domQuiet(page, 300, 3000);
  } else {
    await domQuiet(page, 500, 4000);
  }
  await page.waitForTimeout(200);
}

async function runStep(page, step) {
  const [verb, first, second] = step;
  switch (verb) {
    case 'click':
      await page
        .locator(first)
        .first()
        .click(second ?? {});
      break;
    case 'clickAll':
      // Every element that matches, one after the other (for example every "Show more")
      for (const element of await page.locator(first).all()) {
        await element.click().catch(ignore);
      }
      break;
    case 'fill':
      await page.locator(first).first().fill(second);
      break;
    case 'type':
      await page.locator(first).first().pressSequentially(second, { delay: 20 });
      break;
    case 'check':
      await page
        .locator(first)
        .first()
        .check(second ?? {});
      break;
    case 'press':
      if (second === undefined) {
        await page.keyboard.press(first);
      } else {
        await page.locator(first).first().press(second);
      }
      break;
    case 'hover':
      await page.locator(first).first().hover();
      break;
    case 'focus':
      await page.locator(first).first().focus();
      break;
    case 'scroll':
      await page.locator(first).first().scrollIntoViewIfNeeded();
      break;
    case 'wait':
      await page.waitForTimeout(first);
      break;
    case 'waitFor':
      await page
        .locator(first)
        .first()
        .waitFor({ state: 'visible', timeout: second ?? 20000 });
      break;
    case 'waitDialog':
      // A dialog or a drawer is open (its container can have no size of its own: wait for it to be in the page)
      await page
        .locator('[role=dialog], [role=alertdialog]')
        .first()
        .waitFor({ state: 'attached', timeout: first ?? 20000 });
      await page.waitForTimeout(450);
      break;
    case 'waitGone':
      await page
        .locator(first)
        .first()
        .waitFor({ state: 'hidden', timeout: second ?? 20000 });
      break;
    case 'waitUrl':
      await page.waitForURL((url) => new RegExp(first).test(url.pathname + url.search), { timeout: 60000 });
      break;
    case 'goto':
      await page.goto(BASE + first, { waitUntil: 'load' });
      break;
    case 'settle':
      await settle(page);
      break;
    case 'submit':
      // Send the form without the bubbles of the browser (a page screenshot does not hold them): the errors that
      // the application itself shows are in the picture
      await page
        .locator(first)
        .first()
        .evaluate((form) => {
          form.noValidate = true;
          form.requestSubmit();
        });
      break;
    case 'eval':
      await page.evaluate(first);
      break;
    default:
      throw new Error(`Unknown step "${verb}"`);
  }
}

const TECHNICAL_ERROR = JSON.stringify({ category: 'technical', type: 'internalError', message: 'Internal Server Error', errors: {} });

/** The data requests of the entry: "delay" holds them until the picture is taken, "error" answers 500 */
async function installMocks(page, mocks) {
  const held = [];
  const hits = mocks.map(() => 0);
  for (const [index, mock] of mocks.entries()) {
    const pattern = new RegExp(mock.url);
    await page.route(
      (url) => pattern.test(url.pathname + url.search),
      async (route) => {
        if ((mock.method ?? 'GET') !== route.request().method()) {
          return route.fallback();
        }
        hits[index]++;
        if (mock.mode === 'error') {
          return route.fulfill({ status: 500, contentType: 'application/json', body: TECHNICAL_ERROR });
        }
        // delay: answer after the picture
        await new Promise((resolve) => held.push(resolve));
        return route.abort().catch(ignore);
      },
    );
  }
  const release = async () => {
    held.forEach((free) => free());
    await page.unrouteAll({ behavior: 'ignoreErrors' });
  };
  // The data requests of the entry that the page never made: then the picture does not show the state
  release.unused = () => mocks.filter((mock, index) => hits[index] === 0).map((mock) => mock.url);
  return release;
}

async function outline(page) {
  return page.evaluate(() => {
    const text = (element) =>
      (element.getAttribute('aria-label') || element.innerText || element.getAttribute('title') || '')
        .trim()
        .replace(/\s+/g, ' ')
        .slice(0, 70);
    const visible = (selector, root = document) =>
      [...root.querySelectorAll(selector)].filter((element) => element.getClientRects().length > 0 && !element.closest('footer'));
    const layer = document.querySelector('[role=dialog], [role=menu], [role=listbox], [role=alertdialog]');
    const root = layer ?? document;
    return {
      layer: layer ? `${layer.getAttribute('role')} "${text(layer).slice(0, 40)}"` : null,
      headings: visible('h1,h2,h3', root)
        .map((element) => `${element.tagName}:${text(element)}`)
        .slice(0, 30),
      buttons: visible('button,[role=button],[role=menuitem],[role=option],[role=tab],[role=radio],[role=switch]', root)
        .map(text)
        .slice(0, 60),
      fields: visible('input,textarea,select', root)
        .map((element) => `${element.tagName}#${element.id}[${element.getAttribute('type') ?? ''}${element.required ? ',required' : ''}]`)
        .slice(0, 40),
      forms: visible('form', root).map((element) => `form#${element.id}${element.noValidate ? '[novalidate]' : ''}`),
      alerts: visible('[role=alert],[role=status],[aria-live]', document).map(text).filter(Boolean).slice(0, 10),
    };
  });
}

async function component(page) {
  return page
    .evaluate(() => {
      try {
        const raw = document.querySelector('script[data-page]')?.textContent ?? document.getElementById('app')?.dataset.page;
        return raw ? JSON.parse(raw).component : null;
      } catch {
        return null;
      }
    })
    .catch(() => null);
}

async function shoot(page, screen, role, width, theme) {
  const file = fileOf(screen, role, width, theme);
  const target = path.join(OUT, file);
  const record = { at: new Date().toISOString(), address: screen.path };
  const noise = [];
  const onConsole = (message) => {
    if (message.type() === 'error') {
      noise.push(message.text().slice(0, 300));
    }
  };
  const onPageError = (error) => noise.push('page error: ' + String(error).slice(0, 300));
  const onResponse = (response) => {
    if (response.status() >= 400 && response.url().startsWith(BASE)) {
      noise.push(`${response.status()} ${response.request().method()} ${response.url().slice(BASE.length)}`);
    }
  };
  page.on('console', onConsole);
  page.on('pageerror', onPageError);
  page.on('response', onResponse);
  const release = await installMocks(page, screen.mocks ?? []);
  const quiet = !(screen.mocks ?? []).some((mock) => mock.mode === 'delay');
  try {
    const response = await page.goto(BASE + screen.path, { waitUntil: 'load' });
    record.status = response?.status() ?? null;
    await settle(page, { network: quiet });
    record.component = await component(page);
    for (const step of screen.steps ?? []) {
      await runStep(page, step);
    }
    if ((screen.steps ?? []).length > 0) {
      await settle(page, { network: quiet && screen.settle !== false });
    }
    record.url = page.url().slice(BASE.length);
    if (probe) {
      const seenNow = await outline(page);
      console.log(`\n### ${file}\n${record.status} ${record.url} [${record.component}]`);
      console.log(
        `  layer: ${seenNow.layer}\n  alerts: ${seenNow.alerts.join(' | ')}\n  buttons: ${seenNow.buttons.slice(0, 25).join(' | ')}\n  fields: ${seenNow.fields.slice(0, 14).join(' ')}`,
      );
    }
    fs.mkdirSync(path.dirname(target), { recursive: true });
    await page.screenshot({ path: target, fullPage: screen.fullPage !== false, animations: 'disabled', caret: 'hide' });

    // What the entry expects: a status, the page component, or where the visitor lands
    const expectedStatus = screen.expect?.status ?? 200;
    const expectedComponent =
      screen.expect?.component ?? (screen.component ? screen.component.replace(/^Pages\//, '').replace(/\.tsx$/, '') : null);
    const problems = [];
    if (record.status !== expectedStatus) {
      problems.push(`status ${record.status}, not ${expectedStatus}`);
    }
    if (expectedStatus === 200 && expectedComponent !== null && record.component !== expectedComponent) {
      problems.push(`page ${record.component}, not ${expectedComponent}`);
    }
    if (screen.expect?.url && !new RegExp(screen.expect.url).test(record.url)) {
      problems.push(`address ${record.url}, not ${screen.expect.url}`);
    }
    for (const unused of release.unused()) {
      problems.push(`the page did not ask for ${unused}`);
    }
    if (problems.length > 0) {
      record.problem = problems.join('; ');
    }
    record.ok = true;
  } catch (error) {
    record.ok = false;
    record.error = String(error?.message ?? error)
      .split('\n')[0]
      .slice(0, 400);
    fs.mkdirSync(path.dirname(target), { recursive: true });
    // What the page showed when the step failed, next to the tool (not in the atlas)
    await page.screenshot({ path: path.join(HERE, 'failures', file.replaceAll('/', '__')), fullPage: true }).catch(ignore);
  } finally {
    await release().catch(ignore);
    // What the page keeps in the browser (collapsed threads, drafts, closed cards) must not reach the next screen. The
    // theme comes back with the next page: the script of the context sets it before the first paint
    await page
      .evaluate(() => {
        window.localStorage.clear();
        window.sessionStorage.clear();
      })
      .catch(ignore);
    page.off('console', onConsole);
    page.off('pageerror', onPageError);
    page.off('response', onResponse);
  }
  // Requests to outside hosts are refused on purpose: not a defect of the product
  record.noise = [...new Set(noise)].filter((line) => !/net::ERR_FAILED|net::ERR_ABORTED/.test(line)).slice(0, 12);
  results.shots[file] = record;
  const mark = record.ok ? (record.problem ? 'CHECK' : 'ok') : 'FAILED';
  console.log(`${mark.padEnd(6)} ${file}${record.problem ? '  <- ' + record.problem : ''}${record.error ? '  <- ' + record.error : ''}`);
}

async function runRole(browser, role, width, theme) {
  const mine = selected.filter((screen) => shotsOf(screen).includes(role) && widthsOf(screen).includes(width));
  const todo = mine.filter((screen) => !(flag('resume') && fs.existsSync(path.join(OUT, fileOf(screen, role, width, theme)))));
  if (todo.length === 0) {
    return;
  }
  const context = await newContext(browser, width, theme);
  const page = await context.newPage();
  trackRequests(page);
  try {
    if (role !== 'visitor') {
      await signIn(page, manifest.accounts[role]);
    }
    for (const screen of todo) {
      if (screen.account || screen.fresh || screen.timezone) {
        // Its own session: another account of the seed, a flow that signs in, or a browser in another time zone
        const own = await newContext(browser, width, theme, screen.timezone);
        const ownPage = await own.newPage();
        trackRequests(ownPage);
        try {
          if (screen.account || (!screen.fresh && role !== 'visitor')) {
            await signIn(ownPage, manifest.accounts[screen.account ?? role]);
          }
          await shoot(ownPage, screen, role, width, theme);
        } catch (error) {
          results.shots[fileOf(screen, role, width, theme)] = { ok: false, error: String(error?.message ?? error).split('\n')[0] };
          console.log(`FAILED ${fileOf(screen, role, width, theme)}  <- ${String(error?.message ?? error).split('\n')[0]}`);
        } finally {
          await own.close();
        }
        continue;
      }
      await shoot(page, screen, role, width, theme);
      saveResults();
    }
  } finally {
    await context.close();
  }
}

fs.mkdirSync(path.join(HERE, 'failures'), { recursive: true });
const total = counts();
console.log(`${total.entries} screens in the data file, ${total.rows} rows (screen, state, role), ${total.pictures} pictures in this run`);
const browser = await chromium.launch();
try {
  for (const theme of themes) {
    for (const width of widths) {
      console.log(`\n=== ${width} px, ${theme} ===`);
      runSeed();
      const roles = ROLE_ORDER.filter((role) => onlyRoles === null || onlyRoles.includes(role));
      const queue = [...roles];
      await Promise.all(
        Array.from({ length: Math.min(concurrency, queue.length) }, async () => {
          while (queue.length > 0) {
            const role = queue.shift();
            try {
              await runRole(browser, role, width, theme);
            } catch (error) {
              console.log(`FAILED role ${role} at ${width} ${theme}: ${String(error?.message ?? error).split('\n')[0]}`);
            }
          }
        }),
      );
      saveResults();
    }
  }
} finally {
  await browser.close();
  saveResults();
}
if (!probe) {
  writeIndex();
}
const made = Object.values(results.shots).filter((shot) => shot.ok).length;
console.log(
  `\nDone. Pictures in the log of the last runs: ${made} taken, ${Object.values(results.shots).filter((shot) => !shot.ok).length} failed.`,
);
