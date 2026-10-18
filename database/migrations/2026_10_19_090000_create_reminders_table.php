<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fast_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            // A UTC instant; see decision 0002.
            $table->timestampTz('send_at');
            $table->string('status');
            $table->timestampTz('sent_at')->nullable();
            $table->timestamps();

            // Rescheduling updates a fast's reminder of each type in place,
            // so there's never more than one to send.
            $table->unique(['fast_id', 'type']);
        });

        // The scheduler asks for due, pending reminders every minute. This
        // index only holds pending ones, so it stays small as history grows.
        DB::statement("CREATE INDEX reminders_pending_send_at ON reminders (send_at) WHERE status = 'pending'");
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
