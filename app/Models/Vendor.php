<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'is_active', 'is_demo', 'version', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['working_days' => 'array', 'is_active' => 'boolean', 'is_demo' => 'boolean', 'standard_charge' => 'decimal:2', 'travel_charge' => 'decimal:2'];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(VendorContact::class)->orderByDesc('is_primary')->orderBy('id');
    }

    public function counties(): BelongsToMany
    {
        return $this->belongsToMany(County::class)->orderBy('state')->orderBy('name');
    }

    public function documentTypes(): BelongsToMany
    {
        return $this->belongsToMany(DocumentType::class)->orderBy('name');
    }
}
