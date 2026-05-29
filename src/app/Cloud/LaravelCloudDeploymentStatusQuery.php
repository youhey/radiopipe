<?php

namespace App\Cloud;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Laravel Cloud API から最新 deployment 状態を取得する。
 */
class LaravelCloudDeploymentStatusQuery
{
    private const BASE_URL = 'https://cloud.laravel.com/api';

    private const CACHE_TTL_SECONDS = 60;

    /**
     * Dashboard 表示用の deployment status を返す。
     */
    public function status(): LaravelCloudDeploymentStatus
    {
        $apiToken = $this->configString('api_token');
        $environmentId = $this->configString('environment_id');

        if ($apiToken === null || $environmentId === null) {
            return new LaravelCloudDeploymentStatus(
                configured: false,
                available: false,
                status: 'Not configured',
                errorMessage: 'Laravel Cloud API is not configured.',
            );
        }

        return Cache::remember(
            $this->cacheKey($environmentId),
            self::CACHE_TTL_SECONDS,
            fn (): LaravelCloudDeploymentStatus => $this->load($apiToken, $environmentId),
        );
    }

    /**
     * Laravel Cloud API から最新 deployment を取得する。
     */
    private function load(string $apiToken, string $environmentId): LaravelCloudDeploymentStatus
    {
        try {
            $response = Http::withToken($apiToken)
                ->acceptJson()
                ->timeout(10)
                ->get(sprintf('%s/environments/%s/deployments', self::BASE_URL, $environmentId));

            if (! $response->successful()) {
                return $this->unavailable('Laravel Cloud API request failed.');
            }

            $deployment = data_get($response->json(), 'data.0');

            if (! is_array($deployment)) {
                return $this->unavailable('No deployments found.');
            }

            $attributes = data_get($deployment, 'attributes', []);
            $payload = $this->stringKeyedArray(array_merge($deployment, is_array($attributes) ? $attributes : []));

            return $this->fromPayload($payload);
        } catch (Throwable) {
            return $this->unavailable('Laravel Cloud deployment status could not be loaded.');
        }
    }

    /**
     * API payload から表示状態を作る。
     *
     * @param array<string, mixed> $payload
     */
    private function fromPayload(array $payload): LaravelCloudDeploymentStatus
    {
        $rawStatus = $this->stringValue($payload['status'] ?? null);
        $failureReason = $this->stringValue($payload['failure_reason'] ?? null);
        $startedAt = $this->stringValue($payload['started_at'] ?? null);
        $finishedAt = $this->stringValue($payload['finished_at'] ?? null);
        $status = $rawStatus ?? $this->inferStatus($failureReason, $startedAt, $finishedAt);

        return new LaravelCloudDeploymentStatus(
            configured: true,
            available: true,
            status: $this->statusLabel($status),
            deploymentId: $this->stringValue($payload['id'] ?? null),
            branch: $this->stringValue($payload['branch_name'] ?? null),
            commitHash: $this->stringValue($payload['commit_hash'] ?? null),
            commitMessage: $this->stringValue($payload['commit_message'] ?? null),
            commitAuthor: $this->stringValue($payload['commit_author'] ?? null),
            startedAt: $startedAt,
            finishedAt: $finishedAt,
            failureReason: $failureReason,
        );
    }

    /**
     * 取得不能状態を返す。
     */
    private function unavailable(string $message): LaravelCloudDeploymentStatus
    {
        return new LaravelCloudDeploymentStatus(
            configured: true,
            available: false,
            status: 'Unavailable',
            errorMessage: $message,
        );
    }

    /**
     * status がない場合に deployment 状態を推定する。
     */
    private function inferStatus(?string $failureReason, ?string $startedAt, ?string $finishedAt): string
    {
        if ($failureReason !== null) {
            return 'failed';
        }

        if ($startedAt !== null && $finishedAt === null) {
            return 'running';
        }

        if ($finishedAt !== null) {
            return 'completed';
        }

        return 'unknown';
    }

    /**
     * API status を表示用 label に変換する。
     */
    private function statusLabel(string $status): string
    {
        return str($status)
            ->after('deployment.')
            ->headline()
            ->toString();
    }

    /**
     * services config の文字列値を返す。
     */
    private function configString(string $key): ?string
    {
        $value = config("services.laravel_cloud.{$key}");

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * nullable string として扱える値を返す。
     */
    private function stringValue(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    /**
     * environment ごとの cache key を返す。
     */
    private function cacheKey(string $environmentId): string
    {
        return 'radiopipe:laravel-cloud:deployment-status:' . sha1($environmentId);
    }

    /**
     * 文字列キーの配列として扱える値だけを抽出する。
     *
     * @param array<mixed> $value
     *
     * @return array<string, mixed>
     */
    private function stringKeyedArray(array $value): array
    {
        $result = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }
}
