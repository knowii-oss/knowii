import type { Config } from 'tailwindcss';

import { join } from 'path';
import { createGlobPatternsForDependencies } from '@nx/react/tailwind';

export default {
  content: [
    join(__dirname, './**/*.{js,ts,jsx,tsx,css,html,mdx,scss}'),
    // The expression below will also include the libraries that we depend on implicitly (cfr project.json file of NX)
    ...createGlobPatternsForDependencies(__dirname),
  ],
  presets: [require('../../tailwind-workspace-preset')],
} satisfies Config;
