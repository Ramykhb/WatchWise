<?php

namespace App\MovieRecommendation\InterfaceServices;

use App\Models\User;
use App\MovieRecommendation\DTOs\Responses\TasteProfileResponse;

interface TasteProfileServiceInterface
{
    public function forUser(User $user): TasteProfileResponse;
}
