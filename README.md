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
| Stop WordPress, keeping its data | `npm stop` |
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

The `composer`, `phpunit`, and `wp` scripts wrap the tools provided by the `cli` container, following the [wp-env documentation](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/#using-composer-phpunit-and-wp-cli-tools). Composer and PHPUnit run from the theme directory; WP-CLI runs from the WordPress installation directory.

```sh
npm run composer -- install
npm run wp -- theme list
```

### Tests

With the environment running, execute the unit tests:

```sh
npm test
```

`npm run phpunit -- -- --filter TokenSettingsTest` runs a selected test class. The extra separator forwards PHPUnit options through `wp-env`.

The suite covers token encryption, tampering, saved-token migration, environment overrides, and the admin token field. It uses Brain Monkey to mock WordPress functions and does not access the development database or GitHub. PHPUnit is pinned through Composer so local tests and CI use the same version.

GitHub Actions runs PHP syntax checks and `composer test` on PHP 8.3 for pull requests and pushes to `main`, excluding documentation-only changes. CI installs dependencies directly and does not need Docker. Locally, `composer test` also works when PHP and Composer are installed on the host.

To reset the development database, deleting its posts, settings, and users:

```sh
npm run reset
```

For a complete teardown, `npm run destroy` removes this environment's containers, volumes, and downloaded files. Local theme source files remain in this repository.

The Docker runtime publishes WordPress and database ports on all host interfaces by default. Use a trusted local network or restrict access with your host firewall; the development credentials above are public defaults.

### Source navigation and debugging

Start with `npm run debug` to enable step debugging. In VS Code, select **Listen for wp-env Xdebug** in Run and Debug, start the listener, set a PHP breakpoint, and reload the site.

Editor settings in `.vscode/` are local and gitignored. Use `npm run status -- --json` to find `installPath`. Point Intelephense's `intelephense.environment.includePaths` to its `WordPress` subdirectory. Configure a PHP debugger listener on port 9003 with `/var/www/html` mapped to that subdirectory and `/var/www/html/wp-content/themes/kadence-child` mapped to this repository.

## Self-updates

This theme uses [plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker) to pull updates from GitHub releases. This public repository does not require a token.

For a private repository, go to **Settings → Theme Updates** in WP Admin and paste a fine-grained GitHub Personal Access Token with read-only `Contents` access to that repository. Updates appear under **Appearance → Themes**.

Alternatively, provide `KADENCE_CHILD_GITHUB_TOKEN` in the environment of the PHP process serving WordPress. A nonempty value takes precedence over the saved token and disables the settings field with an explanatory message. The environment token is never saved to the database or displayed in the field. Unset it to use the saved token again. Exporting it in your host shell alone does not pass it into the `wp-env` Docker containers.

## Releasing a new version

1. Bump the `Version` header in `style.css`.
2. Commit and push to `main`.
3. Create a new GitHub release with a tag like `v0.2.0`.
4. The `Build release zip` workflow attaches `kadence-child.zip` to the release. PUC clients will see the update on their next check.
