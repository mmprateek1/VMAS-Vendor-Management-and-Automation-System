@extends('layouts.app')
@section('title', 'Vendors')
@section('content')
<div class="page-heading"><div><span class="eyebrow text-teal">VENDOR DIRECTORY</span><h1>The right people, in one place.</h1><p>Find contacts by county, document capability, and availability.</p></div><div class="d-flex gap-2 flex-wrap">@can('vendors.manage')<a class="btn btn-outline-primary" href="{{ route('vendor-imports.index') }}"><i class="bi bi-upload"></i>Import vendors</a><a class="btn btn-primary" href="{{ route('vendors.create') }}"><i class="bi bi-plus-lg"></i>Add vendor</a>@endcan</div></div>
@if($demoCount)<div class="demo-banner"><i class="bi bi-info-circle"></i><span>This workspace contains {{ $demoCount }} clearly marked dummy vendors for testing. No vendor messages are being sent.</span></div>@endif
<div class="row g-3 mb-4">
@foreach([['All vendors',$total,'Profiles in your directory','buildings'],['Active vendors',$active,'Approved for operational use','check2-circle'],['Available now',$available,'Active vendors marked available','calendar-check']] as [$label,$count,$help,$icon])
<div class="col-md-4"><section class="metric-card"><span class="metric-icon"><i class="bi bi-{{ $icon }}"></i></span><span class="metric-label">{{ $label }}</span><strong>{{ $count }}</strong><small>{{ $help }}</small></section></div>
@endforeach
</div>
<section class="panel">
<form method="GET" action="{{ route('vendors.index') }}" class="vendor-filters">
<div class="row g-3">
<div class="col-lg-6"><label class="form-label" for="q">Search vendors</label><input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Company, contact, email, or phone" maxlength="180"></div>
<div class="col-lg-3 col-sm-6"><label class="form-label" for="state">State</label><select class="form-select" id="state" name="state" data-filter-state><option value="">All states</option>@foreach($states as $state)<option value="{{ $state }}" @selected(($filters['state'] ?? '') === $state)>{{ $state }}</option>@endforeach</select></div>
<div class="col-lg-3 col-sm-6"><label class="form-label" for="county">County / equivalent</label><select class="form-select" id="county" name="county" data-filter-county><option value="">All counties</option>@foreach($counties as $county)<option value="{{ $county->id }}" data-state="{{ $county->state }}" @selected(($filters['county'] ?? '') == $county->id)>{{ $county->state }} · {{ $county->name }}</option>@endforeach</select></div>
<div class="col-lg-3 col-sm-6"><label class="form-label" for="document">Document type</label><select class="form-select" id="document" name="document"><option value="">All document types</option>@foreach($documents as $document)<option value="{{ $document->id }}" @selected(($filters['document'] ?? '') == $document->id)>{{ $document->name }}</option>@endforeach</select></div>
<div class="col-lg-3 col-sm-6"><label class="form-label" for="availability">Availability</label><select class="form-select" id="availability" name="availability"><option value="">Any availability</option>@foreach(['available','limited','unavailable'] as $value)<option @selected(($filters['availability'] ?? '') === $value) value="{{ $value }}">{{ ucfirst($value) }}</option>@endforeach</select></div>
<div class="col-lg-3 col-sm-6"><label class="form-label" for="active">Activation</label><select class="form-select" id="active" name="active"><option value="">All vendors</option><option value="1" @selected(($filters['active'] ?? '') === '1')>Active</option><option value="0" @selected(($filters['active'] ?? '') === '0')>Inactive</option></select></div>
<div class="col-lg-3 col-sm-6 d-flex align-items-end gap-2"><button class="btn btn-primary">Apply filters</button><a class="btn btn-light" href="{{ route('vendors.index') }}">Reset</a></div>
</div></form>
<div class="panel-heading"><div><h2>{{ $vendors->total() }} {{ Str::plural('vendor', $vendors->total()) }} found</h2><p>Availability is vendor information; assignment workload begins in Phase 3.</p></div>@can('vendors.export')<div class="d-flex gap-3 small"><a href="{{ route('vendors.export', array_merge($filters, ['format'=>'csv'])) }}">Export CSV</a><a href="{{ route('vendors.export', array_merge($filters, ['format'=>'xlsx'])) }}">Export Excel</a></div>@endcan</div>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th scope="col">Vendor & contact</th><th scope="col">Coverage</th><th scope="col">Documents</th><th scope="col">Availability</th><th scope="col">Activation</th><th scope="col">Profile</th></tr></thead><tbody>
@forelse($vendors as $vendor)
<tr><td class="vendor-name"><a class="fw-semibold" href="{{ route('vendors.show', $vendor) }}">{{ $vendor->company_name }}</a>@if($vendor->is_demo)<span class="demo-label">DUMMY DATA</span>@endif<small class="d-block text-secondary">{{ $vendor->contacts->firstWhere('is_primary',true)?->name }}</small><small>{{ $vendor->primary_email }}</small></td>
<td>@foreach($vendor->counties->take(2) as $county)<span class="d-block">{{ $county->state }} · {{ $county->name }}</span>@endforeach @if($vendor->counties->count()>2)<small class="text-secondary">+{{ $vendor->counties->count()-2 }} more</small>@endif</td>
<td>{{ $vendor->documentTypes->take(3)->pluck('name')->implode(', ') }}@if($vendor->documentTypes->count()>3)<small class="d-block">+{{ $vendor->documentTypes->count()-3 }} more</small>@endif</td>
<td><span class="status-pill status-{{ $vendor->availability }}">{{ ucfirst($vendor->availability) }}</span><small class="d-block mt-2 text-secondary">{{ ucfirst($vendor->priority) }} priority</small></td>
<td><span class="{{ $vendor->is_active ? 'badge-soft' : 'badge-muted' }}">{{ $vendor->is_active ? 'Active' : 'Inactive' }}</span></td>
<td><a href="{{ route('vendors.show', $vendor) }}" aria-label="View {{ $vendor->company_name }}">View <i class="bi bi-arrow-up-right"></i></a></td></tr>
@empty<tr><td colspan="6"><div class="empty-state"><i class="bi bi-buildings"></i><h3>No vendors match this view</h3><p>Try different filters or add the first vendor.</p></div></td></tr>@endforelse
</tbody></table></div><div class="p-3">{{ $vendors->links() }}</div>
</section>
@endsection
