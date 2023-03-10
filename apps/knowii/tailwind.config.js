'use strict';

/** @type {import('../../node_modules/tailwindcss').Config} */

const { join } = require('path');
const { createGlobPatternsForDependencies } = require('@nrwl/react/tailwind');

module.exports = {
  content: [
    join(__dirname, './**/*.{js,ts,jsx,tsx,css,html,mdx,scss}'),
    // The expression below will also include the libraries that we depend on implicitly (cfr project.json file of NX)
    ...createGlobPatternsForDependencies(__dirname),
  ],
  presets: [require('../../tailwind-workspace-preset')],
};
