<?php

namespace App\Http\Controllers\Api\V1\Shipping;

use App\Http\Controllers\Controller;
use App\Models\Shipping\ShippingPartner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingPartnerController extends Controller
{
    public function index(): JsonResponse
    {
        $partners = ShippingPartner::query()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'logo',
                'code',
                'type',
                'api_base_url',
                'app_id',
                'is_enabled',
                'last_sync_status',
                'last_sync_at',
                'settings',
                'api_key',
                'api_secret',
                'webhook_secret',
            ]);

        return response()->json([
            'status' => true,
            'data' => $partners,
        ]);
    }

    public function update(Request $request, ShippingPartner $partner): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'logo' => ['nullable', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:191'],
            'type' => ['required', 'string', 'max:32'],
            'api_base_url' => ['nullable', 'string', 'max:255'],
            'app_id' => ['nullable', 'string', 'max:191'],
            'api_key' => ['nullable', 'string'],
            'api_secret' => ['nullable', 'string'],
            'webhook_secret' => ['nullable', 'string'],
            'is_enabled' => ['required', 'boolean'],
            'settings' => ['nullable', 'array'],
        ]);

        $partner->fill($data);
        $partner->save();

        return response()->json([
            'status' => true,
        ]);
    }
}
