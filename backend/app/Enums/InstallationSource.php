<?php

namespace App\Enums;

enum InstallationSource: string
{
    case Manual = 'manual';
    case Csv = 'csv';
    case Agent = 'agent';
}
