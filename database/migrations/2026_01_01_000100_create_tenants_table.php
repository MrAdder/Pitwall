<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A tenant is one connected customer Microsoft 365 environment.
 *
 * Everything else that holds customer data carries this table's id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('name');
            $table->string('slug')->unique();

            // The Entra directory (tenant) id. One connected directory maps to
            // exactly one tenant record.
            $table->string('microsoft_tenant_id')->unique();

            // Primary verified domain, e.g. contoso.onmicrosoft.com. Display
            // only: never used as an identifier.
            $table->string('default_domain')->nullable();

            // pending  - created, admin consent not yet granted
            // active   - consent granted, Graph reachable
            // degraded - consent granted but recent Graph calls are failing
            // disabled - disconnected by an owner
            $table->string('status', 32)->default('pending')->index();

            $table->timestamp('consented_at')->nullable();
            $table->string('consented_by')->nullable();

            // Set when Graph rejects our credentials or permissions, so the UI
            // can explain why data has stopped refreshing.
            $table->string('connection_error_code')->nullable();
            $table->text('connection_error_message')->nullable();
            $table->timestamp('connection_checked_at')->nullable();

            $table->json('settings')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
