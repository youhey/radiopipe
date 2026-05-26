<?php

namespace App\Upstream;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * 設定に基づいて Upstream Provider と既定 Query を解決
 */
class UpstreamProviderManager
{
    /**
     * Provider 名から Upstream Provider を解決して返す
     *
     * @param string|null $driver
     *
     * @return UpstreamProvider
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
     * 設定された既定 Window と Limit の Query を作成して返す
     *
     * @param CarbonImmutable|null $now
     *
     * @return UpstreamArticleQuery
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
     * 設定済み Provider で Upstream Items を取得して返す
     *
     * @param UpstreamArticleQuery|null $query
     *
     * @return list<UpstreamArticleItem>
     */
    public function fetch(?UpstreamArticleQuery $query = null): array
    {
        return $this->driver()->fetch($query ?? $this->defaultQuery());
    }

    /**
     * 文字列設定を返す
     *
     * @param string $key
     * @param string $default
     *
     * @return string
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
     * nullable な文字列設定を返す
     *
     * @param string $key
     *
     * @return string|null
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
     * 整数設定を返す
     *
     * @param string $key
     * @param int $default
     *
     * @return int
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
