<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The protocol new fasts use, chosen when signing up. Null until
            // the person has chosen one.
            $table->unsignedTinyInteger('fasting_hours')->nullable();
            // "My why": optional, and only the person sees it.
            $table->text('why')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['fasting_hours', 'why']);
        });
    }
};
