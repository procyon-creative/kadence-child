# Project guidance

## WordPress

This is a classic Kadence child theme. Keep `Template: kadence` in `style.css`.
Use the project skills in `.agents/skills/`, starting with `wordpress-router`
and `wp-env`. The official WordPress skills and `skills-lock.json` are maintained
with `npx skills`; install them locally, never globally for this project.

Follow WordPress APIs and coding conventions. Use American English. Prefer
classes for substantial new functionality and keep entry points thin. Preserve
unrelated work and keep credentials and machine-specific paths out of Git.

## Development and tests

- Install Node dependencies with `npm ci` and start WordPress with `npm start`.
- Startup installs Composer dependencies inside Docker and activates the theme.
- `npm stop` stops both environments; `npm run test:stop` stops only tests.
- Use `npm run wp -- <command>` for development-site WP-CLI commands.
- Use `npm run composer -- <command>` for Composer inside the theme directory.
- Run `npm test` to start `.wp-env.test.json` and run integration tests in its
  `cli` container. Use `npm run test:stop` to stop only the test environment.
- Tests extend `WP_UnitTestCase`, load real WordPress and Kadence, and use the
  dedicated test database. The test config sets `KADENCE_CHILD_TEST_ENVIRONMENT`;
  the bootstrap refuses to touch a database without that marker. Never run tests
  against the development database. Do not replace WordPress functions with
  Brain Monkey or custom stubs.
- Keep test state isolated with WordPress's test lifecycle and factories. Block
  external HTTP calls in tests and restore environment variables after each test.
- CI must run the same `wp-env` test command used locally.

## Git and releases

Do not push, rename remote branches, or change the default branch unless the user
explicitly requests it. Report blocked pushes instead of choosing another remote
branch. Never include AI attribution in commit messages or PR descriptions.

Exclude tests, development dependencies, agent guidance, and skills from release
ZIPs. The release workflow installs Composer dependencies with `--no-dev`.
