<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test-only fixture for the Entity Lifecycle module's own test suite
 * (see docs/lifecycle-integrity.md). A shared (many-to-many) pivot per
 * the module's schema requirements: id, timestamps, deleted_at, and
 * created_by, so severing a link is distinguishable from never having
 * existed, and link creation is auditable - never a bare pivot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lifecycle_demo_category_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lifecycle_demo_category_id')
                ->constrained('lifecycle_demo_categories')
                ->onDelete('restrict');
            $table->foreignId('lifecycle_demo_product_id')
                ->constrained('lifecycle_demo_products')
                ->onDelete('restrict');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lifecycle_demo_category_product');
    }
};
