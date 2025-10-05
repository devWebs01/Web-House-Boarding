<?php

use App\Models\Transaction;
use App\Services\MidtransService;
use function Livewire\Volt\{state, computed};
use function Laravel\Folio\{name, middleware};
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;

name('transactions.payment');
middleware(['auth', 'role:guest']);

// Simpan ID transaksi dari parameter route
state(['transaction']);

// // Ambil data transaksi lengkap
// $transaction = computed(function () {
    
//     return Transaction::with(['room.boardingHouse', 'user.identity'])
//         ->findOrFail($this->transaction);
// });

// Buat Snap Token Midtrans
$snapToken = computed( function () {
    $transaction = $this->transaction;

    try {
        // Validasi agar hanya user yang memiliki transaksi yang bisa membayar
        if ($transaction->user_id !== auth()->id() || $transaction->status !== 'confirmed') {
            \Log::warning('Unauthorized payment attempt', [
                'transaction_id' => $transaction->id,
                'user_id' => auth()->id(),
                'transaction_user_id' => $transaction->user_id,
                'transaction_status' => $transaction->status,
            ]);
            abort(403, 'Unauthorized access to payment');
        }

        $midtransService = new MidtransService();

        $transactionDetails = [
            'order_id' => $transaction->code,
            'gross_amount' => (int) $transaction->total,
        ];

        $customerDetails = [
            'first_name' => $transaction->user->name,
            'email' => $transaction->user->email,
            'phone' => $transaction->user->identity->phone_number ?? '',
        ];

        $itemDetails = [[
            'id' => 'room-' . $transaction->room->id,
            'price' => (int) $transaction->room->price,
            'quantity' => max(1, (int) $transaction->duration), // Pastikan quantity minimal 1
            'name' => 'Sewa Kamar ' . $transaction->room->room_number . ' - ' . $transaction->room->boardingHouse->name,
        ]];

        \Log::info('Creating snap token for transaction', [
            'transaction_id' => $transaction->id,
            'order_id' => $transaction->code,
            'gross_amount' => $transaction->total,
        ]);

        $snapResult = $midtransService->createTransaction($transactionDetails, $customerDetails, $itemDetails);
        $token = $snapResult->token;

        \Log::info('Snap token created successfully', [
            'transaction_id' => $transaction->id,
            'token_length' => strlen($token),
        ]);

        return $token;

    } catch (\Exception $e) {
        \Log::error('Failed to create snap token', [
            'transaction_id' => $transaction->id,
            'error_message' => $e->getMessage(),
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine(),
        ]);

        // Return empty token to prevent frontend errors
        return '';
    }
});
?>

<x-guest-layout>
  @volt
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card shadow">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0">Pilih Metode Pembayaran</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            {{-- Kolom kiri --}}
                            <div class="col-md-8">
                                <h5>Ringkasan Pesanan</h5>
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-sm-3">
                                                <img src="{{ $transaction->room->boardingHouse->thumbnail 
                                                    ? Storage::url($transaction->room->boardingHouse->thumbnail) 
                                                    : 'https://dummyimage.com/200x150/000/bfbfbf&text=no+image' }}"
                                                    class="img-fluid rounded" alt="Kos">
                                            </div>
                                            <div class="col-sm-9">
                                                <h6>{{ $transaction->room->boardingHouse->name }}</h6>
                                                <p class="mb-1">{{ $transaction->room->boardingHouse->address }}</p>
                                                <p class="mb-1">
                                                    Kamar {{ $transaction->room->room_number }}
                                                    ({{ $transaction->room->size }} m²)
                                                </p>
                                                <p class="mb-1">
                                                    Check-in: {{ \Carbon\Carbon::parse($transaction->check_in)->translatedFormat('d-m-Y') }}
                                                </p>
                                                <p class="mb-0">Durasi: {{ $transaction->duration }} bulan</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <h5>Metode Pembayaran Tersedia</h5>
                                <div class="row">
                                    @foreach ([
                                        ['method' => 'gopay', 'color' => '00ff00', 'text' => 'GoPay'],
                                        ['method' => 'ovo', 'color' => 'purple', 'text' => 'OVO'],
                                        ['method' => 'dana', 'color' => '007bff', 'text' => 'DANA'],
                                        ['method' => 'bank', 'color' => '999999', 'text' => 'Transfer Bank'],
                                    ] as $m)
                                        <div class="col-md-6">
                                            <div class="card payment-method mb-3" data-method="{{ $m['method'] }}">
                                                <div class="card-body text-center">
                                                    <img src="https://dummyimage.com/100x50/{{ $m['color'] }}/ffffff&text={{ urlencode($m['text']) }}"
                                                        class="img-fluid mb-2" alt="{{ $m['text'] }}">
                                                    <p class="mb-0">{{ $m['text'] }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Kolom kanan --}}
                            <div class="col-md-4">
                                <div class="card sticky-top" style="top: 20px;">
                                    <div class="card-header">
                                        <h6 class="mb-0">Total Pembayaran</h6>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <tr>
                                                <td>Harga per bulan</td>
                                                <td class="text-end">{{ formatRupiah($transaction->room->price) }}</td>
                                            </tr>
                                            <tr>
                                                <td>Durasi</td>
                                                <td class="text-end">{{ $transaction->duration }} bulan</td>
                                            </tr>
                                            <tr class="fw-bold border-top">
                                                <td>Total</td>
                                                <td class="text-end">{{ formatRupiah($transaction->total) }}</td>
                                            </tr>
                                        </table>

                                        <button id="pay-button" class="btn btn-primary w-100">
                                            <i class="bi bi-credit-card me-2"></i>Bayar Sekarang
                                        </button>

                                        <p class="text-muted small mt-2 mb-0">
                                            <i class="bi bi-shield-check me-1"></i>
                                            Pembayaran aman dengan Midtrans
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Midtrans Snap --}}
    <script src="https://app.midtrans.com/snap/snap.js" data-client-key="{{ config('midtrans.client_key') }}"></script>
    <script>
        document.getElementById('pay-button').onclick = function() {
            const snapToken = '{{ $this->snapToken }}';

            console.log('Initiating payment with snap token:', snapToken ? 'Token exists' : 'No token');

            if (!snapToken || snapToken.trim() === '') {
                console.error('Snap token is empty or missing');
                alert('Error: Tidak dapat memproses pembayaran. Token pembayaran kosong.');
                return;
            }

            snap.pay(snapToken, {
                onSuccess: function(result) {
                    console.log('Payment success:', result);
                    window.location.href = '{{ route('transactions.index') }}';
                },
                onPending: function(result) {
                    console.log('Payment pending:', result);
                    window.location.href = '{{ route('transactions.index') }}';
                },
                onError: function(result) {
                    console.error('Payment error:', result);
                    alert('Pembayaran gagal! Silakan coba lagi atau hubungi administrator.');
                },
                onClose: function() {
                    console.log('Payment popup closed');
                }
            });
        };
    </script>
  @endvolt
</x-guest-layout>
