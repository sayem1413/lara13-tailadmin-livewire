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
        Schema::table('users', function (Blueprint $table) {
            // A plain unique index on "email" doesn't know about
            // "deleted_at" and would still reject an insert at the
            // database layer for an email StoreUserRequest/
            // UpdateUserRequest's Rule::unique()->withoutTrashed() has
            // already decided is reusable (i.e. it only belongs to a
            // soft-deleted row). Laravel's schema builder has no portable
            // way to express a partial/filtered unique index across every
            // supported driver, so uniqueness among non-trashed rows is
            // enforced by that validation rule alone from here on.
            $table->dropUnique(['email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unique('email');
        });
    }
};
