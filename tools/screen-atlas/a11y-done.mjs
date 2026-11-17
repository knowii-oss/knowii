// Focus after "Mark as done" on a lesson (pressed twice: the state is restored)
import { launch, newContext, signIn, goto, focusInfo } from './a11y-lib.mjs';
const browser = await launch();
const context = await newContext(browser, { width: 1920, reducedMotion: 'reduce' });
const page = await context.newPage();
await signIn(page, 'member');
await goto(page, '/community/atlas-garden/space/balcony-course/page/watering');
const btn = page.locator('main button', { hasText: /done/i }).first();
console.log('button', await btn.innerText(), await btn.getAttribute('aria-pressed'));
await btn.focus();
await page.keyboard.press('Enter');
await page.waitForTimeout(40);
console.log('during', JSON.stringify(await focusInfo(page)).slice(0, 140));
await page.waitForTimeout(2500);
console.log('after', JSON.stringify(await focusInfo(page)).slice(0, 140));
console.log(
  'status',
  await page.evaluate(() => [...document.querySelectorAll('[role=status],[aria-live]')].map((e) => e.innerText.trim()).filter(Boolean)),
);
const btn2 = page.locator('main button', { hasText: /done/i }).first();
console.log('button now', await btn2.innerText());
await btn2.focus();
await page.keyboard.press('Enter');
await page.waitForTimeout(2500);
console.log('restored', await page.locator('main button', { hasText: /done/i }).first().innerText());
await browser.close();
