<?php

namespace App\Services;

use App\Models\Transaction;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    protected MidtransService $midtrans;

    public function __construct(?MidtransService $midtrans = null)
    {
        $this->midtrans = $midtrans ?: new MidtransService;
    }

    /**
     * Generate snap token for a transaction.
     * Returns token string.
     */
    protected function normalizePhone(?string $phone): string
    {
        if (! $phone) {
            return '';
        }

        // Hanya angka
        $digits = preg_replace('/\D+/', '', $phone);

        // Jika nomor lokal (starts with 0), ubah ke 62
        if (str_starts_with($digits, '0')) {
            $digits = '62'.ltrim($digits, '0');
        }

        // Jika sudah mulai dengan 8 (tanpa 0), tambahkan 62
        if (preg_match('/^8[0-9]{6,}$/', $digits)) {
            $digits = '62'.$digits;
        }

        return $digits;
    }

    /**
     * Generate snap token safely: build items, validate totals, log params.
     */
    public function generateSnapToken(Transaction $transaction, int $expiryMinutes = 60): string
    {
        $orderId = $transaction->code;
        // Pastikan duration minimal 1 kalau kamu menggunakan duration sebagai quantity
        $quantity = max(1, (int) ($transaction->duration ?? 1));

        // Harga per bulan (pastikan numeric)
        $price = (int) round($transaction->room->price ?? 0);

        // Jika booking pricing bukan per-bulan, sesuaikan logika perhitungan item
        $items = [[
            'id' => $transaction->room->id,
            'price' => $price,
            'quantity' => $quantity,
            'name' => 'Sewa Kamar '.($transaction->room->room_number ?? '-').' - '.($transaction->room->boardingHouse->name ?? '-'),
        ]];

        // Hitung gross dari item details (definitif)
        $calculatedGross = 0;
        foreach ($items as $it) {
            $calculatedGross += ((int) $it['price']) * ((int) max(1, $it['quantity']));
        }

        // Pastikan transaction->total juga numeric; jika berbeda, kita log dan prefer hasil perhitungan item
        $declaredGross = (int) round($transaction->total ?? 0);

        if ($declaredGross <= 0) {
            Log::warning('Transaction total is zero or missing; using calculated gross from items', [
                'transaction_id' => $transaction->id,
                'declaredGross' => $declaredGross,
                'calculatedGross' => $calculatedGross,
            ]);
        }

        // If mismatch, override declaredGross with calculatedGross and log.
        if ($declaredGross !== $calculatedGross) {
            Log::info('Overriding gross_amount to match sum(item_details)', [
                'transaction_id' => $transaction->id,
                'declaredGross' => $declaredGross,
                'calculatedGross' => $calculatedGross,
            ]);
            $grossAmount = $calculatedGross;
        } else {
            $grossAmount = $declaredGross;
        }

        // final validation
        if ($grossAmount <= 0) {
            throw new Exception('Invalid gross_amount for Midtrans: '.$grossAmount);
        }

        // customer phone normalize
        $phone = $this->normalizePhone($transaction->user->identity->phone_number ?? $transaction->user->identity->whatsapp_number ?? $transaction->user->phone ?? '');

        $customer = [
            'first_name' => $transaction->user->name ?? 'Customer',
            'email' => $transaction->user->email ?? '',
            'phone' => $phone,
        ];

        $expiry = [
            'start_time' => Carbon::now()->format('Y-m-d H:i:s O'),
            'unit' => 'minutes',
            'duration' => max(1, $expiryMinutes),
        ];

        $params = $this->midtrans->buildSnapParams($orderId, $grossAmount, $customer, $items, $expiry);

        // Log preview params untuk debugging (hindari menulis full sensitive data)
        Log::info('Midtrans params ready (preview)', [
            'order_id' => $orderId,
            'gross_amount' => $grossAmount,
            'items_count' => count($items),
            'item_sample' => $items[0],
            'customer_phone_preview' => substr($phone ?: 'n/a', -6),
        ]);

        // Request token
        try {
            return $this->midtrans->getSnapToken($params);
        } catch (Exception $e) {
            Log::error('Snap token generation failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'params_preview' => [
                    'gross' => $grossAmount,
                    'items' => array_map(function ($i) {
                        return ['id' => $i['id'], 'price' => $i['price'], 'qty' => $i['quantity']];
                    }, $items),
                ],
            ]);
            throw $e;
        }
    }
}
