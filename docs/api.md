# API

radiopipe の private Web API は Laravel Sanctum personal access token で保護します。

Episode JSON API の機械可読な契約は [`docs/openapi.yaml`](openapi.yaml) に定義します。下流 client が依存するため、レスポンス形を変更する場合は OpenAPI schema と schema validation test も同じ変更で更新してください。

## API Token

API token は Filament admin の `Settings > API Tokens` から管理できます。

- token metadata の一覧表示
- User に対する token 発行
- 発行直後の plain text token の一度だけの表示
- token の個別失効
- User の全 token 失効

plain text token は発行直後だけ表示され、DB には保存されません。token hash、OAuth token、API secret は管理画面に表示しません。

CLI でも token を発行・再発行できます。

```bash
php artisan radiopipe:users:create-api-user user@example.test --name="Radiopipe API User"
php artisan radiopipe:users:rotate-api-token user@example.test
```

既定 token name は `radiopipe-api` です。既定 ability は `episodes:read` です。
topic rating API を使う token には `topics:rate` ability を追加してください。既存 token は自動更新されません。

## Episodes API

Episode JSON API は `auth:sanctum` と `abilities:episodes:read` で保護されています。

```bash
curl -H "Authorization: Bearer ${RADIOPIPE_API_TOKEN}" \
  https://example.test/api/episodes
```

token なし、または `episodes:read` ability のない token ではアクセスできません。API は read-only です。Episode の作成、更新、削除は提供しません。

### GET /api/episodes

生成済み Episode の軽量一覧を返します。既定では `completed` のみを `published_at desc, id desc` で返します。

Query parameters:

- `limit`: 既定 `100`、最大 `500`
- `status`: 既定 `completed`
- `character`: `character_key` で絞り込み
- `from`: `published_at >= from`
- `to`: `published_at <= to`

Response example:

```json
{
  "episodes": [
    {
      "episode_key": "episode_2026-05-29_0700_neko_nyan_001",
      "status": "completed",
      "published_at": "2026-05-29T07:00:00+09:00",
      "processed_at": "2026-05-29T06:55:12+09:00",
      "title": "GitHub防衛線とブラウザ内コンテナの話",
      "character": {
        "key": "neko_nyan_balanced_radio",
        "name": "ねこにゃん"
      },
      "language": "ja"
    }
  ],
  "meta": {
    "count": 1,
    "limit": 100
  }
}
```

一覧 API は `scenario`、`topics`、内部 snapshot JSON を返しません。

### GET /api/episodes/latest

最新の `completed` Episode を詳細形式で返します。`completed` Episode が存在しない場合は 404 を返します。`failed` Episode は返しません。

### GET /api/episodes/{episode_key}

指定した `episode_key` の `completed` Episode を詳細形式で返します。存在しない場合、または `failed` Episode の場合は 404 を返します。

Detail response example:

```json
{
  "episode": {
    "episode_key": "episode_2026-05-29_0700_neko_nyan_001",
    "status": "completed",
    "published_at": "2026-05-29T07:00:00+09:00",
    "processed_at": "2026-05-29T06:55:12+09:00",
    "title": "GitHub防衛線とブラウザ内コンテナの話",
    "character": {
      "key": "neko_nyan_balanced_radio",
      "name": "ねこにゃん"
    },
    "language": "ja",
    "scenario_json": {
      "sections": []
    },
    "topics": [
      {
        "topic_id": "upstream:236",
        "status": "used_in_scenario",
        "title": "GitHubアカウントのセキュリティ設定を点検するCLI「Moat」",
        "summary": "GitHubの設定を読み取り専用で点検するCLIツールです。",
        "why_it_matters": "リポジトリ運用やサプライチェーン安全性に関わります。",
        "source_name": "Laravel News",
        "url": "https://laravel-news.com/example",
        "discussion_url": null,
        "sort_order": 1
      }
    ]
  }
}
```

Detail API は client 表示用に整えた topic 情報だけを返します。以下は返しません。

- `topic_draft_json`
- `screening_json`
- `editorial_json`
- `scenario_selection_json`
- raw prompts
- raw model responses
- raw upstream article bodies
- API keys / OAuth tokens / authorization headers / secrets

## Topics Rating API

Topic Rating API は `auth:sanctum` と `abilities:topics:rate` で保護されています。
rating は upstream digestpipe の Article Rating API に転送され、radiopipe 側には rating 履歴を保存しません。
`RADIOPIPE_UPSTREAM_KEY` には digestpipe 側の `digests:rate` ability が必要です。

topic id は path segment として渡します。`:` など URL 上で扱いに注意が必要な文字を含む場合は URL encode してください。

### PUT /api/topics/{id}/rating

topic rating を設定または上書きします。

Request:

```json
{
  "rating": 1
}
```

受け付ける rating 値:

- `-1`: Bad
- `1`: Good
- `2` - `5`: Good の段階評価

`0`、範囲外、未指定、非整数は 422 です。Good/Bad UI だけで使う client は `-1` と `1` を使えば十分です。

Response:

```json
{
  "topic_rating": {
    "topic_id": "upstream:236",
    "upstream": {
      "provider": "digestpipe",
      "id": 236
    },
    "rating": 1,
    "rated_at": "2026-05-31T10:15:00+09:00"
  }
}
```

### DELETE /api/topics/{id}/rating

topic rating を解除します。`204 No Content` ではなく、解除後の状態を返します。

Response:

```json
{
  "topic_rating": {
    "topic_id": "upstream:236",
    "upstream": {
      "provider": "digestpipe",
      "id": 236
    },
    "rating": null,
    "rated_at": null
  }
}
```

rating API は digestpipe 内部名の `manual_rating`、`manual_rated_at`、raw upstream response、upstream token を返しません。
