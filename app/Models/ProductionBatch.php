<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ProductionBatch extends Model
{
    use HasUuids;

    protected $fillable = [
        'item_id', 'batch_code', 'production_date', 'produced_qty',
        'remaining_qty', 'sold_out_at', 'notes', 'created_by',
    ];

    protected $casts = [
        'production_date' => 'date',
        'produced_qty' => 'decimal:3',
        'remaining_qty' => 'decimal:3',
        'sold_out_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    public function allocations()
    {
        return $this->hasMany(BatchOrderItem::class);
    }

    public function wastages()
    {
        return $this->hasMany(BatchWastage::class);
    }
}
