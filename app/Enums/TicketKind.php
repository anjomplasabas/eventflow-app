<?php

namespace App\Enums;

enum TicketKind: string
{
    case Free = 'free';
    case Paid = 'paid';
}
