<?php

namespace App\Http\Controllers\Api\V1\Admin\Factory;

use App\Enums\AccountStatus;
use App\Enums\AccountVerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Factory\UpdateFactoryStatusRequest;
use App\Http\Resources\Api\V1\Factory\FactoryResource;
use App\Models\Factory\Factory;
use App\Services\Factory\FactoryStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class FactoryAccountStatusController extends Controller
{
    private FactoryStatusService $statusService;

    public function __construct(FactoryStatusService $statusService)
    {
        $this->statusService = $statusService;
    }

    /**
     * Get available account status and verification status options
     */
    public function getStatuses(): JsonResponse
    {
        try {
            $accountStatuses = AccountStatus::getAllMetadata();
            $verificationStatuses = AccountVerificationStatus::getAllMetadata();

            return response()->json([
                'success' => true,
                'data' => [
                    'account_statuses' => $accountStatuses,
                    'verification_statuses' => $verificationStatuses,
                ],
                'message' => 'Status options retrieved successfully.',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve factory status options', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve status options.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Unified API to update factory status (account status, verification status, or both)
     *
     * This endpoint replaces the previous three separate endpoints:
     * - PUT /factories-status/{id}/account-status
     * - PUT /factories-status/{id}/verification-status
     * - PUT /factories-status/{id}/both-statuses
     */
    public function updateStatus(UpdateFactoryStatusRequest $request, Factory $factory): JsonResponse
    {
        try {
            // Get validated data
            $validated = $request->validated();

            // Ensure at least one status-related field is present
            if (
                ! array_key_exists('account_status', $validated) &&
                ! array_key_exists('account_verified', $validated) &&
                ! array_key_exists('verify_email', $validated)
            ) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Validation failed',
                    'errors' => [
                        'account_status' => ['At least one of account_status, account_verified, or verify_email must be provided.'],
                        'account_verified' => ['At least one of account_status, account_verified, or verify_email must be provided.'],
                        'verify_email' => ['At least one of account_status, account_verified, or verify_email must be provided.'],
                    ],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            // Update status using service
            $result = $this->statusService->updateStatus($factory, $validated);

            if (! $result['success']) {
                $statusCode = isset($result['completeness']) ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_INTERNAL_SERVER_ERROR;

                return response()->json([
                    'success' => false,
                    'data' => isset($result['completeness']) ? $result['completeness'] : null,
                    'message' => $result['message'],
                ], $statusCode);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'factory' => new FactoryResource($result['factory']),
                    'changes' => $result['changes'],
                    'reason' => $result['reason'] ?? null,
                ],
                'message' => $result['message'],
            ], Response::HTTP_OK);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Failed to update factory status', [
                'factory_id' => $factory->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to update status.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get factory completeness details
     */
    public function getFactoryCompleteness(Factory $factory): JsonResponse
    {
        try {
            $factory->loadMissing(['business', 'industries', 'addresses']);
            $completeness = $this->statusService->checkFactoryCompleteness($factory);

            return response()->json([
                'success' => true,
                'data' => [
                    'factory_id' => $factory->id,
                    'completeness' => $completeness,
                ],
                'message' => 'Factory completeness details retrieved successfully.',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve factory completeness', [
                'factory_id' => $factory->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve factory completeness.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
