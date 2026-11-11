<?php

namespace Tests\Concerns;

use App\Models\LocalMovie;
use App\Movie\InterfaceServices\MovieCatalogServiceInterface;
use App\Movie\Services\MovieCatalogService;

/**
 * Swaps the catalogue the base test case mocks out for the real one, so hiding and featuring a film works.
 */
trait UsesMovieCatalogue
{
    protected function useRealCatalogue(): void
    {
        $this->app->bind(MovieCatalogServiceInterface::class, MovieCatalogService::class);
    }

    protected function hide(int $tmdbId): LocalMovie
    {
        return LocalMovie::factory()->hidden()->create(['tmdb_id' => $tmdbId]);
    }
}
