// Forced colors: a checked custom checkbox, the segmented control, the quiz radios (visitor register page and member quiz)
import path from 'node:path';
import { launch, newContext, signIn, goto, OUT } from './a11y-lib.mjs';
const browser = await launch();
const context = await newContext(browser, { width: 1280, height: 900, forcedColors: 'active', reducedMotion: 'reduce' });
const page = await context.newPage();
await goto(page, '/register');
await page.locator('input[type=checkbox]').first().check();
await page.locator('input[type=checkbox]').first().scrollIntoViewIfNeeded();
await page.screenshot({ path: path.join(OUT, 'forced2-register-checked.png') });
await signIn(page, 'member');
await goto(page, '/community/atlas-garden/space/balcony-course/page/quiz-water-and-compost');
await page.locator('role=radio[name="In the morning"]').check();
await page.locator('role=checkbox[name="Dry leaves"]').check();
await page.locator('role=checkbox[name="Dry leaves"]').scrollIntoViewIfNeeded();
await page.screenshot({ path: path.join(OUT, 'forced2-quiz.png') });
const seg = await page.evaluate(() => !!document.querySelector('input[type=radio].sr-only'));
console.log('segmented on quiz page', seg);
await browser.close();
