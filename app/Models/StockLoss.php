<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class StockLoss extends Model
{
    use HasUuids;

    protected $fillable = [
        'item_id', 'quantity', 'unit_retail_value', 'reason', 'notes', 'recorded_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_retail_value' => 'decimal:2',
    ];
}
