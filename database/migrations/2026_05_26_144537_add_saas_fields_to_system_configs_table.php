<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('system_configs', function (Blueprint $table) {
            $table->string('fbr_pos_id')->nullable();
            $table->string('fbr_bearer_token')->nullable();
            $table->enum('fbr_environment', ['sandbox', 'production'])->default('sandbox');
            $table->string('kitchen_printer_ip')->nullable();
            $table->string('cashier_printer_ip')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_configs', function (Blueprint $table) {
            $table->dropColumn([
                'fbr_pos_id',
                'fbr_bearer_token',
                'fbr_environment',
                'kitchen_printer_ip',
                'cashier_printer_ip',
            ]);
        });
    }
};
