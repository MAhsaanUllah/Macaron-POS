<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $legacyCategoryIds = DB::table('categories')
            ->whereNull('deleted_at')
            ->whereIn('slug', ['fine-desserts'])
            ->pluck('id');

        DB::table('items')->whereIn('category_id', $legacyCategoryIds)->update(['deleted_at' => now()]);
        DB::table('categories')->whereIn('id', $legacyCategoryIds)->update(['deleted_at' => now()]);
    }

    public function down(): void
    {
        // Keep obsolete restaurant demo data archived.
    }
};
