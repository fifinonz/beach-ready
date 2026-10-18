<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('fasting_hours');
            $table->unsignedTinyInteger('eating_hours');
            // All three are UTC instants; see decision 0002.
            $table->timestampTz('started_at');
            $table->timestampTz('target_end_at');
            $table->timestampTz('ended_at')->nullable();
            $table->boolean('ended_early')->default(false);
            $table->string('feeling')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'ended_at']);
        });

        // One active fast per user, enforced by the database so that two
        // simultaneous "start" requests can't both succeed.
        DB::statement('CREATE UNIQUE INDEX fasts_one_active_per_user ON fasts (user_id) WHERE ended_at IS NULL');

        // The 20-hour cap is a product rule, so the database holds it too.
        DB::statement('ALTER TABLE fasts ADD CONSTRAINT fasts_fasting_hours_range CHECK (fasting_hours BETWEEN 1 AND 20)');
    }

    public function down(): void
    {
        Schema::dropIfExists('fasts');
    }
};
