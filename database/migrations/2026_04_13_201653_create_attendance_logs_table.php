<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create attendance logs table.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table): void {
            $table->id();

            $table->string('site_id', 64);
            $table->string('device_sn', 64);
            $table->unsignedInteger('uid')->nullable();
            $table->string('user_id', 32)->nullable();

            $table->timestamp('timestamp');
            $table->unsignedSmallInteger('punch')->default(0);
            $table->unsignedSmallInteger('status')->default(0);

            $table->timestamp('created_at')->nullable();

            $table->index(['site_id', 'timestamp'], 'attendance_logs_site_timestamp_index');
            $table->index(['site_id', 'device_sn'], 'attendance_logs_site_device_index');
            $table->index(['site_id', 'user_id', 'timestamp'], 'attendance_logs_site_user_timestamp_index');

            $table->unique(
                ['site_id', 'device_sn', 'user_id', 'timestamp', 'punch'],
                'attendance_logs_unique_site_device_user_time_punch'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
