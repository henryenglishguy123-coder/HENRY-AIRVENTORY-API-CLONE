<?php

namespace App\Http\Controllers\Admin\Communication;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AdminCommunicationController extends Controller
{
    /**
     * Display the communications dashboard.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('admin.communications.index');
    }

    /**
     * Get communications data via API.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getData(Request $request)
    {
        $token = session('admin_jwt_token');

        $apiUrl = config('app.api_url', url('/api/v1')).'/admin/communications';

        $page = (int) $request->get('page', 1);
        $limit = (int) $request->get('limit', 20);

        $params = [
            'page' => max(1, $page),
            'limit' => max(1, min(100, $limit)),
        ];

        if ($request->filled('query')) {
            $params['query'] = $request->get('query');
        }

        if ($request->filled('sender_role')) {
            $params['sender_role'] = $request->get('sender_role');
        }

        if ($request->filled('message_type')) {
            $params['message_type'] = $request->get('message_type');
        }

        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->get($apiUrl, $params);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch communications',
            ], 500);
        } catch (\Exception $e) {
            \Log::error('Error fetching communications: '.$e->getMessage(), [
                'exception' => $e,
                'url' => $apiUrl,
                'params' => $params,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching communications.',
            ], 500);
        }
    }

    /**
     * Get communications statistics.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStats()
    {
        $token = session('admin_jwt_token');

        $apiUrl = config('app.api_url', url('/api/v1')).'/admin/communications/stats';

        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->get($apiUrl);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch stats',
            ], 500);
        } catch (\Exception $e) {
            \Log::error('Error fetching stats: '.$e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching statistics.',
            ], 500);
        }
    }

    /**
     * Get messages for a specific order.
     *
     * @param  string  $orderNumber
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByOrder($orderNumber)
    {
        // Sanitize and validate order number
        if (! preg_match('/^[a-zA-Z0-9\-_]+$/', $orderNumber)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid order number format',
            ], 400);
        }

        $token = session('admin_jwt_token');

        $apiUrl = config('app.api_url', url('/api/v1')).'/admin/communications/order/'.rawurlencode($orderNumber);

        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->get($apiUrl);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch order messages',
            ], 500);
        } catch (\Exception $e) {
            \Log::error('Error fetching order messages: '.$e->getMessage(), [
                'exception' => $e,
                'orderNumber' => $orderNumber,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching order messages.',
            ], 500);
        }
    }
}
