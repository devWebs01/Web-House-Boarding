<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Penyewa
            $table->foreignId('room_id')->constrained()->onDelete('cascade'); // Kos yang dipesan
            $table->foreignId('boarding_house_id')->constrained()->onDelete('cascade'); // Kos yang dipesan
            $table->string('code')->unique(); // Kode transaksi unik
            $table->string('snapToken')->nullable(); // Token Snap
            $table->date('check_in');
            $table->date('check_out');
            $table->string('total'); // total pembayaran
            $table->enum('status', ['pending', 'confirmed', 'paid', 'cancelled'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
