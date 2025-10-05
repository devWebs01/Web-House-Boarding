<?php

use App\Models\{Room, Transaction, Comment};
use function Livewire\Volt\{state, computed, on};
use function Laravel\Folio\{name};
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Carbon\Carbon;
use App\Services\FonnteService;

name("catalog.show");

state([
    
    "body" => '', 
    "rating" => '',
    "selectedRoom" => null,
    "boardingHouse",

    "owner" => fn() => $this->boardingHouse->owner,
    "identity" => fn() => $this->boardingHouse->owner->identity,
    "minimum_rental_period" => fn() => $this->boardingHouse->minimum_rental_period,

    "duration" => "",
    "check_in" => "",
    "rooms" => fn() => $this->boardingHouse->rooms->map(function ($room) {
        $isAvailable = $room->status === "available";

        return (object) array_merge($room->toArray(), [
            "isAvailable" => $isAvailable,
            "statusClass" => $isAvailable ? "success" : "danger",
            "statusIcon" => $isAvailable ? "check" : "x",
            "buttonClass" => $isAvailable ? "primary" : "secondary disabled",
        ]);
    }),
]);


$selectRoom = function ($roomId) {
    $this->selectedRoom = Room::find($roomId);
    $this->dispatch("updateTotalPrice");
};

$total = computed(function () {
    if (!$this->selectedRoom || !$this->duration) {
        return 0;
    }

    return $this->selectedRoom->price * $this->duration;
});

$submitTransaction = function () {
    if (!Auth::check()) {
        return Redirect::route("login");
    }

    if (!Auth()->User()->identity) {
        return Redirect::route("profile.guest");
    }

    $this->validate([
        "duration" => "required|in:1,3,6,12",
        "check_in" => "required|date|after_or_equal:today",
    ]);

    if (!$this->selectedRoom) {
        $this->addError("selectedRoom", "Silakan pilih kamar terlebih dahulu.");
        return;
    }

    // Check if room is still available
    if ($this->selectedRoom->status !== 'available') {
        $this->addError('selectedRoom', 'Kamar yang dipilih sudah tidak tersedia.');
        return;
    }

    try {
        $user = Auth::user();

        // Buat kode transaksi yang lebih unik dan aman
        $transactionCode = "INV-" . now()->format("dmY-His") . "-" . strtoupper(Str::random(4));

        // Hitung check-out
        $checkOut = Carbon::parse($this->check_in)->addMonths($this->duration);

        // Simpan transaksi
        $transaction = Transaction::create([
            "user_id" => $user->id,
            "boarding_house_id" => $this->boardingHouse->id,
            "room_id" => $this->selectedRoom->id,
            "code" => $transactionCode,
            "check_in" => $this->check_in,
            "check_out" => $checkOut,
            "total" => $this->total,
        ]);
        if ($transaction) {
            $this->selectedRoom->update(["status" => "booked"]);

            // Format pesan WhatsApp
            $message = implode("\n", [
                "Dear PIC Pemesanan Kos,\n",
                "Berikut ini terlampir data penyewa yang melakukan pemesanan kamar kos melalui sistem:\n",
                formatField("Kode Transaksi", $transactionCode),
                formatField("Nama Penyewa", $user->name),
                formatField("Nomor HP", $user->identity->phone_number),
                formatField("Nomor Whatsapp", $user->identity->whatsapp_number),
                formatField("Email Penyewa", $user->email) . "\n",
                formatField("Nama Kos", $this->selectedRoom->boardingHouse->name ?? null),
                formatField("Nomor Kamar", "Kamar " . $this->selectedRoom->room_number ?? null),
                formatField("Jadwal Check-In", Carbon::parse($this->check_in)->translatedFormat("d-m-Y")),
                formatField("Jadwal Check-Out", Carbon::parse($checkOut)->translatedFormat("d-m-Y")),
                formatField("Total Pembayaran", formatRupiah($this->total)),
                formatField("Status Transaksi", "Menunggu Konfirmasi"),
                "\nMohon pastikan bahwa Anda telah melakukan konfirmasi ulang terhadap pemesanan kamar ini kepada pelanggan melalui Nomor HP/Whatapp Penyewa yang tertera selambat-lambatnya 1 x 24 jam.\n",
                "Terima kasih.",
            ]);

            $whatsappSent = false;

            if ($this->owner->identity && $this->owner->identity->whatsapp_number) {
                $whatsapp_number = $this->owner->identity->whatsapp_number;

                try {
                    // Kirim WhatsApp (gunakan service) (ganti ke nomor pemilik kost langsung)
                    (new FonnteService())->send($whatsapp_number, $message);
                    $whatsappSent = true;
                } catch (\Throwable $e) {
                    // Log activity jika gagal kirim WA
                    activity()
                        ->causedBy($user)
                        ->withProperties([
                            "exception" => $e->getMessage(),
                            "phone" => $whatsapp_number,
                            "message_excerpt" => substr($message, 0, 100) . "...",
                        ])
                        ->log("Gagal mengirim WhatsApp notifikasi pemesanan kamar.");
                }
            } else {
                // Log jika owner tidak memiliki identity atau whatsapp_number
                activity()
                    ->causedBy($user)
                    ->withProperties([
                        "message_excerpt" => substr($message, 0, 100) . "...",
                    ])
                    ->log("Tidak dapat mengirim WhatsApp notifikasi pemesanan kamar karena owner tidak memiliki whatsapp_number.");
            }

            if (!$whatsappSent) {
                // Rollback: hapus transaksi dan kembalikan status kamar
                $transaction->delete();
                $this->selectedRoom->update(['status' => 'available']);

                LivewireAlert::title('Proses gagal! WhatsApp tidak dapat dikirim.')->position('center')->error()->toast()->show();
                return Redirect::back();
            }
        }

        LivewireAlert::title("Proses Berhasil!")->position("center")->success()->toast()->show();

        return Redirect::route("transactions.index");
    } catch (\Throwable $th) {
        report($th); // log error ke Laravel log

        LivewireAlert::title("Proses gagal!")->position("center")->error()->toast()->show();

        return Redirect::back();
    }
};


$comment = function () {
    if (!Auth::check()) {
        return Redirect::route("login");
    }

    if (!Auth()->User()->identity) {
        return Redirect::route("profile.guest");
    }

    if (in_array(Auth()->user()->role, ["owner", "admin"])) {
        return Redirect::route("home");
    }

    $validatedComment = $this->validate([
        "body" => "required|string|min:5",
        "rating" => "required|in:1,2,3,4,5",
    ]);

    // Check if user has already commented on this boarding house
    if (Comment::where('user_id', auth()->id())->where('boarding_house_id', $this->boardingHouse->id)->exists()) {
        $this->addError('body', 'Anda sudah memberikan komentar untuk kos ini.');
        return;
    }

    $validatedComment["user_id"] = auth()->user()->id;
    $validatedComment["boarding_house_id"] = $this->boardingHouse->id;

    try {
        Comment::create($validatedComment);

        LivewireAlert::title("Proses Berhasil!")->position("center")->success()->toast()->show();

        return Redirect::route("catalog.show", ["boardingHouse" => $this->boardingHouse]);
    } catch (\Throwable $th) {
        LivewireAlert::title("Proses Gagal!")->position("center")->error()->toast()->show();

        return Redirect::route("catalog.show", ["boardingHouse" => $this->boardingHouse]);
    }
};

?>

<x-guest-layout>
    @include("components.partials.fancybox")

    @volt
        <div class="container my-5">

            <div class="row">
                <div class="col-lg-6">
                    <div class="kos-gallery">
                        <a data-fancybox
                            data-src="{{ $boardingHouse->thumbnail ? Storage::url($boardingHouse->thumbnail) : "https://dummyimage.com/600x400/000/bfbfbf&text=no+image" }}"
                            data-caption="thumbnail">
                            <img src="{{ $boardingHouse->thumbnail ? Storage::url($boardingHouse->thumbnail) : "https://dummyimage.com/600x400/000/bfbfbf&text=no+image" }}"
                                class="img object-fit-cover mb-3 border" alt="Foto Utama Kos" width="100%" height="500px">
                        </a>

                        <div class="d-flex overflow-auto gap-2 pb-2" style="scroll-snap-type: x mandatory;">
                            @foreach ($boardingHouse->galleries as $gallery)
                                <a data-fancybox="gallery" data-src="{{ Storage::url($gallery->image) }}"
                                    data-caption="Foto Galeri" class="flex-shrink-0"
                                    style="scroll-snap-align: start; width: 120px;">
                                    <img src="{{ Storage::url($gallery->image) }}" class="img-fluid border rounded"
                                        style="object-fit: cover; width: 100%; height: 100px;" alt="Foto Galeri"
                                        loading="lazy" width="120" height="100">
                                </a>
                            @endforeach
                        </div>

                    </div>
                </div>

                <div class="col-lg-6" id="formTransaction">
                    <div class="card shadow-0 border-0">
                        <div class="card-body">
                            <p>
                                <span class="badge bg-primary">{{ __("category." . $boardingHouse->category) }}</span>
                            </p>
                            <h1 class="h3 fw-bold">{{ $boardingHouse->name }}</h1>
                            <p class="text-muted">
                                {{ $boardingHouse->address }}
                            </p>

                            @php
                                $owner = $boardingHouse->owner;
                                $identity = $owner->identity ?? null;
                            @endphp

                            @if ($identity)
                                <div class="accordion mt-4" id="accordionOwnerContact">
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="headingContact">
                                            <button class="accordion-button collapsed" type="button"
                                                data-bs-toggle="collapse" data-bs-target="#collapseContact"
                                                aria-expanded="false" aria-controls="collapseContact">
                                                Kontak Pemilik Kos
                                            </button>
                                        </h2>
                                        <div id="collapseContact" class="accordion-collapse collapse"
                                            aria-labelledby="headingContact" data-bs-parent="#accordionOwnerContact">
                                            <div class="accordion-body">
                                                <ul class="list-unstyled small mb-0">
                                                    @if ($identity->phone_number)
                                                        <li>
                                                            <i class="bi bi-telephone me-2"></i>
                                                            <strong>Telepon:</strong> {{ $identity->phone_number }}
                                                        </li>
                                                    @endif

                                                    @if ($identity->whatsapp_number)
                                                        <li>
                                                            <i class="bi bi-whatsapp me-2"></i>
                                                            <strong>WhatsApp:</strong>
                                                            <a href="https://wa.me/{{ preg_replace("/[^0-9]/", "", $identity->whatsapp_number) }}"
                                                                target="_blank"
                                                                class="text-success text-decoration-underline">
                                                                {{ $identity->whatsapp_number }}
                                                            </a>
                                                        </li>
                                                    @endif

                                                    @if ($identity->address)
                                                        <li>
                                                            <i class="bi bi-geo-alt me-2"></i>
                                                            <strong>Alamat Pemilik:</strong> {{ $identity->address }}
                                                        </li>
                                                        <li>
                                                            <i class="bi bi-map me-2"></i>
                                                            <strong>Lihat di Maps:</strong>
                                                            <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($identity->address) }}"
                                                                target="_blank"
                                                                class="text-primary text-decoration-underline">
                                                                Buka Google Maps
                                                            </a>
                                                        </li>
                                                    @endif
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <p class="text-muted mt-3">Informasi kontak pemilik belum tersedia.</p>
                            @endif

                            <div class="alert alert-secondary" role="alert">
                                <p>Silakan pilih tipe kamar yang tersedia untuk melanjutkan pemesanan.</p>

                                <form wire:submit="submitTransaction" class="row">
                                    @csrf

                                    <div class="col-12 mb-3">
                                        <input type="text" id="roomId" class="form-control" aria-describedby="helpId"
                                            placeholder="kamar yang dipilih"
                                            value="{{ $selectedRoom !== null ? "Kamar " . $selectedRoom->room_number : "" }}"
                                            readonly />

                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="check_in" class="form-label">Tanggal Mulai</label>
                                        <input type="date" wire:model="check_in" class="form-control form-control-sm"
                                            name="check_in" id="check_in" aria-describedby="check_in"
                                            min="{{ today()->format("Y-m-d") }}" placeholder="check_in" />

                                        @error("check_in")
                                            <p id="check_in" class="mt-1 small text-danger">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="duration" class="form-label">Periode Sewa</label>
                                        <select wire:model.live="duration" class="form-select form-select-sm"
                                            name="duration" id="duration">
                                            <option value="">Pilih Durasi</option>
                                            <option value="1" @selected($duration == "1")
                                                @disabled($minimum_rental_period > 1)>
                                                Per 1 Bulan
                                            </option>
                                            <option value="3" @selected($duration == "3")
                                                @disabled($minimum_rental_period > 3)>
                                                Per 3 Bulan
                                            </option>
                                            <option value="6" @selected($duration == "6")
                                                @disabled($minimum_rental_period > 6)>
                                                Per 6 Bulan
                                            </option>
                                            <option value="12" @selected($duration == "12")
                                                @disabled($minimum_rental_period > 12)>
                                                Per 1 Tahun
                                            </option>
                                        </select>

                                        @error("duration")
                                            <p id="duration" class="mt-1 small text-danger">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <hr>

                                    <div class="table-responsive">
                                        <table class="table table-body">
                                            <tbody>
                                                <tr>
                                                    <td>
                                                        {{ formatRupiah($selectedRoom->price ?? "0") }}
                                                    </td>
                                                    <td class="text-center">X</td>
                                                    <td class="text-end">{{ $duration ?? "0" }} Bulan</td>
                                                </tr>
                                                <tr class="fw-bolder">
                                                    <td>Total</td>
                                                    <td colspan="2" class="text-end">
                                                        {{ formatRupiah($this->total ?? "0") }}</td>
                                                </tr>
                                            </tbody>
                                        </table>

                                        <button type="submit" wire:loading.attr="disabled"
                                            class="{{ !empty($selectedRoom) ? "" : "disabled" }} w-100 btn btn-primary">

                                            <span wire:loading.remove>
                                                Submit
                                            </span>

                                            <span wire:loading>
                                                Loading...
                                            </span>
                                        </button>

                                    </div>

                                </form>

                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <section id="fasilitas" class="mt-5">
                <h2 class="section-title">Fasilitas Kos</h2>
                <p>
                    @foreach ($boardingHouse->facilities as $facility)
                        <span class="badge bg-secondary me-2 mb-2 p-2 fw-normal">
                            {{ $facility->name }}
                        </span>
                    @endforeach
                </p>
            </section>

            <section id="daftar-kamar" class="my-5">
                <h2 class="section-title">Pilihan Kamar</h2>

                @foreach ($rooms as $room)
                    {{-- @php
                        $isAvailable = $room->status === "available";
                        $statusClass = $isAvailable ? "success" : "danger";
                        $statusIcon = $isAvailable ? "check" : "x";
                        $buttonClass = $isAvailable ? "primary" : "secondary disabled";
                    @endphp --}}

                    <div class="card card-room mb-3 shadow-sm rounded">
                        <div class="row g-0">
                            <div class="col-md-2">
                                <img src="https://dummyimage.com/600x400/000/bfbfbf&text={{ $room->size }}"
                                    class="img-fluid object-fit-cover w-100 h-100" loading="lazy"
                                    alt="Kamar {{ $room->room_number }}">
                            </div>

                            <div class="col-md-10">
                                <div class="card-body d-flex flex-column h-100">

                                    {{-- Judul & Status --}}
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h5 class="card-title fw-bold mb-2">Kamar {{ $room->room_number }}</h5>
                                            <small class="fw-semibold text-muted">Ukuran: {{ $room->size }}
                                                m<sup>2</sup>
                                            </small>
                                        </div>

                                        <span class="badge bg-{{ $room->statusClass }}">
                                            <i class="bi bi-{{ $room->statusIcon }}-circle me-1"></i>
                                            {{ __("room_status." . $room->status) }}
                                        </span>
                                    </div>

                                    {{-- Harga & Tombol --}}
                                    <div class="mt-auto d-flex justify-content-between align-items-center pt-3">
                                        <p class="card-text fs-5 fw-bold text-primary mb-0">
                                            {{ formatRupiah($room->price) }}
                                            <small class="fw-normal">/bulan</small>
                                        </p>

                                        <a href="#formTransaction" wire:click='selectRoom({{ $room->id }})'
                                            class="btn btn-{{ $room->buttonClass }}">
                                            Sewa Sekarang
                                        </a>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </section>

            @include("pages.guest.catalog.review")

        </div>
    @endvolt
</x-guest-layout>
