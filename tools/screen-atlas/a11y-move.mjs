// Focus after a move button in the space tree of the settings (moves "Off topic" down, then back up: order restored)
import { launch, newContext, signIn, goto, focusInfo } from './a11y-lib.mjs';
const browser = await launch();
const context = await newContext(browser, { width: 1920, reducedMotion: 'reduce' });
const page = await context.newPage();
await signIn(page, 'owner');
await goto(page, '/community/atlas-garden/settings');
const order = () =>
  page.evaluate(() =>
    [...document.querySelectorAll('section[aria-label="Space group Together"] li')].map((l) => l.innerText.split('\n')[0]).join(' | '),
  );
console.log('before', await order());
await page.locator('role=button[name="Move Off topic down"]').focus();
await page.keyboard.press('Enter');
await page.waitForTimeout(30);
console.log('during', JSON.stringify(await focusInfo(page)));
await page.waitForTimeout(2500);
console.log('after', JSON.stringify(await focusInfo(page)));
console.log('moved', await order());
await page.locator('role=button[name="Move Off topic up"]').focus();
await page.keyboard.press('Enter');
await page.waitForTimeout(2500);
console.log('after 2', JSON.stringify(await focusInfo(page)));
console.log('restored', await order());
console.log(
  'live',
  await page.evaluate(() =>
    [...document.querySelectorAll('[aria-live],[role=status],[role=alert]')].map((e) => e.innerText.trim().slice(0, 60)),
  ),
);
await browser.close();
