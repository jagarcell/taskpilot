<?php

namespace App\Enums;

enum IssueType: string
{
    case BUG = 'bug';
    case TASK = 'task';
    case STORY = 'story';
    case EPIC = 'epic';

    /**
     * Get the human-readable label for the issue type.
     *
     * @return string
     * Logic: map the stored enum value to a readable label for display in the UI and reports.
     */
    public function label(): string
    {
        return match ($this) {
            self::BUG => 'Bug',
            self::TASK => 'Task',
            self::STORY => 'Story',
            self::EPIC => 'Epic',
        };
    }
}
