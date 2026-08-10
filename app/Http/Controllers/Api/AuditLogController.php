<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditResult;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Reading the audit trail.
 *
 * Read-only by design: there is no endpoint to write or amend an entry, and the
 * model refuses both. Entries are created only as a side effect of the actions
 * they describe.
 */
final class AuditLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'action' => ['sometimes', Rule::enum(AuditAction::class)],
            'result' => ['sometimes', Rule::enum(AuditResult::class)],
            'actor_id' => ['sometimes', 'string', 'max:26'],
            'resource_type' => ['sometimes', 'string', 'max:64'],
            'resource_id' => ['sometimes', 'string', 'max:255'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ]);

        $logs = AuditLog::query()
            ->with('actor')
            ->when($filters['action'] ?? null, fn ($query, string $a) => $query->where('action', $a))
            ->when($filters['result'] ?? null, fn ($query, string $r) => $query->where('result', $r))
            ->when($filters['actor_id'] ?? null, fn ($query, string $id) => $query->where('actor_id', $id))
            ->when($filters['resource_type'] ?? null, fn ($query, string $t) => $query->where('resource_type', $t))
            ->when($filters['resource_id'] ?? null, fn ($query, string $id) => $query->where('resource_id', $id))
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->where('created_at', '<=', $to))
            ->latest('created_at')
            ->paginate($filters['per_page'] ?? 50)
            ->withQueryString();

        return AuditLogResource::collection($logs);
    }
}
