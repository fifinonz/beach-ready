<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which reminder types a user wants. The defaults match the Reminders
     * screen. Nothing is emailed until the address is confirmed, whatever
     * these say.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('remind_before_goal')->default(true);
            $table->boolean('remind_goal_reached')->default(true);
            $table->boolean('remind_eating_window_closing')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['remind_before_goal', 'remind_goal_reached', 'remind_eating_window_closing']);
        });
    }
};
