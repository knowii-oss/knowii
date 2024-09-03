# TODO

- Retrieve old auth pages
- Migrate login page
- Migrate registration page
- Inspiration: https://github.com/rjvim/jetstream-in-react/commit/9bbe81cf8664182d6aa9fdc049a51f1c96e9b8d8
- review fixmes
- npm dependency versions
- Find how to deploy to Laravel Forge
- update theme to match the PrimeReact theme: primereact/resources/themes/lara-light-pink/theme.css
- TODO, persistent layout: https://inertiajs.com/pages
- Create DB model
  - Db person profile: Always link resources to persons or orgs (n-n)
  - Create "Default" collection automatically, and display those as cards
- Disable this feature: Features::accountDeletion()
- Enable email verification: https://jetstream.laravel.com/features/registration.html#email-verification
- Check out https://github.com/7nohe/laravel-zodgen
- Create GH pipelines for tests
- Create GH pipelines for deployment
- Customize application logo
  - `ApplicationMark`
  - `AuthenticationCardLogo`
- Add socialite: https://laravel.com/docs/11.x/socialite
- Configure logging: https://laravel.com/docs/11.x/logging
- Create a script to reset the DB, run migrations, and seed the DB
- Add Storybook. Related: https://github.com/area17/blast
- When trying to delete a community, protect by asking the user to enter the full name and the action: https://jetstream.laravel.com/features/password-confirmation.html
- Enable moving resources from one community to another
- Enable team invitations: https://jetstream.laravel.com/features/teams.html#invitations
- Require terms of service/privacy policy approval: https://jetstream.laravel.com/features/registration.html#requiring-terms-of-service-privacy-policy-approval
- Remove @headlessui/react
- Create filament admin panel

Add tests:
app.spec.tsx

import { render } from '@testing-library/react';

import App from './app';

describe('App', () => {
it('should render successfully', () => {
const { baseElement } = render(<App />);
expect(baseElement).toBeTruthy();
});

it('should have a greeting as the title', () => {
const { getByText } = render(<App />);
expect(getByText(/Welcome/gi)).toBeTruthy();
});
});

common.spec.ts

import { common } from './common';

describe('common', () => {
it('should work', () => {
expect(common()).toEqual('common');
});
});

- Add prettier plugin for PHP once this is fixed: https://github.com/nrwl/nx/issues/21250#issuecomment-2324649563
- Add Husky
- Review CI config

setup({ el, App, props }) {
if (import.meta.env.DEV) {
createRoot(el).render(<App {...props} />);
return
}

        hydrateRoot(el, <App {...props} />);

- Explore Tailwind passthrough with Prime React: https://tailwind.primevue.org/overview
- Add Sentry: https://sentry.io/for/laravel/
- Add Sonar
- Generate OpenAPI docs: https://github.com/DarkaOnLine/L5-Swagger
- https://github.com/laravel/jetstream/pull/1529
