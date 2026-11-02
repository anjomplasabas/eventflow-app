<?php

namespace App\Enums;

enum UserRole: string
{
    case SystemAdmin = 'system_admin';
    case OrganizerOwner = 'organizer_owner';
    case OrganizerStaff = 'organizer_staff';
    case Attendee = 'attendee';
}
