<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

trait ApiResponse
{
    /**
     * Send a success response.
     *
     * @param  mixed  $data
     */
    protected function successResponse($data = [], string $message = 'Success', int $code = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Send an error response.
     */
    protected function errorResponse(string $message = 'Error', int $code = Response::HTTP_BAD_REQUEST, array $errors = []): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (! empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }
}
