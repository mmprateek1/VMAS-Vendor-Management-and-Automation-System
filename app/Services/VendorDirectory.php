<?php

namespace App\Services;

use App\Models\County;
use App\Models\DocumentType;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VendorDirectory
{
    public const FIELDS = [
        'company_name' => 'Vendor / company name', 'primary_contact' => 'Primary contact',
        'primary_email' => 'Primary email', 'phone' => 'Primary phone',
        'coverage' => 'County coverage', 'document_types' => 'Document types',
        'country' => 'Country', 'address' => 'Address', 'preferred_communication' => 'Preferred communication',
        'working_days' => 'Working days', 'working_start' => 'Working hours start', 'working_end' => 'Working hours end',
        'time_zone' => 'Time zone', 'availability' => 'Availability',
        'standard_charge' => 'Standard retrieval charge', 'travel_charge' => 'Travel charge', 'currency' => 'Currency',
        'payment_terms' => 'Payment terms', 'priority' => 'Internal priority', 'remarks' => 'Internal remarks',
        'alternate_name' => 'Alternate contact', 'alternate_email' => 'Alternate email', 'alternate_phone' => 'Alternate phone',
    ];

    public const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    public function normalize(array $input): array
    {
        $data = [];
        foreach (self::FIELDS as $field => $label) {
            $value = $input[$field] ?? '';
            $data[$field] = is_scalar($value) ? trim((string) $value) : $value;
        }
        $data['company_name'] = is_string($data['company_name']) ? preg_replace('/\s+/u', ' ', $data['company_name']) : $data['company_name'];
        $data['primary_email'] = is_string($data['primary_email']) ? mb_strtolower($data['primary_email']) : $data['primary_email'];
        foreach (['country' => 'United States', 'preferred_communication' => 'email', 'time_zone' => 'America/New_York', 'availability' => 'available', 'priority' => 'normal', 'currency' => 'USD'] as $field => $default) {
            if ($data[$field] === '') {
                $data[$field] = $default;
            }
        }
        if (is_string($data['working_days'])) {
            $data['working_days'] = $data['working_days'] === '' ? [] : preg_split('/[|,]\s*/', $data['working_days']);
        }
        foreach (['standard_charge', 'travel_charge', 'working_start', 'working_end'] as $field) {
            if ($data[$field] === '') {
                $data[$field] = null;
            }
        }
        $data['contacts'] = $input['contacts'] ?? [];

        return $data;
    }

    public function validate(array $input, ?Vendor $vendor = null): array
    {
        $data = $this->normalize($input);
        $rules = [
            'company_name' => ['required', 'string', 'max:180'],
            'primary_contact' => ['required', 'string', 'max:120'],
            'primary_email' => ['required', 'string', 'email:rfc', 'max:254', Rule::unique('vendors')->ignore($vendor?->id)],
            'phone' => ['required', 'string', 'max:40', 'regex:/^[+0-9(). xX#-]{5,40}$/'],
            'coverage' => ['required', 'string', 'max:20000'],
            'document_types' => ['required', 'string', 'max:2000'],
            'country' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:2000'],
            'preferred_communication' => ['required', Rule::in(['email', 'phone'])],
            'working_days' => ['array', 'max:7'],
            'working_days.*' => ['string', Rule::in(self::DAYS), 'distinct'],
            'working_start' => ['nullable', 'date_format:H:i', 'required_with:working_end'],
            'working_end' => ['nullable', 'date_format:H:i', 'required_with:working_start'],
            'time_zone' => ['required', 'timezone:all'],
            'availability' => ['required', Rule::in(['available', 'limited', 'unavailable'])],
            'standard_charge' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'travel_charge' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'currency' => ['required', Rule::in(['USD', 'INR', 'CAD', 'GBP', 'EUR'])],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'priority' => ['required', Rule::in(['high', 'normal', 'low'])],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'alternate_name' => ['nullable', 'string', 'max:120', 'required_with:alternate_email,alternate_phone'],
            'alternate_email' => ['nullable', 'email:rfc', 'max:254'],
            'alternate_phone' => ['nullable', 'string', 'max:40', 'regex:/^[+0-9(). xX#-]{5,40}$/'],
            'contacts' => ['array', 'max:8'],
            'contacts.*.name' => ['required', 'string', 'max:120'],
            'contacts.*.email' => ['nullable', 'email:rfc', 'max:254'],
            'contacts.*.phone' => ['nullable', 'string', 'max:40', 'regex:/^[+0-9(). xX#-]{5,40}$/'],
        ];
        $validator = Validator::make($data, $rules);
        $countyIds = [];
        $documentIds = [];
        $validator->after(function ($validator) use ($data, $vendor, &$countyIds, &$documentIds) {
            if (is_string($data['company_name']) && Vendor::where('name_key', mb_strtolower($data['company_name']))->when($vendor, fn ($q) => $q->where('id', '!=', $vendor->id))->exists()) {
                $validator->errors()->add('company_name', 'A vendor with this company name already exists. Review the existing profile.');
            }
            if (is_string($data['coverage'])) {
                foreach ($this->tokens($data['coverage']) as $token) {
                    $parts = explode(':', $token, 2);
                    $county = count($parts) === 2 ? County::where('state', strtoupper(trim($parts[0])))->where(function ($q) use ($parts) {
                        $q->where('name', trim($parts[1]))->orWhere('name', trim($parts[1]).' County');
                    })->first() : null;
                    if (! $county) {
                        $validator->errors()->add('coverage', 'Unknown county: '.$token.'. Use STATE:County name.');
                    } else {
                        $countyIds[] = $county->id;
                    }
                }
                if (empty($countyIds)) {
                    $validator->errors()->add('coverage', 'Choose at least one county.');
                }
                if (count($countyIds) > 500) {
                    $validator->errors()->add('coverage', 'Use at most 500 counties per vendor.');
                }
            }
            if (is_string($data['document_types'])) {
                foreach ($this->tokens($data['document_types']) as $name) {
                    $id = DocumentType::where('name', $name)->value('id');
                    if (! $id) {
                        $validator->errors()->add('document_types', 'Unknown document type: '.$name.'. Select a listed type.');
                    } else {
                        $documentIds[] = $id;
                    }
                }
                if (empty($documentIds)) {
                    $validator->errors()->add('document_types', 'Choose at least one document type.');
                }
            }
        });
        $validated = $validator->validate();
        $validated['county_ids'] = array_unique($countyIds);
        $validated['document_ids'] = array_unique($documentIds);

        return $validated;
    }

    private function tokens(string $value): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode('|', $value)))));
    }

    public function save(array $input, ?Vendor $vendor = null, ?int $version = null, ?string $reason = null, bool $demo = false): Vendor
    {
        try {
            return DB::transaction(function () use ($input, $vendor, $version, $reason, $demo) {
                if ($vendor) {
                    $vendor = Vendor::lockForUpdate()->findOrFail($vendor->id);
                    $this->checkVersion($vendor, $version);
                }
                $data = $this->validate($input, $vendor);
                $before = $vendor ? $this->snapshot($vendor) : null;
                $new = ! $vendor;
                $vendor ??= new Vendor;
                $vendor->fill(Arr::except($data, ['primary_contact', 'phone', 'coverage', 'document_types', 'county_ids', 'document_ids', 'contacts', 'alternate_name', 'alternate_email', 'alternate_phone']));
                $vendor->name_key = mb_strtolower($data['company_name']);
                $vendor->forceFill(['updated_by' => auth()->id(), 'version' => $new ? 1 : $vendor->version + 1]);
                if ($new) {
                    $vendor->forceFill(['created_by' => auth()->id(), 'is_active' => false, 'is_demo' => $demo || config('vmas.demo_mode')]);
                }
                $vendor->save();
                $contacts = [['name' => $data['primary_contact'], 'email' => $data['primary_email'], 'phone' => $data['phone'], 'is_primary' => true]];
                if (! empty($data['alternate_name'])) {
                    $contacts[] = ['name' => $data['alternate_name'], 'email' => $data['alternate_email'] ?: null, 'phone' => $data['alternate_phone'] ?: null, 'is_primary' => false];
                }
                foreach ($data['contacts'] as $contact) {
                    $contacts[] = ['name' => $contact['name'], 'email' => $contact['email'] ?? null, 'phone' => $contact['phone'] ?? null, 'is_primary' => false];
                }
                $vendor->contacts()->delete();
                $vendor->contacts()->createMany($contacts);
                $vendor->counties()->sync($data['county_ids']);
                $vendor->documentTypes()->sync($data['document_ids']);
                $vendor->unsetRelations();
                Audit::record($new ? 'vendor.created' : 'vendor.updated', 'vendor', $vendor->id, $before, $this->snapshot($vendor), $reason);

                return $vendor;
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw ValidationException::withMessages(['company_name' => 'This vendor name or primary email was just saved by someone else. Review the existing directory.']);
            }
            throw $exception;
        }
    }

    public function checkVersion(Vendor $vendor, ?int $version): void
    {
        if ($vendor->version !== $version) {
            throw ValidationException::withMessages(['version' => 'This vendor changed while you were editing. Reload the profile before making changes.']);
        }
    }

    public function snapshot(Vendor $vendor): array
    {
        return $vendor->refresh()->load(['contacts', 'counties', 'documentTypes'])->toArray();
    }

    public function historyChanges(?array $before, ?array $after): array
    {
        $describe = function (?array $snapshot): array {
            $result = [];
            foreach (self::FIELDS as $key => $label) {
                if (array_key_exists($key, $snapshot ?? []) && ! in_array($key, ['document_types', 'working_days'], true)) {
                    $result[$label] = (string) ($snapshot[$key] ?? '');
                    if (in_array($key, ['working_start', 'working_end'], true)) {
                        $result[$label] = substr($result[$label], 0, 5);
                    }
                }
            }
            foreach (['working_days' => 'Working days', 'contacts' => 'Contacts', 'counties' => 'County coverage', 'document_types' => 'Document types'] as $key => $label) {
                if (! isset($snapshot[$key])) {
                    continue;
                }
                $result[$label] = collect($snapshot[$key])->map(fn ($item) => match ($key) {
                    'contacts' => implode(' · ', array_filter([$item['name'], $item['email'] ?? null, $item['phone'] ?? null, ($item['is_primary'] ?? false) ? 'Primary' : null])),
                    'counties' => $item['state'].' · '.$item['name'],
                    'document_types' => $item['name'],
                    default => $item,
                })->implode("\n");
            }
            if (array_key_exists('is_active', $snapshot ?? [])) {
                $result['Activation'] = $snapshot['is_active'] ? 'Active' : 'Inactive';
            }

            return $result;
        };
        $previous = $describe($before);
        $current = $describe($after);
        $changes = [];
        foreach (array_unique([...array_keys($previous), ...array_keys($current)]) as $label) {
            if (($previous[$label] ?? '') !== ($current[$label] ?? '')) {
                $changes[] = ['label' => $label, 'before' => $previous[$label] ?? '', 'after' => $current[$label] ?? ''];
            }
        }

        return $changes;
    }

    public function formData(Vendor $vendor): array
    {
        $vendor->load(['contacts', 'counties', 'documentTypes']);
        $primary = $vendor->contacts->firstWhere('is_primary', true);
        $alternates = $vendor->contacts->where('is_primary', false)->values();

        return array_merge($vendor->toArray(), [
            'primary_contact' => $primary?->name, 'phone' => $primary?->phone,
            'coverage' => $vendor->counties->map(fn ($c) => $c->state.':'.$c->name)->implode(' | '),
            'document_types' => $vendor->documentTypes->pluck('name')->implode(' | '),
            'working_start' => $vendor->working_start ? substr($vendor->working_start, 0, 5) : null,
            'working_end' => $vendor->working_end ? substr($vendor->working_end, 0, 5) : null,
            'alternate_name' => $alternates->first()?->name, 'alternate_email' => $alternates->first()?->email,
            'alternate_phone' => $alternates->first()?->phone, 'contacts' => $alternates->skip(1)->values()->toArray(),
        ]);
    }

    public function search(array $filters): Builder
    {
        return Vendor::query()->with(['contacts', 'counties', 'documentTypes'])
            ->when($filters['q'] ?? null, fn ($q, $value) => $q->where(function ($q) use ($value) {
                $term = '%'.addcslashes($value, '%_\\').'%';
                $q->where('company_name', 'like', $term)->orWhere('primary_email', 'like', $term)
                    ->orWhereHas('contacts', fn ($c) => $c->where('name', 'like', $term)->orWhere('phone', 'like', $term));
            }))
            ->when(($filters['state'] ?? null) || ($filters['county'] ?? null), fn ($q) => $q->whereHas('counties', fn ($c) => $c
                ->when($filters['state'] ?? null, fn ($c, $state) => $c->where('state', $state))
                ->when($filters['county'] ?? null, fn ($c, $id) => $c->where('counties.id', $id))))
            ->when($filters['document'] ?? null, fn ($q, $id) => $q->whereHas('documentTypes', fn ($d) => $d->where('document_types.id', $id)))
            ->when($filters['availability'] ?? null, fn ($q, $value) => $q->where('availability', $value))
            ->when(isset($filters['active']) && $filters['active'] !== '', fn ($q) => $q->where('is_active', $filters['active']))
            ->orderBy('company_name')->orderBy('id');
    }
}
