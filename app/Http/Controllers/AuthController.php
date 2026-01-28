<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        Auth::login($user);

        AuditLog::record('user.registered', $user->id, 'user', [], $request->ip());

        return redirect()->route('dashboard');
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            AuditLog::record('user.login', Auth::id(), 'user', [], $request->ip());

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'email' => 'Las credenciales no coinciden.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        $userId = Auth::id();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        AuditLog::record('user.logout', $userId, 'user', [], $request->ip());

        return redirect()->route('home');
    }

    /**
     * Magic link login: generate token and send email.
     */
    public function sendMagicLink(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        $user = User::where('email', $validated['email'])->first();
        $token = Str::random(64);

        $user->update([
            'magic_token' => $token,
            'magic_token_expires_at' => now()->addMinutes(15),
        ]);

        // In production, send email with the magic link
        // Mail::to($user)->send(new MagicLinkMail($token));

        return back()->with('status', 'Te hemos enviado un enlace de acceso a tu email.');
    }

    public function loginWithMagicToken(string $token)
    {
        $user = User::where('magic_token', $token)
            ->where('magic_token_expires_at', '>', now())
            ->first();

        if (!$user) {
            return redirect()->route('login')->withErrors([
                'email' => 'Enlace invalido o expirado.',
            ]);
        }

        $user->update([
            'magic_token' => null,
            'magic_token_expires_at' => null,
        ]);

        Auth::login($user);
        session()->regenerate();

        return redirect()->route('dashboard');
    }
}
