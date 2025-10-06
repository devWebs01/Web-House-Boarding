<?php

use App\Services\MidtransService;
use function Livewire\Volt\{state};
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;

state(['transaction']);

$updateStatus = function () {
    $transaction = $this->transaction;

    if (!$transaction || !$transaction->id) {
        $this->alert('error', 'Transaksi tidak ditemukan.', ['position' => 'center', 'toast' => true]);
        return redirect()->back();
    }

    try {
        $midtrans = new MidtransService();
        $response = $midtrans->statusTransaction($transaction->code);

        // response bisa berupa object; ambil property dengan aman
        $txStatus = $response->transaction_status ?? ($response->status_code ?? null);
        $paymentType = $response->payment_type ?? null;
        $fraudStatus = $response->fraud_status ?? null;
        $settlementTime = $response->settlement_time ?? ($response->transaction_time ?? null);

        // Pemetaan Midtrans -> status lokal (sesuai enum migration kamu: pending, confirmed, paid, cancelled)
        // Catatan: "confirmed" adalah status yang kamu gunakan ketika user konfirmasi pesanan.
        // Setelah pembayaran settled/capture -> kita ubah menjadi "paid".
        if ($txStatus === 'capture') {
            // capture biasanya untuk CC; kalau fraud challenge -> pending, else paid
            $localStatus = $paymentType === 'credit_card' && $fraudStatus === 'challenge' ? 'pending' : 'paid';
        } elseif ($txStatus === 'settlement') {
            $localStatus = 'paid';
        } elseif ($txStatus === 'pending') {
            // pembayaran belum selesai (menunggu)
            $localStatus = 'pending';
        } elseif (in_array($txStatus, ['deny', 'cancel', 'expire'])) {
            $localStatus = 'cancelled';
        } elseif ($txStatus === 'challenge') {
            // butuh verifikasi manual
            $localStatus = 'pending';
        } else {
            // unknown status -> jangan ubah status DB, log saja
            \Log::warning('Midtrans returned unknown status', [
                'transaction_id' => $transaction->id,
                'midtrans_status' => $txStatus,
            ]);
            $localStatus = null;
        }

        // Build update payload HANYA untuk kolom yang ada
        $updateData = [];
        if ($localStatus !== null && \Schema::hasColumn($transaction->getTable(), 'status')) {
            $updateData['status'] = $localStatus;
        }

        if (\Schema::hasColumn($transaction->getTable(), 'payment_type') && $paymentType !== null) {
            $updateData['payment_type'] = $paymentType;
        }

        if (\Schema::hasColumn($transaction->getTable(), 'status_message') && isset($response->status_message)) {
            $updateData['status_message'] = $response->status_message;
        }

        if (\Schema::hasColumn($transaction->getTable(), 'gross_amount') && isset($response->gross_amount)) {
            $updateData['gross_amount'] = $response->gross_amount;
        }

        if (\Schema::hasColumn($transaction->getTable(), 'paid_at') && $settlementTime) {
            // simpan paid_at jika ada
            $updateData['paid_at'] = $settlementTime;
        }

        if (!empty($updateData)) {
            try {
                $transaction->update($updateData);
            } catch (\Throwable $e) {
                \Log::warning('Failed to update transaction columns (maybe schema mismatch)', [
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                    'attempted' => $updateData,
                ]);
            }
        }

        \Log::info('Midtrans status checked', [
            'transaction_id' => $transaction->id,
            'midtrans_status' => $txStatus,
            'mapped_status' => $localStatus,
            'payment_type' => $paymentType,
        ]);

        LivewireAlert::title('Status pembayaran diperbarui!')->position('center')->success()->toast()->show();

        return redirect()->route('transactions.show', ['transaction' => $transaction]);
    } catch (\Exception $e) {
        \Log::error('Error checking Midtrans status: ' . $e->getMessage(), ['transaction_id' => $transaction->id]);

        LivewireAlert::title('Error pengecekan Midtrans!')->position('center')->error()->toast()->show();
    }
};

?>

@volt
    <div>
        <button class="w-100 btn btn-outline-secondary btn-embed" wire:click="updateStatus">
            <i class="bi bi-arrow-clockwise me-2"></i> Perbarui Status
        </button>
    </div>
@endvolt
