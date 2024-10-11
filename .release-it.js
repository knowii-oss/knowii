const releaseItConfig = {
  ci: false,
  force: false,
  isDryRun: false,
  verbose: false,
  'disable-metrics': true,
  git: {
    changelog: 'npm run generate:changelog-recent',
    requireCleanWorkingDir: true,
    addUntrackedFiles: false,
    requireBranch: false,
    requireUpstream: true,
    requireCommits: false,
    commit: true,
    commitMessage: 'chore(release): release ${version}',
    commitArgs: '--no-verify',
    tag: true,
    tagName: '${version}',
    tagAnnotation: 'Release ${version}',
    tagArgs: [],
    push: true,
    pushArgs: '--follow-tags',
    pushRepo: 'origin',
  },
  github: {
    release: true,
    releaseName: 'Release ${version}',
    releaseNotes: null,
    draft: false,
    tokenRef: 'GITHUB_TOKEN',
    assets: [],
    host: null,
  },
  gitlab: false,
  npm: {
    publish: false,
  },
  hooks: {
    'before:init': [],
    'after:bump': ['npm run generate:changelog'],
    'after:git:release': [],
    'after:release': 'echo Successfully released ${name} v${version} to ${repo.repository}.',
  },
  plugins: {
    '@release-it/conventional-changelog': {
      preset: 'angular',
    },
  },
};

module.exports = releaseItConfig;
