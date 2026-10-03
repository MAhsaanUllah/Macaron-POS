<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ingredient extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'current_stock',
        'minimum_alert_stock',
        'unit',
        'unit_cost',
    ];

    protected $casts = [
        'current_stock' => 'float',
        'minimum_alert_stock' => 'float',
        'unit_cost' => 'float',
    ];

    public function items()
    {
        return $this->belongsToMany(Item::class, 'recipe_items')
            ->withPivot('qty_required')
            ->withTimestamps();
    }

    public function recipeItems()
    {
        return $this->hasMany(RecipeItem::class);
    }
}
