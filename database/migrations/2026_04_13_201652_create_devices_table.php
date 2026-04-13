<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create devices table for attendance device authentication.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table): void {
            $table->id();
            $table->string('site_id', 64);
            $table->string('device_sn', 64);
            $table->string('device_secret', 128);
            $table->string('device_name', 255)->nullable();
            $table->string('device_ip', 45)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['site_id', 'device_sn'],
                'devices_unique_site_device_sn'
            );

            $table->index('site_id', 'devices_site_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
