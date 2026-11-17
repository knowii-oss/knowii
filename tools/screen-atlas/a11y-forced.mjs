// Forced colors (high contrast) screenshots of the main screens, viewport only, light
import path from 'node:path';
import { launch, newContext, signIn, goto, step, OUT } from './a11y-lib.mjs';

const g = '/community/atlas-garden';
const list = [
  ['community', g],
  ['post', g + '/space/garden-talk/post/my-tomatoes-have-yellow-leaves', [['scroll', 'role=button[name="Add a reaction to this post"]']]],
  ['chat', g + '/space/lounge'],
  ['notif-settings', '/user/notifications'],
  ['composer', g + '/space/garden-talk', [['click', 'role=button[name="New post"]'], ['waitDialog']]],
  ['course', g + '/space/balcony-course'],
  [
    'quiz-errors',
    g + '/space/balcony-course/page/quiz-water-and-compost',
    [
      ['click', 'role=button[name="Check my answers"]'],
      ['wait', 800],
    ],
  ],
  ['profile', '/user/profile'],
  [
    'dashboard-focus',
    '/dashboard',
    [
      ['press', 'Tab'],
      ['press', 'Tab'],
      ['press', 'Tab'],
    ],
  ],
  ['notifications', '/notifications'],
];
const browser = await launch();
const context = await newContext(browser, { width: 1280, height: 900, forcedColors: 'active', reducedMotion: 'reduce' });
const page = await context.newPage();
await signIn(page, 'member');
for (const [name, address, steps] of list) {
  try {
    await goto(page, address);
    for (const s of steps ?? []) await step(page, s);
    await page.waitForTimeout(500);
    await page.screenshot({ path: path.join(OUT, `forced-${name}.png`) });
    console.log('ok', name);
  } catch (e) {
    console.log(name, 'ERROR', String(e.message).split('\n')[0]);
  }
}
await browser.close();
