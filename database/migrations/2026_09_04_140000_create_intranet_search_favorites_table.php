<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_search_favorites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('favorite_key');
            $table->string('title');
            $table->string('url');
            $table->string('icon');
            $table->string('subtitle')->nullable();
            $table->string('app_identifier');
            $table->string('app_name');
            $table->string('source_key');
            $table->boolean('download')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'favorite_key']);
            $table->index('source_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_search_favorites');
    }
};
