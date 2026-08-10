<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cached projection of Entra ID groups and their membership.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entra_groups', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('microsoft_id');

            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->string('mail')->nullable();
            $table->string('mail_nickname')->nullable();

            $table->boolean('security_enabled')->nullable();
            $table->boolean('mail_enabled')->nullable();

            // Graph `groupTypes`, e.g. ["Unified"] or ["DynamicMembership"].
            $table->json('group_types')->nullable();

            // Present only on dynamic groups. Membership of these cannot be
            // edited directly, so the UI must not offer the action.
            $table->text('membership_rule')->nullable();
            $table->string('membership_rule_processing_state', 32)->nullable();

            $table->boolean('on_premises_sync_enabled')->nullable();
            $table->timestamp('created_date_time')->nullable();

            // Count of *user* members only. Nested groups, devices and service
            // principals are not expanded, so this is not the same number the
            // Entra portal shows for a group containing other groups.
            $table->unsignedInteger('member_count')->default(0);

            $table->timestamp('synced_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'microsoft_id']);
            $table->index(['tenant_id', 'display_name']);
        });

        Schema::create('entra_group_memberships', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();

            $table->foreignUlid('entra_group_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('entra_user_id')->constrained()->cascadeOnDelete();

            // Copied so a membership can be resolved without joining, and so
            // the row survives a re-sync that rewrites local ids.
            $table->string('group_microsoft_id');
            $table->string('user_microsoft_id');

            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'entra_group_id', 'entra_user_id'], 'group_membership_unique');
            $table->index(['tenant_id', 'entra_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entra_group_memberships');
        Schema::dropIfExists('entra_groups');
    }
};
