<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks synchronisation per tenant and resource so the UI can show how fresh
 * cached data is, and so incremental syncs know where to resume.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_states', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();

            // "users" | "groups" | "group_members" | "managed_devices"
            $table->string('resource', 64);

            // idle | running | failed
            $table->string('status', 32)->default('idle')->index();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Last successful completion. Drives the "Last synchronised" label,
            // and is deliberately separate from completed_at so a failed run
            // does not make stale data look fresh.
            $table->timestamp('last_successful_at')->nullable();

            // Graph delta link for resources that support it (users, groups).
            // Opaque and may contain a token, so it is encrypted at rest.
            $table->text('delta_link')->nullable();

            $table->unsignedInteger('records_created')->default(0);
            $table->unsignedInteger('records_updated')->default(0);
            $table->unsignedInteger('records_deleted')->default(0);

            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'resource']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_states');
    }
};
