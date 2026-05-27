AGENTS.md

This document describes the development conventions and constraints for agents working on this repository.

Project Overview

radiopipe is a small Laravel application designed to turn structured digest JSON into personalized radio-style news scripts.

Full name:

Personal Radio Script Pipeline for Curated News Digests

Short description:

A tiny pipeline that turns structured digest JSON into personalized radio-style news scripts.

The application is intended to consume curated article records from upstream digest providers such as digestpipe, combine them with optional contextual inputs such as weather and general headlines, and produce script-ready output for a personal radio-style news program.

The first development target is script generation. Audio synthesis, podcast distribution, mobile apps, public publishing, and real-time notification systems are downstream concerns and should not be assumed unless explicitly requested.

The application is intended to be deployed to Laravel Cloud. The local development environment should approximate the Laravel Cloud runtime where practical, while remaining simple and reproducible with Docker Compose.

Repository Layout

The repository root should remain small and focused.

docs/                 # Development documents
docker/               # Dockerfiles and container configuration
src/                  # Laravel application source
docker-compose.yml    # Local development environment
README.md
AGENTS.md

Laravel application files must be placed under src/.

Do not place Laravel framework files directly in the repository root.

Target Runtime

The production deployment target is Laravel Cloud.

Design the application with the following assumptions:

* Application containers are ephemeral.
* Local filesystem must not be used for persistent user data.
* Logs should be emitted to stdout/stderr.
* Environment variables are managed by the platform.
* Database, cache, session, queue, and storage backends must be configurable through environment variables.
* Build-time and deploy-time concerns should remain separate.

Local Development Stack

Use Docker Compose for local development.

Required services:

* php-cli
    * Composer
    * Artisan
    * PHPUnit
    * Pest
    * PHPStan
    * PHP-CS-Fixer
* php-fpm
    * Web runtime for Laravel
* nginx
    * Local HTTP frontend
* node
    * Node.js, npm, and Vite tooling
* mysql
    * MySQL database
* valkey
    * Redis-compatible cache and session backend
* minio
    * S3-compatible object storage

The web frontend should use:

nginx -> php-fpm -> Laravel

PHP commands and tests should use php-cli.
Node.js, npm, and Vite commands should use node.

Application Defaults

The Laravel application should default to the following local development settings:

DB_CONNECTION=mysql
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=database
FILESYSTEM_DISK=s3
LOG_CHANNEL=stderr

Use Valkey through Laravel’s Redis-compatible configuration.

Use MinIO through Laravel’s S3 filesystem driver.

Do not use SQLite for this project.

Do not use file-based cache or file-based sessions as the default environment.

Environment Files

Never commit real environment files.

Ignored:

.env
.env.*

Tracked:

.env.example

The src/.env.example file should be suitable for local Docker Compose development.

It should include example values for:

* MySQL
* Valkey
* MinIO
* Laravel app URL
* Logging
* Queue
* Cache
* Session
* Mail, if needed
* Upstream digest provider API configuration
* Weather provider configuration, if enabled
* General headline provider configuration, if enabled
* AI provider configuration

Do not include real secrets.

Laravel Cloud Compatibility

When making application or infrastructure changes, prefer Laravel Cloud-compatible defaults.

Important rules:

* Do not persist uploads or generated files to storage/app in production paths.
* Use the S3 filesystem driver for persistent object storage.
* Use stderr logging.
* Keep queues, sessions, cache, and storage configurable through .env.
* Avoid relying on shell access or mutable container state.
* Avoid adding runtime dependencies that require unmanaged system daemons.
* Avoid assuming custom nginx behavior in production.

Laravel Cloud Repository Detection

If this repository uses a root-level composer.lock as a Laravel Cloud detection workaround copied from src/composer.lock, do not treat the repository root as the Laravel application root.

The Laravel app remains under src/.

Do not edit the root-level composer.lock manually. Update dependencies in src/, then refresh the copied lock file.

Laravel Cloud MySQL

Laravel Cloud deployment should use Laravel MySQL for this project unless explicitly changed.

Use database environment variables injected by the attached Laravel MySQL resource. Do not add Laravel Cloud Serverless Postgres, Neon, SNI, SSL, or endpoint option workarounds unless the database platform changes.

Custom Laravel Cloud environment variables should not override DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, or DATABASE_URL unless there is a clear operational reason.

Build and Deploy Expectations

For Laravel Cloud, build-time tasks should include things such as:

composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache

Deploy-time tasks should be limited to tasks such as:

php artisan migrate --force

Do not add deployment steps that rely on persistent local filesystem changes.

Avoid using the following as deployment assumptions:

php artisan storage:link
php artisan optimize:clear
php artisan queue:restart

Testing

PHPUnit is the primary test runner.

Pest should also be installed and available for experimentation.

Prefer adding tests for application behavior when implementing features.

Use the Makefile for normal test execution:

make test

Automated tests must not call real external APIs. Use Laravel HTTP fakes, mocked services, fixtures, or fake drivers.

Static And Mechanical Checks

Use PHPStan for static analysis.
Use PHP-CS-Fixer dry-run for coding style checks.
Use Composer audit for PHP dependency vulnerability checks.

PHPStan should be configured in the Laravel application under src/.

Use the Makefile for normal static and mechanical checks:

make lint

Prefer fixing issues rather than suppressing them.

If a suppression is necessary, keep it narrow and documented.

Code Style

Use PHP-CS-Fixer for PHP formatting and coding style checks.

Use the Makefile for normal coding style checks and fixes:

make lint
make fix

Keep formatting rules practical and Laravel-friendly.

Do not introduce broad style changes unrelated to the current task.

PHP Code Readability

Prefer explicit class structure over compact syntax when defining application services.

Do not use constructor property promotion for service dependencies. Declare class properties first, then assign them inside the constructor. This keeps member structure easy to scan.

Avoid direct global namespace references such as \Throwable, \DOMDocument, or \InvalidArgumentException in application code. Add use declarations at the top of the file instead, so dependencies are visible in one place.

Do not mark application classes as final unless there is a concrete technical reason. The default should remain extensible and easy to mock in tests.

Application classes should have a class-level PHPDoc summary. Public methods and public properties should also have concise PHPDoc comments that explain their role. Keep comments descriptive, not historical; do not add comments that merely restate a recent change.

PHPDoc Comment Style

Write PHPDoc comments in Japanese for project application code unless surrounding code already has a stronger local convention.

Use comments to describe the role of the class, method, or public property in the current design. Do not describe recent changes, implementation history, or task phases.

Keep summaries short and concrete. Prefer project vocabulary such as:

* ニュース記事アイテム
* 構造化 digest JSON
* 番組シナリオ
* ラジオ風台本
* 台本構成
* 番組セクション
* ニュース項目
* 天気コンテキスト
* 一般ヘッドライン
* キャラクター人格
* 読み上げ原稿
* dispatch
* Status Field

Do not force technical terms into Japanese when the English term is clearer or already used in the codebase. Terms such as OpenAI Responses API, JSON Schema, dry-run, Status, Payload, rundown, cue, and segment may remain in English.

For public properties, prefer a short one-line @var comment that gives the type and meaning.

For public methods, include a short behavior summary and add @param, @return, and @throws tags when they clarify the contract. Make sure the summary preserves important preconditions. For example, a job that only handles completed upstream digest records should be described as handling completed digest records, not merely as handling news items.

Constructor is acceptable as the constructor summary when the parameters already make the dependency setup clear.

Docker Guidelines

Keep Docker configuration under docker/.

Suggested structure:

docker/
  nginx/
    default.conf
  php/
    cli/
      Dockerfile
    fpm/
      Dockerfile

The Docker Compose file should be at the repository root.

Prefer explicit service names:

php-cli
php-fpm
nginx
mysql
valkey
minio

Do not use Docker Compose as a production deployment model. It is only for local development.

Database

Use MySQL as the default database.

Migrations should be database-portable where reasonable, but MySQL compatibility is the priority.

Avoid PostgreSQL-specific SQL.

Avoid raw SQL unless necessary.

When raw SQL is necessary, document why.

Cache, Session, and Queue

Use Valkey as the Redis-compatible backend for cache and session storage.

Default local settings:

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=database

For small local development and initial production deployment, database queues are acceptable.

If Redis queues are introduced later, keep the queue connection configurable.

Core Domain Responsibilities

radiopipe is not responsible for crawling arbitrary websites or extracting raw article bodies.

The primary responsibility is to consume structured article records from upstream digest providers and turn them into script-ready personal radio content.

Core responsibilities:

* Fetch completed structured digest records from configured upstream providers.
* Normalize provider responses into internal news item records.
* Filter low-value, uncertain, duplicate, or unsuitable items.
* Group related items into topics where useful.
* Rank topics by user relevance, freshness, reliability, and script value.
* Combine selected topics with optional context such as weather, local context, scheduled events, and general headlines.
* Build a program rundown with sections, ordering, tone, and estimated duration.
* Generate a radio-style script using a configured persona.
* Store generated scenarios, their source item references, and generation metadata.

Non-goals unless explicitly requested:

* Public news crawling.
* Raw article content extraction.
* Full-text search over arbitrary news sites.
* Public publishing or podcast hosting.
* Real-time breaking-news alerts.
* TTS/audio generation as a required MVP feature.
* Mobile application development.
* User registration or public multi-tenant SaaS flows.

Core Domain Vocabulary

These terms define intended radiopipe model boundaries. Keep names consistent across code, tests, documentation, and JSON output once each concept is implemented.

* Episode: One generated output package for a specific run, date, or time slot. It contains the final Scenario plus contextual data such as WeatherReport, HeadlineNews, Topics, and metadata.
* Scenario: The actual radio-style reading script. This is the content intended for narration or TTS.
* Topic: A curated, processed topic derived mainly from upstream digest articles, such as digestpipe records. Avoid calling these items News when Topic is more accurate.
* HeadlineNews: General major news collected from a news provider, NewsAPI-style provider, or RSS provider. This is contextual material, not the primary geek-news content.
* WeatherReport: Normalized weather data from a weather provider, optionally with a short human-readable summary for the Scenario.
* UpstreamArticleItem: A normalized article record fetched from an upstream digest provider such as digestpipe. This is input material before it becomes a Topic.
* Rundown: A planned program structure before the final script is written. It may include selected Topics, ordering, section intent, transitions, and timing.
* Provider: A replaceable integration layer for external or upstream data sources. Providers should hide external response shapes from higher-level application code.

The final output concept is an Episode. A future JSON representation is expected to look conceptually like this, but this is not current API behavior:

```json
{
  "schema_version": "1.0",
  "id": "episode_2026-05-25_morning",
  "date": "2026-05-25",
  "published_at": "2026-05-25T07:00:00+09:00",
  "processed_at": "2026-05-25T06:45:12+09:00",
  "scenario": {},
  "weather": {},
  "headline_news": {},
  "topics": [],
  "metadata": {}
}
```

Naming guidance:

* Use Scenario for the script itself.
* Use Episode for the whole generated package returned to downstream applications.
* Use Topic for curated digest/upstream-derived items.
* Use HeadlineNews only for general news/headline-provider items.
* Avoid naming upstream-derived geek articles as News in radiopipe domain code when Topic is more accurate.
* Keep provider DTOs separate from final Episode DTOs.
* Do not let provider-specific response shapes leak into Episode, Scenario, or Topic models.

Upstream Digest Providers

Upstream digest providers supply structured article data. digestpipe is the initial expected provider, but the application should not hard-code provider-specific behavior into business logic.

Provider configuration should be environment-driven.

Example local configuration:

RADIOPIPE_DIGEST_PROVIDER=digestpipe
RADIOPIPE_DIGEST_API_BASE_URL=http://host.docker.internal:8080
RADIOPIPE_DIGEST_API_TOKEN=
RADIOPIPE_DIGEST_DEFAULT_WINDOW_HOURS=24
RADIOPIPE_DIGEST_DEFAULT_LIMIT=100
RADIOPIPE_DIGEST_REQUEST_TIMEOUT=30
RADIOPIPE_DIGEST_MAX_RETRIES=2

Never commit real upstream API tokens.

Do not log raw bearer tokens or full upstream responses when they may contain sensitive data.

The upstream provider client should be testable with HTTP fakes and fixture responses.

Expected Digest Record Shape

The application may assume that upstream digest records contain article metadata and structured analysis JSON similar to:

source.key
source.name
source.feed_url
article.title
article.url
article.discussion_url
article.published_at
article.fetched_at
selection.status
selection.score
analysis.title.original
analysis.title.normalized
analysis.content.brief
analysis.content.background
analysis.content.key_points
analysis.content.limitations
analysis.content.why_it_matters
analysis.content.detailed_summary
analysis.classification.topics
analysis.classification.entities
analysis.classification.confidence
analysis.classification.importance
analysis.classification.content_type
analysis.source_language
processing.analysis_model
processing.analyzed_at

Do not assume every optional field is present.

Treat analysis.content.limitations, analysis.classification.confidence, selection.status, and selection.score as editorial signals, not absolute truth.

Do not expose raw upstream article body content unless explicitly required and permitted.

Script Generation Pipeline

Prefer a staged pipeline rather than a single large prompt.

Recommended stages:

1. Fetch upstream digest records for the target time window.
2. Normalize records into internal news item models.
3. Filter unsuitable items.
4. Group related items into candidate topics.
5. Score topics for the target user and target script.
6. Select items for the rundown.
7. Fetch optional weather and headline context.
8. Build a structured rundown JSON.
9. Generate a script from the rundown and persona.
10. Validate the script against source facts and safety constraints.
11. Store the scenario, source references, prompt metadata, model metadata, and estimated duration.

Keep factual summarization and creative persona rendering separate where practical.

Facts should come from structured source records and verified context. Persona rendering may add transitions, greetings, section framing, and light commentary, but must not invent factual claims.

Scenario Output

Generated scenarios should preserve both human-readable script text and structured metadata.

Recommended stored fields or equivalent model concepts:

scenario title
scenario status
target duration minutes
estimated duration minutes
script body
structured rundown JSON
persona id or persona snapshot
source digest item ids
source provider keys
model name
prompt/schema version
generated at

The script text should be suitable for later TTS usage, but the MVP does not need to generate audio.

Do not store full prompts, full upstream article bodies, provider raw responses, or API secrets unless there is a clear debugging feature with redaction and explicit approval.

Rundown and Section Design

Use a structured rundown before generating final prose.

A rundown should represent the intended program flow, not just a flat list of articles.

Common section concepts:

* Opening greeting.
* Weather or local context.
* Top news.
* Geek or technical news.
* Security or caution topics.
* Light reading or curiosity topics.
* Follow-up items.
* Closing message.

Each section should carry enough metadata for generation:

section type
section title
source topic ids
summary facts
tone
allow_jokes
estimated seconds

Serious topics such as disasters, crimes, war, illness, or major security incidents should use restrained tone and should not include playful commentary unless explicitly appropriate.

Persona Handling

Personas define narration style, not facts.

Persona configuration may include:

* Name.
* First-person pronoun.
* Second-person pronoun.
* Tone.
* Speaking style.
* Catchphrases.
* Forbidden expressions.
* Serious-topic behavior.
* Preferred transition style.

Persona rendering must not override factual constraints, source limitations, or safety rules.

Do not use persona settings to produce misleading, discriminatory, harassing, or unsafe content.

Weather and Context Providers

Weather and general headline inputs are optional context providers.

Prefer structured APIs for weather. Do not use Web Search as the default weather provider.

General headlines may come from RSS, a news API, or Web Search depending on the implementation phase. Web Search should usually be treated as a supplemental verification or context source, not as the primary daily article ingestion mechanism.

Weather and headline provider configuration should be environment-driven.

Example local configuration:

RADIOPIPE_WEATHER_PROVIDER=fake
RADIOPIPE_WEATHER_DEFAULT_LOCATION=
RADIOPIPE_HEADLINE_PROVIDER=fake
RADIOPIPE_HEADLINE_DEFAULT_REGION=JP

Fake providers must remain available for tests and safe local development.

AI Processing

The primary AI pipeline turns structured digest records and contextual inputs into a structured rundown and a radio-style script.

The fake AI driver must remain available for tests and safe local development.

OpenAI-backed processing is selected through Laravel config and environment variables, not hard-coded in jobs.

Example local configuration:

RADIOPIPE_AI_DRIVER=fake
RADIOPIPE_RUNDOWN_MODEL=gpt-5.4-mini
RADIOPIPE_SCRIPT_MODEL=gpt-5.4-mini
RADIOPIPE_SCRIPT_BATCH_LIMIT=10
RADIOPIPE_SCRIPT_DAILY_LIMIT=100
RADIOPIPE_SCRIPT_TARGET_MINUTES=15
RADIOPIPE_SCRIPT_OUTPUT_SCHEMA_VERSION=1.0
OPENAI_API_KEY=
OPENAI_MODEL=gpt-5.4-mini
OPENAI_REQUEST_TIMEOUT=60
OPENAI_MAX_RETRIES=2

Never commit real OpenAI API keys. Do not log API keys, full prompts, full article bodies, full upstream responses, or raw OpenAI responses.

Automated tests must not call the real OpenAI API. Use Laravel HTTP fakes, mocked services, fixtures, or the fake AI driver.

Factuality and Editorial Safety

The application generates scripts from curated source data. It must preserve uncertainty and source limitations.

Important rules:

* Do not present uncertain claims as confirmed facts.
* Do not upgrade a headline-only item into a full story.
* Do not treat opinion essays as reported news.
* Do not treat promotional pages as independent verification.
* Do not invent quotes, numbers, dates, names, locations, or source details.
* Do not remove important caveats from source limitations.
* Do not use playful commentary for serious harm-related topics.
* Keep political, disaster, crime, medical, and financial topics neutral and restrained.
* Keep generated commentary clearly separate from source facts.

When a record has low confidence, strong limitations, or insufficient extracted content, either exclude it or frame it explicitly as a limited/uncertain item.

API Authentication

If radiopipe exposes private HTTP APIs, use Laravel Sanctum personal access tokens unless explicitly requested otherwise.

Do not add OAuth, login APIs, registration APIs, password reset flows, public user management screens, or custom plaintext API token columns unless explicitly requested.

Tokens should use narrow abilities for read-only or generation operations, for example:

scenarios:read
scenarios:generate

Never log raw personal access tokens. Print newly created or rotated tokens only once in command output, and do not commit generated tokens.

Scenario JSON API

If a private Scenario JSON API is added, it should expose generated scenario records through read-only routes such as:

GET /api/scenarios
GET /api/scenarios/{id}

These routes must stay protected by auth:sanctum and appropriate abilities.

Do not expose prompts, provider raw responses, API keys, secrets, or full upstream article bodies.

Write APIs, public unauthenticated access, multi-user account management, audio generation APIs, and public publishing endpoints are intentionally deferred unless explicitly requested.

API Documentation

When API behavior changes, update docs/api.md in the same task.

Do not document planned API features as current behavior.

HTTP Client Smoke Tests

Root tests/http/ may contain PhpStorm HTTP Client smoke tests for manual integration checks.

Do not place these files under src/tests. Laravel application tests remain under src/tests.

Do not put secrets in committed HTTP Client files. Keep real API tokens in tests/http/http-client.private.env.json, which must stay ignored.

Do not wire these smoke tests into make test unless explicitly requested. Keep assertions broad, stable, and environment-independent.

Object Storage

Use MinIO locally as an S3-compatible object storage service.

The Laravel application should use the S3 filesystem driver.

Required local environment variables should include:

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=
AWS_BUCKET=
AWS_ENDPOINT=
AWS_URL=
AWS_USE_PATH_STYLE_ENDPOINT=true

Do not rely on local filesystem storage for user-generated content.

If generated audio files are added in the future, store them in S3-compatible object storage rather than local disk.

Logging

Use stderr logging by default.

LOG_CHANNEL=stderr

Do not rely on storage/logs for production observability.

Do not log secrets, bearer tokens, API keys, full prompts, full article bodies, full scenario source payloads, or raw provider responses.

Application Environment Configuration

Laravel application configuration must follow Laravel’s standard .env loading behavior.

In this repository, the Laravel application lives under src/, so the application environment files are:

src/.env.example  # Sample local development settings. Committed.
src/.env          # Developer-specific local settings. Not committed.

Environment variables that control Laravel application behavior must be defined in src/.env.example / src/.env, not in docker-compose.yml.

Examples:

APP_*
DB_*
CACHE_*
SESSION_*
QUEUE_*
REDIS_*
FILESYSTEM_*
AWS_*
LOG_*
RADIOPIPE_*
OPENAI_*

This keeps the project aligned with Laravel conventions and avoids duplicating the same settings across docker-compose.yml and src/.env. From an SSOT / DRY perspective, Laravel application settings belong in src/.env.

Layer Responsibilities

docker-compose.yml defines the local development infrastructure layer.

It may define:

service image
build context
Dockerfile path
ports
volumes
networks
healthchecks
depends_on
middleware bootstrap settings

Settings required to bootstrap middleware containers may remain in docker-compose.yml.

Examples:

mysql:
  environment:
    MYSQL_DATABASE: radiopipe
    MYSQL_USER: radiopipe
    MYSQL_PASSWORD: radiopipe
    MYSQL_ROOT_PASSWORD: root
minio:
  environment:
    MINIO_ROOT_USER: minioadmin
    MINIO_ROOT_PASSWORD: minioadmin

However, the database, cache, session, queue, storage, and logging backends used by Laravel are application-layer settings. They must be defined in src/.env.example / src/.env.

Even when values overlap, such as MYSQL_DATABASE and DB_DATABASE, they belong to different layers.

MYSQL_DATABASE  # Initial database name created by the MySQL container
DB_DATABASE     # Database name used by the Laravel application connection

This kind of duplication is acceptable because the variables configure different layers.

Docker Compose Must Not Load src/.env

This project must not use Docker Compose env_file to load src/.env.

src/.env is the environment file for the Laravel application. The only intended usage is for Laravel itself to load it through Laravel’s standard mechanism.

Docker Compose and other layers must not understand, load, or depend on src/.env.

Developers may still modify their local src/.env manually after copying it from src/.env.example when they need to change local application behavior.

Laravel Cloud Compatibility

src/.env.example is committed as a sample configuration for local development.

In Laravel Cloud, production environment variables are managed by Laravel Cloud. Therefore, committing local development defaults to src/.env.example does not affect production behavior on Laravel Cloud.

It is acceptable for src/.env.example to include sample values required for the local Docker Compose environment.

Examples:

DB_HOST=mysql
DB_DATABASE=radiopipe
DB_USERNAME=radiopipe
DB_PASSWORD=radiopipe
REDIS_HOST=valkey
AWS_ACCESS_KEY_ID=minioadmin
AWS_SECRET_ACCESS_KEY=minioadmin
AWS_BUCKET=radiopipe-local
AWS_ENDPOINT=http://minio:9000

These are sample credentials for local development only. They are not production secrets. In this context, committing them to src/.env.example is not considered a vulnerability.

App Key Handling

Do not commit a fixed Laravel APP_KEY.

Leave it empty in src/.env.example.

APP_KEY=

Local setup through the Makefile generates it:

make up

APP_KEY is used for encrypted cookies and encrypted application data, so it should be generated per environment, even for local development.

Development Commands

Use the Makefile as the primary entrypoint for local development tasks.

Humans and agents should prefer these commands instead of calling Docker Compose directly:

* make build
* make up
* make down
* make test
* make lint
* make fix

Use raw docker compose commands only when debugging the local environment or when the Makefile does not provide the required operation.

The php-cli and node services are expected to be long-running services so that Makefile tasks can use docker compose exec.

Makefile Policy

The Makefile is the canonical interface for local development operations.

Agents should prefer existing Makefile targets over direct docker compose commands.

Do not add new Makefile targets unless there is a repeated workflow that cannot be expressed with the existing targets.

Keep the command surface small. The primary commands are:

* make build
* make up
* make down
* make test
* make lint
* make fix

Use make destroy only when a full local reset is explicitly requested.

Do not replace docker compose exec with docker compose run for normal development commands unless there is a concrete reason. The php-cli and node services are intentionally long-running so they can be used as execution targets.

Documentation

Use docs/ for development notes and design documents.

Keep documentation short, practical, and close to the current implementation.

When introducing a new local service, provider, command, or tool, update the README or relevant document.

Project Scripts

Project-level operational scripts live under the repository root scripts/ directory.

Do not place Laravel application scripts under src/scripts unless they are part of the Laravel application itself.

Manual operation helpers may exist for local development, but they must not become hidden production dependencies.

Do not depend on local pollers or shell scripts in tests, application logic, scheduler logic, or queue processing unless explicitly requested.

Commit Hygiene

Keep commits focused.

Do not mix infrastructure, formatting, and feature work unless explicitly requested.

Prefer clear commit messages such as:

chore: scaffold local development stack
chore: add Laravel application skeleton
chore: configure testing and static analysis
feat: add upstream digest provider client
feat: generate scenario rundown
feat: generate radio script

Safety Rules for Agents

Before making large changes:

1. Inspect the existing repository structure.
2. Preserve the intended root layout.
3. Keep Laravel files under src/.
4. Do not commit secrets.
5. Do not remove existing documentation unless asked.
6. Do not introduce unrelated dependencies.
7. Prefer small, reviewable changes.
8. Run relevant tests or explain why they could not be run.

If a requirement is ambiguous, choose the simplest Laravel Cloud-compatible implementation and document the assumption.
