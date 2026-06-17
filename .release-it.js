const releaseItConfig = {
  ci: false,
  force: false,
  isDryRun: false,
  verbose: false,
  'disable-metrics': true,
  git: {
    changelog: '',
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
    changelog:
      'npx auto-changelog --stdout --commit-limit false -u --template https://raw.githubusercontent.com/release-it/release-it/main/templates/changelog-compact.hbs',
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
    // Regenerate the changelog from the bumped version and stage it so that
    // release-it includes CHANGELOG.md in the release commit (otherwise the
    // generated changelog is only added to the GitHub release notes and never
    // persisted in the repository).
    'after:bump': ['npx auto-changelog -p', 'git add CHANGELOG.md'],
    'after:git:release': [],
    'after:release': 'echo Successfully released ${name} v${version} to ${repo.repository}.',
  },
};

module.exports = releaseItConfig;
