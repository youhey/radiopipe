<?php

namespace App\Console\Commands;

use App\ApiTokens\ApiTokenService;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * private Web API 用 User と Sanctum token を作成する Artisan command。
 */
class CreateApiUserCommand extends Command
{
    /** @var string */
    protected $signature = 'radiopipe:users:create-api-user
        {email : API user email address}
        {--name=Radiopipe API User : User display name}
        {--token-name= : Sanctum token name}
        {--ability=* : Token ability. Defaults to episodes:read}';

    /** @var string */
    protected $description = 'Create or reuse an API user and issue a Sanctum token.';

    /**
     * API user と token を作成する。
     */
    public function handle(ApiTokenService $tokens): int
    {
        $email = $this->email();
        $name = $this->stringOption('name', 'Radiopipe API User');
        $tokenName = $this->stringOption('token-name', $tokens->defaultTokenName());
        $abilities = $this->abilities($tokens);

        $validator = Validator::make([
            'email' => $email,
            'name' => $name,
            'token_name' => $tokenName,
            'abilities' => $abilities,
        ], [
            'email' => ['required', 'email'],
            'name' => ['required', 'string', 'max:255'],
            'token_name' => ['required', 'string', 'max:255'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'email_verified_at' => now(),
                'password' => Str::random(64),
            ],
        );

        $createdToken = $tokens->createToken($user, $tokenName, $abilities);

        $this->info('API token created.');
        $this->line('User email: ' . $user->email);
        $this->line('Token name: ' . $createdToken->accessToken->name);
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
