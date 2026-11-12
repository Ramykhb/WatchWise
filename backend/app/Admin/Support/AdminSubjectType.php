<?php

namespace App\Admin\Support;

/** What a staff action was done to. */
enum AdminSubjectType: string
{
    case User = 'user';
    case Review = 'review';
    case Movie = 'movie';
    case Genre = 'genre';
    case Setting = 'setting';
    case Report = 'report';
    case Rating = 'rating';
    case MovieList = 'list';
}
