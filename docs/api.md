# API

radiopipe の private Web API は Laravel Sanctum personal access token で保護します。

## API Token

API token は Filament admin の `Operations > API Tokens` から管理できます。

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

`GET /api/episodes` は `auth:sanctum` と `abilities:episodes:read` で保護されています。

```bash
curl -H "Authorization: Bearer ${RADIOPIPE_API_TOKEN}" \
  https://example.test/api/episodes
```

この API は生成済み Episode の read-only JSON を返します。token なし、または `episodes:read` ability のない token ではアクセスできません。
