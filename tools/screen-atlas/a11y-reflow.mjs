// Reflow at 320 px (1.4.10), text spacing (1.4.12), text resize 200% (1.4.4, root font size doubled at 1280 px)
// node a11y-reflow.mjs <mode: reflow|spacing|resize> [role]
import path from 'node:path';
import { launch, newContext, signIn, goto, step, OUT } from './a11y-lib.mjs';

const mode = process.argv[2] ?? 'reflow';
const role = process.argv[3] ?? 'member';
const g = '/community/atlas-garden';
const lists = {
  visitor: [
    ['home', '/'],
    ['login', '/login'],
    ['register', '/register'],
    ['community', g],
    ['post', g + '/space/announcements/post/welcome-to-atlas-garden'],
    ['page', g + '/space/welcome/page/start-here'],
    ['legal', '/privacy-policy'],
  ],
  member: [
    ['dashboard', '/dashboard'],
    ['community', g],
    ['discussion', g + '/space/garden-talk'],
    ['post', g + '/space/garden-talk/post/my-tomatoes-have-yellow-leaves'],
    ['post-long', g + '/space/garden-talk/post/a-full-guide-to-the-first-season-on-a-balcony'],
    ['gallery', g + '/space/garden-talk/post/four-weeks-of-my-raised-bed-in-pictures'],
    ['chat', g + '/space/lounge'],
    ['course', g + '/space/balcony-course'],
    ['lesson', g + '/space/balcony-course/page/watering'],
    ['quiz', g + '/space/balcony-course/page/quiz-water-and-compost'],
    ['resources', g + '/space/reading-list'],
    ['resource', g + '/space/reading-list/resource/balcony-vegetables-guide'],
    ['notifications', '/notifications'],
    ['notif-settings', '/user/notifications'],
    ['profile', '/user/profile'],
    ['messages', g + '/messages'],
    ['composer', g + '/space/garden-talk', [['click', 'role=button[name="New post"]'], ['waitDialog']]],
    [
      'bell',
      '/dashboard',
      [
        ['click', 'role=button[name=/^Notifications(, \\d+ new)?$/]'],
        ['waitFor', '#notifications-panel'],
        ['wait', 1200],
      ],
    ],
  ],
  owner: [
    ['settings', g + '/settings'],
    ['course', g + '/space/balcony-course'],
  ],
};
const SPACING = `* { line-height: 1.5 !important; letter-spacing: 0.12em !important; word-spacing: 0.16em !important; } p { margin-bottom: 2em !important; }`;

const size = mode === 'reflow' ? { width: 320, height: 640 } : { width: 1280, height: 900 };
const browser = await launch();
const context = await newContext(browser, { ...size, reducedMotion: 'reduce' });
const page = await context.newPage();
if (role !== 'visitor') await signIn(page, role);
for (const [name, address, steps] of lists[role]) {
  try {
    await goto(page, address);
    for (const s of steps ?? []) await step(page, s);
    const sheet = async (css) =>
      page.evaluate((c) => {
        const st = new CSSStyleSheet();
        st.replaceSync(c);
        document.adoptedStyleSheets = [...document.adoptedStyleSheets, st];
      }, css);
    if (mode === 'spacing') await sheet(SPACING);
    if (mode === 'resize') await sheet('html { font-size: 200% !important; }');
    await page.waitForTimeout(400);
    const r = await page.evaluate(() => {
      const vw = document.documentElement.clientWidth;
      const out = { scrollW: document.documentElement.scrollWidth, vw, over: [], clipped: [] };
      for (const el of document.querySelectorAll('body *')) {
        if (!(el instanceof HTMLElement) || el.getClientRects().length === 0) continue;
        if (el.closest('.sr-only,[aria-hidden=true]')) continue;
        const rc = el.getBoundingClientRect();
        const cs = getComputedStyle(el);
        // Wider than the viewport and not inside a scroller of its own
        if (rc.right > vw + 1 && rc.width > 0 && !el.closest('pre,table,[role=log],.overflow-x-auto,.overflow-auto')) {
          let p = el.parentElement,
            inScroller = false;
          while (p) {
            const s = getComputedStyle(p);
            if (/(auto|scroll|hidden)/.test(s.overflowX) && p !== document.body && p !== document.documentElement) {
              inScroller = true;
              break;
            }
            p = p.parentElement;
          }
          if (!inScroller)
            out.over.push(
              `${el.tagName}.${String(el.className).slice(0, 50)} r=${Math.round(rc.right)} "${(el.innerText || '').trim().slice(0, 30)}"`,
            );
        }
        // Text cut by its own box (not an ellipsis on purpose)
        const hasText = [...el.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim().length > 0);
        if (
          hasText &&
          /(hidden|clip)/.test(cs.overflow + cs.overflowY + cs.overflowX) &&
          cs.textOverflow !== 'ellipsis' &&
          !/line-clamp/.test(el.className)
        ) {
          if (el.scrollHeight > el.clientHeight + 2 || el.scrollWidth > el.clientWidth + 2)
            out.clipped.push(
              `${el.tagName}.${String(el.className).slice(0, 60)} ${el.scrollWidth}x${el.scrollHeight}>${el.clientWidth}x${el.clientHeight} "${el.innerText.trim().slice(0, 30)}"`,
            );
        }
      }
      out.over = [...new Set(out.over)].slice(0, 8);
      out.clipped = [...new Set(out.clipped)].slice(0, 8);
      return out;
    });
    console.log(`${role}/${name}`, JSON.stringify(r));
    await page.screenshot({ path: path.join(OUT, `${mode}-${role}-${name}.png`), fullPage: true });
  } catch (e) {
    console.log(`${role}/${name} ERROR`, String(e.message).split('\n')[0]);
  }
}
await browser.close();
