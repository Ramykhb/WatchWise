<?php

namespace App\Console\Commands;

use App\User\InterfaceServices\PendingAccountCleanupServiceInterface;
use Illuminate\Console\Command;

class PrunePendingAccountData extends Command
{
    protected $signature = 'users:prune-pending';

    protected $description = 'Clear pending email changes and account deletion requests whose links have expired';

    public function handle(PendingAccountCleanupServiceInterface $cleanup): int
    {
        $pruned = $cleanup->pruneExpired();

        $this->info("Cleared {$pruned->emailChanges} expired pending email changes.");
        $this->info("Cleared {$pruned->deletionRequests} expired account deletion requests.");

        return self::SUCCESS;
    }
}
