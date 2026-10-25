<?php

namespace App\User\InterfaceServices;

use App\Auth\CurrentUser\CurrentUser;
use App\User\DTOs\Responses\HomeResponse;

interface HomeServiceInterface
{
    public function home(CurrentUser $viewer): HomeResponse;
}
