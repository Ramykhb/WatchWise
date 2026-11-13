<?php

namespace App\Setting\Support;

/** The shape of a setting's value. */
enum SettingType: string
{
    case Boolean = 'boolean';
    case Object = 'object';
}
