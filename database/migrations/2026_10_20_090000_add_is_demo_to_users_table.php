<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Demo accounts made by "Try the demo" (decision 0011). They're deleted
     * 24 hours after they're created, so the clean-up looks them up by age.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false);
            $table->index(['is_demo', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_demo', 'created_at']);
            $table->dropColumn('is_demo');
        });
    }
};
