<?php

declare(strict_types=1);

namespace App\Domain\Sync;

enum SyncStatus: string
{
    case Idle = 'idle';
    case Running = 'running';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Idle => 'Idle',
            self::Running => 'Synchronising',
            self::Failed => 'Failed',
        };
    }
}
