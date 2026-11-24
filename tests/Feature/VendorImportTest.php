<?php

namespace Tests\Feature;

use App\Models\Vendor;
use App\Models\VendorImport;
use App\Services\VendorDirectory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\VendorTestCase;

class VendorImportTest extends VendorTestCase
{
    public function test_a_failure_mid_commit_rolls_back_all_vendors_rows_and_audits(): void
    {
        $this->actingAs($this->account());
        $batch = $this->upload([$this->row(), $this->row('Second Vendor', 'second@example.test')]);
        $this->map($batch);
        $real = new VendorDirectory;
        $calls = 0;
        $this->partialMock(VendorDirectory::class, function ($mock) use ($real, &$calls) {
            $mock->shouldReceive('save')->andReturnUsing(function (array $data) use ($real, &$calls) {
                if (++$calls === 2) {
                    throw new \RuntimeException('Simulated storage failure');
                }

                return $real->save($data);
            });
        });
        $this->withoutExceptionHandling();
        try {
            $this->post('/vendor-imports/'.$batch->id.'/commit', ['version' => $batch->version]);
            $this->fail('The injected storage failure should be raised.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated storage failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('vendors', 0);
        $this->assertDatabaseCount('vendor_contacts', 0);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'vendor.created']);
        $this->assertSame(0, $batch->rows()->whereNotNull('vendor_id')->count());
        $this->assertSame('review', $batch->fresh()->status);
    }

    public function test_blank_csv_rows_keep_original_row_numbers_and_need_review(): void
    {
        $this->actingAs($this->account());
        $batch = $this->upload([$this->row(), [], $this->row('Last Vendor', 'last@example.test')]);
        $this->map($batch);
        $this->assertSame('invalid', $batch->rows()->where('row_number', 3)->sole()->status);
        $this->assertSame('Last Vendor', $batch->rows()->where('row_number', 4)->sole()->data['company_name']);
    }

    private function csv(array $rows, ?array $headers = null): UploadedFile
    {
        $headers ??= ['company_name', 'primary_contact', 'primary_email', 'phone', 'coverage', 'document_types'];
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, $headers, escape: '');
        foreach ($rows as $row) {
            fputcsv($stream, $row, escape: '');
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        return UploadedFile::fake()->createWithContent('vendors.csv', $contents);
    }

    private function row(string $name = 'Imported Vendor', string $email = 'import@example.test'): array
    {
        return [$name, 'Test Person', $email, '+1 202 555 0100', 'CA:Los Angeles County', 'Deed'];
    }

    private function upload(array $rows): VendorImport
    {
        $this->post('/vendor-imports', ['file' => $this->csv($rows)])->assertRedirect();

        return VendorImport::latest('id')->firstOrFail();
    }

    private function map(VendorImport $batch): void
    {
        $mapping = array_flip($batch->headers);
        $this->post('/vendor-imports/'.$batch->id.'/mapping', ['version' => $batch->version, 'mapping' => $mapping])->assertRedirect();
        $batch->refresh();
    }

    public function test_review_correction_rejection_and_commit_are_safe_and_single_use(): void
    {
        Storage::fake('local');
        $this->actingAs($this->account());
        $bad = $this->row('Fix Me', 'not-an-email');
        $batch = $this->upload([$this->row(), $bad, $this->row('Duplicate', 'import@example.test')]);
        $this->assertCount(0, Storage::disk('local')->allFiles());
        $this->get('/vendor-imports/'.$batch->id)->assertOk()->assertSee('Match spreadsheet columns');
        $this->assertDatabaseCount('vendors', 0);
        $this->map($batch);
        $this->assertSame(2, $batch->rows()->where('status', 'invalid')->count());
        $this->get('/vendor-imports/'.$batch->id)->assertOk()->assertSee('Duplicate of spreadsheet row');
        $this->post('/vendor-imports/'.$batch->id.'/commit', ['version' => $batch->version])->assertSessionHas('import_error');
        $this->assertDatabaseCount('vendors', 0);
        $batch->refresh();
        $fix = $batch->rows()->where('row_number', 3)->first();
        $this->get('/vendor-imports/'.$batch->id.'/rows/'.$fix->id.'/edit')->assertOk();
        $this->patch('/vendor-imports/'.$batch->id.'/rows/'.$fix->id, $this->payload(['company_name' => 'Fixed Vendor', 'primary_email' => 'fixed@example.test', 'version' => $batch->version, 'action' => 'correct']))->assertRedirect();
        $batch->refresh();
        $duplicate = $batch->rows()->where('row_number', 4)->first();
        $this->patch('/vendor-imports/'.$batch->id.'/rows/'.$duplicate->id, ['version' => $batch->version, 'action' => 'reject'])->assertRedirect();
        $batch->refresh();
        $this->post('/vendor-imports/'.$batch->id.'/commit', ['version' => $batch->version])->assertSessionHas('status');
        $this->assertDatabaseCount('vendors', 2);
        $this->assertSame(0, Vendor::where('is_active', true)->count());
        $this->assertSame(2, $batch->fresh()->imported_count);
        $this->assertSame(1, $batch->fresh()->rejected_count);
        $this->get('/vendor-imports/'.$batch->id)->assertOk()->assertSee('IMPORT COMPLETE');
        $this->post('/vendor-imports/'.$batch->id.'/commit', ['version' => $batch->fresh()->version])->assertSessionHasErrors('import');
        $this->assertDatabaseCount('vendors', 2);
    }

    public function test_database_duplicates_added_after_review_block_the_entire_commit(): void
    {
        $this->actingAs($this->account());
        $batch = $this->upload([$this->row(), $this->row('Second Import', 'second@example.test')]);
        $this->map($batch);
        $this->vendor(['company_name' => 'Conflicting Vendor', 'primary_email' => 'second@example.test']);
        $this->post('/vendor-imports/'.$batch->id.'/commit', ['version' => $batch->version])->assertSessionHas('import_error');
        $this->assertDatabaseCount('vendors', 1);
        $this->assertSame('invalid', $batch->rows()->where('row_number', 3)->first()->status);
        $this->assertNull($batch->fresh()->committed_at);
    }

    public function test_imports_are_private_to_owner_and_admin_and_coo_cannot_upload(): void
    {
        $this->actingAs($this->account());
        $batch = $this->upload([$this->row()]);
        $this->actingAs($this->account());
        $this->get('/vendor-imports/'.$batch->id)->assertForbidden();
        $this->post('/vendor-imports/'.$batch->id.'/mapping', [])->assertForbidden();
        $this->get('/vendor-imports')->assertOk()->assertDontSee('Open #'.$batch->id);
        $this->actingAs($this->account('admin'))->get('/vendor-imports/'.$batch->id)->assertOk();
        $this->actingAs($this->account('coo'))->post('/vendor-imports', ['file' => $this->csv([$this->row()])])->assertForbidden();
    }

    public function test_mapping_requires_required_columns_and_unique_assignments(): void
    {
        $this->actingAs($this->account());
        $batch = $this->upload([$this->row()]);
        $this->post('/vendor-imports/'.$batch->id.'/mapping', ['version' => 1, 'mapping' => ['company_name' => 0]])->assertSessionHasErrors('mapping');
        $mapping = array_flip($batch->headers);
        $mapping['primary_email'] = 0;
        $this->post('/vendor-imports/'.$batch->id.'/mapping', ['version' => 1, 'mapping' => $mapping])->assertSessionHasErrors('mapping');
        $this->map($batch);
        $this->post('/vendor-imports/'.$batch->id.'/mapping', ['version' => $batch->version, 'mapping' => array_flip($batch->headers)])->assertSessionHasErrors('mapping');
    }

    public function test_stale_import_revision_and_cross_batch_row_updates_are_rejected(): void
    {
        $this->actingAs($this->account());
        $batch = $this->upload([$this->row()]);
        $this->map($batch);
        $other = $this->upload([$this->row('Other', 'other@example.test')]);
        $row = $other->rows()->first();
        $this->patch('/vendor-imports/'.$batch->id.'/rows/'.$row->id, ['version' => $batch->version, 'action' => 'reject'])->assertNotFound();
        $this->post('/vendor-imports/'.$batch->id.'/commit', ['version' => 1])->assertSessionHasErrors('version');
        $this->assertDatabaseCount('vendors', 0);
    }

    public function test_cancel_and_expiry_remove_rows_and_prevent_commit(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('vendor-imports/stale.upload', 'old temporary data');
        Storage::disk('local')->put('vendor-imports/current.upload', 'active upload');
        touch(Storage::disk('local')->path('vendor-imports/stale.upload'), now()->subDays(2)->timestamp);
        $this->actingAs($this->account());
        $batch = $this->upload([$this->row()]);
        $this->post('/vendor-imports/'.$batch->id.'/cancel', ['version' => 1])->assertRedirect();
        $this->assertSame(0, $batch->rows()->count());
        $this->post('/vendor-imports/'.$batch->id.'/commit', ['version' => $batch->fresh()->version])->assertSessionHasErrors('import');
        $expired = $this->upload([$this->row()]);
        $this->map($expired);
        $expired->update(['expires_at' => now()->subMinute()]);
        $this->post('/vendor-imports/'.$expired->id.'/commit', ['version' => $expired->version])->assertSessionHasErrors('import');
        $this->artisan('vmas:prune-imports')->assertSuccessful();
        $this->assertSame(0, $expired->rows()->count());
        $this->assertSame('expired', $expired->fresh()->status);
        Storage::disk('local')->assertMissing('vendor-imports/stale.upload');
        Storage::disk('local')->assertExists('vendor-imports/current.upload');
    }

    public function test_malformed_oversized_and_formula_files_are_rejected_and_removed(): void
    {
        Storage::fake('local');
        $this->actingAs($this->account());
        foreach ([
            UploadedFile::fake()->createWithContent('bad.xlsx', 'not a workbook'),
            UploadedFile::fake()->createWithContent('old.xls', 'legacy'),
            UploadedFile::fake()->create('large.csv', 5121),
            $this->csv([$this->row()], ['name', 'name', 'email', 'phone', 'coverage', 'documents']),
            $this->csv([$this->row('=HYPERLINK("https://example.test")')]),
            $this->csv(array_fill(0, 1001, $this->row())),
        ] as $file) {
            $this->post('/vendor-imports', ['file' => $file])->assertSessionHasErrors('file');
        }
        $this->assertDatabaseCount('vendor_imports', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_xlsx_values_import_and_formulas_are_rejected(): void
    {
        $this->actingAs($this->account());
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->fromArray([['company_name', 'primary_contact', 'primary_email', 'phone', 'coverage', 'document_types'], $this->row()]);
        $path = tempnam(sys_get_temp_dir(), 'vmas-test-');
        try {
            (new Xlsx($book))->save($path);
            $this->post('/vendor-imports', ['file' => UploadedFile::fake()->createWithContent('vendors.xlsx', file_get_contents($path))])->assertRedirect();
            $batch = VendorImport::sole();
            $this->map($batch);
            $this->assertSame('valid', $batch->rows()->sole()->status);
            $sheet->setCellValue('A2', '=1+1');
            $writer = new Xlsx($book);
            $writer->setPreCalculateFormulas(false);
            $writer->save($path);
            $this->post('/vendor-imports', ['file' => UploadedFile::fake()->createWithContent('formula.xlsx', file_get_contents($path))])->assertSessionHasErrors('file');
        } finally {
            $book->disconnectWorksheets();
            unlink($path);
        }
    }

    public function test_downloaded_csv_template_can_be_uploaded_and_mapped(): void
    {
        $this->actingAs($this->account());
        $csv = $this->get('/vendor-imports/template?format=csv')->assertOk()->streamedContent();
        $this->post('/vendor-imports', ['file' => UploadedFile::fake()->createWithContent('template.csv', $csv)])->assertRedirect();
        $batch = VendorImport::sole();
        $this->map($batch);
        $this->assertSame('valid', $batch->rows()->sole()->status);
    }

    public function test_reject_and_restore_recalculates_in_batch_duplicate_errors(): void
    {
        $this->actingAs($this->account());
        $batch = $this->upload([$this->row(), $this->row()]);
        $this->map($batch);
        $first = $batch->rows()->first();
        $this->patch('/vendor-imports/'.$batch->id.'/rows/'.$first->id, ['version' => $batch->version, 'action' => 'reject'])->assertRedirect();
        $this->assertSame('valid', $batch->rows()->where('row_number', 3)->first()->status);
        $this->patch('/vendor-imports/'.$batch->id.'/rows/'.$first->id, ['version' => $batch->fresh()->version, 'action' => 'restore'])->assertRedirect();
        $this->assertSame('invalid', $batch->rows()->where('row_number', 3)->first()->status);
    }
}
