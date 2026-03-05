<?php

namespace App\Http\Controllers\Api\V1\Customer\Store;

use App\Enums\Store\StoreConnectionStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer\Store\VendorConnectedStore;
use App\Models\StoreChannels\StoreChannel;
use App\Services\Channels\Factory\StoreConnectorFactory;
use App\Services\Channels\Normalization\StoreUrlNormalizer;
use App\Services\Channels\Validation\StoreUrlValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class StoreConnectionController extends Controller
{
    public function __construct(
        private StoreConnectorFactory $connectorFactory
    ) {}

    public function connect(Request $request, string $storeChannel): JsonResponse
    {
        $customer = Auth::guard('customer')->user();
        $channel = StoreChannel::whereCode($storeChannel)->firstOrFail();
        // app(StoreUrlValidator::class)->validate($storeChannel, $request);
        $storeUrl = app(StoreUrlNormalizer::class)->normalize($storeChannel, $request->input('store_url'));

        // Check if the store is already connected.
        // We allow reconnection if the store status is 'error' or 'disconnected'.
        $alreadyConnected = VendorConnectedStore::where('channel', $storeChannel)
            ->where('link', $storeUrl)
            ->where('status', StoreConnectionStatus::CONNECTED)
            ->exists();

        if ($alreadyConnected) {
            return response()->json([
                'success' => false,
                'message' => __('This store is already connected.'),
            ], Response::HTTP_CONFLICT);
        }
        $nonce = Str::uuid()->toString();
        Cache::put(
            "store_oauth_pending:{$nonce}",
            [
                'vendor_id' => $customer->id,
                'store_url' => $storeUrl,
            ],
            now()->addMinutes(10)
        );
        $connector = $this->connectorFactory->make($channel);

        return response()->json([
            'success' => true,
            'data' => [
                'redirect_url' => $connector->buildAuthorizeUrl(
                    $customer->id,
                    $storeUrl,
                    $nonce
                ),
            ],
        ], Response::HTTP_OK);
    }
}
