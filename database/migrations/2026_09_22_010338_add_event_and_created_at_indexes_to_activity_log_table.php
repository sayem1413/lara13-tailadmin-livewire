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
        Schema::table('activity_log', function (Blueprint $table) {
            // ActivityLogRepository::paginate() filters on 'event' directly
            // and always orders by 'created_at' (the default ->latest()),
            // both previously unindexed - a full table scan/sort on every
            // page load once this table has any real volume.
            $table->index('event');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['event']);
            $table->dropIndex(['created_at']);
        });
    }
};
