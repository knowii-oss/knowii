// Accessibility tree snapshots (Playwright aria snapshot) and a heading / landmark outline of the main screens.
// node a11y-tree.mjs [role] [--width 1920]
import fs from 'node:fs';
import path from 'node:path';
import { launch, newContext, signIn, goto, step, OUT } from './a11y-lib.mjs';

const arg = (n, d) => (process.argv.includes('--' + n) ? process.argv[process.argv.indexOf('--' + n) + 1] : d);
const width = Number(arg('width', '1920'));
const role = process.argv[2] ?? 'member';
const g = '/community/atlas-garden';
const list = {
  visitor: [
    ['home', '/'],
    ['login', '/login'],
    ['register', '/register'],
    [
      'register-errors',
      '/register',
      [
        ['submit', 'form'],
        ['wait', 1500],
      ],
    ],
    ['community-public', g],
    ['post-public', g + '/space/announcements/post/welcome-to-atlas-garden'],
    ['page-public', g + '/space/welcome/page/start-here'],
  ],
  member: [
    ['dashboard', '/dashboard'],
    ['community-home', g],
    ['discussion', g + '/space/garden-talk'],
    ['post', g + '/space/garden-talk/post/my-tomatoes-have-yellow-leaves'],
    ['gallery', g + '/space/garden-talk/post/four-weeks-of-my-raised-bed-in-pictures'],
    ['chat', g + '/space/lounge'],
    ['course', g + '/space/balcony-course'],
    ['lesson', g + '/space/balcony-course/page/watering'],
    ['quiz', g + '/space/balcony-course/page/quiz-water-and-compost'],
    [
      'quiz-errors',
      g + '/space/balcony-course/page/quiz-water-and-compost',
      [
        ['click', 'role=button[name="Check my answers"]'],
        ['wait', 800],
      ],
    ],
    ['resources', g + '/space/reading-list'],
    ['resource', g + '/space/reading-list/resource/balcony-vegetables-guide'],
    ['notifications', '/notifications'],
    ['notif-settings', '/user/notifications'],
    ['profile', '/user/profile'],
    [
      'profile-errors',
      '/user/profile',
      [
        ['fill', '#username', 'x'],
        ['submit', 'form:has(#name)'],
        ['wait', 2000],
      ],
    ],
    ['messages', g + '/messages'],
    [
      'add-resource-errors',
      g + '/space/reading-list',
      [
        ['click', 'role=button[name="Add a resource"]'],
        ['waitDialog'],
        ['fill', 'role=dialog >> input >> nth=0', 'not an address'],
        ['press', 'Tab'],
        ['click', 'role=dialog >> button[type=submit]', { force: true }],
        ['wait', 800],
      ],
    ],
    [
      'composer-errors',
      g + '/space/garden-talk',
      [
        ['click', 'role=button[name="New post"]'],
        ['waitDialog'],
        ['fill', 'role=dialog >> input[type=text] >> nth=0', 'ab'],
        ['fill', 'role=dialog >> textarea >> nth=0', 'x'],
        ['fill', 'role=dialog >> textarea >> nth=0', ''],
        ['press', 'Tab'],
        ['wait', 500],
      ],
    ],
  ],
  owner: [
    ['settings', g + '/settings'],
    ['course', g + '/space/balcony-course'],
  ],
};

const dir = path.join(OUT, 'tree');
fs.mkdirSync(dir, { recursive: true });
const browser = await launch();
const context = await newContext(browser, { width, reducedMotion: 'reduce' });
const page = await context.newPage();
if (role !== 'visitor') await signIn(page, role);
for (const [name, address, steps] of list[role]) {
  try {
    await goto(page, address);
    for (const s of steps ?? []) await step(page, s);
    const snap = await page.locator('body').ariaSnapshot();
    fs.writeFileSync(path.join(dir, `${role}-${name}-${width}.yml`), snap);
    const outline = await page.evaluate(() => {
      const vis = (e) => e.getClientRects().length > 0 || e.closest('.sr-only');
      const hs = [...document.querySelectorAll('h1,h2,h3,h4,h5,h6,[role=heading]')]
        .filter(vis)
        .map((h) => `${h.tagName}${h.getAttribute('aria-level') ?? ''}:${h.innerText.trim().replace(/\s+/g, ' ').slice(0, 60)}`);
      const lm = [
        ...document.querySelectorAll(
          'header,nav,main,aside,footer,section[aria-label],section[aria-labelledby],[role=region],[role=navigation],[role=main],[role=banner],[role=contentinfo],[role=complementary],form[aria-label]',
        ),
      ]
        .filter(vis)
        .map(
          (e) =>
            `${e.tagName.toLowerCase()}${e.getAttribute('role') ? '[' + e.getAttribute('role') + ']' : ''}${e.getAttribute('aria-label') ? ' "' + e.getAttribute('aria-label') + '"' : ''}`,
        );
      const skipped = [];
      let prev = 0;
      for (const h of hs) {
        const lvl = Number(h[1]);
        if (prev && lvl > prev + 1) skipped.push(`${prev}->${h}`);
        prev = lvl;
      }
      const imgs = [...document.querySelectorAll('img')].filter((i) => !i.hasAttribute('alt')).map((i) => i.src.slice(-50));
      const lang = document.documentElement.lang;
      const title = document.title;
      return { title, lang, h1: hs.filter((h) => h.startsWith('H1')).length, headings: hs, skipped, landmarks: lm, imgsNoAlt: imgs };
    });
    console.log(`\n## ${role}/${name}`, JSON.stringify(outline));
  } catch (e) {
    console.log(`\n## ${role}/${name} ERROR`, String(e.message).split('\n')[0]);
  }
}
await browser.close();
