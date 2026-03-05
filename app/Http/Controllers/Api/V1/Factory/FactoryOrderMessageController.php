<?php

namespace App\Http\Controllers\Api\V1\Factory;

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

class FactoryOrderMessageController extends Controller
{
    /**
     * Display a listing of messages for a factory's specific order.
     */
    public function index(Request $request, string $orderNumber): JsonResponse
    {
        try {
            $factory = Auth::guard('factory')->user();
            if (! $factory) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            $order = SalesOrder::where('order_number', $orderNumber)
                ->where('factory_id', $factory->id)  // Assuming there's a factory_id in sales_orders
                ->firstOrFail();

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
                'message' => 'Messages retrieved successfully',
            ]);
        } catch (\Exception $e) {
            \Log::error('Error retrieving messages: '.$e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving messages.',
            ], 500);
        }
    }

    /**
     * Store a newly created message in storage for factory.
     */
    public function store(Request $request, string $orderNumber): JsonResponse
    {
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

        $uploadedAttachments = [];

        try {
            $factory = Auth::guard('factory')->user();
            if (! $factory) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            $order = SalesOrder::where('order_number', $orderNumber)
                ->where('factory_id', $factory->id)
                ->firstOrFail();

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
                'sender_id' => $factory->id,
                'sender_role' => 'factory',
                'message' => $request->input('message'),
                'attachments' => ! empty($uploadedAttachments) ? $uploadedAttachments : null,
                'message_type' => $request->input('message_type', 'text'),
            ]);

            DB::commit();

            $message->load('sender');

            return response()->json([
                'success' => true,
                'data' => new MessageResource($message),
                'message' => 'Message sent successfully',
            ], 201);
        } catch (ValidationException $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
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
                'factory_id' => Auth::guard('factory')->id(),
                'order_number' => $orderNumber,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while sending the message.',
            ], 500);
        }
    }

    /**
     * Get all orders with message activity for the authenticated factory
     */
    public function getOrderHistory(Request $request): JsonResponse
    {
        try {
            $factory = Auth::guard('factory')->user();
            if (! $factory) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            $limit = $request->get('limit', 20);
            $ordersWithMessages = SalesOrder::where('factory_id', $factory->id)
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('sales_order_messages')
                        ->whereColumn('sales_order_messages.sales_order_id', 'sales_orders.id');
                })
                ->withCount('messages')
                ->orderBy('created_at', 'desc')
                ->paginate($limit);

            return response()->json(array_merge([
                'success' => true,
                'message' => 'Order history with messages retrieved successfully',
            ], $ordersWithMessages->toArray()));
        } catch (\Exception $e) {
            \Log::error('Error retrieving order history: '.$e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving order history.',
            ], 500);
        }
    }
}
