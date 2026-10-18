<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Email topics a user has chosen, all off until they turn one on. Nothing
     * is sent for any of them until the address is confirmed
     * (email_verified_at); see decision 0006.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('email_fasting_reminders')->default(false);
            $table->boolean('email_weekly_summary')->default(false);
            $table->boolean('email_product_news')->default(false);
            // When the last confirmation email was sent, for the one-per-60-
            // seconds resend limit and the "only one automatic email" rule.
            $table->timestampTz('confirmation_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_fasting_reminders', 'email_weekly_summary', 'email_product_news', 'confirmation_sent_at']);
        });
    }
};
