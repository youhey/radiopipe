<?php

namespace App\Console\Commands;

use App\ApiTokens\ApiTokenService;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * private Web API 用 Sanctum token を token name 単位で再発行する Artisan command。
 */
class RotateApiTokenCommand extends Command
{
    /** @var string */
    protected $signature = 'radiopipe:users:rotate-api-token
        {email : API user email address}
        {--token-name= : Sanctum token name}
        {--ability=* : Token ability. Defaults to episodes:read}';

    /** @var string */
    protected $description = 'Revoke named API tokens for a user and issue a replacement Sanctum token.';

    /**
     * token を再発行する。
     */
    public function handle(ApiTokenService $tokens): int
    {
        $email = $this->email();
        $tokenName = $this->stringOption('token-name', $tokens->defaultTokenName());
        $abilities = $this->abilities($tokens);

        $validator = Validator::make([
            'email' => $email,
            'token_name' => $tokenName,
            'abilities' => $abilities,
        ], [
            'email' => ['required', 'email'],
            'token_name' => ['required', 'string', 'max:255'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User) {
            $this->error('API user was not found.');

            return self::FAILURE;
        }

        $revokedCount = $tokens->revokeTokensByName($user, $tokenName);
        $createdToken = $tokens->createToken($user, $tokenName, $abilities);

        $this->info('API token rotated.');
        $this->line('User email: ' . $user->email);
        $this->line('Token name: ' . $createdToken->accessToken->name);
        $this->line('Revoked tokens: ' . $revokedCount);
        $this->line('Abilities: ' . implode(', ', $abilities));
        $this->line('Plain text token: ' . $createdToken->plainTextToken);
        $this->warn('Copy this token now. It will not be shown again.');

        return self::SUCCESS;
    }

    /**
     * email argument を文字列として返す。
     */
    private function email(): string
    {
        return $this->argument('email');
    }

    /**
     * string option を返す。
     */
    private function stringOption(string $name, string $default): string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * 指定 ability または既定 ability を返す。
     *
     * @return list<string>
     */
    private function abilities(ApiTokenService $tokens): array
    {
        $abilities = $this->option('ability');

        if ($abilities === []) {
            return $tokens->defaultAbilities();
        }

        $normalized = [];

        foreach ($abilities as $ability) {
            if (is_string($ability) && $ability !== '') {
                $normalized[] = $ability;
            }
        }

        return $normalized === [] ? $tokens->defaultAbilities() : array_values(array_unique($normalized));
    }
}
