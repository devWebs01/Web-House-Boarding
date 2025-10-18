<?php

use App\Models\Transaction;
use App\Services\MidtransService;
use Carbon\Carbon;
use function Livewire\Volt\{state, computed, mount};
use function Laravel\Folio\{name, middleware};
use Illuminate\Support\Facades\Log;

name('transactions.payment');
middleware(['auth']); // PENTING: Pastikan user sudah login

// State
state([
    'loading' => false,
    'transaction',
    'isAuthorized' => false,
]);

/**
 * Mount: Validasi ownership saat component di-load
 */
mount(function () {
    // Pastikan transaction tersedia
    if (!$this->transaction || !$this->transaction->id) {
        Log::warning('Payment view without transaction instance');
        abort(404, 'Transaksi tidak ditemukan.');
    }

    // Ownership check - pindah ke mount agar cek sebelum render
    // if ($this->transaction->user_id !== auth()->id()) {
    //     Log::warning('Unauthorized access to payment page', [
    //         'transaction_id' => $this->transaction->id,
    //         'user_id' => auth()->id(),
    //         'transaction_user_id' => $this->transaction->user_id,
    //     ]);
    //     abort(403, 'Anda tidak berhak mengakses halaman pembayaran ini.');
    // }

    
    $this->isAuthorized = true;
});

/**
 * Computed snapToken:
 * Hanya generate token jika authorized
 */
$snapToken = computed(function () {
    if (!$this->isAuthorized) {
        return '';
    }

    $transaction = $this->transaction;

    // Validasi data transaksi
    try {
        $checkIn = Carbon::parse($transaction->check_in);
        $checkOut = Carbon::parse($transaction->check_out);

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

    // Cek apakah sudah ada token di database
    $existingToken = $transaction->snapToken ?? ($transaction->snap_token ?? null);

    if (!empty($existingToken)) {
        Log::info('Using existing snap token from DB', [
            'transaction_id' => $transaction->id,
            'preview' => substr($existingToken, 0, 12) . '...',
        ]);
        return $existingToken;
    }

    // Generate token baru
    try {
        $midtrans = new MidtransService();
        $orderId = $transaction->code;

        // Hitung durasi
        $checkIn = Carbon::parse($transaction->check_in);
        $checkOut = Carbon::parse($transaction->check_out);
        $duration = $checkIn->diffInDays($checkOut);
        $quantity = max(1, (int) $duration);
        $price = (int) round($transaction->room->price ?? 0);

        // Item details
        $items = [
            [
                'id' => 'room-' . ($transaction->room->id ?? '0'),
                'price' => $price,
                'quantity' => $quantity,
                'name' => 'Sewa Kamar ' . ($transaction->room->room_number ?? '-') . ' - ' . ($transaction->room->boardingHouse->name ?? '-'),
            ],
        ];

        // Hitung gross amount
        $calculatedGross = 0;
        foreach ($items as $item) {
            $calculatedGross += ((int) $item['price']) * ((int) $item['quantity']);
        }

        $grossAmount = (int) round((int) $transaction->total ?? $calculatedGross);

        if ($grossAmount !== $calculatedGross) {
            Log::info('Gross amount mismatch: using calculated items amount', [
                'transaction_id' => $transaction->id,
                'declared' => $transaction->total ?? null,
                'calculated' => $calculatedGross,
            ]);
            $grossAmount = $calculatedGross;
        }

        if ($grossAmount <= 0) {
            Log::error('Invalid gross amount', [
                'transaction_id' => $transaction->id,
                'gross' => $grossAmount,
            ]);
            return '';
        }

        // Customer details
        $customer = [
            'first_name' => $transaction->user->name ?? 'Customer',
            'email' => $transaction->user->email ?? '',
            'phone' => $transaction->user->identity->phone_number ?? ($transaction->user->identity->whatsapp_number ?? ''),
        ];

        // Build transaction params
        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $grossAmount,
            ],
            'customer_details' => $customer,
            'item_details' => $items,
        ];

        // Generate snap token
        $token = null;

        if (method_exists($midtrans, 'buildSnapParams')) {
            $snapParams = $midtrans->buildSnapParams($orderId, $grossAmount, $customer, $items, 60);

            if (method_exists($midtrans, 'getSnapToken')) {
                $token = $midtrans->getSnapToken($snapParams);
            } else {
                $result = $midtrans->createTransaction($snapParams);
                $token = $result->token ?? ($result->snap_token ?? null);
            }
        } else {
            $result = $midtrans->createTransaction($params);
            $token = $result->token ?? ($result->snap_token ?? null);
        }

        if (empty($token)) {
            Log::error('Midtrans returned empty token', [
                'transaction_id' => $transaction->id,
            ]);
            return '';
        }

        // Simpan token ke database
        try {
            $transaction->update(['snapToken' => $token]);
            Log::info('Snap token saved to database', [
                'transaction_id' => $transaction->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to save snap token to DB', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('Snap token generated successfully', [
            'transaction_id' => $transaction->id,
            'token_preview' => substr($token, 0, 12) . '...',
        ]);

        return $token;
    } catch (\Exception $ex) {
        Log::error('Failed to generate snap token', [
            'transaction_id' => $transaction->id,
            'error' => $ex->getMessage(),
            'trace' => $ex->getTraceAsString(),
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

            .loading-spinner {
                display: inline-block;
                width: 2rem;
                height: 2rem;
                border: 3px solid rgba(13, 110, 253, 0.2);
                border-top-color: #0d6efd;
                border-radius: 50%;
                animation: spin 0.8s linear infinite;
            }

            @keyframes spin {
                to {
                    transform: rotate(360deg);
                }
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
                                @php
                                    $thumbnail = $transaction->room->boardingHouse->thumbnail
                                        ? Storage::url($transaction->room->boardingHouse->thumbnail)
                                        : 'https://dummyimage.com/140x90/ddd/777&text=No+Img';
                                @endphp
                                <img src="{{ $thumbnail }}" alt="thumbnail" class="rounded"
                                    style="width:140px;height:90px;object-fit:cover;">
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
                                        @php
                                            $statusColor = match ($transaction->status) {
                                                'paid' => 'success',
                                                'cancelled' => 'danger',
                                                default => 'warning',
                                            };
                                        @endphp
                                        <span class="badge bg-{{ $statusColor }}">
                                            {{ ucfirst($transaction->status) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- PEMBAYARAN MIDTRANS --}}
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0">
                        <div class="card-body d-flex flex-column">
                            <div class="mb-4">
                                <div class="text-muted small">Total Pembayaran</div>
                                <div class="price">{{ formatRupiah($transaction->total) }}</div>
                            </div>

                            <div id="snap-container" class="snap-wrapper mb-3">
                                @if (empty($this->snapToken))
                                    <div class="text-center text-muted">
                                        <div class="loading-spinner mb-2"></div>
                                        <div>Memuat pembayaran...</div>
                                        <small>Jika tidak muncul, silakan muat ulang halaman</small>
                                    </div>
                                @endif
                            </div>

                            <button id="embed-button" class="btn btn-primary btn-embed mb-2" wire:loading.attr="disabled"
                                {{ empty($this->snapToken) ? 'disabled' : '' }}>
                                <span wire:loading.remove>
                                    <i class="bi bi-wallet2 me-2"></i>Pilih Metode Pembayaran
                                </span>
                                <span wire:loading>
                                    <i class="spinner-border spinner-border-sm me-2"></i>Memuat...
                                </span>
                            </button>

                            @if (empty($this->snapToken))
                                <div class="alert alert-info mb-2">
                                    <small>
                                        <i class="bi bi-info-circle me-1"></i>
                                        Token pembayaran sedang diproses. Mohon tunggu beberapa saat.
                                    </small>
                                </div>
                            @endif

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

                if (!embedBtn) {
                    console.error('Embed button not found');
                    return;
                }

                // Auto-enable button when token is available
                if (snapToken && snapToken.trim() !== '') {
                    embedBtn.disabled = false;
                    console.log('Snap token loaded:', snapToken.slice(0, 10) + '...');
                }

                embedBtn.addEventListener('click', function() {
                    if (!snapToken || snapToken.trim() === '') {
                        alert('Snap token tidak tersedia. Silakan refresh halaman atau hubungi admin.');
                        return;
                    }

                    // Clear container
                    snapContainer.innerHTML = '<div class="text-center"></div>';

                    try {
                        window.snap.embed(snapToken, {
                            embedId: 'snap-container',
                            onSuccess: function(result) {
                                console.log('Payment success:', result);
                                alert('Pembayaran berhasil! Halaman akan dimuat ulang.');
                                setTimeout(() => location.reload(), 1000);
                            },
                            onPending: function(result) {
                                console.log('Payment pending:', result);
                                alert('Pembayaran sedang diproses. Halaman akan dimuat ulang.');
                                setTimeout(() => location.reload(), 1000);
                            },
                            onError: function(err) {
                                console.error('Payment error:', err);
                                alert(
                                    'Terjadi kesalahan saat memproses pembayaran. Silakan coba lagi.'
                                );
                                snapContainer.innerHTML =
                                    '<div class="text-center text-danger"><i class="bi bi-x-circle fs-1"></i><div>Gagal memuat pembayaran</div></div>';
                            },
                            onClose: function() {
                                console.log('User closed payment popup');
                                snapContainer.innerHTML =
                                    '<div class="text-center text-muted">Pembayaran dibatalkan. Klik tombol untuk mencoba lagi.</div>';
                            }
                        });
                    } catch (err) {
                        console.error('Snap embed error:', err);
                        alert('Gagal memuat metode pembayaran. Silakan refresh halaman.');
                        snapContainer.innerHTML =
                            '<div class="text-center text-danger">Gagal memuat pembayaran</div>';
                    }
                });

                console.log('Payment page initialized');
            })();
        </script>
    @endvolt
</x-guest-layout>
