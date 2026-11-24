<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\County;
use App\Models\DocumentType;
use App\Models\Vendor;
use App\Services\Audit;
use App\Services\VendorDirectory;
use App\Services\VendorSpreadsheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VendorController extends Controller
{
    public function __construct(private VendorDirectory $directory) {}

    private function filters(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:180'], 'state' => ['nullable', 'string', 'size:2', Rule::exists('counties', 'state')],
            'county' => ['nullable', 'integer', Rule::exists('counties', 'id')],
            'document' => ['nullable', 'integer', Rule::exists('document_types', 'id')],
            'active' => ['nullable', Rule::in(['0', '1'])],
            'availability' => ['nullable', Rule::in(['available', 'limited', 'unavailable'])],
        ]);
    }

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        return view('vendors.index', [
            'vendors' => $this->directory->search($filters)->paginate(20)->withQueryString(),
            'filters' => $filters, 'states' => County::distinct()->orderBy('state')->pluck('state'),
            'counties' => County::when($filters['state'] ?? null, fn ($q, $s) => $q->where('state', $s))->orderBy('state')->orderBy('name')->get(),
            'documents' => DocumentType::orderBy('name')->get(),
            'total' => Vendor::count(), 'active' => Vendor::where('is_active', true)->count(),
            'available' => Vendor::where('is_active', true)->where('availability', 'available')->count(),
            'demoCount' => Vendor::where('is_demo', true)->count(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Vendor, ['working_days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'], 'time_zone' => 'America/New_York']);
    }

    public function edit(Vendor $vendor): View
    {
        return $this->form($vendor, $this->directory->formData($vendor));
    }

    private function form(Vendor $vendor, array $data): View
    {
        return view('vendors.form', compact('vendor', 'data') + [
            'counties' => County::orderBy('state')->orderBy('name')->get(),
            'documents' => DocumentType::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $vendor = $this->directory->save($request->all(), reason: 'Vendor created in directory');

        return redirect()->route('vendors.show', $vendor)->with('status', 'Vendor created. An administrator must activate it before operational use.');
    }

    public function update(Request $request, Vendor $vendor): RedirectResponse
    {
        $input = $request->validate(['version' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'max:500']]);
        $this->directory->save($request->all(), $vendor, $input['version'], $input['reason']);

        return redirect()->route('vendors.show', $vendor)->with('status', 'Vendor details updated and history recorded.');
    }

    public function show(Vendor $vendor): View
    {
        $vendor->load(['contacts', 'counties', 'documentTypes']);

        return view('vendors.show', [
            'vendor' => $vendor,
            'history' => AuditLog::with('user')->where('entity_type', 'vendor')->where('entity_id', $vendor->id)->latest('id')->paginate(10),
        ]);
    }

    public function activation(Request $request, Vendor $vendor): RedirectResponse
    {
        $input = $request->validate(['is_active' => ['required', 'boolean'], 'version' => ['required', 'integer'], 'reason' => ['required', 'string', 'max:500']]);
        DB::transaction(function () use ($vendor, $input) {
            $vendor = Vendor::lockForUpdate()->findOrFail($vendor->id);
            $this->directory->checkVersion($vendor, $input['version']);
            $before = ['is_active' => $vendor->is_active];
            $vendor->forceFill(['is_active' => $input['is_active'], 'version' => $vendor->version + 1, 'updated_by' => auth()->id()])->save();
            Audit::record('vendor.activation_changed', 'vendor', $vendor->id, $before, ['is_active' => $vendor->is_active], $input['reason']);
        });

        return back()->with('status', 'Vendor activation updated.');
    }

    public function export(Request $request, VendorSpreadsheet $spreadsheet): StreamedResponse
    {
        $filters = $this->filters($request);
        $format = $request->validate(['format' => ['required', Rule::in(['csv', 'xlsx'])]])['format'];
        $query = $this->directory->search($filters);
        if ((clone $query)->count() > 5000) {
            throw ValidationException::withMessages(['export' => 'Please narrow the filters to at most 5,000 vendors per export.']);
        }
        $rows = [];
        foreach ($query->get() as $vendor) {
            $data = $this->directory->formData($vendor);
            $data['working_days'] = implode('|', $data['working_days']);
            $row = [];
            foreach (VendorDirectory::FIELDS as $key => $label) {
                $row[] = $data[$key] ?? '';
            }
            $rows[] = [...$row, json_encode($data['contacts'], JSON_UNESCAPED_UNICODE), $vendor->is_active ? 'Active' : 'Inactive', $vendor->is_demo ? 'Dummy data' : '', $vendor->id];
        }
        Audit::record('vendor.exported', 'vendor', after: ['format' => $format, 'count' => count($rows), 'filters' => $filters]);

        return $spreadsheet->download([...array_keys(VendorDirectory::FIELDS), 'additional_contacts_json', 'activation', 'data_label', 'vendor_id'], $rows, $format, 'vmas-vendors-'.now()->format('Ymd-His'));
    }
}
