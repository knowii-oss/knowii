// Target size (2.5.8): interactive elements under 24 x 24 CSS px, with the spacing exception (a 24 px circle on the
// center of the target must not touch another target or its circle). Inline links in sentences are excepted.
import { launch, newContext, signIn, goto, step } from './a11y-lib.mjs';

const width = Number(process.argv[2] ?? '1920');
const g = '/community/atlas-garden';
const list = [
  ['visitor', 'home', '/'],
  ['visitor', 'login', '/login'],
  ['visitor', 'register', '/register'],
  ['member', 'dashboard', '/dashboard'],
  ['member', 'community', g],
  ['member', 'discussion', g + '/space/garden-talk'],
  ['member', 'post', g + '/space/garden-talk/post/my-tomatoes-have-yellow-leaves'],
  ['member', 'gallery', g + '/space/garden-talk/post/four-weeks-of-my-raised-bed-in-pictures'],
  ['member', 'chat', g + '/space/lounge'],
  ['member', 'course', g + '/space/balcony-course'],
  ['member', 'quiz', g + '/space/balcony-course/page/quiz-water-and-compost'],
  ['member', 'resources', g + '/space/reading-list'],
  ['member', 'resource', g + '/space/reading-list/resource/balcony-vegetables-guide'],
  ['member', 'notifications', '/notifications'],
  ['member', 'notif-settings', '/user/notifications'],
  ['member', 'profile', '/user/profile'],
  [
    'member',
    'bell',
    '/dashboard',
    [
      ['click', 'role=button[name=/^Notifications(, \\d+ new)?$/]'],
      ['waitFor', '#notifications-panel'],
      ['wait', 1200],
    ],
  ],
  [
    'member',
    'emoji',
    g + '/space/garden-talk/post/my-tomatoes-have-yellow-leaves',
    [
      ['click', 'role=button[name="Add a reaction to this post"]'],
      ['wait', 1200],
    ],
  ],
  ['owner', 'settings', g + '/settings'],
  ['owner', 'course', g + '/space/balcony-course'],
];
const browser = await launch();
let current = null;
let context, page;
for (const [role, name, address, steps] of list) {
  if (role !== current) {
    if (context) await context.close();
    context = await newContext(browser, { width, reducedMotion: 'reduce' });
    page = await context.newPage();
    if (role !== 'visitor') await signIn(page, role);
    current = role;
  }
  try {
    await goto(page, address);
    for (const s of steps ?? []) await step(page, s);
    const small = await page.evaluate(() => {
      const sel =
        'a[href],button,input:not([type=hidden]),select,textarea,[role=button],[role=link],[role=checkbox],[role=radio],[role=switch],[role=tab],[role=option],[role=menuitem],summary,[tabindex]:not([tabindex="-1"])';
      const els = [...document.querySelectorAll(sel)].filter(
        (e) => e.getClientRects().length && !e.closest('[aria-hidden=true]') && getComputedStyle(e).visibility !== 'hidden',
      );
      const boxes = els.map((e) => ({ e, r: e.getBoundingClientRect() }));
      const out = [];
      for (const { e, r } of boxes) {
        if (r.width >= 24 && r.height >= 24) continue;
        if (r.width === 0 || r.height === 0) continue;
        // Inline link inside a sentence: excepted
        if (
          e.tagName === 'A' &&
          getComputedStyle(e).display === 'inline' &&
          e.parentElement &&
          /\S/.test(
            [...e.parentElement.childNodes]
              .filter((n) => n.nodeType === 3)
              .map((n) => n.textContent)
              .join(''),
          )
        )
          continue;
        if (e.tagName === 'INPUT' && (e.type === 'checkbox' || e.type === 'radio') && e.labels?.length) {
          const lr = e.labels[0].getBoundingClientRect();
          if (lr.height >= 24) continue;
        }
        const cx = r.left + r.width / 2,
          cy = r.top + r.height / 2;
        let clash = null;
        for (const o of boxes) {
          if (o.e === e || o.e.contains(e) || e.contains(o.e)) continue;
          // distance from our center to the other rectangle
          const dx = Math.max(o.r.left - cx, 0, cx - o.r.right);
          const dy = Math.max(o.r.top - cy, 0, cy - o.r.bottom);
          const ocx = o.r.left + o.r.width / 2,
            ocy = o.r.top + o.r.height / 2;
          const oSmall = o.r.width < 24 || o.r.height < 24;
          if (Math.hypot(dx, dy) < 12 || (oSmall && Math.hypot(cx - ocx, cy - ocy) < 24)) {
            clash = (o.e.getAttribute('aria-label') || o.e.innerText || o.e.tagName).trim().slice(0, 30);
            break;
          }
        }
        const nm = (e.getAttribute('aria-label') || e.innerText || e.getAttribute('title') || e.tagName)
          .trim()
          .replace(/\s+/g, ' ')
          .slice(0, 40);
        out.push(
          `${Math.round(r.width)}x${Math.round(r.height)} ${e.tagName} "${nm}"${clash ? ' CLASH with "' + clash + '"' : ' (spacing ok)'}`,
        );
      }
      return [...new Set(out)].slice(0, 25);
    });
    console.log(`\n## ${role}/${name}\n  ` + small.join('\n  '));
  } catch (e) {
    console.log(`## ${role}/${name} ERROR ${String(e.message).split('\n')[0]}`);
  }
}
await browser.close();
