<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_configs', function (Blueprint $table) {
            $table->boolean('card_enabled')->default(false);
            $table->string('card_terminal_name')->nullable();
            $table->boolean('raast_enabled')->default(false);
            $table->string('raast_qr_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('system_configs', fn (Blueprint $table) => $table->dropColumn([
            'card_enabled', 'card_terminal_name', 'raast_enabled', 'raast_qr_path',
        ]));
    }
};
