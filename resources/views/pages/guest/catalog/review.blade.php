<?php

use App\Models\{Comment};
use function Livewire\Volt\{state};

use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;

state(['boardingHouse', 'body' => '', 'rating' => '']);

$comment = function () {
    if (!Auth::check()) {
        return Redirect::route('login');
    }

    if (!Auth()->User()->identity) {
        return Redirect::route('profile.guest');
    }

    if (in_array(Auth()->user()->role, ['owner', 'admin'])) {
        return Redirect::route('home');
    }

    $validatedComment = $this->validate([
        'body' => 'required|string|min:5',
        'rating' => 'required|in:1,2,3,4,5',
    ]);

    // Check if user has already commented on this boarding house
    if (
        Comment::where('user_id', auth()->id())
            ->where('boarding_house_id', $this->boardingHouse->id)
            ->exists()
    ) {
        $this->addError('body', 'Anda sudah memberikan komentar untuk kos ini.');
        return;
    }

    $validatedComment['user_id'] = auth()->user()->id;
    $validatedComment['boarding_house_id'] = $this->boardingHouse->id;

    try {
        Comment::create($validatedComment);

        LivewireAlert::title('Proses Berhasil!')->position('center')->success()->toast()->show();

        return Redirect::route('catalog.show', ['boardingHouse' => $this->boardingHouse]);
    } catch (\Throwable $th) {
        LivewireAlert::title('Proses Gagal!')->position('center')->error()->toast()->show();

        return Redirect::route('catalog.show', ['boardingHouse' => $this->boardingHouse]);
    }
};

?>

@volt
    <div>
        <section id="review" class="my-5">
            <h2 class="section-title">Review Pengguna</h2>
            <div class="card card-body border-0 shadow-sm">
                <div class="d-flex align-items-center mb-3">
                    <h3 class="fw-bold mb-0 me-3">4.8</h3>
                    <div class="review-stars fs-4">
                        <i class="bi bi-star-fill">

                        </i>
                        <i class="bi bi-star-fill">

                        </i>
                        <i class="bi bi-star-fill">

                        </i>
                        <i class="bi bi-star-fill">

                        </i>
                        <i class="bi bi-star-half">

                        </i>
                    </div>
                    <span class="ms-3 text-muted">(dari 25 review)</span>
                </div>
                <hr>

                <div class="review-list">
                    @foreach ($boardingHouse->comments->where('status', true) as $comment)
                        <div class="d-flex mb-3">
                            <img src="{{ 'https://api.dicebear.com/9.x/adventurer/svg?seed=' . ($comment->user->name ?? 'Mason') }}"
                                alt="User" class="rounded-circle me-3" style="width: 50px; height: 50px;">
                            <div>
                                <h6 class="fw-bold mb-0">{{ $comment->user->name }}</h6>
                                <div class="review-stars small">
                                    @for ($i = 0; $i < $comment->rating; $i++)
                                        <i class="bi bi-star-fill">
                                        </i>
                                    @endfor

                                </div>
                                <p class="mt-1">{{ $comment->body }}</p>
                            </div>
                        </div>
                    @endforeach

                </div>

                <div class="add-review mt-4">
                    <h5 class="fw-bold">Tulis Review Anda</h5>
                    <form wire:submit='comment'>
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Rating</label>
                            <select class="form-select"wire:model="rating" aria-label="Default select example">
                                <option value=" " selected>Pilih rating bintang</option>
                                <option value="5">★★★★★ (Luar Biasa)</option>
                                <option value="4">★★★★☆ (Baik)</option>
                                <option value="3">★★★☆☆ (Cukup)</option>
                                <option value="2">★★☆☆☆ (Kurang)</option>
                                <option value="1">★☆☆☆☆ (Buruk)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="body" class="form-label">Komentar Anda</label>
                            <textarea class="form-control" id="body" wire:model='body' rows="3"
                                placeholder="Bagikan pengalaman Anda menginap di sini...">
                    </textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Kirim Review</button>
                    </form>
                </div>
            </div>
        </section>
    </div>
@endvolt
