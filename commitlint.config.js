module.exports = {
  extends: ['@commitlint/config-conventional'],

  /*
   * Any rules defined here will override rules from @commitlint/config-conventional
   Reference: https://commitlint.js.org/#/reference-rules
   */
  rules: {
    'header-max-length': [1, 'always', 100],
    'scope-enum': [
      2,
      'always',
      ['all', 'a11y', 'code', 'deps', 'docs', 'i18n', 'qa', 'release', 'sec', 'ui', 'ux', 'auth', 'blog', 'api', 'websockets', 'build'],
    ],
    'scope-case': [2, 'always', 'lowerCase'],
  },
};
