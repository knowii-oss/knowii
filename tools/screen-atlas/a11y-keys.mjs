// Keyboard-only checks: Tab order, visible focus, Escape, return of the focus.
// node a11y-keys.mjs <scenario> [--width 1920] [--theme light]
import { launch, newContext, signIn, goto, focusInfo } from './a11y-lib.mjs';

const arg = (n, d) => (process.argv.includes('--' + n) ? process.argv[process.argv.indexOf('--' + n) + 1] : d);
const width = Number(arg('width', '1920'));
const theme = arg('theme', 'light');
const which = process.argv[2];
const g = '/community/atlas-garden';

const browser = await launch();
const context = await newContext(browser, { width, theme, reducedMotion: 'reduce' });
const page = await context.newPage();
const log = (...a) => console.log(...a);
const f = async (label = '') => {
  const i = await focusInfo(page);
  log(label, JSON.stringify(i));
  return i;
};
const tabs = async (n, label = 'tab') => {
  const out = [];
  for (let k = 0; k < n; k++) {
    await page.keyboard.press('Tab');
    await page.waitForTimeout(60);
    const i = await focusInfo(page);
    out.push(i);
    const noRing = i.outline && i.outline.startsWith('none') && !i.shadow;
    if (!i.rect) {
      log(`${label}${k + 1}`, 'BODY');
      continue;
    }
    log(
      `${label}${k + 1}`,
      i.tag,
      i.role ?? '',
      JSON.stringify(i.name),
      i.rect.join(','),
      noRing ? 'NO-RING' : '',
      i.covered ? 'COVERED:' + i.covered : '',
      i.inView === false ? 'OUT-OF-VIEW' : '',
    );
  }
  return out;
};
const focusOn = async (selector) => {
  await page.locator(selector).first().focus();
  await f('focused ->');
};

try {
  switch (which) {
    case 'visitor-home':
      await goto(page, '/');
      await tabs(30);
      break;
    case 'visitor-post':
      await goto(page, g + '/space/announcements/post/welcome-to-atlas-garden');
      await tabs(40);
      break;
    case 'login':
      await goto(page, '/login');
      await tabs(14);
      break;
    case 'register':
      await goto(page, '/register');
      await tabs(16);
      break;
    case 'member-post': {
      await signIn(page, 'member');
      await goto(page, g + '/space/garden-talk/post/my-tomatoes-have-yellow-leaves');
      await tabs(Number(arg('n', '90')));
      break;
    }
    case 'member-community': {
      await signIn(page, 'member');
      await goto(page, g);
      await tabs(Number(arg('n', '45')));
      break;
    }
    case 'dialogs': {
      await signIn(page, 'member');
      // 1 New post dialog by keyboard
      await goto(page, g + '/space/garden-talk');
      await focusOn('role=button[name="New post"]');
      await page.keyboard.press('Enter');
      await page.waitForTimeout(700);
      await f('after open (New post)');
      await tabs(14, 'dlg');
      await page.keyboard.press('Escape');
      await page.waitForTimeout(800);
      await f('after Escape (New post)');
      // 2 user menu
      await focusOn('role=button[name="Open the user menu"]');
      await page.keyboard.press('Enter');
      await page.waitForTimeout(400);
      await f('menu open');
      await page.keyboard.press('ArrowDown');
      await f('menu ArrowDown');
      await page.keyboard.press('Escape');
      await page.waitForTimeout(400);
      await f('menu after Escape');
      // 3 main menu drawer
      await focusOn('role=button[name="Open the main menu"]');
      await page.keyboard.press('Enter');
      await page.waitForTimeout(600);
      await f('drawer open');
      await tabs(4, 'drw');
      await page.keyboard.press('Escape');
      await page.waitForTimeout(800);
      await f('drawer after Escape');
      // 4 bell panel
      await focusOn('role=button[name=/^Notifications/]');
      await page.keyboard.press('Enter');
      await page.waitForTimeout(1500);
      await f('bell open');
      await tabs(6, 'bell');
      await page.keyboard.press('Escape');
      await page.waitForTimeout(600);
      await f('bell after Escape');
      // 5 space notification level menu
      await focusOn('role=button[name=/^Notifications: /]');
      await page.keyboard.press('Enter');
      await page.waitForTimeout(600);
      await f('level open');
      await tabs(3, 'lvl');
      await page.keyboard.press('Escape');
      await page.waitForTimeout(600);
      await f('level after Escape');
      break;
    }
    case 'post-widgets': {
      await signIn(page, 'member');
      const url = g + '/space/garden-talk/post/my-tomatoes-have-yellow-leaves';
      await goto(page, url);
      // emoji picker
      await focusOn('role=button[name="Add a reaction to this post"]');
      await page.keyboard.press('Enter');
      await page.waitForTimeout(1200);
      await f('picker open');
      await tabs(5, 'pick');
      await page.keyboard.press('ArrowRight');
      await f('picker ArrowRight');
      await page.keyboard.press('ArrowDown');
      await f('picker ArrowDown');
      await page.keyboard.press('Escape');
      await page.waitForTimeout(600);
      await f('picker after Escape');
      // who reacted
      const who = page.locator('role=button[name="See who reacted to this post"]');
      if (await who.count()) {
        await who.first().focus();
        await page.keyboard.press('Enter');
        await page.waitForTimeout(1200);
        await f('who open');
        await page.keyboard.press('Escape');
        await page.waitForTimeout(800);
        await f('who after Escape');
      }
      // collapse all, then the carets
      await focusOn('role=button[name="Collapse all"]');
      await page.keyboard.press('Enter');
      await page.waitForTimeout(500);
      await f('after collapse all');
      await tabs(8, 'col');
      // reply
      await goto(page, url);
      await focusOn('role=button[name="Reply to Tomas Silva"]');
      await page.keyboard.press('Enter');
      await page.waitForTimeout(700);
      await f('reply open');
      await page.keyboard.press('Escape');
      await page.waitForTimeout(500);
      await f('reply after Escape');
      // copy link toast
      await focusOn('role=button[name="Copy the link to the comment by Tomas Silva"]');
      await page.keyboard.press('Enter');
      await page.waitForTimeout(500);
      await f('after copy');
      log(
        'toast region',
        await page.evaluate(() =>
          [...document.querySelectorAll('[aria-live],[role=status],[role=alert]')].map(
            (e) => `${e.tagName}[${e.getAttribute('aria-live') ?? e.getAttribute('role')}] ${e.innerText.slice(0, 80)}`,
          ),
        ),
      );
      break;
    }
    case 'gallery': {
      await signIn(page, 'member');
      await goto(page, g + '/space/garden-talk/post/four-weeks-of-my-raised-bed-in-pictures');
      await focusOn('a.image-gallery-item >> nth=1');
      await page.keyboard.press('Enter');
      await page.waitForTimeout(1000);
      await f('viewer open');
      log('viewer', await page.evaluate(() => document.querySelector('[role=dialog]')?.outerHTML.slice(0, 900)));
      await tabs(6, 'view');
      await page.keyboard.press('ArrowRight');
      await page.waitForTimeout(300);
      log('after ArrowRight', await page.evaluate(() => document.querySelector('[role=dialog]')?.innerText.slice(0, 200)));
      await page.keyboard.press('Escape');
      await page.waitForTimeout(800);
      await f('viewer after Escape');
      break;
    }
    case 'chat': {
      await signIn(page, 'member');
      await goto(page, g + '/space/lounge');
      await tabs(Number(arg('n', '30')));
      await focusOn('role=button[name=/^5 replies/]');
      await page.keyboard.press('Enter');
      await page.waitForTimeout(1500);
      await f('thread open');
      await tabs(10, 'thr');
      await page.keyboard.press('Escape');
      await page.waitForTimeout(800);
      await f('thread after Escape');
      await focusOn('role=button[name="Edit the message by Marc Member"]');
      await page.keyboard.press('Enter');
      await page.waitForTimeout(700);
      await f('edit open');
      await page.keyboard.press('Escape');
      await page.waitForTimeout(600);
      await f('edit after Escape');
      break;
    }
    case 'emoji': {
      await signIn(page, 'member');
      await goto(page, g + '/space/garden-talk/post/my-tomatoes-have-yellow-leaves');
      await focusOn('role=button[name="Add a reaction to this post"]');
      await page.keyboard.press('Enter');
      await page.waitForTimeout(1500);
      await f('picker open');
      const pickerBox = async () =>
        page.evaluate(() => {
          const d = document.querySelector('[role=dialog][aria-label="Choose a reaction"]');
          if (!d) return 'closed';
          const r = d.getBoundingClientRect();
          return `open at ${Math.round(r.left)},${Math.round(r.top)},${Math.round(r.width)}x${Math.round(r.height)} viewport ${innerWidth}x${innerHeight} active=${document.querySelector('input[role=combobox]')?.getAttribute('aria-activedescendant')}`;
        });
      log(await pickerBox());
      await page.keyboard.press('ArrowRight');
      await page.keyboard.press('ArrowDown');
      log(
        'after arrows',
        await pickerBox(),
        await page.evaluate(() =>
          document
            .getElementById(document.querySelector('input[role=combobox]').getAttribute('aria-activedescendant'))
            ?.getAttribute('aria-label'),
        ),
      );
      await tabs(4, 'pick');
      log('after 4 tabs', await pickerBox());
      await page.keyboard.press('Escape');
      await page.waitForTimeout(400);
      log('after Escape outside', await pickerBox());
      await f('focus');
      // Enter to pick: focus after a pick
      await goto(page, g + '/space/garden-talk/post/my-tomatoes-have-yellow-leaves');
      await focusOn('role=button[name="Add a reaction to this post"]');
      await page.keyboard.press('Enter');
      await page.waitForTimeout(1500);
      await page.keyboard.press('Escape');
      await page.waitForTimeout(400);
      await f('after Escape inside');
      break;
    }
    case 'collapse': {
      await signIn(page, 'member');
      await goto(page, g + '/space/garden-talk/post/my-tomatoes-have-yellow-leaves');
      await page.evaluate(() => localStorage.clear());
      await goto(page, g + '/space/garden-talk/post/my-tomatoes-have-yellow-leaves');
      await focusOn('role=button[name="Collapse all"]');
      await page.keyboard.press('Enter');
      await page.waitForTimeout(500);
      await f('after Collapse all');
      await focusOn('role=button[name="Expand all"]');
      await page.keyboard.press('Enter');
      await page.waitForTimeout(500);
      await f('after Expand all');
      await page.evaluate(() => localStorage.clear());
      break;
    }
    case 'loadmore': {
      await signIn(page, 'member');
      await goto(page, g + '/space/garden-talk');
      const lm = page.locator('role=button[name="Load more posts"]');
      log('load more buttons', await lm.count());
      if (await lm.count()) {
        await lm.first().focus();
        await page.keyboard.press('Enter');
        await page.waitForTimeout(30);
        await f('during load');
        await page.waitForTimeout(2000);
        await f('after load');
        log('still there', await lm.count());
      }
      // the same at the notifications page: Earlier
      break;
    }
    case 'comment': {
      // Writes one comment on the post of the member, then deletes it (test content)
      await signIn(page, 'member');
      await goto(page, g + '/space/garden-talk/post/my-tomatoes-have-yellow-leaves');
      const form = page.locator('form[aria-label="Write a comment"]');
      await form.locator('textarea').fill('Accessibility review test comment, deleted right after.');
      await form.locator('button[type=submit]').focus();
      await page.keyboard.press('Enter');
      await page.waitForTimeout(40);
      await f('during send');
      await page.waitForTimeout(2500);
      await f('after send');
      log(
        'live regions',
        await page.evaluate(() =>
          [...document.querySelectorAll('[aria-live],[role=status],[role=alert],[role=log]')].map(
            (e) => `${e.getAttribute('role') ?? ''}[${e.getAttribute('aria-live') ?? ''}] ${e.innerText.trim().slice(0, 60)}`,
          ),
        ),
      );
      const del = page.locator('role=button[name="Delete the comment by Marc Member"]').last();
      await del.focus();
      await f('delete button');
      await page.keyboard.press('Enter');
      await page.waitForTimeout(800);
      await f('dialog');
      const confirm = page.locator('[role=dialog] >> role=button[name=/^Delete/]').last();
      await confirm.focus();
      await page.keyboard.press('Enter');
      await page.waitForTimeout(2500);
      await f('after delete');
      log(
        'live regions',
        await page.evaluate(() =>
          [...document.querySelectorAll('[aria-live],[role=status],[role=alert]')].map(
            (e) => `${e.getAttribute('role') ?? ''}[${e.getAttribute('aria-live') ?? ''}] ${e.innerText.trim().slice(0, 60)}`,
          ),
        ),
      );
      break;
    }
    case 'mobile': {
      await signIn(page, 'member');
      await goto(page, g + '/space/garden-talk/post/my-tomatoes-have-yellow-leaves');
      await focusOn('role=button[name="Spaces"]');
      await page.keyboard.press('Enter');
      await page.waitForTimeout(700);
      await f('spaces drawer open');
      await page.keyboard.press('Escape');
      await page.waitForTimeout(800);
      await f('spaces drawer after Escape');
      await focusOn('role=button[name="Add a reaction to this post"]');
      await page.keyboard.press('Enter');
      await page.waitForTimeout(1500);
      await f('sheet open');
      await tabs(6, 'sheet');
      log('sheet still open', await page.locator('[role=dialog][aria-label="Choose a reaction"]').count());
      break;
    }
    default:
      log('unknown scenario');
  }
} finally {
  await browser.close();
}
