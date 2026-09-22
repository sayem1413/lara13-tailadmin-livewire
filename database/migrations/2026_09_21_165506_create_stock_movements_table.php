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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('type', 30);
            // Signed: +50 received, -3 sold. quantity_after is the
            // running balance snapshot at the time of this movement -
            // together with product_id/created_at this makes the ledger
            // independently auditable without recomputing from scratch.
            $table->integer('quantity_change');
            $table->integer('quantity_after');
            $table->string('reason')->nullable();
            // Phase 2 will point this at Order/OrderItem.
            $table->nullableMorphs('reference');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // No softDeletes(): movements are immutable audit rows. A
            // mistake is corrected with a compensating movement, never an
            // edit or a delete.

            $table->index(['product_id', 'created_at'], 'stock_movements_product_created_index');
            $table->index('type', 'stock_movements_type_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
