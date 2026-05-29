<?php

namespace Tests\Feature\Admin;

use App\ApiTokens\ApiTokenService;
use App\Filament\Resources\ApiTokens\ApiTokenResource;
use App\Filament\Resources\ApiTokens\Pages\ListApiTokens;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * @internal
 */
class ApiTokenResourceTest extends TestCase
{
    use RefreshDatabase;

    public function testAuthorizedAdminCanAccessApiTokensPage(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->create(['email' => 'api-user@example.test']);
        $token = $user->createToken('radiopipe-api', [ApiTokenService::ABILITY_EPISODES_READ])->accessToken;

        $this->get(ApiTokenResource::getUrl('index'))->assertOk();

        $component = Livewire::test(ListApiTokens::class);
        // @phpstan-ignore-next-line Filament の Livewire test macro を使用する。
        $component->assertCanSeeTableRecords([$token]);
        $component->assertSee('api-user@example.test');
        $component->assertSee('radiopipe-api');
    }

    public function testNonAdminUserCannotAccessApiTokensPage(): void
    {
        config(['radiopipe.admin.allowed_emails' => ['admin@example.test']]);

        $this->actingAs(User::factory()->create(['email' => 'user@example.test']));

        $this->get(ApiTokenResource::getUrl('index'))->assertForbidden();
    }

    public function testCreateApiTokenActionCreatesTokenAndShowsPlainTextTokenOnce(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->create(['email' => 'api-user@example.test']);

        $component = Livewire::test(ListApiTokens::class);
        // @phpstan-ignore-next-line Filament の Livewire test macro を使用する。
        $component->assertActionExists('createApiToken');
        // @phpstan-ignore-next-line Filament の Livewire test macro を使用する。
        $component->callAction('createApiToken', [
            'user_id' => $user->id,
            'token_name' => 'radiopipe-api',
            'abilities' => [ApiTokenService::ABILITY_EPISODES_READ],
        ]);

        $token = PersonalAccessToken::query()->firstOrFail();

        self::assertSame('radiopipe-api', $token->name);
        self::assertSame([ApiTokenService::ABILITY_EPISODES_READ], $token->abilities);
        $instance = $component->instance();

        self::assertInstanceOf(ListApiTokens::class, $instance);
        self::assertNotNull($instance->createdPlainTextToken);
        self::assertStringContainsString('|', $instance->createdPlainTextToken);
        $component->assertSee('Copy this token now. It will not be shown again.');
    }

    public function testTokenListDoesNotDisplayPlainTextTokenOrTokenHash(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->create(['email' => 'api-user@example.test']);
        $createdToken = $user->createToken('radiopipe-api', [ApiTokenService::ABILITY_EPISODES_READ]);

        $component = Livewire::test(ListApiTokens::class);

        $component->assertSee('radiopipe-api');
        $component->assertDontSee($createdToken->plainTextToken);
        $component->assertDontSee($createdToken->accessToken->token);
    }

    public function testRevokeTokenActionDeletesSelectedToken(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->create(['email' => 'api-user@example.test']);
        $token = $user->createToken('radiopipe-api', [ApiTokenService::ABILITY_EPISODES_READ])->accessToken;

        $component = Livewire::test(ListApiTokens::class);
        // @phpstan-ignore-next-line Filament の Livewire test macro を使用する。
        $component->assertTableActionExists('revoke', record: $token);
        // @phpstan-ignore-next-line Filament の Livewire test macro を使用する。
        $component->callTableAction('revoke', $token);

        self::assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->id,
        ]);
    }

    public function testRevokeAllApiTokensActionDeletesAllTokensForUser(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->create(['email' => 'api-user@example.test']);
        $otherUser = User::factory()->create(['email' => 'other-api-user@example.test']);
        $user->createToken('radiopipe-api', [ApiTokenService::ABILITY_EPISODES_READ]);
        $user->createToken('radiopipe-api-secondary', [ApiTokenService::ABILITY_EPISODES_READ]);
        $otherUser->createToken('radiopipe-api', [ApiTokenService::ABILITY_EPISODES_READ]);

        $component = Livewire::test(ListApiTokens::class);
        // @phpstan-ignore-next-line Filament の Livewire test macro を使用する。
        $component->assertActionExists('revokeAllApiTokens');
        // @phpstan-ignore-next-line Filament の Livewire test macro を使用する。
        $component->callAction('revokeAllApiTokens', [
            'user_id' => $user->id,
        ]);

        self::assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count());
        self::assertSame(1, DB::table('personal_access_tokens')->where('tokenable_id', $otherUser->id)->count());
    }

    /**
     * 管理者としてログインする。
     */
    private function actingAsAdmin(): void
    {
        config(['radiopipe.admin.allowed_emails' => ['admin@example.test']]);

        $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
    }
}
