<?php

namespace App\Services;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Transaction;

class MidtransService
{
    public function __construct()
    {
        $this->setupConfig();
    }

    protected function setupConfig(): void
    {
        // Baca dari config/midtrans.php atau fallback ke env()
        Config::$serverKey = config('midtrans.server_key') ?? env('MIDTRANS_SERVER_KEY', '');
        Config::$clientKey = config('midtrans.client_key') ?? env('MIDTRANS_CLIENT_KEY', '');
        Config::$isProduction = (bool) (config('midtrans.is_production') ?? filter_var(env('MIDTRANS_IS_PRODUCTION', false), FILTER_VALIDATE_BOOLEAN));
        Config::$isSanitized = (bool) (config('midtrans.is_sanitized') ?? true);
        Config::$is3ds = (bool) (config('midtrans.is_3ds') ?? true);

        // Debug log singkat (potong key agar tidak full expose)
        Log::info('Midtrans config loaded', [
            'server_key_set' => ! empty(Config::$serverKey),
            'client_key_set' => ! empty(Config::$clientKey),
            'is_production' => Config::$isProduction,
            'server_key_preview' => empty(Config::$serverKey) ? null : substr(Config::$serverKey, 0, 8).'...',
        ]);
    }

    /**
     * Basic guard untuk memastikan server key terpasang
     */
    public function ensureServerKey(): void
    {
        if (empty(Config::$serverKey)) {
            Log::error('Midtrans server key is empty');
            throw new Exception('Midtrans server key is not configured.');
        }
    }

    /**
     * Build basic params array for Snap (transaction_details + optional parts)
     *
     * $expiry can be:
     *  - null (no expiry block),
     *  - integer minutes,
     *  - array with ['start_time' => Carbon|datetime string, 'unit' => 'minutes', 'duration' => int]
     */
    public function buildSnapParams(string $orderId, float $grossAmount, array $customerDetails = [], array $itemDetails = [], $expiry = null): array
    {
        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) round($grossAmount),
            ],
        ];

        if (! empty($customerDetails)) {
            $params['customer_details'] = $customerDetails;
        }

        if (! empty($itemDetails)) {
            $params['item_details'] = $itemDetails;
        }

        if ($expiry !== null) {
            if (is_numeric($expiry)) {
                // duration in minutes from now
                $params['expiry'] = [
                    'start_time' => Carbon::now()->format('Y-m-d H:i:s O'),
                    'unit' => 'minutes',
                    'duration' => (int) $expiry,
                ];
            } elseif (is_array($expiry)) {
                // user supplied explicit expiry array
                $params['expiry'] = $expiry;
            }
        }

        return $params;
    }

    /**
     * Get Snap token (string). Wraps Snap::getSnapToken
     *
     * @throws Exception on failure
     */
    public function getSnapToken(array $params): string
    {
        $this->ensureServerKey();

        try {
            Log::info('Requesting Snap token', ['order_id' => $params['transaction_details']['order_id'] ?? null]);
            $token = Snap::getSnapToken($params);
            Log::info('Snap token received', ['token_preview' => substr($token, 0, 10).'...']);

            return $token;
        } catch (Exception $e) {
            Log::error('Midtrans getSnapToken error', [
                'message' => $e->getMessage(),
                'stack' => $e->getTraceAsString(),
                'params' => $this->sensitiveParamsPreview($params),
            ]);
            throw $e;
        }
    }

    /**
     * Create full transaction via Snap::createTransaction (returns object/array from SDK)
     */
    public function createTransaction(array $params)
    {
        $this->ensureServerKey();

        try {
            Log::info('Creating Midtrans transaction', [
                'order_id' => $params['transaction_details']['order_id'] ?? null,
            ]);

            $result = Snap::createTransaction($params);

            Log::info('Midtrans createTransaction result', [
                'order_id' => $params['transaction_details']['order_id'] ?? null,
                'token' => $result->token ?? null,
                'redirect_url' => $result->redirect_url ?? null,
            ]);

            return $result;
        } catch (Exception $e) {
            Log::error('Midtrans createTransaction error', [
                'message' => $e->getMessage(),
                'params' => $this->sensitiveParamsPreview($params),
            ]);
            throw $e;
        }
    }

    /**
     * Status check wrapper
     */
    public function statusTransaction(string $orderId)
    {
        $this->ensureServerKey();

        return Transaction::status($orderId);
    }

    /**
     * Cancel wrapper
     */
    public function cancelTransaction(string $orderId)
    {
        $this->ensureServerKey();

        return Transaction::cancel($orderId);
    }

    /**
     * Small helper to avoid logging full sensitive payloads.
     */
    protected function sensitiveParamsPreview(array $params): array
    {
        $preview = $params;
        if (isset($preview['customer_details']['email'])) {
            $preview['customer_details']['email'] = substr($preview['customer_details']['email'], 0, 6).'...';
        }
        if (isset($preview['customer_details']['phone'])) {
            $preview['customer_details']['phone'] = substr($preview['customer_details']['phone'], -6);
        }

        // order_id preview is already present, no need to reassign
        return $preview;
    }
}
