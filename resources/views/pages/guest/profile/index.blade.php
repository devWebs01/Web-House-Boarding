<?php

use App\Models\User;
use Illuminate\Validation\Rule;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;

use function Livewire\Volt\{state, usesFileUploads};
use function Laravel\Folio\{name, middleware};

middleware(["auth"]);

usesFileUploads();

name("profile.guest");

state([
    "user" => fn() => Auth::User(),
    "name" => fn() => $this->user->name,
    "email" => fn() => $this->user->email,
    "password",

    // identity
    "identity" => fn() => $this->user->identity ?? null,
    "phone_number" => fn() => $this->identity->phone_number ?? null,
    "whatsapp_number" => fn() => $this->identity->whatsapp_number ?? null,
    "id_card",
    "address" => fn() => $this->identity->address ?? null,
    "user_id" => fn() => $this->identity->user_id ?? null,
]);

$updateUser = function () {
    $user = $this->user;

    $validated = $this->validate([
        // User fields
        "name" => ["required", "min:5"],
        "email" => ["required", "email", "min:5", Rule::unique(User::class)->ignore($user->id)],
        "password" => ["nullable", "min:5"],

        // Identity fields
        "phone_number" => ["required", "digits_between:10,15"],
        "whatsapp_number" => ["required", "digits_between:10,15"],
        "id_card" => ["nullable", "image", "mimes:jpeg,png,jpg"],
        "address" => ["required", "string", "min:10", "max:255"],
    ]);

    // Proses data user
    $userData = [
        "name" => $validated["name"],
        "email" => $validated["email"],
    ];

    if (!empty($validated["password"])) {
        $userData["password"] = bcrypt($validated["password"]);
    }

    $user->update($userData);

    // Proses data identity
    $identityData = [
        "phone_number" => $validated["phone_number"],
        "whatsapp_number" => $validated["whatsapp_number"] ?? null,
        "address" => $validated["address"],
    ];

    if (!empty($this->id_card)) {
        $identityData["id_card"] = $this->id_card->store("id_cards", "public");
    }

    $user->identity()->updateOrCreate(["user_id" => $user->id], $identityData);

    LivewireAlert::title("Proses Berhasil!")->position("center")->success()->toast()->show();

    $this->redirectRoute("profile.guest");
};

?>

<x-guest-layout>
    <x-slot name="title">Profil Akun</x-slot>
    <x-slot name="header">
        <li class="breadcrumb-item">
            <a href="#">Profil Akun</a>
        </li>
    </x-slot>

    @volt
        <div>
            <x-slot name="title">{{ $user->name }}</x-slot>

            <div class="container mt-5">
                <div class="card bg-info-subtle shadow-none position-relative overflow-hidden mb-4">
                    <div class="card-body px-4 py-3">
                        <div class="row align-items-center">
                            <div class="col-9">
                                <h4 class="fw-semibold mb-8">Data Profil Akun</h4>
                                <p class="text-muted mb-4 fs-6">
                                    Pada halaman update pengguna, kamu dapat mengubah informasi pengguna.
                                </p>
                            </div>
                            <div class="col-3">
                                <div class="text-center mb-n5">
                                    <img src="https://ouch-prod-var-cdn.icons8.com/iy/illustrations/thumbs/HX8M3yKf5cJ8K1f1.webp"
                                        alt="modernize-img" class="img-fluid w-50 mb-n4">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card border-0">

                    <div class="card-body border rounded">
                        <form class="row" wire:submit='updateUser' method="POST" enctype="multipart/form-data">

                            <h4 class="fw-bold mb-3">Data Akun</h4>
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Nama Lengkap</label>
                                <input type="text" name="name" class="form-control" wire:model="name" required>
                                @error("name")
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Alamat Email</label>
                                <input type="email" name="email" class="form-control" wire:model="email" required>
                                @error("email")
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md mb-3">
                                <label for="password" class="form-label">Password (Kosongkan jika tidak ingin
                                    mengubah)</label>
                                <input type="password" name="password" class="form-control" wire:model="password">
                                @error("password")
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <h4 class="fw-bold mb-3">Data Identitas</h4>

                            <div class="row">
                                <div class="col-lg-8">

                                    <div class="col-md-12 mb-3">
                                        <label for="phone_number" class="form-label">Nomor Telepon</label>
                                        <input type="number" name="phone_number" class="form-control"
                                            wire:model="phone_number">
                                        @error("phone_number")
                                            <div class="text-danger small mt-1">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <label for="whatsapp_number" class="form-label">Nomor WhatsApp</label>
                                        <input type="number" name="whatsapp_number" class="form-control"
                                            wire:model="whatsapp_number">
                                        @error("whatsapp_number")
                                            <div class="text-danger small mt-1">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <label for="id_card" class="form-label">Upload KTP / ID Card</label>

                                        <div wire:loading wire:target="id_card" class="mb-2">
                                            <div class="spinner-border text-warning spinner-border-sm" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                            <span class="ms-2 text-warning">Mengunggah file...</span>
                                        </div>

                                        <input type="file" name="id_card" class="form-control" wire:model="id_card" {{$identity->id_card === null ? 'required' : ''}}>

                                        @error("id_card")
                                            <div class="text-danger small mt-1">{{ $message }}</div>
                                        @enderror
                                    </div>

                                </div>
                                <div class="col-lg">
                                    @if ($id_card)
                                        <a data-fancybox data-src="{{ $id_card->temporaryUrl() }}"
                                            data-caption="ID Card User">
                                            <img src="{{ $id_card->temporaryUrl() }}" class="img rounded-4" width="100%"
                                                height="280px" style="object-fit: cover;" alt="id_card" />
                                        </a>
                                    @else
                                        <a data-fancybox
                                            data-src="{{ !empty($identity->id_card) ? Storage::url($identity->id_card) : "https://dummyimage.com/600x400/000/bfbfbf&text=silahkan+tambahkan+id+card" }}"
                                            data-caption="ID Card User">
                                            <img src="{{ !empty($identity->id_card) ? Storage::url($identity->id_card) : "https://dummyimage.com/600x400/000/bfbfbf&text=silahkan+tambahkan+id+card" }}"
                                                class="img rounded-4" style="object-fit: cover;" width="100%"
                                                height="280px" alt="id_card" />
                                        </a>
                                    @endif

                                </div>
                                <div class="col-12 mb-3">
                                    <label for="address" class="form-label">Alamat Lengkap</label>
                                    <textarea class="form-control" name="address" wire:model='address' id="address" rows="3">{{ $address }}</textarea>
                                    @error("address")
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">Update Data</button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>

        </div>
    @endvolt
</x-guest-layout>
