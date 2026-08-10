<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cached projection of Intune managed devices.
 *
 * Columns mirror Microsoft Graph `deviceManagement/managedDevices` properties.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('managed_devices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();

            // Graph managedDevice `id`.
            $table->string('microsoft_id');

            // Graph `azureADDeviceId`: links this to the Entra device object.
            $table->string('azure_ad_device_id')->nullable()->index();

            $table->string('device_name')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();

            $table->string('operating_system', 64)->nullable();
            $table->string('os_version', 64)->nullable();

            // compliant | noncompliant | conflict | error | inGracePeriod
            // | configManager | unknown
            $table->string('compliance_state', 32)->nullable();
            $table->timestamp('compliance_grace_period_expires_at')->nullable();

            // company | personal | unknown
            $table->string('owner_type', 32)->nullable();

            // mdm | intuneClient | configurationManagerClient | eas | ...
            $table->string('management_agent', 64)->nullable();
            $table->string('enrollment_type', 64)->nullable();
            $table->string('registration_state', 32)->nullable();

            $table->boolean('is_encrypted')->nullable();
            $table->boolean('is_supervised')->nullable();
            $table->boolean('jail_broken')->nullable();

            // Primary user. The Graph `userId` is an Entra object id, so it
            // joins to entra_users.microsoft_id.
            $table->string('user_microsoft_id')->nullable()->index();
            $table->string('user_principal_name')->nullable();
            $table->foreignUlid('entra_user_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamp('last_sync_date_time')->nullable();
            $table->timestamp('enrolled_date_time')->nullable();

            $table->unsignedBigInteger('total_storage_bytes')->nullable();
            $table->unsignedBigInteger('free_storage_bytes')->nullable();

            $table->string('wifi_mac_address', 64)->nullable();
            $table->string('ethernet_mac_address', 64)->nullable();

            $table->timestamp('synced_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'microsoft_id']);
            $table->index(['tenant_id', 'device_name']);
            $table->index(['tenant_id', 'compliance_state']);
            $table->index(['tenant_id', 'operating_system']);
            $table->index(['tenant_id', 'last_sync_date_time']);
            $table->index(['tenant_id', 'serial_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('managed_devices');
    }
};
