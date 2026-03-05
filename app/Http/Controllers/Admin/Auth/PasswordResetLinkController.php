<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Show forgot-password page.
     */
    public function create(): View
    {
        return view('admin.auth.forgot-password');
    }

    /**
     * Handle reset link request (web + AJAX).
     */
    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink($request->only('email'));

        // Handle AJAX / JSON requests
        if ($request->ajax() || $request->wantsJson() || $request->expectsJson()) {
            if ($status === Password::RESET_LINK_SENT) {
                return response()->json([
                    'message' => __($status),
                ], 200);
            }

            return response()->json([
                'errors' => [
                    'email' => [__($status)],
                ],
            ], 422);
        }

        // Fallback for normal form submits (non-AJAX)
        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
    }
}
