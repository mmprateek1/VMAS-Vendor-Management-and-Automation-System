<?php

namespace Database\Seeders;

use App\Models\Vendor;
use App\Services\Audit;
use App\Services\VendorDirectory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoVendorSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || ! config('vmas.demo_mode')) {
            throw new \RuntimeException('Dummy vendors require APP_ENV=local and VMAS_DEMO_MODE=true.');
        }
        $examples = [
            ['Pacific Records Demo', 'Maya Chen', 'pacific', 'CA:Los Angeles County | CA:Orange County | CA:San Diego County', 'Deed | Mortgage | Release', 'America/Los_Angeles', 'available', 'high', '45.00', true],
            ['Lone Star Retrieval Demo', 'Alex Morgan', 'lonestar', 'TX:Travis County | TX:Williamson County | TX:Harris County', 'Deed | Deed of Trust | Lien', 'America/Chicago', 'available', 'high', '38.00', true],
            ['Suncoast Documents Demo', 'Jordan Rivera', 'suncoast', 'FL:Miami-Dade County | FL:Broward County', 'Deed | Mortgage | Tax Record', 'America/New_York', 'limited', 'normal', '42.50', true],
            ['Hudson County Search Demo', 'Taylor Brooks', 'hudson', 'NY:New York County | NY:Kings County | NJ:Hudson County', 'Court Record | Judgment | Lien', 'America/New_York', 'unavailable', 'normal', '55.00', true],
            ['Desert Records Demo', 'Casey Patel', 'desert', 'AZ:Maricopa County | AZ:Pima County', 'Deed | Assignment | Plat', 'America/Phoenix', 'available', 'normal', '35.00', false],
            ['Great Lakes Title Demo', 'Sam Wilson', 'greatlakes', 'IL:Cook County | IL:DuPage County', 'Deed | Mortgage | Release | Tax Record', 'America/Chicago', 'limited', 'low', '40.00', false],
        ];
        foreach ($examples as $index => [$company,$contact,$email,$coverage,$documents,$zone,$availability,$priority,$charge,$active]) {
            if (Vendor::where('primary_email', $email.'@vendors.example.test')->exists()) {
                continue;
            }
            DB::transaction(function () use ($company, $contact, $email, $coverage, $documents, $zone, $availability, $priority, $charge, $active, $index) {
                $vendor = app(VendorDirectory::class)->save([
                    'company_name' => $company, 'primary_contact' => $contact, 'primary_email' => $email.'@vendors.example.test',
                    'phone' => '+1 202 555 01'.str_pad($index, 2, '0', STR_PAD_LEFT), 'coverage' => $coverage, 'document_types' => $documents,
                    'time_zone' => $zone, 'availability' => $availability, 'priority' => $priority, 'standard_charge' => $charge, 'travel_charge' => '10.00',
                    'working_days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'], 'working_start' => '09:00', 'working_end' => '17:00', 'payment_terms' => 'Net 30 · dummy terms',
                    'alternate_name' => 'Demo Backup Contact', 'alternate_email' => 'backup.'.$email.'@vendors.example.test',
                    'remarks' => 'Fictional vendor for local acceptance testing only. Do not use for real assignments.',
                ], reason: 'User-authorized Phase 2 dummy data', demo: true);
                if ($active) {
                    $vendor->forceFill(['is_active' => true, 'version' => 2])->save();
                    Audit::record('vendor.activation_changed', 'vendor', $vendor->id, ['is_active' => false], ['is_active' => true], 'Dummy fixture activation for testing');
                }
            });
        }
    }
}
