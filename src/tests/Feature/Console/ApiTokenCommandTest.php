<?php

namespace Tests\Feature\Console;

use App\ApiTokens\ApiTokenService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * @internal
 */
class ApiTokenCommandTest extends TestCase
{
    use RefreshDatabase;

    public function testCreateApiUserCommandCreatesUserAndToken(): void
    {
        /** @var PendingCommand $command */
        $command = $this->artisan('radiopipe:users:create-api-user', [
            'email' => 'api-user@example.test',
            '--name' => 'API User',
            '--token-name' => 'radiopipe-api',
            '--ability' => [ApiTokenService::ABILITY_EPISODES_READ],
        ]);

        $command->expectsOutputToContain('API token created.')
            ->expectsOutputToContain('Plain text token:')
            ->assertSuccessful()
            ->execute();

        self::assertDatabaseHas('users', [
            'email' => 'api-user@example.test',
            'name' => 'API User',
        ]);
        self::assertDatabaseHas('personal_access_tokens', [
            'name' => 'radiopipe-api',
            'tokenable_type' => User::class,
        ]);

        $token = PersonalAccessToken::query()->firstOrFail();

        self::assertSame([ApiTokenService::ABILITY_EPISODES_READ], $token->abilities);
    }

    public function testRotateApiTokenCommandRevokesNamedTokenAndCreatesReplacement(): void
    {
        $user = User::factory()->create(['email' => 'api-user@example.test']);
        $oldToken = $user->createToken('radiopipe-api', [ApiTokenService::ABILITY_EPISODES_READ])->accessToken;
        $untouchedToken = $user->createToken('other-token', [ApiTokenService::ABILITY_EPISODES_READ])->accessToken;

        /** @var PendingCommand $command */
        $command = $this->artisan('radiopipe:users:rotate-api-token', [
            'email' => 'api-user@example.test',
            '--token-name' => 'radiopipe-api',
            '--ability' => [ApiTokenService::ABILITY_EPISODES_READ],
        ]);

        $command->expectsOutputToContain('API token rotated.')
            ->expectsOutputToContain('Plain text token:')
            ->assertSuccessful()
            ->execute();

        self::assertDatabaseMissing('personal_access_tokens', [
            'id' => $oldToken->id,
        ]);
        self::assertDatabaseHas('personal_access_tokens', [
            'id' => $untouchedToken->id,
        ]);
        self::assertSame(2, DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count());
    }

    public function testRotateApiTokenCommandFailsWhenUserIsMissing(): void
    {
        /** @var PendingCommand $command */
        $command = $this->artisan('radiopipe:users:rotate-api-token', [
            'email' => 'missing@example.test',
        ]);

        $command->expectsOutputToContain('API user was not found.')
            ->assertFailed()
            ->execute();
    }
}
