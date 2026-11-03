<?php

namespace Tests;

use App\Movie\InterfaceServices\MovieCatalogServiceInterface;
use App\Notification\Events\NotificationPushed;
use App\Notification\Events\NotificationsChanged;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Event;

abstract class TestCase extends BaseTestCase
{
    /**
     * Pushed over Reverb by follows, rooms, movie nights and reviews. A test that fakes its own events has to
     * list these again, since a new fake replaces this one.
     */
    protected const NOTIFICATION_EVENTS = [NotificationPushed::class, NotificationsChanged::class];

    protected function setUp(): void
    {
        parent::setUp();

        // Watchlist, rating and list actions remember the film from TMDB; no test should reach it by accident.
        $this->mock(MovieCatalogServiceInterface::class)->shouldIgnoreMissing();

        // Likewise no test should reach a socket server.
        Event::fake(self::NOTIFICATION_EVENTS);
    }
}
