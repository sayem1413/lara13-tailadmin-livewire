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
        Schema::create('category_product', function (Blueprint $table) {
            // A real pivot model (see App\Models\CategoryProduct), not a
            // bare array pivot - matches the Lifecycle module's shared-
            // relationship pivot schema contract (id, timestamps,
            // deleted_at, created_by) even though Category/Product don't
            // declare a `shared` lifecycle rule (see the Phase 1 plan for
            // why), so it stays adoptable later without a migration.
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // No unique index on the pair, on purpose: with softDeletes,
            // unique(category_id, product_id) would permanently block
            // re-adding a product to a category it was once removed from.
            // Uniqueness is a ProductService::syncCategories() invariant
            // (restore-or-attach, never blind-insert) instead.
            $table->index(['category_id', 'product_id'], 'category_product_pair_index');
            $table->index('product_id', 'category_product_product_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_product');
    }
};
