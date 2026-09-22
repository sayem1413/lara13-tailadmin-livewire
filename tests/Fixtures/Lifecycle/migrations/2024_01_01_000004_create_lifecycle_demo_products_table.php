<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test-only fixture for the Entity Lifecycle module's own test suite
 * (see docs/lifecycle-integrity.md) - loaded only by AppServiceProvider
 * while running unit tests, not part of the application's real schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lifecycle_demo_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('lifecycle_status')->default('active');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamp('lifecycle_orphaned_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lifecycle_demo_products');
    }
};
