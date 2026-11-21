<?php

namespace App\User\InterfaceServices;

use App\Auth\CurrentUser\CurrentUser;
use App\Models\User;
use App\User\DTOs\Responses\HomeResponse;

interface HomeServiceInterface
{
    /** Latest activity visible to this member, respecting current privacy settings. */
    public function activity(User $viewer): array;

    public function home(CurrentUser $viewer): HomeResponse;
}
