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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku');
            $table->string('barcode')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->string('unit', 20)->default('pc');
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();

            $table->decimal('price', 12, 2);
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->decimal('cost_price', 12, 2)->nullable();
            // Null means "use the global ecommerce.tax_rate setting".
            $table->decimal('tax_rate', 5, 2)->nullable();

            $table->boolean('track_inventory')->default(true);
            // Not fillable on the model - only StockService writes this,
            // in the same transaction as a stock_movements row, so
            // stock_quantity === SUM(stock_movements.quantity_change)
            // always holds.
            $table->integer('stock_quantity')->default(0);
            // Null means "use the global ecommerce.low_stock_threshold setting".
            $table->unsignedInteger('low_stock_threshold')->nullable();
            $table->decimal('weight', 10, 3)->nullable();

            // Standard Lifecycle columns (HasActiveStatus, standalone -
            // see Product model). lifecycle_orphaned_at is currently
            // inert (Product declares no `shared` lifecycle rule - see
            // docs/lifecycle-integrity.md and the Phase 1 plan) but kept
            // so the column set matches the documented standard and needs
            // no migration if that's ever revisited.
            $table->string('lifecycle_status')->default('active');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamp('lifecycle_orphaned_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // No unique index on sku/slug - same reasoning as categories.slug.
            $table->index('sku', 'products_sku_index');
            $table->index('barcode', 'products_barcode_index');
            $table->index('slug', 'products_slug_index');
            $table->index('name', 'products_name_index');
            $table->index('lifecycle_status', 'products_lifecycle_status_index');
            $table->index(['track_inventory', 'stock_quantity'], 'products_stock_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
