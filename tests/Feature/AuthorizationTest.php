<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Access\Permission;
use App\Domain\Access\Role;
use App\Models\EntraUser;
use App\Models\ManagedDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Role enforcement.
 *
 * The cases here are the ones from the product's own definition of done: a
 * read-only user cannot change anything, and an operator cannot reach the
 * high-impact operations that are deliberately withheld from that role.
 */
final class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_read_only_user_cannot_disable_an_account(): void
    {
        [$tenant, $user] = $this->tenantWithMember(Role::ReadOnly);

        $entraUser = $this->asTenant($tenant, fn () => EntraUser::create([
            'microsoft_id' => 'user-1',
            'user_principal_name' => 'person@example.com',
            'account_enabled' => true,
        ]));

        $this->actingAs($user)
            ->postJson("/api/tenants/{$tenant->slug}/users/{$entraUser->id}/disable")
            ->assertForbidden();

        $this->assertTrue($entraUser->fresh()->account_enabled);
    }

    #[Test]
    public function a_read_only_user_can_still_read(): void
    {
        [$tenant, $user] = $this->tenantWithMember(Role::ReadOnly);

        $this->actingAs($user)
            ->getJson("/api/tenants/{$tenant->slug}/users")
            ->assertOk();
    }

    #[Test]
    public function a_read_only_user_cannot_read_the_audit_log(): void
    {
        [$tenant, $user] = $this->tenantWithMember(Role::ReadOnly);

        $this->actingAs($user)
            ->getJson("/api/tenants/{$tenant->slug}/audit")
            ->assertForbidden();
    }

    #[Test]
    public function an_auditor_can_read_the_audit_log_but_not_act(): void
    {
        [$tenant, $user] = $this->tenantWithMember(Role::Auditor);

        $device = $this->asTenant($tenant, fn () => ManagedDevice::create([
            'microsoft_id' => 'device-1',
            'device_name' => 'LAPTOP-1',
        ]));

        $this->actingAs($user)
            ->getJson("/api/tenants/{$tenant->slug}/audit")
            ->assertOk();

        $this->actingAs($user)
            ->postJson("/api/tenants/{$tenant->slug}/devices/{$device->id}/sync")
            ->assertForbidden();
    }

    #[Test]
    public function an_operator_is_not_granted_the_destructive_device_permissions(): void
    {
        // Wipe and retire are withheld from Operator on purpose: the help desk
        // needs to sync a machine, not to factory reset one.
        $this->assertFalse(Role::Operator->grants(Permission::DevicesWipe));
        $this->assertFalse(Role::Operator->grants(Permission::DevicesRetire));
        $this->assertFalse(Role::Operator->grants(Permission::PoliciesWrite));

        $this->assertTrue(Role::Operator->grants(Permission::DevicesSync));
    }

    #[Test]
    public function only_an_owner_may_manage_the_tenant_connection_and_membership(): void
    {
        foreach ([Role::Administrator, Role::Operator, Role::Auditor, Role::ReadOnly] as $role) {
            $this->assertFalse($role->grants(Permission::TenantManage), $role->value.' must not manage the tenant');
            $this->assertFalse($role->grants(Permission::MembersManage), $role->value.' must not manage members');
        }

        $this->assertTrue(Role::Owner->grants(Permission::TenantManage));
        $this->assertTrue(Role::Owner->grants(Permission::MembersManage));
    }

    #[Test]
    public function every_high_impact_permission_is_withheld_from_read_only_and_auditor(): void
    {
        foreach (Permission::highImpact() as $permission) {
            $this->assertFalse(Role::ReadOnly->grants($permission), "Read Only must not hold {$permission->value}");
            $this->assertFalse(Role::Auditor->grants($permission), "Auditor must not hold {$permission->value}");
        }
    }

    #[Test]
    public function an_unauthenticated_request_is_rejected(): void
    {
        [$tenant] = $this->tenantWithMember();

        $this->getJson("/api/tenants/{$tenant->slug}/users")->assertUnauthorized();
    }
}
