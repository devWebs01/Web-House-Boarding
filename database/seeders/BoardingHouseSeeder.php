<?php

namespace Database\Seeders;

use App\Models\BoardingHouse;
use App\Models\Facility;
use App\Models\Gallery;
use App\Models\Regulation;
use App\Models\Room;
use App\Models\User;
use Exception;
use Illuminate\Database\Seeder;
use Spatie\ImageOptimizer\OptimizerChainFactory;

class BoardingHouseSeeder extends Seeder
{
    public function run()
    {
        $this->seedUsers();
        $this->seedBoardingHouses();
    }

    protected function seedUsers(): void
    {
        try {
            // Pastikan ada minimal 5 owner dengan identity
            $ownersWithIdentity = User::where('role', 'owner')->whereHas('identity')->count();
            $needed = max(0, 5 - $ownersWithIdentity);

            if ($needed > 0) {
                User::factory()->count($needed)->create(['role' => 'owner']);
                $this->command->info("✓ {$needed} Owner dengan identity berhasil dibuat.");
            } else {
                $this->command->info('! Sudah ada '.User::where('role', 'owner')->whereHas('identity')->count().' Owner dengan identity.');
            }

            // Pastikan ada beberapa guest juga
            $guestsWithIdentity = User::where('role', 'guest')->whereHas('identity')->count();
            if ($guestsWithIdentity < 3) {
                User::factory()->count(3 - $guestsWithIdentity)->create(['role' => 'guest']);
                $this->command->info('✓ Guest dengan identity berhasil dibuat.');
            }
        } catch (Exception $e) {
            $this->command->error('✗ Gagal membuat User: '.$e->getMessage());
        }
    }

    protected function seedBoardingHouses(): void
    {
        try {
            BoardingHouse::factory()
                ->count(10)
                ->create()
                ->each(function ($boardingHouse) {
                    $this->command->info("✓ BoardingHouse dibuat: [ID={$boardingHouse->id}] {$boardingHouse->name}");

                    $this->seedRooms($boardingHouse->id);
                    $this->seedFacilities($boardingHouse->id);
                    $this->seedRegulations($boardingHouse->id);
                    $this->seedGalleries($boardingHouse->id);
                });
        } catch (Exception $e) {
            $this->command->error('✗ Gagal membuat BoardingHouse: '.$e->getMessage());
        }
    }

    protected function seedRooms(int $boardingHouseId): void
    {
        try {
            $roomCount = rand(5, 10);
            Room::factory()->count($roomCount)->create([
                'boarding_house_id' => $boardingHouseId,
            ]);
            $this->command->info("✓ {$roomCount} Room dibuat untuk BoardingHouse ID={$boardingHouseId}.");
        } catch (Exception $e) {
            $this->command->error('✗ Gagal membuat Room: '.$e->getMessage());
        }
    }

    protected function seedFacilities(int $boardingHouseId): void
    {
        try {
            $names = [
                'Wi-Fi Gratis',
                'AC',
                'Kamar Mandi Dalam',
                'Dapur Bersama',
                'Laundry',
                'Parkir Motor',
                'Keamanan 24 Jam',
                'CCTV',
                'Air Panas',
                'TV Kabel',
                'Kulkas',
            ];
            shuffle($names);
            $selected = array_slice($names, 0, rand(3, 6));

            foreach ($selected as $name) {
                Facility::factory()->create([
                    'boarding_house_id' => $boardingHouseId,
                    'name' => $name,
                ]);
            }

            $this->command->info('✓ '.count($selected)." Facility dibuat untuk BoardingHouse ID={$boardingHouseId}.");
        } catch (Exception $e) {
            $this->command->error('✗ Gagal membuat Facility: '.$e->getMessage());
        }
    }

    protected function seedRegulations(int $boardingHouseId): void
    {
        try {
            $rules = [
                '5-orang-per-kamar',
                'maks-1-orang-per-kamar',
                'maks-2-orang-per-kamar',
                'maks-3-orang-per-kamar',
                'maks-4-orang-per-kamar',
                'akses-24-jam',
                'boleh-bawa-anak',
                'boleh-pasutri',
                'dilarang-merokok-di-kamar',
                'dilarang-bawa-hewan',
                'dilarang-menerima-tamu',
                'check-in-14-21',
                'check-out-maks-12',
                'tanpa-deposit',
                'termasuk-listrik',
                'khusus-mahasiswa',
                'khusus-karyawan',
                'swab-negatif-check-in',
                'pasutri-bawa-surat-nikah',
                'kriteria-umum',
                'wajib-ikut-piket',
            ];

            shuffle($rules);
            $selected = array_slice($rules, 0, rand(3, 7));

            foreach ($selected as $rule) {
                Regulation::factory()->create([
                    'boarding_house_id' => $boardingHouseId,
                    'rule' => $rule,
                ]);
            }

            $this->command->info('✓ '.count($selected)." Regulation dibuat untuk BoardingHouse ID={$boardingHouseId}.");
        } catch (Exception $e) {
            $this->command->error('✗ Gagal membuat Regulation: '.$e->getMessage());
        }
    }

    protected function seedGalleries(int $boardingHouseId): void
    {
        try {
            $galleryCount = rand(4, 6);
            Gallery::factory()
                ->count($galleryCount)
                ->create([
                    'boarding_house_id' => $boardingHouseId,
                ])->each(function ($gallery) {
                    if (! empty($gallery->image)) {
                        $this->optimizeImage($gallery->image);
                    }
                });

            $this->command->info("✓ {$galleryCount} Gallery dibuat untuk BoardingHouse ID={$boardingHouseId}.");
        } catch (Exception $e) {
            $this->command->error('✗ Gagal membuat Gallery: '.$e->getMessage());
        }
    }

    protected function optimizeImage(string $relativePath): void
    {
        $fullPath = storage_path('app/public/'.ltrim($relativePath, '/'));

        if (file_exists($fullPath)) {
            $optimizer = OptimizerChainFactory::create();
            $optimizer->optimize($fullPath);
        }
    }
}
