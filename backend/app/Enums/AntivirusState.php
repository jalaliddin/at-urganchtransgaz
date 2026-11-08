<?php

namespace App\Enums;

/** What a report says (the effective status is computed from dates: AntivirusEffective). */
enum AntivirusState: string
{
    case Installed = 'installed';
    case NotInstalled = 'not_installed';
}
