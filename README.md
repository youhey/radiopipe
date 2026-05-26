# radiopipe

Personal Radio Script Pipeline for Curated News Digests.

A tiny pipeline that turns structured digest JSON into personalized radio-style news scripts.

## Development Debug Commands

Inspect the configured topic pipeline without persisting results:

```bash
php artisan radiopipe:topics:debug --limit=10 | jq
```

The command prints JSON to stdout, uses configured services, and is intended only for development/debugging.
