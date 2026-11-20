<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_user_signatures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained(indexName: 'ius_user_fk')->cascadeOnDelete();
            $table->string('image_path');
            $table->text('signature_data')->nullable();
            $table->foreignId('registered_by_id')->constrained('users', indexName: 'ius_registered_by_fk')->cascadeOnDelete();
            $table->timestamp('registered_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_id')->nullable()->constrained('users', indexName: 'ius_revoked_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at'], 'ius_user_active_idx');
        });

        Schema::create('intranet_user_signature_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained(indexName: 'ius_events_user_fk')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users', indexName: 'ius_events_actor_fk')->cascadeOnDelete();
            $table->string('action');
            $table->string('context')->nullable();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'ius_events_user_idx');
            $table->index('action', 'ius_events_action_idx');
            $table->index(['subject_type', 'subject_id'], 'ius_events_subject_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_user_signature_events');
        Schema::dropIfExists('intranet_user_signatures');
    }
};
