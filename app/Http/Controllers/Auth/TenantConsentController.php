<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Access\Role;
use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditEntry;
use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\MicrosoftOidc;
use App\Domain\Tenancy\TenantContext;
use App\Domain\Tenancy\TenantStatus;
use App\Http\Controllers\Controller;
use App\Integrations\MicrosoftGraph\Entra\OrganizationResource;
use App\Integrations\MicrosoftGraph\GraphClient\Exceptions\GraphException;
use App\Integrations\MicrosoftGraph\GraphClientFactory;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Connecting a customer Microsoft 365 tenant.
 *
 * A Global Administrator of the customer directory grants admin consent to
 * this platform's application registration. Only after that can application
 * permissions be used against their directory.
 *
 * The tenant record is created from the directory id Microsoft returns and
 * verified against Graph, not from anything the person typed. The name and
 * primary domain are read back from `/organization`, so a connected tenant
 * always shows what Microsoft says it is.
 */
final class TenantConsentController extends Controller
{
    public function __construct(
        private readonly MicrosoftOidc $oidc,
        private readonly GraphClientFactory $graph,
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Send the administrator to Microsoft's admin consent screen.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $state = MicrosoftOidc::randomValue();

        $request->session()->put('microsoft_consent_state', $state);

        return redirect()->away($this->oidc->adminConsentUrl($state));
    }

    /**
     * Handle the redirect back after consent.
     *
     * Microsoft returns `admin_consent`, `tenant` and our `state`.
     */
    public function callback(Request $request): RedirectResponse
    {
        $expectedState = $request->session()->pull('microsoft_consent_state');

        if (! is_string($expectedState) || ! hash_equals($expectedState, (string) $request->query('state'))) {
            return redirect('/tenants?error=consent_state_mismatch');
        }

        if ($request->query('admin_consent') !== 'True') {
            // The administrator declined, or lacked the directory role needed
            // to consent on the tenant's behalf.
            return redirect('/tenants?error=consent_declined');
        }

        $microsoftTenantId = (string) $request->query('tenant');

        if (! Str::isUuid($microsoftTenantId)) {
            return redirect('/tenants?error=consent_invalid_tenant');
        }

        /** @var User $user */
        $user = $request->user();

        $tenant = $this->connect($microsoftTenantId, $user);

        if ($tenant === null) {
            return redirect('/tenants?error=consent_verification_failed');
        }

        return redirect('/t/'.$tenant->slug);
    }

    /**
     * Create or reactivate the tenant, then confirm we can actually reach it.
     *
     * Consent propagates asynchronously inside Microsoft, so the verifying
     * Graph call can fail for a short period after a genuine consent. The
     * tenant is left pending in that case rather than being marked broken, and
     * the administrator is told to retry.
     */
    private function connect(string $microsoftTenantId, User $user): ?Tenant
    {
        $tenant = $this->tenantContext->withoutTenant(
            fn () => Tenant::withTrashed()->firstOrNew(['microsoft_tenant_id' => $microsoftTenantId]),
        );

        if (! $tenant->exists) {
            $tenant->fill([
                // Placeholder until Graph tells us the real name below.
                'name' => 'Microsoft 365 tenant',
                'slug' => Str::lower(Str::ulid()->toBase32()),
                'status' => TenantStatus::Pending,
            ]);
        }

        $tenant->deleted_at = null;
        $tenant->consented_at = now();
        $tenant->consented_by = $user->email;
        $tenant->save();

        try {
            $profile = $this->graph->organization($tenant)->profile();
        } catch (GraphException $e) {
            Log::warning('Tenant consent verification failed.', [
                'tenant_id' => $tenant->id,
                ...$e->context(),
            ]);

            $tenant->forceFill([
                'status' => TenantStatus::Pending,
                'connection_error_code' => $e->graphErrorCode(),
                'connection_error_message' => $e->userMessage(),
                'connection_checked_at' => now(),
            ])->save();

            return null;
        }

        DB::transaction(function () use ($tenant, $profile, $user): void {
            $name = (string) ($profile['displayName'] ?? '');

            $tenant->forceFill([
                'name' => $name !== '' ? $name : $tenant->name,
                'slug' => $this->uniqueSlug($name !== '' ? $name : $tenant->microsoft_tenant_id, $tenant),
                'default_domain' => OrganizationResource::defaultDomain($profile),
                'status' => TenantStatus::Active,
                'connection_error_code' => null,
                'connection_error_message' => null,
                'connection_checked_at' => now(),
            ])->save();

            // Whoever connects the tenant becomes its Owner. Existing
            // memberships are left alone: reconnecting must not quietly
            // promote someone or wipe the team's roles.
            TenantMembership::firstOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $user->id],
                ['role' => Role::Owner, 'accepted_at' => now()],
            );
        });

        $this->tenantContext->run($tenant, function () use ($tenant, $user): void {
            $this->audit->log(new AuditEntry(
                action: AuditAction::TenantConnected,
                resourceType: 'tenant',
                resourceId: $tenant->id,
                resourceMicrosoftId: $tenant->microsoft_tenant_id,
                resourceLabel: $tenant->name,
                newState: ['status' => TenantStatus::Active->value],
                tenant: $tenant,
                actor: $user,
            ));
        });

        return $tenant->fresh();
    }

    private function uniqueSlug(string $source, Tenant $tenant): string
    {
        $base = Str::slug($source) ?: 'tenant';
        $slug = $base;
        $suffix = 1;

        while ($this->tenantContext->withoutTenant(
            fn (): bool => Tenant::withTrashed()
                ->where('slug', $slug)
                ->whereKeyNot($tenant->getKey())
                ->exists(),
        )) {
            $slug = $base.'-'.++$suffix;
        }

        return $slug;
    }
}
