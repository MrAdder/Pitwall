<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Domain\Tenancy\Exceptions\TenantContextMissing;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Constrains every query on a tenant-owned model to the current tenant.
 *
 * The failure mode matters more than the happy path: with no tenant resolved
 * this throws instead of quietly returning every tenant's rows. Cross-tenant
 * reads have to be written deliberately via TenantContext::withoutTenant().
 */
final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->isUnscoped()) {
            return;
        }

        if (! $context->has()) {
            throw new TenantContextMissing($model::class);
        }

        $builder->where(
            $model->qualifyColumn('tenant_id'),
            $context->id(),
        );
    }
}
