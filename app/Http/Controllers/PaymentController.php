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
            // Build complete params array for Midtrans
            $params = array_merge($transactionDetails, [
                'customer_details' => $customerDetails,
                'item_details' => $itemDetails,
            ]);

            $response = $this->midtransService->createTransaction($params);

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

        // Verify the notification signature
        $signature = hash('sha512', $notification['order_id'].$notification['status_code'].$notification['gross_amount'].config('midtrans.server_key'));

        if ($signature !== $notification['signature_key']) {
            Log::error('Invalid signature for callback', [
                'expected' => $signature,
                'received' => $notification['signature_key'] ?? 'null',
                'order_id' => $notification['order_id'] ?? 'null',
            ]);

            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 403);
        }

        Log::info('Midtrans Callback:', $notification);

        $orderId = $notification['order_id'];
        $status = $notification['transaction_status'];

        // Find the transaction by code
        $transaction = \App\Models\Transaction::where('code', $orderId)->first();

        if (! $transaction) {
            Log::error('Transaction not found for order_id: '.$orderId);

            return response()->json(['status' => 'error', 'message' => 'Transaction not found'], 404);
        }

        // Update transaction status based on Midtrans status
        switch ($status) {
            case 'settlement':
                $transaction->update(['status' => 'paid']);
                Log::info('Transaction '.$orderId.' marked as paid');
                break;
            case 'pending':
                // Keep as confirmed or pending
                Log::info('Transaction '.$orderId.' is pending');
                break;
            case 'cancel':
            case 'deny':
            case 'expire':
            case 'failure':
                $transaction->update(['status' => 'cancelled']);
                Log::info('Transaction '.$orderId.' cancelled/failed');
                break;
            default:
                Log::warning('Unknown transaction status: '.$status.' for order_id: '.$orderId);
                break;
        }

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
