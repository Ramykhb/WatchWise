<?php

namespace App\Setting\Support;

/** How loudly the site announcement is shown. */
enum AnnouncementLevel: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';
}
