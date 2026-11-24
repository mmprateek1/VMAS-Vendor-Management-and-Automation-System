@extends('layouts.app')
@section('title', $vendor->exists ? 'Edit vendor' : 'Add vendor')
@section('content')
<div class="page-heading"><div><a class="small" href="{{ route('vendors.index') }}"><i class="bi bi-arrow-left"></i> Vendor directory</a><h1 class="mt-3">{{ $vendor->exists ? 'Edit '.$vendor->company_name : 'Add a vendor' }}</h1><p>Maintain contact details, coverage, and availability in one profile.</p></div></div>
<div class="vendor-form"><form method="POST" action="{{ $vendor->exists ? route('vendors.update',$vendor) : route('vendors.store') }}">@csrf
@if($vendor->exists)@method('PUT')<input type="hidden" name="version" value="{{ old('version', $vendor->version) }}">@endif
@include('vendors.fields')
@if($vendor->exists)<section class="panel p-4 mb-4"><label class="form-label" for="reason">Reason for this change *</label><input class="form-control" id="reason" name="reason" required maxlength="500" value="{{ old('reason') }}"><p class="small text-secondary mb-0 mt-2">This reason appears in the vendor's history.</p></section>@else<div class="alert alert-info">New vendors are inactive. An administrator will need to activate this profile.</div>@endif
<div class="d-flex gap-3"><button class="btn btn-primary">{{ $vendor->exists ? 'Save vendor changes' : 'Create vendor' }}</button><a class="btn btn-light" href="{{ $vendor->exists ? route('vendors.show',$vendor) : route('vendors.index') }}">Cancel</a></div>
</form></div>
@endsection
