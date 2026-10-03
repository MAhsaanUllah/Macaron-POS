<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_configs', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->boolean('fbr_enabled')->default(false);
            $table->string('seller_province')->default('Punjab');
            $table->string('fbr_scenario_id')->nullable();
            $table->string('kitchen_printer_name')->nullable();
            $table->string('cashier_printer_name')->nullable();
        });

        Schema::table('items', function (Blueprint $table) {
            $table->string('hs_code')->nullable();
            $table->string('uom')->default('Numbers, pieces, units');
            $table->string('sale_type')->default('Goods at standard rate (default)');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('fbr_status')->default('not_configured')->index();
            $table->string('fbr_invoice_number')->nullable()->index();
            $table->text('fbr_error')->nullable();
            $table->timestamp('fbr_submitted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn([
            'fbr_status', 'fbr_invoice_number', 'fbr_error', 'fbr_submitted_at',
        ]));
        Schema::table('items', fn (Blueprint $table) => $table->dropColumn(['hs_code', 'uom', 'sale_type']));
        Schema::table('system_configs', fn (Blueprint $table) => $table->dropColumn([
            'tax_rate', 'fbr_enabled', 'seller_province', 'fbr_scenario_id',
            'kitchen_printer_name', 'cashier_printer_name',
        ]));
    }
};
