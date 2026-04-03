<?php

namespace App\Enums;

enum AppContext: string
{
    case Tenant = 'tenant';
    case Central = 'central';
}
