<?php

namespace Database\Seeders;

use App\Models\WebsiteSystem;
use Illuminate\Database\Seeder;

class WebsiteSystemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        WebsiteSystem::create([
            'name' => 'E-Kos',
            'facebook' => 'https://facebook.com/e.kos.fake',
            'twitter' => 'https://twitter.com/e_kos_fake',
            'instagram' => 'https://instagram.com/ekos.fake',
            'phone_number' => '085951572182',
            'whatsapp_number' => '085951572182',

        ]);
    }
}
