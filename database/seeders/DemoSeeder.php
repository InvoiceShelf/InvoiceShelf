<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Setting;
use App\Models\User;
use App\Space\InstallUtils;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::whereIs('super admin')->first();

        $user->setSettings(['language' => 'en']);

        Address::create(['company_id' => $user->companies()->first()->id, 'country_id' => 1]);

        Setting::setSetting('profile_complete', 'COMPLETED');

        InstallUtils::createDbMarker();
    }
}
