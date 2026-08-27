<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_user_tour_completions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tour_key');
            $table->string('status');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('remind_after')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tour_key']);
            $table->index('tour_key');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_user_tour_completions');
    }
};
