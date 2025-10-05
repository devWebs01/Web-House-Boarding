<?php
use App\Models\Transaction;
use function Livewire\Volt\{state};
use function Laravel\Folio\{name};

name('transactions.show');

state(['transaction']);

?>
<x-guest-layout>

    @push('styles')
        <link rel="stylesheet" href="{{ asset('fe-assets/css/custom.css') }}">
    @endpush

    @volt
        <div class="invoice-container mt-5 pt-5" id="invoice">

            <img src="https://placehold.co/100x40/007bff/ffffff?text=E-KOST" alt="Company Logo" class="img-fluid mb-3 rounded">
            <div class="invoice-header-top">
                <div>

                    <h1 class="invoice-title">Invoice Transaksi</h1>

                </div>
                <div class="text-end invoice-id-dates">
                    <p class="text-muted mb-0">Kode Transaksi: <span
                            class="fw-bold text-primary">{{ $transaction->code }}</span>
                    </p>
                    <p class="text-muted mb-0">Tanggal Transaksi: <span class="fw-bold text-primary">{{ $transaction->created_at->format('d-m-Y H:i') }}</span>
                    </p>

                </div>
            </div>

            <hr class="my-4">

            <div class="row mb-5">
                <div class="col-md-6 mb-4 mb-md-0">
                    <h6 class="text-uppercase text-muted section-title">Dari:</h6>
                    <p class="mb-1 text-break">
                        <strong>{{ $transaction->room->boardingHouse->name }}</strong>
                    </p>
                    <p class="mb-1 text-break">Alamat: {{ $transaction->room->boardingHouse->address }}</p>
                    <p class="mb-1 text-break">Email: {{ $transaction->room->boardingHouse->owner->email }}</p>

                    <p class="mb-1 text-break">Telp:
                        {{ $transaction->room->boardingHouse->owner->identity->phone_number ?? '-' }}
                    </p>

                    <p class="mb-1 text-break">Whatsapp:
                        {{ $transaction->room->boardingHouse->owner->identity->whatsapp_number ?? '-' }}
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <h6 class="text-uppercase text-muted section-title">Untuk:</h6>
                    <p class="mb-1 text-break">
                        <strong>{{ $transaction->user->name }}</strong>
                    </p>

                    <p class="mb-1 text-break">Alamat: {{ $transaction->user->identity->address }}</p>
                    <p class="mb-1 text-break">Email: {{ $transaction->user->email }}</p>

                    <p class="mb-1 text-break">Telp: {{ $transaction->user->identity->phone_number ?? '-' }}</p>
                    <p class="mb-1 text-break">Whatsapp: {{ $transaction->user->identity->whatsapp_number ?? '-' }}</p>

                </div>
            </div>

            <div class="card border-0 shadow-sm mb-5">
                <div class="card-header bg-primary text-white py-3 rounded-top">
                    <div class="row fw-bold">
                        <div class="col-6">DESKRIPSI ITEM</div>
                        <div class="col-3 text-end">PERIODE</div>
                        <div class="col-3 text-end">TOTAL</div>
                    </div>
                </div>
                <ul class="list-group list-group-flush border-bottom">
                    <li class="list-group-item py-3">
                        <div class="row align-items-center">
                            <div class="col-6">
                                <p class="fw-bold mb-0">{{ $transaction->room->boardingHouse->name }} - Kamar No.
                                    {{ $transaction->room->room_number }}</p>
                                <small class="text-muted">Periode Sewa:
                                    {{ \Carbon\Carbon::parse($transaction->check_in)->format('d M Y') }} s/d
                                    {{ \Carbon\Carbon::parse($transaction->check_out)->format('d M Y') }}</small>
                            </div>
                            <div class="col-3 text-end">
                                {{ \Carbon\Carbon::parse($transaction->check_in)->diffInMonths($transaction->check_out) }}
                                Bulan
                            </div>
                            <div class="col-3 text-end text-nowrap">
                                {{ formatRupiah($transaction->room->price) }}</div>

                        </div>
                    </li>

                </ul>
            </div>

            @include('pages.guest.transactions.[Transaction].confirm', ['transaction' => $transaction])

            <div class="text-center mt-5 pt-4 border-top footer-info">
                <p class="lead fw-semibold mb-2 text-primary">Terima kasih!</p>
                <p class="text-muted mb-0">Status Pembayaran: <strong
                        class="text-success">{{ __('transaction_status.' . $transaction->status) }}</strong>
                </p>
                <button class="btn btn-primary mt-4 px-4 py-2 rounded-pill d-print-none" onclick="window.print()">
                    <i class="fas fa-print me-2">
                    </i> Cetak Invoice
                </button>
            </div>
        </div>
    @endvolt
</x-guest-layout>
