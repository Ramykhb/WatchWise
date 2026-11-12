<?php

namespace App\Notification\Support;

/** What staff did to a member's list, as the `list_restricted` notification stores it. Never rename a value, rows keep it. */
enum ListRestriction: string
{
    case MadePrivate = 'made_private';
    case ItemRemoved = 'item_removed';
    case Deleted = 'deleted';
}
