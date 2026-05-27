<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * @internal
 */
class LocalAdminDevLoginTest extends TestCase
{
    use RefreshDatabase;

    public function testRouteReturnsNotFoundInProductionEnvironment(): void
    {
        config([
            'app.env' => 'production',
            'radiopipe.admin.allowed_emails' => ['admin@example.test'],
            'radiopipe.admin.dev_login.enabled' => true,
            'radiopipe.admin.dev_login.email' => 'admin@example.test',
        ]);

        $this->get(route('local.admin.login'))->assertNotFound();

        $this->assertGuest();
    }

    public function testRouteReturnsNotFoundWhenDisabled(): void
    {
        config([
            'app.env' => 'local',
            'radiopipe.admin.allowed_emails' => ['admin@example.test'],
            'radiopipe.admin.dev_login.enabled' => false,
            'radiopipe.admin.dev_login.email' => 'admin@example.test',
        ]);

        $this->get(route('local.admin.login'))->assertNotFound();

        $this->assertGuest();
    }

    public function testRouteReturnsNotFoundWhenEmailIsMissing(): void
    {
        config([
            'app.env' => 'local',
            'radiopipe.admin.allowed_emails' => ['admin@example.test'],
            'radiopipe.admin.dev_login.enabled' => true,
            'radiopipe.admin.dev_login.email' => '',
        ]);

        $this->get(route('local.admin.login'))->assertNotFound();

        $this->assertGuest();
    }

    public function testRouteDeniesDevEmailOutsideAllowList(): void
    {
        config([
            'app.env' => 'local',
            'radiopipe.admin.allowed_emails' => ['admin@example.test'],
            'radiopipe.admin.dev_login.enabled' => true,
            'radiopipe.admin.dev_login.email' => 'other@example.test',
        ]);

        $this->get(route('local.admin.login'))->assertForbidden();

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'other@example.test']);
    }

    public function testRouteLogsInAllowedDevUserAndRedirectsToAdmin(): void
    {
        config([
            'app.env' => 'local',
            'radiopipe.admin.allowed_emails' => [' admin@example.test '],
            'radiopipe.admin.dev_login.enabled' => true,
            'radiopipe.admin.dev_login.email' => 'Admin@Example.Test',
        ]);

        $response = $this->get(route('local.admin.login'));

        self::assertSame(url('/admin'), $response->headers->get('Location'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'Admin@Example.Test',
            'name' => 'Local Admin',
        ]);
    }

    public function testLoggedInDevUserCanAccessFilamentPanel(): void
    {
        config([
            'app.env' => 'local',
            'radiopipe.admin.allowed_emails' => ['admin@example.test'],
            'radiopipe.admin.dev_login.enabled' => true,
            'radiopipe.admin.dev_login.email' => 'admin@example.test',
        ]);

        $this->get(route('local.admin.login'))->assertRedirect(url('/admin'));

        $this->get('/admin')->assertOk();
    }

    public function testRemovingEmailFromAllowListPreventsPanelAccess(): void
    {
        config([
            'app.env' => 'local',
            'radiopipe.admin.allowed_emails' => ['admin@example.test'],
            'radiopipe.admin.dev_login.enabled' => true,
            'radiopipe.admin.dev_login.email' => 'admin@example.test',
        ]);

        $this->get(route('local.admin.login'))->assertRedirect(url('/admin'));

        $user = User::query()->where('email', 'admin@example.test')->sole();
        self::assertTrue($user->canAccessPanel(Filament::getPanel('admin')));

        config(['radiopipe.admin.allowed_emails' => []]);

        self::assertFalse($user->canAccessPanel(Filament::getPanel('admin')));

        $this->get('/admin')->assertForbidden();
    }

    public function testHelperDoesNotStoreOauthTokensOrGoogleProfileData(): void
    {
        config([
            'app.env' => 'local',
            'radiopipe.admin.allowed_emails' => ['admin@example.test'],
            'radiopipe.admin.dev_login.enabled' => true,
            'radiopipe.admin.dev_login.email' => 'admin@example.test',
        ]);

        $this->get(route('local.admin.login'))->assertRedirect(url('/admin'));

        self::assertFalse(Schema::hasColumn('users', 'google_token'));
        self::assertFalse(Schema::hasColumn('users', 'google_refresh_token'));
        self::assertFalse(Schema::hasColumn('users', 'oauth_token'));
        self::assertFalse(Schema::hasColumn('users', 'oauth_refresh_token'));
        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.test',
            'google_id' => null,
            'avatar_url' => null,
        ]);
    }
}
