<?php

namespace App\Http\Controllers\Api\V1\Factory;

use App\Http\Controllers\Controller;
use App\Models\Factory\HangTag;
use App\Models\Factory\PackagingLabel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class LabelSettingController extends Controller
{
    /**
     * Get packaging label settings for the factory.
     */
    public function showPackagingLabel(Request $request)
    {
        try {
            if (Auth::guard('admin_api')->check()) {
                $factoryRouteParam = $request->route('factory');
                $factoryModel = \App\Models\Factory\Factory::find($factoryRouteParam);
                if (! $factoryModel) {
                    return response()->json([
                        'success' => false,
                        'data' => null,
                        'message' => __('Factory not found.'),
                    ], Response::HTTP_BAD_REQUEST);
                }
                $factoryId = $factoryModel->id;
            } else {
                $factory = Auth::guard('factory')->user();
                if (! $factory) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized.',
                    ], Response::HTTP_UNAUTHORIZED);
                }
                $factoryId = $factory->id;
            }

            $label = PackagingLabel::where('factory_id', $factoryId)->first();

            if (! $label) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'factory_id' => $factoryId,
                        'front_price' => 0,
                        'back_price' => 0,
                        'is_active' => false,
                    ],
                    'message' => 'No packaging label settings found.',
                ], Response::HTTP_OK);
            }

            return response()->json([
                'success' => true,
                'data' => $label,
                'message' => 'Packaging label settings retrieved successfully.',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve packaging label settings', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve label settings.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update packaging label settings for the factory.
     */
    public function updatePackagingLabel(Request $request)
    {
        try {
            if (Auth::guard('admin_api')->check()) {
                $factoryRouteParam = $request->route('factory');
                $factoryModel = \App\Models\Factory\Factory::find($factoryRouteParam);
                if (! $factoryModel) {
                    return response()->json([
                        'success' => false,
                        'data' => null,
                        'message' => __('Factory not found.'),
                    ], Response::HTTP_BAD_REQUEST);
                }
                $factoryId = $factoryModel->id;
            } else {
                $factory = Auth::guard('factory')->user();
                if (! $factory) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized.',
                    ], Response::HTTP_UNAUTHORIZED);
                }
                $factoryId = $factory->id;
            }

            $validated = $request->validate([
                'front_price' => ['required', 'numeric', 'min:0'],
                'back_price' => ['required', 'numeric', 'min:0'],
                'is_active' => ['nullable', 'boolean'],
            ]);

            $label = PackagingLabel::firstOrCreate(
                ['factory_id' => $factoryId],
                [
                    'front_price' => 0,
                    'back_price' => 0,
                    'is_active' => false,
                ]
            );

            $label->update($validated);

            return response()->json([
                'success' => true,
                'data' => $label,
                'message' => 'Packaging label settings updated successfully.',
            ], Response::HTTP_OK);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Failed to update packaging label settings', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to update label settings.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get hang tag settings for the factory.
     */
    public function showHangTag(Request $request)
    {
        try {
            if (Auth::guard('admin_api')->check()) {
                $factoryRouteParam = $request->route('factory');
                $factoryModel = \App\Models\Factory\Factory::find($factoryRouteParam);
                if (! $factoryModel) {
                    return response()->json([
                        'success' => false,
                        'data' => null,
                        'message' => __('Factory not found.'),
                    ], Response::HTTP_BAD_REQUEST);
                }
                $factoryId = $factoryModel->id;
            } else {
                $factory = Auth::guard('factory')->user();
                if (! $factory) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized.',
                    ], Response::HTTP_UNAUTHORIZED);
                }
                $factoryId = $factory->id;
            }

            $tag = HangTag::where('factory_id', $factoryId)->first();

            if (! $tag) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'factory_id' => $factoryId,
                        'front_price' => 0,
                        'back_price' => 0,
                        'is_active' => false,
                    ],
                    'message' => 'No hang tag settings found.',
                ], Response::HTTP_OK);
            }

            return response()->json([
                'success' => true,
                'data' => $tag,
                'message' => 'Hang tag settings retrieved successfully.',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve hang tag settings', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve hang tag settings.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update hang tag settings for the factory.
     */
    public function updateHangTag(Request $request)
    {
        try {
            if (Auth::guard('admin_api')->check()) {
                $factoryRouteParam = $request->route('factory');
                $factoryModel = \App\Models\Factory\Factory::find($factoryRouteParam);
                if (! $factoryModel) {
                    return response()->json([
                        'success' => false,
                        'data' => null,
                        'message' => __('Factory not found.'),
                    ], Response::HTTP_BAD_REQUEST);
                }
                $factoryId = $factoryModel->id;
            } else {
                $factory = Auth::guard('factory')->user();
                if (! $factory) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized.',
                    ], Response::HTTP_UNAUTHORIZED);
                }
                $factoryId = $factory->id;
            }

            $validated = $request->validate([
                'front_price' => ['required', 'numeric', 'min:0'],
                'back_price' => ['required', 'numeric', 'min:0'],
                'is_active' => ['nullable', 'boolean'],
            ]);

            $tag = HangTag::firstOrCreate(
                ['factory_id' => $factoryId],
                [
                    'front_price' => 0,
                    'back_price' => 0,
                    'is_active' => false,
                ]
            );

            $tag->update($validated);

            return response()->json([
                'success' => true,
                'data' => $tag,
                'message' => 'Hang tag settings updated successfully.',
            ], Response::HTTP_OK);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Failed to update hang tag settings', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to update hang tag settings.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
