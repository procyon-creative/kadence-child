# Kadence Child

A generic child theme for Kadence.

## Local development

With Docker running, install dependencies and start WordPress:

```sh
npm ci
npm start
```

### Default login

| Setting | Default |
| --- | --- |
| Site | http://localhost:8888 |
| WordPress admin | http://localhost:8888/wp-admin/ |
| Admin username | `admin` |
| Admin password | `password` |
| Database username | `root` |
| Database password | `password` |

Startup installs Composer dependencies inside Docker and activates Kadence Child. Host PHP and Composer are not required. Local theme edits appear immediately. PHP debugging and logging are enabled. No custom database seed is required.

### Common commands

Run these from the repository directory:

| Task | Command |
| --- | --- |
| Start or restart WordPress | `npm start` |
| Stop development and test environments, keeping their data | `npm stop` |
| Show status and ports | `npm run status` |
| Show source directory and database port | `npm run status -- --json` |
| Refresh downloaded sources and configuration, keeping content | `npm run update` |
| Start with Xdebug | `npm run debug` |
| Follow PHP and Docker logs | `npm run logs` |
| List installed themes | `npm run wp -- theme list` |
| Activate the child theme | `npm run wp -- theme activate kadence-child` |
| List WordPress users | `npm run wp -- user list` |
| List installed plugins | `npm run wp -- plugin list` |
| Install PHP dependencies inside Docker | `npm run composer -- install` |
| Open a container shell | `npm run shell` |

The `composer`, `phpunit`, and `wp` scripts wrap the tools provided by the `cli` container, following the [wp-env documentation](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/#using-composer-phpunit-and-wp-cli-tools). Composer runs in `cli`, and PHPUnit runs in the separate test environment's `cli` container, both from the theme directory. WP-CLI runs from the development WordPress installation directory.

```sh
npm run composer -- install
npm run wp -- theme list
```

### Tests

Start the isolated test environment and run the WordPress integration tests:

```sh
npm test
```

After startup, `npm run phpunit -- -- --filter TokenSettingsTest` runs a selected test class. The extra separator forwards PHPUnit options through `wp-env`.

`npm run test:scripts` checks the environment-stop helper with Node's built-in test runner. `npm test` runs these checks before starting WordPress.

The PHP suite extends WordPress's `WP_UnitTestCase` and loads the real Kadence parent and child themes. It covers token encryption, database-backed settings, environment overrides, permissions, and styles. Development uses `.wp-env.json` on port 8888; tests use `.wp-env.test.json` on port 8889. Each config has its own Docker containers and database. Tests use WordPress's core test files provided through `WP_TESTS_DIR`. WordPress recreates test tables as part of the test bootstrap; development-site data is not used. External HTTP calls are blocked. PHPUnit and the WordPress PHPUnit polyfills are installed through Composer.

GitHub Actions starts the same Docker environment and runs `npm test` plus PHP syntax checks for pull requests and pushes to the repository's default branch, excluding documentation-only changes. Use `npm test` locally too; the bootstrap requires the `KADENCE_CHILD_TEST_ENVIRONMENT` marker set only by the test config and refuses to run against the development environment. Stop just the test environment with `npm run test:stop`. Use `npm run test:wp -- <command>` or `npm run test:composer -- <command>` to run tools in that environment.

To reset the development database, deleting its posts, settings, and users:

```sh
npm run reset
```

For a complete teardown, `npm run destroy` removes this environment's containers, volumes, and downloaded files. Local theme source files remain in this repository.

The Docker runtime publishes WordPress and database ports on all host interfaces by default. Use a trusted local network or restrict access with your host firewall; the development credentials above are public defaults.

### Source navigation and debugging

Start with `npm run debug` to enable step debugging. In VS Code, select **Listen for wp-env Xdebug** in Run and Debug, start the listener, set a PHP breakpoint, and reload the site.

Editor settings in `.vscode/` are local and gitignored. Use `npm run status -- --json` to find `installPath`. Point Intelephense's `intelephense.environment.includePaths` to its `WordPress` subdirectory and the downloaded Kadence source directory. To resolve `WP_UnitTestCase` and test factories, also include `WordPress-PHPUnit/tests/phpunit/includes` under the test environment's `installPath`, reported by `npx wp-env status --config=.wp-env.test.json --json`. Configure a PHP debugger listener on port 9003 with `/var/www/html` mapped to that subdirectory and `/var/www/html/wp-content/themes/kadence-child` mapped to this repository.

## Agent guidance and skills

Project instructions live in `AGENTS.md`. The official [WordPress agent skills](https://github.com/WordPress/agent-skills) are installed locally in `.agents/skills/`, with their source hashes recorded in `skills-lock.json`. Commit these files with the project; they are excluded from the theme release ZIP.

The installation command is:

```sh
npx skills add WordPress/agent-skills --agent codex --skill '*' --yes
```

Use `npx skills update --project` to update project skills, then review the changed skill files and lockfile before committing. No global skill installation is needed.

## Self-updates

This theme uses [plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker) to pull updates from GitHub releases. This public repository does not require a token.

For a private repository, go to **Settings → Theme Updates** in WP Admin and paste a fine-grained GitHub Personal Access Token with read-only `Contents` access to that repository. Updates appear under **Appearance → Themes**.

Alternatively, provide `KADENCE_CHILD_GITHUB_TOKEN` in the environment of the PHP process serving WordPress. A nonempty value takes precedence over the saved token and disables the settings field with an explanatory message. The environment token is never saved to the database or displayed in the field. Unset it to use the saved token again. Exporting it in your host shell alone does not pass it into the `wp-env` Docker containers.

## Releasing a new version

1. Bump the `Version` header in `style.css`.
2. Merge the version change into the repository's default branch.
3. Create a new GitHub release with a tag matching the theme version, prefixed with `v`.
4. The `Build release zip` workflow attaches `kadence-child.zip` to the release. PUC clients will see the update on their next check.
