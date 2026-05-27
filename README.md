# radiopipe

Personal Radio Script Pipeline for Curated News Digests.

A tiny pipeline that turns structured digest JSON into personalized radio-style news scripts.

## Development Debug Commands

Inspect the configured topic pipeline without persisting results:

```bash
php artisan radiopipe:topics:debug --limit=10 | jq
```

The command prints JSON to stdout, uses configured services, and is intended only for development/debugging.

## Admin Panel

The internal admin panel is available at `/admin`. It uses Google OAuth only; password login, registration, password reset, and invite flows are not enabled.

Required environment variables:

```env
RADIOPIPE_ADMIN_ALLOWED_EMAILS=admin@example.test
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
```

`RADIOPIPE_ADMIN_ALLOWED_EMAILS` is a comma-separated allow list. Email matching is case-insensitive and trims whitespace. If the allow list is empty, no user can access the admin panel.

Character profiles are managed from the Filament admin panel as master data for future scenario generation instructions. Do not commit private character data; the committed sample profile is the public dummy character `ねこにゃん`.

## Branch And CI Workflow

Recommended development flow:

1. Create a feature branch.
2. Push the feature branch.
3. Open a pull request.
4. Wait for GitHub Actions CI to pass.
5. Merge into `main`.
6. Let Laravel Cloud deploy from `main`.

The CI workflow runs tests, static analysis, coding style checks, migrations against MySQL, and Composer audit. It does not deploy. Branch protection can later require the CI checks before merging into `main`.

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
