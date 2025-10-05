<?php

use App\Models\Transaction;
use App\Services\MidtransService;
use Carbon\Carbon;
use function Livewire\Volt\{state, computed, uses};
use function Laravel\Folio\{name, middleware};
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

name('transactions.payment');
middleware(['auth', 'role:guest']);

// State: kita menerima $transaction (model instance) dari route/volt component
state([
    'transaction', // expects an instance of App\Models\Transaction
]);

/**
 * computed snapToken:
 *  - bila $transaction sudah punya snapToken / snap_token -> gunakan
 *  - jika belum -> generate via MidtransService dan simpan ke DB
 */
$snapToken = computed(function () {
    $transaction = $this->transaction;

    // safety: pastikan model terisi
    if (!$transaction || !$transaction->id) {
        Log::warning('Payment view without transaction instance');
        return '';
    }

    // ownership check
    if ($transaction->user_id !== auth()->id()) {
        Log::warning('Unauthorized access to payment page', [
            'transaction_id' => $transaction->id,
            'user_id' => auth()->id(),
        ]);
        abort(403, 'Anda tidak berhak mengakses halaman pembayaran ini.');
    }

    // pick existing attribute either camelCase or snake_case
    $existingToken = $transaction->snapToken ?? ($transaction->snap_token ?? null);
    if (!empty($existingToken)) {
        Log::info('Using existing snap token from DB', [
            'transaction_id' => $transaction->id,
            'preview' => substr($existingToken, 0, 12) . '...',
        ]);
        return $existingToken;
    }

    // Build & validate params before calling Midtrans
    try {
        $midtrans = new MidtransService();

        $orderId = $transaction->code;
        $quantity = max(1, (int) ($transaction->duration ?? 1));
        $price = (int) round($transaction->room->price ?? 0);

        $items = [
            [
                'id' => 'room-' . ($transaction->room->id ?? '0'),
                'price' => $price,
                'quantity' => $quantity,
                'name' => 'Sewa Kamar ' . ($transaction->room->room_number ?? '-') . ' - ' . ($transaction->room->boardingHouse->name ?? '-'),
            ],
        ];

        // ensure gross amount matches items
        $calculatedGross = 0;
        foreach ($items as $it) {
            $calculatedGross += ((int) $it['price']) * ((int) $it['quantity']);
        }

        $grossAmount = (int) round($transaction->total ?? $calculatedGross);
        if ($grossAmount !== $calculatedGross) {
            Log::info('gross_amount mismatch: overriding with calculated items amount', [
                'transaction_id' => $transaction->id,
                'declared' => $transaction->total ?? null,
                'calculated' => $calculatedGross,
            ]);
            $grossAmount = $calculatedGross;
        }

        if ($grossAmount <= 0) {
            Log::error('Invalid gross amount for transaction', ['transaction_id' => $transaction->id, 'gross' => $grossAmount]);
            return '';
        }

        $customer = [
            'first_name' => $transaction->user->name ?? 'Customer',
            'email' => $transaction->user->email ?? '',
            // try both identity phone & whatsapp
            'phone' => $transaction->user->identity->phone_number ?? ($transaction->user->identity->whatsapp_number ?? ''),
        ];

        // build params via service helper (if available) or manual
        if (method_exists($midtrans, 'buildSnapParams')) {
            $params = $midtrans->buildSnapParams($orderId, $grossAmount, $customer, $items, 60);
            // use getSnapToken (if service supports it) or createTransaction
            if (method_exists($midtrans, 'getSnapToken')) {
                $token = $midtrans->getSnapToken($params);
            } else {
                $result = $midtrans->createTransaction(['transaction_details' => ['order_id' => $orderId, 'gross_amount' => $grossAmount], 'customer_details' => $customer, 'item_details' => $items]);
                $token = $result->token ?? ($result->snap_token ?? null);
            }
        } else {
            // fallback to createTransaction signature (older service)
            $result = $midtrans->createTransaction(['transaction_details' => ['order_id' => $orderId, 'gross_amount' => $grossAmount], 'customer_details' => $customer, 'item_details' => $items]);
            $token = $result->token ?? ($result->snap_token ?? null);
        }

        if (empty($token)) {
            Log::error('Midtrans returned empty token', ['transaction_id' => $transaction->id]);
            return '';
        }

        // store token to DB: try camelCase then snake_case
        try {
            // try camelCase attribute first (per model fillable)
            $transaction->update(['snapToken' => $token]);
        } catch (\Throwable $e) {
            // fallback to snake_case column (snap_token)
            try {
                $transaction->update(['snap_token' => $token]);
            } catch (\Throwable $e2) {
                Log::error('Failed to save snap token to DB', [
                    'transaction_id' => $transaction->id,
                    'error1' => $e->getMessage(),
                    'error2' => $e2->getMessage(),
                ]);
                // token still returned to frontend, but DB not updated
            }
        }

        Log::info('Snap token created & returned', ['transaction_id' => $transaction->id, 'token_preview' => substr($token, 0, 12) . '...']);

        return $token;
    } catch (\Exception $ex) {
        Log::error('Failed to generate snap token', [
            'transaction_id' => $transaction->id,
            'error' => $ex->getMessage(),
        ]);
        return '';
    }
});

/**
 * updateStatus: periksa Midtrans transaction status, update transaction->status (best-effort)
 * If DB schema does not support columns updated below, function will catch and log the error.
 */
$updateStatus = function () {
    $transaction = $this->transaction;

    if (!$transaction || !$transaction->id) {
        $this->alert('error', 'Transaksi tidak ditemukan.', ['position' => 'center', 'toast' => true]);
        return redirect()->back();
    }

    try {
        $midtrans = new MidtransService();
        // statusTransaction expects order_id (transaction->code)
        $response = $midtrans->statusTransaction($transaction->code);

        // map midtrans transaction_status to local status
        $mapping = [
            'capture' => 'paid',
            'settlement' => 'paid',
            'pending' => 'pending',
            'deny' => 'failed',
            'cancel' => 'failed',
            'expire' => 'expired',
            'challenge' => 'challenge',
        ];

        $txStatus = $response->transaction_status ?? ($response->status_code ?? null);
        $paymentType = $response->payment_type ?? null;

        // choose mapped status, fallback to raw
        $localStatus = $mapping[$txStatus] ?? ($txStatus ?? 'unknown');

        // Try update transaction record with best-effort fields
        $updateData = [
            'status' => $localStatus,
        ];

        // add some optional fields if exist on model/table
        if (property_exists($transaction, 'payment_type') || \Schema::hasColumn($transaction->getTable(), 'payment_type')) {
            $updateData['payment_type'] = $paymentType;
        }
        if (property_exists($transaction, 'paid_at') || \Schema::hasColumn($transaction->getTable(), 'paid_at')) {
            // settlement_time or transaction_time
            $paidAt = $response->settlement_time ?? ($response->transaction_time ?? null);
            if ($paidAt) {
                $updateData['paid_at'] = $paidAt;
            }
        }

        try {
            $transaction->update($updateData);
        } catch (\Throwable $e) {
            Log::warning('Failed to update transaction columns (maybe schema mismatch)', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('Midtrans status checked', [
            'transaction_id' => $transaction->id,
            'midtrans_status' => $txStatus,
            'local_status' => $localStatus,
        ]);

        $this->alert('success', 'Status pembayaran diperbarui: ' . strtoupper($localStatus), ['position' => 'center', 'toast' => true]);

        return redirect()->route('transactions.show', ['transaction' => $transaction->id]);
    } catch (\Exception $e) {
        Log::error('Error checking Midtrans status: ' . $e->getMessage(), ['transaction_id' => $transaction->id]);
        $msg = $e instanceof ValidationException ? implode('<br>', $e->validator->errors()->all()) : 'Terjadi kesalahan saat mengecek status Midtrans.';
        return $this->alert('error', 'Error pengecekan Midtrans!<br>' . $msg, ['position' => 'center', 'timer' => 4000, 'toast' => true]);
    }
};

?>

<x-guest-layout>
    @volt
        <style>
            /* minimal improved styles */
            .price {
                color: #0d6efd;
                font-weight: 700;
                font-size: 1.6rem;
            }

            .btn-embed {
                width: 100%;
                padding: .75rem;
                font-size: 1rem;
            }

            .snap-wrapper {
                min-height: 520px;
                width: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
            }
        </style>

        <div class="container">
            <div class="row py-5 gap-2 justify-content-between">
                <div class="col-lg-6 card">
                    <div class="card-body">
                        <div class="mt-1 mb-3">
                            <div class="small ">Kode Transaksis</div>
                            <div class="price">{{ $transaction->code }}</div>
                        </div>
                        <div class="d-flex gap-3 align-items-start">
                            <img src="{{ $transaction->room->boardingHouse->thumbnail ? Storage::url($transaction->room->boardingHouse->thumbnail) : 'https://dummyimage.com/140x90/ddd/777&text=No+Img' }}"
                                style="width:140px;height:90px;object-fit:cover;border-radius:.5rem" alt="thumbnail">
                            <div>
                                <h6 class="mb-1">{{ $transaction->room->boardingHouse->name }}</h6>
                                <div class="small mb-2">
                                    Kamar {{ $transaction->room->room_number }}</div>
                                <div class="small mb-2">
                                    Ukuran {{ $transaction->room->size }} m²</div>
                                <div class="small">{{ $transaction->room->boardingHouse->address }}</div>
                            </div>
                        </div>

                        <hr>

                        <div class="row g-2">
                            <div class="col-lg-12 ">Ringkasan Pesanan</div>

                            <div class="col-lg-6 ">Check-in</div>
                            <div class="col-lg-6 text-end">
                                {{ \Carbon\Carbon::parse($transaction->check_in)->translatedFormat('d M Y') }}</div>

                            <div class="col-lg-6 ">Check-out</div>
                            <div class="col-lg-6 text-end">
                                {{ \Carbon\Carbon::parse($transaction->check_out)->translatedFormat('d M Y') }}</div>

                            <div class="col-lg-6 ">Tipe</div>
                            <div class="col-lg-6 text-end">
                                {{ $transaction->room->boardingHouse->type == 'putra' ? 'Putra' : ($transaction->room->boardingHouse->type == 'putri' ? 'Putri' : 'Campur') }}
                            </div>
                        </div>

                    </div>
                </div>

                <div class="col-lg-5 card">
                    <div class="card-body d-flex flex-column">
                        <div class="mb-3">
                            <div class="small ">Total Pembayaran</div>
                            <div class="price">{{ formatRupiah($transaction->total) }}</div>
                        </div>

                        <div id="snap-container" class="snap-wrapper mb-3">
                            {{-- snap.embed akan merender di sini --}}
                            @if (empty($this->snapToken))
                                <div class="text-center ">Token pembayaran belum tersedia. Silakan muat ulang halaman.
                                </div>
                            @endif
                        </div>

                        <button id="embed-button" class="btn btn-primary btn-embed mb-2"
                            {{ empty($this->snapToken) ? 'disabled' : '' }}>
                            <i class="bi bi-wallet2 me-2"></i> Pilih Metode Pembayaran
                        </button>

                        <button id="refresh-status" class="btn btn-outline-secondary btn-embed" wire:click="updateStatus">
                            Perbarui Status Pembayaran
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- load snap.js (sandbox/production auto) --}}
        <script
            src="{{ config('midtrans.is_production') ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js' }}"
            data-client-key="{{ config('midtrans.client_key') }}"></script>

        <script>
            (function() {
                const embedBtn = document.getElementById('embed-button');
                const snapContainer = document.getElementById('snap-container');
                const snapToken = @json($this->snapToken);

                if (!embedBtn) return;

                embedBtn.addEventListener('click', function() {
                    if (!snapToken || snapToken.trim() === '') {
                        alert('Snap token tidak tersedia. Silakan refresh halaman atau hubungi admin.');
                        return;
                    }

                    // clear container, then embed
                    snapContainer.innerHTML = '';
                    try {
                        window.snap.embed(snapToken, {
                            embedId: 'snap-container',
                            onSuccess: function(result) {
                                console.log('Midtrans success', result);
                                alert('Pembayaran sukses.');
                                location.reload();
                            },
                            onPending: function(result) {
                                console.log('Midtrans pending', result);
                                alert('Pembayaran menunggu.');
                                location.reload();
                            },
                            onError: function(err) {
                                console.error('Midtrans error', err);
                                alert('Terjadi kesalahan saat memproses pembayaran.');
                            },
                            onClose: function() {
                                console.log('User closed snap');
                            }
                        });
                    } catch (err) {
                        console.error('Embed error', err);
                        alert('Gagal memuat metode pembayaran.');
                    }
                });

                // quick dev log
                console.log('Snap token preview:', snapToken ? (snapToken.slice(0, 10) + '...') : 'empty');
            })();
        </script>
    @endvolt
</x-guest-layout>
