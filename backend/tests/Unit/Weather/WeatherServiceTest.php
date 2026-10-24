<?php

namespace Tests\Unit\Weather;

use App\Weather\DTOs\Requests\WeatherQuery;
use App\Weather\InterfaceRepository\WeatherRepositoryInterface;
use App\Weather\Services\WeatherService;
use Mockery;
use Tests\TestCase;

class WeatherServiceTest extends TestCase
{
    public function test_it_uses_the_default_location_without_coordinates(): void
    {
        $repository = Mockery::mock(WeatherRepositoryInterface::class);
        $repository->shouldReceive('current')
            ->once()
            ->with(33.8938, 35.5018)
            ->andReturn(['temperature_2m' => 18]);

        $response = (new WeatherService($repository))->current(new WeatherQuery);

        $this->assertSame(33.8938, $response->latitude);
        $this->assertSame(35.5018, $response->longitude);
        $this->assertTrue($response->isDefault);
        $this->assertSame(['temperature_2m' => 18], $response->current);
    }

    public function test_it_uses_the_given_coordinates(): void
    {
        $repository = Mockery::mock(WeatherRepositoryInterface::class);
        $repository->shouldReceive('current')->once()->with(40.7, -74.0)->andReturn(null);

        $response = (new WeatherService($repository))->current(new WeatherQuery(40.7, -74.0));

        $this->assertSame(40.7, $response->latitude);
        $this->assertSame(-74.0, $response->longitude);
        $this->assertFalse($response->isDefault);
        $this->assertNull($response->current);
    }
}
