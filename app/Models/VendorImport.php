<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorImport extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['headers' => 'array', 'mapping' => 'array', 'expires_at' => 'datetime', 'committed_at' => 'datetime'];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(VendorImportRow::class)->orderBy('row_number');
    }
}
