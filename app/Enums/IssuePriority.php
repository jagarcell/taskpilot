<?php

namespace App\Enums;

enum IssuePriority: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case URGENT = 'urgent';

    /**
     * Get the human-readable label for the issue priority.
     *
     * @return string
     * Logic: map the stored enum value to a readable label for the UI and issue summaries.
     */
    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Low',
            self::MEDIUM => 'Medium',
            self::HIGH => 'High',
            self::URGENT => 'Urgent',
        };
    }
}
