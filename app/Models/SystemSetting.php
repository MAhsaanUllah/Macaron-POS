<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SystemSetting extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'shop_name',
        'phone_number',
        'address',
        'logo_path',
        'currency_symbol',
        'tax_number',
    ];

    public static function instance(): self
    {
        return static::first() ?? new static;
    }
}
