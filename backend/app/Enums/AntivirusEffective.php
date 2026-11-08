<?php

namespace App\Enums;

/** Spec §5.5 statuses: o'rnatilgan, yangilangan, eskirgan, o'rnatilmagan, noma'lum. */
enum AntivirusEffective: string
{
    case Updated = 'updated';
    case Installed = 'installed';
    case Outdated = 'outdated';
    case NotInstalled = 'not_installed';
    case Unknown = 'unknown';

    /** Some antivirus is present on the device. */
    public function isProtected(): bool
    {
        return in_array($this, [self::Updated, self::Installed, self::Outdated], true);
    }
}
