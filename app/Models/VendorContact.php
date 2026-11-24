<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorContact extends Model
{
    public $timestamps = false;

    protected $guarded = ['id', 'vendor_id'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }
}
