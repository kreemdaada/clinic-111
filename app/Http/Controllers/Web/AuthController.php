<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Session-based web authentication (separate from Sanctum API tokens).
 *
 * Routes: GET/POST /login, POST /logout
 */
class AuthController extends Controller
{
    /**
     * Show login form or redirect authenticated users to import page.
     *
     * @return View|RedirectResponse Login blade or redirect to `imports.index`.
     */
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('imports.index');
        }

        return view('auth.login');
    }

    /**
     * Attempt session login with email and password.
     *
     * Regenerates session ID on success to prevent fixation attacks.
     *
     * @param  LoginRequest  $request  Validated credentials.
     * @return RedirectResponse Redirect to intended URL or import page; back() on failure.
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ])) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Invalid email or password.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('imports.index'));
    }

    /**
     * Log out the current user and invalidate the session.
     *
     * @param  Request  $request  Current HTTP request (for session invalidation).
     * @return RedirectResponse Redirect to login page.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
