# Environment Variables

この文書は `src/.env.example` に含まれる Laravel 標準外の radiopipe 向け環境変数を説明します。
実際の値は `src/.env` または Laravel Cloud の環境変数で管理し、API key、OAuth secret、管理者メールアドレスなどの実値は commit しません。

## Google OAuth

Filament 管理画面の Google OAuth ログインで使います。

| 変数 | 既定値 | 説明 |
|---|---|---|
| `GOOGLE_CLIENT_ID` | empty | Google OAuth client ID。 |
| `GOOGLE_CLIENT_SECRET` | empty | Google OAuth client secret。commit しません。 |
| `GOOGLE_REDIRECT_URI` | `${APP_URL}/auth/google/callback` | Google OAuth callback URL。Google Cloud Console 側の authorized redirect URI と一致させます。 |

## OpenAI

OpenAI-backed analyzer や将来の AI 生成処理で使う共通設定です。

| 変数 | 既定値 | 説明 |
|---|---|---|
| `OPENAI_API_KEY` | empty | OpenAI API key。`fake` driver 利用時は不要です。commit しません。 |
| `OPENAI_MODEL` | `gpt-5.4-mini` | 汎用の OpenAI model 名。個別機能の model 設定がある場合はそちらが優先されます。 |
| `OPENAI_REQUEST_TIMEOUT` | `60` | OpenAI HTTP request timeout 秒数。 |
| `OPENAI_MAX_RETRIES` | `2` | OpenAI request の最大 retry 回数。 |

## Laravel Cloud

Filament Dashboard の Cloud Status widget で、Laravel Cloud の最新 deployment 状態を表示するために使います。

| 変数 | 既定値 | 説明 |
|---|---|---|
| `LARAVEL_CLOUD_API_TOKEN` | empty | Laravel Cloud API token。commit しません。未設定の場合、Dashboard には safe state を表示します。 |
| `LARAVEL_CLOUD_ENVIRONMENT_ID` | empty | Laravel Cloud API の environment identifier。例: `env-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`。 |

## Upstream Provider

構造化 digest JSON を取得する upstream provider の設定です。

| 変数 | 既定値 | 説明 |
|---|---|---|
| `RADIOPIPE_UPSTREAM_PROVIDER` | `fake` | upstream provider driver。local/test では `fake` が安全な既定値です。 |
| `RADIOPIPE_UPSTREAM_URL` | empty | upstream provider API base URL。 |
| `RADIOPIPE_UPSTREAM_KEY` | empty | upstream provider API key/token。commit しません。 |
| `RADIOPIPE_UPSTREAM_REQUEST_TIMEOUT` | `30` | upstream API request timeout 秒数。 |
| `RADIOPIPE_UPSTREAM_MAX_RETRIES` | `2` | upstream API request の最大 retry 回数。 |
| `RADIOPIPE_UPSTREAM_DEFAULT_WINDOW_HOURS` | `24` | 明示指定がない場合に取得する記事 window の時間数。 |
| `RADIOPIPE_UPSTREAM_DEFAULT_LIMIT` | `100` | 明示指定がない場合の取得件数上限。 |

## Weather Provider

天気コンテキスト取得の設定です。

| 変数 | 既定値 | 説明 |
|---|---|---|
| `RADIOPIPE_WEATHER_PROVIDER` | `fake` | weather provider driver。local/test では `fake` が安全な既定値です。 |
| `RADIOPIPE_WEATHER_REQUEST_TIMEOUT` | `10` | weather provider request timeout 秒数。 |
| `RADIOPIPE_WEATHER_MAX_RETRIES` | `2` | weather provider request の最大 retry 回数。 |
| `RADIOPIPE_OPEN_METEO_BASE_URL` | `https://api.open-meteo.com` | Open-Meteo provider の base URL。 |
| `RADIOPIPE_WEATHER_DEFAULT_LATITUDE` | empty | 既定地点の latitude。 |
| `RADIOPIPE_WEATHER_DEFAULT_LONGITUDE` | empty | 既定地点の longitude。 |
| `RADIOPIPE_WEATHER_DEFAULT_LOCATION_NAME` | empty | 既定地点の表示名。 |
| `RADIOPIPE_WEATHER_DEFAULT_TIMEZONE` | `Asia/Tokyo` | 既定地点の timezone。 |

## News Provider

一般ヘッドライン取得の設定です。

| 変数 | 既定値 | 説明 |
|---|---|---|
| `RADIOPIPE_NEWS_PROVIDER` | `fake` | news provider driver。local/test では `fake` が安全な既定値です。 |
| `RADIOPIPE_NEWS_REQUEST_TIMEOUT` | `10` | news provider request timeout 秒数。 |
| `RADIOPIPE_NEWS_MAX_RETRIES` | `2` | news provider request の最大 retry 回数。 |
| `RADIOPIPE_NEWSAPI_BASE_URL` | `https://newsapi.org` | NewsAPI provider の base URL。 |
| `RADIOPIPE_NEWSAPI_KEY` | empty | NewsAPI API key。commit しません。 |
| `RADIOPIPE_NEWSAPI_COUNTRY` | `jp` | NewsAPI country filter。 |
| `RADIOPIPE_NEWSAPI_CATEGORY` | `general` | NewsAPI category filter。 |
| `RADIOPIPE_NEWSAPI_LANGUAGE` | `ja` | NewsAPI language filter。 |
| `RADIOPIPE_NEWSAPI_PAGE_SIZE` | `20` | NewsAPI request の page size。 |
| `RADIOPIPE_NEWSAPI_SOURCES` | empty | NewsAPI sources filter。複数指定の形式は provider 実装に従います。 |
| `RADIOPIPE_RSS_FEEDS` | empty | RSS provider で読む feed URL 一覧。comma-separated で指定します。 |

## Topic Editorial Evaluation

Phase 6 `Topic Editorial Evaluation` の analyzer 設定です。

| 変数 | 既定値 | 説明 |
|---|---|---|
| `RADIOPIPE_TOPIC_EDITORIAL_ANALYZER` | `fake` | topic editorial analyzer driver。`fake` は local/test 用の安全な既定値、`openai` は OpenAI-backed analyzer です。 |
| `RADIOPIPE_TOPIC_EDITORIAL_MODEL` | `gpt-5.4-mini` | OpenAI-backed topic editorial analyzer で使う model 名。 |

## Scenario Generation

将来の rundown / script generation 用の AI 設定です。
現時点で未実装の処理も含むため、実装済みの機能だけがこれらの値を参照します。

| 変数 | 既定値 | 説明 |
|---|---|---|
| `RADIOPIPE_SCENARIO_GENERATOR` | `fake` | scenario generator driver。`fake` は local/test 用の安全な既定値、`openai` は OpenAI-backed generator です。 |
| `RADIOPIPE_SCENARIO_MODEL` | `gpt-5.4-mini` | OpenAI scenario generator で使う model 名。 |
| `RADIOPIPE_SCENARIO_MAX_TOPICS` | `5` | scenario generation で使う最大 topic 数。 |
| `RADIOPIPE_SCENARIO_TARGET_SECONDS` | `900` | scenario の目標読み上げ秒数。 |
| `RADIOPIPE_AI_DRIVER` | `fake` | scenario generation 系 AI driver の既定値。 |
| `RADIOPIPE_RUNDOWN_MODEL` | `gpt-5.4-mini` | rundown generation 用 model 名。 |
| `RADIOPIPE_SCRIPT_MODEL` | `gpt-5.4-mini` | script generation 用 model 名。 |
| `RADIOPIPE_SCRIPT_BATCH_LIMIT` | `10` | script generation batch の処理上限。 |
| `RADIOPIPE_SCRIPT_DAILY_LIMIT` | `100` | script generation の日次上限。 |
| `RADIOPIPE_SCRIPT_TARGET_MINUTES` | `15` | 目標 script 長の分数。 |
| `RADIOPIPE_SCRIPT_OUTPUT_SCHEMA_VERSION` | `1.0` | script output schema version。 |

## Pipeline Schedule

Laravel scheduler は named callback `radiopipe:pipeline:compile` を 10 分ごとに実行します。
scheduled pipeline は `radiopipe:topics:nominate` を先に実行し、成功した場合だけ `radiopipe:episodes:compile` を実行します。
hosting platform 側で Laravel scheduler を実行する設定は別途必要です。

| 変数 | 既定値 | 説明 |
|---|---|---|
| `RADIOPIPE_TOPIC_NOMINATION_THROTTLE_SECONDS` | `3600` | `topics:nominate` の成功後に再実行を skip する throttle lock TTL。`0` 以下で無効。 |

## Admin

Filament 管理画面へのアクセス制御設定です。

| 変数 | 既定値 | 説明 |
|---|---|---|
| `RADIOPIPE_ADMIN_ALLOWED_EMAILS` | empty | 管理画面に入れる Google account email の allow-list。comma-separated、case-insensitive、前後空白は無視されます。empty の場合は誰も管理画面へ入れません。 |
| `RADIOPIPE_ADMIN_DEV_LOGIN_ENABLED` | `false` | local/testing 限定 dev login helper を有効化するか。production では有効にしません。 |
| `RADIOPIPE_ADMIN_DEV_LOGIN_EMAIL` | empty | dev login helper でログインする local 開発用 email。`RADIOPIPE_ADMIN_ALLOWED_EMAILS` にも含まれている必要があります。 |

dev login helper は `GET /_local/admin/login` で利用します。
Codex や開発者が local browser で Filament UI を確認するためのもので、本番認証の代替ではありません。
