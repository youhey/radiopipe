# radiopipe

Personal Radio Script Pipeline for Curated News Digests.

A tiny pipeline that turns structured digest JSON into personalized radio-style news scripts.

## Development Debug Commands

Inspect the configured topic pipeline without persisting results:

```bash
php artisan radiopipe:topics:debug --limit=10 | jq
```

The command prints JSON to stdout, uses configured services, and is intended only for development/debugging.

## Branch And CI Workflow

Recommended development flow:

1. Create a feature branch.
2. Push the feature branch.
3. Open a pull request.
4. Wait for GitHub Actions CI to pass.
5. Merge into `main`.
6. Let Laravel Cloud deploy from `main`.

The CI workflow runs tests, static analysis, coding style checks, migrations against MySQL, and Composer audit. It does not deploy. Branch protection can later require the CI checks before merging into `main`.
