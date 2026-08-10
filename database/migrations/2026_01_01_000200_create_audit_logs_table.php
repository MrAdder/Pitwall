<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only record of every administrative action taken through the platform.
 *
 * Rows are written once and never updated or deleted by application code; the
 * model enforces this. Only the scheduled retention prune removes rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();

            // Who acted. Denormalised name/email so the entry stays readable
            // after the platform user is removed.
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('actor_email')->nullable();

            // "user.disable", "device.wipe", "tenant.connect", ...
            $table->string('action', 64)->index();

            // What was acted on: "user", "device", "group", "policy", "tenant".
            $table->string('resource_type', 64)->nullable();

            // Our local id, and the Microsoft id it maps to.
            $table->string('resource_id')->nullable();
            $table->string('resource_microsoft_id')->nullable();
            $table->string('resource_label')->nullable();

            // success | failure | pending | denied
            $table->string('result', 32)->index();

            // Before/after snapshots of only the fields the action touched.
            // Never a full Graph payload, and never anything sensitive.
            $table->json('previous_state')->nullable();
            $table->json('new_state')->nullable();
            $table->json('context')->nullable();

            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();

            // Ties this entry to the Graph request(s) it produced, for support.
            $table->uuid('correlation_id')->index();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            // "web" | "automation" | "console" | "api"
            $table->string('channel', 32)->default('web');

            $table->timestamp('created_at')->index();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'action', 'created_at']);
            $table->index(['tenant_id', 'resource_type', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
