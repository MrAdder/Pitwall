<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Exceptions;

use RuntimeException;

/**
 * Thrown when tenant-owned data is touched without a resolved tenant.
 *
 * This is always a programming error, never a user error: it means a query
 * that should have been constrained to one customer was about to run across
 * all of them. It is not caught and rendered as a friendly message.
 */
final class TenantContextMissing extends RuntimeException
{
    public function __construct(?string $model = null)
    {
        parent::__construct($model === null
            ? 'No tenant is set for the current request. Tenant-owned data cannot be queried.'
            : sprintf('No tenant is set for the current request. [%s] is tenant-owned and cannot be queried.', $model));
    }
}
