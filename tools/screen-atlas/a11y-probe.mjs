// One-off probes: node a11y-probe.mjs <role> <address> <width> <height> <css or ""> <js expression>
import { launch, newContext, signIn, goto } from './a11y-lib.mjs';
const [role, address, w, h, css, expr] = process.argv.slice(2);
const browser = await launch();
const context = await newContext(browser, { width: Number(w), height: Number(h), reducedMotion: 'reduce' });
const page = await context.newPage();
if (role !== 'visitor') await signIn(page, role);
await goto(page, address);
if (css)
  await page.evaluate((c) => {
    const st = new CSSStyleSheet();
    st.replaceSync(c);
    document.adoptedStyleSheets = [...document.adoptedStyleSheets, st];
  }, css);
await page.waitForTimeout(400);
console.log(JSON.stringify(await page.evaluate(expr), null, 1));
if (process.env.SHOT) await page.screenshot({ path: process.env.SHOT });
await browser.close();
