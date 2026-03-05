<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use App\Models\Payment\PaymentSetting;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class PaymentSettingController extends Controller
{
    /**
     * Get all active payment methods (frontend use)
     */
    public function index()
    {
        $cacheKey = 'payment_settings:frontend';

        $paymentSettings = Cache::remember($cacheKey, 600, function () {
            return PaymentSetting::query()
                ->active()
                ->orderBy('title')
                ->get()
                ->map(fn ($setting) => $this->formatFrontendPaymentSetting($setting));
        });

        return response()->json([
            'success' => true,
            'message' => __('Payment methods fetched successfully'),
            'data' => $paymentSettings,
        ], Response::HTTP_OK);
    }

    /**
     * Get single payment method detail
     */
    public function show(string $payment_method)
    {
        $cacheKey = "payment_settings:{$payment_method}";

        $paymentSetting = Cache::remember($cacheKey, 600, function () use ($payment_method) {
            return PaymentSetting::query()
                ->active()
                ->where('payment_method', $payment_method)
                ->first();
        });

        if (! $paymentSetting) {
            return response()->json([
                'success' => false,
                'message' => __('Payment method not found'),
            ], Response::HTTP_NOT_FOUND);
        }
        $isAdmin = auth('admin_api')->check();

        return response()->json([
            'success' => true,
            'data' => $isAdmin
                ? $this->formatAdminPaymentSetting($paymentSetting)
                : $this->formatFrontendPaymentSetting($paymentSetting),
        ], Response::HTTP_OK);
    }

    /**
     * Format payment setting for API response
     */
    private function formatFrontendPaymentSetting(PaymentSetting $setting): array
    {
        return [
            'payment_method' => $setting->payment_method,
            'title' => $setting->title,
            'client_id' => $setting->app_id,
            'environment' => $setting->is_live ? 'live' : 'test',
            'logo' => getImageUrl($setting->logo),
            'description' => $setting->description,
        ];
    }

    private function formatAdminPaymentSetting(PaymentSetting $setting): array
    {
        return [
            'id' => $setting->id,
            'payment_method' => $setting->payment_method,
            'title' => $setting->title,
            'app_id' => $setting->app_id,
            'app_secret' => $setting->app_secret,
            'is_live' => $setting->is_live,
            'is_active' => $setting->is_active,
            'logo' => getImageUrl($setting->logo),
            'description' => $setting->description,
            'created_at' => $setting->created_at,
            'updated_at' => $setting->updated_at,
        ];
    }
}
