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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            // Lifecycle-governed self-FK: the column must be literally
            // "parent_id" for the self_referential rule type, and restrict
            // (not cascade) so a raw delete can never bypass
            // LifecycleIntegrityService's guarded cascade - see
            // docs/lifecycle-integrity.md.
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            // Standard Lifecycle columns - see HasActiveStatus/HasLifecycleIntegrity.
            $table->string('lifecycle_status')->default('active');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamp('lifecycle_orphaned_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // No unique index on slug: it wouldn't understand deleted_at
            // and would permanently burn a trashed category's slug (same
            // reasoning as drop_unique_email_index_from_users_table).
            // Uniqueness among live rows is enforced in the Form
            // Requests/Service instead.
            $table->index('slug', 'categories_slug_index');
            $table->index(['parent_id', 'sort_order'], 'categories_parent_sort_index');
            $table->index('lifecycle_status', 'categories_lifecycle_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
