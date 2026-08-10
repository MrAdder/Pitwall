<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Domain\Audit\AuditResult;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * An append-only record of an administrative action.
 *
 * Entries are written through {@see AuditLogger} and are
 * immutable afterwards. The model refuses updates and deletes outright rather
 * than relying on convention, because an audit trail that can be quietly
 * rewritten is not an audit trail.
 *
 * Retention pruning uses a direct query builder delete, bypassing this guard
 * deliberately and in one identifiable place.
 *
 * @property string $tenant_id
 * @property AuditAction $action
 * @property AuditResult $result
 */
class AuditLog extends Model
{
    use BelongsToTenant, HasUlids;

    /**
     * Only created_at is meaningful; an immutable row is never updated.
     */
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'result' => AuditResult::class,
            'previous_state' => 'array',
            'new_state' => 'array',
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new RuntimeException('Audit log entries are append-only and cannot be modified.');
        });

        static::deleting(function (): never {
            throw new RuntimeException('Audit log entries are append-only and cannot be deleted.');
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
