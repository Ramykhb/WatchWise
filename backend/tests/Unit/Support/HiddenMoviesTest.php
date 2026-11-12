<?php

namespace Tests\Unit\Support;

use App\Support\HiddenMovies;
use PHPUnit\Framework\TestCase;

class HiddenMoviesTest extends TestCase
{
    public function test_it_hides_movies_but_not_tv_shows_with_the_same_id(): void
    {
        $hidden = new HiddenMovies([550, 603]);

        $this->assertTrue($hidden->hides('movie', 550));
        $this->assertFalse($hidden->hides('tv', 550));
        $this->assertFalse($hidden->hides('movie', 1));
    }

    public function test_none_hides_nothing(): void
    {
        $this->assertFalse(HiddenMovies::none()->hides('movie', 550));
    }
}
