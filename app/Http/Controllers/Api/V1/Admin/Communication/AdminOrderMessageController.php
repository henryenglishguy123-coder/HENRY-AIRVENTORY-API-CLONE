<?php

namespace App\Http\Controllers\Api\V1\Admin\Communication;

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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AdminOrderMessageController extends Controller
{
    /**
     * Display a listing of all messages for all orders (paginated).
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $admin = Auth::guard('admin_api')->user();
            if (! $admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            // Get query parameters for filtering
            $page = $request->get('page', 1);
            $limit = min($request->get('limit', 20), 100); // Max 100 per page
            $orderNumber = $request->get('order_number');
            $senderRole = $request->get('sender_role');
            $messageType = $request->get('message_type');

            $query = Message::with([
                'order:id,order_number',
                'sender' => function ($m) {
                    $m->morphWith([\App\Models\Factory\Factory::class => ['business']]);
                },
            ])->orderBy('created_at', 'desc');

            // Apply filters if provided
            if ($orderNumber) {
                $query->whereHas('order', function ($q) use ($orderNumber) {
                    $q->where('order_number', 'LIKE', "%{$orderNumber}%");
                });
            }

            if ($senderRole) {
                $query->where('sender_role', $senderRole);
            }

            if ($messageType) {
                $query->where('message_type', $messageType);
            }

            if ($request->has('page')) {
                $messages = $query->paginate($limit, ['*'], 'page', $page);
                $resourceCollection = MessageResource::collection($messages)->response()->getData(true);

                return response()->json(array_merge([
                    'success' => true,
                    'message' => 'Messages retrieved successfully',
                ], $resourceCollection));
            } else {
                $messages = $query->limit($limit)->get();

                return response()->json([
                    'success' => true,
                    'data' => MessageResource::collection($messages),
                    'message' => 'Messages retrieved successfully',
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Error retrieving messages: '.$e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving messages.',
            ], 500);
        }
    }

    /**
     * Display messages for a specific order.
     */
    public function showByOrder(string $orderNumber): JsonResponse
    {
        try {
            $admin = Auth::guard('admin_api')->user();
            if (! $admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            $order = SalesOrder::where('order_number', $orderNumber)->firstOrFail();

            $messages = Message::where('sales_order_id', $order->id)
                ->with([
                    'sender' => function ($m) {
                        $m->morphWith([\App\Models\Factory\Factory::class => ['business']]);
                    },
                ])
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => MessageResource::collection($messages),
                'message' => 'Order messages retrieved successfully',
            ]);
        } catch (\Exception $e) {
            \Log::error('Error retrieving order messages: '.$e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving order messages.',
            ], 500);
        }
    }

    /**
     * Get statistics about messages.
     */
    public function stats(): JsonResponse
    {
        try {
            $admin = Auth::guard('admin_api')->user();
            if (! $admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            $stats = \Illuminate\Support\Facades\Cache::remember('admin_message_stats', 300, function () {
                return [
                    'total_messages' => Message::count(),
                    'total_orders' => Message::distinct('sales_order_id')->count('sales_order_id'),
                    'messages_by_role' => Message::select('sender_role', DB::raw('COUNT(*) as count'))
                        ->groupBy('sender_role')
                        ->get(),
                    'messages_by_type' => Message::select('message_type', DB::raw('COUNT(*) as count'))
                        ->groupBy('message_type')
                        ->get(),
                    'recent_messages' => Message::with([
                        'order:id,order_number',
                        'sender' => function ($m) {
                            $m->morphWith([\App\Models\Factory\Factory::class => ['business']]);
                        },
                    ])->orderBy('created_at', 'desc')
                        ->limit(10)
                        ->get(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Message statistics retrieved successfully',
            ]);
        } catch (\Exception $e) {
            \Log::error('Error retrieving message statistics: '.$e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving message statistics.',
            ], 500);
        }
    }

    /**
     * Search messages by content.
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $admin = Auth::guard('admin_api')->user();
            if (! $admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'query' => 'required|string|min:1',
                'page' => 'nullable|integer|min:1',
                'limit' => 'nullable|integer|min:1|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $query = $request->get('query');
            $page = $request->get('page', 1);
            $limit = min($request->get('limit', 20), 100);

            $escapedQuery = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
            $likeStr = "%{$escapedQuery}%";

            $messages = Message::where('message', 'LIKE', $likeStr)
                ->with([
                    'order:id,order_number',
                    'sender' => function ($m) {
                        $m->morphWith([\App\Models\Factory\Factory::class => ['business']]);
                    },
                ])->orderBy('created_at', 'desc')
                ->paginate($limit, ['*'], 'page', $page);

            return response()->json(array_merge([
                'success' => true,
                'message' => 'Search results retrieved successfully',
            ], MessageResource::collection($messages)->response()->getData(true)));
        } catch (\Exception $e) {
            \Log::error('Error searching messages: '.$e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while searching messages.',
            ], 500);
        }
    }

    /**
     * Store a newly created message from admin.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_number' => 'required|string|exists:sales_orders,order_number',
            'message' => 'required|string|max:1000',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|max:10240|mimes:jpeg,png,jpg,gif,pdf,doc,docx,xlsx,csv,txt',
            'message_type' => 'nullable|in:text,sample_sent,feedback,revision_request,approval,general',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $uploadedAttachments = [];

        try {
            $admin = Auth::guard('admin_api')->user();
            if (! $admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            $order = SalesOrder::where('order_number', $request->order_number)->firstOrFail();

            DB::beginTransaction();

            if ($request->hasFile('attachments')) {
                $fileUploadService = new FileUploadService;
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

            $message = Message::create([
                'sales_order_id' => $order->id,
                'sender_id' => $admin->id,
                'sender_role' => 'admin',
                'message' => $request->input('message'),
                'attachments' => ! empty($uploadedAttachments) ? $uploadedAttachments : null,
                'message_type' => $request->input('message_type', 'text'),
            ]);

            DB::commit();

            $message->load(['sender', 'order:id,order_number']);

            return response()->json([
                'success' => true,
                'data' => new MessageResource($message),
                'message' => 'Message sent successfully',
            ], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            foreach ($uploadedAttachments as $attachment) {
                if (isset($attachment['path'])) {
                    Storage::delete($attachment['path']);
                }
            }

            \Log::error('Error sending message: '.$e->getMessage(), [
                'exception' => $e,
                'admin_id' => Auth::guard('admin_api')->id(),
                'order_number' => $request->order_number,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while sending the message.',
            ], 500);
        }
    }
}
