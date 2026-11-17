// axe-core pass (WCAG 2.0/2.1/2.2 A and AA tags) on the main screens, per role, theme and width.
// node a11y-axe.mjs [--themes light,dark] [--widths 1920,360] [--roles visitor,member,owner]
import fs from 'node:fs';
import path from 'node:path';
import { launch, newContext, signIn, goto, runAxe, step, OUT, ignore } from './a11y-lib.mjs';

const arg = (n, d) => (process.argv.includes('--' + n) ? process.argv[process.argv.indexOf('--' + n) + 1] : d);
const themes = arg('themes', 'light,dark').split(',');
const widths = arg('widths', '1920,360').split(',').map(Number);
const roles = arg('roles', 'visitor,member,owner').split(',');

const g = '/community/atlas-garden';
const screens = {
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
    [
      'login-errors',
      '/login',
      [
        ['submit', 'form'],
        ['wait', 1500],
      ],
    ],
    ['forgot', '/forgot-password'],
    ['contact', '/contact'],
    ['legal', '/privacy-policy'],
    ['community-public', g],
    ['pages-space', g + '/space/welcome'],
    ['page', g + '/space/welcome/page/start-here'],
    ['discussion-public', g + '/space/announcements'],
    ['post-public', g + '/space/announcements/post/welcome-to-atlas-garden'],
    ['members-only', g + '/space/garden-talk'],
    ['not-found', '/this-page-does-not-exist'],
  ],
  member: [
    ['dashboard', '/dashboard'],
    [
      'user-menu',
      '/dashboard',
      [
        ['click', 'role=button[name="Open the user menu"]'],
        ['waitFor', 'role=menu'],
      ],
    ],
    [
      'bell',
      '/dashboard',
      [
        ['click', 'role=button[name=/^Notifications(, \\d+ new)?$/]'],
        ['waitFor', '#notifications-panel'],
        ['wait', 1500],
      ],
    ],
    ['new-community', '/dashboard', [['click', 'role=button[name="New community"]'], ['waitDialog']]],
    ['community-home', g],
    ['about', g + '/about'],
    ['profile-other', g + '/member/atlas-tomas'],
    ['discussion', g + '/space/garden-talk'],
    ['composer', g + '/space/garden-talk', [['click', 'role=button[name="New post"]'], ['waitDialog']]],
    ['post', g + '/space/garden-talk/post/my-tomatoes-have-yellow-leaves'],
    [
      'post-reply',
      g + '/space/garden-talk/post/my-tomatoes-have-yellow-leaves',
      [
        ['click', 'role=button[name="Reply to Tomas Silva"]'],
        ['wait', 600],
      ],
    ],
    [
      'post-emoji',
      g + '/space/garden-talk/post/my-tomatoes-have-yellow-leaves',
      [
        ['click', 'role=button[name="Add a reaction to this post"]'],
        ['wait', 1200],
      ],
    ],
    ['post-gallery', g + '/space/garden-talk/post/four-weeks-of-my-raised-bed-in-pictures'],
    [
      'image-viewer',
      g + '/space/garden-talk/post/four-weeks-of-my-raised-bed-in-pictures',
      [
        ['click', 'a.image-gallery-item >> nth=1'],
        ['waitFor', 'role=button[name="Close the viewer"]'],
        ['wait', 800],
      ],
    ],
    ['chat', g + '/space/lounge'],
    ['chat-thread', g + '/space/lounge', [['click', 'role=button[name=/^5 replies/]'], ['waitDialog'], ['wait', 1500]]],
    [
      'chat-edit',
      g + '/space/lounge',
      [
        ['click', 'role=button[name="Edit the message by Marc Member"]'],
        ['wait', 600],
      ],
    ],
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
    ['add-resource', g + '/space/reading-list', [['click', 'role=button[name="Add a resource"]'], ['waitDialog']]],
    ['resource', g + '/space/reading-list/resource/balcony-vegetables-guide'],
    ['pages-member', g + '/space/welcome/page/community-guidelines'],
    ['messages', g + '/messages'],
    ['notifications', '/notifications'],
    ['notif-settings', '/user/notifications'],
    ['profile', '/user/profile'],
    ['delete-account', '/user/profile', [['click', 'role=button[name="Delete Account"]'], ['waitDialog']]],
  ],
  owner: [
    ['dashboard', '/dashboard'],
    ['community-home', g],
    ['settings', g + '/settings'],
    ['space-form', g + '/settings', [['click', 'role=button[name="Add a space"]'], ['waitDialog']]],
    [
      'role-menu',
      g + '/settings',
      [
        ['click', 'role=button[name="Role of Marc Member"]'],
        ['wait', 500],
      ],
    ],
    ['course', g + '/space/balcony-course'],
    ['new-lesson', g + '/space/balcony-course', [['click', 'role=button[name=/^(Add|New) (a )?lesson/]'], ['waitDialog']]],
    ['pages', g + '/space/welcome'],
    ['chat', g + '/space/lounge'],
    ['resources', g + '/space/reading-list'],
  ],
};

const out = path.join(OUT, 'axe.json');
const all = fs.existsSync(out) ? JSON.parse(fs.readFileSync(out, 'utf8')) : {};
const browser = await launch();
try {
  for (const theme of themes)
    for (const width of widths)
      for (const role of roles) {
        const context = await newContext(browser, { width, theme });
        const page = await context.newPage();
        if (role !== 'visitor') await signIn(page, role);
        for (const [name, address, steps] of screens[role]) {
          const key = `${role}/${name}/${width}/${theme}`;
          try {
            await goto(page, address);
            for (const s of steps ?? []) await step(page, s);
            const r = await runAxe(page);
            all[key] = { address, ...r };
            console.log(key, r.violations.map((v) => `${v.id}(${v.count})`).join(' '));
          } catch (e) {
            all[key] = { address, error: String(e.message).split('\n')[0] };
            console.log(key, 'ERROR', String(e.message).split('\n')[0]);
          }
          await page.evaluate(() => sessionStorage.clear()).catch(ignore);
          fs.writeFileSync(out, JSON.stringify(all, null, 1));
        }
        await context.close();
      }
} finally {
  await browser.close();
}
