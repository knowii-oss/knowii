// Tailwind CSS v4 uses a dedicated PostCSS plugin (`@tailwindcss/postcss`).
// The Tailwind configuration is loaded from the `@config` directive in
// `src/styles.css`. Vendor prefixing (previously `autoprefixer`) is built in.
module.exports = {
  plugins: {
    '@tailwindcss/postcss': {},
  },
};
