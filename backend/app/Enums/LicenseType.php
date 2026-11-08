<?php

namespace App\Enums;

enum LicenseType: string
{
    case Freeware = 'freeware';
    case OpenSource = 'open_source';
    case Commercial = 'commercial';
    case Subscription = 'subscription';
    case Oem = 'oem';
}
