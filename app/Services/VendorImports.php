<?php

namespace App\Services;

use App\Models\VendorImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendorImports
{
    public function __construct(private VendorDirectory $directory) {}

    public function locked(VendorImport $batch, int $version, callable $operation): mixed
    {
        return DB::transaction(function () use ($batch, $version, $operation) {
            $batch = VendorImport::lockForUpdate()->findOrFail($batch->id);
            if (in_array($batch->status, ['committed', 'cancelled', 'expired'], true) || $batch->expires_at->isPast()) {
                throw ValidationException::withMessages(['import' => 'This import is completed or expired. Start a new upload if needed.']);
            }
            if ($batch->version !== $version) {
                throw ValidationException::withMessages(['version' => 'This import changed in another tab. Reload the review before continuing.']);
            }
            $result = $operation($batch);
            $batch->increment('version');

            return $result;
        });
    }

    public function review(VendorImport $batch): void
    {
        $seenNames = [];
        $seenEmails = [];
        foreach ($batch->rows()->get() as $row) {
            if ($row->status === 'rejected') {
                continue;
            }
            $errors = [];
            try {
                $this->directory->validate($row->data ?? []);
            } catch (ValidationException $exception) {
                $errors = $exception->errors();
            }
            $data = $this->directory->normalize($row->data ?? []);
            foreach (['company_name' => &$seenNames, 'primary_email' => &$seenEmails] as $field => &$seen) {
                $key = is_string($data[$field]) ? mb_strtolower($data[$field]) : '';
                if ($key !== '' && isset($seen[$key])) {
                    $errors[$field][] = 'Duplicate of spreadsheet row '.$seen[$key].'. Correct or reject this row.';
                } elseif ($key !== '') {
                    $seen[$key] = $row->row_number;
                }
            }
            unset($seen);
            $row->update(['errors' => $errors, 'status' => empty($errors) ? 'valid' : 'invalid']);
        }
        $batch->update(['status' => 'review']);
    }

    public function commit(VendorImport $batch): bool
    {
        $this->review($batch);
        if ($batch->rows()->where('status', 'invalid')->exists()) {
            return false;
        }
        $rows = $batch->rows()->where('status', 'valid')->get();
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['import' => 'There are no valid rows to import.']);
        }
        foreach ($rows as $row) {
            $vendor = $this->directory->save($row->data, reason: 'Reviewed spreadsheet import #'.$batch->id.', row '.$row->row_number);
            $row->update(['vendor_id' => $vendor->id, 'status' => 'imported']);
        }
        $rejected = $batch->rows()->where('status', 'rejected')->count();
        $batch->update(['status' => 'committed', 'committed_at' => now(), 'imported_count' => $rows->count(), 'rejected_count' => $rejected]);
        Audit::record('vendor.import_committed', 'vendor_import', $batch->id, after: ['imported' => $rows->count(), 'rejected' => $rejected]);

        return true;
    }
}
