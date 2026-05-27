<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

/**
 * @internal
 */
class GoogleOAuthAdminTest extends TestCase
{
    use RefreshDatabase;

    public function testAllowedGoogleEmailCanLogIn(): void
    {
        config(['radiopipe.admin.allowed_emails' => ['admin@example.test']]);
        $this->fakeGoogleUser(email: 'Admin@Example.Test');

        $response = $this->get(route('auth.google.callback'));

        self::assertSame(url('/admin'), $response->headers->get('Location'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'Admin@Example.Test',
            'google_id' => 'google-admin',
            'avatar_url' => 'https://example.test/avatar.png',
        ]);
    }

    public function testDisallowedGoogleEmailCannotLogIn(): void
    {
        config(['radiopipe.admin.allowed_emails' => ['admin@example.test']]);
        $this->fakeGoogleUser(email: 'other@example.test');

        $this->get(route('auth.google.callback'))->assertForbidden();

        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'other@example.test',
        ]);
    }

    public function testEmailMatchingIsCaseInsensitiveAndTrimsConfiguredWhitespace(): void
    {
        config(['radiopipe.admin.allowed_emails' => ['  ADMIN@example.test  ']]);
        $this->fakeGoogleUser(email: 'admin@EXAMPLE.test');

        $this->get(route('auth.google.callback'))->assertRedirect(url('/admin'));

        $this->assertAuthenticated();
    }

    public function testEmptyAllowListDeniesGoogleLogin(): void
    {
        config(['radiopipe.admin.allowed_emails' => []]);
        $this->fakeGoogleUser(email: 'admin@example.test');

        $this->get(route('auth.google.callback'))->assertForbidden();

        $this->assertGuest();
    }

    public function testAllowedLoggedInUserCanAccessFilamentPanel(): void
    {
        config(['radiopipe.admin.allowed_emails' => ['admin@example.test']]);
        $user = User::factory()->create(['email' => 'admin@example.test']);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk();
    }

    public function testUserRemovedFromAllowListCannotAccessFilamentPanel(): void
    {
        config(['radiopipe.admin.allowed_emails' => ['admin@example.test']]);
        $user = User::factory()->create(['email' => 'admin@example.test']);

        self::assertTrue($user->canAccessPanel(Filament::getPanel('admin')));

        config(['radiopipe.admin.allowed_emails' => []]);

        self::assertFalse($user->canAccessPanel(Filament::getPanel('admin')));

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function testGoogleOauthRedirectStartsSocialiteFlow(): void
    {
        Socialite::fake('google');

        $this->get(route('auth.google.redirect'))
            ->assertRedirect('https://socialite.fake/google/authorize');
    }

    public function testGoogleOauthTokensAreNotStored(): void
    {
        config(['radiopipe.admin.allowed_emails' => ['admin@example.test']]);
        $this->fakeGoogleUser(email: 'admin@example.test');

        $this->get(route('auth.google.callback'))->assertRedirect(url('/admin'));

        self::assertFalse(Schema::hasColumn('users', 'google_token'));
        self::assertFalse(Schema::hasColumn('users', 'google_refresh_token'));
        self::assertFalse(Schema::hasColumn('users', 'oauth_token'));
        self::assertFalse(Schema::hasColumn('users', 'oauth_refresh_token'));
    }

    private function fakeGoogleUser(string $email): void
    {
        $socialiteUser = (new SocialiteUser())
            ->setRaw([
                'sub' => 'google-admin',
                'email' => $email,
                'name' => 'Admin User',
                'picture' => 'https://example.test/avatar.png',
                'access_token' => 'fake-access-token',
                'refresh_token' => 'fake-refresh-token',
            ])
            ->map([
                'id' => 'google-admin',
                'nickname' => null,
                'name' => 'Admin User',
                'email' => $email,
                'avatar' => 'https://example.test/avatar.png',
            ]);

        Socialite::fake('google', $socialiteUser);
    }
}
