<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountCode extends Model
{
    protected $fillable = ['email', 'code', 'used', 'used_at'];

    protected function casts(): array
    {
        return [
            'used'    => 'boolean',
            'used_at' => 'datetime',
        ];
    }
}
