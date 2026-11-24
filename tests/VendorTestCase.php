<?php

namespace Tests;

use App\Models\County;
use App\Models\DocumentType;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Services\VendorDirectory;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class VendorTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessSeeder::class);
        $this->withoutVite();
        config(['vmas.demo_mode' => false]);
        foreach ([['06037', 'CA', 'Los Angeles County'], ['06059', 'CA', 'Orange County'], ['48453', 'TX', 'Travis County']] as [$fips,$state,$name]) {
            County::create(compact('fips', 'state', 'name'));
        }
        foreach (['Deed', 'Mortgage', 'Lien'] as $name) {
            DocumentType::create(compact('name'));
        }
    }

    protected function account(string $role = 'employee'): User
    {
        return User::factory()->create(['role_id' => Role::where('name', $role)->value('id'), 'is_active' => true, 'must_change_password' => false, 'two_factor_confirmed_at' => $role === 'admin' ? now() : null]);
    }

    protected function payload(array $overrides = []): array
    {
        return array_replace([
            'company_name' => 'Example Records', 'primary_contact' => 'Example Person', 'primary_email' => 'records@example.test',
            'phone' => '+1 202 555 0101', 'coverage' => 'CA:Los Angeles County | TX:Travis County', 'document_types' => 'Deed | Mortgage',
            'working_days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'], 'working_start' => '09:00', 'working_end' => '17:00',
            'time_zone' => 'America/Los_Angeles', 'standard_charge' => '40.25', 'travel_charge' => '0.00',
            'alternate_name' => 'Second Person', 'alternate_email' => 'backup@example.test', 'alternate_phone' => '+1 202 555 0102',
        ], $overrides);
    }

    protected function vendor(array $overrides = []): Vendor
    {
        return app(VendorDirectory::class)->save($this->payload($overrides));
    }
}
