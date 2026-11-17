// The edit box of a chat message at 360 and 320 px: what is visible inside the message log
import path from 'node:path';
import { launch, newContext, signIn, goto, OUT } from './a11y-lib.mjs';

const browser = await launch();
for (const [width, height] of [
  [360, 800],
  [320, 640],
  [1280, 720],
]) {
  const context = await newContext(browser, { width, height, reducedMotion: 'reduce' });
  const page = await context.newPage();
  await signIn(page, 'member');
  await goto(page, '/community/atlas-garden/space/lounge');
  await page.locator('role=button[name="Edit the message by Marc Member"]').first().click();
  await page.waitForTimeout(800);
  const m = await page.evaluate(() => {
    const log = document.querySelector('ol[role=log]');
    const ta = document.activeElement;
    const lr = log.getBoundingClientRect();
    const tr = ta.getBoundingClientRect();
    const item = ta.closest('li');
    const ir = item.getBoundingClientRect();
    const sec = log.closest('section').getBoundingClientRect();
    return {
      section: [Math.round(sec.top), Math.round(sec.height)],
      log: [Math.round(lr.top), Math.round(lr.height), 'scrollH', log.scrollHeight],
      editItem: [Math.round(ir.top), Math.round(ir.height)],
      textarea: [Math.round(tr.top), Math.round(tr.height), 'scrollH', ta.scrollHeight],
      textareaVisibleInLog: Math.round(Math.min(tr.bottom, lr.bottom) - Math.max(tr.top, lr.top)),
      itemVisibleInLog: Math.round(Math.min(ir.bottom, lr.bottom) - Math.max(ir.top, lr.top)),
      docScrollW: document.documentElement.scrollWidth,
    };
  });
  console.log(width, JSON.stringify(m));
  await page.screenshot({ path: path.join(OUT, `chat-edit-${width}.png`) });
  await context.close();
}
await browser.close();
