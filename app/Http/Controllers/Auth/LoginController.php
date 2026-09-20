<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('username', $credentials['username'])->first();

        if (! $user) {
            return $this->handleFailedLogin($request);
        }

        if (! $user->isActive()) {
            if ($user->isLocked()) {
                return back()->withErrors(['username' => 'Account is locked. Please contact IT.']);
            }

            return back()->withErrors(['username' => 'Account is suspended or disabled.']);
        }

        if (! Hash::check($credentials['password'], $user->password)) {
            return $this->handleFailedLogin($request, $user);
        }

        // Success logic
        $user->update(['login_attempts' => 0]);

        // Enforce Single Active Session Rule
        $count = DB::table('sessions')->where('user_id', $user->id)->count();
        if ($count > 0) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $this->logAction($user, 'other_sessions_terminated', "Invalidated {$count} previous session(s)");
        }

        Auth::login($user);

        $this->logAction($user, 'login', 'User logged in successfully');

        if ($user->requires_password_change) {
            return redirect()->route('password.change');
        }

        return $this->redirectBasedOnRole($user);
    }

    protected function handleFailedLogin(Request $request, ?User $user = null)
    {
        if ($user) {
            $user->increment('login_attempts');
            if ($user->login_attempts >= 5) {
                $user->update(['status' => 'locked']);
                $this->logAction($user, 'account_locked', 'Account locked after 5 failed attempts');
            }
        }

        return back()->withErrors([
            'username' => 'Invalid username or password',
        ]);
    }

    protected function redirectBasedOnRole(User $user)
    {
        if ($user->hasRole('admin')) {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->intended('dashboard');
    }

    protected function logAction(User $user, string $action, string $description)
    {
        AuditLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            $this->logAction($user, 'logout', 'User logged out');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
