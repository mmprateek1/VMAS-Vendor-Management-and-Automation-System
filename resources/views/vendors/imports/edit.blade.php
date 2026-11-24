@extends('layouts.app')
@section('title', 'Correct import row')
@section('content')
<div class="page-heading"><div><a href="{{ route('vendor-imports.show',$import) }}" class="small"><i class="bi bi-arrow-left"></i> Back to review</a><h1 class="mt-3">Correct spreadsheet row {{ $row->row_number }}</h1><p>Save corrections, then review the updated validation results.</p></div></div>
<div class="vendor-form"><form method="POST" action="{{ route('vendor-imports.update',[$import,$row]) }}">@csrf @method('PATCH')<input type="hidden" name="version" value="{{ old('version', $import->version) }}"><input type="hidden" name="action" value="correct">@include('vendors.fields')<button class="btn btn-primary">Save corrected row</button></form></div>
@endsection
