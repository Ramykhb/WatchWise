<?php

namespace App\Review\InterfaceServices;

interface ReviewModerationServiceInterface
{
    /** @return list<string>|null Labels, or null when classification could not be completed. */
    public function classify(string $text): ?array;
}
