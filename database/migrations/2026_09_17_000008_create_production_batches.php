<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('item_id')->constrained()->cascadeOnDelete();
            $table->string('batch_code')->unique();
            $table->date('production_date');
            $table->decimal('produced_qty', 12, 3);
            $table->decimal('remaining_qty', 12, 3);
            $table->timestamp('sold_out_at')->nullable();
            $table->string('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('batch_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('production_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('order_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_order_items');
        Schema::dropIfExists('production_batches');
    }
};
