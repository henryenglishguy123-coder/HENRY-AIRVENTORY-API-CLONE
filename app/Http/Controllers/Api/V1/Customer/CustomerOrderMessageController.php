<?php

namespace App\Http\Controllers\Api\V1\Customer;

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

class CustomerOrderMessageController extends Controller
{
    /**
     * Display a listing of messages for a customer's specific order.
     */
    public function index(Request $request, string $orderNumber): JsonResponse
    {
        try {
            $customer = Auth::guard('customer')->user();
            if (! $customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            $order = SalesOrder::where('order_number', $orderNumber)
                ->where('customer_id', $customer->id)
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
     * Store a newly created message in storage for customer.
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
            $customer = Auth::guard('customer')->user();
            if (! $customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            $order = SalesOrder::where('order_number', $orderNumber)
                ->where('customer_id', $customer->id)
                ->firstOrFail();

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

            DB::beginTransaction();

            $message = Message::create([
                'sales_order_id' => $order->id,
                'sender_id' => $customer->id,
                'sender_role' => 'customer',
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
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            foreach ($uploadedAttachments as $attachment) {
                if (isset($attachment['path'])) {
                    Storage::delete($attachment['path']);
                }
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
                'customer_id' => Auth::guard('customer')->id(),
                'order_number' => $orderNumber,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while sending the message.',
            ], 500);
        }
    }

    /**
     * Get all orders with message activity for the authenticated customer
     */
    public function getOrderHistory(Request $request): JsonResponse
    {
        try {
            $customer = Auth::guard('customer')->user();
            if (! $customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            $ordersWithMessages = SalesOrder::where('customer_id', $customer->id)
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('sales_order_messages')
                        ->whereColumn('sales_order_messages.sales_order_id', 'sales_orders.id');
                })
                ->withCount('messages')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $ordersWithMessages,
                'message' => 'Order history with messages retrieved successfully',
            ]);
        } catch (\Exception $e) {
            \Log::error('Error retrieving order history: '.$e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving order history.',
            ], 500);
        }
    }
}
