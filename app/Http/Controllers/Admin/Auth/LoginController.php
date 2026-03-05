<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class LoginController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('admin.auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request)
    {
        try {
            $request->authenticate();
            $request->session()->regenerate();

            return response()->json([
                'status' => true,
                'message' => __('Login successful'),
                'user' => Auth::guard('admin')->user(),
                'redirect_url' => route('admin.dashboard'),
                'csrf_token' => csrf_token(),
            ], Response::HTTP_OK);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => __('Login failed'),
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => __('Something went wrong. Please try again later.'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request)
    {
        $guards = ['admin', 'web'];
        $results = [];
        $errors = [];

        $hadAuthenticatedGuard = false;
        foreach ($guards as $guard) {
            try {
                if (Auth::guard($guard)->check()) {
                    $hadAuthenticatedGuard = true;
                }
            } catch (\Throwable $e) {
            }
        }

        foreach ($guards as $guard) {
            try {
                if (Auth::guard($guard)->check()) {
                    Auth::guard($guard)->logout();
                    $results[$guard] = 'logged_out';
                } else {
                    $results[$guard] = 'not_logged_in';
                }
            } catch (\Throwable $e) {
                $results[$guard] = 'error';
                $errors[$guard] = $e->getMessage();
                Log::error('Admin web logout guard error', [
                    'guard' => $guard,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $sessionCleared = false;
        try {
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                $sessionCleared = true;
            }
        } catch (\Throwable $e) {
            $errors['session'] = $e->getMessage();
            Log::error('Admin web logout session error', [
                'message' => $e->getMessage(),
            ]);
        }

        $stillAuthenticated = [];
        foreach ($guards as $guard) {
            try {
                if (Auth::guard($guard)->check()) {
                    $stillAuthenticated[] = $guard;
                }
            } catch (\Throwable $e) {
                $stillAuthenticated[] = $guard;
            }
        }

        if ($errors || $stillAuthenticated) {
            Log::warning('Admin web logout partial failure', [
                'results' => $results,
                'errors' => $errors,
                'still_authenticated' => $stillAuthenticated,
                'session_cleared' => $sessionCleared,
            ]);

            return response()->json([
                'success' => false,
                'message' => __('Logout encountered issues.'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        Log::info('Admin web logout successful', [
            'results' => $results,
            'session_cleared' => $sessionCleared,
        ]);

        if (! $hadAuthenticatedGuard) {
            return response()->json([
                'success' => false,
                'message' => __('You are already logged out.'),
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'success' => true,
            'message' => __('You have been logged out successfully.'),
            'redirect_url' => route('admin.login'),
        ], Response::HTTP_OK);
    }
}
