<?php

namespace App\Http\Requests\Admin\Auth;

use App\Models\Admin\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();
        $user = User::where(function ($query) {
            $query->where('email', $this->username)
                ->orWhere('username', $this->username);
        })->first();
        if (! $user) {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'username' => trans('auth.failed'),
            ]);
        }
        if ($user->is_blocked) {
            throw ValidationException::withMessages([
                'username' => __('Your account is blocked due to too many login attempts.'),
            ]);
        }
        if (! Hash::check($this->password, $user->password)) {
            $this->incrementLoginAttempts();
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'password' => trans('auth.failed'),
            ]);
        }
        Auth::guard('admin')->login($user, $this->boolean('remember'));

        $this->resetLoginAttempts();
        RateLimiter::clear($this->throttleKey());

        $user->last_login_at = now();
        $user->save();
    }

    protected function incrementLoginAttempts(): void
    {
        $user = User::where(function ($query) {
            $query->where('email', $this->username)
                ->orWhere('username', $this->username);
        })->first();

        if ($user) {
            $user->increment('last_login_attempts');

            if ($user->last_login_attempts >= 4) {
                $user->is_blocked = 1;
            }

            $user->save();
        }
    }

    protected function resetLoginAttempts(): void
    {
        $user = Auth::guard('admin')->user();

        if ($user) {
            $user->last_login_attempts = 0;
            $user->save();
        }
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'username' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->input('username')).'|'.$this->ip());
    }
}
