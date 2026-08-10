<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AuditLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Enforces the audit retention window.
 *
 * The AuditLog model refuses deletes, which is the point: nothing in the
 * administrative interface can erase history. Retention is a separate, single,
 * auditable place that bypasses the model deliberately via the query builder,
 * and logs how much it removed.
 */
final class PruneAuditLogs implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('maintenance');
    }

    public function handle(): void
    {
        $retentionDays = (int) config('platform.audit.retention_days');

        if ($retentionDays <= 0) {
            return;
        }

        $cutoff = now()->subDays($retentionDays);
        $total = 0;

        // Deleted in batches so a long-lived platform's first prune does not
        // hold a single enormous transaction open.
        do {
            $deleted = DB::table((new AuditLog)->getTable())
                ->where('created_at', '<', $cutoff)
                ->limit(5000)
                ->delete();

            $total += $deleted;
        } while ($deleted > 0);

        if ($total > 0) {
            Log::info('Pruned audit log entries past the retention window.', [
                'deleted' => $total,
                'retention_days' => $retentionDays,
                'cutoff' => $cutoff->toIso8601String(),
            ]);
        }
    }
}
