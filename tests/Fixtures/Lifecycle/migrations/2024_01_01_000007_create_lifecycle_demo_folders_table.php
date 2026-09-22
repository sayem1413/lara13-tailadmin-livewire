<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test-only fixture for the Entity Lifecycle module's own test suite
 * (see docs/lifecycle-integrity.md, Part D). `parent_id` is a nullable
 * self-FK - the standard, fixed column name self-referential
 * relationships use (unlike exclusive/hierarchical FKs, which are
 * whatever name the model's own belongsTo() declares).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lifecycle_demo_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('lifecycle_demo_folders')->onDelete('restrict');
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
        Schema::dropIfExists('lifecycle_demo_folders');
    }
};
