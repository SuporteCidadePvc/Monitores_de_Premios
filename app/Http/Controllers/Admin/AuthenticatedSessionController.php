<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\AdminUser;
use App\Domain\Admin\AdminUserRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class AuthenticatedSessionController
{
    public function create(): View|RedirectResponse
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    public function store(Request $request, AdminUserRepository $users): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]{3,64}$/'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $key = $this->throttleKey((string) $credentials['username'], (string) $request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['username' => 'Demasiadas tentativas. Aguarde um minuto antes de repetir.'])->onlyInput('username');
        }

        if (! Auth::guard('admin')->attempt($credentials)) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['username' => 'As credenciais indicadas não são válidas.'])->onlyInput('username');
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        $user = Auth::guard('admin')->user();

        if ($user instanceof AdminUser) {
            $users->recordLogin($user);
        }

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    private function throttleKey(string $username, string $ip): string
    {
        return 'admin-login:'.Str::lower($username).'|'.$ip;
    }
}
