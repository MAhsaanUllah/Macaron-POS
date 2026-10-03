<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Item;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SweetShopSeeder extends Seeder
{
    public function run(): void
    {
        $categories = collect(['Mithai', 'Bakery', 'Cakes', 'Savoury', 'Gift Boxes'])
            ->mapWithKeys(fn ($name) => [$name => Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            )]);

        $items = [
            ['Mithai', 'Gulab Jamun', 900, 'KG', 25, 'images/products/gulab-jamun.jpg'],
            ['Mithai', 'Mix Mithai', 1200, 'KG', 25, 'images/products/mithai-box.jpg'],
            ['Mithai', 'Kaju Barfi', 2200, 'KG', 10, 'images/products/barfi.jpg'],
            ['Bakery', 'Plain Cake Rusk', 650, 'KG', 20, 'images/products/cake-rusk.jpg'],
            ['Bakery', 'Chicken Patty', 180, 'Numbers, pieces, units', 50, 'images/products/chicken-patty.jpg'],
            ['Cakes', 'Chocolate Cake 2lb', 2600, 'Numbers, pieces, units', 8, 'images/products/chocolate-cake.jpg'],
            ['Savoury', 'Samosa', 70, 'Numbers, pieces, units', 100, 'images/products/samosa.jpg'],
            ['Gift Boxes', 'Premium Mithai Box 1kg', 1800, 'Numbers, pieces, units', 15, 'images/products/mithai-box.jpg'],
        ];

        foreach ($items as [$category, $name, $price, $uom, $stock, $image]) {
            $item = Item::firstOrCreate([
                'name' => $name,
            ], [
                'price' => $price,
                'category_id' => $categories[$category]->id,
                'stock_qty' => $stock,
                'barcode' => Str::random(10),
                'uom' => $uom,
                'sale_type' => 'Goods at standard rate (default)',
            ]);

            if (! $item->image_path) {
                $item->update(['image_path' => $image]);
            }
        }
    }
}
