# radiopipe

Personal Radio Script Pipeline for Curated News Digests.

A tiny pipeline that turns structured digest JSON into personalized radio-style news scripts.

## Development Debug Commands

Inspect the configured topic pipeline without persisting results:

```bash
php artisan radiopipe:topics:debug --limit=10 | jq
```

Inspect the configured scenario generation pipeline without persisting results:

```bash
php artisan radiopipe:scenario:debug --limit=10 --character=neko_nyan_balanced_radio | jq
```

Generate and persist an episode:

```bash
php artisan radiopipe:episodes:generate --character=neko_nyan_balanced_radio
php artisan radiopipe:episodes:generate --dry-run | jq
```

These commands use configured providers, analyzers, and scenario generators. Debug and dry-run modes print JSON to stdout without saving. Episode generation persists an `Episode` unless `--dry-run` is used. Audio generation is deferred.

## Episode Generation Schedule

Automatic episode generation is disabled by default. Enable and configure the Laravel scheduler with environment variables:

```env
RADIOPIPE_EPISODE_SCHEDULE_ENABLED=true
RADIOPIPE_EPISODE_SCHEDULE_TIME=07:00
RADIOPIPE_EPISODE_SCHEDULE_TIMEZONE=Asia/Tokyo
RADIOPIPE_EPISODE_SCHEDULE_LIMIT=20
RADIOPIPE_EPISODE_SCHEDULE_CHARACTER=neko_nyan_balanced_radio
```

When enabled, Laravel's scheduler runs `radiopipe:episodes:generate` once per day at the configured time and timezone, passes the configured `--limit`, and passes `--character` only when configured. The hosting platform still needs to run Laravel's scheduler; Laravel Cloud scheduler setup is a deployment concern for a later task.

## Development Checks

Use `make test` to run the PHPUnit test suite.
Use `make lint` for static and mechanical checks, including PHPStan, PHP-CS-Fixer dry-run, and Composer audit.

## Admin Panel

The internal admin panel is available at `/admin`. It uses Google OAuth only; password login, registration, password reset, and invite flows are not enabled.

Required environment variables:

```env
RADIOPIPE_ADMIN_ALLOWED_EMAILS=admin@example.test
RADIOPIPE_ADMIN_DEV_LOGIN_ENABLED=false
RADIOPIPE_ADMIN_DEV_LOGIN_EMAIL=
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
```

`RADIOPIPE_ADMIN_ALLOWED_EMAILS` is a comma-separated allow list. Email matching is case-insensitive and trims whitespace. If the allow list is empty, no user can access the admin panel.

For local browser debugging, `GET /_local/admin/login` can log in a configured development user without Google OAuth. This helper is disabled by default, only works when `APP_ENV` is `local` or `testing`, and the dev email must also be in `RADIOPIPE_ADMIN_ALLOWED_EMAILS`. Never enable it in production.

```env
RADIOPIPE_ADMIN_ALLOWED_EMAILS=admin@example.test
RADIOPIPE_ADMIN_DEV_LOGIN_ENABLED=true
RADIOPIPE_ADMIN_DEV_LOGIN_EMAIL=admin@example.test
```

Character profiles are managed from the Filament admin panel as master data for future scenario generation instructions. Do not commit private character data; the committed sample profile is the public dummy character `ねこにゃん`.

Topic screening keyword rules are also managed from the Filament admin panel. The database is the source of truth; default limitation and sensitive keyword rules are seeded by `TopicScreeningKeywordRuleSeeder`, and config fallback keyword lists are intentionally not used.

Generated Episodes and Episode Topics can be inspected from the Filament admin panel under Analysis. These resources are read-only and are intended for debugging and data analysis; manual editing of generated records is intentionally not supported yet.

## Branch And CI Workflow

Recommended development flow:

1. Create a feature branch.
2. Push the feature branch.
3. Open a pull request.
4. Wait for GitHub Actions CI to pass.
5. Merge into `main`.
6. Let Laravel Cloud deploy from `main`.

The CI workflow runs tests, static analysis, coding style checks, migrations against MySQL, and Composer audit. Local `make test` and `make lint` cover the main pre-merge checks except the CI-only MySQL migration step. The workflow does not deploy. Branch protection can later require the CI checks before merging into `main`.

## Dependency Maintenance

Dependabot creates weekly update pull requests for Composer dependencies in `src/` and GitHub Actions workflows. Auto-merge is intentionally disabled.

Maintenance policy:

1. Review security update pull requests early.
2. Review normal dependency update pull requests roughly monthly.
3. Review major updates manually and carefully.
4. Wait for GitHub Actions CI to pass before merging dependency pull requests.
5. Merge into `main`, then let Laravel Cloud deploy from `main`.

Composer dependencies are managed under `src/`. The authoritative Composer files are `src/composer.json` and `src/composer.lock`; do not treat the repository root as the Composer project root.

If a root-level `composer.lock` is added as a Laravel Cloud detection workaround, it is only a copied detection file. In that case, Dependabot may update only `src/composer.lock`, and a maintainer may need to refresh the copied root lock before merging a dependency pull request if CI or Laravel Cloud behavior requires it:

```bash
cp src/composer.lock composer.lock
```

Enable GitHub Dependabot alerts and Dependabot security updates in the repository security settings if they are not already enabled.
