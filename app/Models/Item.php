<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'barcode',
        'name',
        'price',
        'stock_qty',
        'category_id',
        'image_path',
        'hs_code',
        'uom',
        'sale_type',
        'tracks_batches',
        'expiry_date',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock_qty' => 'decimal:3',
        'tracks_batches' => 'boolean',
        'expiry_date' => 'date',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function ingredients()
    {
        return $this->belongsToMany(Ingredient::class, 'recipe_items')
            ->withPivot('qty_required')
            ->withTimestamps();
    }

    public function recipeItems()
    {
        return $this->hasMany(RecipeItem::class);
    }
}
