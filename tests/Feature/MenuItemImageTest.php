<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Shift;
use App\Models\SystemConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MenuItemImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_image_is_stored_as_portable_relative_path(): void
    {
        Storage::fake('public');
        SystemConfig::create(['is_setup_completed' => true]);
        $user = User::create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => Hash::make('secret123'),
            'role' => User::ROLE_ADMIN,
        ]);
        Shift::create([
            'user_id' => $user->id,
            'start_time' => now(),
            'opening_balance' => 0,
            'expected_cash' => 0,
            'status' => 'open',
        ]);
        $category = Category::create(['name' => 'Mithai', 'slug' => 'mithai']);

        $this->actingAs($user)->post('/menu/items', [
            'name' => 'Kaju Barfi',
            'price' => 1200,
            'category_id' => $category->id,
            'stock_qty' => 5,
            'uom' => 'KG',
            'sale_type' => 'weight',
            'image' => UploadedFile::fake()->image('barfi.png'),
        ])->assertSessionHasNoErrors();

        $item = Item::firstOrFail();
        $this->assertStringStartsWith('storage/items/', $item->image_path);
        $this->assertStringNotContainsString('http', $item->image_path);
    }
}
