<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Access\Role;
use App\Domain\Tenancy\Exceptions\TenantContextMissing;
use App\Models\EntraUser;
use App\Models\ManagedDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tenant isolation is the single property this platform cannot get wrong: one
 * customer seeing another's directory would be the end of the product.
 *
 * These tests cover the boundary from both directions — that a member of one
 * tenant cannot reach another's data through the API, and that the query layer
 * itself refuses to run unscoped.
 */
final class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_member_of_one_tenant_cannot_read_another_tenants_users(): void
    {
        [$tenantA, $userA] = $this->tenantWithMember();
        [$tenantB] = $this->tenantWithMember();

        $this->asTenant($tenantB, fn () => EntraUser::create([
            'microsoft_id' => 'b-user-1',
            'display_name' => 'Tenant B Person',
            'user_principal_name' => 'person@tenant-b.example',
        ]));

        // Tenant B exists, but this user is not a member. It must be
        // indistinguishable from a tenant that does not exist.
        $this->actingAs($userA)
            ->getJson("/api/tenants/{$tenantB->slug}/users")
            ->assertNotFound();

        $this->actingAs($userA)
            ->getJson("/api/tenants/{$tenantA->slug}/users")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function a_member_cannot_read_another_tenants_device_by_id(): void
    {
        [$tenantA, $userA] = $this->tenantWithMember();
        [$tenantB] = $this->tenantWithMember();

        $device = $this->asTenant($tenantB, fn () => ManagedDevice::create([
            'microsoft_id' => 'b-device-1',
            'device_name' => 'TENANT-B-LAPTOP',
        ]));

        // Guessing another tenant's record id must not be enough: the route
        // resolves the model through the tenant scope, so it is simply absent.
        $this->actingAs($userA)
            ->getJson("/api/tenants/{$tenantA->slug}/devices/{$device->id}")
            ->assertNotFound();
    }

    #[Test]
    public function search_does_not_cross_tenant_boundaries(): void
    {
        [$tenantA, $userA] = $this->tenantWithMember();
        [$tenantB] = $this->tenantWithMember();

        $this->asTenant($tenantB, fn () => EntraUser::create([
            'microsoft_id' => 'b-user-2',
            'display_name' => 'Daniel Green',
            'user_principal_name' => 'daniel@tenant-b.example',
        ]));

        $this->actingAs($userA)
            ->getJson("/api/tenants/{$tenantA->slug}/search?q=Daniel")
            ->assertOk()
            ->assertJsonCount(0, 'data.users');
    }

    #[Test]
    public function tenant_owned_models_refuse_to_query_without_a_tenant(): void
    {
        [$tenant] = $this->tenantWithMember();

        $this->asTenant($tenant, fn () => EntraUser::create([
            'microsoft_id' => 'a-user-1',
            'display_name' => 'Someone',
        ]));

        // The failure mode matters more than the happy path: with no tenant
        // resolved this must throw, not silently return every tenant's rows.
        $this->expectException(TenantContextMissing::class);

        EntraUser::count();
    }

    #[Test]
    public function records_are_stamped_with_the_current_tenant_on_creation(): void
    {
        [$tenant] = $this->tenantWithMember();

        $user = $this->asTenant($tenant, fn () => EntraUser::create([
            'microsoft_id' => 'stamped-1',
            'display_name' => 'Stamped',
        ]));

        $this->assertSame($tenant->id, $user->tenant_id);
    }

    #[Test]
    public function a_user_with_no_membership_cannot_reach_any_tenant(): void
    {
        [$tenant] = $this->tenantWithMember(Role::Owner);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->getJson("/api/tenants/{$tenant->slug}/dashboard")
            ->assertNotFound();
    }

    #[Test]
    public function me_only_lists_tenants_the_caller_belongs_to(): void
    {
        [$tenantA, $userA] = $this->tenantWithMember();
        [$tenantB] = $this->tenantWithMember();

        $response = $this->actingAs($userA)->getJson('/api/me')->assertOk();

        $slugs = array_column($response->json('data.tenants'), 'slug');

        $this->assertContains($tenantA->slug, $slugs);
        $this->assertNotContains($tenantB->slug, $slugs);
    }
}
