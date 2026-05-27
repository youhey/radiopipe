<?php

namespace App\Http\Controllers\Auth;

use App\Admin\AdminAccess;
use App\Http\Controllers\Controller;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

/**
 * Google OAuth による管理画面ログインを処理する Controller。
 */
class GoogleOAuthController extends Controller
{
    /**
     * Google OAuth の認可画面へリダイレクトする。
     */
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Google OAuth callback を処理して管理者セッションを開始する。
     */
    public function callback(AdminAccess $adminAccess): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->user();
        $email = $googleUser->getEmail();

        abort_unless($adminAccess->isAllowedEmail($email), 403);

        $email = trim((string) $email);
        $name = $googleUser->getName();
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = is_string($name) && trim($name) !== '' ? $name : $email;
        $user->google_id = $googleUser->getId();
        $user->avatar_url = $googleUser->getAvatar();
        $user->email_verified_at = now();

        if (! $user->exists) {
            $user->password = Str::password(32);
        }

        $user->save();

        Auth::login($user);
        request()->session()->regenerate();

        return redirect()->to(Filament::getUrl() ?? url('/admin'));
    }
}
