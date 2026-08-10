<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Domain\Tenancy\TenantContext;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Applied to every model that stores customer data.
 *
 * Reads are constrained by {@see TenantScope}; writes have tenant_id stamped
 * on creation so a caller cannot accidentally (or deliberately) write a row
 * into another customer's tenant.
 *
 * @property string $tenant_id
 */
#[ScopedBy(TenantScope::class)]
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::creating(function (self $model): void {
            if ($model->tenant_id !== null) {
                return;
            }

            $model->tenant_id = app(TenantContext::class)->id();
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
