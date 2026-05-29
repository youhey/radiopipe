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
      "title": "今日のギークニュース",
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
    "title": "今日のギークニュース",
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
