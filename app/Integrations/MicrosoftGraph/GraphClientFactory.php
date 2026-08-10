<?php

declare(strict_types=1);

namespace App\Integrations\MicrosoftGraph;

use App\Domain\Tenancy\TenantContext;
use App\Integrations\MicrosoftGraph\Authentication\TokenProvider;
use App\Integrations\MicrosoftGraph\Entra\GroupsResource;
use App\Integrations\MicrosoftGraph\Entra\OrganizationResource;
use App\Integrations\MicrosoftGraph\Entra\ReportsResource;
use App\Integrations\MicrosoftGraph\Entra\UsersResource;
use App\Integrations\MicrosoftGraph\GraphClient\GraphClient;
use App\Integrations\MicrosoftGraph\Intune\ManagedDevicesResource;
use App\Models\Tenant;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * Builds tenant-bound Graph clients and resource services.
 *
 * Callers ask for the resource they need for a tenant. Nothing constructs a
 * GraphClient directly, which is what keeps the "one client, one tenant"
 * guarantee true.
 */
final class GraphClientFactory
{
    /** @var array<string, GraphClient> */
    private array $clients = [];

    public function __construct(
        private readonly TokenProvider $tokens,
        private readonly HttpFactory $http,
        private readonly TenantContext $tenantContext,
    ) {}

    public function for(Tenant $tenant): GraphClient
    {
        return $this->clients[$tenant->id] ??= new GraphClient($tenant, $this->tokens, $this->http);
    }

    /**
     * A client for the tenant of the current request or job.
     */
    public function forCurrentTenant(): GraphClient
    {
        return $this->for($this->tenantContext->tenant());
    }

    public function users(?Tenant $tenant = null): UsersResource
    {
        return new UsersResource($this->resolve($tenant));
    }

    public function groups(?Tenant $tenant = null): GroupsResource
    {
        return new GroupsResource($this->resolve($tenant));
    }

    public function managedDevices(?Tenant $tenant = null): ManagedDevicesResource
    {
        return new ManagedDevicesResource($this->resolve($tenant));
    }

    public function organization(?Tenant $tenant = null): OrganizationResource
    {
        return new OrganizationResource($this->resolve($tenant));
    }

    public function reports(?Tenant $tenant = null): ReportsResource
    {
        return new ReportsResource($this->resolve($tenant));
    }

    private function resolve(?Tenant $tenant): GraphClient
    {
        return $tenant instanceof Tenant
            ? $this->for($tenant)
            : $this->forCurrentTenant();
    }
}
