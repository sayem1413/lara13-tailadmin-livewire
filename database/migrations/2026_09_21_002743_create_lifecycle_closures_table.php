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
        // Shared across every self-referential model that uses
        // HasLifecycleIntegrity - one polymorphic table rather than a
        // migration per model, so adopting the self_referential
        // relationship type never requires a new table. depth 0 is a
        // node's row pointing to itself, used so "is X a descendant of Y"
        // and "collect X's whole subtree" are both single indexed reads
        // instead of a recursive query.
        Schema::create('lifecycle_closures', function (Blueprint $table) {
            $table->id();
            $table->string('closureable_type');
            $table->unsignedBigInteger('ancestor_id');
            $table->unsignedBigInteger('descendant_id');
            $table->unsignedSmallInteger('depth');
            $table->timestamps();

            $table->unique(['closureable_type', 'ancestor_id', 'descendant_id'], 'lifecycle_closures_unique');
            $table->index(['closureable_type', 'descendant_id'], 'lifecycle_closures_descendant_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lifecycle_closures');
    }
};
