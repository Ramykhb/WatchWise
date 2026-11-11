<?php

namespace App\Auth\InterfaceServices;

/** Asks Google whether the signed-in account's photo is its own or the generated letter placeholder. */
interface GooglePhotoInterface
{
    /** true = generated placeholder, false = a real photo, null = could not tell (API off or down, or no token). */
    public function isPlaceholder(?string $accessToken): ?bool;
}
