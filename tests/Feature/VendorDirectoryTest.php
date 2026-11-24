<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\County;
use App\Models\DocumentType;
use App\Models\Vendor;
use Database\Seeders\VendorReferenceSeeder;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\VendorTestCase;

class VendorDirectoryTest extends VendorTestCase
{
    public function test_directory_requires_login_and_management_has_read_only_access(): void
    {
        $vendor = $this->vendor();
        $this->get('/vendors')->assertRedirect('/login');
        $this->actingAs($this->account('coo'));
        $this->get('/vendors')->assertOk()->assertSee('Example Records')->assertDontSee('Add vendor');
        $this->get('/vendors/'.$vendor->id)->assertOk()->assertDontSee('Edit vendor');
        $this->get('/vendors/create')->assertForbidden();
        $this->get('/vendors/'.$vendor->id.'/edit')->assertForbidden();
        $this->get('/vendor-imports')->assertForbidden();
        $this->post('/vendors', $this->payload())->assertForbidden();
        $this->put('/vendors/'.$vendor->id, $this->payload())->assertForbidden();
        $this->get('/vendors/export?format=csv')->assertOk();
    }

    public function test_employee_creates_a_complete_inactive_vendor_with_history(): void
    {
        $user = $this->account();
        $this->actingAs($user)->post('/vendors', $this->payload(['is_active' => true, 'is_demo' => true, 'contacts' => [['name' => 'Third Person', 'email' => 'third@example.test']]]))->assertRedirect();
        $vendor = Vendor::sole();
        $this->assertFalse($vendor->is_active);
        $this->assertFalse($vendor->is_demo);
        $this->assertSame('40.25', $vendor->standard_charge);
        $this->assertCount(3, $vendor->contacts);
        $this->assertCount(2, $vendor->counties);
        $this->assertCount(2, $vendor->documentTypes);
        $this->assertSame($user->id, $vendor->created_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'vendor.created', 'entity_id' => $vendor->id, 'user_id' => $user->id]);
        $this->get('/vendors/'.$vendor->id)->assertOk()->assertSee('Third Person')->assertSee('Request performance');
        $this->get('/vendors/'.$vendor->id.'/edit')->assertOk()->assertSee('Third Person');
    }

    public function test_duplicate_name_and_email_are_rejected_without_partial_records(): void
    {
        $this->vendor();
        $this->actingAs($this->account());
        $this->post('/vendors', $this->payload(['company_name' => '  EXAMPLE   Records ', 'primary_email' => 'new@example.test']))->assertSessionHasErrors('company_name');
        $this->post('/vendors', $this->payload(['company_name' => 'Different Company', 'primary_email' => 'RECORDS@EXAMPLE.TEST']))->assertSessionHasErrors('primary_email');
        $this->assertDatabaseCount('vendors', 1);
        $this->assertDatabaseCount('vendor_contacts', 2);
    }

    public function test_invalid_fields_and_unknown_coverage_are_rejected(): void
    {
        $this->actingAs($this->account())->post('/vendors', $this->payload([
            'coverage' => 'XX:Missing', 'document_types' => 'Invented Document', 'time_zone' => 'Mars/Crater', 'standard_charge' => '-1',
            'travel_charge' => '1.234', 'working_days' => ['Funday'], 'phone' => 'hello', 'contacts' => [['email' => 'invalid']],
        ]))->assertSessionHasErrors(['coverage', 'document_types', 'time_zone', 'standard_charge', 'travel_charge', 'working_days.0', 'phone', 'contacts.0.name']);
        $this->assertDatabaseCount('vendors', 0);
    }

    public function test_edit_changes_relationships_and_cannot_overwrite_a_newer_revision(): void
    {
        $vendor = $this->vendor();
        $this->actingAs($this->account());
        $input = $this->payload(['coverage' => 'CA:Orange County', 'document_types' => 'Lien', 'version' => 1, 'reason' => 'Coverage revised', 'is_active' => true, 'alternate_name' => '', 'alternate_email' => '', 'alternate_phone' => '']);
        $this->put('/vendors/'.$vendor->id, $input)->assertRedirect();
        $vendor->refresh();
        $this->assertSame(2, $vendor->version);
        $this->assertFalse($vendor->is_active);
        $this->assertCount(1, $vendor->contacts);
        $this->assertSame('Orange County', $vendor->counties->sole()->name);
        $this->assertSame('Lien', $vendor->documentTypes->sole()->name);
        $event = AuditLog::where('action', 'vendor.updated')->sole();
        $this->assertSame('Coverage revised', $event->reason);
        $this->assertCount(2, $event->before['counties']);
        $this->assertCount(1, $event->after['counties']);
        $this->assertSame($event->before['working_start'], $event->after['working_start']);
        $this->put('/vendors/'.$vendor->id, $input)->assertSessionHasErrors('version');
        $this->assertSame(2, $vendor->fresh()->version);
        $this->get('/vendors/'.$vendor->id.'/edit')->assertOk()->assertSee('name="version" value="1"', false);
    }

    public function test_only_admin_can_activate_and_activation_checks_revision_and_reason(): void
    {
        $vendor = $this->vendor();
        $this->actingAs($this->account())->patch('/vendors/'.$vendor->id.'/activation', ['version' => 1, 'is_active' => 1, 'reason' => 'Approved'])->assertForbidden();
        $this->actingAs($this->account('admin'));
        $this->patch('/vendors/'.$vendor->id.'/activation', ['version' => 1, 'is_active' => 1])->assertSessionHasErrors('reason');
        $this->patch('/vendors/'.$vendor->id.'/activation', ['version' => 1, 'is_active' => 1, 'reason' => 'Details checked'])->assertRedirect();
        $this->assertTrue($vendor->fresh()->is_active);
        $this->patch('/vendors/'.$vendor->id.'/activation', ['version' => 1, 'is_active' => 0, 'reason' => 'Old tab'])->assertSessionHasErrors('version');
        $this->patch('/vendors/'.$vendor->id.'/activation', ['version' => 2, 'is_active' => 0, 'reason' => 'Temporarily suspended'])->assertRedirect();
        $this->assertFalse($vendor->fresh()->is_active);
        $this->assertSame(2, AuditLog::where('action', 'vendor.activation_changed')->count());
    }

    public function test_state_county_document_and_status_filters_match_the_same_coverage(): void
    {
        $first = $this->vendor();
        $second = $this->vendor(['company_name' => 'Orange Partner', 'primary_email' => 'orange@example.test', 'coverage' => 'CA:Orange County', 'document_types' => 'Lien', 'availability' => 'limited']);
        $second->forceFill(['is_active' => true])->save();
        $this->actingAs($this->account());
        $this->get('/vendors?state=CA&county='.County::where('state', 'TX')->value('id'))->assertOk()->assertSee('0 vendors found');
        $this->get('/vendors?state=CA&document='.DocumentType::where('name', 'Lien')->value('id').'&active=1&availability=limited')->assertOk()->assertSee('Orange Partner')->assertDontSee('Example Records');
        $this->get('/vendors?q=Second+Person')->assertOk()->assertSee('Example Records');
        $this->get('/vendors?county=99999')->assertSessionHasErrors('county');
    }

    public function test_profile_escapes_vendor_content_and_requires_a_change_reason(): void
    {
        $vendor = $this->vendor(['remarks' => '<script>alert(1)</script>']);
        $this->actingAs($this->account())->get('/vendors/'.$vendor->id)->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
        $this->put('/vendors/'.$vendor->id, $this->payload(['version' => 1]))->assertSessionHasErrors('reason');
    }

    public function test_exports_escape_formulas_and_excel_preserves_literal_text(): void
    {
        $vendor = $this->vendor(['company_name' => '=HYPERLINK("https://example.test")']);
        $this->actingAs($this->account());
        $csv = $this->get('/vendors/export?format=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('additional_contacts_json', $csv);
        $xlsx = $this->get('/vendors/export?format=xlsx')->assertOk()->streamedContent();
        $path = tempnam(sys_get_temp_dir(), 'vmas-test-');
        try {
            file_put_contents($path, $xlsx);
            $book = IOFactory::load($path);
            $this->assertSame(DataType::TYPE_STRING, $book->getActiveSheet()->getCell('A2')->getDataType());
            $this->assertSame($vendor->company_name, $book->getActiveSheet()->getCell('A2')->getValue());
            $book->disconnectWorksheets();
        } finally {
            unlink($path);
        }
        $this->assertSame(2, AuditLog::where('action', 'vendor.exported')->count());
    }

    public function test_reference_seed_is_repeatable_and_contains_current_county_equivalents(): void
    {
        $this->seed(VendorReferenceSeeder::class);
        $count = County::count();
        $this->assertGreaterThan(3000, $count);
        $this->seed(VendorReferenceSeeder::class);
        $this->assertSame($count, County::count());
        $this->assertDatabaseHas('counties', ['state' => 'CT', 'name' => 'Capitol Planning Region']);
    }

    public function test_demo_mode_marks_new_test_records(): void
    {
        config(['vmas.demo_mode' => true]);
        $this->assertTrue($this->vendor()->is_demo);
    }
}
