<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intranet_background_activity_runs', function (Blueprint $table): void {
            $table->unsignedInteger('deliveries')->default(1)->after('trigger');
            $table->timestamp('bucket_at')->nullable()->after('deliveries');
            $table->unique(['activity_key', 'channel', 'status', 'bucket_at'], 'iba_runs_bucket_uq');
        });
    }

    public function down(): void
    {
        Schema::table('intranet_background_activity_runs', function (Blueprint $table): void {
            $table->dropUnique('iba_runs_bucket_uq');
            $table->dropColumn(['deliveries', 'bucket_at']);
        });
    }
};
