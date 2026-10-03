<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecipeItem extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'item_id',
        'ingredient_id',
        'qty_required',
    ];

    protected $casts = [
        'qty_required' => 'float',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}
