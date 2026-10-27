<?php

namespace App\Cinema\InterfaceServices;

use App\Cinema\DTOs\Requests\NearbyCinemasQuery;
use App\Cinema\DTOs\Responses\CinemaResponse;

interface CinemaServiceInterface
{
    /** @return list<CinemaResponse> */
    public function nearby(NearbyCinemasQuery $query): array;
}
