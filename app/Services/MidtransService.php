<?php

namespace App\Services;

use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Transaction;

class MidtransService
{
    public function __construct()
    {
        Config::$serverKey = config('midtrans.server_key');
        Config::$clientKey = config('midtrans.client_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized = config('midtrans.is_sanitized');
        Config::$is3ds = config('midtrans.is_3ds');
    }

    /**
     * Create a new transaction
     *
     * @return mixed
     */
    public function createTransaction(array $transactionDetails, array $customerDetails = [], array $itemDetails = [])
    {
        $params = [
            'transaction_details' => $transactionDetails,
        ];

        if (! empty($customerDetails)) {
            $params['customer_details'] = $customerDetails;
        }

        if (! empty($itemDetails)) {
            $params['item_details'] = $itemDetails;
        }

        return Snap::createTransaction($params);
    }

    /**
     * Get transaction status
     *
     * @return mixed
     */
    public function statusTransaction(string $orderId)
    {
        return Transaction::status($orderId);
    }

    /**
     * Cancel a transaction
     *
     * @return mixed
     */
    public function cancelTransaction(string $orderId)
    {
        return Transaction::cancel($orderId);
    }
}
