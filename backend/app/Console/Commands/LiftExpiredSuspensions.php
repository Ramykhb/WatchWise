<?php

namespace App\Console\Commands;

use App\Admin\InterfaceServices\AdminUserToolsServiceInterface;
use Illuminate\Console\Command;

class LiftExpiredSuspensions extends Command
{
    protected $signature = 'admin:lift-suspensions';

    protected $description = 'Reinstate members whose suspension has ended and tell them by email';

    public function handle(AdminUserToolsServiceInterface $tools): int
    {
        $this->info("Reinstated {$tools->liftExpiredSuspensions()} members whose suspension ended.");

        return self::SUCCESS;
    }
}
