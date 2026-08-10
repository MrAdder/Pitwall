<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cached projection of Entra ID users.
 *
 * Microsoft remains the source of truth. This table exists to make search,
 * reporting and dashboards fast, and is refreshed by the sync jobs. Anything
 * read from here is presented alongside its synchronisation timestamp.
 *
 * Columns mirror Microsoft Graph `/users` properties; nothing is derived or
 * invented beyond the two rollups noted below.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entra_users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();

            // Graph `id` (object id). Immutable; the permanent identifier.
            $table->string('microsoft_id');

            $table->string('user_principal_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('given_name')->nullable();
            $table->string('surname')->nullable();
            $table->string('mail')->nullable();

            $table->string('job_title')->nullable();
            $table->string('department')->nullable();
            $table->string('office_location')->nullable();
            $table->string('mobile_phone')->nullable();
            $table->string('employee_id')->nullable();
            $table->string('usage_location', 8)->nullable();

            // Graph `userType`: Member | Guest
            $table->string('user_type', 32)->nullable();

            $table->boolean('account_enabled')->nullable()->index();
            $table->boolean('on_premises_sync_enabled')->nullable();

            $table->timestamp('created_date_time')->nullable();

            // From signInActivity ($select=signInActivity, AuditLog.Read.All).
            // Null means Microsoft returned no activity, not "never signed in".
            $table->timestamp('last_sign_in_at')->nullable();
            $table->timestamp('last_non_interactive_sign_in_at')->nullable();

            // From /reports/authenticationMethods/userRegistrationDetails.
            // Left null until that report has been synchronised.
            $table->boolean('is_mfa_registered')->nullable();
            $table->boolean('is_mfa_capable')->nullable();

            // Rollups maintained by the sync jobs so list views avoid N+1
            // queries. Both are cache, not truth.
            $table->unsignedInteger('assigned_license_count')->default(0);
            $table->unsignedInteger('managed_device_count')->default(0);

            // SKU part numbers of assigned licences, for display and filtering.
            $table->json('assigned_license_skus')->nullable();

            $table->timestamp('synced_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'microsoft_id']);
            $table->index(['tenant_id', 'display_name']);
            $table->index(['tenant_id', 'user_principal_name']);
            $table->index(['tenant_id', 'account_enabled']);
            $table->index(['tenant_id', 'department']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entra_users');
    }
};
