// Error state of form fields after a failed submit: aria-invalid, aria-describedby, where the focus goes
import { launch, newContext, signIn, goto, step, focusInfo } from './a11y-lib.mjs';
const cases = [
  [
    'visitor',
    '/register',
    [
      ['submit', 'form'],
      ['wait', 2000],
    ],
  ],
  [
    'visitor',
    '/login',
    [
      ['fill', '#email', 'atlas-nobody@example.com'],
      ['fill', '#password', 'wrong-password-1'],
      ['click', 'role=button[name="Log in"]'],
      ['wait', 2500],
    ],
  ],
  [
    'visitor',
    '/forgot-password',
    [
      ['submit', 'form'],
      ['wait', 2000],
    ],
  ],
  [
    'member',
    '/user/profile',
    [
      ['fill', '#current_password', 'not-the-current-password'],
      ['fill', '#password', 'a-new-password-2026'],
      ['fill', '#password_confirmation', 'a-new-password-2026'],
      ['click', 'form:has(#current_password) button[type=submit]'],
      ['wait', 2500],
    ],
  ],
  [
    'member',
    '/community/atlas-garden/space/reading-list',
    [
      ['click', 'role=button[name="Add a resource"]'],
      ['waitDialog'],
      ['fill', 'role=dialog >> input >> nth=0', 'not an address'],
      ['press', 'Tab'],
      ['click', 'role=dialog >> button[type=submit]', { force: true }],
      ['wait', 1000],
    ],
  ],
];
const browser = await launch();
for (const [role, address, steps] of cases) {
  const context = await newContext(browser, { width: 1280, reducedMotion: 'reduce' });
  const page = await context.newPage();
  if (role !== 'visitor') await signIn(page, role);
  await goto(page, address);
  for (const s of steps) await step(page, s);
  const r = await page.evaluate(() => {
    const errs = [...document.querySelectorAll('.text-danger-text, [role=alert]')]
      .filter((e) => e.innerText.trim())
      .map((e) => ({ id: e.id, text: e.innerText.trim().slice(0, 70) }));
    const fields = [...document.querySelectorAll('input:not([type=hidden]),textarea')]
      .filter((f) => f.getClientRects().length)
      .map((f) => `${f.id}: invalid=${f.getAttribute('aria-invalid')} describedby=${f.getAttribute('aria-describedby')}`);
    return { errs, fields };
  });
  console.log('\n##', address, JSON.stringify(r, null, 0));
  console.log('focus', JSON.stringify(await focusInfo(page)).slice(0, 160));
  await context.close();
}
await browser.close();
