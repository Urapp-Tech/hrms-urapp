<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Utility;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Request;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        Artisan::call('module:migrate LandingPage');
        Artisan::call('module:seed LandingPage');
        if (Request::route()->getName() != 'LaravelUpdater::database') {
            $this->call(UsersTableSeeder::class);
            $this->call(PlansTableSeeder::class);
            $this->call(NotificationSeeder::class);
            $this->call(AiTemplateSeeder::class);
        } else {
            Utility::languagecreate();
        }
    }
}
