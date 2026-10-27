<?php

use App\Room\InterfaceServices\RoomServiceInterface;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('rooms:prune-messages', function (RoomServiceInterface $rooms) {
    $this->info("Deleted {$rooms->pruneMessages()} room messages.");
})->purpose('Delete room messages whose 24 hours are up and that no member still has to read');

// Runs with `php artisan schedule:work` locally, or a cron entry calling `schedule:run` every minute on a server.
Schedule::command('rooms:prune-messages')->everyMinute();
