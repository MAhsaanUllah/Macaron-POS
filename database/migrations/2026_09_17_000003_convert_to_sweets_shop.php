<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_configs', function (Blueprint $table) {
            $table->string('business_type')->default('sweets');
        });
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('stock_qty', 12, 3)->default(0)->change();
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('quantity', 12, 3)->change();
        });

        DB::table('system_configs')->update([
            'business_type' => 'sweets',
            'is_restaurant' => false,
        ]);
        DB::table('users')->where('role', 'kitchen')->update(['role' => 'production']);
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'production')->update(['role' => 'kitchen']);
        Schema::table('system_configs', fn (Blueprint $table) => $table->dropColumn('business_type'));
    }
};
