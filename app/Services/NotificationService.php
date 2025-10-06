<?php

namespace App\Services;

use App\Models\Transaction;
use Exception;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected FonnteService $fonnte;

    public function __construct(?FonnteService $fonnte = null)
    {
        $this->fonnte = $fonnte ?: new FonnteService;
    }

    /**
     * Build message string.
     */
    public function buildWhatsAppMessage(Transaction $transaction): string
    {
        $user = $transaction->user;

        return implode("\n", [
            "Dear PIC Pemesanan Kos,\n",
            "Data penyewa pemesanan:\n",
            formatField('Kode Transaksi', $transaction->code),
            formatField('Nama Penyewa', $user->name),
            formatField('Nomor HP', $user->identity->phone_number ?? '-'),
            formatField('Nomor WhatsApp', $user->identity->whatsapp_number ?? '-'),
            formatField('Email', $user->email)."\n",
            formatField('Nama Kos', $transaction->room->boardingHouse->name ?? '-'),
            formatField('Nomor Kamar', 'Kamar '.($transaction->room->room_number ?? '-')),
            formatField('Check-In', \Carbon\Carbon::parse($transaction->check_in)->translatedFormat('d-m-Y')),
            formatField('Check-Out', \Carbon\Carbon::parse($transaction->check_out)->translatedFormat('d-m-Y')),
            formatField('Total', formatRupiah($transaction->total)),
            formatField('Status', $transaction->status ?? 'Menunggu Konfirmasi'),
            "\nTerima kasih.",
        ]);
    }

    /**
     * Send WA to owner. Returns true if success (no exception), false otherwise.
     */
    public function notifyOwner(Transaction $transaction, ?string $ownerWhatsappNumber = null): bool
    {
        try {
            if (empty($ownerWhatsappNumber)) {
                Log::info('Notification skipped: owner has no whatsapp number', ['transaction' => $transaction->id]);

                return false;
            }

            $message = $this->buildWhatsAppMessage($transaction);
            $result = $this->fonnte->send($ownerWhatsappNumber, $message);

            Log::info('WhatsApp sent', [
                'transaction' => $transaction->id,
                'owner_phone' => $ownerWhatsappNumber,
                'result' => $result,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('WhatsApp failed', ['transaction' => $transaction->id, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
