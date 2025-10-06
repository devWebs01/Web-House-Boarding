<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'boarding_house_id',
        'room_id',
        'code',
        'check_in',
        'check_out',
        'total',
        'status',
        'snapToken',
    ];

    // Relasi ke user (penyewa)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relasi ke kamar
    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    // Relasi ke induk kos
    public function boardingHouse()
    {
        return $this->belongsTo(BoardingHouse::class);
    }
}
