<?php

namespace App\Enums;

enum AgentRunStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
