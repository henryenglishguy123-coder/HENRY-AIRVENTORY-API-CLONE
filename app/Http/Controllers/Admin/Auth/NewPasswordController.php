<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): View
    {
        $email = $request->email;
        $token = $request->token;

        $passwordReset = DB::table('password_reset_tokens')->where('email', $email)->first();
        if (! $passwordReset || ! Hash::check($token, $passwordReset->token)) {
            abort(404, __('The link you are trying to open has expired or is invalid.'));
        }
        $tokenCreatedAt = Carbon::parse($passwordReset->created_at);
        $expiresAt = config('auth.passwords.users.expire');
        if ($tokenCreatedAt->addMinutes($expiresAt)->isPast()) {
            abort(404, __('The link you are trying to open has expired.'));
        }

        return view('admin.auth.reset-password', ['request' => $request]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {

        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status == Password::PASSWORD_RESET) {
            return response()->json([
                'status' => 'success',
                'message' => __('Password has been reset successfully.'),
                'redirect_url' => route('admin.login'),
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => __($status),
            'errors' => ['email' => [__($status)]],
        ], 422);
    }
}
