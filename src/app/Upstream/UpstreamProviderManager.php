<?php

namespace App\Upstream;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * 設定に基づいて upstream provider と既定 query を解決します。
 */
class UpstreamProviderManager
{
    /**
     * provider 名から upstream provider を解決します。
     */
    public function driver(?string $driver = null): UpstreamProvider
    {
        $resolvedDriver = $driver ?? $this->stringConfig('radiopipe.upstream.provider', 'fake');

        return match ($resolvedDriver) {
            'fake' => new FakeUpstreamProvider(),
            'digestpipe' => new DigestpipeUpstreamProvider(
                $this->stringConfig('radiopipe.upstream.url', ''),
                $this->nullableStringConfig('radiopipe.upstream.key'),
                $this->intConfig('radiopipe.upstream.request_timeout', 30),
                $this->intConfig('radiopipe.upstream.max_retries', 2),
            ),
            default => throw new InvalidArgumentException("Unsupported radiopipe upstream provider [{$resolvedDriver}]."),
        };
    }

    /**
     * 設定された既定 window と limit の query を作成します。
     */
    public function defaultQuery(?CarbonImmutable $now = null): UpstreamArticleQuery
    {
        $current = $now ?? CarbonImmutable::now('UTC');
        $windowHours = $this->intConfig('radiopipe.upstream.default_window_hours', 24);

        return new UpstreamArticleQuery(
            from: $current->subHours($windowHours),
            to: $current,
            limit: $this->intConfig('radiopipe.upstream.default_limit', 100),
        );
    }

    /**
     * 設定済み provider で upstream 記事を取得します。
     *
     * @return list<UpstreamArticleItem>
     */
    public function fetch(?UpstreamArticleQuery $query = null): array
    {
        return $this->driver()->fetch($query ?? $this->defaultQuery());
    }

    /**
     * 文字列設定を取得します。
     */
    private function stringConfig(string $key, string $default): string
    {
        $value = config($key, $default);

        if (! is_string($value) || $value === '') {
            return $default;
        }

        return $value;
    }

    /**
     * nullable な文字列設定を取得します。
     */
    private function nullableStringConfig(string $key): ?string
    {
        $value = config($key);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    /**
     * 整数設定を取得します。
     */
    private function intConfig(string $key, int $default): int
    {
        $value = config($key, $default);

        if (! is_int($value)) {
            return $default;
        }

        return $value;
    }
}
