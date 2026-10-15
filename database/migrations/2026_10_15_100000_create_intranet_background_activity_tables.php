<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_background_activity_configs', function (Blueprint $table): void {
            $table->id();
            $table->string('activity_key');
            $table->boolean('enabled')->default(true);
            $table->string('cadence')->nullable();
            $table->unsignedSmallInteger('every')->default(1);
            $table->unsignedSmallInteger('offset')->default(0);
            $table->string('at', 5)->nullable();
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->json('parameters')->nullable();
            $table->timestamps();

            $table->unique('activity_key', 'iba_configs_key_uq');
        });

        Schema::create('intranet_background_activity_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('activity_key');
            $table->string('channel', 32)->nullable();
            $table->string('status', 16);
            $table->string('trigger', 32);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('exception')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['activity_key', 'channel', 'id'], 'iba_runs_key_channel_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_background_activity_runs');
        Schema::dropIfExists('intranet_background_activity_configs');
    }
};
