<?php

namespace App\Console\Commands;

use App\Auth\InterfaceServices\RefreshTokenCleanupServiceInterface;
use Illuminate\Console\Command;

class PruneRefreshTokens extends Command
{
    protected $signature = 'auth:prune-refresh-tokens';

    protected $description = 'Delete refresh tokens that expired or were revoked longer ago than the retention period';

    public function handle(RefreshTokenCleanupServiceInterface $cleanup): int
    {
        $this->info("Deleted {$cleanup->pruneStale()} stale refresh tokens.");

        return self::SUCCESS;
    }
}
