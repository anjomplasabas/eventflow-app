<?php

namespace App\Enums;

enum CheckInMethod: string
{
    case Qr = 'qr';
    case Manual = 'manual';
}
