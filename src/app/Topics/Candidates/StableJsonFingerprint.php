<?php

namespace App\Topics\Candidates;

use JsonException;
use RuntimeException;

/**
 * 安定した JSON 表現から SHA-256 fingerprint を生成する。
 */
class StableJsonFingerprint
{
    /**
     * @param array<string, mixed> $payload
     */
    public function hash(array $payload): string
    {
        $normalized = $this->normalize($payload);

        try {
            $json = json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new RuntimeException($exception->getMessage(), previous: $exception);
        }

        return hash('sha256', $json);
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->normalize($item);
        }

        return $value;
    }
}
