<?php

namespace Tests;

use App\Movie\InterfaceServices\MovieCatalogServiceInterface;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Watchlist, rating and list actions remember the film from TMDB; no test should reach it by accident.
        $this->mock(MovieCatalogServiceInterface::class)->shouldIgnoreMissing();
    }
}
