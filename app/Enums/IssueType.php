<?php

namespace App\Enums;

enum IssueType: string
{
    case BUG = 'bug';
    case TASK = 'task';
    case STORY = 'story';
    case EPIC = 'epic';
}
