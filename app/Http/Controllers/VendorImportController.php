<?php

namespace App\Http\Controllers;

use App\Models\County;
use App\Models\DocumentType;
use App\Models\VendorImport;
use App\Models\VendorImportRow;
use App\Services\Audit;
use App\Services\VendorDirectory;
use App\Services\VendorImports;
use App\Services\VendorSpreadsheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VendorImportController extends Controller
{
    public function __construct(private VendorImports $imports) {}

    private function authorizeBatch(VendorImport $import): void
    {
        abort_unless($import->user_id === auth()->id() || auth()->user()->hasPermission('vendors.activate'), 403);
    }

    public function index(): View
    {
        return view('vendors.imports.index', ['imports' => VendorImport::when(! auth()->user()->hasPermission('vendors.activate'), fn ($q) => $q->where('user_id', auth()->id()))->latest('id')->paginate(15)]);
    }

    public function store(Request $request, VendorSpreadsheet $spreadsheet): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:5120']]);
        $file = $request->file('file');
        $path = $file->storeAs('vendor-imports', Str::uuid().'.upload', 'local');
        try {
            [$headers, $rows] = $spreadsheet->read(new UploadedFile(Storage::disk('local')->path($path), $file->getClientOriginalName(), test: true));
            $batch = DB::transaction(function () use ($file, $headers, $rows) {
                $batch = VendorImport::create([
                    'user_id' => auth()->id(), 'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
                    'headers' => $headers, 'expires_at' => now()->addDays(7),
                ]);
                foreach ($rows as $index => $raw) {
                    $batch->rows()->create(['row_number' => $index + 2, 'raw' => $raw]);
                }
                Audit::record('vendor.import_uploaded', 'vendor_import', $batch->id, after: ['rows' => count($rows)]);

                return $batch;
            });
        } finally {
            Storage::disk('local')->delete($path);
        }

        return redirect()->route('vendor-imports.show', $batch);
    }

    public function show(VendorImport $import): View
    {
        $this->authorizeBatch($import);

        return view('vendors.imports.show', [
            'import' => $import, 'rows' => $import->rows()->paginate(20),
            'counts' => $import->rows()->selectRaw('status, count(*) as total')->groupBy('status')->reorder()->pluck('total', 'status'),
            'fields' => VendorDirectory::FIELDS,
        ]);
    }

    public function mapping(Request $request, VendorImport $import): RedirectResponse
    {
        $this->authorizeBatch($import);
        $input = $request->validate([
            'version' => ['required', 'integer'], 'mapping' => ['required', 'array'],
            'mapping.*' => ['nullable', 'integer', 'min:0', 'max:'.(count($import->headers) - 1)],
        ]);
        $mapping = array_filter(Arr::only($input['mapping'], array_keys(VendorDirectory::FIELDS)), fn ($value) => $value !== null && $value !== '');
        foreach (['company_name', 'primary_contact', 'primary_email', 'phone', 'coverage', 'document_types'] as $required) {
            if (! array_key_exists($required, $mapping)) {
                throw ValidationException::withMessages(['mapping' => 'Map all six required fields before review.']);
            }
        }
        if (count(array_unique($mapping)) !== count($mapping)) {
            throw ValidationException::withMessages(['mapping' => 'Map each spreadsheet column to only one field.']);
        }
        $this->imports->locked($import, $input['version'], function ($batch) use ($mapping) {
            if ($batch->status !== 'uploaded') {
                throw ValidationException::withMessages(['mapping' => 'Column mapping is already saved. Start a new upload to change the mapping.']);
            }
            $batch->update(['mapping' => $mapping]);
            foreach ($batch->rows()->get() as $row) {
                $data = [];
                foreach ($mapping as $field => $column) {
                    $data[$field] = $row->raw[(int) $column] ?? '';
                }
                $row->update(['data' => $data]);
            }
            $this->imports->review($batch);
            Audit::record('vendor.import_mapped', 'vendor_import', $batch->id, after: ['mapping' => $mapping]);
        });

        return back()->with('status', 'Columns mapped. Correct or reject every invalid row before committing.');
    }

    public function edit(VendorImport $import, VendorImportRow $row): View
    {
        $this->authorizeBatch($import);
        abort_unless($row->vendor_import_id === $import->id, 404);
        abort_unless($import->status === 'review' && ! $import->expires_at->isPast(), 409);

        return view('vendors.imports.edit', [
            'import' => $import, 'row' => $row, 'data' => app(VendorDirectory::class)->normalize($row->data ?? []),
            'counties' => County::orderBy('state')->orderBy('name')->get(), 'documents' => DocumentType::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, VendorImport $import, VendorImportRow $row): RedirectResponse
    {
        $this->authorizeBatch($import);
        abort_unless($row->vendor_import_id === $import->id, 404);
        $input = $request->validate(['version' => ['required', 'integer'], 'action' => ['required', Rule::in(['correct', 'reject', 'restore'])]]);
        $this->imports->locked($import, $input['version'], function ($batch) use ($request, $input, $row) {
            abort_unless($batch->status === 'review', 409);
            $row = $batch->rows()->findOrFail($row->id);
            if ($input['action'] === 'correct') {
                $data = app(VendorDirectory::class)->validate($request->all());
                $row->update(['data' => Arr::only($data, [...array_keys(VendorDirectory::FIELDS), 'contacts']), 'status' => 'pending']);
            } else {
                $row->update(['status' => $input['action'] === 'reject' ? 'rejected' : 'pending']);
            }
            $this->imports->review($batch);
            Audit::record('vendor.import_row_'.$input['action'], 'vendor_import', $batch->id, after: ['row' => $row->row_number]);
        });

        return redirect()->route('vendor-imports.show', $import)->with('status', 'Row updated. The entire import has been revalidated.');
    }

    public function commit(Request $request, VendorImport $import): RedirectResponse
    {
        $this->authorizeBatch($import);
        $input = $request->validate(['version' => ['required', 'integer']]);
        $success = $this->imports->locked($import, $input['version'], function ($batch) {
            abort_unless($batch->status === 'review', 409);

            return $this->imports->commit($batch);
        });

        return back()->with($success ? 'status' : 'import_error', $success
            ? 'Import completed. New vendors are inactive until an administrator activates them.'
            : 'Nothing was imported. Some rows are now invalid or duplicate; review the updated errors.');
    }

    public function cancel(Request $request, VendorImport $import): RedirectResponse
    {
        $this->authorizeBatch($import);
        $input = $request->validate(['version' => ['required', 'integer']]);
        $this->imports->locked($import, $input['version'], function ($batch) {
            $batch->rows()->delete();
            $batch->update(['status' => 'cancelled']);
            Audit::record('vendor.import_cancelled', 'vendor_import', $batch->id);
        });

        return redirect()->route('vendor-imports.index')->with('status', 'Import cancelled and staged rows removed.');
    }

    public function template(Request $request, VendorSpreadsheet $spreadsheet): StreamedResponse
    {
        $format = $request->validate(['format' => ['required', Rule::in(['csv', 'xlsx'])]])['format'];
        $data = ['company_name' => 'Example Vendor - replace before import', 'primary_contact' => 'Example Contact', 'primary_email' => 'example@example.test',
            'phone' => '+1 202 555 0100', 'coverage' => 'CA:Los Angeles County | CA:Orange County', 'document_types' => 'Deed | Mortgage',
            'country' => 'United States', 'working_days' => 'Mon|Tue|Wed|Thu|Fri', 'working_start' => '09:00', 'working_end' => '17:00',
            'time_zone' => 'America/Los_Angeles', 'availability' => 'available', 'currency' => 'USD', 'priority' => 'normal', 'preferred_communication' => 'email'];
        $row = [];
        foreach (VendorDirectory::FIELDS as $field => $label) {
            $row[] = $data[$field] ?? '';
        }

        return $spreadsheet->download(array_keys(VendorDirectory::FIELDS), [$row], $format, 'vmas-vendor-template');
    }
}
