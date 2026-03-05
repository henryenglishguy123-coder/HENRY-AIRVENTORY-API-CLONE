<?php

namespace App\Http\Controllers\Api\V1\Sales\Order;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Sales\Order\MessageResource;
use App\Models\Sales\Order\Message;
use App\Models\Sales\Order\SalesOrder;
use App\Services\Communication\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OrderMessageController extends Controller
{
    /**
     * Resolves the authenticated user across supported guards.
     */
    private function getAuthenticatedUser()
    {
        return Auth::guard('admin_api')->user()
            ?? Auth::guard('customer')->user()
            ?? Auth::guard('factory')->user();
    }

    /**
     * Display a listing of messages for a specific order.
     */
    public function index(Request $request, string $orderNumber): JsonResponse
    {
        try {
            $order = SalesOrder::where('order_number', $orderNumber)->firstOrFail();

            // Authorization check
            $user = $this->getAuthenticatedUser();

            if (! $user) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
            }

            // RBAC check
            if ($user instanceof \App\Models\Customer\Vendor && $order->customer_id !== $user->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
            if ($user instanceof \App\Models\Factory\Factory && $order->factory_id !== $user->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $page = $request->get('page', 1);
            $limit = min($request->get('limit', 20), 100);
            $afterId = $request->get('after_id');
            $beforeId = $request->get('before_id');

            $query = Message::where('sales_order_id', $order->id)
                ->with([
                    'sender' => function ($m) {
                        $m->morphWith([\App\Models\Factory\Factory::class => ['business']]);
                    },
                ]) // Load sender details safely
                ->orderBy('id', 'desc');

            if ($afterId) {
                $query->where('id', '>', $afterId);
            }

            if ($beforeId) {
                $query->where('id', '<', $beforeId);
            }

            if ($request->has('page')) {
                $messages = $query->paginate($limit, ['*'], 'page', $page);

                return response()->json(MessageResource::collection($messages)->response()->getData(true));
            } else {
                $messages = $query->limit($limit)->get();

                return response()->json([
                    'success' => true,
                    'data' => MessageResource::collection($messages),
                    'message' => 'Messages retrieved successfully',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error retrieving messages: '.$e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving messages.',
            ], 500);
        }
    }

    /**
     * Store a newly created message in storage.
     */
    public function store(Request $request, string $orderNumber): JsonResponse
    {
        try {
            // Get authenticated user details first for authorization
            $authUser = $this->getAuthenticatedUser();

            if (! $authUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            // Validate the request
            $validator = Validator::make($request->all(), [
                'message' => 'required|string|max:1000',
                'attachments' => 'nullable|array|max:5', // Max 5 attachments
                'attachments.*' => 'file|max:10240|mimes:jpeg,png,jpg,gif,pdf,doc,docx,xlsx,csv,txt', // Max 10MB per file
                'message_type' => 'nullable|in:text,sample_sent,feedback,revision_request,approval,general',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $order = SalesOrder::where('order_number', $orderNumber)->firstOrFail();

            // Authorization check
            if ($authUser instanceof \App\Models\Customer\Vendor && $order->customer_id !== $authUser->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
            if ($authUser instanceof \App\Models\Factory\Factory && $order->factory_id !== $authUser->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            // Determine sender role based on user type
            $senderRole = $this->determineSenderRole($authUser);

            // Handle file uploads if any
            $uploadedAttachments = [];
            try {
                DB::beginTransaction();

                if ($request->hasFile('attachments')) {
                    $fileUploadService = new FileUploadService();
                    $files = Arr::wrap($request->file('attachments'));

                    foreach ($files as $file) {
                        if ($file instanceof UploadedFile) {
                            $result = $fileUploadService->uploadFile($file, 'order-messages/'.$order->id);
                            if ($result) {
                                $uploadedAttachments[] = $result;
                            } else {
                                throw new \Exception('Failed to upload attachment: '.$file->getClientOriginalName());
                            }
                        }
                    }
                }

                // Create the message
                $message = Message::create([
                    'sales_order_id' => $order->id,
                    'sender_id' => $authUser->id,
                    'sender_role' => $senderRole,
                    'message' => $request->input('message'),
                    'attachments' => ! empty($uploadedAttachments) ? $uploadedAttachments : null,
                    'message_type' => $request->input('message_type', 'text'),
                ]);

                DB::commit();

                // Load the sender relationship for the response
                $message->load('sender');

                return response()->json([
                    'success' => true,
                    'data' => new MessageResource($message),
                    'message' => 'Message sent successfully',
                ], 201);
            } catch (\Exception $e) {
                DB::rollBack();

                // Cleanup uploaded files
                foreach ($uploadedAttachments as $attachment) {
                    if (isset($attachment['path'])) {
                        Storage::delete($attachment['path']);
                    }
                }

                throw $e;
            }
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error sending message: '.$e->getMessage(), [
                'exception' => $e,
                'user_id' => $authUser->id ?? null,
                'order_number' => $orderNumber,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while sending the message.',
            ], 500);
        }
    }

    /**
     * Determine sender role based on authenticated user type
     *
     * @param  mixed  $user
     */
    private function determineSenderRole($user): string
    {
        // This assumes you have some way to identify the user type
        // Adjust according to your authentication setup
        $userClass = get_class($user);

        if (str_contains($userClass, 'Customer')) {
            return 'customer';
        } elseif (str_contains($userClass, 'Factory')) {
            return 'factory';
        } elseif (str_contains($userClass, 'Admin')) {
            return 'admin';
        }

        // Default fallback - you might want to adjust this based on your auth guards
        return 'customer'; // Assuming customer as default for API endpoints
    }
}
