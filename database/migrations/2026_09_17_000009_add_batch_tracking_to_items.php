<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', fn (Blueprint $table) => $table->boolean('tracks_batches')->default(false));

        $madeHereCategories = DB::table('categories')
            ->whereIn('name', ['Mithai', 'Bakery', 'Cakes', 'Savoury'])
            ->pluck('id');
        DB::table('items')->whereIn('category_id', $madeHereCategories)->update(['tracks_batches' => true]);
    }

    public function down(): void
    {
        Schema::table('items', fn (Blueprint $table) => $table->dropColumn('tracks_batches'));
    }
};
