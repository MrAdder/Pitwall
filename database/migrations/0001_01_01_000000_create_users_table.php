<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform administrators.
 *
 * These are the people who sign in to this application, not the Entra ID users
 * they manage (those live in `entra_users` and are tenant-owned).
 *
 * Authentication is delegated to Microsoft Entra ID via OpenID Connect, so no
 * password is ever set. The column is retained only because the framework's
 * auth plumbing expects it, and is nullable so a null password can never
 * accidentally verify.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();

            // The `oid` claim from the Microsoft id token: immutable, and the
            // only safe permanent identifier. Email addresses change.
            $table->string('microsoft_object_id')->nullable()->unique();

            // The `tid` claim: the Entra tenant this person signs in from.
            $table->string('microsoft_tenant_id')->nullable()->index();

            $table->string('password')->nullable();

            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUlid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
