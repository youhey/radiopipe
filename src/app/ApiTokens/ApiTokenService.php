<?php

namespace App\ApiTokens;

use App\Models\User;
use InvalidArgumentException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * private Web API 用 Sanctum token を管理する service。
 */
class ApiTokenService
{
    public const ABILITY_EPISODES_READ = 'episodes:read';

    public const ABILITY_TOPICS_RATE = 'topics:rate';

    public const DEFAULT_TOKEN_NAME = 'radiopipe-api';

    /**
     * UI/CLI で発行可能な ability 一覧を返す。
     *
     * @return array<string, string>
     */
    public function allowedAbilities(): array
    {
        $abilities = config('radiopipe.api_tokens.allowed_abilities', [self::ABILITY_EPISODES_READ]);

        if (! is_array($abilities)) {
            return [self::ABILITY_EPISODES_READ => self::ABILITY_EPISODES_READ];
        }

        $allowed = [];

        foreach ($abilities as $ability) {
            if (! is_string($ability) || $ability === '') {
                continue;
            }

            $allowed[$ability] = $ability;
        }

        return $allowed === [] ? [self::ABILITY_EPISODES_READ => self::ABILITY_EPISODES_READ] : $allowed;
    }

    /**
     * 既定 ability 一覧を返す。
     *
     * @return list<string>
     */
    public function defaultAbilities(): array
    {
        $abilities = config('radiopipe.api_tokens.default_abilities', [self::ABILITY_EPISODES_READ]);

        if (! is_array($abilities)) {
            return [self::ABILITY_EPISODES_READ];
        }

        return $this->normalizeConfigAbilities($abilities);
    }

    /**
     * 既定 token name を返す。
     */
    public function defaultTokenName(): string
    {
        $name = config('radiopipe.api_tokens.default_name', self::DEFAULT_TOKEN_NAME);

        return is_string($name) && $name !== '' ? $name : self::DEFAULT_TOKEN_NAME;
    }

    /**
     * User に Sanctum token を発行する。
     *
     * @param array<int, mixed> $abilities
     */
    public function createToken(User $user, string $name, array $abilities): CreatedApiToken
    {
        $normalizedAbilities = $this->normalizeAbilities($abilities);
        $this->ensureAllowedAbilities($normalizedAbilities);

        $token = $user->createToken($name, $normalizedAbilities);

        return new CreatedApiToken(
            accessToken: $token->accessToken,
            plainTextToken: $token->plainTextToken,
        );
    }

    /**
     * token を個別失効する。
     */
    public function revokeToken(PersonalAccessToken $token): void
    {
        $token->delete();
    }

    /**
     * User の全 token を失効する。
     */
    public function revokeAllTokens(User $user): int
    {
        $count = $user->tokens()->getQuery()->delete();

        return is_int($count) ? $count : 0;
    }

    /**
     * User の指定 token name の token を失効する。
     */
    public function revokeTokensByName(User $user, string $name): int
    {
        $count = $user->tokens()
            ->getQuery()
            ->where('name', $name)
            ->delete();

        return is_int($count) ? $count : 0;
    }

    /**
     * config 由来の ability 入力を正規化する。
     *
     * @param array<mixed, mixed> $abilities
     *
     * @return list<string>
     */
    private function normalizeConfigAbilities(array $abilities): array
    {
        return $this->normalizeAbilities(array_values($abilities));
    }

    /**
     * ability 入力を正規化する。
     *
     * @param array<int, mixed> $abilities
     *
     * @return list<string>
     */
    private function normalizeAbilities(array $abilities): array
    {
        $normalized = [];

        foreach ($abilities as $ability) {
            if (! is_string($ability)) {
                continue;
            }

            $ability = trim($ability);

            if ($ability === '') {
                continue;
            }

            $normalized[] = $ability;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * allow-list 外の ability を拒否する。
     *
     * @param list<string> $abilities
     */
    private function ensureAllowedAbilities(array $abilities): void
    {
        $allowedAbilities = array_keys($this->allowedAbilities());

        foreach ($abilities as $ability) {
            if (! in_array($ability, $allowedAbilities, true)) {
                throw new InvalidArgumentException("Unsupported API token ability [{$ability}].");
            }
        }
    }
}
