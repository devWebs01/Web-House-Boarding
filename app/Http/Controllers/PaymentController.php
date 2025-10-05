<?php

namespace App\Http\Controllers;

use App\Services\MidtransService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected $midtransService;

    public function __construct(MidtransService $midtransService)
    {
        $this->midtransService = $midtransService;
    }

    /**
     * Create a payment transaction
     */
    public function createPayment(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|string',
            'gross_amount' => 'required|numeric',
            'customer_name' => 'required|string',
            'customer_email' => 'required|email',
            'item_name' => 'required|string',
            'quantity' => 'required|integer',
            'price' => 'required|numeric',
        ]);

        $transactionDetails = [
            'order_id' => $request->order_id,
            'gross_amount' => $request->gross_amount,
        ];

        $customerDetails = [
            'first_name' => $request->customer_name,
            'email' => $request->customer_email,
        ];

        $itemDetails = [
            [
                'id' => 'item1',
                'price' => $request->price,
                'quantity' => $request->quantity,
                'name' => $request->item_name,
            ],
        ];

        try {
            $response = $this->midtransService->createTransaction($transactionDetails, $customerDetails, $itemDetails);

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle Midtrans callback/notification
     */
    public function handleCallback(Request $request): JsonResponse
    {
        $notification = $request->all();

        // Verify the notification (you should implement signature verification)
        // For now, just log it
        Log::info('Midtrans Callback:', $notification);

        $orderId = $notification['order_id'];
        $status = $notification['transaction_status'];

        // Update your order status based on $status
        // e.g., if ($status == 'settlement') { // mark as paid }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Get transaction status
     */
    public function getStatus(string $orderId): JsonResponse
    {
        try {
            $status = $this->midtransService->statusTransaction($orderId);

            return response()->json($status);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Cancel transaction
     */
    public function cancelTransaction(string $orderId): JsonResponse
    {
        try {
            $result = $this->midtransService->cancelTransaction($orderId);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
