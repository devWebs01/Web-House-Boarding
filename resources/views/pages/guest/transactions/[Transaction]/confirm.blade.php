<?php
use App\Models\Transaction;
use function Livewire\Volt\{state};
use function Laravel\Folio\{name};
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;


state(['transaction']);

$confirmOrder = function () {
    // Update transaction status to confirmed
    $this->transaction->update(['status' => 'confirmed']);

    LivewireAlert::title('Proses Berhasil!')->position('center')->success()->toast()->show();

    return redirect()->route('transactions.payment', ['transaction' => $this->transaction]);
};

?>


@volt
    <div>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            Pastikan semua detail pesanan sudah benar sebelum melanjutkan ke pembayaran.
        </div>

        <div class="row ">
            <div class="col-md-6">
                <a href="{{ route('transactions.index') }}" class="btn btn-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Kembali
                </a>

            </div>
            <div class="col-md-6 text-end">
                <form wire:submit="confirmOrder" class="d-inline">
                    <input type="hidden" name="transaction_id" value="{{ $transaction }}">
                    @csrf
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-2"></i>Konfirmasi
                    </button>
                </form>
            </div>
        </div>
    </div>
@endvolt
