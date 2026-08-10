<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cached membership edge between an Entra group and an Entra user.
 *
 * Only user members are stored. Nested groups, devices and service principals
 * are counted in EntraGroup::$member_count but not expanded, because the MVP's
 * membership views are about people.
 */
class EntraGroupMembership extends Model
{
    use BelongsToTenant, HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'synced_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(EntraGroup::class, 'entra_group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(EntraUser::class, 'entra_user_id');
    }
}
