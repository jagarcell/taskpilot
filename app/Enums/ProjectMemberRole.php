<?php

namespace App\Enums;

enum ProjectMemberRole: string
{
    case MEMBER = 'member';
    case OWNER = 'owner';
}
