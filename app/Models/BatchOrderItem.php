<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BatchOrderItem extends Model
{
    protected $fillable = ['production_batch_id', 'order_item_id', 'quantity'];

    protected $casts = ['quantity' => 'decimal:3'];

    public function batch()
    {
        return $this->belongsTo(ProductionBatch::class, 'production_batch_id');
    }
}
