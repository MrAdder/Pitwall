<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Access\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Grants one platform user access to one tenant, with one role.
 *
 * Deliberately not tenant-scoped: membership has to be queryable before a
 * tenant context exists, since it is what decides whether that context may be
 * established at all.
 *
 * @property string $tenant_id
 * @property string $user_id
 * @property Role $role
 */
#[Fillable(['tenant_id', 'user_id', 'role', 'invited_by', 'accepted_at'])]
class TenantMembership extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'accepted_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
