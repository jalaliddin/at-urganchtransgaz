<?php

namespace App\Enums;

enum SoftwareCategory: string
{
    case Os = 'os';
    case Office = 'office';
    case Antivirus = 'antivirus';
    case Browser = 'browser';
    case Security = 'security';
    case Utility = 'utility';
    case Development = 'development';
    case Business = 'business';
    case Other = 'other';
}
