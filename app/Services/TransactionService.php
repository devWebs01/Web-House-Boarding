<?php

namespace App\Services;

use App\Models\Room;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionService
{
    /**
     * Create transaction and mark room booked.
     * Returns created Transaction.
     */
    public function createTransaction(array $payload): Transaction
    {
        return DB::transaction(function () use ($payload) {
            // payload must contain: user_id, boarding_house_id, room_id, code, check_in, check_out, duration, total
            $transaction = Transaction::create([
                'user_id' => $payload['user_id'],
                'boarding_house_id' => $payload['boarding_house_id'],
                'room_id' => $payload['room_id'],
                'code' => $payload['code'],
                'check_in' => $payload['check_in'],
                'check_out' => $payload['check_out'],
                'duration' => $payload['duration'] ?? null,
                'total' => $payload['total'],
                // tambahkan default fields bila perlu, mis: 'status' => 'pending'
            ]);

            // update room status
            Room::find($payload['room_id'])->update(['status' => 'booked']);

            return $transaction;
        });
    }

    /**
     * Rollback helper: delete transaction and set room status available.
     */
    public function cancelAndReleaseRoom(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            try {
                $room = $transaction->room;
                $transaction->delete();

                if ($room) {
                    $room->update(['status' => 'available']);
                }
            } catch (\Throwable $e) {
                Log::error('Failed to cancel transaction: '.$e->getMessage(), [
                    'transaction_id' => $transaction->id ?? null,
                ]);
                throw $e;
            }
        });
    }

    /**
     * Update transaction field(s).
     */
    public function update(Transaction $transaction, array $data): Transaction
    {
        $transaction->update($data);

        return $transaction->refresh();
    }
}
