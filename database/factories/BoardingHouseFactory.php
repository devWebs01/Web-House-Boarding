<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BoardingHouse>
 */
class BoardingHouseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = ['male', 'female', 'mixed'];
        $statuses = ['pending', 'verified', 'rejected'];

        // 1. Siapkan daftar URL fallback
        $seed = Str::random(8);
        $providers = [
            // Picsum (random seed)
            "https://picsum.photos/seed/{$seed}/640/480",
            // Unsplash random
            'https://source.unsplash.com/random/640x480',
            // Placeholder.com dengan teks
            'https://via.placeholder.com/640x480.jpg?text=Kos+Image',
        ];

        $imageContents = null;
        $usedUrl = null;

        // 2. Coba satu per satu sampai ok
        foreach ($providers as $url) {
            try {
                $response = Http::timeout(5)->get($url);
                if ($response->ok()) {
                    $imageContents = $response->body();
                    $usedUrl = $url;
                    break;
                }
            } catch (\Exception $e) {
                // silent, lanjut ke provider berikutnya
            }
        }

        // 3. Simpan ke storage
        $filename = Str::random(12).'.jpg';
        $disk = Storage::disk('public');
        $dir = 'thumbnails';

        if (! $disk->exists($dir)) {
            $disk->makeDirectory($dir);
        }

        if ($imageContents) {
            $disk->put("{$dir}/{$filename}", $imageContents);
            $thumbnailPath = "{$dir}/{$filename}";
        } else {
            // fallback lokal jika semua provider gagal
            $thumbnailPath = "{$dir}/default.jpg";
        }

        return [
            'name' => $this->faker->company.' Kos',
            'location_map' => 'https://goo.gl/maps/'.$this->faker->regexify('[A-Za-z0-9]{8}'),
            'address' => $this->faker->address,
            'owner_id' => User::whereHas('identity')->inRandomOrder()->first()->id ?? User::factory()->create()->id,
            'thumbnail' => $thumbnailPath,
            'category' => $this->faker->randomElement($categories),
            'verification_status' => $this->faker->randomElement($statuses),
            'minimum_rental_period' => $this->faker->randomElement(['1', '3', '6', '12']),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
