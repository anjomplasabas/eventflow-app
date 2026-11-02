<?php

namespace App\Enums;

enum OrganizerMemberRole: string
{
    case Owner = 'owner';
    case Staff = 'staff';
}
