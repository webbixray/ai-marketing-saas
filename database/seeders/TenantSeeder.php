<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Tenant;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Tenant::firstOrCreate(
            ['domain' => 'acme.test'],
            ['name' => 'Acme Corp']
        );

        Tenant::firstOrCreate(
            ['domain' => 'beta.test'],
            ['name' => 'Beta LLC']
        );
    }
}
