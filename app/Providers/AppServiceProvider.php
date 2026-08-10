<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Access\Permission;
use App\Domain\Tenancy\TenantContext;
use App\Integrations\MicrosoftGraph\Authentication\ClientCredentialsTokenProvider;
use App\Integrations\MicrosoftGraph\Authentication\TokenProvider;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One tenant context per request/job. Everything tenant-scoped reads
        // from this single instance.
        $this->app->singleton(TenantContext::class);

        $this->app->bind(TokenProvider::class, ClientCredentialsTokenProvider::class);
    }

    public function boot(): void
    {
        $this->configureModels();
        $this->configureAuthorization();
    }

    private function configureModels(): void
    {
        // Fail on accessing a relationship that was not loaded, rather than
        // silently issuing N+1 queries against tables that will hold every
        // user and device of every customer.
        Model::preventLazyLoading(! $this->app->isProduction());

        // Assigning an attribute that does not exist is a bug, not something
        // to discard quietly.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Model::unguard(false);
    }

    /**
     * Registers one Gate ability per permission.
     *
     * Every ability is tenant-relative: the tenant is taken from the current
     * context rather than passed in, so a controller cannot accidentally
     * authorise against the wrong one.
     */
    private function configureAuthorization(): void
    {
        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, function (User $user, ?Tenant $tenant = null) use ($permission): bool {
                $tenant ??= app(TenantContext::class)->current();

                if (! $tenant instanceof Tenant) {
                    return false;
                }

                return $user->hasPermissionFor($tenant, $permission);
            });
        }
    }
}
