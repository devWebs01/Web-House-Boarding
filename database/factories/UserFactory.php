<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'role' => fake()->randomElement([
                'guest',
                'admin',
                'owner',
            ]),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            if (in_array($user->role, ['owner', 'guest'])) {
                // Generate random Unsplash photo
                $unsplashUrl = 'https://source.unsplash.com/600x400/?id-card,document';
                $filename = 'id_cards/'.Str::uuid().'.jpg';
                $imageContents = @file_get_contents($unsplashUrl);

                // Fallback dummy image if Unsplash fails
                if (empty($imageContents)) {
                    $dummyUrl = 'https://dummyimage.com/600x400/000/bfbfbf&text=id+card';
                    try {
                        $imageContents = @file_get_contents($dummyUrl);
                    } catch (\Exception $e) {
                        $imageContents = null;
                    }
                }

                // Simpan ke storage/public/id_cards
                $imagePath = null;
                if (! empty($imageContents)) {
                    Storage::disk('public')->put($filename, $imageContents);
                    $imagePath = $filename;
                }

                // Buat data identity
                $user->identity()->create([
                    'phone_number' => '085951572182',
                    'whatsapp_number' => '085951572182',
                    'id_card' => $imagePath ?? 'default.png',
                    'address' => fake()->address(),
                ]);

            }
            \Log::info("User {$user->name} dibuat sebagai {$user->role}");
        });
    }
}
