<?php

use App\Models\Transaction;
use App\Services\MidtransService;
use Carbon\Carbon;
use function Livewire\Volt\{state, computed, uses};
use function Laravel\Folio\{name, middleware};
use Illuminate\Support\Facades\Log;

name('transactions.payment');

// State: kita menerima $transaction (model instance) dari route/volt component
state([
    'loading' => false,
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

    // // ownership check
    // if (!$transaction || $transaction->user_id !== Auth::user()->id) {
    //     Log::warning('Unauthorized access to payment page', [
    //         'transaction_id' => $transaction->id ?? 'null',
    //         'user_id' => Auth::user()->id,
    //         'transaction_user_id' => $transaction->user_id ?? 'null',
    //     ]);
    //     abort(403, 'Anda tidak berhak mengakses halaman pembayaran ini.');
    // }
    // validate transaction data
    try {
        $checkIn = \Carbon\Carbon::parse($transaction->check_in);
        $checkOut = \Carbon\Carbon::parse($transaction->check_out);
        if ($checkIn->gte($checkOut)) {
            Log::error('Invalid check-in/check-out dates', [
                'transaction_id' => $transaction->id,
                'check_in' => $transaction->check_in,
                'check_out' => $transaction->check_out,
            ]);
            return '';
        }
        $duration = $checkIn->diffInDays($checkOut);
        if ($duration <= 0) {
            Log::error('Invalid duration for transaction', [
                'transaction_id' => $transaction->id,
                'duration' => $duration,
            ]);
            return '';
        }
    } catch (\Exception $e) {
        Log::error('Failed to parse dates', [
            'transaction_id' => $transaction->id,
            'error' => $e->getMessage(),
        ]);
        return '';
    }

    // pick existing attribute
    $existingToken = $transaction->snapToken;
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
        // calculate duration in days
        $checkIn = \Carbon\Carbon::parse($transaction->check_in);
        $checkOut = \Carbon\Carbon::parse($transaction->check_out);
        $duration = $checkIn->diffInDays($checkOut);
        $quantity = max(1, (int) $duration);
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

        $grossAmount = (int) round((int) $transaction->total ?? $calculatedGross);
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

        // store token to DB
        try {
            $transaction->update(['snapToken' => $token]);
        } catch (\Throwable $e) {
            Log::error('Failed to save snap token to DB', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
            // token still returned to frontend, but DB not updated
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

?>

<x-guest-layout>
    @volt
        <style>
            .price {
                color: #0d6efd;
                font-weight: 700;
                font-size: 1.6rem;
            }

            .btn-embed {
                width: 100%;
                padding: 0.75rem;
                font-size: 1rem;
            }

            .snap-wrapper {
                min-height: 420px;
                width: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                border: 1px dashed #ddd;
                border-radius: 0.75rem;
                background-color: #f8f9fa;
            }

            .transaction-summary .row>div {
                padding: 0.4rem 0;
            }

            .badge {
                font-size: 0.85rem;
                text-transform: capitalize;
            }
        </style>

        <div class="container py-5">
            <div class="row g-4">
                {{-- DETAIL TRANSAKSI --}}
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <div class="mb-4">
                                <div class="text-muted small">Kode Transaksi</div>
                                <div class="price">{{ $transaction->code }}</div>
                            </div>

                            <div class="d-flex gap-3 mb-3 align-items-start">
                                <img src="{{ $transaction->room->boardingHouse->thumbnail ? Storage::url($transaction->room->boardingHouse->thumbnail) : 'https://dummyimage.com/140x90/ddd/777&text=No+Img' }}"
                                    alt="thumbnail" class="rounded" style="width:140px;height:90px;object-fit:cover;">
                                <div>
                                    <h6 class="mb-1">{{ $transaction->room->boardingHouse->name }}</h6>
                                    <div class="small text-muted">
                                        Kamar {{ $transaction->room->room_number }}<br>
                                        Ukuran {{ $transaction->room->size }} m²<br>
                                        {{ $transaction->room->boardingHouse->address }}
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <div class="transaction-summary">
                                <div class="row">
                                    <div class="col-6">Check-in</div>
                                    <div class="col-6 text-end">
                                        {{ \Carbon\Carbon::parse($transaction->check_in)->translatedFormat('d M Y') }}
                                    </div>

                                    <div class="col-6">Check-out</div>
                                    <div class="col-6 text-end">
                                        {{ \Carbon\Carbon::parse($transaction->check_out)->translatedFormat('d M Y') }}
                                    </div>

                                    <div class="col-6">Durasi Sewa</div>
                                    <div class="col-6 text-end">
                                        {{ \Carbon\Carbon::parse($transaction->check_in)->diffInDays(\Carbon\Carbon::parse($transaction->check_out)) }}
                                        hari
                                    </div>

                                    <div class="col-6">Harga per Hari</div>
                                    <div class="col-6 text-end">{{ formatRupiah($transaction->room->price) }}</div>

                                    <div class="col-6 fw-semibold">Total Pembayaran</div>
                                    <div class="col-6 fw-semibold text-end">{{ formatRupiah($transaction->total) }}</div>

                                    <div class="col-6">Tipe Kos</div>
                                    <div class="col-6 text-end">
                                        {{ ucfirst($transaction->room->boardingHouse->type) }}
                                    </div>

                                    <div class="col-6">Status Transaksi</div>
                                    <div class="col-6 text-end">
                                        <span
                                            class="badge bg-{{ $transaction->status === 'paid' ? 'success' : ($transaction->status === 'cancelled' ? 'danger' : 'warning') }}">
                                            {{ ucfirst($transaction->status) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- PEMBAYARAN MIDTRANS --}}
                <div class="col-lg-5">
                    <div class="card shadow-sm border-0">
                        <div class="card-body d-flex flex-column">
                            <div class="mb-4">
                                <div class="text-muted small">Total Pembayaran</div>
                                <div class="price">{{ formatRupiah($transaction->total) }}</div>
                            </div>

                            <div id="snap-container" class="snap-wrapper mb-3">
                                @if (empty($this->snapToken))
                                    <div class="text-center text-muted">
                                        Token pembayaran belum tersedia.<br>Silakan muat ulang halaman.
                                    </div>
                                @endif
                            </div>

                            <button id="embed-button" class="btn btn-primary btn-embed mb-2" wire:loading.attr="disabled"
                                {{ empty($this->snapToken) ? 'disabled' : '' }}>
                                <span wire:loading.remove><i class="bi bi-wallet2 me-2"></i>Pilih Metode Pembayaran</span>
                                <span wire:loading><i class="spinner-border spinner-border-sm me-2"></i>Memuat...</span>
                            </button>

                            @include('pages.guest.transactions.[Transaction].check-status', [
                                'transaction' => $transaction,
                            ])
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Snap.js Loader --}}
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

                console.log('Snap token preview:', snapToken ? (snapToken.slice(0, 10) + '...') : 'empty');
            })();
        </script>
    @endvolt
</x-guest-layout>
