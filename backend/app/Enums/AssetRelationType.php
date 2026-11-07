<?php

namespace App\Enums;

enum AssetRelationType: string
{
    case ConnectedTo = 'connected_to';
    case InstalledIn = 'installed_in';
    case PartOf = 'part_of';
    case DependsOn = 'depends_on';
}
