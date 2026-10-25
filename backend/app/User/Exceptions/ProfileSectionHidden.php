<?php

namespace App\User\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/** The owner hides who follows them (and who they follow) from this viewer. Answers 403, like abort(403). */
class ProfileSectionHidden extends HttpException
{
    public function __construct()
    {
        parent::__construct(403);
    }
}
