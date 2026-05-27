<?php

namespace App\Http\Controllers\Auth;

use App\Admin\AdminAccess;
use App\Http\Controllers\Controller;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * ローカル開発用の管理画面ログインを処理する Controller。
 */
class LocalAdminLoginController extends Controller
{
    /**
     * 設定済みの開発用管理者としてログインする。
     */
    public function __invoke(AdminAccess $adminAccess): RedirectResponse
    {
        $environment = config('app.env');

        abort_unless(is_string($environment) && in_array($environment, ['local', 'testing'], true), 404);
        abort_unless((bool) config('radiopipe.admin.dev_login.enabled', false), 404);

        $email = config('radiopipe.admin.dev_login.email');

        abort_unless(is_string($email) && trim($email) !== '', 404);
        abort_unless($adminAccess->isAllowedEmail($email), 403);

        $email = trim($email);
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = 'Local Admin';
        $user->email_verified_at = now();

        if (! $user->exists) {
            $user->password = Str::password(32);
        }

        $user->save();

        abort_unless($user->canAccessPanel(Filament::getPanel('admin')), 403);

        Auth::login($user);
        request()->session()->regenerate();

        return redirect()->to(Filament::getUrl() ?? url('/admin'));
    }
}
